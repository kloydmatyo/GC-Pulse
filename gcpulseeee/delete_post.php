<?php
include("session_check.php");
include("db.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["post_id"])) {
    $post_id = intval($_POST["post_id"]);
    $user_id = $_SESSION["user_id"];

    if ($result->num_rows > 0) {
        // Delete comments linked to this post
        $delete_comments = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
        $delete_comments->bind_param("i", $post_id);
        $delete_comments->execute();

        // Now delete the post
        $delete_post = $conn->prepare("DELETE FROM posts WHERE post_id = ?");
        $delete_post->bind_param("i", $post_id);
        $delete_post->execute();
    }
}

header("Location: index.php");
exit();
