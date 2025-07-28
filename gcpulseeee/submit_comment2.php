<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $post_id = $_POST['post_id'];
    $comment = trim($_POST['comment']);
    $parent_comment_id = !empty($_POST['parent_comment_id']) ? $_POST['parent_comment_id'] : null;

    if (!empty($comment)) {
        $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comment, parent_comment_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $post_id, $user_id, $comment, $parent_comment_id);
        $stmt->execute();

        // Get post owner
        $owner_stmt = $conn->prepare("SELECT users.user_id FROM posts JOIN users ON posts.user_id = users.user_id WHERE posts.post_id = ?");
        $owner_stmt->bind_param("i", $post_id);
        $owner_stmt->execute();
        $owner_result = $owner_stmt->get_result();
        $owner_data = $owner_result->fetch_assoc();

        if ($owner_data && $owner_data['user_id'] != $user_id) {
            // Create notification
            $post_owner_id = $owner_data['user_id'];
            $commenter_name = $_SESSION['username'] ?? 'Someone';
            $message = "<strong>$commenter_name</strong> commented on your post";

            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, post_id) VALUES (?, ?, ?)");
            $notif_stmt->bind_param("isi", $post_owner_id, $message, $post_id);
            $notif_stmt->execute();
        }
    }
}

header("Location: index.php");
exit;
