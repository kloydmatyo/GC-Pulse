<?php
include("session_check.php");
include("db.php");

$user_id = $_SESSION['user_id'];

$query = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows > 0):
    while ($row = $result->fetch_assoc()):
?>
    <div class="notif-item">
        <?= htmlspecialchars($row['message']) ?>
        <a href="mark_read.php?notification_id=<?= $row['notification_id'] ?>" class="mark-read">✓</a>
    </div>
<?php
    endwhile;
else:
?>
    <p>No new notifications.</p>
<?php endif; ?>
