<?php
session_start();
include("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo "Unauthorized";
        exit;
    }

    $comment_id = $_POST['comment_id'];
    $new_content = trim($_POST['content']);

    // Only allow the user to edit their own comment
    $stmt = $conn->prepare("UPDATE comments SET comment = ? WHERE comment_id = ? AND user_id = ?");
    $stmt->bind_param("sii", $new_content, $comment_id, $_SESSION['user_id']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "Success";
    } else {
        http_response_code(400);
        echo "Update failed or unauthorized";
    }

    $stmt->close();
} else {
    http_response_code(405);
    echo "Method not allowed";
}
?>