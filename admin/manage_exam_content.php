<?php
include '../partials/dbconnect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = $_POST['exam_name'];
    $class_id = $_POST['class_id'];
    $exam_type = 'Terminal'; // Fixed as requested

    // Insert into exams
    $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, class_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $exam_name, $exam_type, $class_id);
    $stmt->execute();
    $exam_id = $stmt->insert_id;

    // Insert subject marks
    $subjects = $_POST['subject_id'] ?? [];
    $full_marks = $_POST['full_marks'] ?? [];
    $pass_marks = $_POST['pass_marks'] ?? [];

    if (!empty($subjects) && is_array($subjects)) {
        for ($i = 0; $i < count($subjects); $i++) {
            $sub_id = $subjects[$i];
            $full = $full_marks[$i];
            $pass = $pass_marks[$i];

            $stmt = $conn->prepare("INSERT INTO exam_subjects (exam_id, subject_id, full_marks, pass_marks) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiii", $exam_id, $sub_id, $full, $pass);
            $stmt->execute();
        }
    }

    echo "<script>alert('✅ Exam created successfully'); window.location='manage_exams.php';</script>";
    exit;
}
?>

<!-- Frontend -->
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
        <?php while ($row = $class_result->fetch_assoc()): ?>
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
