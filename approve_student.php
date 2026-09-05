<?php
    session_start();
    if(!isset($_SESSION['admin'])){
        header("Location: index.php");
        exit();
    }

    include 'connection.php';

    if(isset($_GET['id'])) {
        $student_id = mysqli_real_escape_string($conn, $_GET['id']);
        
        // Update the stage parameter to move them to the final active dashboard checklist
        $approveQuery = mysqli_query($conn, "
            UPDATE student 
            SET registration_stage='Final' 
            WHERE id='$student_id'
        ");
    }

    // Redirect straight back to your active dashboard view panel
    header("Location: dashboard.php");
    exit();
?>