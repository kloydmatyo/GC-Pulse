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

// Fetch appointments
$calendar_stmt = $conn->prepare("SELECT * FROM appointments WHERE appointment_date >= NOW() AND status = 'approved' ORDER BY appointment_date ASC");
$calendar_stmt->execute();
$calendar_result = $calendar_stmt->get_result();

while ($row = $calendar_result->fetch_assoc()) {
    $events[] = [
        "id" => $row['appointment_id'],
        "title" => addslashes($row['title']),
        "start" => $row['appointment_date'],
        "extendedProps" => [
            "description" => $row['description'],
            "duration" => $row['duration'],
            "status" => $row['status']
        ]
    ];
}

// Use the correct column name
$sql = "SELECT firstname, lastname, profile_picture FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($firstname, $lastname, $profile_picture);
$stmt->fetch();
$stmt->close();




$category_filter = isset($_GET['category']) && $_GET['category'] != "" ? $_GET['category'] : null;
$search_term = isset($_GET['search']) && $_GET['search'] != "" ? "%" . $_GET['search'] . "%" : null;

// You must fetch the user's department first
$user_department = strtoupper(trim($_SESSION['department'] ?? ''));
// Replace the existing department code with this:
if (!isset($_SESSION['department'])) {
    // Fetch department if not in session
    $dept_stmt = $conn->prepare("SELECT department FROM users WHERE user_id = ?");
    $dept_stmt->bind_param("i", $user_id);
    $dept_stmt->execute();
    $dept_stmt->bind_result($department);
    $dept_stmt->fetch();
    $dept_stmt->close();
    
    $_SESSION['department'] = $department;
}

$user_department = strtoupper(trim($_SESSION['department'] ?? ''));

// Debug output
echo "Your department: " . htmlspecialchars($_SESSION['department'] ?? 'Not set');

// Updated query logic
$query = "SELECT posts.*, users.firstname, users.lastname, users.profile_picture,
                 (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.post_id) AS like_count
          FROM posts
          JOIN users ON posts.user_id = users.user_id";

// Update the conditions array in index.php where you build the query
$conditions = [
    "(posts.audience_type = 'public' 
     OR (posts.audience_type = 'selected' 
         AND FIND_IN_SET(?, posts.audience))
     OR (posts.audience_type = 'private' 
         AND posts.user_id = ?)
     OR posts.user_id = ? -- ensure user sees their own post regardless
    )"
];

$params = [$user_department, $user_id, $user_id];
$types = "sii";

