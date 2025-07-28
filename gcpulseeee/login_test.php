<?php
session_start();
include("db.php");
require "2-google.php";

$error = "";

// (A) CHECK IF USER IS ALREADY LOGGED IN
if (isset($_SESSION['user_id']) || isset($_SESSION["token"])) {
    redirectBasedOnRole($role);
    exit;
}

// (B) GOOGLE LOGIN FLOW
if (isset($_GET["code"])) {
    $token = $goo->fetchAccessTokenWithAuthCode($_GET["code"]);

    if (!isset($token["error"])) {
        // Get user info from Google
        $oauth2 = new Google\Service\Oauth2($goo);
        $google_user = $oauth2->userinfo->get();

        $email = $google_user['email'];
        $firstname = $google_user['givenName'];
        $lastname = $google_user['familyName'];
        $username = explode('@', $email)[0];
        $role = inferRoleFromEmail($email);


    // ✅ Restrict to @gordoncollege.edu.ph emails only
    //if (!str_ends_with($email, '@gordoncollege.edu.ph')) {
       // $_SESSION['error_message'] = "Only @gordoncollege.edu.ph accounts are allowed.";
       // header("Location: login.php");
       // exit;
   // }


        // Check if user exists
        $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $stmt->bind_result($user_id, $username);
        $stmt->fetch();

        if ($stmt->num_rows > 0) {
            // Existing user, update role
            $update_stmt = $conn->prepare("UPDATE users SET role = ? WHERE email = ?");
            $update_stmt->bind_param("ss", $role, $email);
            $update_stmt->execute();

            $_SESSION['user_id'] = $user_id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION["token"] = $token;
            redirectBasedOnRole($role);
        } else {
            // New user - register
            $stmt = $conn->prepare("INSERT INTO users (username, email, firstname, lastname, role, is_verified, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
            $stmt->bind_param("sssss", $username, $email, $firstname, $lastname, $role);

            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;

                $_SESSION['user_id'] = $new_user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $_SESSION["token"] = $token;
                redirectBasedOnRole($role);
            } else {
                $_SESSION['error_message'] = "Auto-registration failed. Please try again.";
                header("Location: login.php");
            }
        }
    }
}

function inferRoleFromEmail($email) {
    $parts = explode('@', $email);
    $local = $parts[0];
    $domain = $parts[1];

    // Student ID
    if (preg_match('/^\d{6,}$/', $local)) {
        return 'student';
    }

    // Faculty email
    if (preg_match('/^[a-z]+\.[a-z]+$/', $local)) {
        return 'faculty';
    }

    // Organizations
    if (strpos($local, 'gcccs') !== false || strpos($local, 'elites') !== false) {
        return 'organization';
    }

    return 'student'; // default fallback
}




function redirectBasedOnRole($role) {
    switch ($role) {
        case 'admin':
            header("Location: index.php");
            break;
        case 'faculty':
            header("Location: index.php");
            break;
        case 'council':
            header("Location: Cindex.php");
            break;
        case 'elite':
            header("Location: Eindex.php");
            break;
        case 'student':
        default:
            header("Location: Sindex.php");
            break;
    }
    exit;
}

// (C) MANUAL EMAIL/PASSWORD LOGIN
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

$query = $conn->prepare("SELECT user_id, username, password, role, is_verified FROM users WHERE email = ?");
$query->bind_param("s", $email);
$query->execute();
$query->store_result();
$query->bind_result($user_id, $username, $hashed_password, $role, $is_verified);
$query->fetch();

if ($query->num_rows > 0 && password_verify($password, $hashed_password)) {
    if (!$is_verified) {
        $error = "Please verify your email before logging in.";
    } else {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        redirectBasedOnRole($role);
        exit;
    }
} else {
    $error = "Invalid email or password!";
}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="logincss.css?v=3">
</head>
<body>
<div class="login-wrapper">
    <div class="login-left">
        <h1>Welcome to GC Pulse!</h1>
        <img src="../gcpulseeee/img/pulse_white.png" alt="Logo">
    </div>
    <div class="login-right">
        <h2>Sign In</h2>

        <?php if (!empty($error)): ?>
            <p class="error-msg"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <p class="error-msg"><?= htmlspecialchars($_SESSION['error_message']) ?></p>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Manual Login Form -->
        <form action="login.php" method="POST">
            <input type="email" name="email" placeholder="Username or email" required>
            <input type="password" name="password" placeholder="Password" required>

            <div class="options">
                <a href="forgot_password.php">Forgot password?</a>

            </div>

            <button type="submit">Log In</button>
        </form>

        <!-- Google Login Button -->
        <div class="center-container" style="margin-top: 20px;">
            <a href="<?= $goo->createAuthUrl() ?>" class="google-login-btn">
                <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo">
                Sign in with Google
            </a>
        </div>
    </div>
</div>
</body>
</html>