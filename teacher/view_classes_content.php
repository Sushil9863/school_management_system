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

// 📚 Fetch only classes & subjects for this teacher's school
$stmt = $conn->prepare("
    SELECT c.grade, c.section, c.type, s.name AS subject_name
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.teacher_id = ? AND c.school_id = ?
    ORDER BY c.grade ASC, c.section ASC
");
$stmt->bind_param("ii", $teacher_id, $school_id);
$stmt->execute();
$assigned = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Classes</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen p-6">
  <div class="max-w-4xl mx-auto bg-white p-6 rounded-lg shadow">
    <h1 class="text-3xl font-bold text-gray-800 mb-4">👨‍🏫 Welcome, <?= htmlspecialchars($teacher_name) ?></h1>
    <h2 class="text-xl font-semibold text-gray-700 mb-6">📚 Your Assigned Classes & Subjects</h2>

    <?php if ($assigned->num_rows > 0): ?>
      <table class="w-full text-left border border-collapse">
        <thead class="bg-gray-100">
          <tr>
            <th class="py-2 px-4 border">Grade</th>
            <th class="py-2 px-4 border">Section</th>
            <th class="py-2 px-4 border">Class Type</th>
            <th class="py-2 px-4 border">Subject</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $assigned->fetch_assoc()): ?>
            <tr class="border-t hover:bg-gray-50">
              <td class="py-2 px-4 border"><?= htmlspecialchars($row['grade']) ?></td>
              <td class="py-2 px-4 border"><?= htmlspecialchars($row['section']) ?></td>
              <td class="py-2 px-4 border"><?= htmlspecialchars($row['type']) ?></td>
              <td class="py-2 px-4 border"><?= htmlspecialchars($row['subject_name']) ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-gray-500">You are not assigned to any classes or subjects yet.</p>
    <?php endif; ?>
  </div>
</body>
</html>
