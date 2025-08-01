<?php
include '../partials/dbconnect.php';

// Start session if not already
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Check teacher session
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
  header("Location: ../index.php");
  exit;
}

$teacher_username = $_SESSION['username'];

// Get teacher info (with school_id)
$stmt = $conn->prepare("SELECT id, full_name, school_id FROM teachers WHERE username = ?");
$stmt->bind_param("s", $teacher_username);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

if (!$teacher) {
  die("Teacher not found.");
}

$teacher_id = $teacher['id'];
$teacher_name = $teacher['full_name'];
$school_id = $teacher['school_id'];

// Fetch all classes and subjects assigned to this teacher (restricted by school)
$stmt = $conn->prepare("
    SELECT 
      c.id AS class_id, c.grade, c.section, c.type,
      s.id AS subject_id, s.name AS subject_name
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.teacher_id = ? AND c.school_id = ?
    ORDER BY c.grade ASC, c.section ASC, s.name ASC
");
$stmt->bind_param("ii", $teacher_id, $school_id);
$stmt->execute();
$assignedResult = $stmt->get_result();

// Save result to array (since we loop twice)
$assigned = $assignedResult->fetch_all(MYSQLI_ASSOC);

// Collect class IDs
$classIds = array_unique(array_column($assigned, 'class_id'));

// Fetch exams for those classes, restricted by school_id
$examsByClass = [];
if (!empty($classIds)) {
  $placeholders = implode(',', array_fill(0, count($classIds), '?'));
  $types = str_repeat('i', count($classIds)) . 'i'; // all class IDs + school_id

  $sql = "SELECT * FROM exams 
        WHERE (class_id IN ($placeholders) OR class_id IS NULL) 
          AND school_id = ? 
        ORDER BY class_id, created_at DESC";

  $stmtExams = $conn->prepare($sql);

  // Merge params (class IDs + school_id)
  $params = array_merge($classIds, [$school_id]);
  $stmtExams->bind_param($types, ...$params);

  $stmtExams->execute();
  $examsResult = $stmtExams->get_result();

  while ($exam = $examsResult->fetch_assoc()) {
    if ($exam['class_id'] === null) {
        // This is a global exam – assign to all teacher classes
        foreach ($classIds as $cid) {
            $examsByClass[$cid][] = $exam;
        }
    } else {
        $examsByClass[$exam['class_id']][] = $exam;
    }
}

}

// Group subjects by class_id
$subjectsByClass = [];
foreach ($assigned as $row) {
  $subjectsByClass[$row['class_id']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Teacher Exams & Marks Entry</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow space-y-8 mt-6">
    <h1 class="text-3xl font-bold text-gray-800">👨‍🏫 Welcome, <?= htmlspecialchars($teacher_name) ?></h1>
    <h2 class="text-xl font-semibold text-gray-700">Your Assigned Classes, Subjects & Exams</h2>

    <?php if (empty($assigned)): ?>
      <p class="text-gray-600 mt-4">You are not assigned to any classes or subjects.</p>
    <?php else: ?>
      <?php foreach ($subjectsByClass as $class_id => $subjects): ?>
        <?php
        $classInfo = $subjects[0]; // contains grade, section, type
        $exams = $examsByClass[$class_id];
        ?>
        <div class="border rounded-lg shadow p-4 bg-gray-50">
          <h3 class="text-xl font-semibold text-blue-800 mb-2">
            Grade <?= htmlspecialchars($classInfo['grade']) ?> - <?= htmlspecialchars($classInfo['section']) ?>
            (<?= htmlspecialchars($classInfo['type']) ?>)
          </h3>

          <p class="font-semibold mb-2">Subjects assigned to you in this class:</p>
          <ul class="mb-4 list-disc list-inside">
            <?php foreach ($subjects as $sub): ?>
              <li><?= htmlspecialchars($sub['subject_name']) ?></li>
            <?php endforeach; ?>
          </ul>

          <?php if (empty($exams)): ?>
            <p class="italic text-gray-500">No exams scheduled for this class yet.</p>
          <?php else: ?>
            <p class="font-semibold mb-2">Exams:</p>
            <ul class="space-y-2">
              <?php foreach ($exams as $exam): ?>
                <li class="p-3 bg-white border rounded flex justify-between items-center">
                  <div>
                    <strong class="text-lg"><?= htmlspecialchars($exam['exam_name']) ?></strong><br />
                    <span class="text-sm text-gray-600">
                      Type: <?= htmlspecialchars($exam['exam_type']) ?> | Created:
                      <?= date('M d, Y', strtotime($exam['created_at'])) ?>
                    </span>
                  </div>
                  <div class="flex gap-2">
                    <?php foreach ($subjects as $sub): ?>
                      <button
                        onclick="openMarksModal(<?= $exam['id'] ?>, <?= $class_id ?>, <?= $sub['subject_id'] ?>, '<?= htmlspecialchars(addslashes($sub['subject_name'])) ?>')"
                        class="bg-green-600 text-white px-4 py-1 rounded hover:bg-green-700 text-sm whitespace-nowrap">
                        Marks - <?= htmlspecialchars($sub['subject_name']) ?>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Marks Modal -->
  <div id="marksModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center"
    onclick="closeMarksModal(event)">
    <div onclick="event.stopPropagation()"
      class="bg-white w-full max-w-3xl p-6 rounded-lg shadow-lg overflow-y-auto max-h-[90vh]">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-2xl font-bold text-blue-800">
          Enter Marks for <span id="modalSubjectName"></span>
        </h2>
        <button onclick="closeMarksModal()" class="text-gray-500 text-2xl">&times;</button>
      </div>

      <p id="fullMarksDisplay" class="text-lg font-semibold text-gray-700 mb-4"></p>

      <form id="marksForm" method="POST" action="submit_marks.php">
        <input type="hidden" name="exam_id" id="modalExamId" />
        <input type="hidden" name="class_id" id="modalClassId" />
        <input type="hidden" name="subject_id" id="modalSubjectId" />

        <div id="marksStudentList" class="space-y-4 max-h-96 overflow-y-auto border p-2 rounded bg-gray-50"></div>

        <div class="mt-6 text-right">
          <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">✅ Submit
            Marks</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openMarksModal(examId, classId, subjectId, subjectName) {
      document.getElementById('modalExamId').value = examId;
      document.getElementById('modalClassId').value = classId;
      document.getElementById('modalSubjectId').value = subjectId;
      document.getElementById('modalSubjectName').textContent = subjectName;

      const listContainer = document.getElementById('marksStudentList');
      const fullMarksDisplay = document.getElementById('fullMarksDisplay');

      listContainer.innerHTML = 'Loading students...';
      fullMarksDisplay.textContent = '';

      fetch(`get_students_for_marks.php?class_id=${classId}&exam_id=${examId}&subject_id=${subjectId}`)
        .then(res => res.json())
        .then(data => {
          if (!data.success) {
            alert('❌ Error: ' + data.error);
            listContainer.innerHTML = '<p class="text-red-500">Failed to load students.</p>';
            return;
          }

          if (data.full_marks) {
            fullMarksDisplay.textContent = `Full Marks: ${data.full_marks}`;
          }

          if (data.students.length === 0) {
            listContainer.innerHTML = '<p class="text-red-500">No students found for this class.</p>';
            return;
          }

          listContainer.innerHTML = '';
          data.students.forEach(student => {
            const div = document.createElement('div');
            div.className = 'flex items-center gap-4';
            div.innerHTML = `
              <input type="hidden" name="student_id[]" value="${student.id}" />
              <label class="w-1/2 font-medium text-gray-700">${student.full_name}</label>
              <input type="number" name="marks[]" value="${student.marks ?? ''}" 
                min="0" max="${data.full_marks ?? 100}" 
                placeholder="Enter marks" required 
                class="w-1/2 px-3 py-2 border rounded" />
            `;
            listContainer.appendChild(div);
          });
        })
        .catch(err => {
          alert('❌ Error loading students: ' + err.message);
          listContainer.innerHTML = '<p class="text-red-500">Failed to load students.</p>';
        });

      document.getElementById('marksModal').classList.remove('hidden');
    }

    function closeMarksModal(e) {
      if (!e || e.target.id === 'marksModal') {
        document.getElementById('marksModal').classList.add('hidden');
      }
    }
  </script>
</body>

</html>