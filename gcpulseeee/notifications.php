<?php
include("session_check.php");
include("db.php");

$user_id = $_SESSION['user_id'];

// Get unread notifications
$query = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
?>

<h2>Notifications</h2>
<?php if ($result->num_rows > 0): ?>
    <ul>
        <?php while ($row = $result->fetch_assoc()): ?>
            <li>
                <?php echo htmlspecialchars($row['message']); ?> - 
                <a href="mark_read.php?notification_id=<?php echo $row['notification_id']; ?>">Mark as Read</a>
            </li>
        <?php endwhile; ?>
    </ul>
<?php else: ?>
    <p>No new notifications.</p>
<?php endif; ?>
