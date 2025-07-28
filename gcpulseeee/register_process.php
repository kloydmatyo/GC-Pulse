<?php
session_start();
include("db.php");

// Include PHPMailer at the top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Get user input
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$token = bin2hex(random_bytes(32));
$role = 'user';
$is_verified = 0;

// Check if email already exists
$stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    header("Location: register.php?error=Email already registered.");
    exit;
}

// Insert the new user into the database
$stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_verified, verification_token) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssis", $username, $email, $password, $role, $is_verified, $token);

if ($stmt->execute()) {
    // Send the verification email
    $verify_link = "http://" . $_SERVER['HTTP_HOST'] . "/gcpulseeee/verify.php?token=$token";

    // Initialize PHPMailer object BEFORE using it
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'kloydmatyo@gmail.com'; // Your Gmail address
        $mail->Password = 'froe rgbb jrfs dqyl'; // Your Gmail App Password (NOT Gmail login password)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587; // TCP port to connect to

        // Sender and recipient
        $mail->setFrom('kloydmatyo@gmail.com', 'GC Pulse');
        $mail->addAddress($email, $username); // Add the recipient's email

        // Email content
        $mail->isHTML(true); // Set email format to HTML
        $mail->Subject = 'Verify your email address';
        $mail->Body    = '<p>Hi ' . $username . ',</p><p>Click the link below to verify your email:</p><p><a href="' . $verify_link . '">Verify your email</a></p>';
        $mail->AltBody = 'Hi ' . $username . ',\n\nClick the link below to verify your email:\n' . $verify_link;

        // Send the email
        $mail->send();

        // Redirect with success message
        header("Location: register.php?success=Verification email sent. Please check your inbox.");
        exit;
    } catch (Exception $e) {
        // Redirect with error message if email fails
        header("Location: register.php?error=Email could not be sent. Error: {$mail->ErrorInfo}");
        exit;
    }
} else {
    // If insert failed, redirect with error message
    header("Location: register.php?error=Registration failed. Please try again.");
    exit;
}
?>
