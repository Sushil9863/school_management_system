<?php
include '../partials/dbconnect.php';

// Fetch teachers
$teacher_result = $conn->query("SELECT id, username, full_name FROM teachers ORDER BY full_name ASC");

// Handle Add Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
  $grade = $_POST['grade'];
  $section = $_POST['section'];
  $class_type = $_POST['class_type'];
  $teacher_id = $_POST['class_teacher_id'];

  $stmt = $conn->prepare("SELECT full_name FROM teachers WHERE id = ?");
  $stmt->bind_param("i", $teacher_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $teacher = $result->fetch_assoc();
  $class_teacher = $teacher['full_name'] ?? '';

  $stmt = $conn->prepare("INSERT INTO classes (grade, section, type, class_teacher) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssss", $grade, $section, $class_type, $class_teacher);
  $stmt->execute();

  $class_id = $conn->insert_id;

  $stmt = $conn->prepare("INSERT INTO sections (class_id, section_name) VALUES (?, ?)");
  $stmt->bind_param("is", $class_id, $section);
  $stmt->execute();
}

$class_result = $conn->query("SELECT * FROM classes ORDER BY grade ASC");

$students = $subjects = null;
$selected_class_id = $_GET['class_id'] ?? null;
if ($selected_class_id) {
  $stmt = $conn->prepare("SELECT * FROM students WHERE grade = ?");
  $stmt->bind_param("s", $selected_class_id);
  $stmt->execute();
  $students = $stmt->get_result();

  $stmt = $conn->prepare("SELECT s.*, t.full_name as teacher_name FROM subjects s 
                          JOIN teachers t ON s.teacher_id = t.id WHERE s.class_id = ?");
  $stmt->bind_param("i", $selected_class_id);
  $stmt->execute();
  $subjects = $stmt->get_result();

  // Get the selected class data for display
  $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
  $stmt->bind_param("i", $selected_class_id);
  $stmt->execute();
  $class_data = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Classes</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow space-y-8">
  <div class="flex justify-between items-center">
    <h1 class="text-3xl font-bold text-gray-800">📘 Manage Classes</h1>
    <button onclick="document.getElementById('addModal').classList.remove('hidden')"
      class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg">+ Add Class</button>
  </div>

  <form method="GET" class="flex space-x-4">
    <select name="class_id" class="border px-4 py-2 rounded w-64">
      <option value="">-- Select a Class --</option>
      <?php while ($class = $class_result->fetch_assoc()): ?>
        <option value="<?= $class['id'] ?>" <?= $selected_class_id == $class['id'] ? 'selected' : '' ?>>
          Grade <?= $class['grade'] ?> - <?= $class['section'] ?>
        </option>
      <?php endwhile; ?>
    </select>
    <button class="bg-green-600 text-white px-4 py-2 rounded">Load</button>
  </form>

  <?php if ($selected_class_id && $class_data): ?>
  <div>
    <div class="border-b border-gray-200 mb-4">
      <nav class="-mb-px flex space-x-8" id="tabs">
        <button class="tab-btn text-gray-500 py-2 px-4 border-b-2" onclick="openTab('students')">Students</button>
        <button class="tab-btn text-gray-500 py-2 px-4 border-b-2" onclick="openTab('subjects')">Subjects</button>
        <button class="tab-btn text-gray-500 py-2 px-4 border-b-2" onclick="openTab('teacher')">Class Teacher</button>
        <button class="tab-btn text-gray-500 py-2 px-4 border-b-2" onclick="openTab('sections')">Sections</button>
      </nav>
    </div>

    <div id="students" class="tab-content hidden">
      <div class="flex justify-between mb-2">
        <h2 class="text-xl font-semibold">👩‍🎓 Students</h2>
        <button class="bg-blue-500 text-white px-3 py-1 rounded">+ Add Student</button>
      </div>
      <?php if ($students && $students->num_rows > 0): ?>
        <ul class="space-y-2">
          <?php while ($s = $students->fetch_assoc()): ?>
            <li class="flex justify-between items-center bg-gray-50 p-3 rounded">
              <span><?= htmlspecialchars($s['full_name']) ?> (<?= $s['gender'] ?>)</span>
              <div class="space-x-2">
                <button class="text-blue-600">Edit</button>
                <button class="text-red-600">Delete</button>
              </div>
            </li>
          <?php endwhile; ?>
        </ul>
      <?php else: ?>
        <p class="text-gray-500">No students found.</p>
      <?php endif; ?>
    </div>

    <div id="subjects" class="tab-content hidden">
      <div class="flex justify-between mb-2">
        <h2 class="text-xl font-semibold">📚 Subjects</h2>
        <button class="bg-blue-500 text-white px-3 py-1 rounded">+ Add Subject</button>
      </div>
      <?php if ($subjects && $subjects->num_rows > 0): ?>
        <table class="w-full text-sm border mt-2">
          <thead class="bg-gray-100">
            <tr>
              <th class="text-left py-2 px-4">Subject</th>
              <th class="text-left py-2 px-4">Teacher</th>
              <th class="text-left py-2 px-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($sub = $subjects->fetch_assoc()): ?>
              <tr class="border-t">
                <td class="py-2 px-4"><?= htmlspecialchars($sub['name']) ?></td>
                <td class="py-2 px-4"><?= htmlspecialchars($sub['teacher_name']) ?></td>
                <td class="py-2 px-4 space-x-2">
                  <button class="text-blue-600">Edit</button>
                  <button class="text-red-600">Remove</button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-500">No subjects assigned.</p>
      <?php endif; ?>
    </div>

    <div id="teacher" class="tab-content hidden">
      <h2 class="text-xl font-semibold mb-2">👨‍🏫 Class Teacher</h2>
      <p class="text-gray-700">Currently assigned: <strong><?= htmlspecialchars($class_data['class_teacher'] ?? 'N/A') ?></strong></p>
      <button class="mt-2 bg-blue-500 text-white px-3 py-1 rounded">Change Teacher</button>
    </div>

    <div id="sections" class="tab-content hidden">
      <h2 class="text-xl font-semibold mb-2">🏷️ Sections</h2>
      <p>Section: <strong><?= htmlspecialchars($class_data['section'] ?? '-') ?></strong></p>
      <button class="mt-2 bg-blue-500 text-white px-3 py-1 rounded">Edit Section</button>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden z-50">
  <div class="bg-white rounded-lg p-6 w-full max-w-lg">
    <h2 class="text-lg font-bold mb-4 text-gray-700">Add New Class</h2>
    <form method="POST">
      <input type="hidden" name="add_class" value="1" />
      <div class="grid grid-cols-2 gap-4">
        <input type="text" name="grade" placeholder="Grade" required class="border px-4 py-2 rounded" />
        <input type="text" name="section" placeholder="Section" required class="border px-4 py-2 rounded" />
        <select name="class_type" required class="border px-4 py-2 rounded col-span-2">
          <option value="">-- Select Class Type --</option>
          <option value="Pre-Primary">Pre-Primary</option>
          <option value="Primary">Primary</option>
          <option value="Secondary">Secondary</option>
        </select>
        <select name="class_teacher_id" required class="border px-4 py-2 rounded col-span-2">
          <option value="">-- Select Class Teacher --</option>
          <?php $teacher_result->data_seek(0); while ($t = $teacher_result->fetch_assoc()): ?>
            <option value="<?= $t['id'] ?>"><?= $t['full_name'] ?> (<?= $t['username'] ?>)</option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="flex justify-end mt-4">
        <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
          class="bg-gray-300 px-4 py-2 rounded mr-2">Cancel</button>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Add</button>
      </div>
    </form>
  </div>
</div>

<script>
function openTab(id) {
  document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('border-blue-500', 'text-blue-600'));
  const button = document.querySelector(`[onclick="openTab('${id}')"]`);
  const tab = document.getElementById(id);
  if (tab && button) {
    tab.classList.remove('hidden');
    button.classList.add('border-blue-500', 'text-blue-600');
  }
}
<?php if ($selected_class_id): ?>
  window.addEventListener('DOMContentLoaded', () => openTab('students'));
<?php endif; ?>
</script>
</body>
</html>
