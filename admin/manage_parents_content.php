<?php
require '../partials/dbconnect.php';
require 'check_admin.php'; // Session check (sets $_SESSION['school_id'])

require '../vendor/autoload.php';
use Dompdf\Dompdf;

// Custom password hash (same as teachers/students)
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

$school_id = $_SESSION['school_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $full_name = $_POST['full_name'];
        $contact = $_POST['contact'];
        $address = $_POST['address'];
        $email = $_POST['email'];
        $username = $_POST['username'];
        $password = custom_hash($_POST['password']);

        $stmt1 = $conn->prepare("INSERT INTO parents (full_name, contact, address, email, username, password, school_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt1->bind_param("ssssssi", $full_name, $contact, $address, $email, $username, $password, $school_id);
        $stmt1->execute();

        $stmt2 = $conn->prepare("INSERT INTO users (username, type, password, email, school_id) VALUES (?, 'parent', ?, ?, ?)");
        $stmt2->bind_param("sssi", $username, $password, $email, $school_id);
        $stmt2->execute();
    }

    if ($action === 'edit') {
        $id = $_POST['parent_id'];
        $stmt = $conn->prepare("UPDATE parents SET full_name=?, contact=?, address=?, email=?, username=? WHERE id=? AND school_id=?");
        $stmt->bind_param("sssssii", $_POST['full_name'], $_POST['contact'], $_POST['address'], $_POST['email'], $_POST['username'], $id, $school_id);
        $stmt->execute();

        $stmt2 = $conn->prepare("UPDATE users SET email=? WHERE username=(SELECT username FROM parents WHERE id=? AND school_id=?) AND school_id=?");
        $stmt2->bind_param("siii", $_POST['email'], $id, $school_id, $school_id);
        $stmt2->execute();
    }

    if ($action === 'delete') {
        $id = $_POST['parent_id'];
        $res = $conn->prepare("SELECT username FROM parents WHERE id=? AND school_id=?");
        $res->bind_param("ii", $id, $school_id);
        $res->execute();
        $username = $res->get_result()->fetch_assoc()['username'] ?? '';

        if ($username) {
            $stmtDelUser = $conn->prepare("DELETE FROM users WHERE username=? AND type='parent' AND school_id=?");
            $stmtDelUser->bind_param("si", $username, $school_id);
            $stmtDelUser->execute();

            $stmtDelParent = $conn->prepare("DELETE FROM parents WHERE id=? AND school_id=?");
            $stmtDelParent->bind_param("ii", $id, $school_id);
            $stmtDelParent->execute();
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
</head>
<body class="bg-gradient-to-r from-green-100 to-blue-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
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
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="parent_id" id="parent_id">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-white">
          <div>
            <label class="block font-medium">Full Name</label>
            <input type="text" name="full_name" id="full_name" required class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div>
            <label class="block font-medium">Contact</label>
            <input type="text" name="contact" id="contact" required class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div>
            <label class="block font-medium">Address</label>
            <input type="text" name="address" id="address" required class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div>
            <label class="block font-medium">Email</label>
            <input type="email" name="email" id="email" required class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div>
            <label class="block font-medium">Username</label>
            <input type="text" name="username" id="username" required class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div id="passwordDiv">
            <label class="block font-medium">Password</label>
            <input type="password" name="password" id="password" class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
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
      ["full_name", "contact", "address", "email", "username", "password"].forEach(id => document.getElementById(id).value = "");
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
      document.getElementById("full_name").value = data.full_name;
      document.getElementById("contact").value = data.contact;
      document.getElementById("address").value = data.address;
      document.getElementById("email").value = data.email;
      document.getElementById("username").value = data.username;
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
      if (e.target.id === "parentModal" || e.target.id === "deleteModal") e.target.classList.add("hidden");
    }

    function closeAllModals() {
      document.getElementById("parentModal").classList.add("hidden");
      document.getElementById("deleteModal").classList.add("hidden");
    }
  </script>
</body>
</html>
