<?php
include("session_check.php");
include("db.php");

$user_id = $_SESSION['user_id'];

// Fetch user's letters
$letters = $conn->prepare("SELECT * FROM letters WHERE user_id = ? ORDER BY created_at DESC");
$letters->bind_param("i", $user_id);
$letters->execute();
$result = $letters->get_result();
?>

<h2>Your Letters</h2>
<table>
    <tr>
        <th>Title</th>
        <th>Status</th>
        <th>Response</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['title']; ?></td>
        <td><?php echo ucfirst($row['status']); ?></td>
        <td><?php echo $row['response'] ? $row['response'] : "No response yet"; ?></td>
    </tr>
    <?php endwhile; ?>
</table>
