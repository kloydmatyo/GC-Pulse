<?php
session_start();
include("db.php");


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
// Connect to your database

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Use the correct column name
$sql = "SELECT firstname, lastname FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($firstname, $lastname);
$stmt->fetch();
$stmt->close();



// Filter category
$category_filter = isset($_GET['category']) && $_GET['category'] != "" ? $_GET['category'] : null;

$query = "SELECT posts.*, users.username, 
                 (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id) AS like_count 
          FROM posts 
          JOIN users ON posts.user_id = users.user_id";
if ($category_filter) {
    $query .= " WHERE category = ?";
}
$stmt = $conn->prepare($query);
if ($category_filter) {
    $stmt->bind_param("s", $category_filter);
}
$stmt->execute();
$result = $stmt->get_result();

// Check which posts the user has liked
$user_likes = [];
if ($user_id) {
    $like_result = $conn->prepare("SELECT post_id FROM likes WHERE user_id = ?");
    $like_result->bind_param("i", $user_id);
    $like_result->execute();
    $like_data = $like_result->get_result();
    while ($row = $like_data->fetch_assoc()) {
        $user_likes[] = $row['post_id'];
    }
}

// Fetch comments for all posts
$comments_by_post = [];
$comment_query = $conn->query("
    SELECT comments.*, users.username
    FROM comments 
    JOIN users ON comments.user_id = users.user_id
    ORDER BY comments.created_at ASC
");
while ($comment = $comment_query->fetch_assoc()) {
    $comments_by_post[$comment['post_id']][] = $comment;
}
if (isset($_GET['notif'])) {
    $notif_id = intval($_GET['notif']);
    $mark_query = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
    $mark_query->bind_param("i", $notif_id);
    $mark_query->execute();
}

// Query to get the unread notifications count
$unread_count_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_count_stmt->bind_param("i", $user_id);
$unread_count_stmt->execute();
$unread_count_stmt->bind_result($unread_count);
$unread_count_stmt->fetch();
$unread_count_stmt->close();

// Fetch user's appointments for both table and calendar
$appointments = $conn->prepare("SELECT * FROM appointments ORDER BY appointment_date ASC");
$appointments->execute();
$table_result = $appointments->get_result();

while ($row = $table_result->fetch_assoc()) {
}

// Helper to nest threaded comments
function renderComments($comments, $parent_id = null) {
    $html = '';
    foreach ($comments as $comment) {
        if ($comment['parent_comment_id'] == $parent_id) {
            $html .= '<div class="comment">';
            $html .= '<img src="' . htmlspecialchars($comment['avatar'] ?? '../gcpulseeee/img/user-icon.png') . '" class="comment-avatar">';
            $html .= '<div class="comment-content">';
            $html .= '<strong>' . htmlspecialchars($comment['username']) . '</strong>';
            $html .= '<p>' . htmlspecialchars($comment['comment']) . '</p>';
            $html .= '<small>' . date('F j, Y H:i', strtotime($comment['created_at'])) . '</small>';
            $html .= '<div class="comment-actions">';
            $html .= '<button onclick="replyToComment(' . $comment['comment_id'] . ', ' . $comment['post_id'] . ')">Reply</button>';

            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $comment['user_id']) {
                $html .= '<button onclick="editComment(' . $comment['comment_id'] . ', \'' . htmlspecialchars($comment['comment']) . '\')">Edit</button>';
                $html .= '<form method="POST" action="delete_comment.php" style="display:inline;">
                            <input type="hidden" name="comment_id" value="' . $comment['comment_id'] . '">
                            <button type="submit">Delete</button>
                          </form>';
            }
            $html .= '</div>';
            $html .= renderComments($comments, $comment['comment_id']);
            $html .= '</div></div>';
        }
    }
    return $html ? '<div class="comment-thread">' . $html . '</div>' : '';
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GC Pulse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet"  href="../gcpulseeee/navbar.css">
    <link rel="stylesheet"  href="../gcpulseeee/notification.css">
    <link rel="stylesheet"  href="../gcpulseeee/body.css">
    <link rel="stylesheet"  href="../gcpulseeee/fab.css">
    <link rel="stylesheet"  href="../gcpulseeee/comment.css">
    <link rel="stylesheet"  href="../gcpulseeee/modal.css">
    <link rel="stylesheet"  href="../gcpulseeee/postcard.css">
    <link rel="stylesheet"  href="../gcpulseeee/responsive.css">
    <link rel="stylesheet"  href="../gcpulseeee/index.css">
    <link rel="stylesheet"  href="../gcpulseeee/category.css">
    <link rel="stylesheet"  href="../gcpulseeee/sidebarindex.css">
    <style>
/* Core Chatbox Container */
.chatbox {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 500px;
    height: 400px;
    background-color: #fff;
    border-radius: 8px;
    border: 1px solid #ddd;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1055;
    display: flex;
    flex-direction: column;
}

/* Header */
.chatbox-header {
    font-weight: 500;
    font-size: 16px;
    background-color: #355E3B !important;
    color: #fff;
    padding: 10px 12px;
    border-bottom: 1px solid #ddd;
    flex: 0 0 auto;
}

/* Close Button */
.chatbox-header .btn-close {
    background-color: transparent;
    border: none;
    opacity: 0.8;
}
.chatbox-header .btn-close:hover {
    opacity: 1;
}

/* Body */
.chatbox-body {
    padding: 12px;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    gap: 10px;
    overflow: hidden;
}

/* Input: To field */
.chatbox-body input {
    font-size: 14px;
    flex: 0 0 auto;
}

/* Textarea */
.chatbox-body textarea {
    font-size: 14px;
    resize: none;
    flex: 1 1 auto;
height: 240px;
    overflow-y: auto;
}

/* Send Button */
.chatbox-body button {
    font-size: 14px;
    background-color: #355E3B;
    border: #355E3B;
    color: white;
    flex: 0 0 auto;
    margin-top: 0;
}

.btn-primary {
    margin: 0;
}
.btn-primary:hover {
    background-color: #1B3B20;
}
.upcoming-appointments {
    margin-top: 1rem;

       background-color: #355E3B;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, .5); /* OUTSIDE shadow */
}

.appointments-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
    color:#fff;
}

