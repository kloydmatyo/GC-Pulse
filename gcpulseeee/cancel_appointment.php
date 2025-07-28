<?php
include("session_check.php");
include("db.php");

if (!isset($_GET['id'])) {
    header("Location: view_appointments.php");
    exit();
}

$appointment_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Ensure user owns the appointment
$stmt = $conn->prepare("DELETE FROM appointments WHERE appointment_id = ? AND user_id = ?");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();

header("Location: view_appointments.php");
exit();
?>
