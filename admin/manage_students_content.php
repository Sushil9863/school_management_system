<?php
include '../partials/dbconnect.php';

// Fetch parents and classes for dropdowns
$parent_result = $conn->query("SELECT id, full_name FROM parents ORDER BY full_name ASC");
$class_result = $conn->query("SELECT id, grade, section FROM classes ORDER BY grade ASC");

// Handle student form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $class_id = $_POST['class_id'];
    $parent_id = $_POST['parent'];

    $stmt = $conn->prepare("INSERT INTO students (full_name, gender, dob, grade, parent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssii", $full_name, $gender, $dob, $class_id, $parent_id);
    $stmt->execute();
}

// Fetch students list with class and parent info
$students = $conn->query("
    SELECT s.*, p.full_name AS parent_name, c.grade AS class_grade, c.section AS class_section
    FROM students s
    LEFT JOIN parents p ON s.parent = p.id
    LEFT JOIN classes c ON s.grade = c.id
    ORDER BY s.id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Students</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .glass {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-radius: 1rem;
      border: 1px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    }
  </style>
</head>

<body class="bg-gradient-to-r from-blue-100 to-purple-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">🎓 Manage Students</h1>
      <button onclick="document.getElementById('addModal').classList.remove('hidden')"
        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Student</button>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full bg-white border text-sm">
        <thead class="bg-gray-100 text-gray-600 uppercase">
          <tr>
            <th class="py-3 px-6 text-left">Full Name</th>
            <th class="py-3 px-6 text-left">Gender</th>
            <th class="py-3 px-6 text-left">Date of Birth</th>
            <th class="py-3 px-6 text-left">Class</th>
            <th class="py-3 px-6 text-left">Parent</th>
            <th class="py-3 px-6 text-center">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $students->fetch_assoc()): ?>
            <tr class="border-t hover:bg-gray-50">
              <td class="py-3 px-6"><?= htmlspecialchars($row['full_name']) ?></td>
              <td class="py-3 px-6"><?= htmlspecialchars($row['gender']) ?></td>
              <td class="py-3 px-6"><?= htmlspecialchars($row['dob']) ?></td>
              <td class="py-3 px-6">Grade <?= htmlspecialchars($row['class_grade']) ?> - <?= htmlspecialchars($row['class_section']) ?></td>
              <td class="py-3 px-6"><?= htmlspecialchars($row['parent_name']) ?></td>
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

  <!-- Add Student Modal -->
  <div id="addModal" class="fixed inset-0 bg-black/50 flex items-center justify-center hidden z-50">
    <div class="glass p-8 rounded-xl w-full max-w-2xl">
      <h2 class="text-xl font-semibold mb-6 text-white text-center">Add New Student</h2>
      <form method="POST" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-white mb-1">Full Name</label>
            <input type="text" name="full_name" required class="w-full px-4 py-2 rounded border border-white/30 bg-white/10 text-white placeholder-white" />
          </div>
          <div>
            <label class="block text-sm text-white mb-1">Gender</label>
            <select name="gender" required class="w-full px-4 py-2 rounded border border-white/30 bg-white/10 text-white">
              <option value="">-- Select --</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm text-white mb-1">Date of Birth</label>
            <input type="date" name="dob" required class="w-full px-4 py-2 rounded border border-white/30 bg-white/10 text-white" />
          </div>
          <div>
            <label class="block text-sm text-white mb-1">Class</label>
            <select name="class_id" required class="w-full px-4 py-2 rounded border border-white/30 bg-white/10 text-white">
              <option value="">-- Select Class --</option>
              <?php
              $class_result->data_seek(0);
              while ($class = $class_result->fetch_assoc()): ?>
                <option value="<?= $class['id'] ?>">
                  Grade <?= htmlspecialchars($class['grade']) ?> - Section <?= htmlspecialchars($class['section']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm text-white mb-1">Parent</label>
          <select name="parent" required class="w-full px-4 py-2 rounded border border-white/30 bg-white/10 text-white">
            <option value="">-- Select Parent --</option>
            <?php
            $parent_result->data_seek(0);
            while ($parent = $parent_result->fetch_assoc()): ?>
              <option value="<?= $parent['id'] ?>">
                <?= htmlspecialchars($parent['full_name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="flex justify-end mt-4">
          <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
            class="px-4 py-2 bg-white text-gray-700 rounded mr-2">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Add Student</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
