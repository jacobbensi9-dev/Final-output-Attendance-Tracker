<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin']) || !isset($_SESSION['adviser_id'])) {
    echo json_encode([]);
    exit();
}

include 'connection.php';

$current_adviser_id = (int)$_SESSION['adviser_id'];
$section = isset($_GET['section']) ? mysqli_real_escape_string($conn, $_GET['section']) : '';
$today = date('Y-m-d');

if (empty($section)) {
    echo json_encode([]);
    exit();
}

$query = mysqli_query($conn, "
    SELECT s.first_name, s.last_name, a.timestamp 
    FROM attendance a 
    JOIN student s ON a.student_id = s.id 
    WHERE a.date = '$today' 
      AND s.section = '$section' 
      AND s.adviser_id = $current_adviser_id 
    ORDER BY a.timestamp DESC 
    LIMIT 10
");

$scans = [];
while ($row = mysqli_fetch_assoc($query)) {
    $scans[] = [
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'scan_time' => date('h:i:s A', strtotime($row['timestamp']))
    ];
}

echo json_encode($scans);
exit();
?>