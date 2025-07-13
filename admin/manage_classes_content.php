<?php
include '../partials/dbconnect.php';

// Fetch all teachers (for dropdown)
$teacher_result = $conn->query("SELECT username, full_name FROM teachers ORDER BY full_name ASC");

// Handle Add Class Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $grade = $_POST['grade'];
  $section = $_POST['section'];
  $class_type = $_POST['class_type'];
  $selected_username = $_POST['class_teacher_username'];

  // Get full name using username
  $stmt = $conn->prepare("SELECT full_name FROM teachers WHERE username = ?");
  $stmt->bind_param("s", $selected_username);
  $stmt->execute();
  $result = $stmt->get_result();
  $teacher = $result->fetch_assoc();
  $class_teacher = $teacher ? $teacher['full_name'] : '';

  // Insert into classes
  $stmt = $conn->prepare("INSERT INTO classes (grade, section, type, class_teacher) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssss", $grade, $section, $class_type, $class_teacher);
  $stmt->execute();
}

// Fetch all classes
$class_query = "SELECT * FROM classes ORDER BY id DESC";
$classes = $conn->query($class_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Classes</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">📘 Manage Classes</h1>
      <div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Class</button>
       </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full bg-white border text-sm">
        <thead>
          <tr class="bg-gray-100 text-gray-600 uppercase">
            <th class="py-3 px-6 text-left">Grade</th>
            <th class="py-3 px-6 text-left">Section</th>
            <th class="py-3 px-6 text-left">Type</th>
            <th class="py-3 px-6 text-left">Class Teacher</th>
            <th class="py-3 px-6 text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $classes->fetch_assoc()): ?>
            <tr class="border-t hover:bg-gray-50">
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['grade']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['section']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['type']); ?></td>
              <td class="py-3 px-6"><?php echo htmlspecialchars($row['class_teacher']); ?></td>
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
          <div>
            <label class="block text-sm font-medium text-gray-700">Grade</label>
            <input type="text" name="grade" required class="w-full px-4 py-2 border rounded" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Section</label>
            <input type="text" name="section" required class="w-full px-4 py-2 border rounded" />
          </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700">Class Type</label>
            <select name="class_type" required class="w-full px-4 py-2 border rounded">
              <option value="">-- Select Class Type --</option>
              <option value="Pre-Primary">Pre-Primary</option>
              <option value="Primary">Primary</option>
              <option value="Secondary">Secondary</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Class Teacher</label>
            <select name="class_teacher_username" required class="w-full px-4 py-2 border rounded">
              <option value="">-- Select Class Teacher --</option>
              <?php
              // Reset the result pointer since it's already been used once
              $teacher_result->data_seek(0);
              while ($teacher = $teacher_result->fetch_assoc()): ?>
                <option value="<?= htmlspecialchars($teacher['username']) ?>">
                  <?= htmlspecialchars($teacher['full_name']) ?> (<?= htmlspecialchars($teacher['username']) ?>)
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
        <div class="flex justify-end mt-4">
          <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
            class="px-4 py-2 bg-gray-300 rounded mr-2">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Add Class</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
