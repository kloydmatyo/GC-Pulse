<?php
include("session_check.php");
include("db.php");

$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$query = "SELECT * FROM posts WHERE status = 'published'";

// Keyword Search
if (!empty($keyword)) {
    $query .= " AND (title LIKE '%$keyword%' OR content LIKE '%$keyword%')";
}

// Category Filter
if (!empty($category)) {
    $query .= " AND category = '$category'";
}

// Date Range Filter
if (!empty($start_date) && !empty($end_date)) {
    $query .= " AND created_at BETWEEN '$start_date' AND '$end_date'";
} elseif (!empty($start_date)) {
    $query .= " AND created_at >= '$start_date'";
} elseif (!empty($end_date)) {
    $query .= " AND created_at <= '$end_date'";
}

$result = $conn->query($query);

echo "<h1>🔍 Search Results</h1>";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<h3>{$row['title']}</h3>";
        echo "<p>{$row['content']}</p>";
        echo "<p>Category: {$row['category']}</p>";
        echo "<p>Published on: {$row['created_at']}</p>";
        echo "<hr>";
    }
} else {
    echo "<p>No results found.</p>";
}

$limit = 10; // Number of results per page
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$query .= " LIMIT $limit OFFSET $offset";

$result = $conn->query($query);

// Pagination Controls
$total_results = $conn->query("SELECT COUNT(*) as total FROM posts WHERE status = 'published'")->fetch_assoc()['total'];
$total_pages = ceil($total_results / $limit);

if ($page > 1) {
    echo "<a href='search_results.php?page=".($page-1)."&keyword=$keyword&category=$category&start_date=$start_date&end_date=$end_date'>⏪ Previous</a> ";
}
if ($page < $total_pages) {
    echo "<a href='search_results.php?page=".($page+1)."&keyword=$keyword&category=$category&start_date=$start_date&end_date=$end_date'>Next ⏩</a>";
}

?>
