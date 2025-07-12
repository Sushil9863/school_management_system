<?php
include '../partials/dbconnect.php';

// Handle add parent
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Securely hash password

    // Insert into parents table
    $stmt1 = $conn->prepare("INSERT INTO parents (full_name, contact, address, email, username, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt1->bind_param("ssssss", $full_name, $contact, $address, $email, $username, $password);
    $stmt1->execute();

    // Insert into users table
    $stmt2 = $conn->prepare("INSERT INTO users (username, type, password, email) VALUES (?, 'parent', ?, ?)");
    $stmt2->bind_param("sss", $username, $password, $email);
    $stmt2->execute();
}

// Fetch all parents
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
      <button onclick="document.getElementById('addModal').classList.remove('hidden')"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Parent</button>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full bg-white border text-sm">
        <thead>
          <tr class="bg-gray-100 text-gray-600 uppercase">
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
                <button class="bg-yellow-400 text-white px-3 py-1 rounded text-sm hover:bg-yellow-500">Edit</button>
                <button class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Delete</button>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add Parent Modal -->
  <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white w-full max-w-lg rounded-lg p-6 relative">
      <h2 class="text-xl font-semibold mb-4 text-gray-800">Add New Parent</h2>
      <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Full Name</label>
            <input type="text" name="full_name" required class="w-full px-4 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Contact</label>
            <input type="text" name="contact" required class="w-full px-4 py-2 border rounded" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Address</label>
          <input type="text" name="address" required class="w-full px-4 py-2 border rounded" />
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" required class="w-full px-4 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Username</label>
            <input type="text" name="username" required class="w-full px-4 py-2 border rounded" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Password</label>
          <input type="password" name="password" required class="w-full px-4 py-2 border rounded" />
        </div>
        <div class="flex justify-end mt-4">
          <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
            class="px-4 py-2 bg-gray-300 rounded mr-2">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Add Parent</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
