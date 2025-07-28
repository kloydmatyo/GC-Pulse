<?php
include("session_check.php");
include("db.php");

if (!isset($_GET['post_id'])) {
    echo "Invalid request.";
    exit();
}

$post_id = $_GET['post_id'];

// Fetch post
$query = $conn->prepare("SELECT posts.*, users.username FROM posts JOIN users ON posts.user_id = users.user_id WHERE post_id = ?");
$query->bind_param("i", $post_id);
$query->execute();
$post = $query->get_result()->fetch_assoc();

// Fetch likes count
$likes_count = $conn->query("SELECT COUNT(*) as total FROM likes WHERE post_id = $post_id")->fetch_assoc()['total'];

// Fetch comments
$comments_query = $conn->prepare("SELECT comments.*, users.username FROM comments JOIN users ON comments.user_id = users.user_id WHERE post_id = ? ORDER BY created_at ASC");
$comments_query->bind_param("i", $post_id);
$comments_query->execute();
$comments_result = $comments_query->get_result();
?>

<h2><?php echo htmlspecialchars($post['title']); ?></h2>
<p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
<p><strong>By:</strong> <?php echo htmlspecialchars($post['username']); ?></p>
<p><strong>Likes:</strong> <?php echo $likes_count; ?></p>

<!-- Like/Unlike Button -->
<form action="like_post.php" method="POST">
    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
    <button type="submit">Like/Unlike</button>
</form>

<!-- Comments Section -->
<h3>Comments</h3>
<form action="comment_post.php" method="POST">
    <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
    <textarea name="content" required></textarea>
    <button type="submit">Comment</button>
</form>

<?php while ($comment = $comments_result->fetch_assoc()): ?>
    <p><strong><?php echo htmlspecialchars($comment['username']); ?>:</strong> <?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
<?php endwhile; ?>

<?php
$comments = $conn->prepare("SELECT post_comments.comment, users.username, post_comments.created_at FROM post_comments JOIN users ON post_comments.user_id = users.user_id WHERE post_id = ? ORDER BY created_at DESC");
$comments->bind_param("i", $post_id);
$comments->execute();
$result = $comments->get_result();

while ($row = $result->fetch_assoc()) {
    echo "<p><strong>{$row['username']}</strong>: {$row['comment']} <small>({$row['created_at']})</small></p>";
}
?>

<?php
$result = $conn->query("SELECT * FROM posts ORDER BY created_at DESC");

while ($row = $result->fetch_assoc()) {
    echo "<h3>{$row['title']}</h3>";
    echo "<p>Category: <strong>" . ucfirst($row['category']) . "</strong></p>";
    echo "<p>{$row['content']}</p>";
    echo "<hr>";
}
?>
