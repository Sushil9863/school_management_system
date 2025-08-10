<?php
include '../partials/dbconnect.php';

// 🔐 Ensure logged-in teacher
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
  header("Location: ../index.php");
  exit;
}

$teacher_username = $_SESSION['username'];

// 🔹 Get teacher info with school_id
$stmt = $conn->prepare("SELECT id, full_name, school_id FROM teachers WHERE username = ?");
$stmt->bind_param("s", $teacher_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Unauthorized: Teacher not found.");
}

$teacher = $result->fetch_assoc();
$teacher_id = $teacher['id'];
$teacher_name = $teacher['full_name'];
$school_id = $teacher['school_id'];

// 📚 Fetch assigned classes and subjects, also if teacher is class teacher for that class
// Use DISTINCT so classes not duplicated if multiple subjects
$query = "
    SELECT DISTINCT c.id as class_id, c.grade, c.section, c.type, 
           c.class_teacher_id, s.name AS subject_name
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.teacher_id = ? AND c.school_id = ?
    ORDER BY c.grade ASC, c.section ASC, s.name ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $teacher_id, $school_id);
$stmt->execute();
$assigned = $stmt->get_result();

// Organize data by class_id to group subjects
$classes = [];
while ($row = $assigned->fetch_assoc()) {
  $cid = $row['class_id'];
  if (!isset($classes[$cid])) {
    $classes[$cid] = [
      'grade' => $row['grade'],
      'section' => $row['section'],
      'type' => $row['type'],
      'class_teacher_id' => $row['class_teacher_id'],
      'subjects' => [],
    ];
  }
  $classes[$cid]['subjects'][] = $row['subject_name'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>My Classes</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    // Modal open function
    function openModal(classId) {
      const modal = document.getElementById('modal-' + classId);
      if (modal) {
        modal.classList.remove('hidden');
      }
    }

    // Modal close function
    function closeModal(classId) {
      const modal = document.getElementById('modal-' + classId);
      if (modal) {
        modal.classList.add('hidden');
      }
    }

    // Close modal on outside click
    function outsideClick(event, classId) {
      const modalContent = document.getElementById('modal-content-' + classId);
      if (!modalContent.contains(event.target)) {
        closeModal(classId);
      }
    }
  </script>
</head>

<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">👨‍🏫 Welcome, <?= htmlspecialchars($teacher_name) ?></h1>
    <h2 class="text-xl font-semibold text-gray-700 mb-6">📚 Your Assigned Classes & Subjects</h2>

    <?php if (!empty($classes)): ?>
      <table class="w-full text-left border border-collapse">
        <thead class="bg-gray-100">
          <tr>
            <th class="py-2 px-4 border">Grade</th>
            <th class="py-2 px-4 border">Section</th>
            <th class="py-2 px-4 border">Class Type</th>
            <th class="py-2 px-4 border">Subjects</th>
            <th class="py-2 px-4 border">Role</th>
            <th class="py-2 px-4 border">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($classes as $classId => $class): ?>
            <tr class="border-t hover:bg-gray-50">
              <td class="py-2 px-4 border"><?= htmlspecialchars($class['grade']) ?></td>
              <td class="py-2 px-4 border"><?= htmlspecialchars($class['section']) ?></td>
              <td class="py-2 px-4 border"><?= htmlspecialchars($class['type']) ?></td>
              <td class="py-2 px-4 border"><?= htmlspecialchars(implode(', ', $class['subjects'])) ?></td>
              <td class="py-2 px-4 border">
                <?php if ($class['class_teacher_id'] == $teacher_id): ?>
                  <span class="inline-block bg-green-200 text-green-800 px-2 py-1 rounded text-xs font-semibold">Class
                    Teacher</span>
                <?php else: ?>
                  <span class="inline-block bg-gray-200 text-gray-600 px-2 py-1 rounded text-xs">Teacher</span>
                <?php endif; ?>
              </td>
              <td class="py-2 px-4 border">
                <button onclick="openModal(<?= $classId ?>)"
                  class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-1 px-3 rounded"
                  aria-label="View students of class <?= htmlspecialchars($class['grade'] . ' ' . $class['section']) ?>">
                  View Students
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Modals for students -->
      <?php foreach ($classes as $classId => $class): ?>
        <?php
        // Fetch students for this class
        // Fetch students for this class, join parent to get contact
        // Updated query to also fetch parent's full name
        $stmtStudents = $conn->prepare("
        SELECT st.full_name, st.gender, st.dob, p.contact AS phone, p.email AS email, p.full_name AS parent_name
        FROM students st
        LEFT JOIN parents p ON st.parent_id = p.id
        WHERE st.class_id = ? 
        ORDER BY st.full_name ASC
      ");
        $stmtStudents->bind_param("i", $classId);
        $stmtStudents->execute();
        $studentsResult = $stmtStudents->get_result();


        ?>

        <div id="modal-<?= $classId ?>"
          class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden" role="dialog"
          aria-modal="true" aria-labelledby="modal-title-<?= $classId ?>" onclick="outsideClick(event, <?= $classId ?>)">
          <div id="modal-content-<?= $classId ?>"
            class="bg-white rounded-lg max-w-3xl w-full max-h-[80vh] overflow-y-auto p-6 relative"
            onclick="event.stopPropagation()">
            <h3 id="modal-title-<?= $classId ?>" class="text-2xl font-semibold mb-4">
              Students in Grade <?= htmlspecialchars($class['grade']) ?> Section <?= htmlspecialchars($class['section']) ?>
            </h3>
            <button onclick="closeModal(<?= $classId ?>)"
              class="absolute top-3 right-3 text-gray-600 hover:text-gray-900 font-bold text-xl"
              aria-label="Close modal">&times;</button>

            <?php if ($studentsResult->num_rows > 0): ?>
              <table class="w-full text-left border border-collapse">
                <thead class="bg-gray-100 sticky top-0">
                  <tr>
                    <th class="py-2 px-4 border">Name</th>
                    <th class="py-2 px-4 border">Gender</th>
                    <th class="py-2 px-4 border">Date of Birth</th>
                    <th class="py-2 px-4 border">Parent Name</th>
                    <th class="py-2 px-4 border">Phone</th>
                    <th class="py-2 px-4 border">Email</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($student = $studentsResult->fetch_assoc()): ?>
                    <tr class="border-t">
                      <td class="py-2 px-4 border"><?= htmlspecialchars($student['full_name']) ?></td>
                      <td class="py-2 px-4 border"><?= htmlspecialchars($student['gender']) ?></td>
                      <td class="py-2 px-4 border"><?= htmlspecialchars($student['dob']) ?></td>
                      <td class="py-2 px-4 border"><?= htmlspecialchars($student['parent_name'] ?? '') ?></td>
                      <td class="py-2 px-4 border"><?= htmlspecialchars($student['phone']) ?></td>
                      <td class="py-2 px-4 border"><?= htmlspecialchars($student['email']) ?></td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            <?php else: ?>
              <p class="text-gray-500">No students found in this class.</p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>

    <?php else: ?>
      <p class="text-gray-500">You are not assigned to any classes or subjects yet.</p>
    <?php endif; ?>
  </div>
</body>

</html>