<?php
include '../partials/dbconnect.php';

$user_type = $_SESSION['user_type'] ?? '';
$school_id = $_SESSION['school_id'] ?? 0;

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $exam_name = $_POST['exam_name'] ?? '';
    $class_id = $_POST['class_id'] ?? '';
    $exam_type = 'Terminal';
    $subject_ids = $_POST['subject_id'] ?? [];
    $full_marks = $_POST['full_marks'] ?? [];
    $pass_marks = $_POST['pass_marks'] ?? [];

    if (empty($exam_name) || empty($class_id) || empty($subject_ids)) {
      echo "<script>alert('Please fill all required fields.'); window.history.back();</script>";
      exit;
    }

    if ($class_id === 'all') {
      $marksMap = [];
      foreach ($subject_ids as $i => $sid) {
        $res = $conn->query("SELECT name FROM subjects WHERE id = " . (int)$sid);
        if ($row = $res->fetch_assoc()) {
          $marksMap[$row['name']] = [
            'full' => (int)$full_marks[$i],
            'pass' => (int)$pass_marks[$i]
          ];
        }
      }

      $class_q = $conn->query("SELECT id FROM classes " . ($user_type !== 'superadmin' ? "WHERE school_id = $school_id" : ""));
      while ($cls = $class_q->fetch_assoc()) {
        $cid = $cls['id'];
        $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, class_id, school_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssii", $exam_name, $exam_type, $cid, $school_id);
        $stmt->execute();
        $exam_id = $stmt->insert_id;

        $sub_q = $conn->query("SELECT id, name FROM subjects WHERE class_id = $cid");
        while ($sub = $sub_q->fetch_assoc()) {
          $sid = $sub['id'];
          $name = $sub['name'];
          $fm = $marksMap[$name]['full'] ?? 0;
          $pm = $marksMap[$name]['pass'] ?? 0;

          $ins = $conn->prepare("INSERT INTO exam_subjects (exam_id, subject_id, full_marks, pass_marks) VALUES (?, ?, ?, ?)");
          $ins->bind_param("iiii", $exam_id, $sid, $fm, $pm);
          $ins->execute();
        }
      }
    } else {
      $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, class_id, school_id, created_at) VALUES (?, ?, ?, ?, NOW())");
      $stmt->bind_param("ssii", $exam_name, $exam_type, $class_id, $school_id);
      $stmt->execute();
      $exam_id = $stmt->insert_id;

      foreach ($subject_ids as $i => $sid) {
        $fm = (int)$full_marks[$i];
        $pm = (int)$pass_marks[$i];
        $ins = $conn->prepare("INSERT INTO exam_subjects (exam_id, subject_id, full_marks, pass_marks) VALUES (?, ?, ?, ?)");
        $ins->bind_param("iiii", $exam_id, $sid, $fm, $pm);
        $ins->execute();
      }
    }
    header("Location: manage_exams.php");
    exit;
  }

  elseif ($action === 'edit') {
    $exam_id = $_POST['exam_id'] ?? 0;
    $name = $_POST['exam_name'] ?? '';
    $class_id = $_POST['class_id'] ?? '';
    $subject_ids = $_POST['subject_id'] ?? [];
    $full_marks = $_POST['full_marks'] ?? [];
    $pass_marks = $_POST['pass_marks'] ?? [];

    if (empty($exam_id) || empty($name) || empty($class_id)) {
      echo "<script>alert('Missing data.'); window.history.back();</script>";
      exit;
    }

    $stmt = $conn->prepare("UPDATE exams SET exam_name = ?, class_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $name, $class_id, $exam_id);
    $stmt->execute();

    $conn->query("DELETE FROM exam_subjects WHERE exam_id = " . (int)$exam_id);

    foreach ($subject_ids as $i => $sid) {
      $fm = (int)$full_marks[$i];
      $pm = (int)$pass_marks[$i];
      $ins = $conn->prepare("INSERT INTO exam_subjects (exam_id, subject_id, full_marks, pass_marks) VALUES (?, ?, ?, ?)");
      $ins->bind_param("iiii", $exam_id, $sid, $fm, $pm);
      $ins->execute();
    }
    header("Location: manage_exams.php");
    exit;
  }

  elseif ($action === 'delete') {
    $exam_id = $_POST['exam_id'] ?? 0;
    $conn->query("DELETE FROM exam_subjects WHERE exam_id = " . (int)$exam_id);
    $conn->query("DELETE FROM exams WHERE id = " . (int)$exam_id);
    header("Location: manage_exams.php");
    exit;
  }
}

