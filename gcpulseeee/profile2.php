<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$stmt = $conn->prepare("SELECT firstname, lastname, email, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "User not found.";
    exit();
}

$user = $result->fetch_assoc();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet"  href="../gcpulseeee/navbar.css">
    <link rel="stylesheet"  href="../gcpulseeee/notification.css">
    <link rel="stylesheet"  href="../gcpulseeee/body.css">
    <link rel="stylesheet"  href="../gcpulseeee/fab.css">
    <link rel="stylesheet"  href="../gcpulseeee/comment.css">
    <link rel="stylesheet"  href="../gcpulseeee/modal.css">
    <link rel="stylesheet"  href="../gcpulseeee/buttons.css">
    <link rel="stylesheet"  href="../gcpulseeee/postcard.css">
    <link rel="stylesheet"  href="../gcpulseeee/dropdown.css">
    <link rel="stylesheet"  href="../gcpulseeee/responsive.css">
    <link rel="stylesheet"  href="../gcpulseeee/index.css">
    <link rel="stylesheet"  href="../gcpulseeee/profile.css">
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
        <div class="flex-grow-1 text-center">
    <a href="index.php" class="text-white">
        <i class="fa-solid fa-house fs-4" role="button"></i>
    </a>
</div>
    </div>

    <!-- Right Section -->
    <div class="d-flex align-items-center gap-3">
        <!-- Notification Icon -->
        <div class="position-relative">
            <i class="fas fa-bell text-white fs-4" id="notifIcon" role="button"></i>
            <!-- Notification Panel -->
            <div class="dropdown-menu dropdown-menu-end p-3" id="notifPanel" style="min-width: 300px;">
                <h6 class="dropdown-header text-center">Notifications</h6>
                <ul class="list-unstyled mb-0">
                    <?php
                    $notif_query = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                    $notif_query->bind_param("i", $user_id);
                    $notif_query->execute();
                    $notif_result = $notif_query->get_result();

                    if ($notif_result->num_rows > 0):
                        while ($notif = $notif_result->fetch_assoc()):
                    ?>
                        <li class="mb-2">
                            <a href="javascript:void(0);" onclick="openPostFromNotif(<?= $notif['post_id'] ?>, <?= $notif['notification_id'] ?>)" class="text-decoration-none text-dark d-flex align-items-start">
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



<div class="profile-container">
    <img src="../gcpulseeee/img/user-icon.png" alt="User Icon">
    <h2><?= htmlspecialchars($user['firstname']) ?> <?= htmlspecialchars($user['lastname']) ?></h2>
    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
    <p><strong>Member since:</strong> <?= date('F j, Y', strtotime($user['created_at'])) ?></p>
    <a href="edit_profile.php" class="button">Edit Profile</a>
</div>

<div class="posts-container">
    <h2 style="text-align:center; margin-bottom:20px;">Your Posts</h2>
    <?php
    $post_stmt = $conn->prepare("SELECT post_id, title, content, created_at FROM posts WHERE user_id = ? ORDER BY created_at DESC");
    $post_stmt->bind_param("i", $user_id);
    $post_stmt->execute();
    $posts = $post_stmt->get_result();

    if ($posts->num_rows > 0) {
        while ($post = $posts->fetch_assoc()) {
            echo "<div class='post'>";
            echo "<h3>" . htmlspecialchars($post['title']) . "</h3>";
            echo "<p>" . nl2br(htmlspecialchars(substr($post['content'], 0, 150))) . "...</p>";
            echo "<small>Posted on " . date('F j, Y \a\t g:i A', strtotime($post['created_at'])) . "</small>";
            echo "<a href='view_post.php?post_id=" . $post['post_id'] . "' style='font-size:0.9em; color:#2c7ae7;'>View Full Post</a>";
            echo "</div>";
        }
    } else {
        echo "<p style='text-align:center; color:#777;'>You haven't posted anything yet.</p>";
    }

    $post_stmt->close();
    ?>
</div>

<a href="change_password.php" class="change-password-link">Change Password</a>


<div class="back-to-feed">
    <a href="index.php">← Back to Feed</a>
</div>

<script src="profile.js">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</script>

</body>
</html>

<?php $stmt->close(); ?>
