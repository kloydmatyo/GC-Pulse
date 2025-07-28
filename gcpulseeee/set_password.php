<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error = $success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Both fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);

        if ($stmt->execute()) {
            $success = "Password set successfully. You can now log in manually.";
        } else {
            $error = "Error updating password. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Set Password</title>
    <link rel="stylesheet" href="logincss.css?v=4">
</head>
<body>
<div class="login-wrapper">
    <div class="login-right">
        <h2>Set Your Password</h2>

<?php if ($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if ($success): ?>
    <div id="successModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Success 🎉</h3>
            <p><?= htmlspecialchars($success) ?></p>
            <a href="index.php" class="modal-button">Go to Home</a>
        </div>
    </div>
    <script>
        // Show the modal on load if success
        window.onload = function () {
            document.getElementById("successModal").style.display = "block";
        };

        function closeModal() {
            document.getElementById("successModal").style.display = "none";
        }
    </script>
<?php endif; ?>


        <form action="set_password.php" method="POST">
            <input type="password" name="password" placeholder="New Password" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password" required>
            <button type="submit">Set Password</button>
        </form>
        <a href="index.php">← Back to Home</a>
    </div>
</div>
</body>
</html>
