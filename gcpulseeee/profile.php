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

// Fetch user info
$stmt = $conn->prepare("SELECT firstname, lastname, email, created_at, profile_picture FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "User not found.";
    exit();
}

$user = $result->fetch_assoc();


// Fetch posts created by the user
$query = "SELECT * FROM posts WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();


// Fetch user's appointments for both table and calendar
// All upcoming appointments for the list
$table_stmt = $conn->prepare("SELECT * FROM appointments WHERE appointment_date >= NOW() ORDER BY appointment_date ASC");
$table_stmt->execute();
$table_result = $table_stmt->get_result();

// Fetch blocked dates
$blocked_stmt = $conn->prepare("SELECT * FROM blocked_dates");
$blocked_stmt->execute();
$blocked_result = $blocked_stmt->get_result();
$events = []; // Initialize events here

while ($row = $blocked_result->fetch_assoc()) {
    $events[] = [
        "title" => "Not Available: " . addslashes($row['reason']),
        "start" => $row['date'],
        "allDay" => true,
        "color" => "#e74c3c",
        "textColor" => "#fff",
        "display" => "background"
    ];
}


// Use the correct column name
$sql = "SELECT firstname, lastname FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($firstname, $lastname);
$stmt->fetch();
$stmt->close();



$category_filter = isset($_GET['category']) && $_GET['category'] != "" ? $_GET['category'] : null;
$search_term = isset($_GET['search']) && $_GET['search'] != "" ? "%" . $_GET['search'] . "%" : null;

$query = "SELECT posts.*, users.firstname, users.lastname, users.profile_picture,
                 (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id) AS like_count 
          FROM posts 
          JOIN users ON posts.user_id = users.user_id";

$conditions = [];
$params = [];
$types = "";

// Filter by category if selected
if ($category_filter) {
    $conditions[] = "category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

// Filter by search if provided
if ($search_term) {
    $conditions[] = "(posts.title LIKE ? OR posts.content LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

if (count($conditions) > 0) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
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
    SELECT comments.*, users.firstname
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

 $stmt = $conn->prepare("SELECT  firstname, lastname FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result( $firstname, $lastname);
$stmt->fetch();
$stmt->close();

// Helper to nest threaded comments
function renderComments($comments, $parent_id = null) {
    $html = '';
    foreach ($comments as $comment) {
        if ($comment['parent_comment_id'] == $parent_id) {
            $html .= '<div class="comment" style="margin-left: '.($parent_id ? '30px' : '0').'">';
            $html .= '<img src="' . htmlspecialchars($comment['avatar'] ?? '../gcpulseeee/img/user-icon.png') . '" class="comment-avatar">';
            $html .= '<div class="comment-content">';
            $html .= '<strong>' . htmlspecialchars($comment['firstname']) . '</strong>';
            $html .= '<p>' . htmlspecialchars($comment['comment']) . '</p>';
            $html .= '<small>' . date('F j, Y H:i', strtotime($comment['created_at'])) . '</small>';
            $html .= '<div class="comment-actions">';
            
            // Updated reply button with inline form
            $html .= '<button type="button" class="reply-btn" onclick="toggleReplyForm(' . $comment['comment_id'] . ', ' . $comment['post_id'] . ')">Reply</button>';
            $html .= '<div id="replyForm-' . $comment['comment_id'] . '" class="reply-form" style="display:none;">';
            $html .= '<textarea class="reply-textarea" placeholder="Write your reply..."></textarea>';
            $html .= '<button type="button" onclick="submitReply(' . $comment['comment_id'] . ', ' . $comment['post_id'] . ')">Submit Reply</button>';
            $html .= '</div>';

            // Show edit/delete if user is owner
            if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $comment['user_id']) {
                $html .= '<button onclick="editComment(' . $comment['comment_id'] . ', \'' . htmlspecialchars($comment['comment']) . '\')">Edit</button>';
                $html .= '<form method="POST" action="delete_comment.php" style="display:inline;">
                            <input type="hidden" name="comment_id" value="' . $comment['comment_id'] . '">
                            <button type="submit">Delete</button>
                          </form>';
            }

            $html .= '</div>'; // actions
            $html .= '</div>'; // content
            $html .= renderComments($comments, $comment['comment_id']);
            $html .= '</div>'; // comment
        }
    }
    return $html;
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GC Pulse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap CSS -->
    
    <!-- Font Awesome -->
     <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.css" rel="stylesheet" />
    <link rel="stylesheet"  href="../gcpulseeee/navbar.css">
    <link rel="stylesheet"  href="../gcpulseeee/notification.css">
    <link rel="stylesheet"  href="../gcpulseeee/comment.css">
    <link rel="stylesheet"  href="../gcpulseeee/modal.css">
    <link rel="stylesheet"  href="../gcpulseeee/postcard.css">
    <link rel="stylesheet"  href="../gcpulseeee/responsive.css">
    <link rel="stylesheet"  href="../gcpulseeee/index.css">
    <link rel="stylesheet"  href="../gcpulseeee/profile.css">

    <style>
        html, body {
    height: 100%; /* Ensures the HTML and body elements take up the full viewport height */
    margin: 0; /* Removes default margin */
}
        .bgcontainer {
    background-color: #fff; /* Solid black background */
    width: 100%; /* Full viewport width */
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4)!important;

}
         body {
    font-family: 'Inter', sans-serif;
  }
/* Core Chatbox Container */
.modal-backdrop {
  z-index: 1040 !important;
}

.modal {
  z-index: 1050 !important;
}
  #fileName {
    font-size: 0.8rem;
    color: #555;
  }
  .custom-file-upload {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    cursor: pointer;
    border: 1px solid #ccc;
    border-radius: 4px;
        font-size: 14px;
    border: #355E3B;
    color: #000;
    flex: 0 0 auto;
    margin-top: 0;
  }
  .custom-file-upload:hover {
    background-color: #ccc;
  }

  #fileInput {
    display: none; /* hide the default input */
  }