.request-button {
    font-size: 0.875rem;
    padding: 0.25rem 0.5rem;
    background-color: #fff;
    border: none;
    border-radius: 4px;
    color: #000;
    cursor: pointer;
}

.request-button:hover {
    background-color: #ccc;
}

.appointment-card {
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 0.375rem;
    margin-bottom: 0.5rem;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.appointment-card-body {
    padding: 0.5rem;
}

.appointment-title {
    margin-bottom: 0.25rem;
    font-size: 1rem;
    font-weight: 600;
}

.appointment-time {
    margin: 0;
    font-size: 0.875rem;
    color: #6c757d;
}


</style>

</head>
<body>

<nav class="navbar navbar-expand-lg navbar -4 py-2">
    <!-- Left Section -->
    <div class="d-flex align-items-center">
 <form class="search-form-with-icon" method="GET">
    <div class="input-group">
        <span class="input-group-text bg-white border-end-0">
            <i class="fas fa-search text-muted"></i>
        </span>
        <input type="text" name="search" class="form-control border-start-0" placeholder="Search posts..." aria-label="Search">
    </div>
</form>

    </div>

    <!-- Spacer for center -->
<div class="flex-grow-1 text-center">
    <a href="index.php" class="text-white">
        <i class="fa-solid fa-house fs-4" role="button"></i>
    </a>
</div>

    <!-- Right Section -->
    <div class="d-flex align-items-center gap-3">
        <!-- Notification Icon -->
        <div class="position-relative">
            <i class="fas fa-bell text-white fs-4" id="notifIcon" role="button"></i>
            <!-- Notification Panel -->
            <div class="dropdown-menu dropdown-menu-end p-3" id="notifPanel" style="min-width: 300px; max-height: 400px; overflow-y: auto;">

                <h6 class="dropdown-header text-center">Notifications</h6>
                <ul class="list-unstyled mb-0">
                    <?php
                    $notif_query = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
                    $notif_query->bind_param("i", $user_id);
                    $notif_query->execute();
                    $notif_result = $notif_query->get_result();

                    if ($notif_result->num_rows > 0):
                        while ($notif = $notif_result->fetch_assoc()):
                    ?>
<li class="mb-2 <?= $notif['is_read'] ?>">
    <a href="javascript:void(0);" onclick="openPostFromNotif(<?= $notif['post_id'] ?>, <?= $notif['notification_id'] ?>)" 
       class="text-decoration-none text-dark d-flex align-items-start">
        <i class="fas fa-bell me-2"></i>
        <div>
            <div><?= htmlspecialchars_decode($notif['message']) ?></div>
            <small class="text-muted"><?= date('M j, g:i A', strtotime($notif['created_at'])) ?></small>
            
        </div>
    </a>
</li>

                    <?php endwhile; else: ?>
                        <li><div class="text-muted">No new notifications.</div></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- User Dropdown -->
<div class="dropdown">
    <a href="#" id="userIcon" class="text-white fs-4 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-user"></i> <!-- User icon -->
    </a>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
    <a class="dropdown-item" href="profile.php">
        <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>
    </a>
</li>
        <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
        <li><a class="dropdown-item" href="view_appointments.php">Appointments</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
    </ul>
</div>
    </div>
</nav>



<div class="layout-wrapper">
  <div class="sidebar" id="customSidebar">
  <div class="sidebar-content">
    <!-- User Profile Link -->
    <a href="profile.php" class="nav-link">
      <img src="../gcpulseeee/img/user-icon.png" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%;">
      <span><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></span>
    </a>

    <!-- Navigation Links -->
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item">
        <a href="index.php" class="nav-link">
          <i class="fas fa-home"></i>
          <span>Home</span>
        </a>
      </li>
      <li class="nav-item">
        <button class="nav-link" onclick="toggleComposeBox(event)">
          <i class="fas fa-pen"></i>
          <span>Compose</span>
        </button>
      </li>
      <li class="nav-item">
        <a href="dashboard.php" class="nav-link">
          <i class="fas fa-tachometer-alt"></i>
          <span>Dashboard</span>
        </a>
      </li>
      <li class="nav-item">
        <a href="view_appointments.php" class="nav-link">
          <i class="fas fa-calendar-alt"></i>
          <span>Appointments</span>
        </a>
      </li>
    </ul>

    <hr>

    <!-- Filter Section -->
    <div class="filter-section">
      <form method="GET" action="Sindex.php">
        <label class="form-label">Filter Posts</label>
        <select name="category" id="category" class="form-select" onchange="this.form.submit()">
          <option value="">All Posts</option>
          <option value="event" <?= ($category_filter == 'event') ? 'selected' : ''; ?>>Events</option>
          <option value="announcement" <?= ($category_filter == 'announcement') ? 'selected' : ''; ?>>Announcements</option>
          <option value="news" <?= ($category_filter == 'news') ? 'selected' : ''; ?>>News</option>
        </select>
      </form>
    </div>

    <!-- Upcoming Appointments -->
    <div class="upcoming-appointments">
      <div class="appointments-header">
        <strong>Upcoming Appointments</strong>
        <button type="button" class="btn btn-primary" onclick="openAppointmentModal()">
          <i class="fas fa-plus"></i>
        </button>
      </div>

      <?php 
      $table_result->data_seek(0); 
      $count = 0;
      while ($row = $table_result->fetch_assoc()): 
          if ($count >= 2) break;
          $count++;
      ?>
      <div class="appointment-card">
        <h6 class="appointment-title"><?= htmlspecialchars($row['description']); ?></h6>
        <p class="appointment-time">
          <?= date('F j, Y, g:i A', strtotime($row['appointment_date'])); ?>
        </p>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="flex-grow-1 p-4">
    <!-- Post prompt box -->
    <div class="main-content-box">
        <div class="post-box">
            <div class="post-input-section">
                <img src="path_to_user_profile_picture.jpg" alt="Profile" class="profile-img">
                <button class="post-input" onclick="openCreatePostModal()" title="New Post">Create Post...</button>
            </div>
        </div>
    </div>

    <!-- Post Cards Container -->
    <div class="d-flex justify-content-center">
        <div class="main-content-card">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="post-card">
                    <div class="post-header">
                        <img src="../gcpulseeee/img/user-icon.png" class="user-icon" />
                        <span class="username"><?= htmlspecialchars($row['username']) ?></span>
                        <span class="date"><?= date('F j, Y', strtotime($row['created_at'])) ?></span>
                        <div class="meatball-menu-container">
                            <button class="meatball-btn" onclick="toggleMenu(this)">⋯</button>
                            <div class="meatball-dropdown">
                                <button type="button" class="edit-post-btn"
                                    data-post-id="<?= $row['post_id'] ?>"
                                    data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
                                    data-content="<?= htmlspecialchars($row['content'], ENT_QUOTES) ?>"
                                    data-image="<?= htmlspecialchars($row['image_path'] ?? '', ENT_QUOTES) ?>">Edit
                                </button>
                                <form method="POST" action="delete_post.php" onsubmit="return confirm('Delete this post?');">
                                    <input type="hidden" name="post_id" value="<?= $row['post_id'] ?>">
                                    <button type="submit" class="delete-btn">Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="post-title"><?= htmlspecialchars($row['title']) ?></div>
                    <?php if (!empty($row['image_path'])): ?>
                        <img src="<?= htmlspecialchars($row['image_path']) ?>" class="post-image" />
                    <?php endif; ?>

                    <div class="content-container">
                        <span class="content short-content"><?= nl2br(htmlspecialchars($row['content'])) ?></span>
                        <button class="read-toggle-btn" style="display: none;" onclick="toggleReadMore(this)">Read more</button>
                    </div>

                    <hr class="divider">
                    <div class="post-actions">
                        <form action="toggle_like.php" method="POST" style="display:inline;">
                            <input type="hidden" name="post_id" value="<?= $row['post_id'] ?>">
                            <button type="submit" class="action-btn-like <?= in_array($row['post_id'], $user_likes) ? 'liked' : '' ?>">
                                <?= in_array($row['post_id'], $user_likes) ? 'Unlike ' : 'Like ' ?>
                            </button>
                            <!--<span class="like-count"><?= $row['like_count'] ?></span>-->
                        </form>
                        <button type="button" class="action-btn-comment" onclick="openModal(<?= $row['post_id'] ?>)"> Comment</button>
                    </div>

                    <button class="collapsible"> View Comments</button>
                    <div class="collapsible-content">
                        <?= isset($comments_by_post[$row['post_id']]) ? renderComments($comments_by_post[$row['post_id']]) : "<p>No comments yet.</p>" ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>


<!-- Floating Action Button System-->
<div class="fab-container">
    <div class="fab-buttons" id="fabButtons">
        <button class="fab-button" onclick="openCreatePostModal()" title="New Post">✏️</button>
        <button class="fab-button" onclick="openAppointmentModal()" title="Appointment">📅</button>
    </div>
    <button class="fab-main" id="fabMain">+</button>
</div>
<!-- CREATE POST MODAL -->
<div id="createPostModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeCreatePostModal()">&times;</span>
    <form action="create_post.php" method="POST" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="Title" required><br>
        <textarea name="content" placeholder="Content..." required></textarea><br>
        <select name="category" required>
            <option value="">Select Category</option>
            <option value="event">Event</option>
            <option value="announcement">Announcement</option>
            <option value="news">News</option>
        </select><br>
        
        <input type="file" name="image"><br>
        <button type="submit" style="color:white;"><strong>Post</strong></button>
    </form>
  </div>
</div>

<!-- Edit Modal -->

<div id="editPostModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closeEditPostModal()">&times;</span>
    <form action="edit_post.php" method="POST" enctype="multipart/form-data">

        <input type="hidden" name="post_id" id="editPostId">
        <input type="text" name="title" id="editTitle" required><br>
        <textarea name="content" id="editContent" required></textarea><br>
        <input type="file" name="image">
        <img id="editPostImagePreview" src="" alt="Current Image" style="max-width: 100%; margin-top: 10px;">
        <button type="submit">Save</button>
        <button type="button" onclick="closeEditPostModal()">Cancel</button>
    </form>
  </div>
</div>


<div id="composeBox" class="chatbox shadow" style="display: none;">
    <div class="chatbox-header d-flex justify-content-between align-items-center p-2 bg-primary text-white">
        <span>New Message</span>
        <button type="button" class="btn-close btn-close-white" onclick="toggleComposeBox(event)"></button>
    </div>
    <div class="chatbox-body p-2">
        <p class="chatbox-text"><strong>To: OSWS</strong></p>
        <textarea id="messageInput" class="form-control mb-2" rows="3" placeholder="Type your message..." required></textarea>
        <input type="file" id="fileInput" class="form-control mb-2" accept=".pdf,.docx" />
        <button class="btn btn-sm btn-primary w-100" onclick="submitLetter()">Send</button>
    </div>
</div>



<!-- Appointment Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAppointmentModal()">&times;</span>
        <h2>Set an Appointment</h2>
        <form action="submit_appointment.php" method="POST">
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required><br><br>

            <label for="description">Description:</label>
            <textarea id="description" name="description" rows="4" required></textarea><br><br>

            <label for="appointment_date">Appointment Date & Time:</label>
            <input type="datetime-local" id="appointment_date" name="appointment_date" required><br><br>

            <label for="duration">Duration:</label>
            <select id="duration" name="duration" required>
                <option value="">Select duration</option>
                <option value="15">15 minutes</option>
                <option value="30">30 minutes</option>
                <option value="45">45 minutes</option>
                <option value="60">1 hour</option>
                <option value="90">1 hour 30 minutes</option>
            </select><br><br>

            <button type="submit" class="submit-btn" style="color: white;"><strong>Submit Appointment</strong></button>
        </form>
    </div>
</div>


<!-- COMMENT MODAL -->
<div id="commentModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <form action="submit_comment.php" method="POST">
            <input type="hidden" id="modalPostId" name="post_id">
            <input type="hidden" id="parentCommentId" name="parent_comment_id" value="">
            <textarea name="comment" required placeholder="Write your comment here..."></textarea>
            <button type="submit">Comment</button>
        </form>
    </div>
</div>

<!-- Reply Modal -->
<div id="replyModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close" onclick="closeReplyModal()">&times;</span>
    <h2>Reply to Comment</h2>
    <form id="replyForm" method="POST" action="submit_comment.php">
      <input type="hidden" name="post_id" id="reply_post_id">
      <input type="hidden" name="parent_comment_id" id="reply_parent_id">
      <textarea name="comment" required placeholder="Write your reply..." rows="4"></textarea>
      <button type="submit">Submit Reply</button>
    </form>
  </div>
</div>


<!-- Post Details Modal -->
<div id="postDetailModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closePostModal()">&times;</span>
    <div id="postDetailBody">Loading post...</div>
  </div>
</div>

<!-- Appointment Conflict Modal -->
<div id="conflictModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close" onclick="closeConflictModal()">&times;</span>
    <h2>Unavailable time frame</h2>
    <p>The time you've chosen for this appointment overlaps with another entry in our schedule. Kindly select an alternative.</p>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="index.js"></script>
<script>
function toggleComposeBox(event) {
    event.preventDefault();
    const box = document.getElementById("composeBox");
    box.style.display = (box.style.display === "none" || box.style.display === "") ? "block" : "none";
}
</script>
<script>
function submitLetter() {
    const message = document.getElementById("messageInput").value.trim();
    if (message === "") {
        alert("Please enter a message.");
        return;
    }

    const formData = new FormData();
    formData.append("recipient", "OSWS");
    formData.append("message", message);

    fetch("submit_letter.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        alert("Message sent!");
        document.getElementById("messageInput").value = ""; // Clear the field
        toggleComposeBox(new Event('click')); // Close the box
    })
    .catch(error => {
        console.error("Error:", error);
        alert("Failed to send message.");
    });
}
</script>


