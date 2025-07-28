<?php
// (A) NOT LOGGED IN
session_start();
if (!isset($_SESSION["token"])) {
  header("Location: login.php"); exit;
}

// (B) TOKEN EXPIRED - TO LOGIN PAGE
require "2-google.php";
$goo->setAccessToken($_SESSION["token"]);
if ($goo->isAccessTokenExpired()) {
  unset($_SESSION["token"]);
  header("Location: login.php"); exit;
}

// (C) GET USER PROFILE
$user = (new Google_Service_Oauth2($goo))->userinfo->get();
?>

<!DOCTYPE html>
<html>
<head>
  <title>User Dashboard</title>
</head>
<body>
  <h1>Welcome, <?php echo htmlspecialchars($user->name); ?>!</h1>
  <p><strong>Email:</strong> <?php echo htmlspecialchars($user->email); ?></p>
  <p><img src="<?php echo htmlspecialchars($user->picture); ?>" alt="Profile Picture" width="100" height="100"></p>

  <!-- (D) LOGOUT BUTTON -->
  <form action="5-logout.php" method="post">
    <button type="submit">Logout</button>
  </form>
</body>
</html>
