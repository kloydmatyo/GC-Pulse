<?php
session_start();
include("db.php");
require "2-google.php";

// (A) GOOGLE LOGOUT FLOW
if (isset($_SESSION["token"])) {
    // Revoke Google OAuth token (optional, to log out the user from Google as well)
    $token = $_SESSION["token"];
    $google_client = $goo;
    $google_client->revokeToken($token['access_token']);
    unset($_SESSION["token"]);
}

// (B) DESTROY SESSION VARIABLES AND LOG OUT
session_unset();  // Remove all session variables
session_destroy();  // Destroy the session

// (C) REDIRECT TO LOGIN PAGE AFTER LOGOUT
header("Location: login.php");
exit;
?>
