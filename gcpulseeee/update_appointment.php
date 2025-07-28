<?php
include("session_check.php");
include("db.php");

// Load PHPMailer (using Composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'send_mail.php';

if (!isset($_GET['appointment_id']) || !isset($_GET['status'])) {
    echo "Invalid request.";
    exit();
}

$appointment_id = $_GET['appointment_id'];
$status = $_GET['status'];

// Map action to status
if ($status === 'approve') {
    $status = 'approved';
} elseif ($status === 'reject') {
    $status = 'rejected';
}

$valid_statuses = ['approved', 'rejected', 'pending'];
if (!in_array($status, $valid_statuses)) {
    echo "Invalid status value: " . htmlspecialchars($status);
    exit();
}

// Update appointment status
$query = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
$query->bind_param("si", $status, $appointment_id);
$update_success = $query->execute();

if ($update_success) {
    // Get user ID from appointment
    $get_user = $conn->prepare("SELECT user_id FROM appointments WHERE appointment_id = ?");
    $get_user->bind_param("i", $appointment_id);
    $get_user->execute();
    $result = $get_user->get_result();
    $appointment = $result->fetch_assoc();
    $user_id = $appointment['user_id'];

    // Get user email and name
    $get_email = $conn->prepare("SELECT email, username FROM users WHERE user_id = ?");
    $get_email->bind_param("i", $user_id);
    $get_email->execute();
    $user_result = $get_email->get_result();
    $user = $user_result->fetch_assoc();

    // Compose email
    $subject = "Appointment " . ucfirst($status);
    $body = "Hi <strong>{$user['username']}</strong>,<br><br>Your appointment has been <strong>$status</strong>.<br><br>Thank you.";

    // Insert in-app notification
    $message = "Your appointment has been $status.";
    $notify = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notify->bind_param("is", $user_id, $message);
    $notify->execute();

    // Send email
    $sent = sendMail($user['email'], $subject, $body);
    if (!$sent) {
        error_log("Failed to send email to {$user['email']}");
    }

} else {
    error_log("Database update failed: " . $query->error);
    header("Location: manage_appointment.php?error=update_failed");
    exit();
}

// Redirect back
header("Location: manage_appointment.php");
exit();
?>
