<?php
session_start();
include("session_check.php");
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['appointment_id']);
    $desc = trim($_POST['description']);
    $date = $_POST['appointment_date'];
    $duration = intval($_POST['duration']);

    // Prepare and bind
    $stmt = $conn->prepare("
        UPDATE appointments 
        SET description = ?, appointment_date = ?, duration = ? 
        WHERE appointment_id = ? AND user_id = ?
    ");
    $stmt->bind_param("ssiii", $desc, $date, $duration, $id, $_SESSION['user_id']);

    // 🔽 HERE is where you add your success/failure check
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['message'] = "Appointment updated successfully.";
        } else {
            $_SESSION['message'] = "No changes made.";
        }
    } else {
        $_SESSION['message'] = "Update failed.";
    }

    header("Location: view_appointments.php");
    exit();
} else {
    header("Location: view_appointments.php");
    exit();
}