$class_filter = $_GET['filter_class'] ?? 'all';
$where = ($class_filter !== 'all') ? "AND classes.id = " . (int)$class_filter : '';
$exams = $conn->query("
  SELECT exams.*, classes.grade, classes.section 
  FROM exams 
  JOIN classes ON exams.class_id = classes.id
  " . ($user_type !== 'superadmin' ? "WHERE exams.school_id = $school_id $where" : ($where ? "WHERE 1=1 $where" : "")) . "
  ORDER BY exams.id DESC
");
$class_result = $conn->query("SELECT * FROM classes " . ($user_type !== 'superadmin' ? "WHERE school_id = $school_id" : "") . " ORDER BY grade ASC");
$all_subjects = $conn->query("SELECT MIN(id) AS id, name FROM subjects " . ($user_type !== 'superadmin' ? "WHERE school_id = $school_id" : "") . " GROUP BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Manage Exams</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

  <!-- Filter -->
  <div class="mb-4 max-w-6xl mx-auto">
    <label class="font-medium text-gray-700">Filter by Class:</label>
    <select name="filter_class" id="filterClass" class="border rounded px-3 py-1">
      <option value="all" <?= $class_filter === 'all' ? 'selected' : '' ?>>All Classes</option>
      <?php $class_result->data_seek(0);
      while ($row = $class_result->fetch_assoc()): ?>
        <option value="<?= $row['id'] ?>" <?= $class_filter == $row['id'] ? 'selected' : '' ?>>
          Grade <?= $row['grade'] ?> - <?= $row['section'] ?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>

  <!-- Exams Table -->
  <div class="bg-white p-6 rounded-lg shadow max-w-6xl mx-auto mb-8">
    <div class="flex justify-between items-center mb-4">
      <h1 class="text-2xl font-bold">📋 Exams List</h1>
      <button onclick="openExamModal()" class="bg-blue-600 text-white px-4 py-2 rounded">➕ Add Exam</button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full table-auto border-collapse border">
        <thead>
          <tr class="bg-gray-200 text-left">
            <th class="p-3 border">S.No.</th>
            <th class="p-3 border">Exam Name</th>
            <th class="p-3 border">Class</th>
            <th class="p-3 border">Type</th>
            <th class="p-3 border">Actions</th>
          </tr>
        </thead>
        <tbody id="examTableBody">
          <?php if ($exams->num_rows > 0): $i=1; while ($row=$exams->fetch_assoc()): ?>
            <tr class="hover:bg-gray-100">
              <td class="p-3 border"><?= $i++ ?></td>
              <td class="p-3 border"><?= htmlspecialchars($row['exam_name']) ?></td>
              <td class="p-3 border">Grade <?= $row['grade'] ?> - <?= $row['section'] ?></td>
              <td class="p-3 border"><?= htmlspecialchars($row['exam_type']) ?></td>
              <td class="p-3 border">
                <button onclick='openEditModal(<?= json_encode($row) ?>)' class="px-3 py-1 bg-yellow-400 text-white rounded">✏️</button>
                <button onclick='openDeleteModal(<?= $row["id"] ?>, "<?= addslashes($row["exam_name"]) ?>")' class="px-3 py-1 bg-red-500 text-white rounded">🗑️</button>
              </td>
            </tr>
          <?php endwhile; else: ?>
            <tr><td colspan="5" class="text-center p-4 text-gray-500">No exams found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add Exam Modal -->
  <div id="examModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center"
    onclick="closeModal(event)">
    <div onclick="event.stopPropagation();"
      class="bg-white max-w-3xl w-full p-6 rounded-xl shadow-xl overflow-y-auto max-h-[90vh]">
      <h2 class="text-xl font-bold mb-4">➕ Create Terminal Exam</h2>
      <form method="POST">
        <input type="hidden" name="action" value="add" />
        <div class="mb-4">
          <label class="block font-medium">Exam Name</label>
          <input type="text" name="exam_name" required class="w-full border px-3 py-2 rounded" />
        </div>
        <div class="mb-4">
          <label class="block font-medium">Select Class</label>
          <select name="class_id" id="class_id" class="w-full border px-3 py-2 rounded" onchange="loadSubjects()">
            <option value="">-- Select --</option>
            <option value="all">All Classes</option>
            <?php $class_result->data_seek(0);
            while ($row = $class_result->fetch_assoc()): ?>
              <option value="<?= $row['id'] ?>">Grade <?= $row['grade'] ?> - <?= $row['section'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div id="subjectContainer" class="space-y-3 hidden"></div>
        <div class="text-right mt-4">
          <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded">Create</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editExamModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center"
    onclick="closeEditModal(event)">
    <div onclick="event.stopPropagation();"
      class="bg-white max-w-3xl w-full p-6 rounded-xl shadow-xl overflow-y-auto max-h-[90vh]">
      <h2 class="text-xl font-bold mb-4">✏️ Edit Exam</h2>
      <form method="POST" id="editExamForm">
        <input type="hidden" name="action" value="edit" />
        <input type="hidden" name="exam_id" id="edit_exam_id" />
        <div class="mb-4">
          <label class="block font-medium">Exam Name</label>
          <input type="text" name="exam_name" id="edit_exam_name" required class="w-full border px-3 py-2 rounded" />
        </div>
        <div class="mb-4">
          <label class="block font-medium">Select Class</label>
          <select name="class_id" id="edit_class_id" class="w-full border px-3 py-2 rounded"
            onchange="loadEditSubjects()">
            <option value="">-- Select --</option>
            <?php $class_result->data_seek(0);
            while ($row = $class_result->fetch_assoc()): ?>
              <option value="<?= $row['id'] ?>">Grade <?= $row['grade'] ?> - <?= $row['section'] ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div id="editSubjectContainer" class="space-y-3"></div>
        <div class="text-right mt-4">
          <button type="submit" class="px-5 py-2 bg-yellow-500 text-white rounded">Update</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteExamModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center"
    onclick="closeDeleteModal(event)">
    <div onclick="event.stopPropagation();" class="bg-white max-w-md w-full p-6 rounded-xl shadow-xl">
      <h2 class="text-xl font-bold mb-4 text-red-600">🗑️ Confirm Delete</h2>
      <p>Are you sure you want to delete the exam: <strong id="deleteExamName"></strong>?</p>
      <form method="POST" class="mt-6" id="deleteExamForm">
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" name="exam_id" id="delete_exam_id" />
        <div class="flex justify-end gap-4">
          <button type="button" onclick="closeDeleteModal()"
            class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openExamModal() {
      document.getElementById('examModal').classList.remove('hidden');
    }
    function closeModal(event) {
      if (!event || event.target.id === 'examModal') {
        document.getElementById('examModal').classList.add('hidden');
      }
    }
    function openEditModal(exam) {
      document.getElementById('editExamModal').classList.remove('hidden');
      document.getElementById('edit_exam_id').value = exam.id;
      document.getElementById('edit_exam_name').value = exam.exam_name;
      document.getElementById('edit_class_id').value = exam.class_id;
      loadEditSubjects(exam.id, exam.class_id);
    }
    function closeEditModal(event) {
      if (!event || event.target.id === 'editExamModal') {
        document.getElementById('editExamModal').classList.add('hidden');
      }
    }
    function openDeleteModal(id, name) {
      document.getElementById('deleteExamModal').classList.remove('hidden');
      document.getElementById('delete_exam_id').value = id;
      document.getElementById('deleteExamName').innerText = name;
    }
    function closeDeleteModal(event) {
      if (!event || event.target.id === 'deleteExamModal') {
        document.getElementById('deleteExamModal').classList.add('hidden');
      }
    }
    function loadEditSubjects(exam_id, class_id) {
      const container = document.getElementById('editSubjectContainer');
      container.innerHTML = '';
      fetch('fetch_exam_subjects.php?exam_id=' + exam_id)
        .then(res => res.json())
        .then(data => {
          let filtered = (class_id === "all")
            ? [...new Map(subjects.map(s => [s.name, s])).values()]
            : subjects.filter(s => s.class_id == class_id);
          filtered.forEach(s => {
            const found = data.find(d => d.subject_id == s.id) || { full_marks: '', pass_marks: '' };
            container.innerHTML += `
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input type="hidden" name="subject_id[]" value="${s.id}">
                <input type="text" value="${s.name}" disabled class="border px-2 py-1 bg-gray-100 rounded" />
                <input type="number" name="full_marks[]" value="${found.full_marks}" placeholder="Full Marks" required class="border px-2 py-1 rounded" />
                <input type="number" name="pass_marks[]" value="${found.pass_marks}" placeholder="Pass Marks" required class="border px-2 py-1 rounded" />
              </div>`;
          });
        });
    }
    const subjects = <?php
      $subs = $conn->query("SELECT id,name,class_id FROM subjects " . ($user_type !== 'superadmin' ? "WHERE school_id = $school_id" : "") . " ORDER BY name");
      $subs_array = [];
      while ($r = $subs->fetch_assoc()) { $subs_array[] = $r; }
      echo json_encode($subs_array);
    ?>;
    function loadSubjects() {
      const classId = document.getElementById("class_id").value;
      const container = document.getElementById("subjectContainer");
      container.innerHTML = "";
      if (!classId) { container.classList.add("hidden"); return; }
      let filtered = (classId === "all")
        ? [...new Map(subjects.map(s => [s.name, s])).values()]
        : subjects.filter(s => s.class_id == classId);
      if (filtered.length) {
        filtered.forEach(s => {
          container.innerHTML += `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <input type="hidden" name="subject_id[]" value="${s.id}">
              <input type="text" value="${s.name}" disabled class="border px-2 py-1 bg-gray-100 rounded" />
              <input type="number" name="full_marks[]" placeholder="Full Marks" required class="border px-2 py-1 rounded" />
              <input type="number" name="pass_marks[]" placeholder="Pass Marks" required class="border px-2 py-1 rounded" />
            </div>`;
        });
        container.classList.remove("hidden");
      } else { container.classList.add("hidden"); }
    }
    document.getElementById("filterClass").addEventListener("change", function() {
      const classId = this.value;
      window.location = "manage_exams.php?filter_class=" + classId;
    });
  </script>
</body>
</html>
