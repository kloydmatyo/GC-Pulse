function openPostFromNotif(postId, notifId) {
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



function closePostModal() {
    document.getElementById('postDetailModal').style.display = 'none';
}


    function openCreatePostModal() {
        document.getElementById('createPostModal').style.display = 'block';
    }
    function closeCreatePostModal() {
        document.getElementById('createPostModal').style.display = 'none';
    }
    function openModal(postId) {
        document.getElementById('modalPostId').value = postId;
        document.getElementById('parentCommentId').value = '';
        document.getElementById('commentModal').style.display = 'block';
    }
    function closeModal() {
        document.getElementById('commentModal').style.display = 'none';
    }
    function replyToComment(commentId) {
        document.getElementById('parentCommentId').value = commentId;
        document.getElementById('commentModal').style.display = 'block';
    }
    function editComment(id, content) {
        const commentBox = prompt("Edit your comment:", content);
        if (commentBox !== null) {
            fetch("edit_comment.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: "comment_id=" + id + "&content=" + encodeURIComponent(commentBox)
            }).then(() => location.reload());
        }
    }
    function toggleDropdown() {
        const menu = document.getElementById('dropdownMenu');
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    }

    // Toggle notification panel
    const notifIcon = document.getElementById('notifIcon');
    const notifPanel = document.getElementById('notifPanel');

    notifIcon.addEventListener('click', () => {
        notifPanel.style.display = notifPanel.style.display === 'block' ? 'none' : 'block';
        
    });
    // Hide panel when clicking outside
document.addEventListener('click', (event) => {
    const isClickInside = notifPanel.contains(event.target) || notifIcon.contains(event.target);

    if (!isClickInside) {
        notifPanel.style.display = 'none';
    }
});
    

    function markAsRead(notificationId) {
        fetch('mark_read.php?notification_id=${notificationId}').then(() => location.reload());
    }

    // Collapsible comments
    document.querySelectorAll(".collapsible").forEach(btn => {
        btn.addEventListener("click", function() {
            this.classList.toggle("active");
            const content = this.nextElementSibling;
            content.style.display = (content.style.display === "block") ? "none" : "block";
        });
    });
function openAppointmentModal() {
    document.getElementById("appointmentModal").style.display = "block";
}

function closeAppointmentModal() {
    document.getElementById("appointmentModal").style.display = "none";
}

window.onclick = function(event) {
    const modal = document.getElementById("appointmentModal");
    if (event.target == modal) {
        closeAppointmentModal();
    }
}
window.onclick = function(event) {
    const editModal = document.getElementById('editPostModal');
    if (event.target == editModal) {
        editModal.style.display = "none";
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('customSidebar');
    sidebar.classList.add('open'); // Add open class by default
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.action-like-btn').forEach(button => {
        button.addEventListener('click', function () {
            const postId = this.getAttribute('data-post-id');
            fetch('toggle_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'post_id=' + postId
            })
            .then(response => response.text())
            .then(data => {
                // Toggle UI state
                this.classList.toggle('liked');
                this.textContent = this.classList.contains('liked') ? 'Unlike' : 'Like';
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
    });
});
