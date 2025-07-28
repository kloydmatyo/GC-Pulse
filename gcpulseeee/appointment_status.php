<?php
include("session_check.php");
include("db.php");

$organization_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM appointments WHERE organization_id = $organization_id ORDER BY created_at DESC");

echo "<h1>📅 Your Appointments</h1>";
while ($row = $result->fetch_assoc()) {
    echo "<h3>Date & Time: {$row['date_time']}</h3>";
    echo "<p>Purpose: {$row['purpose']}</p>";
    echo "<p>Status: <b>{$row['status']}</b></p>";
    if ($row['response']) {
        echo "<p>Response: {$row['response']}</p>";
    }
    echo "<hr>";
}
?>
