<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$error = $success = "";
$password_is_set = false;

// Step 1: Check if user already has a password
$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($existing_password);
$stmt->fetch();
$password_is_set = !empty($existing_password);
$stmt->close();

// Step 2: Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

if ($password_is_set) {
    if (empty($current_password)) {
        $error = "Current password is required.";
    } elseif (!password_verify($current_password, $existing_password)) {
        $error = "Current password is incorrect.";
    }
}

if (empty($error)) {
    if (empty($new_password) || empty($confirm_password)) {
        $error = "New password and confirmation are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        if ($stmt->execute()) {
            $success = $password_is_set
                ? "Password updated successfully."
                : "Password set successfully. You can now log in manually.";
        } else {
            $error = "Error updating password. Please try again.";
        }
    }
}

}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= $password_is_set ? "Change Password" : "Set Password" ?></title>
    <link rel="stylesheet" href="logincss.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body{
            font-family: apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            background-color: #f5f7fa !important;
        }
        .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
    font-family: sans-serif;
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}

.modal h3 {
    margin-top: 0;
    font-size: 24px;
    color: #28a745;
}

.modal-button {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 18px;
    background-color: #28a745;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
}

.modal .close {
    position: absolute;
    right: 20px;
    top: 10px;
    font-size: 24px;
    color: #aaa;
    cursor: pointer;
}

  .cancel{
    width: 100%;
    background: linear-gradient(to right, #074D34, #418C4C);
    color: white;
    padding: 12px;
    border: none;
    border-radius: 30px;
    font-size: 16px;
    cursor: pointer;
    transition: background 0.3sease;
    margin: 0;
  }

  .cancel:hover {
        background: linear-gradient(to right, #1B3B20, #2C5F34);
  }
   .form-buttons {
    display: flex;
    gap: 10px; /* space between the button and the link */
    margin-top: 10px;
  }

  .form-buttons button,
  .form-buttons .cancel {
    padding: 10px 15px;
    font-size: 14px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    justify-content: center;
      display: inline-flex;
  }

.login-wrapper {
    display: flex;
    height:700px;
        background: linear-gradient(135deg, #074D34, #418C4C) !important;
    padding: 2rem;
}

.login-right {
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    max-width: 450px;
    width: 100%;
    margin: auto;
}

h2 {
    color: #2e4e33;
    font-size: 28px;
    margin-bottom: 1.5rem;
    text-align: center;
        margin-top: 30px;
}

input[type="password"] {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e1e5ea;
    border-radius: 8px;
    font-size: 15px;
    margin-bottom: 1rem;
    transition: border-color 0.2s;
}

input[type="password"]:focus {
    border-color: #2e4e33;
    outline: none;
}

.form-buttons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-top: 1.5rem;
}

.form-buttons button,
.form-buttons .cancel, {
    padding: 12px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
    text-decoration: none;
}

.form-buttons button {
    background: #2e4e33;
    color: white;
    border: none;
}

.form-buttons button:hover {
    background: #1B3B20;
}

.form-buttons .cancel {
    background: #f8f9fa;
    color: #2e4e33;
    border: 2px solid #2e4e33;
}

.form-buttons .cancel:hover {
    background: #e9ecef;
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    text-align: center;
}

.modal h3 {
    color: #2e4e33;
    margin-bottom: 1rem;
}

.modal-button {
    display: inline-block;
    padding: 12px 24px;
    background: #2e4e33;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    margin-top: 1.5rem;
    transition: background 0.2s;
}

.modal-button:hover {
    background: #1B3B20;
}

.error-msg {
    background: #ffebee;
    color: #c62828;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 14px;
    text-align: center;
}
.edit-profile-btn {
    display: inline-block;
    background: #2e4e33;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    text-decoration: none;
    transition: background 0.3s ease;
    position: relative;
    top: -425px;
    left: 200px;
}

.edit-profile-btn:hover {
    background: #1B3B20;
}

    </style>
</head>
<body>
    <div class="login-right">
        <h2><?= $password_is_set ? "Change Password" : "Set a Password" ?></h2>

<?php if ($error): ?>
    <p class="error-msg"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<?php if ($success): ?>
    <div id="successModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>Success <i class="fa-solid fa-party-horn"></i></h3>
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


<form action="change_password.php" method="POST">
    
    <?php if ($password_is_set): ?>
        <input type="password" name="current_password" placeholder="Current Password" required>
    <?php endif; ?>

    <input type="password" name="password" placeholder="New Password" required>
    <input type="password" name="confirm_password" placeholder="Confirm New Password" required>

    <div class="form-buttons">
        <a href="index.php" class="cancel">Cancel</a>
        <button type="submit"><?= $password_is_set ? "Change Password" : "Set Password" ?></button>
                <!-- Edit Profile button above login-right -->
        <div style="width: 100%; text-align: center; margin-bottom: 1rem;">
            <a href="update_profile.php" class="edit-profile-btn">Edit Profile</a>
        </div>
    </div>
</form>

    </div>
</div>

</body>
</html>