<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".content-container").forEach(container => {
        const content = container.querySelector(".content");
        const toggleBtn = container.querySelector(".read-toggle-btn");

        // Temporarily remove clamp to measure full height
        const originalClamp = content.style.webkitLineClamp;
        content.classList.remove("short-content");

        const fullHeight = content.scrollHeight;

        // Apply clamp back
        content.classList.add("short-content");
        const clampedHeight = content.clientHeight;

        // Show button only if content is actually truncated
        if (fullHeight > clampedHeight + 5) { // +5 for small buffer
            toggleBtn.style.display = "inline";
        }
    });
});

function toggleReadMore(button) {
    const content = button.previousElementSibling;
    content.classList.toggle('expanded');
    button.textContent = content.classList.contains('expanded') ? 'Read less' : 'Read more';
}
</script>



<script>

function replyToComment(commentId, postId) {
    document.getElementById('reply_parent_id').value = commentId;
    document.getElementById('reply_post_id').value = postId;
    document.getElementById('replyModal').style.display = 'block';
}

function closeReplyModal() {
    document.getElementById('replyModal').style.display = 'none';
}

// Close modal on outside click
window.onclick = function(event) {
  const modal = document.getElementById('replyModal');
  if (event.target === modal) {
    closeReplyModal();
  }
}


