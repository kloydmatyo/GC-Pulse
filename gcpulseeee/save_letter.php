<?php
include("session_check.php");
include("db.php");

$title = $_POST['title'];
$content = $_POST['content'];
$organization_id = $_SESSION['user_id'];

$conn->query("INSERT INTO letters (organization_id, title, content) 
              VALUES ($organization_id, '$title', '$content')");

header("Location: letter_status.php");
?>
