<?php
include_once("session_check.php");
include("db.php");


// Fetch blocked dates
$blocked_stmt = $conn->prepare("SELECT * FROM blocked_dates");
$blocked_stmt->execute();
$blocked_result = $blocked_stmt->get_result();
$events = []; // Initialize events here

while ($row = $blocked_result->fetch_assoc()) {
    $events[] = [
        "title" => "Blocked: " . addslashes($row['reason']),
        "start" => $row['date'],
        "allDay" => true,
        "color" => "#e74c3c",
        "textColor" => "#fff",
        "display" => "background"
    ];
}

// Fetch appointments
$calendar_stmt = $conn->prepare("SELECT * FROM appointments WHERE appointment_date >= NOW() AND status = 'approved' ORDER BY appointment_date ASC");
$calendar_stmt->execute();
$calendar_result = $calendar_stmt->get_result();

while ($row = $calendar_result->fetch_assoc()) {
    $events[] = [
        "id" => $row['appointment_id'],
        "title" => addslashes($row['title']),
        "start" => $row['appointment_date'],
        "extendedProps" => [
            "description" => $row['description'],
            "duration" => $row['duration'],
            "status" => $row['status']
        ]
    ];
}

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM posts WHERE user_id = $user_id ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Analytics Dashboard</title>
    
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.css' rel='stylesheet' />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="body.css">
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="modal.css">
    <style>
        #postsChart {
            width: 400px !important;
            height: 400px !important;
                display: block;
    margin: 0 auto;
        }
        #engagementChart {

                            display: block;
    margin: 0 auto;
        }
        .modal {
  display: none; /* hidden by default */
  position: fixed; 
  z-index: 1000; 
  left: 0; top: 0; 
  width: 100%; height: 100%;
  overflow: auto; 
  background-color: rgba(0,0,0,0.5); /* translucent background */
}

.modal-content-calendar {
  background-color: #fff;
  margin: 10% auto;
  padding: 20px;
  width: 80%;
  max-width: 500px;
  border-radius: 4px;
  position: relative;
}
/* Modal container */
.modal {
  display: none; /* Hidden by default */
  position: fixed;
  z-index: 999;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0, 0, 0, 0.5); /* Black background with opacity */
}

/* Modal content */
.modal-content-calendar {
  background-color: #fff;
  margin: 10% auto;
  padding: 20px 30px;
  border-radius: 8px;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  position: relative;
}

/* Close button */
.close-btn {
  color: #aaa;
  font-size: 28px;
  font-weight: bold;
  position: absolute;
  top: 10px;
  right: 20px;
  cursor: pointer;
  transition: color 0.3s;
}

.close-btn:hover {
  color: #000;
}

/* Heading */
.modal-content-calendar h3 {
  margin-top: 0;
  margin-bottom: 20px;
  font-size: 22px;
  color: #333;
}

/* Inputs */
.modal-content-calendar input[type="date"],
.modal-content-calendar input[type="text"] {
  width: 100%;
  padding: 10px;
  margin: 8px 0 16px 0;
  border: 1px solid #ccc;
  border-radius: 4px;
  box-sizing: border-box;
box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
    position: relative;
    top: 20px;
}

/* Button */
.modal-content-calendar button {
  background-color: #074D34;
  color: white;
  padding: 10px 18px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  width: 100%;
  font-size: 16px;
}

.modal-content-calendar button:hover {
  background-color: #1B3B20;
}
#blockDateSubmitBtn {
  background-color: #074D34;
  color: white;
  padding: 10px 18px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  width: 100%;
  font-size: 16px;
  top: 160px; /* Adjust this value to move the button up or down */
  position: relative;
}

