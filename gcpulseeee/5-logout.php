<?php
// (A) NOT LOGGED IN
session_start();
if (!isset($_SESSION["token"])) {
  header("Location: login.php"); exit;
}

// (B) REMOVE & REVOKE TOKEN
require "2-google.php";
$goo->setAccessToken($_SESSION["token"]);
$goo->revokeToken();

// OPTIONAL: Delay to avoid hitting rate limits
sleep(1); // Delay by 1 second (or adjust as needed)

unset($_SESSION["token"]);
// REMOVE YOUR OWN USER SESSION VARIABLES AS WELL
header("Location: login.php"); exit;
?>
