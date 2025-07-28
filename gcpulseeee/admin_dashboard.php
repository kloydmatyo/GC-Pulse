<?php
include("session_check.php");
checkRole(['admin']);

echo "<h1>Admin Dashboard</h1>";
echo "<a href='manage_users.php'>Manage Users</a>";
echo "<a href='manage_posts.php'>Manage Posts</a>";
?>
