<?php
session_start();
include("db.php");

$post_id = intval($_GET['id'] ?? 0);

$user_id = $_SESSION['user_id'] ?? null;

// Mark notification as read if passed
if (isset($_GET['notif'])) {
    $notif_id = intval($_GET['notif']);
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ?");
    $stmt->bind_param("i", $notif_id);
    $stmt->execute();
}

// Get post
$stmt = $conn->prepare("SELECT posts.*, users.firstname FROM posts JOIN users ON posts.user_id = users.user_id WHERE post_id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();

// ✅ This MUST be before any HTML access to $post
if (!$post) {
    echo "<p>Post not found.</p>";
    exit; // 🚨 Prevents rest of script from running
}


// Get like count
$like_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM likes WHERE post_id = ?");
$like_stmt->bind_param("i", $post_id);
$like_stmt->execute();
$like_count = $like_stmt->get_result()->fetch_assoc()['total'];

// Check if user liked
$liked = false;
if ($user_id) {
    $check_like = $conn->prepare("SELECT * FROM likes WHERE post_id = ? AND user_id = ?");
    $check_like->bind_param("ii", $post_id, $user_id);
    $check_like->execute();
    $liked = $check_like->get_result()->num_rows > 0;
}

// Get comments
$comment_query = $conn->prepare("
    SELECT comments.*, users.firstname, users.profile_picture
    FROM comments 
    JOIN users ON comments.user_id = users.user_id 
    WHERE post_id = ?
    ORDER BY created_at DESC
");
$comment_query->bind_param("i", $post_id);
$comment_query->execute();
$comments = $comment_query->get_result();
$comment_list = [];
while ($row = $comments->fetch_assoc()) {
    $comment_list[] = $row;
}



// Render comments
function renderComments($comments, $parent_id = null) {
    $html = '';
    foreach ($comments as $comment) {
         if ($comment['parent_comment_id'] == $parent_id) {
$html .= '<div class="comment">';
$html .= '<div class="comment-content-wrapper">';
$default_avatar = '../gcpulseeee/img/user-icon.png';
$avatar_path = !empty($comment['profile_picture']) ? $comment['profile_picture'] : '';
$avatar_file = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($avatar_path, '/');
$avatar = (!empty($avatar_path) && file_exists($avatar_file)) ? $avatar_path : $default_avatar;

$html .= '<img src="' . htmlspecialchars($avatar) . '" class="comment-avatar">';

$html .= '<div class="comment-content">';
$html .= '<strong>' . htmlspecialchars($comment['firstname']) . '</strong>';
$html .= '<p>' . htmlspecialchars($comment['comment']) . '</p>';
$html .= '<small>' . date('F j, Y H:i', strtotime($comment['created_at'])) . '</small>';
$html .= '<div class="comment-actions">';
$html .= '<button type="button" class="reply-btn" onclick="toggleModalReplyForm(' . $comment['comment_id'] . ', ' . $comment['post_id'] . ')">Reply</button>';
$html .= '<div id="modalReplyForm-' . $comment['comment_id'] . '" class="reply-form" style="display:none;">';
$html .= '<textarea class="reply-textarea" placeholder="Write your reply..."></textarea>';
$html .= '<button type="button" onclick="submitModalReply(' . $comment['comment_id'] . ', ' . $comment['post_id'] . ')">Submit Reply</button>';
$html .= '</div>';

if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $comment['user_id']) {
    $html .= '<button onclick="editComment(' . $comment['comment_id'] . ', \'' . htmlspecialchars($comment['comment']) . '\')">Edit</button>';
    $html .= '<form method="POST" action="delete_comment.php" style="display:inline;">
                <input type="hidden" name="comment_id" value="' . $comment['comment_id'] . '">
                <button id= "btn-delete" type="submit">Delete</button>
              </form>';
}

$html .= '</div>'; // actions
$html .= '</div>'; // comment-content
$html .= '</div>'; // comment-content-wrapper
$html .= '<div class="replies">';
$html .= renderComments($comments, $comment['comment_id']);
$html .= '</div>';
$html .= '</div>'; // comment

$html .= '<hr class="comment-divider">';

        }
    }
    return $html;
}
?>

<div class="modal-post">
    <h2 id="postTitle"><?= htmlspecialchars($post['title']) ?></h2>
    <p id="postAuthor"><em>By <?= htmlspecialchars($post['firstname']) ?> on <?= date('F j, Y', strtotime($post['created_at'])) ?></em></p>
    <?php if (!empty($post['image_path'])): ?>
        <img src="<?= htmlspecialchars($post['image_path']) ?>" alt="Post Image" style="width:600px; border-radius:10px; margin:0 20px;">
    <?php endif; ?>
    <p id="postContent"><strong><?= nl2br(htmlspecialchars($post['content'])) ?></strong></p>

    <div class="form-buttons">
    <!-- Like button -->
<form class="like-form" data-post-id="<?= $post_id ?>">
    <button id="likeBtn" type="submit"> <?= $liked ? 'Unlike' : 'Like' ?><p id="likeCount"><?= $like_count ?></p></button>
    
</form>


    <hr>

    <!-- Comment Form -->
<!-- Comment Form -->
<form class="comment-form" data-post-id="<?= $post_id ?>">
    <input type="hidden" name="parent_comment_id" id="modalParentCommentId" value="">
    <textarea id="commentText" name="comment" placeholder="Write your comment..." required style="width: 615px; height: 80px; resize: vertical;"></textarea><br>
    <button id="commentBtn" type="submit">Comment</button>
</form>
</div>

<!-- Comments Section (wrapped for AJAX reload) -->
<h4>Comments</h4>
<div class="modal-comments" id="modalComments<?= $post_id ?>">
    <?= renderComments($comments_by_post[$post_id] ?? $comment_list ?? []) ?>
</div>

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
</script>
