<?php
include("session_check.php");
include("db.php");

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$date = $_GET['date'] ?? '';

// Build SQL query dynamically
$query = "SELECT * FROM posts WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (title LIKE '%$search%' OR content LIKE '%$search%')";
}
if (!empty($category)) {
    $query .= " AND category = '$category'";
}
if (!empty($date)) {
    $query .= " AND DATE(created_at) = '$date'";
}

$result = $conn->query($query);
?>

<!-- Search Form -->
<form method="GET" action="search_posts.php">
    <input type="text" name="search" placeholder="Search by keyword" value="<?php echo $search; ?>">

    <select name="category">
        <option value="">All Categories</option>
        <option value="event" <?php if ($category == "event") echo "selected"; ?>>Event</option>
        <option value="announcement" <?php if ($category == "announcement") echo "selected"; ?>>Announcement</option>
        <option value="news" <?php if ($category == "news") echo "selected"; ?>>News</option>
    </select>

    <input type="date" name="date" value="<?php echo $date; ?>">
    <button type="submit">Search</button>
</form>
<h2>Search Results</h2>
<table>
    <tr>
        <th>Title</th>
        <th>Category</th>
        <th>Date</th>
        <th>Actions</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?php echo $row['title']; ?></td>
        <td><?php echo ucfirst($row['category']); ?></td>
        <td><?php echo $row['created_at']; ?></td>
        <td><a href="view_post.php?id=<?php echo $row['post_id']; ?>">View</a></td>
    </tr>
    <?php endwhile; ?>
</table>
