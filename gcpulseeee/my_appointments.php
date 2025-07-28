<?php
include("session_check.php");
include("db.php");

$user_id = $_SESSION['user_id'];
$query = $conn->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date ASC");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
?>

<h2>My Appointments</h2>
<table border="1">
    <tr>
        <th>Date</th>
        <th>Status</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['appointment_date']; ?></td>
        <td><?php echo ucfirst($row['status']); ?></td>
    </tr>
    <?php endwhile; ?>
</table>
