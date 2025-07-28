<?php
include("session_check.php");
include("db.php");

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Validate input (allow only specific statuses)
$allowed_statuses = ['pending', 'approved', 'declined'];
$filter_query = "";

if (in_array($status_filter, $allowed_statuses)) {
    $filter_query = "WHERE l.status = '$status_filter'";
}

// Fetch letters (filtered or all)
$letters = $conn->query("SELECT l.letter_id, l.user_id, l.osws_id, l.title, l.content, l.status, l.response, l.created_at, l.file_path, u.firstname 
                         FROM letters l 
                         JOIN users u ON l.user_id = u.user_id 
                         $filter_query");


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $letter_id = $_POST['letter_id'];
    $status = $_POST['status'];
    $response = $_POST['response'];

    // Update letter status
    $update = $conn->prepare("UPDATE letters SET status = ?, response = ? WHERE letter_id = ?");
    $update->bind_param("ssi", $status, $response, $letter_id);
    $update->execute();

    // Notify organization
    $user_query = $conn->prepare("SELECT user_id FROM letters WHERE letter_id = ?");
    $user_query->bind_param("i", $letter_id);
    $user_query->execute();
    $user = $user_query->get_result()->fetch_assoc();

    $message = "Your letter has been " . ucfirst($status);
    $notify = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $notify->bind_param("is", $user['user_id'], $message);
    $notify->execute();

    echo "Letter status updated!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Letters</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="manage_letters.css">
    <style>
body {
    margin: 0;
    padding: 0;
    font-family: 'Inter', sans-serif;
    background-color: #f8f9fa;
}

.main-content {
    margin-left: 180px;
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
    margin-left: 180px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding: 16px 24px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    width: 1500px;
    margin-left: 50px;
}

.page-title {
    font-size: 24px;
    color: #2C5F34;
    font-weight: 600;
    margin: 0;
}

.header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.filter-group {
    display: flex;
    gap: 12px;
    align-items: center;
}

.search-box {
    position: relative;
    width: 300px;
}

.search-box input {
    width: 100%;
    padding: 10px 16px 10px 40px;
    border: 2px solid #e1e5ea;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.search-box i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #6b7280;
}

.letters-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr); /* 4 items per row */
    gap: 20px;
    margin-top: 24px;
    margin-left: 50px;  /* Centering the grid horizontally */
    margin-right: auto; /* Centering the grid horizontally */
    max-width: 1200px; /* Optional: you can set a max width for the container */
}

.letters-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    padding: 25px;
}

.filters {
    margin-bottom: 24px;
    background: white;
    padding: 16px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.filter-dropdown {
    padding: 10px 16px;
    border: 2px solid #e1e5ea;
    border-radius: 8px;
    min-width: 200px;
    font-size: 14px;
    color: #2C5F34;
    background-color: white;
    transition: all 0.2s;
    cursor: pointer;
}

.filter-dropdown:focus {
    border-color: #2C5F34;
    outline: none;
    box-shadow: 0 0 0 3px rgba(44, 95, 52, 0.1);
}

.letter-card {
    background: white;
    border: 1px solid #eef0f2;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition: all 0.2s;
}

.letter-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}

.letter-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.letter-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6b7280;
    font-size: 13px;
}

.letter-content {
    flex: 1;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

.status-badge.pending {
    background-color: #fff7ed;
    color: #c2410c;
}

.status-badge.approved {
    background-color: #f0fdf4;
    color: #15803d;
}

.status-badge.declined {
    background-color: #fef2f2;
    color: #b91c1c;
}

.action-buttons {
    display: flex;
    gap: 8px;
    margin-top: auto;
}

.action-btn {
    flex: 1;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
}

.response-input {
    width: 300px;
    height: 80px;
    resize: vertical;
    padding: 12px;
    border-radius: 8px;
    border: 2px solid #e1e5ea;
    font-family: inherit;
    font-size: 14px;
    transition: all 0.2s;
}

.response-input:focus {
    border-color: #2C5F34;
    outline: none;
    box-shadow: 0 0 0 3px rgba(44, 95, 52, 0.1);
}

.approve-btn { 
    color: #28a745;
    background: rgba(40, 167, 69, 0.1);
}

.decline-btn { 
    color: #dc3545;
    background: rgba(220, 53, 69, 0.1);
}

.info-btn { 
    color: #2C5F34;
    background: rgba(44, 95, 52, 0.1);
}

.action-btn:hover {
    transform: translateY(-1px);
}

.attachment-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #2C5F34;
    text-decoration: none;
    padding: 6px 12px;
    border-radius: 6px;
    background: rgba(44, 95, 52, 0.1);
    transition: all 0.2s;
}

