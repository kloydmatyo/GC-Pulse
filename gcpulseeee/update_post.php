<?php
include("session_check.php");
include("db.php");

$post_id = $_POST['id'];
$title = $_POST['title'];
$content = $_POST['content'];
$category = $_POST['category'];
$user_id = $_SESSION['user_id'];

$conn->query("UPDATE posts SET title = '$title', content = '$content', category = '$category' 
              WHERE id = $post_id AND user_id = $user_id");

header("Location: dashboard.php");
?>
