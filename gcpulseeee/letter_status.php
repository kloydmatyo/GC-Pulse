<?php
include("session_check.php");
include("db.php");

$organization_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM letters WHERE organization_id = $organization_id ORDER BY submitted_at DESC");

echo "<h1>📜 Submitted Letters</h1>";
while ($row = $result->fetch_assoc()) {
    echo "<h3>{$row['title']}</h3>";
    echo "<p>Status: <b>{$row['status']}</b></p>";
    if ($row['response']) {
        echo "<p>Response: {$row['response']}</p>";
    }
    echo "<hr>";
}
?>
