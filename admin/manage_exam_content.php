<?php
include '../partials/dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $exam_name = $_POST['exam_name'] ?? '';
        $class_id = $_POST['class_id'] ?? '';
        $exam_type = 'Terminal';

        if (empty($exam_name) || empty($class_id)) {
            echo "<script>alert('Please fill all required fields.'); window.history.back();</script>";
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, class_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $exam_name, $exam_type, $class_id);

        if ($stmt->execute()) {
            header("Location: manage_exams.php");
            exit;
        } else {
            echo "<script>alert('Error adding exam. Please try again.'); window.history.back();</script>";
            exit;
        }
    } elseif ($action === 'edit') {
        $exam_id = $_POST['exam_id'] ?? 0;
        $name = $_POST['exam_name'] ?? '';
        $class = $_POST['class_id'] ?? '';

        if (empty($exam_id) || empty($name) || empty($class)) {
            echo "<script>alert('Please fill all required fields.'); window.history.back();</script>";
            exit;
        }

        $stmt = $conn->prepare("UPDATE exams SET exam_name = ?, class_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $class, $exam_id);

        if ($stmt->execute()) {
            header("Location: manage_exams.php");
            exit;
        } else {
            echo "<script>alert('Error updating exam. Please try again.'); window.history.back();</script>";
            exit;
        }
    } elseif ($action === 'delete') {
        $exam_id = $_POST['exam_id'] ?? 0;

        if (empty($exam_id)) {
            echo "<script>alert('Invalid exam selected.'); window.history.back();</script>";
            exit;
        }

        $stmt1 = $conn->prepare("DELETE FROM exam_subjects WHERE exam_id = ?");
        $stmt1->bind_param("i", $exam_id);
        $stmt1->execute();

        $stmt2 = $conn->prepare("DELETE FROM exams WHERE id = ?");
        $stmt2->bind_param("i", $exam_id);

        if ($stmt2->execute()) {
            header("Location: manage_exams.php");
            exit;
        } else {
            echo "<script>alert('Error deleting exam. Please try again.'); window.history.back();</script>";
            exit;
        }
    } else {
        header("Location: manage_exams.php");
        exit;
    }
}

