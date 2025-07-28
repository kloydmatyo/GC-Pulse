<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT role FROM users WHERE user_id = $user_id");
$user_role = $result->fetch_assoc()['role'];

// Function to check user role
function checkRole($allowed_roles) {
    global $user_role;
    if (!in_array($user_role, $allowed_roles)) {
        die("Access Denied");
    }
}
?>
