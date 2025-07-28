<?php
session_start();
include("db.php");

// Recursive comment rendering
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


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
    $comment = trim($_POST['comment']);
    $parent_comment_id = isset($_POST['parent_comment_id']) && is_numeric($_POST['parent_comment_id']) ? intval($_POST['parent_comment_id']) : null;

    // Validate post exists
    $check_post = $conn->prepare("SELECT post_id FROM posts WHERE post_id = ?");
    $check_post->bind_param("i", $post_id);
    $check_post->execute();
    if ($check_post->get_result()->num_rows === 0) {
        http_response_code(400);
        echo "Invalid post ID.";
        exit;
    }

    if (!empty($comment)) {
        if ($parent_comment_id === null) {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comment, parent_comment_id, created_at) VALUES (?, ?, ?, NULL, NOW())");
            $stmt->bind_param("iis", $post_id, $user_id, $comment);
        } else {
            $stmt = $conn->prepare("INSERT INTO comments (post_id, user_id, comment, parent_comment_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisi", $post_id, $user_id, $comment, $parent_comment_id);
        }
        $stmt->execute();

        // Notify post owner except if commenter is the post owner
        $owner_stmt = $conn->prepare("SELECT user_id FROM posts WHERE post_id = ?");
        $owner_stmt->bind_param("i", $post_id);
        $owner_stmt->execute();
        $owner_result = $owner_stmt->get_result();
        $owner = $owner_result->fetch_assoc();

if ($owner && $owner['user_id'] != $user_id) {
    $post_owner_id = $owner['user_id'];

    // ✅ Securely fetch commenter's first name from DB
    $name_stmt = $conn->prepare("SELECT firstname FROM users WHERE user_id = ?");
    $name_stmt->bind_param("i", $user_id);
    $name_stmt->execute();
    $name_result = $name_stmt->get_result();
    $name_row = $name_result->fetch_assoc();
    $commenter_name = $name_row['firstname'] ?? 'Someone';

    $message = "<strong>" . htmlspecialchars($commenter_name) . "</strong> commented on your post";

    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, post_id) VALUES (?, ?, ?)");
    $notif_stmt->bind_param("isi", $post_owner_id, $message, $post_id);
    $notif_stmt->execute();
}


        

        // Fetch updated comments
        $result = $conn->prepare("SELECT c.comment_id, c.parent_comment_id, c.post_id, c.comment, c.created_at, c.user_id, u.firstname, u.profile_picture
                                  FROM comments c 
                                  JOIN users u ON c.user_id = u.user_id 
                                  WHERE c.post_id = ? 
                                  ORDER BY c.created_at DESC");
        $result->bind_param("i", $post_id);
        $result->execute();
        $res = $result->get_result();

        $comments = [];
        while ($row = $res->fetch_assoc()) {
            $comments[] = $row;
        }

        echo renderComments($comments);
        exit;
    }
}


http_response_code(400);
echo "Invalid request";
exit;
