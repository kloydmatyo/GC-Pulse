<?php
include("session_check.php");
include("db.php");

$result = $conn->query("SELECT * FROM appointments WHERE status = 'approved'");
$events = [];

while ($row = $result->fetch_assoc()) {
    $events[] = [
        'title' => $row['title'],
        'start' => $row['appointment_date']
    ];
}

echo json_encode($events);
?>
