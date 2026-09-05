<?php
session_start();
include 'db.php'; 

if (isset($_POST['register'])) {
    $teacher_name = mysqli_real_escape_string($conn, $_POST['teacher_name']);
    $grade        = mysqli_real_escape_string($conn, $_POST['grade']);
    $pin          = $_POST['pin'];
    $re_pin       = $_POST['re_pin'];

    // 1. Check if PIN fields match
    if ($pin !== $re_pin) {
        $_SESSION['reg_error'] = "PINs do not match! Please try again.";
        header("Location: login.php");
        exit();
    }

    // 2. Check if this teacher account already exists for the given grade
    $check_query = "SELECT * FROM advisers WHERE teacher_name='$teacher_name' AND grade='$grade'";
    $check_result = mysqli_query($conn, $check_query);

    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['reg_error'] = "This advisor account is already registered!";
        header("Location: login.php");
        exit();
    }

    // 3. Insert without section column
    $sql = "INSERT INTO advisers (teacher_name, grade, pin) VALUES ('$teacher_name', '$grade', '$pin')";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['reg_success'] = "Registered successfully!";
        header("Location: login.php");
        exit();
    } else {
        $_SESSION['reg_error'] = "Database Error: " . mysqli_error($conn);
        header("Location: login.php");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>