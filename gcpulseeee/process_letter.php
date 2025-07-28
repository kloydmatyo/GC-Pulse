<?php
include("session_check.php");
include("db.php");

$letter_id = $_POST['letter_id'];
$status = $_POST['status'];
$response = $_POST['response'];

$conn->query("INSERT INTO notifications (user_id, message) 
              VALUES ((SELECT organization_id FROM letters WHERE id = $letter_id), 
              'Your letter has been $status. Response: $response')");


header("Location: review_letters.php");
?>
