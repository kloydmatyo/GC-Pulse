<?php
session_start();
include("db.php");

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    } else {
        header("Location: login.php");
    }
    exit;
}

$user_id = $_SESSION['user_id'];

// 🟢 Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Unknown error'];

    $department = isset($_POST['department']) ? trim($_POST['department']) : '';

    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_picture']['type'];

        if (in_array($file_type, $allowed_types)) {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/profile_pictures/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $filename = $user_id . '_' . basename($_FILES['profile_picture']['name']);
            $upload_path = $upload_dir . $filename;
            $db_path = '/uploads/profile_pictures/' . $filename;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                if (!empty($department)) {
                    $stmt = $conn->prepare("UPDATE users SET profile_picture = ?, department = ? WHERE user_id = ?");
                    $stmt->bind_param("ssi", $db_path, $department, $user_id);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $db_path, $user_id);
                }

                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Profile updated successfully.', 'path' => $db_path];
                } else {
                    $response['message'] = 'Database update failed.';
                }
            } else {
                $response['message'] = 'Failed to move uploaded file.';
            }
        } else {
            $response['message'] = 'Invalid file type.';
        }
    } else {
        $response['message'] = 'No file uploaded or upload error.';
    }

    echo json_encode($response);
    exit;
}

// 🟢 Handle GET
$stmt = $conn->prepare("SELECT profile_picture, department FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$current_profile_pic = !empty($user['profile_picture']) ? $user['profile_picture'] : '../img/user-icon.png';
$current_department = $user['department'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Profile Picture</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
body {
    font-family: apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    background-color: #f5f7fa !important;
    margin: 0;
    padding: 0;
}

.login-wrapper {    
    display: flex;
    min-height: 100vh;
    background: linear-gradient(135deg, #074D34, #418C4C);
    padding: 2rem;
    justify-content: center;
    align-items: center;
}

.login-right {
    background: white;
    padding: 2.5rem;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
    max-width: 450px;
    width: 100%;
    text-align: center;
}

h2 {
    color: #2e4e33;
    font-size: 28px;
    margin: 30px 0 1.5rem;
}

input[type="file"] {
display: block;
    padding: 8px;
    border: 2px solid #ccc;
    border-radius: 6px;
    background-color: #f9f9f9;
    font-size: 16px;
    cursor: pointer;
    color: #333;
    width: 100%;
    max-width: 300px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);
    margin: 10px;
    position: relative;
    left: 40px;
}
input[type="file"]::file-selector-button {
        background-color: #355E3B;
    color: white;
    padding: 6px 12px;
    border: none;
    border-radius: 4px;
    font-weight: bold;
    cursor: pointer;
    transition: background-color 0.3s;
}

.form-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    margin-top: 1.5rem;
}

.form-buttons button,
.form-buttons .cancel {
    padding: 12px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 30px;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    width: 100%;
    transition: all 0.3s ease;
}

.form-buttons button {
    background: #2e4e33;
    color: white;
    border: none;
}

.form-buttons button:hover {
    background: #1B3B20;
}

.form-buttons .cancel {
    background: #f8f9fa;
    color: #2e4e33;
    border: 2px solid #2e4e33;
    display: flex;
    align-items: center;
    justify-content: center;
}

.form-buttons .cancel:hover {
    background: #e9ecef;
}

.error-msg {
    background: #ffebee;
    color: #c62828;
    padding: 12px;
    border-radius: 8px;
    margin-top: 1rem;
    font-size: 14px;
    display: none;
}

img#avatarPreview {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #ccc;
    margin-bottom: 1rem;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 30px;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
}

.modal h3 {
    margin-top: 0;
    font-size: 24px;
    color: #28a745;
}

.modal-button {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 18px;
    background-color: #28a745;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-weight: bold;
}

.modal .close {
    position: absolute;
    right: 20px;
    top: 10px;
    font-size: 24px;
    color: #aaa;
    cursor: pointer;
}


.edit-profile-btn {
    display: inline-block;
    background: #fff;
    color: #000;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    text-decoration: none;
    transition: background 0.3s ease;
    position: relative;
top: -500px;
    right: -140px;
}


.edit-profile-btn:hover {
    background: #1B3B20;
}

.change-password-btn {
    display: inline-block;
    background: #2e4e33;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 16px;
    text-decoration: none;
    transition: background 0.3s ease;
    position: relative;
top: -440px;
    right: 140px;
}


.change-password-btn:hover {
    background: #1B3B20;
}

/* Style the checkbox label */
label[for="editDepartmentCheckbox"],
label > input[type="checkbox"] {
  cursor: pointer;
  font-weight: 600;
  user-select: none;
  margin-right: 8px;
}

