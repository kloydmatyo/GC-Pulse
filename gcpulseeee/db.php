<?php
$host = "localhost";  // Change to your database host if needed
$user = "root";       // MySQL username
$pass = "";           // MySQL password (leave empty if none)
$dbname = "gc_db"; // Your database name

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
