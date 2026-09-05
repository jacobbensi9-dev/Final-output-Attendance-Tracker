<?php
include 'connection.php';

if (isset($_GET['id'])) {

    $id = (int)$_GET['id'];

    // Delete all attendance records of the student
    mysqli_query($conn, "DELETE FROM attendance WHERE student_id = $id");

    // Delete the student
    mysqli_query($conn, "DELETE FROM student WHERE id = $id");
}

header("Location: dashboard.php?view=roster");
exit();
?>