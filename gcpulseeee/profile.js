const notifIcon = document.getElementById("notifIcon");
const notifPanel = document.getElementById("notifPanel");
const dropdownToggle = document.querySelector(".dropdown-toggle");
const dropdownMenu = document.getElementById("dropdownMenu");

notifIcon.addEventListener("click", () => {
    notifPanel.style.display = notifPanel.style.display === "block" ? "none" : "block";
});

function toggleDropdown() {
    dropdownMenu.style.display = dropdownMenu.style.display === "block" ? "none" : "block";
}

function markAsRead(notificationId) {
    window.location.href = `post.php?id=${notificationId}`;
}

// Close dropdowns on click outside
document.addEventListener("click", function(event) {
    if (!notifIcon.contains(event.target) && !notifPanel.contains(event.target)) {
        notifPanel.style.display = "none";
    }
    if (!dropdownToggle.contains(event.target) && !dropdownMenu.contains(event.target)) {
        dropdownMenu.style.display = "none";
    }
});