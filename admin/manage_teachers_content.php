<?php
require '../partials/dbconnect.php';
require 'check_admin.php'; 
require '../vendor/autoload.php';
use Dompdf\Dompdf;

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    die("Invalid access: School not identified.");
}

function custom_hash($password) {
    $salt = 'XyZ@2025!abc123';
    $rounds = 3;
    $result = $password;
    for ($r = 0; $r < $rounds; $r++) {
        $temp = '';
        for ($i = 0; $i < strlen($result); $i++) {
            $char = ord($result[$i]);
            $saltChar = ord($salt[$i % strlen($salt)]);
            $mix = ($char ^ $saltChar) + ($char << 1);
            $hex = dechex($mix);
            $temp .= $hex;
        }
        $base64 = base64_encode($temp);
        $result = substr($temp, 0, 16) . substr($base64, -16);
    }
    return strtoupper($result);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $full_name = $_POST['full_name'];
        $address = $_POST['address'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $username = $_POST['username'];
        $password = custom_hash($_POST['password']);

        // Insert into users table first
        $stmt2 = $conn->prepare("INSERT INTO users (school_id, username, type, password, email) VALUES (?, ?, 'teacher', ?, ?)");
        $stmt2->bind_param("isss", $school_id, $username, $password, $email);
        $stmt2->execute();

        $user_id = $conn->insert_id; // Get the inserted user ID

        // Now insert into teachers table with user_id
        $stmt1 = $conn->prepare("INSERT INTO teachers (user_id, full_name, address, phone, email, username, password, school_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt1->bind_param("issssssi", $user_id, $full_name, $address, $phone, $email, $username, $password, $school_id);
        $stmt1->execute();
    }

    if ($action === 'edit') {
        $teacher_id = $_POST['teacher_id'];

        // Verify teacher belongs to this school
        $check_stmt = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND school_id = ?");
        $check_stmt->bind_param("ii", $teacher_id, $school_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows === 0) {
            die("Unauthorized operation.");
        }

        $stmt = $conn->prepare("UPDATE teachers SET full_name=?, address=?, phone=?, email=? WHERE id=? AND school_id=?");
        $stmt->bind_param("sssiii", $_POST['full_name'], $_POST['address'], $_POST['phone'], $_POST['email'], $teacher_id, $school_id);
        $stmt->execute();

        $stmt2 = $conn->prepare("UPDATE users SET email=? WHERE username=(SELECT username FROM teachers WHERE id=?)");
        $stmt2->bind_param("si", $_POST['email'], $teacher_id);
        $stmt2->execute();
    }

    if ($action === 'delete') {
        $teacher_id = $_POST['teacher_id'];

        // Verify teacher belongs to this school before deleting
        $check_stmt = $conn->prepare("SELECT username FROM teachers WHERE id = ? AND school_id = ?");
        $check_stmt->bind_param("ii", $teacher_id, $school_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        if ($result->num_rows === 0) {
            die("Unauthorized operation.");
        }
        $username = $result->fetch_assoc()['username'];

        $del_user_stmt = $conn->prepare("DELETE FROM users WHERE username = ? AND type = 'teacher'");
        $del_user_stmt->bind_param("s", $username);
        $del_user_stmt->execute();

        $del_teacher_stmt = $conn->prepare("DELETE FROM teachers WHERE id = ? AND school_id = ?");
        $del_teacher_stmt->bind_param("ii", $teacher_id, $school_id);
        $del_teacher_stmt->execute();
    }

    header("Location: manage_teachers.php");
    exit;
}

// Fetch teachers only for this school
$stmt = $conn->prepare("SELECT * FROM teachers WHERE school_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$teachers = $stmt->get_result();

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ob_start();
    echo "<h2 style='text-align:center;'>Teacher List</h2><table border='1' cellpadding='8' cellspacing='0' style='width:100%; font-size:14px;'>";
    echo "<thead><tr><th>Full Name</th><th>Address</th><th>Phone</th><th>Email</th><th>Username</th></tr></thead><tbody>";
    foreach ($teachers as $row) {
        echo "<tr>
                <td>".htmlspecialchars($row['full_name'])."</td>
                <td>".htmlspecialchars($row['address'])."</td>
                <td>".htmlspecialchars($row['phone'])."</td>
                <td>".htmlspecialchars($row['email'])."</td>
                <td>".htmlspecialchars($row['username'])."</td>
              </tr>";
    }
    echo "</tbody></table>";
    $html = ob_get_clean();

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    if (ob_get_length()) ob_end_clean();

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="teachers.pdf"');
    echo $dompdf->output();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Teachers</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .error-message {
      color: #ef4444;
      font-size: 0.75rem;
      margin-top: 0.25rem;
      display: none;
    }
    .input-error {
      border-color: #ef4444 !important;
      background-color: #fee2e2 !important;
    }
    .input-success {
      border-color: #10b981 !important;
    }
  </style>
</head>
<body class="bg-gradient-to-r from-blue-100 to-purple-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold text-gray-800">👩‍🏫 Manage Teachers</h1>
      <div>
        <a href="?export=pdf" target="_blank" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">📄 Export PDF</a>
        <button onclick="openAddModal()" class="ml-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Teacher</button>
      </div>
    </div>

    <div class="mb-4">
      <input type="text" id="searchInput" onkeyup="filterTable('searchInput','teacherTable')" placeholder="Search by name..."
        class="w-full md:w-64 px-4 py-2 rounded border border-gray-300 shadow-sm">
    </div>

    <table id= "teacherTable" class="min-w-full bg-white border text-sm">
      <thead class="bg-gray-100 text-gray-600 uppercase">
        <tr>
          <th class="py-3 px-6 text-left">Full Name</th>
          <th class="py-3 px-6 text-left">Address</th>
          <th class="py-3 px-6 text-left">Phone</th>
          <th class="py-3 px-6 text-left">Email</th>
          <th class="py-3 px-6 text-left">Username</th>
          <th class="py-3 px-6 text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $teachers->fetch_assoc()): ?>
          <tr class="border-t hover:bg-gray-50">
            <td class="py-3 px-6"><?= htmlspecialchars($row['full_name']) ?></td>
            <td class="py-3 px-6"><?= htmlspecialchars($row['address']) ?></td>
            <td class="py-3 px-6"><?= htmlspecialchars($row['phone']) ?></td>
            <td class="py-3 px-6"><?= htmlspecialchars($row['email']) ?></td>
            <td class="py-3 px-6"><?= htmlspecialchars($row['username']) ?></td>
            <td class="py-3 px-6 text-center space-x-2">
              <button onclick='openEditModal(<?= json_encode($row) ?>)' class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">Edit</button>
              <button onclick='openDeleteModal(<?= $row["id"] ?>)' class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">Delete</button>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Add/Edit Modal -->
  <div id="teacherModal" onclick="closeModal(event)" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div id="modalBox" class="animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-xl transition duration-300 ease-in-out
      filter hover:brightness-110" onclick="event.stopPropagation();">
      <h2 id="modalTitle" class="text-2xl font-bold text-white mb-6 text-center">➕ Add Teacher</h2>
      <form method="POST" class="space-y-4" id="teacherForm">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="teacher_id" id="teacher_id">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-white font-medium mb-1">Full Name</label>
            <input type="text" name="full_name" id="full_name" required 
                   class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800"
                   onkeyup="validateFullName(this)" />
            <div id="full_name_error" class="error-message">Full name must be 3-50 characters and contain only letters and spaces</div>
          </div>
          <div>
            <label class="block text-white font-medium mb-1">Address</label>
            <input type="text" name="address" id="address" required 
                   class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800"
                   onkeyup="validateAddress(this)" />
            <div id="address_error" class="error-message">Address must be 5-100 characters</div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-white font-medium mb-1">Phone</label>
            <input type="text" name="phone" id="phone" required 
                   class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800"
                   onkeyup="validatePhone(this)" />
            <div id="phone_error" class="error-message">Phone must be 10-15 digits and valid</div>
          </div>
          <div>
            <label class="block text-white font-medium mb-1">Email</label>
            <input type="email" name="email" id="email" required 
                   class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800"
                   onkeyup="validateEmail(this)" />
            <div id="email_error" class="error-message">Please enter a valid email address</div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="authFields">
          <div>
            <label class="block text-white font-medium mb-1">Username</label>
            <input type="text" name="username" id="username" 
                   class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800"
                   onkeyup="validateUsername(this)" />
            <div id="username_error" class="error-message">Username must be 4-20 characters (letters, numbers, _)</div>
          </div>
          <div>
            <label class="block text-white font-medium mb-1">Password</label>
            <input type="password" name="password" id="password" 
                   class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800"
                   onkeyup="validatePassword(this)" />
            <div id="password_error" class="error-message">Password must be 8-20 characters with at least one uppercase, one lowercase, and one number</div>
          </div>
        </div>

        <div class="flex justify-end space-x-3 mt-4">
          <button type="button" onclick="closeAllModals()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 font-semibold">Cancel</button>
          <button type="submit" id="submitBtn" class="px-6 py-2 rounded bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:scale-105 hover:shadow-xl font-semibold">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" onclick="closeModal(event)" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-md text-center transition duration-300 ease-in-out
      hover:ring-4 hover:ring-red-400 hover:ring-offset-2
      hover:shadow-[0_0_30px_rgba(220,38,38,0.6)] filter hover:brightness-110" onclick="event.stopPropagation();">
      <form method="POST" id="deleteForm">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="teacher_id" id="delete_id">
        <h3 class="text-xl font-bold text-white mb-4">⚠️ Confirm Deletion</h3>
        <p class="text-white mb-6">Are you sure you want to delete this teacher?</p>
        <div class="flex justify-center space-x-4">
          <button type="button" onclick="closeAllModals()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 font-semibold">Cancel</button>
          <button type="submit" class="px-6 py-2 rounded bg-gradient-to-r from-red-600 to-pink-600 text-white hover:scale-105 hover:shadow-xl font-semibold">Delete</button>
        </div>
      </form>
    </div>
  </div>

<script>
  // Validation patterns
  const patterns = {
    full_name: /^[a-zA-Z\s]{3,50}$/,
    address: /^.{5,100}$/,
    phone: /^[\d\s\-+]{10,15}$/,
    email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    username: /^[a-zA-Z0-9_]{4,20}$/,
    password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,20}$/
  };

  // Validation messages
  const validationMessages = {
    full_name: "Full name must be 3-50 characters and contain only letters and spaces",
    address: "Address must be 5-100 characters",
    phone: "Phone must be 10-15 digits and valid",
    email: "Please enter a valid email address",
    username: "Username must be 4-20 characters (letters, numbers, _)",
    password: "Password must be 8-20 characters with at least one uppercase, one lowercase, and one number"
  };

  // Validate field based on pattern
  function validateField(field, pattern) {
    const value = field.value.trim();
    const errorElement = document.getElementById(`${field.id}_error`);
    
    if (value === '') {
      field.classList.remove('input-success');
      field.classList.remove('input-error');
      errorElement.style.display = 'none';
      return false;
    }
    
    if (pattern.test(value)) {
      field.classList.add('input-success');
      field.classList.remove('input-error');
      errorElement.style.display = 'none';
      return true;
    } else {
      field.classList.add('input-error');
      field.classList.remove('input-success');
      errorElement.textContent = validationMessages[field.id];
      errorElement.style.display = 'block';
      return false;
    }
  }

  // Individual validation functions for each field
  function validateFullName(field) {
    return validateField(field, patterns.full_name);
  }

  function validateAddress(field) {
    return validateField(field, patterns.address);
  }

  function validatePhone(field) {
    return validateField(field, patterns.phone);
  }

  function validateEmail(field) {
    return validateField(field, patterns.email);
  }

  function validateUsername(field) {
    return validateField(field, patterns.username);
  }

  function validatePassword(field) {
    return validateField(field, patterns.password);
  }

  // Form validation before submission
  function validateForm() {
    let isValid = true;
    
    // Check all required fields based on form action
    const formAction = document.getElementById('formAction').value;
    
    // Always validate these fields
    isValid = validateFullName(document.getElementById('full_name')) && isValid;
    isValid = validateAddress(document.getElementById('address')) && isValid;
    isValid = validatePhone(document.getElementById('phone')) && isValid;
    isValid = validateEmail(document.getElementById('email')) && isValid;
    
    // Only validate username/password for add action
    if (formAction === 'add') {
      isValid = validateUsername(document.getElementById('username')) && isValid;
      isValid = validatePassword(document.getElementById('password')) && isValid;
    }
    
    return isValid;
  }

  // Attach form validation to submit event
  document.getElementById('teacherForm').addEventListener('submit', function(e) {
    if (!validateForm()) {
      e.preventDefault();
      // Highlight all invalid fields
      document.querySelectorAll('input').forEach(input => {
        if (input.value.trim() === '' && input.required) {
          input.classList.add('input-error');
          const errorElement = document.getElementById(`${input.id}_error`);
          errorElement.textContent = 'This field is required';
          errorElement.style.display = 'block';
        }
      });
    }
  });

  function closeModal(e) {
    if (e.target.id === "teacherModal" || e.target.id === "deleteModal") {
      e.target.classList.add("hidden");
    }
  }

  function closeAllModals() {
    document.getElementById("teacherModal").classList.add("hidden");
    document.getElementById("deleteModal").classList.add("hidden");
  }

  function openAddModal() {
    document.getElementById("modalTitle").innerText = "➕ Add Teacher";
    document.getElementById("formAction").value = "add";
    document.getElementById("teacher_id").value = "";
    document.getElementById("full_name").value = "";
    document.getElementById("address").value = "";
    document.getElementById("phone").value = "";
    document.getElementById("email").value = "";
    document.getElementById("username").value = "";
    document.getElementById("password").value = "";
    document.getElementById("authFields").classList.remove("hidden");
    
    // Reset validation states
    document.querySelectorAll('.input-error, .input-success').forEach(el => {
      el.classList.remove('input-error', 'input-success');
    });
    document.querySelectorAll('.error-message').forEach(el => {
      el.style.display = 'none';
    });
    
    const modalBox = document.getElementById("modalBox");
    modalBox.className = modalBox.className.replace(/hover:ring-\w+-400.*? /, "");
    modalBox.classList.add("hover:ring-blue-400", "hover:shadow-[0_0_30px_rgba(59,130,246,0.6)]");
    document.getElementById("teacherModal").classList.remove("hidden");
  }

  function openEditModal(data) {
    document.getElementById("modalTitle").innerText = "✏️ Edit Teacher";
    document.getElementById("formAction").value = "edit";
    document.getElementById("teacher_id").value = data.id;
    document.getElementById("full_name").value = data.full_name;
    document.getElementById("address").value = data.address;
    document.getElementById("phone").value = data.phone;
    document.getElementById("email").value = data.email;
    document.getElementById("authFields").classList.add("hidden");
    
    // Reset validation states
    document.querySelectorAll('.input-error, .input-success').forEach(el => {
      el.classList.remove('input-error', 'input-success');
    });
    document.querySelectorAll('.error-message').forEach(el => {
      el.style.display = 'none';
    });
    
    const modalBox = document.getElementById("modalBox");
    modalBox.className = modalBox.className.replace(/hover:ring-\w+-400.*? /, "");
    modalBox.classList.add("hover:ring-green-400", "hover:shadow-[0_0_30px_rgba(34,197,94,0.6)]");
    document.getElementById("teacherModal").classList.remove("hidden");
  }

  function openDeleteModal(id) {
    document.getElementById("delete_id").value = id;
    document.getElementById("deleteModal").classList.remove("hidden");
  }
</script>
<script src="../partials/algorithms.js"></script>
</body>
</html>