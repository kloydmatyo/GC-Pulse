<?php
include("session_check.php");
include("db.php");

header('Content-Type: application/json'); // Ensure JSON is returned

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $appointment_date = $_POST['appointment_date'];
    $duration = (int)$_POST['duration'];

    $osws_id = 1;
    $buffer_minutes = 5;

    $new_start = new DateTime($appointment_date);
    $now = new DateTime();

    $appointmentDate = $new_start->format('Y-m-d');

    // Check if this date is blocked
    $checkBlocked = $conn->prepare("SELECT * FROM blocked_dates WHERE date = ?");
    $checkBlocked->bind_param("s", $appointmentDate);
    $checkBlocked->execute();
    $blockedResult = $checkBlocked->get_result();

    if ($blockedResult->num_rows > 0) {
        echo json_encode(['status' => 'blocked_date']);
        exit;
    }

    // Check if the appointment date is in the past
    if ($new_start < $now) {
        echo json_encode(['status' => 'past_date']);
        exit;
    }

    $new_end = clone $new_start;
    $new_end->modify("+$duration minutes");

    $start_str = $new_start->format('Y-m-d H:i:s');
    $end_str = $new_end->format('Y-m-d H:i:s');

    // Check for conflicts
    $check_stmt = $conn->prepare("
        SELECT COUNT(*) FROM appointments 
        WHERE 
            appointment_date < DATE_ADD(?, INTERVAL ? MINUTE) AND
            DATE_ADD(appointment_date, INTERVAL duration + ? MINUTE) > ?
    ");
    $check_stmt->bind_param("siss", $end_str, $buffer_minutes, $buffer_minutes, $start_str);
    $check_stmt->execute();
    $check_stmt->bind_result($conflict_count);
    $check_stmt->fetch();
    $check_stmt->close();

    if ($conflict_count > 0) {
        echo json_encode(['status' => 'conflict']);
        exit;
    }

    // Insert appointment
    $stmt = $conn->prepare("INSERT INTO appointments (user_id, title, osws_id, description, appointment_date, duration) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isissi", $user_id, $title, $osws_id, $description, $appointment_date, $duration);
    $stmt->execute();
    $stmt->close();

    // Notify OSWS
    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $message = "New appointment request: '$title'";
    $notif_stmt->bind_param("is", $osws_id, $message);
    $notif_stmt->execute();
    $notif_stmt->close();

    // ✅ Return success as JSON
    echo json_encode(['status' => 'success']);
    exit;
}
?>
