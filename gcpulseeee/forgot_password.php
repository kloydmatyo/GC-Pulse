<?php

session_start();
include("db.php");
date_default_timezone_set('Asia/Manila'); // Philippines Time (PHT)

$error = $success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Generate and store new token
        $token = bin2hex(random_bytes(32));
        $expiry = date("Y-m-d H:i:s", strtotime("+30 minutes"));

        $stmt->close();
        $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
        $update->bind_param("sss", $token, $expiry, $email);
        $update->execute();
        $update->close();

        // Email reset link (change the URL below)
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/gcpulseeee/reset_password.php?token=$token";

        $subject = "Password Reset - GC Pulse";
        $message = "Click the link to reset your password: $reset_link\n\nThis link expires in 30 minutes.";
        $headers = "From: no-reply@gordoncollege.edu.ph";

        require 'send_mail.php';

$subject = "Password Reset - GC Pulse";
$body = '
<table cellpadding="0" cellspacing="0" width="100%" style="background-color: #f5f7fa; padding: 40px 0;">
  <tr>
    <td align="center">
      <table cellpadding="0" cellspacing="0" width="480" style="background-color: #ffffff; padding: 40px; border-radius: 8px; font-family: Arial, sans-serif;">
        <tr>
          <td align="center" style="padding-bottom: 24px;">
            <h2 style="margin: 0; color: #2e4e33;">Reset your GC Pulse password</h2>
          </td>
        </tr>
        <tr>
          <td style="font-size: 15px; color: #444444; padding-bottom: 20px;">
            We received a request to reset your password. Click the button below to choose a new one:
          </td>
        </tr>
        <tr>
          <td align="center" style="padding-bottom: 30px;">
            <a href="' . $reset_link . '" style="background-color: #2e4e33; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold;">Reset Password</a>
          </td>
        </tr>
        <tr>
          <td style="font-size: 14px; color: #666666;">
            If you didn’t request this, you can safely ignore this email. This link will expire in 30 minutes.
          </td>
        </tr>
        <tr>
          <td style="padding-top: 30px; font-size: 13px; color: #999999; text-align: center;">
            © ' . date('Y') . ' GC Pulse. All rights reserved.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
';


if (sendMail($email, $subject, $body)) {
    $success = "A password reset link has been sent to your email.";
} else {
    $error = "Failed to send email. Please try again later.";
}
 // or use PHPMailer

        $success = "A password reset link has been sent to your email.";
    } else {
        $error = "No account found with that email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .container {
    max-width: 450px;
    padding: 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

body {
    font-family: apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    background: #f5f7fa;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    margin: 0;
    padding: 1rem;
    background: linear-gradient(135deg,  #418C4C, #074D34);
}

h2 {
    font-size: 28px;
    color: #2e4e33;
    margin-bottom: 0.5rem;
    text-align: center;
}

.subtext {
    color: #666;
    text-align: center;
    margin-bottom: 2rem;
    font-size: 15px;
}

input[type="email"] {
    width: 400px;
    padding: 12px 16px;
    border: 2px solid #e1e5ea;
    border-radius: 8px;
    font-size: 15px;
    margin-bottom: 1.5rem;
    transition: border-color 0.2s;
}

input[type="email"]:focus {
    border-color: #2e4e33;
    outline: none;
}

button {
    width: 100%;
    background-color: #2e4e33;
    color: white;
    padding: 14px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

button:hover {
    background-color: #1B3B20;
}

.message {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    font-size: 14px;
    text-align: center;
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

.back-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    margin-top: 1.5rem;
    color: #2e4e33;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
}

.back-link:hover {
    text-decoration: underline;
}

.icon {
    font-size: 20px;
}
    </style>
</head>
<body>

<div class="container">
    <h2>Password reset</h2>
    <p class="subtext">You will receive instructions for resetting your password.</p>

    <?php if (!empty($error)): ?>
        <p class="message error"><?= $error ?></p>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <p class="message success"><?= $success ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" required placeholder="Your email address">
        <button type="submit">SEND</button>
    </form>

    <a href="login.php" class="back-link">← Back to login</a>
</div>

</body>
</html>