.file-button-group {
  display: flex;
  gap: 8px; /* space between input and button */
  align-items: center;
      margin-top: -10px;
    margin-bottom: 0 !important;
}

.file-button-group input[type="file"] {
  flex: 1; /* take available space */
}
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
    width: 60px;
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
/* Custom Divider */
.profile-divider {
    border: none;
    border-top: 2px solid #ccc;
    margin: 20px 0;
    width: 100%;
}

/* Post divider (between individual posts) */
.divider {
    border: none;
    border-top: 20px solid #ddd;
    margin: 20px 0;
    width: 100%;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}


</style>

</head>
<body>

<nav class="navbar navbar-expand-lg navbar -4 py-2">
  <div class="container-fluid justify-content-between align-items-center">

    <!-- Left: Logo -->
    <div class="d-flex align-items-center">
      <h3 id="logo" class="mb-0" style="color: #fff;">GC Pulse</h3>
    </div>

    <!-- Center: Search bar -->
    <div class="mx-auto" style="max-width: 400px; width: 100%;">
      <form class="search-form-with-icon" method="GET" action="index.php">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0">
            <i class="fas fa-search text-muted"></i>
          </span>
          <label for="search" class="visually-hidden">Search</label>
          <input type="text" id="search" name="search" class="form-control border-start-0" placeholder="Search posts..." aria-label="Search">
        </div>
      </form>
    </div>

    <!-- Right Section -->
    <div class="d-flex align-items-center gap-3">
        <!-- Notification Icon -->
        <div class="position-relative">
                <a href="index.php" id="userIcon" class="text-white fs-4">
        <i class="fa-solid fa-house text-white fs-4"></i> <!-- User icon -->
    </a>
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
        <img src="../gcpulseeee/img/user-icon.png" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%;">
        <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>
    </a>
