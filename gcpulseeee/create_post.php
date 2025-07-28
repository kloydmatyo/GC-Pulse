<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$audiences = $_POST['audience'] ?? []; // can be empty
$audience_type = $_POST['audience_type'] ?? 'public';

$audience_string = !empty($audiences) ? implode(',', $audiences) : null;

$user_id = $_SESSION['user_id'];
$title = $_POST['title'];
$content = $_POST['content'];
$category = $_POST['category'];

// Handle image upload
$image_path = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    if ($_FILES['image']['size'] > $maxFileSize) {
        die("❌ Image is too large. Maximum allowed size is 5MB.");
    }
    
    $upload_dir = "uploads/";
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $image_name = time() . '_' . basename($_FILES['image']['name']);
    $target_path = $upload_dir . $image_name;

    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
        $image_path = $target_path;
    } else {
        echo "❌ Failed to upload the image.";
        exit;
    }
}

// Insert post
$stmt = $conn->prepare("INSERT INTO posts (user_id, title, content, category, audience_type, audience, created_at, image_path) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
$stmt->bind_param("issssss", $user_id, $title, $content, $category, $audience_type, $audience_string, $image_path);

$stmt->execute();
$post_id = $stmt->insert_id;

// Send notifications to other users
$notif_msg = "New post in " . ucfirst($category) . ": \"$title\"";

$users = $conn->query("SELECT user_id FROM users WHERE user_id != $user_id");
$notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, post_id, message, created_at) VALUES (?, ?, ?, NOW())");

while ($user = $users->fetch_assoc()) {
    $notif_stmt->bind_param("iis", $user['user_id'], $post_id, $notif_msg);
    $notif_stmt->execute();
}

$stmt->close();
$notif_stmt->close();

header("Location: index.php");
exit;

?>
