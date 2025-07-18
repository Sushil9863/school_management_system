<?php
include '../partials/dbconnect.php';


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
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = custom_hash($_POST['password']);

    $stmt1 = $conn->prepare("INSERT INTO parents (full_name, contact, address, email, username, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt1->bind_param("ssssss", $full_name, $contact, $address, $email, $username, $password);
    $stmt1->execute();

    $stmt2 = $conn->prepare("INSERT INTO users (username, type, password, email) VALUES (?, 'parent', ?, ?)");
    $stmt2->bind_param("sss", $username, $password, $email);
    $stmt2->execute();
  }

  if ($action === 'edit') {
    $id = $_POST['parent_id'];
    $full_name = $_POST['full_name'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $username = $_POST['username'];

    $stmt = $conn->prepare("UPDATE parents SET full_name=?, contact=?, address=?, email=?, username=? WHERE id=?");
    $stmt->bind_param("sssssi", $full_name, $contact, $address, $email, $username, $id);
    $stmt->execute();
  }

  if ($action === 'delete') {
    $id = $_POST['parent_id'];

    $res = $conn->query("SELECT username FROM parents WHERE id = $id");
    $parent = $res->fetch_assoc();
    $username = $parent['username'];

    $conn->query("DELETE FROM users WHERE username = '$username' AND type = 'parent'");
    $conn->query("DELETE FROM parents WHERE id = $id");
  }
}


// Fetch parents
$parents = $conn->query("SELECT * FROM parents ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Parents</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">👨‍👩‍👧 Manage Parents</h1>
      <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add
        Parent</button>
    </div>

    <div class="mb-4">
      <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search by name..."
        class="px-4 py-2 rounded border border-gray-300 shadow-sm w-full md:w-64">
    </div>

    <table id="parentTable" class="min-w-full bg-white border text-sm">
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
              <button onclick='openEditModal(<?= json_encode($row) ?>)'
                class="bg-yellow-400 text-white px-3 py-1 rounded text-sm hover:bg-yellow-500">Edit</button>
              <button onclick='openDeleteModal(<?= $row['id'] ?>)'
                class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Delete</button>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

  <!-- Add/Edit Modal -->
  <div id="parentModal" onclick="closeModal(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div id="modalBox" class="animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-2xl transition duration-300 ease-in-out
      hover:ring-4 hover:ring-offset-2 filter hover:brightness-110 bg-white/30 backdrop-blur-md"
      onclick="event.stopPropagation();">
      <h2 id="modalTitle" class="text-2xl font-bold mb-6 text-center text-white">➕ Add Parent</h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="parent_id" id="parent_id">
        <input type="hidden" name="action" id="formAction" value="add">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-white">
          <div>
            <label class="block font-medium">Full Name</label>
            <input type="text" name="full_name" id="full_name" required
              class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div>
            <label class="block font-medium">Contact</label>
            <input type="text" name="contact" id="contact" required
              class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div>
            <label class="block font-medium">Address</label>
            <input type="text" name="address" id="address" required
              class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div>
            <label class="block font-medium">Email</label>
            <input type="email" name="email" id="email" required
              class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div>
            <label class="block font-medium">Username</label>
            <input type="text" name="username" id="username" required
              class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
          <div id="passwordDiv">
            <label class="block font-medium">Password</label>
            <input type="password" name="password" id="password" required
              class="w-full px-4 py-2 border rounded bg-white/80 text-black" />
          </div>
        </div>
        <div class="flex justify-end mt-4">
          <button type="button" onclick="document.getElementById('parentModal').classList.add('hidden')"
            class="px-4 py-2 bg-gray-300 rounded mr-2">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" onclick="closeModal(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-md text-center transition duration-300 ease-in-out
      hover:ring-4 hover:ring-red-400 hover:ring-offset-2
      hover:shadow-[0_0_30px_rgba(220,38,38,0.6)] filter hover:brightness-110 bg-white/30 backdrop-blur-md"
      onclick="event.stopPropagation();">
      <form method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="parent_id" id="delete_id">
        <h3 class="text-xl font-bold text-white mb-4">⚠️ Confirm Deletion</h3>
        <p class="text-white mb-6">Are you sure you want to delete this parent?</p>
        <div class="flex justify-center space-x-4">
          <button type="button" onclick="closeAllModals()"
            class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400 font-semibold">Cancel</button>
          <button type="submit"
            class="px-6 py-2 rounded bg-gradient-to-r from-red-600 to-pink-600 text-white hover:scale-105 hover:shadow-xl font-semibold">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function filterTable() {
      const filter = document.getElementById("searchInput").value.toLowerCase();
      const rows = document.querySelectorAll("#parentTable tbody tr");
      rows.forEach(row => {
        const name = row.querySelector("td").textContent.toLowerCase();
        row.style.display = name.includes(filter) ? "" : "none";
      });
    }

    function closeModal(e) {
      if (e.target.id === "parentModal" || e.target.id === "deleteModal") {
        e.target.classList.add("hidden");
      }
    }

    function openAddModal() {
      document.getElementById("modalTitle").innerText = "➕ Add Parent";
      document.getElementById("formAction").value = "add";
      document.getElementById("parent_id").value = "";
      document.getElementById("full_name").value = "";
      document.getElementById("contact").value = "";
      document.getElementById("address").value = "";
      document.getElementById("email").value = "";
      document.getElementById("username").value = "";
      document.getElementById("password").value = "";

      // Show and make password required
      document.getElementById("passwordDiv").style.display = "block";
      document.getElementById("password").required = true;

      document.getElementById("modalBox").classList.remove("ring-green-400");
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

      // Hide and remove required from password
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

    function closeAllModals() {
      document.getElementById("parentModal").classList.add("hidden");
      document.getElementById("deleteModal").classList.add("hidden");
    }
  </script>
</body>

</html>