<?php
include("session_check.php");
include("db.php");

$organization_id = $_SESSION['user_id'];
$osws_id = 1; // Assuming OSWS user ID is 1
$date_time = $_POST['date_time'];
$purpose = $_POST['purpose'];

$conn->query("INSERT INTO appointments (organization_id, osws_id, date_time, purpose) 
              VALUES ($organization_id, $osws_id, '$date_time', '$purpose')");

// Notify OSWS about the new appointment request
$conn->query("INSERT INTO notifications (user_id, message) 
              VALUES ($osws_id, 'New appointment request from Organization ID: $organization_id')");

header("Location: appointment_status.php");
?>
