<?php
include("session_check.php");
include("db.php");
checkRole(['organization', 'admin']);

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// If admin, show all posts; otherwise, show only user's posts
$query = ($role === 'admin') ? 
    "SELECT posts.*, users.username FROM posts JOIN users ON posts.user_id = users.user_id ORDER BY created_at DESC" :
    "SELECT posts.*, users.username FROM posts JOIN users ON posts.user_id = users.user_id WHERE posts.user_id = ? ORDER BY created_at DESC";

    $condition = ($_SESSION['role'] == 'admin') ? "" : "WHERE user_id = $user_id";

$posts = $conn->query("SELECT * FROM posts $condition ORDER BY created_at DESC");

$stmt = $conn->prepare($query);
if ($role !== 'admin') {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();

// Add category counting code
$categoryQuery = "SELECT category, COUNT(*) as count FROM posts";
if ($role !== 'admin') {
    $categoryQuery .= " WHERE user_id = $user_id";
}
$categoryQuery .= " GROUP BY category";
$categoryResult = $conn->query($categoryQuery);
$categories = [];
while ($cat = $categoryResult->fetch_assoc()) {
    $categories[$cat['category']] = $cat['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts - GC Pulse</title>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.css' rel='stylesheet' />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="body.css">
    <style>
                body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
            background-color: #f5f7fa;
        }
        .content-wrapper {
            margin-left: 250px;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .add-post-btn {
            background-color: #0066ff;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }

        .posts-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .posts-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 500;
            color: #444;
        }

        .posts-table td {
            padding: 12px;
            border-top: 1px solid #eee;
        }

        .action-buttons a {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            margin-right: 8px;
            font-size: 14px;
        }

        .edit-btn {
            background-color: #f0f0f0;
            color: #333;
        }

        .delete-btn {
            background-color: #fff0f0;
            color: #dc3545;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .category-cards {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.category-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    flex: 1;
    min-width: 150px;
    transition: box-shadow 0.3s ease, transform 0.3s ease; /* Added transition */
}

.category-card h3 {
    margin: 0;
    color: #444;
    font-size: 14px;
    font-weight: 500;
}

.category-card .count {
    font-size: 24px;
    font-weight: 600;
    color: #0066ff;
    margin: 10px 0 0 0;
}
.view-btn {
    background-color: #e8f0ff;
    color: #0066ff;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border-radius: 8px;
    width: 70%;
    max-width: 570px;
    position: relative;
}

.close-modal {
    position: absolute;
    right: 20px;
    top: 15px;
    font-size: 28px;
    cursor: pointer;
    color: #666;
}

.post-details {
    margin-top: 20px;
}

.post-details h2 {
    margin-bottom: 15px;
    color: #333;
}

.post-meta {
    color: #666;
    font-size: 14px;
    margin-bottom: 20px;
}

.post-content {
    line-height: 1.6;
    color: #444;
}
.post-image {
    max-width: 100%;
    height: auto;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.delete-confirmation {
    text-align: center;
    padding: 20px;
}

.delete-confirmation h2 {
    color: #dc3545;
    margin-bottom: 15px;
}

.delete-confirmation p {
    margin-bottom: 20px;
    color: #666;
}

.delete-actions {
    display: flex;
    justify-content: center;
    gap: 15px;
}

.cancel-btn, .confirm-delete-btn {
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    border: none;
    font-size: 14px;
}

.cancel-btn {
    background-color: #f0f0f0;
    color: #333;
}

.confirm-delete-btn {
    background-color: #dc3545;
    color: white;
}
        .category-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        .table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Hide scrollbar for WebKit (Chrome, Safari) */
.table-container::-webkit-scrollbar {
    display: none;
}

/* Hide scrollbar for Firefox */
.table-container {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none;  /* IE and Edge */
    height: 730px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.posts-table {
    min-width: 1000px; /* Adjust width as needed */
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.posts-table tr:hover {
    background-color: #e6f0e8; /* Light blue shade for hover */
    cursor: pointer;
}
    </style>
</head>
<body>
        <div class="sidebar">
        <div class="logo">
            <h1 >GC Pulse</h1>
        </div>
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="users.php"><i class="fa-solid fa-users">‌</i> Users</a>
        <a href="manage_appointment.php"><i class="fas fa-calendar"></i> Appointments</a>
        <a href="manage_letters.php"><i class="fas fa-envelope"></i> Proposals</a>
        <a href="manage_post.php"><i class="fa-solid fa-laptop">‌</i> Posts</a>
        
    </div>
    
    <div class="content-wrapper">
        <div class="page-header">
            <h2>Manage Posts</h2>
        </div>
<div class="category-cards">
    <?php foreach ($categories as $category => $count): ?>
    <div class="category-card">
        <h3><?php echo ucfirst(htmlspecialchars($category)); ?></h3>
        <p class="count"><?php echo $count; ?></p>
    </div>
    <?php endforeach; ?>
    <div class="category-card">
        <h3>Total Posts</h3>
        <p class="count"><?php echo array_sum($categories); ?></p>
    </div>
</div>
<div class="table-container">
        <table class="posts-table">
            <tr>
                <th>Title</th>
                <th>Content</th>
                <th>Category</th>
                <th>Author</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($row['title']); ?></strong>
                </td>
                <td><?php echo substr(htmlspecialchars($row['content']), 0, 100) . '...'; ?></td>
                <td>
                    <span class="status-badge">
                        <?php echo ucfirst($row['category']); ?>
                    </span>
                </td>
                <td><?php echo htmlspecialchars($row['username']); ?></td>
                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                <td class="action-buttons">
<a href="#" class="view-btn" onclick='viewPost(<?php echo json_encode([
    "title" => htmlspecialchars($row["title"]),
    "content" => htmlspecialchars($row["content"]),
    "category" => ucfirst($row["category"]),
    "author" => htmlspecialchars($row["username"]),
    "date" => date("M d, Y", strtotime($row["created_at"])),
    "image" => $row["image_path"] ?? null // Make sure image is being passed correctly
]); ?>)'>
    <i class="fas fa-eye"></i> 
</a>

 <a href="#" onclick="showDeleteModal(<?php echo $row['post_id']; ?>)" class="delete-btn">
    <i class="fas fa-trash"></i> 
</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
                    </div>

<div id="postModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <div class="post-details">
            <h2 id="modalTitle"></h2>
            <div class="post-meta">
                <span id="modalCategory"></span> | 
                <span id="modalAuthor"></span> | 
                <span id="modalDate"></span>
            </div>
            <div class="post-content" id="modalContent"></div>
        </div>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
        <div class="delete-confirmation">
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this post? This action cannot be undone.</p>
            <div class="delete-actions">
                <button class="cancel-btn" onclick="closeDeleteModal()">Cancel</button>
                <a href="#" id="confirmDelete" class="confirm-delete-btn">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
const deleteModal = document.getElementById('deleteModal');

function showDeleteModal(postId) {
    deleteModal.style.display = 'block';
    document.getElementById('confirmDelete').href = 'delete_post2.php?post_id=' + postId;
}

function closeDeleteModal() {
    deleteModal.style.display = 'none';
}

// Add to existing window.onclick function
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
    if (event.target == deleteModal) {
        deleteModal.style.display = 'none';
    }
}
</script>
<script>
const modal = document.getElementById('postModal');
const closeBtn = document.querySelector('.close-modal');

function viewPost(postData) {
    const modalContent = document.getElementById('modalContent');
    modalContent.innerHTML = ''; // Clear existing content

    console.log("Image Data:", postData.image); // Debugging line to check the image data

    // Check if image exists
    if (postData.image) {
        const imgElement = document.createElement('img');
        imgElement.src = postData.image; // Use the image path without 'uploads/' here
        imgElement.className = 'post-image';
        modalContent.appendChild(imgElement);

        // Log the final image URL
        console.log("Final Image URL:", imgElement.src);  // Should show something like 'uploads/image.jpg'
    } else {
        console.log("No image data available.");
    }

    // Add post content
    const contentDiv = document.createElement('div');
    contentDiv.innerHTML = postData.content;
    modalContent.appendChild(contentDiv);

    // Fill other fields
    document.getElementById('modalTitle').textContent = postData.title;
    document.getElementById('modalCategory').textContent = postData.category;
    document.getElementById('modalAuthor').textContent = postData.author;
    document.getElementById('modalDate').textContent = postData.date;

    modal.style.display = 'block';
}


closeBtn.onclick = function() {
    modal.style.display = 'none';
}

window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}
</script>
</body>
</html>
