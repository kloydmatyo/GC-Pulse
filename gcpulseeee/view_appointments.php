<?php
include("session_check.php");
include("db.php");

$user_id = $_SESSION['user_id'];


// Use the correct column name
$sql = "SELECT firstname, lastname FROM users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($firstname, $lastname);
$stmt->fetch();
$stmt->close();
// Fetch user's appointments for both table and calendar
// All upcoming appointments for the list
$table_stmt = $conn->prepare("SELECT * FROM appointments WHERE appointment_date >= NOW() ORDER BY appointment_date ASC");
$table_stmt->execute();
$table_result = $table_stmt->get_result();

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


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="view_appointments.css">
    <link rel="stylesheet"  href="../gcpulseeee/body.css">
    <link rel="stylesheet" href="navbar.css">
    <link rel="stylesheet" href="modal.css">
    <link rel="stylesheet"  href="../gcpulseeee/notification.css">
    <link rel="stylesheet"  href="../gcpulseeee/index.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.css" rel="stylesheet" />
<style>
    .modal-backdrop {
  z-index: 1040 !important;
}

.modal {
  z-index: 1050 !important;
}
.upcoming-section .upcoming-header .view-all {
    background-color:rgb(222, 222, 222); /* White background color */
    color: #28a745; /* Green text color */
    border: 1px solid #28a745; /* Green border */
    padding: 8px 16px; /* Add padding for size */
    font-size: 14px; /* Adjust font size */
    cursor: pointer; /* Show pointer cursor on hover */
    border-radius: 5px; /* Rounded corners */
    transition: background-color 0.3s ease, transform 0.2s ease; /* Smooth transition effects */
}

.upcoming-section .upcoming-header .view-all:hover {
    background-color: #28a745; /* Green background when hovered */
    color: white; /* White text when hovered */
    border: 1px solid #218838; /* Darker green border when hovered */
    transform: scale(1.05); /* Slight scale effect on hover */
}

.upcoming-section .upcoming-header .view-all:focus {
    outline: none; /* Remove focus outline */
    box-shadow: 0 0 3px 2px rgba(40, 167, 69, 0.5); /* Add a glowing effect when focused */
}



</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar -4 py-2">
  <div class="container-fluid justify-content-between align-items-center">

    <!-- Left: Logo -->
    <div class="d-flex align-items-center">
      <h3 id="logo" class="mb-0" style="color: #fff;">GC Pulse</h3>
    </div>

    <!-- Center: Search bar -->
    <div class="mx-auto" style="max-width: 400px; width: 100%;">
      <form class="search-form-with-icon" method="GET" action="index.php">
        <div class="input-group">
          <span class="input-group-text bg-white border-end-0">
            <i class="fas fa-search text-muted"></i>
          </span>
          <label for="search" class="visually-hidden">Search</label>
          <input type="text" id="search" name="search" class="form-control border-start-0" placeholder="Search posts..." aria-label="Search">
        </div>
      </form>
    </div>

    <!-- Right Section -->
    <div class="d-flex align-items-center gap-3">
        <!-- Notification Icon -->
        <div class="position-relative">
                <a href="index.php" id="userIcon" class="text-white fs-4" style="margin-right: 10px; margin-top: 1px;">
    <i class="fa-solid fa-house text-white fs-4"></i> <!-- User icon -->
</a>
            <i class="fas fa-bell text-white fs-4" id="notifIcon" role="button"></i>
            <!-- Notification Panel -->
            <div class="dropdown-menu dropdown-menu-end p-3" id="notifPanel" style="min-width: 300px; max-height: 400px; overflow-y: auto;">

                <h6 class="dropdown-header text-center">Notifications</h6>
                <ul class="list-unstyled mb-0">
                    <?php
                    $notif_query = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
                    $notif_query->bind_param("i", $user_id);
                    $notif_query->execute();
                    $notif_result = $notif_query->get_result();

                    if ($notif_result->num_rows > 0):
                        while ($notif = $notif_result->fetch_assoc()):
                    ?>
<li class="mb-2 <?= $notif['is_read'] ?>">
    <a href="javascript:void(0);" onclick="openPostFromNotif(<?= $notif['post_id'] ?>, <?= $notif['notification_id'] ?>)" 
       class="text-decoration-none text-dark d-flex align-items-start">
        <i class="fas fa-bell me-2"></i>
        <div>
            <div><?= htmlspecialchars_decode($notif['message']) ?></div>
            <small class="text-muted"><?= date('M j, g:i A', strtotime($notif['created_at'])) ?></small>
            
        </div>
    </a>
