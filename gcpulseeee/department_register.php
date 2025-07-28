<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department = trim($_POST['department']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $profile_path = null;

    // Validate form fields
    if ($department === "" || $password === "" || $confirm_password === "") {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // ✅ Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = $_FILES['profile_picture']['type'];

            if (in_array($file_type, $allowed_types)) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/gcpulseeee/uploads/profile_pictures/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $filename = $user_id . '_' . basename($_FILES['profile_picture']['name']);
                $upload_path = $upload_dir . $filename;
                $db_path = '/gcpulseeee/uploads/profile_pictures/' . $filename;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    $profile_path = $db_path;
                } else {
                    $error = "Failed to upload profile picture.";
                }
            } else {
                $error = "Invalid file type for profile picture.";
            }
        }

        // ✅ Save to database if no errors so far
        if (empty($error)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET department = ?, password = ?, profile_picture = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $department, $hashed_password, $profile_path, $user_id);

            if ($stmt->execute()) {
                $success = "Registration complete! Redirecting to your dashboard...";
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'index.php';
                    }, 3000);
                </script>";
            } else {
                $error = "Something went wrong while saving your info.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Registration</title>
    <link rel="stylesheet" href="logincss.css?v=3">
    <style>
        .register-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 500px;
            margin: 20px auto;
            padding: 20px;
        }
        .register-box {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
            text-align: center;
        }
        input, select {
            padding: 10px;
            width: 100%;
            font-size: 16px;
            margin: 10px 0;
        }
        .btn-save {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #3366cc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-save:hover {
            background-color: #254a99;
        }
        .error-msg { color: red; margin-bottom: 10px; }
        .success-msg { color: green; font-weight: bold; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="register-wrapper">
    <div class="register-box">
        <h2>Complete Your Registration</h2>

        <?php if (!empty($error)): ?>
            <p class="error-msg"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <p class="success-msg"><?= htmlspecialchars($success) ?></p>
        <?php endif; ?>

<form method="POST" enctype="multipart/form-data"> 
    <!-- Avatar Preview Wrapper (centered) -->
    <div style="margin: 20px 0; display: flex; justify-content: center;">
        <img
            id="avatarPreview"
            src="../gcpulseeee/img/user-icon.png"  Default avatar image 
            alt="Profile Preview"
            style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #ccc;"
        >
    </div>

    <!-- Profile Picture Input -->
    <label for="profile_picture">Upload Profile Picture (optional)</label>
    <input type="file" name="profile_picture" id="profile_picture" accept="image/*">

    <!-- Department Selection -->
    <select name="department" required>
        <option value="">-- Select Department --</option>
        <option value="College of Business and Accountancy (CBA)">College of Business and Accountancy (CBA)</option>
        <option value="College of Hospitality and Tourism Management (CHTM)">College of Hospitality and Tourism Management (CHTM)</option>
        <option value="College of Computer Studies (CCS)">College of Computer Studies (CCS)</option>
        <option value="College of Education and Allied Studies (CEAS)">College of Education and Allied Studies (CEAS)</option>
        <option value="College of Allied Health Studies (CAHS)">College of Allied Health Studies (CAHS)</option>
    </select>

    <!-- Password Fields -->
    <input type="password" name="password" placeholder="Set a password" required>
    <input type="password" name="confirm_password" placeholder="Confirm password" required>

    <!-- Submit -->
    <button type="submit" class="btn-save">Finish Registration</button>
</form>

    </div>
</div>
<script>
document.getElementById('profile_picture').addEventListener('change', function (event) {
    const file = event.target.files[0];
    const preview = document.getElementById('avatarPreview');

    if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        preview.src = '#';
        preview.style.display = 'none';
    }
});
</script>

</body>
</html>
