<?php
include '../partials/dbconnect.php';

// Fetch all classes
$class_result = $conn->query("SELECT * FROM classes ORDER BY grade ASC");

// Handle exam creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = $_POST['exam_name'];
    $selected_class_id = $_POST['class_id'];
    $exam_type = 'Terminal'; // Fixed type

    // Gather subject data
    $subjects = $_POST['subject_id'] ?? [];
    $full_marks = $_POST['full_marks'] ?? [];
    $pass_marks = $_POST['pass_marks'] ?? [];

    // Determine target classes
    $class_ids = [];

    if ($selected_class_id === 'all') {
        $class_ids_result = $conn->query("SELECT id FROM classes");
        while ($row = $class_ids_result->fetch_assoc()) {
            $class_ids[] = $row['id'];
        }
    } else {
        $class_ids[] = (int)$selected_class_id;
    }

    // Loop through classes and insert exams and subjects
    foreach ($class_ids as $class_id) {
        $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, class_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $exam_name, $exam_type, $class_id);
        $stmt->execute();
        $exam_id = $stmt->insert_id;

        if (!empty($subjects) && is_array($subjects)) {
            for ($i = 0; $i < count($subjects); $i++) {
                $sub_id = $subjects[$i];
                $full = $full_marks[$i];
                $pass = $pass_marks[$i];

                $stmt_sub = $conn->prepare("INSERT INTO exam_subjects (exam_id, subject_id, full_marks, pass_marks) VALUES (?, ?, ?, ?)");
                $stmt_sub->bind_param("iiii", $exam_id, $sub_id, $full, $pass);
                $stmt_sub->execute();
            }
        }
    }

    echo "<script>alert('✅ Exam created successfully'); window.location='manage_exams.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Exams</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">
  <div class="bg-white p-6 rounded-lg shadow max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold mb-4 text-gray-800">📝 Create Terminal Exam</h1>
    <form method="POST" id="examForm">
      <div class="mb-4">
        <label class="block font-medium mb-1">Exam Name</label>
        <input type="text" name="exam_name" placeholder="e.g., First Terminal" required class="w-full border px-4 py-2 rounded" />
      </div>

      <div class="mb-4">
        <label class="block font-medium mb-1">Select Class</label>
        <select name="class_id" id="class_id" onchange="fetchSubjects()" required class="w-full border px-4 py-2 rounded">
          <option value="">-- Select Class --</option>
          <option value="all">🌐 All Classes</option>
          <?php
          $class_result = $conn->query("SELECT * FROM classes ORDER BY grade ASC");
          while ($row = $class_result->fetch_assoc()): ?>
            <option value="<?= $row['id'] ?>">Grade <?= $row['grade'] ?> - <?= $row['section'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>

      <div id="subjectContainer" class="space-y-4 hidden">
        <h2 class="text-lg font-semibold text-gray-700">📚 Subjects</h2>
        <div id="subjectRows"></div>
      </div>

      <button type="submit" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">➕ Create Exam</button>
    </form>
  </div>

<script>
function fetchSubjects() {
  const classId = document.getElementById("class_id").value;
  const container = document.getElementById("subjectContainer");
  const rows = document.getElementById("subjectRows");

  if (!classId) {
    container.classList.add("hidden");
    rows.innerHTML = '';
    return;
  }

  const fetchId = classId === 'all' ? 'first' : classId;

  fetch(`get_subjects.php?class_id=${fetchId}`)
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
</script>
</body>
</html>