</li>

                    <?php endwhile; else: ?>
                        <li><div class="text-muted">No new notifications.</div></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- User Dropdown -->
<div class="dropdown">
    <a href="#" id="userIcon" class="text-white fs-4 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-user"></i> <!-- User icon -->
    </a>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
    <a class="dropdown-item" href="profile.php">
        <img src="../gcpulseeee/img/user-icon.png" alt="Profile" style="width: 36px; height: 36px; border-radius: 50%;">
        <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>
    </a>
</li>
    <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
        <li><a class="dropdown-item" href="view_appointments.php">Appointments</a></li>
        <li><hr class="dropdown-divider"></li>
        <a class="dropdown-item" href="#" id="logoutBtn">Logout</a>
    </ul>
</div>
    </div>
</nav>

<h2 style="text-align: center;">Your Appointments</h2>


<div class="appointments-container">


<!-- Appointment Cards -->
<div class="appointments-list">
    <?php $table_result->data_seek(0); while ($row = $table_result->fetch_assoc()): ?>
        <div class="appointment-card" 
     data-id="<?= $row['appointment_id'] ?>"
     data-date="<?= date('Y-m-d\TH:i', strtotime($row['appointment_date'])) ?>"
     data-duration="<?= $row['duration'] ?>">
    <strong class="appointment-title"><?= htmlspecialchars($row['title']); ?></strong>
    <div class="appointment-description">
            <small><?= htmlspecialchars(ucfirst($row['description'])) ?></small>
        </div>
    <!-- Display Appointment Date -->
               <div class="appointment-date">
            <small> <?= date('F j, Y g:i A', strtotime($row['appointment_date'])) ?></small>
        </div>
        <!-- Display Duration -->
        <small>
            <?php 
            // Display duration in hours and minutes format
            $duration = $row['duration'];
            $hours = floor($duration / 60);
            $minutes = $duration % 60;
            
            // Format the duration string
            if ($hours > 0) {
                echo "{$hours} hour" . ($hours > 1 ? "s" : "") . " ";
            }
            if ($minutes > 0) {
                echo "{$minutes} minute" . ($minutes > 1 ? "s" : "");
            }
            ?>
        </small>

<?php
$status = strtolower($row['status']);
$status_class = '';

switch ($status) {
    case 'approved':
        $status_class = 'status-approved';
        break;
    case 'rejected':
        $status_class = 'status-rejected';
        break;
    case 'pending':
        $status_class = 'status-pending';
        break;
}
?>
<div class="appointment-status <?= $status_class ?>">
    <small>Status: <strong><?= ucfirst($status) ?></strong></small>
</div>

    <!-- Meatball button -->
    <?php if ($row['user_id'] == $_SESSION['user_id']): ?>
    <div class="meatball-container">
        <button class="meatball-btn" onclick="toggleMeatballMenu(this)">⋮</button>
        <div class="meatball-menu">
            <button onclick="editAppointment(<?= $row['appointment_id'] ?>)">Edit</button>
            <button onclick="cancelAppointment(<?= $row['appointment_id'] ?>)">Cancel</button>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endwhile; ?>
</div>

<!-- Cancel Confirmation Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-content-cancel">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <div class="centered-icon-cancel">
        <i class="fa-solid fa-cancel">‌</i>
        </div>
        <h3>Cancel Appointment</h3>
        <p>Are you sure you want to cancel this appointment?</p>
        <div class="modal-actions">
            <button id=cancelCancelBtn onclick="closeModal()">Cancel</button>
            <button id="confirmCancelBtn">Confirm</button>
            
        </div>
    </div>
</div>

<!-- Right Sidebar -->
<div class="appointments-sidebar">
    <div class="calendar-box">
        <div id="calendar"></div>
    </div>

    <div class="upcoming-section">
        <div class="upcoming-header">
            <span><strong>Upcoming Appointments</strong></span>
        </div>

        <?php $table_result->data_seek(0); while ($row = $table_result->fetch_assoc()): ?>
        <div class="upcoming-card">
            <strong><?php echo htmlspecialchars($row['description']); ?></strong>
            <div class="upcoming-sub">
                <?php
                    echo date('F j, Y, g:i A', strtotime($row['appointment_date'] . ' ' ));
                ?>
            </div>
        </div>
        <?php endwhile; ?>

    </div>
