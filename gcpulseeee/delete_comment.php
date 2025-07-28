<?php
session_start();
include("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_id'])) {
        echo "Unauthorized";
        exit;
    }

    $comment_id = $_POST['comment_id'];

    // Ensure the comment belongs to the user
    $stmt = $conn->prepare("DELETE FROM comments WHERE comment_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $comment_id, $_SESSION['user_id']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        header("Location: index.php"); // Redirect after delete
        exit;
    } else {
        echo "Delete failed or unauthorized";
    }

    $stmt->close();
} else {
    echo "Invalid request method.";
}
?>