</li>
               <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
    <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
<?php endif; ?>
        <li><a class="dropdown-item" href="view_appointments.php">Appointments</a></li>
        <li><hr class="dropdown-divider"></li>
        <a class="dropdown-item" href="#" id="logoutBtn">Logout</a>
    </ul>
</div>
    </div>
</nav>
<div class="bgcontainer">
<div class="profile-page-container">
    <!-- Cover Photo -->
    <div class="profile-cover">
        <img src="../gcpulseeee/img/default-cover.jpg" alt="Cover Photo">

    </div>

    <!-- Profile Info Section -->
 <!-- Profile Info Section -->
<div class="profile-info-section">
    <div class="profile-info">
        <!-- Move avatar container here -->
        <div class="profile-avatar-container">
<?php
$default_avatar = '../gcpulseeee/img/user-icon.png';
$avatar_path = !empty($user['profile_picture']) ? $user['profile_picture'] : '';

$avatar_file = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($avatar_path, '/');

$avatar = (!empty($avatar_path) && file_exists($avatar_file)) 
    ? $avatar_path 
    : $default_avatar;
?>
<img src="<?= htmlspecialchars($avatar) ?>" alt="Profile Picture" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover;">

        </div>
        <h2><?= htmlspecialchars($firstname . ' ' . $lastname) ?></h2>
    </div>
    <div class="profile-actions">
        <a href="change_password.php" class="button primary-action">
            <i class="fas fa-edit"></i>
            Edit Profile
        </a>
    </div>
