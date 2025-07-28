<?php
include("session_check.php"); // Assumes session_start() is already called inside
include("db.php");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch post count over time
$postData = [];
if ($role === 'admin') {
    $stmt = $conn->prepare("SELECT DATE(created_at) as date, COUNT(*) as count FROM posts GROUP BY date");
} else {
    $stmt = $conn->prepare("SELECT DATE(created_at) as date, COUNT(*) as count FROM posts WHERE user_id = ? GROUP BY date");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $postData[] = ["date" => $row['date'], "count" => (int)$row['count']];
}
$stmt->close();

// Fetch engagement metrics
if ($role === 'admin') {
    $stmt = $conn->prepare("SELECT SUM(likes) as likes, SUM(comments) as comments FROM posts");
} else {
    $stmt = $conn->prepare("SELECT SUM(likes) as likes, SUM(comments) as comments FROM posts WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
$engagement = $result->fetch_assoc();
$engagement = [
    "likes" => (int)$engagement['likes'],
    "comments" => (int)$engagement['comments']
];
$stmt->close();

// Fetch post categories
$categoryData = [];
if ($role === 'admin') {
    $stmt = $conn->prepare("SELECT category, COUNT(*) as count FROM posts GROUP BY category");
} else {
    $stmt = $conn->prepare("SELECT category, COUNT(*) as count FROM posts WHERE user_id = ? GROUP BY category");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categoryData[] = ["category" => $row['category'], "count" => (int)$row['count']];
}
$stmt->close();

echo json_encode([
    "posts" => $postData,
    "engagement" => $engagement,
    "categories" => $categoryData
]);
?>
