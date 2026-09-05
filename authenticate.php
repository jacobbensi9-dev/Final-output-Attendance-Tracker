<?php
session_start();
include 'db.php'; // Connects to your database ($conn)

if (isset($_POST['login'])) {
    // Collect and sanitize user input
    $teacher_name = mysqli_real_escape_string($conn, $_POST['teacher_name']);
    $grade        = mysqli_real_escape_string($conn, $_POST['grade']);
    $pin          = $_POST['pin'];
    $re_pin       = $_POST['re_pin'];

    // 1. Check if PIN fields match
    if ($pin !== $re_pin) {
        $_SESSION['login_error'] = "PINs do not match! Please try again.";
        header("Location: index.php");
        exit();
    }

    // 2. Query adviser details
    $sql = "SELECT * FROM advisers WHERE teacher_name='$teacher_name' AND grade='$grade' AND pin='$pin'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        // Save active user info in session
        $_SESSION['adviser_id']   = $user['id']; // Connects user to their own sections
        $_SESSION['admin']        = $user['teacher_name'];
        $_SESSION['grade']        = $user['grade'];
        $_SESSION['is_logged_in'] = true;

        // Clear active section so a new user starts fresh
        unset($_SESSION['section']);

        // Clear errors if any existed previously
        unset($_SESSION['login_error']);

        header("Location: attendance_graph.php");
        exit();
    } else {
        // Account details don't match database records
        $_SESSION['login_error'] = "Invalid account details or unregistered adviser.";
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>