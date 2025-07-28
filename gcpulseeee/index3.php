<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

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

// Fetch user's appointments for both table and calendar
$appointments = $conn->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date ASC");
$appointments->bind_param("i", $user_id);
$appointments->execute();
$table_result = $appointments->get_result(); // For table


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
</head>
<body>

<nav class="navbar">
    <div class="nav-left">
        <img src= ../gcpulseeee/img/pulsee.png stlye="width: 55px"; style=" height: 55px";>
                <form class="search-form" method="GET">
            <input type="text" name="search" placeholder="Search posts...">
            <button type="submit" class="search-button" style="color: #000">
  <i class="fas fa-search"></i>
</button>

    </div>
    <div class="nav-center">
    </div>
    <div class="nav-right">
    <i class="fas fa-bell nav-icon-3x" id="notifIcon" style="font-size:30px"></i>

        <div class="notif-panel" id="notifPanel">
    <h4 style="text-align: center;">Notifications</h4>
    <ul>
        <?php
        $notif_query = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
        $notif_query->bind_param("i", $user_id);
        $notif_query->execute();
        $notif_result = $notif_query->get_result();

        if ($notif_result->num_rows > 0):
            while ($notif = $notif_result->fetch_assoc()):
        ?>
            <li>
            <i class="fas fa-bell nav-icon-3x" id="notifIcon" style="font-size:30px"></i>
                <div class="notif-message">
                <a href="javascript:void(0);" onclick="openPostFromNotif(<?= $notif['post_id'] ?>, <?= $notif['notification_id'] ?>)" style="text-decoration: none; color: inherit;">
    <?= htmlspecialchars_decode($notif['message']) ?>
</a>
                    <span class="notif-time"><?= date('M j, g:i A', strtotime($notif['created_at'])) ?></span>
                </div>
            </li>
        <?php endwhile; else: ?>
            <li><div class="notif-message">No new notifications.</div></li>
        <?php endif; ?>
    </ul>
</div>

         <div class="dropdown">
            <div class="fas fa-user nav-icon user" id="userIcon" style="font-size:30px; color:#fff; " onclick="toggleDropdown()">
            </div>
            <div class="dropdown-menu" id="dropdownMenu">
                <a href="dashboard.php">Dashboard</a>
                <a href="view_appointments.php">Appointments</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</nav>



<div class="sidebar">
    <ul>
                <li>        <form method="GET" class="filter-form-inline">
            <select name="category" onchange="this.form.submit()">
                <option value="">All</option>
                <option value="event" <?= ($category_filter == 'event') ? 'selected' : ''; ?>>Events</option>
                <option value="announcement" <?= ($category_filter == 'announcement') ? 'selected' : ''; ?>>Announcements</option>
                <option value="news" <?= ($category_filter == 'news') ? 'selected' : ''; ?>>News</option>
            </select>
        </form></li>
        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="view_appointments.php"><i class="fas fa-calendar-alt"></i> Appointments</a></li>

        
        <hr class="divider">
            <div class="upcoming-section">
    <div class="upcoming-header">
        <span><strong>Upcoming Appointments</strong></span>
        <button class="view-all" onclick="openAppointmentModal()" title="Appointment">Request</button>
    </div>

    <?php 
    $table_result->data_seek(0); 
    $count = 0;
    while ($row = $table_result->fetch_assoc()): 
        if ($count >= 2) break;
        $count++;
    ?>
    <div class="upcoming-card">
        <strong><?php echo htmlspecialchars($row['description']); ?></strong>
        <div class="upcoming-sub">
            <?php
                echo date('F j, Y, g:i A', strtotime($row['appointment_date']));
            ?>
        </div>
    </div>
    <?php endwhile; ?>
</div>

</div>
        </ul>
</div>



<!-- Floating Action Button System -->
<div class="fab-container">
    <div class="fab-buttons" id="fabButtons">
        <button class="fab-button" onclick="openCreatePostModal()" title="New Post">✏️</button>
        <button class="fab-button" onclick="openAppointmentModal()" title="Appointment">📅</button>
    </div>
    <button class="fab-main" id="fabMain">+</button>
</div>

<!-- Post prompt box -->
<div class="main-content-box">
  <div class="post-box">
    <div class="post-input-section">
      <img src="path_to_user_profile_picture.jpg" alt="Profile" class="profile-img">
      <button class="post-input" onclick="openCreatePostModal()" title="New Post">Create Post...</button>
    </div>
  </div>
</div>


<div class="main-content-card">
<?php while ($row = $result->fetch_assoc()): ?>
    <div class="post-card">
        <div class="post-header">
            <img src="../gcpulseeee/img/user-icon.png" class="user-icon" />
            <span class="username"><?= htmlspecialchars($row['username']) ?></span>
            <span class="date"><?= date('F j, Y', strtotime($row['created_at'])) ?></span>

            <!-- Meatball Menu Trigger -->
            <div class="meatball-menu-container">
                <button class="meatball-btn" onclick="toggleMenu(this)">⋯</button>
                <div class="meatball-dropdown">
                <button type="button" class="edit-post-btn"
    data-post-id="<?= $row['post_id'] ?>"
    data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES) ?>"
    data-content="<?= htmlspecialchars($row['content'], ENT_QUOTES) ?>"
    data-image="<?= htmlspecialchars($row['image_path'] ?? '', ENT_QUOTES) ?>">
    Edit
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
                <button type="submit" class="action-btn <?= in_array($row['post_id'], $user_likes) ? 'liked' : '' ?>">
                    <?= in_array($row['post_id'], $user_likes) ? 'Unlike ' : 'Like ' ?>
                </button>
                <span class="like-count"><?= $row['like_count'] ?></span>
            </form>
            <button type="button" class="action-btn" onclick="openModal(<?= $row['post_id'] ?>)"> Comment</button>
        </div>

        <button class="collapsible"> View Comments</button>
        <div class="collapsible-content">
            <?= isset($comments_by_post[$row['post_id']]) ? renderComments($comments_by_post[$row['post_id']]) : "<p>No comments yet.</p>" ?>
        </div>
    </div>
<?php endwhile; ?>
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



<script src="index.js"></script>
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