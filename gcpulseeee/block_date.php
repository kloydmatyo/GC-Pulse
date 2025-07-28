<?php
include("db.php");

$data = json_decode(file_get_contents("php://input"), true);
$date = $data['date'];
$reason = $data['reason'];

if ($date && $reason) {
    $stmt = $conn->prepare("INSERT INTO blocked_dates (date, reason) VALUES (?, ?)");
    $stmt->bind_param("ss", $date, $reason);
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false]);
    }
} else {
    echo json_encode(["success" => false]);
}
?>
