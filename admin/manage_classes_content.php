<?php
include '../partials/dbconnect.php'; // Include database connection
// Fetch all classes with school names
$class_query = "SELECT classes.*, schools.name AS school_name FROM classes 
                LEFT JOIN schools ON classes.school_id = schools.id ORDER BY classes.created_at DESC";
$classes = $conn->query($class_query);

// Fetch all schools for the dropdown
$school_query = "SELECT id, name FROM schools";
$schools = $conn->query($school_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Classes</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">📘 Manage Classes</h1>
      <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Class</button>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full bg-white border">
        <thead>
          <tr class="bg-gray-100 text-left text-gray-600 text-sm uppercase">
            <th class="py-3 px-6">Class Name</th>
            <th class="py-3 px-6 text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $classes->fetch_assoc()): ?>
            <tr class="border-t hover:bg-gray-50">
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['name']); ?></td>
      
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['school_name']); ?></td>
              <td class="py-3 px-6">
                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $row['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                  <?php echo ucfirst($row['status']); ?>
                </span>
              </td>
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

  <!-- Add Class Modal -->
  <div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="bg-white w-full max-w-lg rounded-lg p-6 relative">
      <h2 class="text-xl font-semibold mb-4 text-gray-800">Add New Class</h2>
      <form action="insert_class.php" method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Class Name</label>
            <input type="text" name="name" required class="w-full px-4 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Class Code</label>
            <input type="text" name="code" required class="w-full px-4 py-2 border rounded" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">School</label>
          <select name="school_id" required class="w-full px-4 py-2 border rounded">
            <option value="">-- Select School --</option>
            <?php while ($s = $schools->fetch_assoc()): ?>
              <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Status</label>
          <select name="status" class="w-full px-4 py-2 border rounded">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        <div class="flex justify-end mt-4">
          <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-4 py-2 bg-gray-300 rounded mr-2">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Add Class</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