#blockDateSubmitBtn:hover {
  background-color: #1B3B20;
}
.modal-content-calendar {
    background-color: #fff;
    margin: 10% auto;
    padding: 15px;
    border-radius: 12px;
    max-width: 480px;
    min-height: 400px;
    position: relative;
    color: var(--text-color);
    animation: fadeIn 0.3s ease-in-out;
    text-align: center;
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
        <!-- Stats Overview Section -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
                <div class="stat-info">
                    <h3 id="totalPosts">0</h3>
                    <p>Total Posts</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-thumbs-up"></i></div>
                <div class="stat-info">
                    <h3 id="totalLikes">0</h3>
                    <p>Total Likes</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-comments"></i></div>
                <div class="stat-info">
                    <h3 id="totalComments">0</h3>
                    <p>Total Comments</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                <div class="stat-info">
                    <h3 id="engagementRate">0%</h3>
                    <p>Engagement Rate</p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
<!-- Charts Section -->
<div class="charts-container">
    <div class="chart-card">
        <div class="chart-header">
            <h3>Posts by Category</h3>
            <select class="time-filter">
                <option>Last 7 days</option>
                <option>Last 30 days</option>
                <option>Last 90 days</option>
            </select>
        </div>
        <canvas id="postsChart"></canvas>
    </div>

    <div class="chart-card" style="flex: 1 1 500px;">
        <div class="chart-header">
            <h3>Calendar</h3>
        </div>
        <!-- Example Button + Modal Trigger -->
        <button id="blockDateButton" onclick="openBlockDateModal()">Block a Date</button>
        <div id="dashboardCalendar"></div>
    </div>

    <div class="chart-card" id="engagementChartContainer">
        <div class="chart-header">
            <h3>Engagement Overview</h3>
        </div>
        <canvas id="engagementChart"></canvas>
    </div>
</div> <!-- ✅ This was missing -->


<!-- Calendar Event Modal -->
<div id="calendarModal" class="modal">
    <div class="modal-content-calendar">
        <span class="close-btn" onclick="closeCalendarModal()">&times;</span>
                          <div class="centered-icon-bell">
<i class="fa-solid fa-bell"></i>    </div>
        <h3 id="modalTitle">Appointment Details</h3>
        
        <p><strong></strong> <span id="modalDescription"></span></p>
        <p><strong></strong> <span id="modalDate"></span></p>

        <p><strong></strong> <span id="modalDuration"></span></p>
        <p><strong></strong> <span id="modalStatus"></span></p>
        <div class="modal-actions">
            <button id="calendarCloseBtn" onclick="closeCalendarModal()">Close</button>
        </div>
    </div>
</div>




<!-- Modal Structure -->
<div id="blockDateModal" class="modal">
  <div class="modal-content-calendar">
    <span class="close-btn" onclick="closeBlockDateModal()">&times;</span>
    <h3>Block a Date</h3>
    <input type="date" id="blockDate">
    <input type="text" id="blockReason" placeholder="Reason">
    <button id="blockDateSubmitBtn" onclick="submitBlockDate()">Submit</button>
  </div>
</div>

<script>
function openBlockDateModal() {
  document.getElementById("blockDateModal").style.display = "block";
}
function closeBlockDateModal() {
  document.getElementById("blockDateModal").style.display = "none";
}
function submitBlockDate() {
  const date = document.getElementById("blockDate").value;
  const reason = document.getElementById("blockReason").value;

  fetch('block_date.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ date, reason })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert("Date blocked successfully.");
      location.reload();
    } else {
      alert("Failed to block date.");
    }
  });
}
</script>


    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.js'></script>
    <script>
        fetch("analytics.php")
            .then(response => response.json())
            .then(data => {
                // Totals
                const totalPosts = Object.values(data.posts).reduce((a, b) => a + b, 0);
                const totalLikes = data.engagement.reduce((sum, post) => sum + post.likes, 0);
                const totalComments = data.engagement.reduce((sum, post) => sum + post.comments, 0);
                const totalEngagements = totalLikes + totalComments;
                const engagementRate = totalPosts > 0 ? ((totalEngagements / totalPosts) * 100).toFixed(1) : 0;

                document.getElementById('totalPosts').textContent = totalPosts;
                document.getElementById('totalLikes').textContent = totalLikes;
                document.getElementById('totalComments').textContent = totalComments;
                document.getElementById('engagementRate').textContent = engagementRate + '%';

                const chartColors = {
                    primary: '#6c5ce7',
                    secondary: '#00cec9',
                    tertiary: '#fd79a8',
                    background: ['#6c5ce7', '#00cec9', '#fd79a8', '#fdcb6e']
                };

                // Pie Chart: Posts by Category
                new Chart(document.getElementById("postsChart"), {
                    type: "pie",
                    data: {
                        labels: Object.keys(data.posts),
                        datasets: [{
                            data: Object.values(data.posts),
                            backgroundColor: chartColors.background
                        }]
                    }
                });

                // Bar Chart: Likes and Comments
                new Chart(document.getElementById("engagementChart"), {
                    type: "bar",
                    data: {
                        labels: data.engagement.map(e => e.title),
                        datasets: [
                            { label: "Likes", data: data.engagement.map(e => e.likes), backgroundColor: chartColors.primary },
                            { label: "Comments", data: data.engagement.map(e => e.comments), backgroundColor: chartColors.secondary }
                        ]
                    }
                });

            });
    </script>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('dashboardCalendar');

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 500,
        events: <?= json_encode($events) ?>,
        eventColor: '#4caf50',
        eventTextColor: '#fff',
        eventClick: function(info) {
            const event = info.event;

            document.getElementById("modalTitle").textContent = event.title;
            document.getElementById("modalDescription").textContent = event.extendedProps.description;
            document.getElementById("modalDate").textContent = new Date(event.start).toLocaleString();

            const duration = parseInt(event.extendedProps.duration);
            const hours = Math.floor(duration / 60);
            const minutes = duration % 60;
            document.getElementById("modalDuration").textContent =
                (hours ? `${hours} hour${hours > 1 ? 's' : ''} ` : '') +
                (minutes ? `${minutes} minute${minutes > 1 ? 's' : ''}` : '');

            document.getElementById("modalStatus").textContent = event.extendedProps.status;

            document.getElementById("calendarModal").style.display = "block";
        }
    });

    calendar.render();
});
function closeCalendarModal() {
    document.getElementById("calendarModal").style.display = "none";
}
</script>

</body>
</html>
