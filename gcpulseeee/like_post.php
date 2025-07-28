<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['post_id'])) {
    $post_id = intval($_POST['post_id']);
    $user_id = $_SESSION['user_id'] ?? 0;

    if ($user_id) {
        // Prevent duplicate likes
        $check = $conn->prepare("SELECT * FROM likes WHERE post_id = ? AND user_id = ?");
        $check->bind_param("ii", $post_id, $user_id);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows === 0) {
            $like = $conn->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
            $like->bind_param("ii", $post_id, $user_id);
            $like->execute();
        }
    }

    header("Location: index.php");
    exit();
}
?>
