<?php
include("session_check.php");
include("db.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = intval($_POST['post_id']);
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);

    // Step 1: Verify ownership
    $check_stmt = $conn->prepare("SELECT image_path FROM posts WHERE post_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $post_id, $user_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        echo "Unauthorized action.";
        exit;
    }

    $row = $result->fetch_assoc();
    $oldImagePath = $row['image_path'];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imageTmp = $_FILES['image']['tmp_name'];
        $imageName = basename($_FILES['image']['name']);
        $imagePath = 'uploads/' . time() . '_' . $imageName;
    
        // Check image size
        $maxFileSize = 5 * 1024 * 1024; // 2MB
        if ($_FILES['image']['size'] > $maxFileSize) {
            echo "Image is too large. Maximum allowed size is 2MB.";
            exit;
        }
    
        // Optionally: Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array(mime_content_type($imageTmp), $allowedTypes)) {
            echo "Invalid image type.";
            exit;
        }
    
        if (move_uploaded_file($imageTmp, $imagePath)) {
            // Optionally delete old image
            if (!empty($oldImagePath) && file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
    
            $stmt = $conn->prepare("UPDATE posts SET title = ?, content = ?, image_path = ? WHERE post_id = ?");
            $stmt->bind_param("sssi", $title, $content, $imagePath, $post_id);
        } else {
            echo "Failed to upload image.";
            exit;
        }
    } else {
        $stmt = $conn->prepare("UPDATE posts SET title = ?, content = ? WHERE post_id = ?");
        $stmt->bind_param("ssi", $title, $content, $post_id);
    }

    $stmt->execute();
    header("Location: index.php");
    exit;

} else {
    echo "Invalid request.";
}
?>