// Add category filter
if ($category_filter) {
    $conditions[] = "posts.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

// Add search filter
if ($search_term) {
    $conditions[] = "(posts.title LIKE ? OR posts.content LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

$query .= " WHERE " . implode(" AND ", $conditions);
$query .= " ORDER BY posts.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
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
    <link rel="stylesheet"  href="../gcpulseeee/body.css">
    <link rel="stylesheet"  href="../gcpulseeee/comment.css">
    <link rel="stylesheet"  href="../gcpulseeee/modal.css">
    <link rel="stylesheet"  href="../gcpulseeee/postcard.css">
    <link rel="stylesheet"  href="../gcpulseeee/responsive.css">
    <link rel="stylesheet"  href="../gcpulseeee/index.css">
    <link rel="stylesheet"  href="../gcpulseeee/category.css">
    <link rel="stylesheet"  href="../gcpulseeee/sidebarindex.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
         body {
    font-family: 'Inter', sans-serif;
    background-color: #fff;
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
height: 200px;
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
    background-color: #ccc;
}
.upcoming-appointments {
    margin-top: 1rem;

       background-color: #fff;
    padding: 15px;
    border-radius: 10px;
    /* box-shadow: 0 4px 10px rgba(0, 0, 0, .5); OUTSIDE shadow */
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

/* Chatbot Styles */
.chatbot-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}

.chatbot-toggle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #355E3B;
    border: none;
    color: white;
    cursor: pointer;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.chatbot-window {
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 350px;
    height: 500px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.chatbot-header {
    padding: 15px;
    background: #355E3B;
    color: white;
    border-radius: 10px 10px 0 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.chatbot-avatar {
    width: 35px;
    height: 35px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.chatbot-info h4 {
    margin: 0;
    font-size: 16px;
}

.chatbot-info span {
    font-size: 12px;
    opacity: 0.8;
}

.chatbot-messages {
    flex: 1;
    overflow-y: auto;
    padding: 15px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.message {
    padding: 10px 15px;
    border-radius: 15px;
    max-width: 80%;
    word-wrap: break-word;
}

.message.bot {
    background: #f0f0f0;
    margin-right: auto;
    border-bottom-left-radius: 5px;
}

.message.user {
    background: #355E3B;
    color: white;
    margin-left: auto;
    border-bottom-right-radius: 5px;
}

.chatbot-input {
    padding: 15px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
    background: white;
}

.chatbot-input input {
    flex: 1;
    padding: 8px 15px;
    border: 1px solid #ddd;
    border-radius: 20px;
    outline: none;
    font-size: 14px;
}

.chatbot-input input:focus {
    border-color: #355E3B;
}

.send-btn {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: #355E3B;
    color: white;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
}

.send-btn:hover {
    background: #2a4a2f;
}

/* Scrollbar Styling */
.chatbot-messages::-webkit-scrollbar {
    width: 6px;
}

.chatbot-messages::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.chatbot-messages::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.chatbot-messages::-webkit-scrollbar-thumb:hover {
    background: #555;
}
  .audience-grid {
    display: grid;
    grid-template-columns: 1fr 1fr; /* 2 columns */
    gap: 10px;
    max-width: 300px;
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
                <a href="index.php" id="userIcon" class="text-white fs-4" style="margin-right: 10px; margin-top: 1px;">
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
<?php
$default_avatar = '../gcpulseeee/img/user-icon.png';
$avatar_path = !empty($profile_picture) ? '../' . ltrim($profile_picture, '/') : '';
$avatar = (!empty($avatar_path) && file_exists($avatar_path)) ? $avatar_path : $default_avatar;

?>
         <img src="<?= htmlspecialchars($avatar) ?>" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; margin-right: 8px;">
        <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>
    </a>
</li>
    <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
        <li><a class="dropdown-item" href="view_appointments.php">Appointments</a></li>
        <li><hr class="dropdown-divider"></li>
        <a class="dropdown-item" href="#" id="logoutBtn">Logout</a>
    </ul>
</div>
    </div>
</nav>



<div class="layout-wrapper">
  <div class="sidebar" id="customSidebar">
  <div class="sidebar-content">
    <!-- User Profile Link -->
    <a href="profile.php" class="nav-link">
        <?php
$default_avatar = '../gcpulseeee/img/user-icon.png';
$avatar_path = !empty($profile_picture) ? '../' . ltrim($profile_picture, '/') : '';
$avatar = (!empty($avatar_path) && file_exists($avatar_path)) ? $avatar_path : $default_avatar;

?>
       <img src="<?= htmlspecialchars($avatar) ?>" alt="Profile" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 8px;">
      <span><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></span>
    </a>

    <!-- Navigation Links -->
    <ul class="nav nav-pills flex-column mb-auto">
      <li class="nav-item">
        <button class="nav-link" onclick="toggleComposeBox(event)">
          <i class="fas fa-pen"></i>
          <span>Compose Message</span>
        </button>
      </li>      
      <li class="nav-item">
            <button type="button" class="nav-link" onclick="openAppointmentModal()">
          <i class="fas fa-calendar-alt"></i>
          <span>Set an Appointment</span>
        </a>
      </li>
      <li class="nav-item">
    <a href="dashboard.php" class="nav-link">
        <i class="fas fa-chart-line"></i>
        <span>Dashboard</span>
    </a>

    </ul>

    <hr>

    <!-- Filter Section -->
    <div class="filter-section">
      <form method="GET" action="index.php">
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
        <!-- <strong>Set an Appointment</strong>
        <button type="button" class="btn btn-primary" onclick="openAppointmentModal()">
          <i class="fas fa-plus"></i>
        </button> -->
      </div>
  <div class="vertical-divider"></div>
      <!-- <?php 
      $table_result->data_seek(0); 
      $count = 0;
      $current_time = time(); // ✅ Define this BEFORE the loop
 while ($row = $table_result->fetch_assoc()): 
    $appointment_time = strtotime($row['appointment_date']);
    
    // Skip past appointments
    if ($appointment_time < $current_time) continue;

    // Limit to 2 upcoming appointments
    if ($count >= 2) break;
    $count++;
?>
<div class="appointment-card">
  <h6 class="appointment-title"><?= htmlspecialchars($row['description']); ?></h6>
  <p class="appointment-time">
    <?= date('F j, Y, g:i A', $appointment_time); ?>
  </p>
</div>
<?php endwhile; ?> -->
    </div>
  </div>
</div>
<!-- Right Sidebar -->
<div class="appointments-sidebar">
    <div class="calendar-box">
        <div id="calendar"></div>
    </div>


</div>
<!-- Main Content -->
<div class="flex-grow-1 p-4">
    <!-- Post prompt box -->
<?php
// Set profile picture
$default_avatar = '../gcpulseeee/img/user-icon.png';
$avatar_path = !empty($profile_picture) ? '../' . ltrim($profile_picture, '/') : '';
$avatar = (!empty($avatar_path) && file_exists($avatar_path)) ? $avatar_path : $default_avatar;
?>

<div class="main-content-box">
    <div class="post-box">
        <div class="post-input-section">
            <img src="<?= htmlspecialchars($avatar) ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;margin-right: 10px;">
            <button class="post-input" onclick="openCreatePostModal()" title="New Post">Create Post...</button>
        </div>
    </div>
</div>


    <!-- Post Cards Container -->
    <div class="d-flex justify-content-center">
        <div class="main-content-card">
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
                <img src="<?= htmlspecialchars($avatar) ?>" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;margin-right: 10px;">
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

                    <hr class="divider">
                    <div class="post-actions">
                        <form action="toggle_like.php" method="POST" style="display:inline;">
                            <input type="hidden" name="post_id" value="<?= $row['post_id'] ?>">
<button type="button" 
        class="action-like-btn <?= in_array($row['post_id'], $user_likes) ? 'liked' : '' ?>" 
        data-post-id="<?= $row['post_id'] ?>">
    <?= in_array($row['post_id'], $user_likes) ? 'Unlike' : 'Like' ?>
</button>
                            <!--<span class="like-count"><?= $row['like_count'] ?></span>-->
                        </form>
                        <button type="button" class="action-btn-comment" onclick="openModal(<?= $row['post_id'] ?>)"> Comment</button>
                    </div>

                    <!-- <button class="collapsible"> View Comments</button> -->
                    <div class="collapsible-content">
                        <?= isset($comments_by_post[$row['post_id']]) ? renderComments($comments_by_post[$row['post_id']]) : "<p>No comments yet.</p>" ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>




<!-- CREATE POST MODAL -->
<div id="createPostModal" class="modal">
  <div class="modal-content" id="createPostContent">
    <span class="close" onclick="closeCreatePostModal()">&times;</span>
    <?php
$default_avatar = '../gcpulseeee/img/user-icon.png';
$avatar_path = !empty($profile_picture) ? '../' . ltrim($profile_picture, '/') : '';
$avatar = (!empty($avatar_path) && file_exists($avatar_path)) ? $avatar_path : $default_avatar;

?>
<form action="create_post.php" method="POST" enctype="multipart/form-data">
     <img src="<?= htmlspecialchars($avatar) ?>" alt="Profile"
         style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; margin-right: 10px; margin-bottom: 10px;">
    <span class="firstname"><?= htmlspecialchars($firstname . ' ' . $lastname) ?></span>
    
    <input type="text" name="title" placeholder="Title" required><br>
    <textarea name="content" placeholder="Content..." required></textarea><br>
    
    <select name="category" required>
        <option value="">Select Category</option>
        <option value="event">Event</option>
        <option value="announcement">Announcement</option>
        <option value="news">News</option>
    </select><br>
    <select name="audience_type">
    <option value="public">Public</option>
    <option value="selected">Selected Departments</option>
</select>

    <!-- Multiple Audience Checkboxes -->
<p><strong>Select Target Audience:</strong></p>
<div class="audience-grid">
  <label><input type="checkbox" name="audience[]" value="College of Hospitality and Tourism Management (CHTM)"> CHTM</label>
  <label><input type="checkbox" name="audience[]" value="College of Business and Accountancy (CBA)"> CBA</label>
  <label><input type="checkbox" name="audience[]" value="College of Computer Studies (CCS)"> CCS</label>
  <label><input type="checkbox" name="audience[]" value="College of Education and Allied Studies (CEAS)"> CEAS</label>
  <label><input type="checkbox" name="audience[]" value="College of Allied Health Studies (CAHS)"> CAHS</label>
  <label><input type="checkbox" name="audience[]" value="organization"> ORGS</label>
</div>


    <input type="file" name="image"><br>
    <button id="create-post-btn" type="submit" style="color:white;"><strong>Post</strong></button>
</form>


  </div>
</div>

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


<div id="composeBox" class="chatbox shadow" style="display: none;"> 
  <div class="chatbox-header d-flex justify-content-between align-items-center p-2 bg-primary text-white">
    <span>New Message</span>
    <button type="button" class="btn-close btn-close-white" onclick="toggleComposeBox(event)"></button>
  </div>

  <div class="chatbox-body p-2">
    <!-- Title input field -->
    <input type="text" id="titleInput" class="form-control mb-2" placeholder="Title" required />

    <p class="chatbox-text"><strong>To: OSWS</strong></p>
    <textarea id="messageInput" class="form-control mb-2" rows="3" placeholder="Type your message..." required></textarea>

    <div class="file-button-group">
      <!-- Send button -->
      <button id="send-btn" class="btn btn-sm btn-primary" onclick="submitLetter()">Send</button>

      <!-- File upload trigger -->
      <label for="fileInput" class="custom-file-upload" title="Attach file">
        <i class="fas fa-paperclip"></i>
      </label>

      <!-- Hidden file input -->
      <input type="file" id="fileInput" accept=".pdf,.docx" />

      <!-- Display file name -->
      <span id="fileName"></span>
    </div>
  </div>
</div>





<!-- Appointment Modal -->
<div id="appointmentModal" class="modal">
    <div class="modal-content" id="appointmentContent">
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

            <button type="submit" id="appointment-btn" style="color: white;"><strong>Submit Appointment</strong></button>
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

<!-- Reply Modal 
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
</div>-->


<!-- Post Details Modal -->
<div id="postDetailModal" class="modal">
  <div class="modal-content">
    <span class="close" onclick="closePostModal()">&times;</span>
    <div id="postDetailBody">Loading post...</div>
  </div>
</div>

<!-- Appointment Conflict Modal -->
<div id="conflictModal" class="modal" style="display:none;">
  <div class="modal-content" id="conflictContent">
    <span class="close" onclick="closeConflictModal()">&times;</span>
    <div class="centered-icon">
    <i class="fa-solid fa-circle-exclamation">‌</i>
    </div>
    <h2 class="conflict-heading" >Unavailable Time Frame</h2>
    <p class="conflict-text">The time you've chosen for this appointment overlaps with another <br>entry in our schedule. Kindly select an alternative.</p>
          <div class="modal-footer">
        <button type="button" class="request-button" onclick="handleRequestClick()">Request</button>

      </div>
  </div>
</div>
<!-- Appointment Modal for Past Date Error -->
<div class="modal fade" id="pastDateModal" tabindex="-1" aria-labelledby="pastDateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg-custom">
    <div class="modal-content modal-content-error" id="pastDateContent">

      <div class="modal-header">
        <h5 class="modal-title" id="pastDateModalLabel">Invalid Appointment Date</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
          <div class="centered-icon">
    <i class="fa-solid fa-circle-exclamation">‌</i>
    </div>
    
      <div class="modal-body" id="pastDateBody">
        Please select a future date and time for the appointment.
      </div>
      <div class="modal-footer">
        <button type="button" class="request-button" onclick="handleRequestClick()">Request</button>

      </div>
    </div>
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
<!-- Calendar Event Modal -->
<div id="calendarModal" class="modal">
    <div class="modal-content-calendar">
        <span class="close-btn" onclick="closeCalendarModal()">&times;</span>
                          <div class="centered-icon-bell">
<i class="fa-solid fa-bell"></i>    </div>
        <h3 id="modalTitle">Appointment Details</h3>
        
        <p><strong></strong> <span id="modalDescription"></span></p>
        <p><strong></strong> <span id="modalDate"></span></p>

        <p><strong></strong> <span id="modalDuration"></span></p>
        <p><strong></strong> <span id="modalStatus"></span></p>
        <div class="modal-actions">
            <button id="calendarCloseBtn" onclick="closeCalendarModal()">Close</button>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="index.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.js"></script>
<script type="module">
    import { generateText } from '/gcpulseeee/chatbot/chatBot.config.js';
    window.generateText = generateText;
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const appointmentForm = document.querySelector('#appointmentModal form');

    if (appointmentForm) {
        appointmentForm.addEventListener('submit', function (event) {
            event.preventDefault(); // ✅ Prevent page reload

            if (this.checkValidity()) {
                const formData = new FormData(this);

                // 🔧 Send the data to PHP using fetch
                fetch('submit_appointment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.json(); // Expect JSON from PHP
                })
.then(data => {
    const appointmentModalEl = document.getElementById('appointmentModal');
    const appointmentModal = bootstrap.Modal.getInstance(appointmentModalEl);

    if (data.status === 'past_date') {
        if (appointmentModal) {
            appointmentModal.hide();
        } else {
            appointmentModalEl.style.display = 'none';
        }

        const modalEl = document.getElementById('pastDateModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

    } else if (data.status === 'conflict') {
        if (appointmentModal) {
            appointmentModal.hide();
        } else {
            appointmentModalEl.style.display = 'none';
        }

        const modalEl = document.getElementById('conflictModal');
        const modal = new bootstrap.Modal(modalEl);
        modal.show();

    } else if (data.status === 'blocked_date') {
        if (appointmentModal) {
            appointmentModal.hide();
        } else {
            appointmentModalEl.style.display = 'none';
        }

        alert("This date is blocked.");

    } else if (data.status === 'success') {
        sessionStorage.removeItem('appointmentFormData');

        if (appointmentModal) {
            appointmentModal.hide();
        } else {
            appointmentModalEl.style.display = 'none';
        }

        alert('✅ Appointment successfully submitted.');
    } else {
        console.error('Unknown server response:', data);
    }
})

                .catch(error => {
                    console.error('❌ Error submitting appointment:', error);
                });
            }
        });
    }
});
</script>
 
<script>
  // Add to your existing scripts
document.addEventListener('DOMContentLoaded', () => {
    // Chatbot initialization
    if (typeof window.generateText !== 'function') {
        console.error('Chatbot API not loaded');
        return;
    }

    // Wait for elements to be available
    setTimeout(() => {
        const elements = {
            toggleBtn: document.querySelector('.chatbot-toggle'),
            chatWindow: document.querySelector('.chatbot-window'),
            icon: document.querySelector('.chatbot-toggle i'),
            input: document.getElementById('userInput'),
            sendBtn: document.getElementById('sendBtn'),
            messagesContainer: document.getElementById('messagesContainer')
        };

        if (Object.values(elements).every(el => el !== null)) {
            initializeChatbot(elements);
        } else {
            console.warn('Required chatbot elements not found');
        }
    }, 100);

    // Comment form initialization
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(commentForm);
            fetch('submit_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                commentForm.reset();
                // Handle success
            })
            .catch(error => {
                console.error('Error submitting comment:', error);
            });
        });
    }
});

function initializeChatbot(elements) {
    const chatbot = {
        ...elements,
        init() {
            if (this.toggleBtn) {
                this.toggleBtn.addEventListener('click', () => this.toggleChat());
            }
            if (this.sendBtn) {
                this.sendBtn.addEventListener('click', () => this.sendMessage());
            }
            if (this.input) {
                this.input.addEventListener('keyup', (e) => {
                    if (e.key === 'Enter') this.sendMessage();
                });
            }
        },
        toggleChat() {
            const isOpen = this.chatWindow.style.display !== 'none';
            this.chatWindow.style.display = isOpen ? 'none' : 'flex';
            this.icon.className = isOpen ? 'bi bi-chat-dots' : 'bi bi-x-lg';
        },
        async sendMessage() {
            const text = this.input.value.trim();
            if (!text) return;

            this.appendMessage(text, 'user');
            this.input.value = '';

            try {
                const response = await window.generateText(text);
                this.appendMessage(response, 'bot');
            } catch (error) {
                console.error('Chatbot error:', error);
                this.appendMessage('Sorry, I encountered an error. Please try again.', 'bot');
            }
        },
        appendMessage(content, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.innerHTML = `
                <div class="message-wrapper">
                    ${type === 'bot' ? '<div class="chatbot-message-avatar"><i class="bi bi-robot"></i></div>' : ''}
                    <div class="message-content">${content}</div>
                </div>
                <div class="message-time">${new Date().toLocaleTimeString()}</div>
            `;
            this.messagesContainer.appendChild(messageDiv);
            this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
        }
    };

    chatbot.init();
    return chatbot;
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
function toggleComposeBox(event) {
    event.preventDefault();
    const box = document.getElementById("composeBox");
    box.style.display = (box.style.display === "none" || box.style.display === "") ? "block" : "none";
}
</script>
<script>
function submitLetter() {
  const title = document.getElementById('titleInput').value;
    const message = document.getElementById("messageInput").value.trim();
    const fileInput = document.getElementById("fileInput");
    const file = fileInput.files[0];

    // Require message or file to be present
    if (!message || !title || !file) {
        alert("Please enter a message or select a file.");
        return;
    }

    // If file is selected, validate type
    if (file) {
        const allowedTypes = [
            "application/pdf",
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
        ];
        if (!allowedTypes.includes(file.type)) {
            alert("Only PDF and DOCX files are allowed.");
            return;
        }
    }

    const formData = new FormData();
    formData.append('title', title);
    formData.append("recipient", "OSWS");
    formData.append('message', message);
    if (file) {
        formData.append("file", file);
    }

    fetch("submit_letter.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        alert("Message sent!");
        document.getElementById("messageInput").value = ""; // Clear message field
        fileInput.value = ""; // Clear file input
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
 document.body.addEventListener('click', function(e) {
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
    
    if (commentForm) { // Add null check
        commentForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(commentForm);

            fetch('submit_comment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                commentForm.reset();
                closeModal();
                alert('Comment submitted successfully!');
            })
            .catch(error => {
                console.error('Error submitting comment:', error);
            });
        });
    }
});
</script>
<!-- Add before any script tags -->
<div class="chatbot-container">
    <button class="chatbot-toggle">
        <i class="bi bi-chat-dots"></i>
    </button>

    <div class="chatbot-window" style="display: none;">
        <div class="chatbot-header">
            <div class="chatbot-avatar"><i class="bi bi-robot"></i></div>
            <div class="chatbot-info">
                <h4>GC Pulse</h4>
                <span>GC Pulse Assistant</span>
            </div>
        </div>

        <div class="chatbot-messages" id="messagesContainer"></div>

        <div class="chatbot-input">
            <input type="text" id="userInput" placeholder="Ask me anything about GC Pulse..." />
            <button id="sendBtn" class="send-btn">
                <i class="bi bi-send"></i>
            </button>
        </div>
    </div>
</div>

<!-- Update script import -->

</body>
</html>

<?php $stmt->close(); ?>