</div>

    <!-- Profile Navigation -->
    <div class="profile-nav" style = "justify-content-center";>
        <div class="profile-nav-item active">Posts</div>
    </div>

    <!-- Content Grid -->
    <div class="profile-content-grid">
        <!-- Left Sidebar -->
        <div class="profile-sidebar">
            <div class="profile-details">
                <div class="profile-detail-item">
                    <i class="fas fa-envelope"></i>
                    <strong>Email:</strong>
                    <span><?= htmlspecialchars($user['email']) ?></span>
                </div>
                <div class="profile-detail-item">
                    <i class="fas fa-calendar"></i>
                    <strong>Member since:</strong>
                    <span><?= date('F j, Y', strtotime($user['created_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Posts Section -->
        <div class="profile-posts">
            <!-- Your existing post cards go here -->
<?php while ($row = $result->fetch_assoc()): ?>
    <?php
        $default_avatar = '../gcpulseeee/img/user-icon.png';
        $avatar_path = !empty($row['profile_picture']) ? $row['profile_picture'] : '';
        $avatar_file = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($avatar_path, '/');
        $avatar = (!empty($avatar_path) && file_exists($avatar_file)) ? $avatar_path : $default_avatar;
    ?>
    <div class="post-card">
        <div class="post-header">
            <div class="avatar-container">
                <img src="<?= htmlspecialchars($avatar) ?>" alt="Profile" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;margin-right: 10px;">
            </div>
            <span class="firstname"><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></span>
            <span class="date"><?= date('F j, Y', strtotime($row['created_at'])) ?></span>
            <?php if ($row['user_id'] == $_SESSION['user_id']): ?>
                <div class="meatball-menu-container">
                    <button class="meatball-btn" onclick="toggleMenu(this)">⋯</button>
                    <div class="meatball-dropdown">
                        <button class="edit-post-btn" 
                                onclick="openEditModal(<?= $row['post_id'] ?>, 
                                                     '<?= htmlspecialchars(addslashes($row['title'])) ?>', 
                                                     '<?= htmlspecialchars(addslashes($row['content'])) ?>', 
                                                     '<?= htmlspecialchars(addslashes($row['image_path'] ?? '')) ?>')"
                                data-post-id="<?= $row['post_id'] ?>"
                                data-title="<?= htmlspecialchars($row['title']) ?>"
                                data-content="<?= htmlspecialchars($row['content']) ?>"
                                data-image="<?= htmlspecialchars($row['image_path'] ?? '') ?>">
                            Edit
                        </button>
                        <form method="POST" action="delete_post.php" onsubmit="return confirm('Delete this post?');">
                            <input type="hidden" name="post_id" value="<?= $row['post_id'] ?>">
                            <button type="submit" class="delete-btn">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>

                <div class="post-title"><?= htmlspecialchars($row['title']) ?></div>
                <?php if (!empty($row['image_path'])): ?>
                    <img src="<?= htmlspecialchars($row['image_path']) ?>" class="post-image" />
                <?php endif; ?>

                <div class="content-container">
                    <span class="content short-content"><?= nl2br(htmlspecialchars($row['content'])) ?></span>
                    <button class="read-toggle-btn" style="display: none;" onclick="toggleReadMore(this)">Read more</button>
                </div>
                <div class="post-actions">
                    <form action="toggle_like.php" method="POST" style="display:inline;">
                        <input type="hidden" name="post_id" value="<?= $row['post_id'] ?>">
                        <button type="button" 
                                class="action-like-btn <?= in_array($row['post_id'], $user_likes) ? 'liked' : '' ?>" 
                                data-post-id="<?= $row['post_id'] ?>">
                            <?= in_array($row['post_id'], $user_likes) ? 'Unlike' : 'Like' ?>
                        </button>
                    </form>
                    <button type="button" class="action-btn-comment" onclick="openModal(<?= $row['post_id'] ?>)"> Comment</button>
                </div>

                <div class="collapsible-content">
                    <?= isset($comments_by_post[$row['post_id']]) ? renderComments($comments_by_post[$row['post_id']]) : "<p>No comments yet.</p>" ?>
                </div>
            </div>
        <?php endwhile; ?>
<!-- Edit Modal -->

<div id="editPostModal" class="modal">
  <div class="modal-content" id="editPostContent">
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



<!-- COMMENT MODAL -->
<div id="commentModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
<form id="commentForm" action="submit_comment.php" method="POST">
    <input type="hidden" id="modalPostId" name="post_id">
    <input type="hidden" id="parentCommentId" name="parent_comment_id" value="">
    <textarea name="comment" required placeholder="Write your comment here..."></textarea>
    <button type="submit">Comment</button>
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


<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg-custom">
    <div class="modal-content" id="successContent">
      <div class="modal-header">
        
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="centered-icon-check">
        <i class="fa-solid fa-circle-check">‌</i>
      </div>
      <h5 class="modal-title" id="successModalLabel">Appointment Submitted</h5>
      <div class="modal-body">
        Your appointment was submitted successfully!
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>
<!-- Logout Confirmation Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content" id="logoutContent">
      <div class="modal-header">
        <h5 class="modal-title" id="logoutModalLabel">Confirm Logout</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <i class="fa fa-sign-out" id="centered-icon-logout" aria-hidden="true"></i>
        Are you sure you want to logout?
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="cancelLogoutBtn"    data-bs-dismiss="modal">Cancel</button>
        <form method="POST" action="logout.php" class="d-inline">
          <button type="submit" class="btn btn-danger" id="btnLogout">Logout</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.js"></script>
    <script>
// Get the modal
var modal = document.getElementById("editProfileModal");

// Get the image that opens the modal
var img = document.getElementById("profile-image");

// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];

// When the user clicks the image, open the modal
img.onclick = function() {
    modal.style.display = "block";
}

// When the user clicks on <span> (x), close the modal
span.onclick = function() {
    modal.style.display = "none";
}

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = "none";
    }
}
</script>

<script>
  // When the user clicks the logout button
  document.querySelector('.dropdown-item[href="#"]').addEventListener('click', function(event) {
    event.preventDefault(); // Prevent the default logout action
    // Show the modal
    var myModal = new bootstrap.Modal(document.getElementById('logoutModal'));
    myModal.show();
  });
