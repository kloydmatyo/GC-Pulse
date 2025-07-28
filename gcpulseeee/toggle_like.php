<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $post_id = $_POST['post_id'];

    // Check if already liked
    $check_stmt = $conn->prepare("SELECT * FROM likes WHERE user_id = ? AND post_id = ?");
    $check_stmt->bind_param("ii", $user_id, $post_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Unlike
        $unlike_stmt = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND post_id = ?");
        $unlike_stmt->bind_param("ii", $user_id, $post_id);
        $unlike_stmt->execute();
    } else {
        // Like
        $like_stmt = $conn->prepare("INSERT INTO likes (user_id, post_id) VALUES (?, ?)");
        $like_stmt->bind_param("ii", $user_id, $post_id);
        $like_stmt->execute();

        // Get post owner
        $owner_stmt = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ?");
        $owner_stmt->bind_param("i", $post_id);
        $owner_stmt->execute();
        $owner_result = $owner_stmt->get_result();
        $owner_data = $owner_result->fetch_assoc();

        if ($owner_data && $owner_data['user_id'] != $user_id) {
            $post_owner_id = $owner_data['user_id'];
            $liker_name = $_SESSION['username'] ?? 'Someone';
            $message = "<strong>$liker_name</strong> liked your post";

            $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, post_id) VALUES (?, ?, ?)");
            $notif_stmt->bind_param("isi", $post_owner_id, $message, $post_id);
            $notif_stmt->execute();
        }
    }
}

header("Location: index.php");
exit;
