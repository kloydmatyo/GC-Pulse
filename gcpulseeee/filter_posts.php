<?php
include("session_check.php");
include("db.php");

$category = $_GET['category'] ?? '';

$query = "SELECT * FROM posts";
if (!empty($category)) {
    $query .= " WHERE category = '$category'";
}

$result = $conn->query($query);
?>

<!-- Category Filter -->
<form method="GET" action="filter_posts.php">
    <select name="category" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <option value="event" <?php if ($category == "event") echo "selected"; ?>>Event</option>
        <option value="announcement" <?php if ($category == "announcement") echo "selected"; ?>>Announcement</option>
        <option value="news" <?php if ($category == "news") echo "selected"; ?>>News</option>
    </select>
</form>

<h2>Filtered Posts</h2>
<?php while ($row = $result->fetch_assoc()): ?>
    <h3><?php echo $row['title']; ?></h3>
    <p>Category: <strong><?php echo ucfirst($row['category']); ?></strong></p>
    <p><?php echo $row['content']; ?></p>
    <hr>
<?php endwhile; ?>
