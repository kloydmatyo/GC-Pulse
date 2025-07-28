<?php
include("session_check.php");
include("db.php");

$$notif_id = $_GET['notification_id'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
$stmt->bind_param("ii", $notif_id, $user_id);
$stmt->execute();
header("Location: notifications.php");
?>
