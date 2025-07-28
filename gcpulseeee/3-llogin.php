<?php
// (A) ALREADY SIGNED IN
session_start();
if (isset($_SESSION["token"])) {
  header("Location: 4-home.php"); exit;
}
 
// (B) ON LOGIN - PUT TOKEN INTO SESSION
require "2-google.php";
if (isset($_GET["code"])) {
  $token = $goo->fetchAccessTokenWithAuthCode($_GET["code"]);
  if (!isset($token["error"])) {
    $_SESSION["token"] = $token;
    header("Location: 4-home.php"); exit;
  }
}

// (C) SHOW LOGIN PAGE ?>
<!DOCTYPE html>
<html>
  <head>
    <title>Login With Google</title>
  </head>
  <body>
    <?php if (isset($token["error"])) { ?>
    <div><?= print_r($token); ?></div>
    <?php } ?>
 
    <a href="<?= $goo->createAuthUrl() ?>">Login with Google</a>
  </body>
</html>