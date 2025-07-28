<?php
include("session_check.php");
include("db.php");

$org_id = $_SESSION['user_id']; // Organization ID

// Total posts per category
$posts_query = $conn->prepare("
    SELECT category, COUNT(*) AS total 
    FROM posts 
    WHERE user_id = ? 
    GROUP BY category
");
$posts_query->bind_param("i", $org_id);
$posts_query->execute();
$posts_result = $posts_query->get_result();

$posts_data = [];
while ($row = $posts_result->fetch_assoc()) {
    $posts_data[$row['category']] = $row['total'];
}

// Total engagement (likes + comments per post)
$engagement_query = $conn->prepare("
    SELECT p.title, 
        (SELECT COUNT(*) FROM likes WHERE post_id = p.post_id) AS likes, 
        (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) AS comments
    FROM posts p
    WHERE p.user_id = ?
");
$engagement_query->bind_param("i", $org_id);
$engagement_query->execute();
$engagement_result = $engagement_query->get_result();

$engagement_data = [];
while ($row = $engagement_result->fetch_assoc()) {
    $engagement_data[] = $row;
}

// Monthly activity trends (posts per month)
$trends_query = $conn->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total 
    FROM posts 
    WHERE user_id = ? 
    GROUP BY month
");
$trends_query->bind_param("i", $org_id);
$trends_query->execute();
$trends_result = $trends_query->get_result();

$trends_data = [];
while ($row = $trends_result->fetch_assoc()) {
    $trends_data[$row['month']] = $row['total'];
}

$data = [
    "posts" => $posts_data,
    "engagement" => $engagement_data,
    "trends" => $trends_data
];

echo json_encode($data);
?>
