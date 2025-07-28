<?php
include("db.php");

// Get posts that are scheduled for publishing now or earlier
$query = $conn->query("UPDATE posts SET scheduled_at = NULL WHERE scheduled_at <= NOW()");

echo "Scheduled posts published!";
?>
