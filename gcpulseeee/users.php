<?php
include("session_check.php");
include("db.php");

// Handle search input
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Get total users count
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

// Get new users (last 30 days)
$new_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];

// Get active users (assuming you have a last_login field)
$active_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_verified = 1")->fetch_assoc()['count'];

// Get users list with optional search
$sql = "SELECT * FROM users";
if (!empty($search)) {
    $sql .= " WHERE firstname LIKE '%$search%' OR lastname LIKE '%$search%' OR email LIKE '%$search%'";
}
$sql .= " ORDER BY created_at DESC";

$result = $conn->query($sql);
$users = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Users Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="sidebar.css">
    <style>
        .main-content {
            margin-left: 200px;
            padding: 2rem;
        }
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stats-value {
            font-size: 2rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }
        .stats-label {
            color: #666;
            font-size: 0.9rem;
        }
        .trend-up {
            color: #22c55e;
        }
        .trend-down {
            color: #ef4444;
        }
        .users-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-top: 2rem;
        }
        .table th {
            font-weight: 500;
            color: #666;
            border-bottom-width: 1px;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
        }
        .status-active {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-warned {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-blocked {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .search-bar {
            max-width: 400px;
        }
        .table-wrapper {
    width: 100%;
}

.table-wrapper > table {
    width: 100%;
    table-layout: fixed;
}

.table-body-wrapper {
    max-height: 530px; /* Adjust based on your design */
    overflow-y: scroll;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

.table-body-wrapper::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
}

.table-body-wrapper > table {
    width: 100%;
    table-layout: fixed;
}

    </style>
</head>
<body class="bg-light">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Users Management</h2>
            <div class="d-flex align-items-center">
                <div class="search-bar me-3">
                    <form method="GET" class="d-flex align-items-center search-bar me-3">
    <input type="text" name="search" class="form-control" placeholder="Search for user..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
    <button class="btn btn-outline-secondary ms-2" type="submit"><i class="fas fa-search"></i></button>
    <?php if (!empty($_GET['search'])): ?>
    <a href="users.php" class="btn btn-link text-danger ms-2">Clear</a>
<?php endif; ?>
</form>

                </div>
            
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-label">Total users</div>
                    <div class="stats-value"><?= number_format($total_users) ?></div>
                    <div class="trend-up">
                        <i class="fas fa-arrow-up"></i> +40% vs last month
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-label">New users</div>
                    <div class="stats-value"><?= number_format($new_users) ?></div>
                    <div class="trend-up">
                        <i class="fas fa-arrow-up"></i> +10% vs last month
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-label">Verified users</div>
                    <div class="stats-value"><?= number_format($active_users) ?></div>
                    <div class="trend-down">
                        <i class="fas fa-arrow-down"></i> -5% vs last month
                    </div>
                </div>
            </div>
        </div>

<div class="users-table p-4">
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email address</th>
                    <th>Department</th>
                    <th>Created date</th>
                    <th>User status</th>
                </tr>
            </thead>
        </table>
        <div class="table-body-wrapper">
            <table class="table">
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3">
<?php
$default_avatar = '../gcpulseeee/img/user-icon.png';
$profile_picture = $user['profile_picture'] ?? '';
$avatar_path = !empty($profile_picture) ? '../' . ltrim($profile_picture, '/') : '';
$avatar = (!empty($avatar_path) && file_exists($avatar_path)) ? $avatar_path : $default_avatar;
?>
<img src="<?= htmlspecialchars($avatar) ?>" alt="Profile" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                    </div>
                                    <div>
                                        <?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['department']) ?></td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                                <?php
$status_class = $user['is_verified'] ? 'status-active' : 'status-warned';
$status_text = $user['is_verified'] ? 'Verified' : 'Pending';
                                ?>
                                <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
