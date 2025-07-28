<?php
include("session_check.php");
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $scheduled_at = $_POST['scheduled_at'];

    $status = $scheduled_at ? 'scheduled' : 'published';

    $query = $conn->prepare("INSERT INTO posts (user_id, title, content, category, scheduled_at, status) VALUES (?, ?, ?, ?, ?, ?)");
    $query->bind_param("isssss", $user_id, $title, $content, $category, $scheduled_at, $status);

    if ($query->execute()) {
        echo "Post scheduled successfully!";
    } else {
        echo "Error: " . $query->error;
    }
}
?>

<!-- HTML Form -->
<form action="schedule_post.php" method="POST">
    <label>Title:</label>
    <input type="text" name="title" required>
    
    <label>Content:</label>
    <textarea name="content" required></textarea>
    
    <label>Category:</label>
    <select name="category">
        <option value="event">Event</option>
        <option value="announcement">Announcement</option>
        <option value="news">News</option>
    </select>

    <label>Schedule Date (Optional):</label>
    <input type="datetime-local" name="scheduled_at">

    <button type="submit">Schedule Post</button>
</form>
