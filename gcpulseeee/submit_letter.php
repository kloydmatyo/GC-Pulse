<?php
include("session_check.php");
include("db.php");  // Assumes $conn or $mysqli are being set in db.php

// Make sure session is started before accessing session variables
session_start();

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? 1;
    $osws_id = 1;
    $title = $_POST['title'] ?? 'Untitled';
    $content = trim($_POST['message'] ?? '');
    $status = 'pending';
    $response = null;
    $created_at = date('Y-m-d H:i:s');
    
    $upload_dir = 'uploads/';
    $allowed_types = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $file_path = null;

    if (!empty($_FILES['file']['name'])) {
        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = uniqid() . "_" . basename($_FILES['file']['name']);
        $file_type = mime_content_type($file_tmp);

        if (in_array($file_type, $allowed_types)) {
            $target_path = $upload_dir . $file_name;
            if (move_uploaded_file($file_tmp, $target_path)) {
                $file_path = $target_path; // ✅ THIS IS WHAT UPDATES THE VALUE
            } else {
                echo "Failed to upload file.";
                exit;
            }
        } else {
            echo "Only PDF and DOCX files are allowed.";
            exit;
        }
    }

    if (!empty($content) || $file_path) {
        $stmt = $conn->prepare("INSERT INTO letters (user_id, osws_id, title, content, status, response, created_at, file_path) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissssss", $user_id, $osws_id, $title, $content, $status, $response, $created_at, $file_path);

        if ($stmt->execute()) {
            echo "Message sent successfully.";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Message or file is required.";
    }
} else {
    echo "Invalid request.";
}
?>
