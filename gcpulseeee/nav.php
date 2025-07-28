<?php
session_start();
?>

<nav>
    <a href="index.php">Home</a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    <?php else: ?>
        <a href="login.html">Login</a>
        <a href="register.html">Register</a>
    <?php endif; ?>
</nav>