</div>

</div>

<!-- Edit Appointment Modal -->
<div id="editModal" class="modal">
    <div class="modal-content-edit">
        <span class="close-btn" onclick="closeEditModal()">&times;</span>
        <h3>Edit Appointment</h3>
        <form id="editForm" method="POST" action="edit_appointment.php">
            <input type="hidden" name="appointment_id" id="editAppointmentId">

            <label for="editDescription">Description:</label>
            <input type="text" name="description" id="editDescription" required><br><br>

            <label for="editDate">Date:</label>
            <input type="datetime-local" name="appointment_date" id="editDate" required><br><br>

            <label for="editDuration">Duration (minutes):</label>
            <input type="number" name="duration" id="editDuration" required><br><br>

            <div class="modal-actions">
                <button id="editCancelBtn"  type="button" onclick="closeEditModal()">Cancel</button>
                <button id="editSaveBtn" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
</div>

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
        <p><strong></strong> <span id="modalStatus"Style="color: #355E3B; font-size: 30px;"></span></p>
        <div class="modal-actions">
            <button id="calendarCloseBtn" onclick="closeCalendarModal()">Close</button>
        </div>
    </div>
</div>

<!-- Appointment Modal for Past Date Error -->
<div class="modal fade" id="pastDateModal" tabindex="-1" aria-labelledby="pastDateModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg-custom">
    <div class="modal-content modal-content-error" id="pastDateContent">
      <div class="modal-header">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="centered-icon">
        <i class="fa-solid fa-circle-exclamation"></i>
      </div>
      <h5 class="modal-title" id="pastDateModalLabel">Invalid Appointment Date</h5>
      <div class="modal-body">
        Please select a future date and time for the appointment.
      </div>
      <div class="modal-footer">
        <button type="button" class="request-button" onclick="handleRequestClick()">Request</button>
      </div>
    </div>
  </div>
</div>
<!-- Appointment Conflict Modal -->
<div id="conflictModal" class="modal" style="display:none;">
  <div class="modal-content" id="conflictContent">
    <span class="close" onclick="closeConflictModal()">&times;</span>
    <div class="centered-icon">
      <i class="fa-solid fa-circle-exclamation"></i>
    </div>
    <h2 class="conflict-heading">Unavailable Time Frame</h2>
    <p class="conflict-text">The time you've chosen for this appointment overlaps with another<br>entry in our schedule. Kindly select an alternative.</p>
    <div class="modal-footer">
      <button type="button" class="request-button" onclick="handleRequestClick()">Request</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.7/index.global.min.js"></script>
<script src="view_appointments.js"></script>
<script>
    const blockedDates = <?php echo json_encode(array_column($events, 'start')); ?>;
</script>
<script>
    const existingAppointments = <?= json_encode(array_map(function ($row) {
        return [
            'id' => $row['appointment_id'],
            'start' => $row['appointment_date'],
            'duration' => $row['duration'],
        ];
    }, iterator_to_array($table_result))) ?>;
</script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const editForm = document.getElementById("editForm");
    editForm.addEventListener("submit", function(e) {
        e.preventDefault();

        const dateInput = document.getElementById("editDate").value;
        const durationInput = parseInt(document.getElementById("editDuration").value, 10);
        const appointmentId = parseInt(document.getElementById("editAppointmentId").value, 10);

        const selectedStart = new Date(dateInput);
        const selectedEnd = new Date(selectedStart.getTime() + durationInput * 60000);

        const now = new Date();

        // Check for past date
        if (selectedStart < now) {
            const modalEl = new bootstrap.Modal(document.getElementById("pastDateModal"));
            modalEl.show();
            return;
        }

        // Check for blocked dates
        const selectedDate = dateInput.split('T')[0]; // Get just the date part
        const isBlocked = blockedDates.some(date => date.split('T')[0] === selectedDate);
        
        if (isBlocked) {
            document.getElementById("conflictModal").style.display = "block";
            return;
        }

        // Check for conflicts with existing appointments
        for (let appt of existingAppointments) {
            if (appt.id === appointmentId) continue; // skip current appointment

            const apptStart = new Date(appt.start);
            const apptEnd = new Date(apptStart.getTime() + appt.duration * 60000);

            const conflict = selectedStart < apptEnd && selectedEnd > apptStart;
            if (conflict) {
                document.getElementById("conflictModal").style.display = "block";
                return;
            }
        }

        // No conflicts - submit form
        editForm.submit();
    });
});
</script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('error') === 'past_date') {
        // Hide any other open modals
        document.querySelectorAll('.modal.show').forEach(modal => {
            const instance = bootstrap.Modal.getInstance(modal);
            if (instance) instance.hide();
        });

        const modalElement = document.getElementById('pastDateModal');
        if (modalElement) {
            let modal = bootstrap.Modal.getInstance(modalElement);
            if (!modal) {
                modal = new bootstrap.Modal(modalElement);
            }
            modalElement.classList.remove('fade'); // optional
            modal.show();

            // Clean URL (remove ?error=past_date)
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }
});