</script>
<script>
  const fileInput = document.getElementById('fileInput');
  const fileName = document.getElementById('fileName');

  fileInput.addEventListener('change', function () {
    if (fileInput.files.length > 0) {
      fileName.textContent = fileInput.files[0].name;
    } else {
      fileName.textContent = '';
    }
  });
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('error') === 'past_date') {
        // Hide any currently open modals
        document.querySelectorAll('.modal.show').forEach(modal => {
            const instance = bootstrap.Modal.getInstance(modal);
            if (instance) instance.hide();
        });

        const modalElement = document.getElementById('pastDateModal');
        if (modalElement) {
            // Ensure modal instance exists
            let modal = bootstrap.Modal.getInstance(modalElement);
            if (!modal) {
                modal = new bootstrap.Modal(modalElement);
            }

            modal.show();

            // Optional: remove 'fade' class if causing animation issues
            modalElement.classList.remove('fade');

            // Clean URL (remove ?error=past_date)
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }
});

function handleRequestClick() {
    console.log('✅ handleRequestClick called');

    // Hide the pastDateModal
    const modalEl = document.getElementById('pastDateModal');
    if (modalEl) {
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }
        modal.hide();
    }

    // Open your appointment modal (replace with actual function)
    openAppointmentModal?.(); // only call if defined
}
</script>

    <script>
document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 500,
        events: <?= json_encode($events) ?>,
        eventColor: '#4caf50',
        eventTextColor: '#fff',
        eventClick: function(info) {
            const event = info.event;

            document.getElementById("modalTitle").textContent = event.title;
            document.getElementById("modalDescription").textContent = event.extendedProps.description;
            document.getElementById("modalDate").textContent = new Date(event.start).toLocaleString();

            const duration = parseInt(event.extendedProps.duration);
            const hours = Math.floor(duration / 60);
            const minutes = duration % 60;
            document.getElementById("modalDuration").textContent =
                (hours ? `${hours} hour${hours > 1 ? 's' : ''} ` : '') +
                (minutes ? `${minutes} minute${minutes > 1 ? 's' : ''}` : '');

            document.getElementById("modalStatus").textContent = event.extendedProps.status;

            document.getElementById("calendarModal").style.display = "block";
        }
    });

    calendar.render();
});
function closeCalendarModal() {
    document.getElementById("calendarModal").style.display = "none";
}
</script>
<script>
  const modal = document.getElementById("createPostModal");
  const modalContent = document.querySelector(".modal-content");

  // Close modal when clicking outside of modal content
  window.addEventListener("click", function(event) {
    if (event.target === modal) {
      closeCreatePostModal();
    }
  });

  // Function to close modal
  function closeCreatePostModal() {
    modal.style.display = "none";
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
    // Add these functions to your JavaScript
function toggleReplyForm(commentId, postId) {
    const replyForm = document.getElementById(`replyForm-${commentId}`);
    const allReplyForms = document.querySelectorAll('.reply-form');
    
    // Hide all other reply forms
    allReplyForms.forEach(form => {
        if (form.id !== `replyForm-${commentId}`) {
            form.style.display = 'none';
        }
    });

    // Toggle the clicked reply form
    replyForm.style.display = replyForm.style.display === 'none' ? 'block' : 'none';
}

function submitReply(commentId, postId) {
    const replyForm = document.getElementById(`replyForm-${commentId}`);
    const textarea = replyForm.querySelector('.reply-textarea');
    const comment = textarea.value.trim();

    if (!comment) {
        alert('Please write a reply before submitting.');
        return;
    }

    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('parent_comment_id', commentId);
    formData.append('comment', comment);

    fetch('submit_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Refresh comments section
        const commentsContainer = replyForm.closest('.collapsible-content');
        if (commentsContainer) {
            commentsContainer.innerHTML = data;
        }
    })
    .catch(error => {
        console.error('Error submitting reply:', error);
        alert('Failed to submit reply. Please try again.');
    });
}
// Add these functions to your JavaScript section in index.php
function toggleModalReplyForm(commentId, postId) {
    const replyForm = document.getElementById(`modalReplyForm-${commentId}`);
    const allReplyForms = document.querySelectorAll('.modal-comments .reply-form');
    
    // Hide all other reply forms
    allReplyForms.forEach(form => {
        if (form.id !== `modalReplyForm-${commentId}`) {
            form.style.display = 'none';
        }
    });

    // Toggle the clicked reply form
    replyForm.style.display = replyForm.style.display === 'none' ? 'block' : 'none';
}

