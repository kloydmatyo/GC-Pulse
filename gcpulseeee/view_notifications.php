<?php
include("session_check.php");
include("db.php");

$user_id = $_SESSION['user_id'];
$notifications = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notifications->bind_param("i", $user_id);
$notifications->execute();
$result = $notifications->get_result();
?>

<h2>Your Notifications</h2>
<table>
    <tr>
        <th>Message</th>
        <th>Date</th>
        <th>Actions</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['message']; ?></td>
        <td><?php echo $row['created_at']; ?></td>
        <td>
            <a href="mark_read.php?id=<?php echo $row['id']; ?>">Mark as Read</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
