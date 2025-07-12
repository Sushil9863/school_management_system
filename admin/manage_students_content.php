<?php
include '../partials/dbconnect.php';

// Optional: secure access only to logged-in users (you can remove this if not needed)
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Fetch students with school names
$query = "SELECT students.*, schools.name AS school_name 
          FROM students 
          LEFT JOIN schools ON students.school_id = schools.id 
          ORDER BY students.created_at DESC";
$result = $conn->query($query);

// Fetch schools for dropdown
$schools = $conn->query("SELECT id, name FROM schools");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Students - Shikshalaya</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

  <div class="max-w-7xl mx-auto bg-white mt-8 rounded-lg shadow-md p-6">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold text-gray-800">Manage Students</h1>
      <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
        + Add Student
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full table-auto border text-sm text-left text-gray-600">
        <thead class="bg-gray-100 text-gray-700 font-semibold">
          <tr>
            <th class="p-3">Name</th>
            <th class="p-3">Gender</th>
            <th class="p-3">DOB</th>
            <th class="p-3">Grade</th>
            <th class="p-3">School</th>
            <th class="p-3">Status</th>
            <th class="p-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
          <tr class="border-b hover:bg-gray-50">
            <td class="p-3"><?= htmlspecialchars($row['full_name']) ?></td>
            <td class="p-3"><?= htmlspecialchars($row['gender']) ?></td>
            <td class="p-3"><?= htmlspecialchars($row['dob']) ?></td>
            <td class="p-3"><?= htmlspecialchars($row['grade']) ?></td>
            <td class="p-3"><?= $row['school_name'] ?? 'N/A' ?></td>
            <td class="p-3">
              <span class="px-2 py-1 text-xs rounded-full <?= $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= ucfirst($row['status']) ?>
              </span>
            </td>
            <td class="p-3 text-center space-x-2">
              <button class="bg-yellow-400 hover:bg-yellow-500 px-3 py-1 text-white rounded text-sm">Edit</button>
              <button class="bg-red-500 hover:bg-red-600 px-3 py-1 text-white rounded text-sm">Delete</button>
            </td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add Student Modal -->
  <div id="addModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 hidden z-50">
    <div class="bg-white w-full max-w-lg rounded-lg p-6 relative shadow-xl">
      <h2 class="text-xl font-semibold mb-4">Add New Student</h2>
      <form action="insert_student.php" method="POST" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium">Full Name</label>
            <input type="text" name="full_name" required class="w-full px-3 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium">Gender</label>
            <select name="gender" required class="w-full px-3 py-2 border rounded">
              <option value="">Select</option>
              <option>Male</option>
              <option>Female</option>
              <option>Other</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium">Date of Birth</label>
            <input type="date" name="dob" required class="w-full px-3 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium">Grade</label>
            <input type="text" name="grade" required class="w-full px-3 py-2 border rounded" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium">Email</label>
            <input type="email" name="email" class="w-full px-3 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium">Phone</label>
            <input type="text" name="phone" class="w-full px-3 py-2 border rounded" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium">Address</label>
          <textarea name="address" class="w-full px-3 py-2 border rounded"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium">School</label>
          <select name="school_id" required class="w-full px-3 py-2 border rounded">
            <option value="">Select School</option>
            <?php while ($school = $schools->fetch_assoc()): ?>
              <option value="<?= $school['id'] ?>"><?= htmlspecialchars($school['name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="flex justify-end pt-2">
          <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 bg-gray-300 rounded mr-2">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
        </div>
      </form>
    </div>
  </div>

</body>
</html>
