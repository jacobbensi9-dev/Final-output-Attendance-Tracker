<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['admin']) || !isset($_SESSION['adviser_id'])) {
    header("Location: index.php");
    exit();
}

$current_adviser_id = (int)$_SESSION['adviser_id'];

if (isset($_GET['id'])) {
    $attendance_id = (int)$_GET['id'];

    // Ensure the attendance log belongs to a student under the logged-in adviser before deleting
    $check_query = mysqli_query($conn, "
        SELECT a.id 
        FROM attendance a 
        JOIN student s ON a.student_id = s.id 
        WHERE a.id = $attendance_id AND s.adviser_id = $current_adviser_id
    ");

    if (mysqli_num_rows($check_query) > 0) {
        mysqli_query($conn, "DELETE FROM attendance WHERE id = $attendance_id");
    }
}

header("Location: dashboard.php");
exit();
?>