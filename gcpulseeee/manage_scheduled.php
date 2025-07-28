<?php
include("session_check.php");
include("db.php");

$user_id = $_SESSION['user_id'];
$posts = $conn->prepare("SELECT * FROM posts WHERE user_id = ? AND scheduled_at IS NOT NULL ORDER BY scheduled_at ASC");
$posts->bind_param("i", $user_id);
$posts->execute();
$result = $posts->get_result();
?>

<h2>Scheduled Posts</h2>
<table>
    <tr>
        <th>Title</th>
        <th>Category</th>
        <th>Scheduled Date</th>
        <th>Actions</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['title']; ?></td>
        <td><?php echo ucfirst($row['category']); ?></td>
        <td><?php echo $row['scheduled_at']; ?></td>
        <td>
            <a href="edit_post.php?id=<?php echo $row['post_id']; ?>">Edit</a>
            <a href="delete_post.php?id=<?php echo $row['post_id']; ?>">Delete</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
