<?php
include("session_check.php");
include("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = $_POST['post_id'];
    $user_id = $_SESSION['user_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];

    // Check ownership
    $stmt = $conn->prepare("SELECT * FROM posts WHERE post_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $post_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "Unauthorized action.";
        exit;
    }

    $update = $conn->prepare("UPDATE posts SET title = ?, content = ? WHERE post_id = ?");
    $update->bind_param("ssi", $title, $content, $post_id);
    $update->execute();

    header("Location: index.php");
    exit;
} else {
    echo "Invalid request.";
}
?>
$stmt = $conn->prepare("SELECT * FROM posts WHERE post_id = ? AND user_id = ?");