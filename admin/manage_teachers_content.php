<?php
include '../partials/dbconnect.php';

// Handle teacher creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {
    $full_name = $_POST['full_name'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // secure hash

    // Insert into teachers table
    $stmt1 = $conn->prepare("INSERT INTO teachers (full_name, address, phone, email, username, password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt1->bind_param("ssssss", $full_name, $address, $phone, $email, $username, $password);
    $stmt1->execute();

    // Insert into users table
    $user_type = 'teacher';
    $stmt2 = $conn->prepare("INSERT INTO users (username, type, password, email) VALUES (?, ?, ?, ?)");
    $stmt2->bind_param("ssss", $username, $user_type, $password, $email);
    $stmt2->execute();
}

// Handle teacher delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Get teacher by ID to get username
    $res = $conn->query("SELECT username FROM teachers WHERE id = $id");
    $teacher = $res->fetch_assoc();
    $username = $teacher['username'];

    // Delete from users table
    $conn->query("DELETE FROM users WHERE username = '$username' AND type = 'teacher'");

    // Delete from teachers table
    $conn->query("DELETE FROM teachers WHERE id = $id");
}

// Fetch all teachers
$result = $conn->query("SELECT * FROM teachers ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Teachers</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">👩‍🏫 Manage Teachers</h1>
      <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Teacher</button>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full bg-white border text-sm">
        <thead class="bg-gray-100 text-gray-600 uppercase">
          <tr>
            <th class="py-3 px-6 text-left">Full Name</th>
            <th class="py-3 px-6 text-left">Address</th>
            <th class="py-3 px-6 text-left">Phone</th>
            <th class="py-3 px-6 text-left">Email</th>
            <th class="py-3 px-6 text-left">Username</th>
            <th class="py-3 px-6 text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr class="border-t hover:bg-gray-50">
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['full_name']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['address']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['phone']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['email']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['username']); ?></td>
              <td class="py-3 px-6 text-center">
                <a href="?delete=<?php echo $row['id']; ?>" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm" onclick="return confirm('Are you sure you want to delete this teacher?')">Delete</a>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add Teacher Modal -->
  <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white w-full max-w-lg rounded-lg p-6 relative">
      <h2 class="text-xl font-semibold mb-4 text-gray-800">Add New Teacher</h2>
      <form action="" method="POST" class="space-y-4">
        <input type="hidden" name="add_teacher" value="1">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Full Name</label>
            <input type="text" name="full_name" required class="w-full px-4 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Address</label>
            <input type="text" name="address" required class="w-full px-4 py-2 border rounded" />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Phone</label>
            <input type="text" name="phone" required class="w-full px-4 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" required class="w-full px-4 py-2 border rounded" />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Username</label>
            <input type="text" name="username" required class="w-full px-4 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Password</label>
            <input type="password" name="password" required class="w-full px-4 py-2 border rounded" />
          </div>
        </div>
        <div class="flex justify-end mt-4">
          <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 bg-gray-300 rounded mr-2">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Add Teacher</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
