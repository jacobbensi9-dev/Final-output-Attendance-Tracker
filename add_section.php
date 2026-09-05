<?php
// add_section.php
session_start();
include 'connection.php'; // Updated to match your connection file name

// Ensure the user is logged in as an adviser/admin
if (!isset($_SESSION['admin']) || !isset($_SESSION['adviser_id'])) {
    header("Location: index.php");
    exit();
}

$current_adviser_id = (int)$_SESSION['adviser_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['section_name'])) {
    $section_name = trim($_POST['section_name']);

    if (!empty($section_name)) {
        // Tied to adviser_id so same section names from different users don't conflict or share data
        $stmt = $conn->prepare("INSERT INTO section (section_name, adviser_id) VALUES (?, ?)");
        $stmt->bind_param("si", $section_name, $current_adviser_id);
        
        if ($stmt->execute()) {
            $new_section_id = $stmt->insert_id;
            
            // Also store it in the session if your dashboard reads from it
            $_SESSION['section'] = $section_name;

            // Redirect to dashboard viewing the brand new section
            header("Location: dashboard.php?section_id=" . $new_section_id);
            exit();
        }
    }
}
?>