<?php
include '../partials/dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $full_name = $_POST['full_name'];
        $address = $_POST['address'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $stmt1 = $conn->prepare("INSERT INTO teachers (full_name, address, phone, email, username, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt1->bind_param("ssssss", $full_name, $address, $phone, $email, $username, $password);
        $stmt1->execute();

        $stmt2 = $conn->prepare("INSERT INTO users (username, type, password, email) VALUES (?, 'teacher', ?, ?)");
        $stmt2->bind_param("sss", $username, $password, $email);
        $stmt2->execute();
    }

    if ($action === 'edit') {
        $teacher_id = $_POST['teacher_id'];
        $stmt = $conn->prepare("UPDATE teachers SET full_name=?, address=?, phone=?, email=? WHERE id=?");
        $stmt->bind_param("ssssi", $_POST['full_name'], $_POST['address'], $_POST['phone'], $_POST['email'], $teacher_id);
        $stmt->execute();

        $stmt2 = $conn->prepare("UPDATE users SET email=? WHERE username=(SELECT username FROM teachers WHERE id=?)");
        $stmt2->bind_param("si", $_POST['email'], $teacher_id);
        $stmt2->execute();
    }

    if ($action === 'delete') {
        $teacher_id = $_POST['teacher_id'];
        $res = $conn->query("SELECT username FROM teachers WHERE id = $teacher_id");
        $username = $res->fetch_assoc()['username'];

        $conn->query("DELETE FROM users WHERE username = '$username' AND type = 'teacher'");
        $conn->query("DELETE FROM teachers WHERE id = $teacher_id");
    }
}

$teachers = $conn->query("SELECT * FROM teachers ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Teachers</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-r from-blue-100 to-purple-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold text-gray-800">👩‍🏫 Manage Teachers</h1>
      <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Teacher</button>
    </div>

    <div class="mb-4">
      <input type="text" id="searchInput" onkeyup="filterTeachers()" placeholder="Search by name..."
        class="w-full md:w-64 px-4 py-2 rounded border border-gray-300 shadow-sm">
    </div>

    <table class="min-w-full bg-white border text-sm">
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
      <form method="POST" class="space-y-4">
        <input type="hidden" name="action" id="formAction" value="add">
        <input type="hidden" name="teacher_id" id="teacher_id">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-white font-medium mb-1">Full Name</label>
            <input type="text" name="full_name" id="full_name" required class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800" />
          </div>
          <div>
            <label class="block text-white font-medium mb-1">Address</label>
            <input type="text" name="address" id="address" required class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-white font-medium mb-1">Phone</label>
            <input type="text" name="phone" id="phone" required class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800" />
          </div>
          <div>
            <label class="block text-white font-medium mb-1">Email</label>
            <input type="email" name="email" id="email" required class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800" />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="authFields">
          <div>
            <label class="block text-white font-medium mb-1">Username</label>
            <input type="text" name="username" id="username" class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800" />
          </div>
          <div>
            <label class="block text-white font-medium mb-1">Password</label>
            <input type="password" name="password" id="password" class="w-full px-4 py-2 rounded bg-white/70 border border-white text-gray-800" />
          </div>
        </div>

        <div class="flex justify-end space-x-3 mt-4">
          <button type="button" onclick="closeAllModals()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 font-semibold">Cancel</button>
          <button type="submit" class="px-6 py-2 rounded bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:scale-105 hover:shadow-xl font-semibold">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" onclick="closeModal(event)" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-md text-center transition duration-300 ease-in-out
      hover:ring-4 hover:ring-red-400 hover:ring-offset-2
      hover:shadow-[0_0_30px_rgba(220,38,38,0.6)] filter hover:brightness-110" onclick="event.stopPropagation();">
      <form method="POST">
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
    function filterTeachers() {
      const filter = document.getElementById("searchInput").value.toLowerCase();
      const rows = document.querySelectorAll("table tbody tr");
      rows.forEach(row => {
        const name = row.querySelector("td").textContent.toLowerCase();
        row.style.display = name.includes(filter) ? "" : "none";
      });
    }

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
</body>
</html>
