<?php
session_start();
include 'connection.php'; //

header('Content-Type: application/json');

function json_response($status, $message) {
    echo json_encode([
        'status' => $status,
        'message' => $message
    ]);
    exit();
}

/**
 * Function to send SMS via TextBee Free Android Gateway API
 */
function sendSMSNotification($mobileNumber, $messageText) {
    $api_url = "https://api.textbee.dev/api/v1/gateway/send-sms"; 
    $api_key = "txb_TBitv6W3LaZovqoV4WbKZvtsrOpkfoxX"; // Your TextBee API Key

    // Format local Philippine number (09XXXXXXXXX) to international format (+639XXXXXXXXX)
    $formatted_number = trim($mobileNumber);
    if (strpos($formatted_number, '09') === 0) {
        $formatted_number = '+63' . substr($formatted_number, 1);
    }

    $data = [
        'recipients' => [$formatted_number],
        'message' => $messageText
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . $api_key
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return $response;
}

/*
 * Attendance rules:
 * 1. Time-In is allowed from the schedule start until start + 10 minutes.
 * 2. A second scan records Time-Out.
 * 3. The class schedule end_time is the official class end.
 * 4. Students with Time-In but NO Time-Out are given a 5-minute grace period
 *    after end_time. After that deadline, their attendance row is deleted so
 *    the existing dashboard logic will correctly count them as Absent.
 * 5. A Time-In attempted after the start-time cutoff is NOT inserted as an
 *    "Absent" attendance row. This is important because dashboard.php treats
 *    students with an attendance row as present.
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response('error', 'Invalid request method.');
}

/*
 * Background cleanup request.
 * dashboard.php can call:
 *   POST process_qr.php
 *   action=finalize
 */
if (isset($_POST['action']) && $_POST['action'] === 'finalize') {
    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    $cleanup_query = mysqli_query($conn, "
        SELECT
            a.id,
            a.time_in,
            a.time_out,
            sec.end_time
        FROM attendance a
        INNER JOIN student s ON a.student_id = s.id
        INNER JOIN section sec ON s.section = sec.section_name AND s.adviser_id = sec.adviser_id
        WHERE a.date = '$current_date'
          AND a.time_out IS NULL
    ");

    $removed_count = 0;

    if ($cleanup_query && mysqli_num_rows($cleanup_query) > 0) {
        while ($row = mysqli_fetch_assoc($cleanup_query)) {
            $end_time = $row['end_time']; 
            $grace_deadline = date('H:i:s', strtotime($end_time . ' + 5 minutes'));

            if ($current_time > $grace_deadline) {
                $att_id = (int)$row['id'];
                mysqli_query($conn, "DELETE FROM attendance WHERE id = $att_id");
                $removed_count++;
            }
        }
    }

    json_response('success', "Removed $removed_count incomplete attendance record(s).");
}

// Ensure the adviser is logged in for manual QR processing
if (!isset($_SESSION['admin']) || !isset($_SESSION['adviser_id'])) {
    json_response('error', 'Unauthorized access.');
}

$current_adviser_id = (int)$_SESSION['adviser_id'];

if (!isset($_POST['student_id']) || empty(trim($_POST['student_id']))) {
    json_response('error', 'No student ID provided.');
}

$student_id = (int)$_POST['student_id'];

// Verify student exists and belongs to this adviser (using correct parent_number column)
$student_q = mysqli_query($conn, "SELECT * FROM student WHERE id = $student_id AND adviser_id = $current_adviser_id");
if (!mysqli_num_rows($student_q)) {
    json_response('error', 'Student ID not found in your registry.');
}

$student = mysqli_fetch_assoc($student_q);
$section_name = $student['section'];
$parent_number = $student['parent_number'] ?? ''; // Mapped to correct database column
$student_fullname = $student['first_name'] . ' ' . $student['last_name'];

// Fetch section schedule
$sec_q = mysqli_query($conn, "SELECT start_time, end_time FROM section WHERE section_name = '$section_name' AND adviser_id = $current_adviser_id");
if (!mysqli_num_rows($sec_q)) {
    json_response('error', 'Section schedule configuration not found.');
}

$section_info = mysqli_fetch_assoc($sec_q);
$sched_start = $section_info['start_time']; 
$sched_end = $section_info['end_time'];     

$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$formatted_time = date('h:i A', strtotime($current_time));

// Check if attendance row already exists for today
$att_check = mysqli_query($conn, "SELECT * FROM attendance WHERE student_id = $student_id AND date = '$current_date'");

if (mysqli_num_rows($att_check) > 0) {
    // Attendance row exists: Handle Time-Out
    $att_row = mysqli_fetch_assoc($att_check);

    if (!empty($att_row['time_out'])) {
        json_response('info', "$student_fullname has already completed attendance today.");
    }

    // Record Time-Out
    mysqli_query($conn, "UPDATE attendance SET time_out = '$current_time' WHERE id = {$att_row['id']}");

    // Send SMS Notification for Time-Out if parent number is available
    if (!empty($parent_number)) {
        $sms_message = "School Alert: Your child, $student_fullname, has logged Time-Out at $formatted_time. [AUTOMATED MESSAGE - DO NOT REPLY]. For concerns, please call the school office.";
        sendSMSNotification($parent_number, $sms_message);
    }

    json_response('success', "Time-Out recorded successfully for $student_fullname.");
} else {
    // No attendance row yet: Handle Time-In
    $start_ts = strtotime($sched_start);
    $cutoff_ts = $start_ts + (10 * 60); // 10 minutes limit
    $now_ts = strtotime($current_time);

    if ($now_ts > $cutoff_ts) {
        json_response('error', "Time-In rejected: Past the 10-minute limit for $student_fullname.");
    }

    $status = 'Present';
    if ($now_ts > $start_ts + 60) {
        $status = 'Late/Present';
    }

    mysqli_query($conn, "INSERT INTO attendance (student_id, date, time_in, status) VALUES ($student_id, '$current_date', '$current_time', '$status')");

    // Send SMS Notification for Time-In if parent number is available
    if (!empty($parent_number)) {
        $sms_message = "School Alert: Your child, $student_fullname, has successfully logged Time-In ($status) at $formatted_time. [AUTOMATED MESSAGE - DO NOT REPLY]. For concerns, please call the school office.";
        sendSMSNotification($parent_number, $sms_message);
    }

    json_response('success', "Time-In recorded as '$status' for $student_fullname.");
}
?>
```[cite: 1]

***

Is there anything else you need to add or change in your attendance system before it's completely finished?