function submitModalReply(commentId, postId) {
    const replyForm = document.getElementById(`modalReplyForm-${commentId}`);
    const textarea = replyForm.querySelector('.reply-textarea');
    const comment = textarea.value.trim();

    if (!comment) {
        alert('Please write a reply before submitting.');
        return;
    }

    const formData = new FormData();
    formData.append('post_id', postId);
    formData.append('parent_comment_id', commentId);
    formData.append('comment', comment);

    fetch('submit_comment.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Refresh the modal comments section
        const modalCommentsContainer = document.getElementById(`modalComments${postId}`);
        if (modalCommentsContainer) {
            modalCommentsContainer.innerHTML = data;
        }
        
        // Also refresh the main post comments if visible
        const mainCommentsContainer = document.querySelector(`.post-card[data-post-id="${postId}"] .collapsible-content`);
        if (mainCommentsContainer) {
            mainCommentsContainer.innerHTML = data;
        }
    })
    .catch(error => {
        console.error('Error submitting reply:', error);
        alert('Failed to submit reply. Please try again.');
    });
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

function openEditModal(postId, title, content, imagePath) {
    // Get the modal
    const modal = document.getElementById('editPostModal');
    
    // Set the values
    document.getElementById('editPostId').value = postId;
    document.getElementById('editTitle').value = title;
    document.getElementById('editContent').value = content;
    
    // Handle image preview
    const imagePreview = document.getElementById('editPostImagePreview');
    if (imagePath && imagePath !== 'null' && imagePath !== '') {
        imagePreview.src = imagePath;
        imagePreview.style.display = 'block';
    } else {
        imagePreview.style.display = 'none';
    }
    
    // Show the modal
    modal.style.display = 'block';
}

function closeEditPostModal() {
    const modal = document.getElementById('editPostModal');
    modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editPostModal');
    if (event.target === modal) {
        closeEditPostModal();
    }
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
  const conflictParam = urlParams.has('conflict') || getQueryParam('appointment_conflict') === '1';
  const successParam = getQueryParam('success') === '1';

  const conflictModal = document.getElementById('conflictModal');

  if (conflictParam && conflictModal) {
    conflictModal.style.display = 'block';

    // Clean up the URL
    urlParams.delete('conflict');
    urlParams.delete('appointment_conflict');
    const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
    window.history.replaceState({}, document.title, cleanUrl);

    // Close modal on X click
    const span = document.querySelector('#conflictModal .close');
    span.onclick = closeConflictModal;

    // Close on outside click
    window.onclick = function (event) {
      if (event.target === conflictModal) {
        closeConflictModal();
      }
    };
  }

  if (successParam) {
    const successModalEl = document.getElementById('successModal');
    const successModal = new bootstrap.Modal(successModalEl);
    successModal.show();

    // Remove success param
    window.history.replaceState({}, document.title, window.location.pathname);
  }
});

function getQueryParam(name) {
  return new URLSearchParams(window.location.search).get(name);
}

function openAppointmentModal() {
  const appointmentModal = document.getElementById('appointmentModal');
  if (appointmentModal) {
    appointmentModal.style.display = 'block';
  }
}

