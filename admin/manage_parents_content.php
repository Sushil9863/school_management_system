<?php
require '../partials/dbconnect.php';
require '../partials/algorithms.php';
require 'check_admin.php'; 

require '../vendor/autoload.php';
use Dompdf\Dompdf;

$school_id = $_SESSION['school_id'] ?? 0;

// Initialize validation variables
$errors = [];
$form_data = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $form_data = $_POST;

    // Common validation for both add and edit
    $full_name = trim($_POST['full_name'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');

    // Validate full name
    if (empty($full_name)) {
        $errors['full_name'] = "Full name is required";
    } elseif (strlen($full_name) > 100) {
        $errors['full_name'] = "Name must be less than 100 characters";
    } elseif (!preg_match('/^[a-zA-Z\s\.\-]+$/', $full_name)) {
        $errors['full_name'] = "Only letters, spaces, dots and hyphens allowed";
    }

    // Validate contact - must start with 97 or 98
    if (empty($contact)) {
        $errors['contact'] = "Contact number is required";
    } elseif (!preg_match('/^(97|98)[0-9]{8}$/', $contact)) {
        $errors['contact'] = "Contact must start with 97 or 98 and be 10 digits total";
    }

    // Validate address - must start with alphabet
    if (empty($address)) {
        $errors['address'] = "Address is required";
    } elseif (strlen($address) > 255) {
        $errors['address'] = "Address must be less than 255 characters";
    } elseif (!preg_match('/^[a-zA-Z]/', $address)) {
        $errors['address'] = "Address must start with a letter";
    }

    // Validate email
    if (empty($email)) {
        $errors['email'] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    } elseif (strlen($email) > 100) {
        $errors['email'] = "Email must be less than 100 characters";
    }

    // Validate username
    if (empty($username)) {
        $errors['username'] = "Username is required";
    } elseif (strlen($username) < 4 || strlen($username) > 20) {
        $errors['username'] = "Username must be 4-20 characters";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors['username'] = "Only letters, numbers and underscore allowed";
    }

    if ($action === 'add') {
        // Additional validation for add action
        $password = $_POST['password'] ?? '';
        
        if (empty($password)) {
            $errors['password'] = "Password is required";
        } elseif (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters";
        }

        // Check if username already exists
        $check = $conn->prepare("SELECT id FROM parents WHERE username=? AND school_id=?");
        $check->bind_param("si", $username, $school_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors['username'] = "Username already exists";
        }

        if (empty($errors)) {
            try {
                $password_hash = custom_password_hash($password);
                
                // Start transaction
                $conn->begin_transaction();
                
                // FIRST: Insert into users table
                $stmt2 = $conn->prepare("INSERT INTO users (username, type, password, email, school_id) VALUES (?, 'parent', ?, ?, ?)");
                $stmt2->bind_param("sssi", $username, $password_hash, $email, $school_id);
                
                if (!$stmt2->execute()) {
                    throw new Exception("User insert failed: " . $stmt2->error);
                }
                
                // Get the inserted user ID
                $user_id = $conn->insert_id;
                
                // THEN: Insert into parents table with the user_id
                $stmt1 = $conn->prepare("INSERT INTO parents (user_id, full_name, contact, address, email, username, password, school_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt1->bind_param("issssssi", $user_id, $full_name, $contact, $address, $email, $username, $password_hash, $school_id);
                
                if (!$stmt1->execute()) {
                    throw new Exception("Parent insert failed: " . $stmt1->error);
                }
                
                // Commit transaction
                $conn->commit();
                
                $success_message = "Parent added successfully";
                $form_data = []; // Clear form data
                
                // Redirect to avoid form resubmission
                header("Location: manage_parents.php?success=1");
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $errors['database'] = "Error adding parent. Please try again.";
                error_log("Database error: " . $e->getMessage());
            }
        }
    }

    if ($action === 'edit') {
        $id = $_POST['parent_id'] ?? 0;
        
        // Check if username is being changed to one that already exists
        $check = $conn->prepare("SELECT id FROM parents WHERE username=? AND school_id=? AND id!=?");
        $check->bind_param("sii", $username, $school_id, $id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors['username'] = "Username already exists";
        }

        if (empty($errors)) {
            // Get the user_id for this parent
            $get_user_id = $conn->prepare("SELECT user_id FROM parents WHERE id=? AND school_id=?");
            $get_user_id->bind_param("ii", $id, $school_id);
            $get_user_id->execute();
            $user_id_result = $get_user_id->get_result()->fetch_assoc();
            $user_id = $user_id_result['user_id'] ?? 0;
            
            // Update parents table
            $stmt = $conn->prepare("UPDATE parents SET full_name=?, contact=?, address=?, email=?, username=? WHERE id=? AND school_id=?");
            $stmt->bind_param("sssssii", $full_name, $contact, $address, $email, $username, $id, $school_id);
            $stmt->execute();

            // Update users table using the user_id
            $stmt2 = $conn->prepare("UPDATE users SET email=?, username=? WHERE id=? AND school_id=?");
            $stmt2->bind_param("ssii", $email, $username, $user_id, $school_id);
            $stmt2->execute();

            $success_message = "Parent updated successfully";
        }
    }

    if ($action === 'delete') {
        $id = $_POST['parent_id'] ?? 0;
        
        // Get the user_id for this parent
        $get_ids = $conn->prepare("SELECT user_id, username FROM parents WHERE id=? AND school_id=?");
        $get_ids->bind_param("ii", $id, $school_id);
        $get_ids->execute();
        $ids_result = $get_ids->get_result()->fetch_assoc();
        $user_id = $ids_result['user_id'] ?? 0;
        $username = $ids_result['username'] ?? '';

        if ($user_id) {
            // Delete from users table using user_id
            $stmtDelUser = $conn->prepare("DELETE FROM users WHERE id=? AND type='parent' AND school_id=?");
            $stmtDelUser->bind_param("ii", $user_id, $school_id);
            $stmtDelUser->execute();

            // Delete from parents table
            $stmtDelParent = $conn->prepare("DELETE FROM parents WHERE id=? AND school_id=?");
            $stmtDelParent->bind_param("ii", $id, $school_id);
            $stmtDelParent->execute();

            $success_message = "Parent deleted successfully";
        }
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    $stmt = $conn->prepare("SELECT * FROM parents WHERE school_id=? ORDER BY id DESC");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $res = $stmt->get_result();

    ob_start();
    echo "<h2 style='text-align:center;'>Parent List</h2><table border='1' cellpadding='8' cellspacing='0' style='width:100%; font-size:14px;'>";
    echo "<thead><tr><th>Full Name</th><th>Contact</th><th>Address</th><th>Email</th><th>Username</th></tr></thead><tbody>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr>
          <td>{$row['full_name']}</td>
          <td>{$row['contact']}</td>
          <td>{$row['address']}</td>
          <td>{$row['email']}</td>
          <td>{$row['username']}</td>
        </tr>";
    }
    echo "</tbody></table>";
    $html = ob_get_clean();

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    if (ob_get_length()) ob_end_clean();
    header("Content-Type: application/pdf");
    header("Content-Disposition: inline; filename=parents.pdf");
    echo $dompdf->output();
    exit;
}

$stmt = $conn->prepare("SELECT * FROM parents WHERE school_id=? ORDER BY id DESC");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$parents = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Parents</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
  <style>
    .error-border {
      border-color: #ef4444 !important;
    }
    .error-text {
      color: #ef4444;
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }
    .help-text {
      color: #6b7280;
      font-size: 0.75rem;
      margin-top: 0.25rem;
    }
  </style>
</head>
<body class="bg-gradient-to-r from-green-100 to-blue-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
    <?php if (!empty($success_message)): ?>
      <div id="successAlert" class="relative mb-6 px-4 py-3 rounded bg-green-100 text-green-700 flex items-center justify-between">
        <span>✅ <?= htmlspecialchars($success_message) ?></span>
        <button onclick="document.getElementById('successAlert').remove()" class="ml-4 text-green-700 font-bold hover:text-red-600">
          ✕
        </button>
      </div>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">👨‍👩‍👧 Manage Parents</h1>
      <div>
        <a href="?export=pdf" target="_blank" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">📄 Export PDF</a>
        <button onclick="openAddModal()" class="ml-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Parent</button>
      </div>
    </div>

    <div class="mb-4">
      <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search by name..."
        class="px-4 py-2 rounded border border-gray-300 shadow-sm w-full md:w-64">
    </div>

    <table class="min-w-full bg-white border text-sm">
      <thead class="bg-gray-100 text-gray-600 uppercase">
        <tr>
          <th class="py-3 px-6 text-left">Full Name</th>
          <th class="py-3 px-6 text-left">Contact</th>
          <th class="py-3 px-6 text-left">Address</th>
          <th class="py-3 px-6 text-left">Email</th>
          <th class="py-3 px-6 text-left">Username</th>
          <th class="py-3 px-6 text-center">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $parents->fetch_assoc()): ?>
        <tr class="border-t hover:bg-gray-50">
          <td class="py-3 px-6"><?= htmlspecialchars($row['full_name']) ?></td>
          <td class="py-3 px-6"><?= htmlspecialchars($row['contact']) ?></td>
          <td class="py-3 px-6"><?= htmlspecialchars($row['address']) ?></td>
          <td class="py-3 px-6"><?= htmlspecialchars($row['email']) ?></td>
          <td class="py-3 px-6"><?= htmlspecialchars($row['username']) ?></td>
          <td class="py-3 px-6 text-center space-x-2">
            <button onclick='openEditModal(<?= json_encode($row) ?>)' class="bg-yellow-400 text-white px-3 py-1 rounded text-sm hover:bg-yellow-500">Edit</button>
            <button onclick='openDeleteModal(<?= $row['id'] ?>)' class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Delete</button>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Add/Edit Modal -->
  <div id="parentModal" onclick="closeModal(event)" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div id="modalBox" class="animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-2xl transition duration-300 ease-in-out hover:ring-offset-2 hover:ring-4 bg-white/30 backdrop-blur-md" onclick="event.stopPropagation();">
      <h2 id="modalTitle" class="text-2xl font-bold text-white mb-6 text-center">➕ Add Parent</h2>
      <form method="POST" id="parentForm" class="space-y-4">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="parent_id" id="parent_id">
        
        <?php if (isset($errors['database'])): ?>
          <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
            <?= htmlspecialchars($errors['database']) ?>
          </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-white">
          <!-- Full Name -->
          <div>
            <label class="block font-medium">Full Name</label>
            <input type="text" name="full_name" id="full_name" required 
                   class="w-full px-4 py-2 border rounded bg-white/80 text-black <?= isset($errors['full_name']) ? 'error-border' : '' ?>" 
                   value="<?= htmlspecialchars($form_data['full_name'] ?? '') ?>" />
            <?php if (isset($errors['full_name'])): ?>
              <div class="error-text"><?= htmlspecialchars($errors['full_name']) ?></div>
            <?php endif; ?>
            <div class="help-text">Letters, spaces, dots and hyphens only (max 100 chars)</div>
          </div>
          
          <!-- Contact -->
          <div>
            <label class="block font-medium">Contact</label>
            <input type="text" name="contact" id="contact" required 
                   class="w-full px-4 py-2 border rounded bg-white/80 text-black <?= isset($errors['contact']) ? 'error-border' : '' ?>" 
                   value="<?= htmlspecialchars($form_data['contact'] ?? '') ?>" 
                   maxlength="10" pattern="(97|98)[0-9]{8}" />
            <?php if (isset($errors['contact'])): ?>
              <div class="error-text"><?= htmlspecialchars($errors['contact']) ?></div>
            <?php endif; ?>
            <div class="help-text">Must start with 97 or 98 and be 10 digits total</div>
          </div>
          
          <!-- Address -->
          <div>
            <label class="block font-medium">Address</label>
            <input type="text" name="address" id="address" required 
                   class="w-full px-4 py-2 border rounded bg-white/80 text-black <?= isset($errors['address']) ? 'error-border' : '' ?>" 
                   value="<?= htmlspecialchars($form_data['address'] ?? '') ?>" />
            <?php if (isset($errors['address'])): ?>
              <div class="error-text"><?= htmlspecialchars($errors['address']) ?></div>
            <?php endif; ?>
            <div class="help-text">Must start with a letter (max 255 chars)</div>
          </div>
          
          <!-- Email -->
          <div>
            <label class="block font-medium">Email</label>
            <input type="email" name="email" id="email" required 
                   class="w-full px-4 py-2 border rounded bg-white/80 text-black <?= isset($errors['email']) ? 'error-border' : '' ?>" 
                   value="<?= htmlspecialchars($form_data['email'] ?? '') ?>" />
            <?php if (isset($errors['email'])): ?>
              <div class="error-text"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
            <div class="help-text">Valid email address (max 100 chars)</div>
          </div>
          
          <!-- Username -->
          <div>
            <label class="block font-medium">Username</label>
            <input type="text" name="username" id="username" required 
                   class="w-full px-4 py-2 border rounded bg-white/80 text-black <?= isset($errors['username']) ? 'error-border' : '' ?>" 
                   value="<?= htmlspecialchars($form_data['username'] ?? '') ?>" />
            <?php if (isset($errors['username'])): ?>
              <div class="error-text"><?= htmlspecialchars($errors['username']) ?></div>
            <?php endif; ?>
            <div class="help-text">4-20 chars, letters, numbers, underscore</div>
          </div>
          
          <!-- Password (only shown for add) -->
          <div id="passwordDiv">
            <label class="block font-medium">Password</label>
            <input type="password" name="password" id="password" 
                   class="w-full px-4 py-2 border rounded bg-white/80 text-black <?= isset($errors['password']) ? 'error-border' : '' ?>" />
            <?php if (isset($errors['password'])): ?>
              <div class="error-text"><?= htmlspecialchars($errors['password']) ?></div>
            <?php endif; ?>
            <div class="help-text">Minimum 8 characters</div>
          </div>
        </div>
        <div class="flex justify-end space-x-4 mt-4">
          <button type="button" onclick="closeAllModals()" class="px-4 py-2 bg-gray-300 text-black rounded">Cancel</button>
          <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded hover:scale-105 hover:shadow-xl">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" onclick="closeModal(event)" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="p-8 rounded-2xl shadow-2xl w-full max-w-md text-center bg-white/30 backdrop-blur-md hover:ring-4 hover:ring-red-400 hover:ring-offset-2 hover:shadow-[0_0_30px_rgba(220,38,38,0.6)]" onclick="event.stopPropagation();">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="parent_id" id="delete_id">
        <h3 class="text-xl font-bold text-white mb-4">⚠️ Confirm Deletion</h3>
        <p class="text-white mb-6">Are you sure you want to delete this parent?</p>
        <div class="flex justify-center space-x-4">
          <button type="button" onclick="closeAllModals()" class="px-4 py-2 bg-gray-300 rounded text-black">Cancel</button>
          <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded hover:scale-105 hover:shadow-xl">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Validation functions
    function validateFullName(name) {
      const regex = /^[a-zA-Z\s\.\-]+$/;
      if (!name) return "Full name is required";
      if (name.length > 100) return "Name must be less than 100 characters";
      if (!regex.test(name)) return "Only letters, spaces, dots and hyphens allowed";
      return "";
    }

    function validateContact(contact) {
      const regex = /^(97|98)[0-9]{8}$/;
      if (!contact) return "Contact number is required";
      if (!regex.test(contact)) return "Contact must start with 97 or 98 and be 10 digits";
      return "";
    }

    function validateAddress(address) {
      if (!address) return "Address is required";
      if (address.length > 255) return "Address must be less than 255 characters";
      if (!/^[a-zA-Z]/.test(address)) return "Address must start with a letter";
      return "";
    }

    function validateEmail(email) {
      const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email) return "Email is required";
      if (!regex.test(email)) return "Invalid email format";
      if (email.length > 100) return "Email must be less than 100 characters";
      return "";
    }

    function validateUsername(username) {
      const regex = /^[a-zA-Z0-9_]+$/;
      if (!username) return "Username is required";
      if (username.length < 4 || username.length > 20) return "Username must be 4-20 characters";
      if (!regex.test(username)) return "Only letters, numbers and underscore allowed";
      return "";
    }

    function validatePassword(password) {
      if (!password) return "Password is required";
      if (password.length < 8) return "Password must be at least 8 characters";
      return "";
    }

    // Real-time validation
    $(document).ready(function() {
      // Full Name validation
      $('#full_name').on('input', function() {
        const error = validateFullName($(this).val());
        updateFieldValidation(this, 'full_name', error);
      });

      // Contact validation
      $('#contact').on('input', function() {
        const error = validateContact($(this).val());
        updateFieldValidation(this, 'contact', error);
      });

      // Address validation
      $('#address').on('input', function() {
        const error = validateAddress($(this).val());
        updateFieldValidation(this, 'address', error);
      });

      // Email validation
      $('#email').on('input', function() {
        const error = validateEmail($(this).val());
        updateFieldValidation(this, 'email', error);
      });

      // Username validation
      $('#username').on('input', function() {
        const error = validateUsername($(this).val());
        updateFieldValidation(this, 'username', error);
      });

      // Password validation
      $('#password').on('input', function() {
        const error = validatePassword($(this).val());
        updateFieldValidation(this, 'password', error);
      });

      // Helper function to update field validation UI
      function updateFieldValidation(field, fieldName, error) {
        const errorElement = $(`#${fieldName}Error`);
        if (error) {
          $(field).addClass('error-border');
          if (!errorElement.length) {
            $(field).after(`<div id="${fieldName}Error" class="error-text">${error}</div>`);
          } else {
            errorElement.text(error);
          }
        } else {
          $(field).removeClass('error-border');
          errorElement.remove();
        }
      }

      // Form submission validation
      $('#parentForm').on('submit', function(e) {
        let isValid = true;
        
        // Validate all fields
        const fieldsToValidate = [
          { id: 'full_name', validator: validateFullName },
          { id: 'contact', validator: validateContact },
          { id: 'address', validator: validateAddress },
          { id: 'email', validator: validateEmail },
          { id: 'username', validator: validateUsername }
        ];

        // Only validate password if it's an add action
        if ($('#formAction').val() === 'add') {
          fieldsToValidate.push({ id: 'password', validator: validatePassword });
        }

        fieldsToValidate.forEach(field => {
          const value = $(`#${field.id}`).val();
          const error = field.validator(value);
          if (error) {
            updateFieldValidation($(`#${field.id}`)[0], field.id, error);
            isValid = false;
          }
        });

        if (!isValid) {
          e.preventDefault();
          // Scroll to the first error
          $('html, body').animate({
            scrollTop: $('.error-border').first().offset().top - 100
          }, 500);
        }
      });
    });

    function filterTable() {
      const filter = document.getElementById("searchInput").value.toLowerCase();
      const rows = document.querySelectorAll("table tbody tr");
      rows.forEach(row => {
        const name = row.querySelector("td").textContent.toLowerCase();
        row.style.display = name.includes(filter) ? "" : "none";
      });
    }

    function openAddModal() {
      document.getElementById("modalTitle").innerText = "➕ Add Parent";
      document.getElementById("formAction").value = "add";
      document.getElementById("parent_id").value = "";
      
      // Clear all fields and errors
      const fields = ['full_name', 'contact', 'address', 'email', 'username', 'password'];
      fields.forEach(id => {
        document.getElementById(id).value = "";
        document.getElementById(id).classList.remove("error-border");
        const errorElement = document.getElementById(`${id}Error`);
        if (errorElement) errorElement.remove();
      });
      
      document.getElementById("passwordDiv").style.display = "block";
      document.getElementById("password").required = true;
      document.getElementById("modalBox").classList.remove("hover:ring-green-400");
      document.getElementById("modalBox").classList.add("hover:ring-blue-400");
      document.getElementById("parentModal").classList.remove("hidden");
    }

    function openEditModal(data) {
      document.getElementById("modalTitle").innerText = "✏️ Edit Parent";
      document.getElementById("formAction").value = "edit";
      document.getElementById("parent_id").value = data.id;
      
      // Set field values
      const fields = {
        'full_name': data.full_name,
        'contact': data.contact,
        'address': data.address,
        'email': data.email,
        'username': data.username
      };
      
      Object.entries(fields).forEach(([id, value]) => {
        document.getElementById(id).value = value;
        document.getElementById(id).classList.remove("error-border");
        const errorElement = document.getElementById(`${id}Error`);
        if (errorElement) errorElement.remove();
      });
      
      document.getElementById("passwordDiv").style.display = "none";
      document.getElementById("password").required = false;
      document.getElementById("modalBox").classList.remove("hover:ring-blue-400");
      document.getElementById("modalBox").classList.add("hover:ring-green-400");
      document.getElementById("parentModal").classList.remove("hidden");
    }

    function openDeleteModal(id) {
      document.getElementById("delete_id").value = id;
      document.getElementById("deleteModal").classList.remove("hidden");
    }

    function closeModal(e) {
      if (e.target.id === "parentModal" || e.target.id === "deleteModal") {
        e.target.classList.add("hidden");
      }
    }

    function closeAllModals() {
      document.getElementById("parentModal").classList.add("hidden");
      document.getElementById("deleteModal").classList.add("hidden");
    }
  </script>
</body>
</html>