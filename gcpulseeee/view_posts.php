<?php
include("session_check.php");
include("db.php");

$query = $conn->query("SELECT * FROM posts ORDER BY scheduled_at DESC, created_at DESC");

echo "<h2>All Posts</h2>";
while ($row = $query->fetch_assoc()) {
    echo "<h3>{$row['title']}</h3>";
    echo "<p>{$row['content']}</p>";
    echo "<p><strong>Status:</strong> " . ucfirst($row['status']) . "</p>";

    if ($row['scheduled_at']) {
        echo "<p><strong>Scheduled for:</strong> " . $row['scheduled_at'] . "</p>";
    }
    echo "<hr>";
}
?>

<?php
$current_time = date('Y-m-d H:i:s');
$query = "SELECT * FROM posts WHERE scheduled_at IS NULL OR scheduled_at <= '$current_time' ORDER BY created_at DESC";
$result = $conn->query($query);

while ($row = $result->fetch_assoc()) {
    echo "<h3>{$row['title']}</h3>";
    echo "<p>Category: <strong>" . ucfirst($row['category']) . "</strong></p>";
    echo "<p>{$row['content']}</p>";
    echo "<p><small>Published: {$row['scheduled_at']}</small></p>";
    echo "<hr>";
}
?>
