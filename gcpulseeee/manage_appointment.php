<?php
include("session_check.php");
include("db.php");

// Fetch all appointments
$query = $conn->query("
    SELECT appointments.*, users.firstname, users.lastname 
    FROM appointments 
    JOIN users ON appointments.user_id = users.user_id 
    ORDER BY created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appointments</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="body.css">
    <link rel="stylesheet" href="table.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2C5F34;
            --primary-light: #e6f0e8;
            --primary-dark: #234a29;
            --secondary-color: #4a6fa5;
            --text-dark: #333;
            --text-light: #666;
            --bg-light: #f8f9fa;
            --bg-white: #ffffff;
            --success-color: #34A853;
            --success-bg: #E6F4EA;
            --warning-color: #FBBC04;
            --warning-bg: #FEF7E0;
            --danger-color: #EA4335;
            --danger-bg: #FCE8E6;
            --info-color: #4285F4;
            --info-bg: #E8F0FE;
            --border-color: #E0E4E8;
            --shadow-sm: 0 2px 5px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --transition: all 0.3s ease;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
            background-color: #f5f7fa;
        }
        
        /* Main content styles */
        .main-content {
            margin-left: 200px;
            padding: 30px;
            transition: var(--transition);
            background-color: #f5f7fa;
            min-height: 100vh;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(0,0,0,0.08);
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: var(--bg-white);
            border-radius: var(--radius-md);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .stat-title {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .stat-pending .stat-value {
            color: var(--warning-color);
        }
        
        .stat-approved .stat-value {
            color: var(--success-color);
        }
        
        .stat-rejected .stat-value {
            color: var(--danger-color);
        }
        
        .stat-total .stat-value {
            color: var(--secondary-color);
        }
        
        /* Filter section styles */
        .filter-section {
            background-color: var(--bg-white);
            padding: 20px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .filter-section label {
            font-weight: 500;
            color: var(--text-light);
            margin-right: 8px;
        }
        
        select {
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            background-color: var(--bg-white);
            color: var(--text-dark);
            font-size: 14px;
            min-width: 180px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23555' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            transition: var(--transition);
        }
        
        select:hover {
            border-color: #C0C4C8;
        }
        
        select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(44, 95, 52, 0.15);
            outline: none;
        }
        
        button[type="submit"] {
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        button[type="submit"]:hover {
            background-color: var(--primary-dark);
        }
        
        button[type="submit"]:active {
            transform: translateY(1px);
        }
        
        /* Table styles */
.table-container {
    background-color: var(--bg-white);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    overflow: auto; /* Enable scrolling */
    max-height: 540px; /* Optional: set a max height for scrollable area */
}

/* Hide scrollbar */
.table-container::-webkit-scrollbar {
    display: none;
}

.table-container {
    -ms-overflow-style: none;  /* Internet Explorer */
    scrollbar-width: none;  /* Firefox */
}

        
        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-white);
            overflow: hidden;
        }
        
        th {
            background-color: var(--bg-light);
            color: var(--text-light);
            font-weight: 600;
            text-align: left;
            padding: 16px;
            font-size: 14px;
            border-bottom: 2px solid var(--border-color);
            position: sticky;
            top: 0;
        }
        
        td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
            font-size: 14px;
            color: var(--text-dark);
        }
        
        tr:hover {
            background-color: var(--primary-light);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        /* Status badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 500;
            line-height: 1;
        }
        
        .status-badge::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-approved {
            background-color: var(--success-bg);
            color: var(--success-color);
        }
        
        .status-approved::before {
            background-color: var(--success-color);
        }
        
        .status-rejected {
            background-color: var(--danger-bg);
            color: var(--danger-color);
        }
        
        .status-rejected::before {
            background-color: var(--danger-color);
        }
        
        .status-pending {
            background-color: var(--warning-bg);
            color: var(--warning-color);
        }
        
        .status-pending::before {
            background-color: var(--warning-color);
        }
        
        /* Action buttons container */
        .actions {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
            align-items: center;
        }
        
        /* Common button styles */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .action-btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.05);
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .action-btn:hover::after {
            opacity: 1;
        }
        
        .action-btn:active {
            transform: scale(0.95);
        }
        
        /* View button */
        .view-btn {
            background-color: var(--info-bg);
            color: var(--info-color);
        }
        
        .view-btn:hover {
            background-color: #D2E3FC;
        }
        
        /* Approve button */
        .approve-btn {
            background-color: var(--success-bg);
            color: var(--success-color);
        }
        
        .approve-btn:hover {
            background-color: #CEEAD6;
        }
        
        /* Reject button */
        .reject-btn {
            background-color: var(--danger-bg);
            color: var(--danger-color);
        }
        
        .reject-btn:hover {
            background-color: #FADBD8;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.2s ease;
            overflow-y: auto;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: var(--bg-white);
            margin: 10% auto;
            padding: 0;
            border: none;
            width: 450px;
            border-radius: var(--radius-md);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
            transform: translateY(20px);
            animation: slideUp 0.3s forwards;
        }
        
        @keyframes slideUp {
            to { transform: translateY(0); }
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;

        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
            text-align: center;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .close {
            font-size: 22px;
            font-weight: 400;
            cursor: pointer;
            color: var(--text-light);
            line-height: 1;
            transition: color 0.2s;
        }
        
        .close:hover {
            color: var(--text-dark);
        }
        
        .modal-btn {
            padding: 10px 16px;
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .confirm-btn {
            background-color: var(--primary-color);
            color: white;
        }
        
        .confirm-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .cancel-btn {
            background-color: #F1F3F4;
            color: var(--text-light);
        }
        
        .cancel-btn:hover {
            background-color: #E8EAED;
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            color: #E8EAED;
        }
        
        .empty-state h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            max-width: 400px;
            margin: 0 auto;
            color: var(--text-light);
        }
        
        /* Responsive styles */
        @media (max-width: 992px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .page-title {
                font-size: 24px;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filter-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .filter-section select,
            .filter-section button {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 650px;
            }
            
            .modal-content {
                width: 90%;
                margin: 20% auto;
            }
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
        <h1 class="page-title">Appointments</h1>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-container">
        <?php
        $total = $query->num_rows;
        $pending = 0;
        $approved = 0;
        $rejected = 0;
        
        // Count statuses
        $query->data_seek(0);
        while ($row = $query->fetch_assoc()) {
            if ($row['status'] == 'pending') $pending++;
            else if ($row['status'] == 'approved') $approved++;
            else if ($row['status'] == 'rejected') $rejected++;
        }
        $query->data_seek(0);
        ?>
        
        <div class="stat-card stat-total">
            <div class="stat-title">Total Appointments</div>
            <div class="stat-value"><?php echo $total; ?></div>
        </div>
        
        <div class="stat-card stat-pending">
            <div class="stat-title">Pending</div>
            <div class="stat-value"><?php echo $pending; ?></div>
        </div>
        
        <div class="stat-card stat-approved">
            <div class="stat-title">Approved</div>
            <div class="stat-value"><?php echo $approved; ?></div>
        </div>
        
        <div class="stat-card stat-rejected">
            <div class="stat-title">Declined</div>
            <div class="stat-value"><?php echo $rejected; ?></div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form method="get" action="" class="filter-form">
            <label for="status">Filter by Status:</label>
            <select name="status" id="status">
                <option value="all" <?php echo (isset($_GET['status']) && $_GET['status'] == 'all') ? 'selected' : ''; ?>>All</option>
                <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Declined</option>
            </select>
            <button type="submit"><i class="fas fa-filter"></i> Filter</button>
        </form>
    </div>

    <!-- Table Container -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Appointment Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
            while ($row = $query->fetch_assoc()):
                if ($status_filter !== 'all' && $row['status'] !== $status_filter) {
                    continue;
                }
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></td>
                    <td><?= date('F j, Y g:i A', strtotime($row['appointment_date'])) ?></td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                            <?php echo ucfirst(htmlspecialchars($row['status'])); ?>
                        </span>
                    </td>
                    <td class="actions">
                        <button class="action-btn view-btn" title="View Details"
        onclick="viewAppointment('<?php echo htmlspecialchars($row['firstname'] . ' ' . $row['lastname'], ENT_QUOTES); ?>',
                                 '<?php echo $row['appointment_date']; ?>',
                                 '<?php echo $row['status']; ?>');">
    <i class="fas fa-eye"></i>
</button>


                        <?php if ($row['status'] == 'pending'): ?>
                            <?php $escaped_username = htmlspecialchars($row['firstname'] . ' ' . $row['lastname'], ENT_QUOTES, 'UTF-8'); ?>
                            <a href="#"
                               class="action-btn approve-btn"
                               title="Approve"
                               onclick="openModal('approve', '<?php echo $escaped_username; ?>', '<?php echo $row['appointment_id']; ?>'); return false;">
                                <i class="fas fa-check"></i>
                            </a>

                            <a href="#"
                               class="action-btn reject-btn"
                               title="Reject"
                               onclick="openModal('reject', '<?php echo $escaped_username; ?>', '<?php echo $row['appointment_id']; ?>'); return false;">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ✅ Confirmation modal -->
<div id="confirmationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Confirm Action</h3>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <p id="modalMessage"></p>
        </div>
        <div class="modal-footer">
            <button id="confirmBtn" class="modal-btn confirm-btn">Confirm</button>
            <button id="cancelBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>
<!-- View Details Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Appointment Details</h3>
            <span class="close-view">&times;</span>
        </div>
        <div class="modal-body" id="viewModalContent">
            <!-- Appointment details will be injected here -->
        </div>
        <div class="modal-footer">
            <button class="modal-btn cancel-btn close-view">Close</button>
        </div>
    </div>
</div>


<script>
let selectedAction = '';
let selectedAppointmentId = '';

function openModal(action, username, appointmentId) {
    selectedAction = action;
    selectedAppointmentId = appointmentId;

    const modal = document.getElementById('confirmationModal');
    const message = document.getElementById('modalMessage');
    const title = document.getElementById('modalTitle');

    title.textContent = `Confirm ${action.charAt(0).toUpperCase() + action.slice(1)}`;
    message.textContent = `Are you sure you want to ${action} the appointment for "${username}"?`;

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
        if (selectedAction && selectedAppointmentId) {
            const url = `update_appointment.php?appointment_id=${encodeURIComponent(selectedAppointmentId)}&status=${encodeURIComponent(selectedAction)}`;
            window.location.href = url;
        }
    };
};
function viewAppointment(username, date, status) {
    const viewModal = document.getElementById('viewModal');
    const content = document.getElementById('viewModalContent');

    content.innerHTML = `
        <p><strong>User:</strong> ${username}</p>
        <p><strong>Appointment Date:</strong> ${new Date(date).toLocaleString()}</p>
        <p><strong>Status:</strong> ${status.charAt(0).toUpperCase() + status.slice(1)}</p>
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

</body>
</html>
