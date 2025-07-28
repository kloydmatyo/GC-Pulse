<?php
include("db.php");

// Publish posts where scheduled time has passed
$conn->query("UPDATE posts SET status = 'published' WHERE status = 'scheduled' AND scheduled_at <= NOW()");
?>