/* Checkbox itself */
#editDepartmentCheckbox {
  width: 18px;
  height: 18px;
  vertical-align: middle;
  cursor: pointer;
}

/* Style the department dropdown */
#department {
  padding: 8px 12px;
  border: 2px solid #ccc;
  border-radius: 6px;
  font-size: 16px;
  width: 100%;
  max-width: 350px;
  transition: border-color 0.3s ease;
}

/* Disabled dropdown styling */
#department:disabled {
  background-color: #f0f0f0;
  border-color: #aaa;
  color: #666;
  cursor: not-allowed;
}

    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-right">
        <h2>Update Profile Picture</h2>

        <!-- Avatar Preview -->
        <div style="text-align: center; margin-bottom: 20px;">
<img
    id="avatarPreview"
    src="<?= $current_profile_pic ?>"
    alt="Profile Preview"
    style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 2px solid #ccc;"
>

        </div>

        <!-- Upload Form -->
        <form id="profilePictureForm" enctype="multipart/form-data">
            <input type="file" name="profile_picture" id="profile_picture" accept="image/*" required>
<label>
  <input type="checkbox" id="editDepartmentCheckbox"> Update Department
</label>
<br>
<select name="department" id="department" disabled required>
  <option value="<?= htmlspecialchars($current_department) ?>" selected hidden>
    <?= $current_department ? $current_department : '-- Select Department --' ?>
  </option>
  <option value="College of Business and Accountancy (CBA)">College of Business and Accountancy (CBA)</option>
  <option value="College of Hospitality and Tourism Management (CHTM)">College of Hospitality and Tourism Management (CHTM)</option>
  <option value="College of Computer Studies (CCS)">College of Computer Studies (CCS)</option>
  <option value="College of Education and Allied Studies (CEAS)">College of Education and Allied Studies (CEAS)</option>
  <option value="College of Allied Health Studies (CAHS)">College of Allied Health Studies (CAHS)</option>
</select>



            <div class="form-buttons">
                <a href="index.php" class="cancel">Cancel</a>
                <button type="submit">Upload</button>
                
            </div>
                   <div style="width: 100%; text-align: center; margin-bottom: 1rem;">
            <a href="change_password.php" class="change-password-btn">Change Password</a>
            </div>
                   <div style="width: 100%; text-align: center; margin-bottom: 1rem;">
            <a href="update_profile.php" class="edit-profile-btn">Edit Profile</a>
        </div>


        </form>

        <div id="uploadStatus" class="error-msg" style="display:none;"></div>
    </div>
</div>



<div id="successModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Success <i class="fa-solid fa-party-horn"></i></h3>
        <p id="modalMessage">Profile updated successfully.</p>
        <a href="index.php" class="modal-button">Go to Home</a>
    </div>
</div>

<script>
function closeModal() {
    document.getElementById("successModal").style.display = "none";
}
</script>

<script>
document.getElementById('profilePictureForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const fileInput = document.getElementById('profile_picture');
    const statusDiv = document.getElementById('uploadStatus');
    const preview = document.getElementById('avatarPreview');

    statusDiv.style.display = "block";

    if (!fileInput.files.length) {
        statusDiv.textContent = "Please choose a file.";
        statusDiv.style.color = "red";
        return;
    }

    const formData = new FormData();
    formData.append('profile_picture', fileInput.files[0]);
    formData.append('department', document.getElementById('department').value);

    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log(data);
        if (data.status === 'success') {
            statusDiv.textContent = data.message;
            statusDiv.style.color = "green";
            preview.src = data.path + '?t=' + new Date().getTime();

            // ✅ Show success modal
            document.getElementById("modalMessage").textContent = data.message;
            document.getElementById("successModal").style.display = "block";
        } else {
            statusDiv.textContent = data.message;
            statusDiv.style.color = "red";
        }
    })
    .catch(err => {
        console.error('Upload failed:', err);
        statusDiv.textContent = "Upload failed.";
        statusDiv.style.color = "red";
    });
});

// Show live preview on file selection
document.getElementById('profile_picture').addEventListener('change', function () {
    const file = this.files[0];
    const preview = document.getElementById('avatarPreview');

    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
});

document.getElementById('editDepartmentCheckbox').addEventListener('change', function() {
  const departmentSelect = document.getElementById('department');
  departmentSelect.disabled = !this.checked;
  
  // Optional: clear selection if disabled
  if (!this.checked) {
    departmentSelect.selectedIndex = 0; // or set to current department option
  }
});

</script>

</body>
</html>
