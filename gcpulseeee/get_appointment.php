<?php
include("session_check.php");
include("db.php");

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(["error" => "No ID"]);
    exit();
}

$appointment_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT appointment_id, description, appointment_date FROM appointments WHERE appointment_id = ? AND user_id = ?");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["error" => "Appointment not found"]);
    exit();
}

$appointment = $result->fetch_assoc();
echo json_encode($appointment);
?>
