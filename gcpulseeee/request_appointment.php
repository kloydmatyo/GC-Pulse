<?php
include("session_check.php");
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $osws_id = 1; // OSWS User ID (Assuming only one OSWS account)
    $appointment_date = $_POST['appointment_date'];

    $query = $conn->prepare("INSERT INTO appointments (user_id, osws_id, appointment_date) VALUES (?, ?, ?)");
    $query->bind_param("iis", $user_id, $osws_id, $appointment_date);

    if ($query->execute()) {
        // Notify OSWS
        $message = "New appointment request from Organization ID: $user_id on $appointment_date.";
        $notify = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $notify->bind_param("is", $osws_id, $message);
        $notify->execute();

        echo "Appointment requested successfully!";
    } else {
        echo "Error: " . $query->error;
    }
}
?>

<!-- HTML Form -->
<form action="save_appointment.php" method="POST">
    <label>Date & Time:</label>
    <input type="datetime-local" name="date_time" required><br>

    <label>Purpose:</label>
    <textarea name="purpose" required></textarea><br>

    <button type="submit">Request Appointment</button>
</form>
