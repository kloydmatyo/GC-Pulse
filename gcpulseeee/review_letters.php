<?php
include("session_check.php");
include("db.php");

$result = $conn->query("SELECT * FROM letters WHERE status = 'pending' ORDER BY submitted_at DESC");

echo "<h1>📜 Pending Letters</h1>";
while ($row = $result->fetch_assoc()) {
    echo "<h3>{$row['title']}</h3>";
    echo "<p>{$row['content']}</p>";
    echo "<form action='process_letter.php' method='POST'>";
    echo "<input type='hidden' name='letter_id' value='{$row['id']}'>";
    echo "<textarea name='response' placeholder='Enter response...'></textarea><br>";
    echo "<button type='submit' name='status' value='approved'>✅ Approve</button>";
    echo "<button type='submit' name='status' value='rejected'>❌ Reject</button>";
    echo "</form><hr>";
}
header("Location: manage_appointment.php");
?>
