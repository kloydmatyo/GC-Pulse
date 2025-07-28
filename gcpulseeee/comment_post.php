<?php
if (isset($_GET['post_id'])) {
    $post_id = intval($_GET['post_id']);
    // Redirect to comment form or show form here
    header("Location: comment_form.php?post_id=$post_id");
    exit();
}
?>
