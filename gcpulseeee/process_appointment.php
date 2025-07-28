<?php
include("session_check.php");
include("db.php");

$appointment_id = $_POST['appointment_id'];
$status = $_POST['status'];
$response = $_POST['response'];

$conn->query("UPDATE appointments SET status = '$status', response = '$response' WHERE id = $appointment_id");

// Notify the organization about the decision
$conn->query("INSERT INTO notifications (user_id, message) 
              VALUES ((SELECT organization_id FROM appointments WHERE id = $appointment_id), 
              'Your appointment has been $status. Response: $response')");

header("Location: manage_appointments.php");
?>
