<?php
include 'db.php'; // Your database connection file

if(isset($_POST['submit_attendance'])) {
    $date = date('Y-m-d'); // Today's date
    
    // Loop through each student marked in the dashboard
    if(isset($_POST['status'])) {
        foreach($_POST['status'] as $student_id => $status) {
            $sid = (int)$student_id;
            $s = mysqli_real_escape_string($conn, $status);
            
            // Insert status, or update if it already exists for this date
            $sql = "INSERT INTO attendance (student_id, status, date) 
                    VALUES ($sid, '$s', '$date') 
                    ON DUPLICATE KEY UPDATE status='$s'";
            
            mysqli_query($conn, $sql);
        }
    }
    
    // Redirect to the history page to view the saved list
    header("Location: history.php?date=$date");
    exit();
}
?>