<?php
include("session_check.php");
include("db.php");
checkRole(['admin', 'organization']);

if (isset($_GET["post_id"])) {
    $post_id = intval($_GET["post_id"]);
    $user_id = $_SESSION["user_id"];
    $role = $_SESSION["role"];

    // Optional: permission check for non-admin users
    if ($role !== 'admin') {
        $stmt = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $post_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Unauthorized or post doesn't exist
            header("Location: manage_post.php?error=unauthorized");
            exit();
        }
    }

    // Delete related comments first
    $delete_comments = $conn->prepare("DELETE FROM comments WHERE post_id = ?");
    $delete_comments->bind_param("i", $post_id);
    $delete_comments->execute();

    // Then delete the post
    $delete_post = $conn->prepare("DELETE FROM posts WHERE post_id = ?");
    $delete_post->bind_param("i", $post_id);
    $delete_post->execute();

    header("Location: manage_post.php?deleted=1");
    exit();
} else {
    // If no post_id is set, go back
    header("Location: manage_post.php?error=missing_id");
    exit();
}
?>