function toggleMenu(button) {
    const dropdown = button.nextElementSibling;
    const isVisible = dropdown.style.display === 'block';
    document.querySelectorAll('.meatball-dropdown').forEach(menu => menu.style.display = 'none');
    if (!isVisible) {
        dropdown.style.display = 'block';
    }
    document.addEventListener('click', function handler(e) {
        if (!button.parentElement.contains(e.target)) {
            dropdown.style.display = 'none';
            document.removeEventListener('click', handler);
        }
    });
}

function openEditModal(postId, title, content) {
    document.getElementById('editPostId').value = postId;
    document.getElementById('editTitle').value = title;
    document.getElementById('editContent').value = content;
    document.getElementById('editPostModal').style.display = 'block';
}

document.addEventListener('DOMContentLoaded', function () {
    // Toggle FAB
    const fabMain = document.getElementById('fabMain');
    const fabButtons = document.getElementById('fabButtons');
    fabMain.addEventListener('click', () => fabButtons.classList.toggle('show'));

    // Handle edit post button clicks
    document.body.addEventListener('click', function (e) {
        if (e.target.classList.contains('edit-post-btn')) {
            const postId = e.target.dataset.postId;
            const title = e.target.dataset.title;
            const content = e.target.dataset.content;
            const imagePath = e.target.dataset.image;

            document.getElementById('editPostId').value = postId;
            document.getElementById('editTitle').value = title;
            document.getElementById('editContent').value = content;

            const previewImg = document.getElementById('editPostImagePreview');
            if (imagePath && previewImg) {
                previewImg.src = imagePath;
                previewImg.style.display = 'block';
            }

            document.getElementById('editPostModal').style.display = 'block';
        }
    });

    // File size validation (5MB max)
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function () {
            const file = this.files[0];
            if (file && file.size > 5 * 1024 * 1024) {
                alert("File size must be under 5MB.");
                this.value = "";
            }
        });
    });

    // Close modals
    document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            closeBtn.closest('.modal').style.display = 'none';
        });
    });
});