function closeConflictModal() {
  const conflictModal = document.getElementById('conflictModal');
  if (conflictModal) {
    conflictModal.style.display = 'none';
  }
  // Clean URL params when modal closes
  const url = new URL(window.location);
  url.searchParams.delete('conflict');
  url.searchParams.delete('appointment_conflict');
  window.history.replaceState({}, document.title, url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
}

function handleRequestClick() {
  console.log('✅ handleRequestClick called');

  // Close pastDateModal if it's open
  const pastDateModalEl = document.getElementById('pastDateModal');
  if (pastDateModalEl) {
      let pastDateModal = bootstrap.Modal.getInstance(pastDateModalEl);
      if (!pastDateModal) {
          pastDateModal = new bootstrap.Modal(pastDateModalEl);
      }
      pastDateModal.hide();
  }

  // Close conflict modal if it's open
  closeConflictModal();

  // Open the appointment modal
  openAppointmentModal();
}


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
    <script>

// LIKE form handler
document.addEventListener('submit', function (e) {
    if (e.target.classList.contains('like-form')) {
        e.preventDefault();
        const form = e.target;
        const postId = form.getAttribute('data-post-id');

        fetch('toggle_like.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'post_id=' + encodeURIComponent(postId)
        })
        .then(response => response.text())
        .then(data => {
            // Optional: toggle button text and like count
            const button = form.querySelector('button');
            const likeCountSpan = form.querySelector('span');

            if (button.textContent.trim().toLowerCase() === 'like') {
                button.textContent = 'Unlike';
                likeCountSpan.textContent = updateLikeCount(likeCountSpan.textContent, +1);
            } else {
                button.textContent = 'Like';
                likeCountSpan.textContent = updateLikeCount(likeCountSpan.textContent, -1);
            }
        })
        .catch(error => console.error('Like error:', error));
    }
});

function loadComments(postId) {
    const commentBox = document.getElementById('modalComments' + postId);

    fetch('load_comments.php?post_id=' + encodeURIComponent(postId))
        .then(response => response.text())
        .then(html => {
            if (commentBox) commentBox.innerHTML = html;
        })
        .catch(error => console.error('Failed to load comments:', error));
}

// COMMENT form handler

document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('submit', function (e) {
        if (e.target.classList.contains('comment-form')) {
            e.preventDefault();

            const form = e.target;
            const postId = form.getAttribute('data-post-id');
            const formData = new FormData(form);
            formData.append('post_id', postId);

            fetch('submit_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                const container = document.getElementById('modalComments' + postId);
                if (container) {
                    container.innerHTML = html;
                    form.reset();
                }
            })
            .catch(error => console.error('Comment submission failed:', error));
        }
    });
});



// Helper function to update like count
function updateLikeCount(text, delta) {
    const match = text.match(/^(\d+)/);
    if (!match) return text;
    const current = parseInt(match[1], 10);
    return `${current + delta} likes`;
}
</script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const commentForm = document.getElementById('commentForm');
    
    commentForm.addEventListener('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(commentForm);

        fetch('submit_comment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Clear the textarea and close modal
            commentForm.reset();
            closeModal(); // Make sure this function hides the modal
            // Optionally reload comments using another AJAX call
            alert('Comment submitted successfully!');
        })
        .catch(error => {
            console.error('Error submitting comment:', error);
        });
    });
});
</script>
<script>
// Add to index.js or a separate script tag
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = textarea.scrollHeight + 'px';
    
    // Show/hide submit button based on content
    const form = textarea.closest('.comment-form');
    const submitBtn = form.querySelector('button[type="submit"]');
    if (textarea.value.trim().length > 0) {
        submitBtn.style.display = 'block';
    } else {
        submitBtn.style.display = 'none';
    }
}

// Add enter key handler for comments
document.addEventListener('keypress', function(e) {
    if (e.target.matches('.comment-form textarea') && e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        const postId = e.target.dataset.postId;
        const comment = e.target.value;
        submitComment(postId, comment);
        e.target.value = '';
        autoResize(e.target);
    }
});
</script>
