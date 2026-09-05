<?php
session_start();
include 'connection.php'; // Updated to match your connection file name[cite: 2]

// Ensure the user is logged in as an adviser/admin[cite: 2]
if (!isset($_SESSION['admin']) || !isset($_SESSION['adviser_id'])) {
    header("Location: index.php");
    exit();
}

$current_adviser_id = (int)$_SESSION['adviser_id'];

if(isset($_POST['save_student']))
{
    // Capture input fields safely[cite: 2]
    $last_name      = mysqli_real_escape_string($conn, $_POST['last_name']);
    $first_name     = mysqli_real_escape_string($conn, $_POST['first_name']);
    $middle_initial = isset($_POST['middle_initial']) ? mysqli_real_escape_string($conn, $_POST['middle_initial']) : '';
    $section        = mysqli_real_escape_string($conn, $_POST['section']);
    
    // Captured and sanitized parent contact number
    $parent_number  = isset($_POST['parent_number']) ? mysqli_real_escape_string($conn, trim($_POST['parent_number'])) : '';

    // INSERT query including parent_number and adviser_id to isolate students per user account[cite: 2]
    $query = "INSERT INTO student (last_name, first_name, middle_initial, section, parent_number, adviser_id, status, time_in) 
              VALUES ('$last_name', '$first_name', '$middle_initial', '$section', '$parent_number', $current_adviser_id, 'Present', NOW())";

    if(mysqli_query($conn, $query)) {
        // Redirect back to dashboard with a success parameter
        header("Location: dashboard.php?student_added=success");
        exit();
    } else {
        // Redirect back with an error parameter instead of dying on raw output
        header("Location: dashboard.php?student_error=db_error");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}
?>