<form action="submit_comment.php" method="POST">
    <input type="hidden" name="post_id" value="<?= $_GET['post_id'] ?>">
    <textarea name="comment" required></textarea>
    <button type="submit">Post Comment</button>
</form>
