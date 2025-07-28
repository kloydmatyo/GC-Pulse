<?php
include("db.php");

// Get all users (excluding the post creator)
function sendNotifications($post_id, $title, $user_id) {
    global $conn;
    $message = "New post published: " . $title;

    $users = $conn->query("SELECT user_id FROM users WHERE user_id != $user_id");
    while ($row = $users->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->bind_param("is", $row['user_id'], $message);
        $stmt->execute();
    }
}

// Check if a new post is published
$query = $conn->query("SELECT * FROM posts WHERE scheduled_at IS NULL");
while ($row = $query->fetch_assoc()) {
    sendNotifications($row['post_id'], $row['title'], $row['user_id']);
}
?>