function handleRequestClick() {
  console.log('✅ handleRequestClick called');

  // Close pastDateModal if open
  const pastDateModalEl = document.getElementById('pastDateModal');
  if (pastDateModalEl) {
      let pastDateModal = bootstrap.Modal.getInstance(pastDateModalEl);
      if (!pastDateModal) {
          pastDateModal = new bootstrap.Modal(pastDateModalEl);
      }
      pastDateModal.hide();
  }

  // Also close conflict modal (in case)
  closeConflictModal();

  // Open the edit modal
  openeditModal();
}

</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  const conflictParam = urlParams.has('conflict') || getQueryParam('appointment_conflict') === '1';

  const conflictModal = document.getElementById('conflictModal');

  if (conflictParam && conflictModal) {
    conflictModal.style.display = 'block';

    // Clean URL
    urlParams.delete('conflict');
    urlParams.delete('appointment_conflict');
    const cleanUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
    window.history.replaceState({}, document.title, cleanUrl);

    // Close modal on X click
    const span = document.querySelector('#conflictModal .close');
    span.onclick = closeConflictModal;

    // Close on outside click
    window.onclick = function (event) {
      if (event.target === conflictModal) {
        closeConflictModal();
      }
    };
  }
});

function closeConflictModal() {
  const conflictModal = document.getElementById('conflictModal');
  if (conflictModal) {
    conflictModal.style.display = 'none';
  }
  // Clean URL params
  const url = new URL(window.location);
  url.searchParams.delete('conflict');
  url.searchParams.delete('appointment_conflict');
  window.history.replaceState({}, document.title, url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''));
}

</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var calendarEl = document.getElementById('calendar');

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
<script>
    function toggleMeatballMenu(button) {
    // Close any open menu
    document.querySelectorAll('.meatball-menu').forEach(menu => {
        if (menu !== button.nextElementSibling) menu.style.display = 'none';
    });

    const menu = button.nextElementSibling;
    menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
}

window.onclick = function(event) {
    if (!event.target.matches('.meatball-btn')) {
        document.querySelectorAll('.meatball-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    }
}

function editAppointment(id) {
    // Redirect or open a modal
    window.location.href = `edit_appointment.php?id=${id}`;
}

function cancelAppointment(id) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
        // Redirect or send AJAX request
        window.location.href = `cancel_appointment.php?id=${id}`;
    }
}

let cancelTargetId = null;

function cancelAppointment(id) {
    cancelTargetId = id;
    document.getElementById("cancelModal").style.display = "block";
}

function closeModal() {
    document.getElementById("cancelModal").style.display = "none";
    cancelTargetId = null;
}

document.getElementById("confirmCancelBtn").addEventListener("click", function () {
    if (cancelTargetId) {
        window.location.href = `cancel_appointment.php?id=${cancelTargetId}`;
    }
});

let editTargetId = null;

function editAppointment(id) {
    // You can fetch details from the DOM if available or via AJAX if needed
    const card = document.querySelector(`.appointment-card[data-id="${id}"]`);
    if (!card) return;

    const description = card.querySelector('.appointment-description').textContent;
    const date = card.getAttribute('data-date');
    const duration = card.getAttribute('data-duration');

    document.getElementById("editAppointmentId").value = id;
    document.getElementById("editDescription").value = description;
    document.getElementById("editDate").value = date;
    document.getElementById("editDuration").value = duration;

    document.getElementById("editModal").style.display = "block";
}

function closeEditModal() {
    document.getElementById("editModal").style.display = "none";
}

</script>
</body>
</html>