// Fetch exams with class info
$exams = $conn->query("
  SELECT exams.*, classes.grade, classes.section 
  FROM exams 
  JOIN classes ON exams.class_id = classes.id
  ORDER BY exams.id DESC
");

// Fetch all classes for dropdown
$class_result = $conn->query("SELECT * FROM classes ORDER BY grade ASC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Manage Exams</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

<!-- Exams List -->
<div class="bg-white p-6 rounded-lg shadow max-w-6xl mx-auto mb-8">
  <div class="flex justify-between items-center mb-4">
    <h1 class="text-2xl font-bold text-gray-800">📋 Exams List</h1>
    <button onclick="document.getElementById('examModal').classList.remove('hidden')"
      class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">➕ Add Exam</button>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full table-auto border-collapse border">
      <thead>
        <tr class="bg-gray-200 text-left">
          <th class="p-3 border">#</th>
          <th class="p-3 border">Exam Name</th>
          <th class="p-3 border">Class</th>
          <th class="p-3 border">Type</th>
          <th class="p-3 border">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($exams->num_rows > 0):
          $i = 1;
          while ($row = $exams->fetch_assoc()): ?>
            <tr class="hover:bg-gray-100">
              <td class="p-3 border"><?= $i++ ?></td>
              <td class="p-3 border"><?= htmlspecialchars($row['exam_name']) ?></td>
              <td class="p-3 border">Grade <?= $row['grade'] ?> - <?= $row['section'] ?></td>
              <td class="p-3 border"><?= htmlspecialchars($row['exam_type']) ?></td>
              <td class="p-3 border space-x-2">
                <button onclick='openEditModal(<?= json_encode($row) ?>)'
                  class="px-3 py-1 bg-yellow-400 text-white rounded hover:bg-yellow-500 text-sm">
                  ✏️ Edit
                </button>
                <button onclick='openDeleteModal(<?= $row["id"] ?>, "<?= addslashes($row["exam_name"]) ?>")'
                  class="px-3 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-sm">
                  🗑️ Delete
                </button>
              </td>
            </tr>
          <?php endwhile; else: ?>
          <tr>
            <td colspan="5" class="text-center text-gray-500 p-4">No exams found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Exam Modal -->
<div id="examModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center" onclick="closeModal(event)">
  <div onclick="event.stopPropagation();" class="bg-white max-w-2xl w-full p-6 rounded-xl shadow-xl overflow-y-auto max-h-[90vh]">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-gray-800">📝 Create Terminal Exam</h2>
      <button onclick="document.getElementById('examModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-800 text-xl">&times;</button>
    </div>

    <form method="POST" id="examForm">
      <input type="hidden" name="action" value="add" />
      <div class="mb-4">
        <label class="block font-medium mb-1">Exam Name</label>
        <input type="text" name="exam_name" placeholder="e.g., First Terminal" required class="w-full border px-4 py-2 rounded" />
      </div>

      <div class="mb-4">
        <label class="block font-medium mb-1">Select Class</label>
        <select name="class_id" id="class_id" onchange="fetchSubjects()" required class="w-full border px-4 py-2 rounded">
          <option value="">-- Select Class --</option>
          <?php $class_result->data_seek(0);
          while ($row = $class_result->fetch_assoc()): ?>
            <option value="<?= $row['id'] ?>">Grade <?= $row['grade'] ?> - <?= $row['section'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div id="subjectContainer" class="space-y-4 hidden">
        <h2 class="text-lg font-semibold text-gray-700">📚 Subjects</h2>
        <div id="subjectRows"></div>
      </div>

      <div class="mt-6 flex justify-end">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">✅ Create Exam</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Exam Modal -->
<div id="editExamModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center" onclick="closeEditModal(event)">
  <div onclick="event.stopPropagation();" class="bg-white max-w-2xl w-full p-6 rounded-xl shadow-xl overflow-y-auto max-h-[90vh]">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-gray-800">✏️ Edit Exam</h2>
      <button onclick="document.getElementById('editExamModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-800 text-xl">&times;</button>
    </div>
    <form method="POST" action="">
      <input type="hidden" name="action" value="edit" />
      <input type="hidden" name="exam_id" id="edit_exam_id" />
      <div class="mb-4">
        <label class="block mb-1 font-medium">Exam Name</label>
        <input type="text" name="exam_name" id="edit_exam_name" required class="w-full border px-4 py-2 rounded" />
      </div>
      <div class="mb-4">
        <label class="block mb-1 font-medium">Class</label>
        <select name="class_id" id="edit_class_id" required class="w-full border px-4 py-2 rounded">
          <option value="">-- Select Class --</option>
          <?php $class_result->data_seek(0); while ($row = $class_result->fetch_assoc()): ?>
            <option value="<?= $row['id'] ?>">Grade <?= $row['grade'] ?> - <?= $row['section'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="bg-yellow-500 text-white px-6 py-2 rounded hover:bg-yellow-600">✅ Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Exam Modal -->
<div id="deleteExamModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center" onclick="closeDeleteModal(event)">
  <div onclick="event.stopPropagation();" class="bg-white max-w-md w-full p-6 rounded-xl shadow-xl">
    <h2 class="text-xl font-bold mb-4 text-gray-800">❌ Confirm Delete</h2>
    <p id="deleteExamText" class="mb-6 text-gray-700">Are you sure you want to delete this exam?</p>
    <form method="POST" action="" class="flex justify-end gap-4">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="exam_id" id="delete_exam_id" />
      <button type="button" onclick="document.getElementById('deleteExamModal').classList.add('hidden')" class="px-4 py-2 bg-gray-300 text-gray-700 rounded">Cancel</button>
      <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
    </form>
  </div>
</div>

<script>
  function closeModal(event) {
    if (event.target.id === 'examModal') {
      document.getElementById('examModal').classList.add('hidden');
    }
  }

  function fetchSubjects() {
    const classId = document.getElementById("class_id").value;
    const container = document.getElementById("subjectContainer");
    const rows = document.getElementById("subjectRows");

    if (!classId) {
      container.classList.add("hidden");
      rows.innerHTML = '';
      return;
    }

    fetch(`get_subjects.php?class_id=${classId}`)
      .then(res => res.json())
      .then(data => {
        if (Array.isArray(data) && data.length > 0) {
          container.classList.remove("hidden");
          rows.innerHTML = '';
          data.forEach(sub => {
            rows.innerHTML += `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <input type="hidden" name="subject_id[]" value="${sub.id}">
              <input type="text" value="${sub.name}" disabled class="w-full px-4 py-2 bg-gray-100 border rounded" />
              <input type="number" name="full_marks[]" placeholder="Full Marks" required class="w-full border px-4 py-2 rounded" />
              <input type="number" name="pass_marks[]" placeholder="Pass Marks" required class="w-full border px-4 py-2 rounded" />
            </div>`;
          });
        } else {
          container.classList.add("hidden");
          rows.innerHTML = '<p class="text-red-500">No subjects found.</p>';
        }
      });
  }

  function openEditModal(exam) {
    document.getElementById('edit_exam_id').value = exam.id;
    document.getElementById('edit_exam_name').value = exam.exam_name;
    document.getElementById('edit_class_id').value = exam.class_id;
    document.getElementById('editExamModal').classList.remove('hidden');
  }

  function openDeleteModal(id, name) {
    document.getElementById('delete_exam_id').value = id;
    document.getElementById('deleteExamText').textContent = `Are you sure you want to delete exam "${name}"?`;
    document.getElementById('deleteExamModal').classList.remove('hidden');
  }

  function closeEditModal(e) {
    if (e.target.id === 'editExamModal') {
      document.getElementById('editExamModal').classList.add('hidden');
    }
  }

  function closeDeleteModal(e) {
    if (e.target.id === 'deleteExamModal') {
      document.getElementById('deleteExamModal').classList.add('hidden');
    }
  }
</script>

</body>
</html>
