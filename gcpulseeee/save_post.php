<?php
include("session_check.php");
include("db.php");

$title = $_POST['title'];
$content = $_POST['content'];
$category = $_POST['category'];
$user_id = $_SESSION['user_id'];
$scheduled_at = $_POST['scheduled_at'];

// Determine post status
$status = empty($scheduled_at) ? 'published' : 'scheduled';

$conn->query("INSERT INTO posts (user_id, title, content, category, scheduled_at, status) 
              VALUES ($user_id, '$title', '$content', '$category', '$scheduled_at', '$status')");

header("Location: dashboard.php");
?>
