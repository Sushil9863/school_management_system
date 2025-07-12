<?php
include '../partials/dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $grade = $_POST['grade'];
    $section = $_POST['section'];
    $class_type = $_POST['class_type'];

    $stmt = $conn->prepare("INSERT INTO classes (name, grade, section, class_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $grade, $section, $class_type);
    $stmt->execute();
}

$class_query = "SELECT * FROM classes ORDER BY created_at DESC";
$classes = $conn->query($class_query);
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
      <div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Class</button>
        <a href="export_classes.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded ml-2">📄 Export Excel</a>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full bg-white border text-sm">
        <thead>
          <tr class="bg-gray-100 text-gray-600 uppercase">
            <!-- <th class="py-3 px-6 text-left">Class Name</th> -->
            <th class="py-3 px-6 text-left">Class Type</th>
            <th class="py-3 px-6 text-left">Grade</th>
            <th class="py-3 px-6 text-left">Section</th>
            <th class="py-3 px-6 text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $classes->fetch_assoc()): ?>
            <tr class="border-t hover:bg-gray-50">
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['name']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['class_type']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['grade']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['section']); ?></td>
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
      <form action="" method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <!-- <div>
            <label class="block text-sm font-medium text-gray-700">Class Name</label>
            <input type="text" name="name" required class="w-full px-4 py-2 border rounded" />
          </div> -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Grade</label>
            <input type="text" name="grade" required class="w-full px-4 py-2 border rounded" />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Section</label>
            <input type="text" name="section" required class="w-full px-4 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Class Type</label>
            <select name="class_type" required class="w-full px-4 py-2 border rounded">
              <option value="">-- Select Class Type --</option>
              <option value="Pre-Primary">Pre-Primary</option>
              <option value="Primary">Primary</option>
              <option value="Secondary">Secondary</option>
            </select>
          </div>
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