document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('conflict')) {
    const modal = document.getElementById("conflictModal");
    const span = document.querySelector(".close");
    
    modal.style.display = "block";
    
    span.onclick = function() {
      modal.style.display = "none";
      // Optionally clean up the URL
      const url = new URL(window.location);
      url.searchParams.delete('conflict');
      window.history.replaceState({}, document.title, url);
    };
    
    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = "none";
        const url = new URL(window.location);
        url.searchParams.delete('conflict');
        window.history.replaceState({}, document.title, url);
      }
    };
  }
});

function getQueryParam(name) {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get(name);
}

function closeConflictModal() {
  document.getElementById('conflictModal').style.display = 'none';
  // Optionally remove the query parameter from the URL
  history.replaceState(null, '', window.location.pathname);
}

window.onload = function() {
  if (getQueryParam('appointment_conflict') === '1') {
    document.getElementById('conflictModal').style.display = 'block';
  }
};


</script>
<script>
function openModal(postId, notifId) {
    const modal = document.getElementById('postDetailModal');
    const content = document.getElementById('postDetailBody');
    modal.style.display = 'block';
    content.innerHTML = 'Loading post...';

    // Mark notification as read and get post HTML
    fetch(`post.php?id=${postId}&notif=${notifId}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = 'Failed to load post.';
            console.error(error);
        });
}
</script>
</body>
</html>

<?php $stmt->close(); ?>