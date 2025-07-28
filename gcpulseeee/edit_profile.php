<?php
session_start();
include("db.php");

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update Profile Picture
    if (isset($_POST['update_picture'])) {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $profile_picture = $_FILES['profile_picture'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

            if (in_array($profile_picture['type'], $allowed_types)) {
                // Set the upload directory
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/gcpulseeee/uploads/profile_pictures/';

                // Check if the directory exists, if not, create it
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);  // Create the directory with proper permissions
                }

                // Create a new filename to prevent conflicts
                $new_filename = $user_id . '_' . basename($profile_picture['name']);
                $upload_path = $upload_dir . $new_filename;

                // Try to move the uploaded file
                if (move_uploaded_file($profile_picture['tmp_name'], $upload_path)) {
                    // Update the profile picture URL in the database
                    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $upload_path, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $message = "Profile picture updated successfully.";
                } else {
                    $error = "Error uploading the file.";
                }
            } else {
                $error = "Invalid file type.";
            }
        } else {
            $error = "No file uploaded.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>

<div class="container">
    <h2>Edit Profile</h2>

    <!-- Show Message or Error -->
    <?php if (isset($message)): ?>
        <div class="success"><?= $message ?></div>
    <?php elseif (isset($error)): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <!-- Edit Profile Form -->
    <form action="edit_profile.php" method="POST" enctype="multipart/form-data">
        <h3>Change Profile Picture</h3>
        <div class="form-group">
            <label for="profile_picture">Choose a new profile picture</label>
            <input type="file" name="profile_picture" id="profile_picture" accept="image/*" required>
        </div>
        <button type="submit" name="update_picture">Update Profile Picture</button>
    </form>

    <hr>
    <a href="profile.php">Back to Profile</a>
</div>

</body>
</html>
