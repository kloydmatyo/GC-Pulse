<?php

session_start();
include("db.php");
date_default_timezone_set('Asia/Manila'); // Philippines Time (PHT)

$error = $success = "";
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die("Invalid or missing token.");
}

// Validate token and expiry
$stmt = $conn->prepare("SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->bind_result($user_id);
$stmt->fetch();
$stmt->close();

if (!$user_id) {
    die("This password reset link is invalid or expired.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = trim($_POST['password']);
    $confirm = trim($_POST['confirm']);

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Update password & clear token
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?");
        $update->bind_param("si", $hashed, $user_id);
        if ($update->execute()) {
            $success = "Password successfully reset. <a href='login.php'>Log in now</a>";
        } else {
            $error = "Something went wrong. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <style>
body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #074D34, #418C4C);
    min-height: 100vh;
    margin: 0;
    display: grid;
    place-items: center;
    padding: 1rem;
}

.container {
    max-width: 450px;
    width: 100%;
    padding: 2.5rem;
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

h2 {
    text-align: center;
    color: #2e4e33;
    font-size: 28px;
    margin-bottom: 1.5rem;
    font-weight: 600;
}

.input-group {
    position: relative;
    margin-bottom: 1.5rem;
}

.input-group label {
    display: block;
    margin-bottom: 0.5rem;
    color: #374151;
    font-weight: 500;
}

.input-group i {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
}

input[type="password"] {
    width: 400px;
    padding: 12px 16px 12px 45px;
    border: 2px solid #e1e5ea;
    border-radius: 8px;
    font-size: 15px;
    transition: all 0.2s;
}

input[type="password"]:focus {
    border-color: #2e4e33;
    outline: none;
    box-shadow: 0 0 0 3px rgba(46, 78, 51, 0.1);
}

button {
    width: 100%;
    padding: 14px;
    background-color: #2e4e33;
    color: white;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

button:hover {
    background-color: #1B3B20;
    transform: translateY(-1px);
}

button:active {
    transform: translateY(0);
}

.message {
    text-align: center;
    margin-bottom: 1.5rem;
    padding: 12px;
    border-radius: 8px;
    font-weight: 500;
}

.message.success {
    background-color: #e7f5e7;
    color: #1b7a1b;
    border: 1px solid #c3e6c3;
}

.message.error {
    background-color: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
}
    </style>
</head>
<body>

<div class="container">
    <h2>Reset Password</h2>

    <?php if (!empty($error)): ?>
        <p class="message error"><?= $error ?></p>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <p class="message success"><?= $success ?></p>
    <?php else: ?>
        <form method="POST">
            <div class="input-group">
                <label for="password">Password<span>*</span></label>
                <input type="password" id="password" name="password" required placeholder="Enter new password">
            </div>

            <div class="input-group">
                <label for="confirm">Confirm Password<span>*</span></label>
                <input type="password" id="confirm" name="confirm" required placeholder="Confirm new password">
            </div>

            <button type="submit">Reset Password</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>

