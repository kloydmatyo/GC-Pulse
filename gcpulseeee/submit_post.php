<?php
include("session_check.php");
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $scheduled_at = $_POST['scheduled_at'] ?: NULL;
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("INSERT INTO posts (title, content, category, scheduled_at, user_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $title, $content, $category, $scheduled_at, $user_id);
    $stmt->execute();
    $post_id = $conn->insert_id;

    if (!$scheduled_at) {
        // Notify all users about the new post
        $users = $conn->query("SELECT user_id FROM users WHERE user_id != $user_id");
        while ($row = $users->fetch_assoc()) {
            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, post_id, message) VALUES (?, ?, ?)");
            $message = "A new post '$title' has been published.";
            $notif_stmt->bind_param("iis", $row['user_id'], $post_id, $message);
            $notif_stmt->execute();
        }
    }

    header("Location: view_posts.php");
    exit();
}
?>