.attachment-link:hover {
    background: rgba(44, 95, 52, 0.15);
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    animation: fadeIn 0.2s;
}

.modal-content {
    background: white;
    max-width: 500px;
    width: 90%;
    margin: 40px auto;
    border-radius: 12px;
    padding: 24px;
    position: relative;
    animation: slideIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-title {
    color: #2C5F34;
    margin: 0 0 16px 0;
    font-size: 20px;
}

.modal-header {
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-body {
    margin-bottom: 24px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.modal-buttons {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

.modal-btn {
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
}

.confirm-btn {
    background: #2C5F34;
    color: white;
}

.cancel-btn {
    background: #f8f9fa;
    color: #2C5F34;
    border: 2px solid #2C5F34;
}

.modal-btn:hover {
    transform: translateY(-1px);
}
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <h1 >GC Pulse</h1>
        </div>
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a>
        <a href="users.php"><i class="fa-solid fa-users">‌</i> Users</a>
        <a href="manage_appointment.php"><i class="fas fa-calendar"></i> Appointments</a>
        <a href="manage_letters.php"><i class="fas fa-envelope"></i> Proposals</a>
        <a href="manage_post.php"><i class="fa-solid fa-laptop">‌</i> Posts</a>
        
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Manage Letters</h1>
            <div class="header-actions">
                <div class="filter-group">
                    <select id="statusFilter" class="filter-dropdown">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="declined">Declined</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="letters-grid">
            <?php while ($row = $letters->fetch_assoc()): ?>
            <div class="letter-card">
                <div class="letter-header">
                    <div class="letter-meta">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo date('M j, Y', strtotime($row['created_at'])); ?></span>
                    </div>
                    <div class="status-badge <?php echo $row['status']; ?>">
                        <span class="dot"></span>
                        <?php echo ucfirst($row['status']); ?>
                    </div>
                </div>
                
                <div class="letter-content">
                    <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                    <p><strong>From:</strong> <?php echo htmlspecialchars($row['firstname']); ?></p>
                    <?php if ($row['file_path']): ?>
                    <a href="<?php echo htmlspecialchars($row['file_path']); ?>" download class="attachment-link">
                        <i class="fas fa-paperclip"></i> Attachment
                    </a>
                    <?php endif; ?>
                </div>

                <form action="manage_letters.php" method="POST" class="response-form" data-letter-id="<?php echo $row['letter_id']; ?>">
                    <input type="hidden" name="letter_id" value="<?php echo $row['letter_id']; ?>">
                    <textarea name="response" placeholder="Write response..." class="response-input" <?php echo ($row['status'] !== 'pending') ? 'readonly disabled' : ''; ?>>
    <?php echo htmlspecialchars($row['response']); ?>
</textarea>
                    
                    <div class="action-buttons">
                        <?php if ($row['status'] === 'pending'): ?>
                        <button type="button" class="action-btn approve-btn" 
                            onclick="openModal('approved', '<?php echo addslashes($row['firstname']); ?>', '<?php echo $row['letter_id']; ?>')">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button type="button" class="action-btn decline-btn" 
                            onclick="openModal('declined', '<?php echo addslashes($row['firstname']); ?>', '<?php echo $row['letter_id']; ?>')">
                            <i class="fas fa-times"></i> Decline
                        </button>
                        <?php endif; ?>
                        <button type="button" class="action-btn info-btn" 
                            onclick="viewLetter('<?php echo addslashes($row['firstname']); ?>', '<?php echo addslashes($row['title']); ?>', '<?php echo addslashes($row['content']); ?>', '<?php echo $row['status']; ?>', '<?php echo addslashes($row['response']); ?>')">
                            <i class="fas fa-info-circle"></i> Details
                        </button>
                    </div>
                </form>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmationModal" style="display:none; position:fixed; z-index:1000; left:0;top:0;width:100%;height:100%; overflow:auto; background: rgba(0,0,0,0.5);">
      <div style="background:#fff; margin:10% auto; padding:20px; border-radius:8px; width:300px; position:relative;">
        <span class="close" style="position:absolute; top:10px; right:15px; cursor:pointer; font-weight:bold;">&times;</span>
        <h3 id="modalTitle"></h3>
        <p id="modalMessage"></p>
        <button id="confirmBtn" style="margin-right:10px;">Confirm</button>
        <button id="cancelBtn" style="margin-right:10px;" style="background-color: #000; color: #2C5F34; border: 2px solid #2C5F34;">Cancel</button>
      </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" style="display:none; position:fixed; z-index:1000; left:0;top:0;width:100%;height:100%; overflow:auto; background: rgba(0,0,0,0.5);">
      <div style="background:#fff; margin:10% auto; padding:20px; border-radius:8px; width:350px; position:relative;">
        <span class="close-view" style="position:absolute; top:10px; right:15px; cursor:pointer; font-weight:bold;">&times;</span>
        <div id="viewModalContent"></div>
      </div>
    </div>

<script>
let selectedAction = '';
let selectedLetterId = '';

function openModal(action, firstname, letterId) {
    selectedAction = action;
    selectedLetterId = letterId;

    const modal = document.getElementById('confirmationModal');
    const message = document.getElementById('modalMessage');
    const title = document.getElementById('modalTitle');

    title.textContent = `Confirm ${action.charAt(0).toUpperCase() + action.slice(1)}`;
    message.textContent = `Are you sure you want to ${action} the letter from "${firstname}"?`;

    modal.style.display = 'block';
}

function closeModal() {
    document.getElementById('confirmationModal').style.display = 'none';
}

window.onload = function () {
    const modal = document.getElementById('confirmationModal');
    document.querySelector('.close').onclick = closeModal;
    document.getElementById('cancelBtn').onclick = closeModal;

    window.onclick = function (event) {
        if (event.target === modal) {
            closeModal();
        }
    };

    document.getElementById('confirmBtn').onclick = function () {
        if (selectedAction && selectedLetterId) {
            // Submit the form corresponding to the letter ID with the selected status
            const form = document.querySelector(`form[data-letter-id='${selectedLetterId}']`);
            if (form) {
                // Remove any old hidden status inputs we may have added
                const oldStatusInput = form.querySelector('input[name="status"]');
                if (oldStatusInput && oldStatusInput.getAttribute('data-hidden-input') === 'true') {
                    oldStatusInput.remove();
                }
                // Add hidden input for status
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = selectedAction;
                statusInput.setAttribute('data-hidden-input', 'true');
                form.appendChild(statusInput);

                // Hide the action buttons after approval or decline
                const actionButtons = form.querySelector('.action-buttons');
                actionButtons.style.display = 'none'; // Hide action buttons

                form.submit();
            }
            closeModal();
        }
    };
};


// View letter details modal
function viewLetter(firstname, title, content, status, response) {
    const viewModal = document.getElementById('viewModal');
    const contentDiv = document.getElementById('viewModalContent');

    contentDiv.innerHTML = `
        <p><strong>User:</strong> ${firstname}</p>
        <p><strong>Title:</strong> ${title}</p>
        <p><strong>Content:</strong> ${content}</p>
        <p><strong>Status:</strong> ${status.charAt(0).toUpperCase() + status.slice(1)}</p>
        <p><strong>Response:</strong> ${response ? response : 'No response yet'}</p>
    `;

    viewModal.style.display = 'block';
}

// Close handlers for view modal
document.querySelectorAll('.close-view').forEach(el => {
    el.onclick = () => {
        document.getElementById('viewModal').style.display = 'none';
    };
});

window.onclick = function(event) {
    const viewModal = document.getElementById('viewModal');
    if (event.target === viewModal) {
        viewModal.style.display = 'none';
    }
};
</script>
<script>
document.getElementById('statusFilter').addEventListener('change', function() {
    const selectedStatus = this.value;
    // Reload page with selected status as GET parameter
    const url = new URL(window.location);
    if(selectedStatus) {
        url.searchParams.set('status', selectedStatus);
    } else {
        url.searchParams.delete('status');
    }
    window.location.href = url.toString();
});
document.addEventListener('DOMContentLoaded', () => {
    const statusFilter = document.getElementById('statusFilter');

    // Check if there is a saved status in localStorage and set it
    const savedStatus = localStorage.getItem('statusFilter');
    if (savedStatus) {
        statusFilter.value = savedStatus;
    }

    // Listen for changes in the status dropdown
    statusFilter.addEventListener('change', (event) => {
        // Save the selected status in localStorage
        const selectedStatus = event.target.value;
        localStorage.setItem('statusFilter', selectedStatus);
        
        // Optionally, you can filter the data here or trigger a function to update the content
        filterDataByStatus(selectedStatus);
    });
});

function filterDataByStatus(status) {
    // Add logic here to filter the displayed content by status
    console.log('Filtering by status:', status);
}

</script>

</body>
</html>
