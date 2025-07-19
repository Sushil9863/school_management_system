<?php
require '../partials/dbconnect.php';
require 'check_admin.php'; // admin check & session school_id set भैसकेको मानिन्छ
require '../vendor/autoload.php';
use Dompdf\Dompdf;

// admin check गर्दा school_id session मा हुनु पर्छ
$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
  die("Invalid access: School not identified.");
}

// Parents query filtered by school_id
$parent_stmt = $conn->prepare("SELECT id, full_name FROM parents WHERE school_id = ? ORDER BY full_name ASC");
$parent_stmt->bind_param("i", $school_id);
$parent_stmt->execute();
$parent_result = $parent_stmt->get_result();

// Classes query filtered by school_id
$class_stmt = $conn->prepare("SELECT id, grade, section FROM classes WHERE school_id = ? ORDER BY grade ASC");
$class_stmt->bind_param("i", $school_id);
$class_stmt->execute();
$class_result = $class_stmt->get_result();

// Handle POST actions for add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $stmt = $conn->prepare("INSERT INTO students (full_name, gender, dob, class_id, parent_id, school_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
      "sssiii",
      $_POST['full_name'],
      $_POST['gender'],
      $_POST['dob'],
      $_POST['class_id'],
      $_POST['parent'],
      $school_id
    );
    $stmt->execute();
    header("Location: manage_students.php");
    exit;
  } elseif ($action === 'edit') {
    $stmt = $conn->prepare("UPDATE students SET full_name=?, gender=?, dob=?, class_id=?, parent_id=? WHERE id=? AND school_id=?");
    $stmt->bind_param(
      "sssiiii",
      $_POST['full_name'],
      $_POST['gender'],
      $_POST['dob'],
      $_POST['class_id'],
      $_POST['parent'],
      $_POST['student_id'],
      $school_id
    );
    $stmt->execute();
    header("Location: manage_students.php");
    exit;
  } elseif ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ? AND school_id = ?");
    $stmt->bind_param("ii", $_POST['student_id'], $school_id);
    $stmt->execute();
    header("Location: manage_students.php");
    exit;
  }
}

// Filter for class_id from GET
$class_id_filter = $_GET['class_id'] ?? null;

// Build query with school filter and optional class filter
if ($class_id_filter) {
  $query = "SELECT s.*, p.full_name AS parent_name, c.grade AS class_grade, c.section AS class_section 
              FROM students s
              LEFT JOIN parents p ON s.parent_id = p.id
              LEFT JOIN classes c ON s.class_id = c.id
              WHERE s.school_id = ? AND s.class_id = ?
              ORDER BY s.id DESC";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("ii", $school_id, $class_id_filter);
} else {
  $query = "SELECT s.*, p.full_name AS parent_name, c.grade AS class_grade, c.section AS class_section 
              FROM students s
              LEFT JOIN parents p ON s.parent_id = p.id
              LEFT JOIN classes c ON s.class_id = c.id
              WHERE s.school_id = ?
              ORDER BY s.id DESC";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $school_id);
}

$stmt->execute();
$students = $stmt->get_result();

// PDF Export if requested
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
  ob_start();
  echo "<h2 style='text-align:center;'>Student List</h2><table border='1' cellpadding='8' cellspacing='0' style='width:100%; font-size:14px;'>";
  echo "<thead><tr><th>Name</th><th>Gender</th><th>DOB</th><th>Class</th><th>Parent</th></tr></thead><tbody>";
  foreach ($students as $row) {
    echo "<tr>
                <td>" . htmlspecialchars($row['full_name']) . "</td>
                <td>" . htmlspecialchars($row['gender']) . "</td>
                <td>" . htmlspecialchars($row['dob']) . "</td>
                <td>Grade " . htmlspecialchars($row['class_grade']) . " - " . htmlspecialchars($row['class_section']) . "</td>
                <td>" . htmlspecialchars($row['parent_name']) . "</td>
              </tr>";
  }
  echo "</tbody></table>";
  $html = ob_get_clean();

  $dompdf = new Dompdf();
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();

  if (ob_get_length())
    ob_end_clean();

  header('Content-Type: application/pdf');
  header('Content-Disposition: inline; filename="students.pdf"');
  echo $dompdf->output();
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Manage Students</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-br from-blue-200 to-purple-300 min-h-screen p-6">
  <div class="max-w-6xl mx-auto bg-white/60 backdrop-blur-md p-6 rounded-xl shadow-xl">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold text-gray-900">🎓 Manage Students</h1>
      <div>
        <a href="?export=pdf<?= $class_id_filter ? '&class_id=' . $class_id_filter : '' ?>" target="_blank"
          class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">📄 Export PDF</a>
        <button onclick="openAddModal()" class="ml-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">+ Add
          Student</button>
      </div>
    </div>

    <form method="GET" class="mb-4 flex items-center gap-3">
      <label>Filter by Class:</label>
      <select name="class_id" onchange="this.form.submit()" class="px-3 py-2 border rounded">
        <option value="">-- All Classes --</option>
        <?php while ($class = $class_result->fetch_assoc()): ?>
          <option value="<?= $class['id'] ?>" <?= ($class_id_filter == $class['id']) ? 'selected' : '' ?>>
            Grade <?= htmlspecialchars($class['grade']) ?> - <?= htmlspecialchars($class['section']) ?>
          </option>
        <?php endwhile; ?>
      </select>
      <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search..."
        class="px-4 py-2 border rounded flex-1" />
    </form>

    <table id="studentTable" class="min-w-full bg-white text-sm rounded shadow overflow-hidden">
      <thead class="bg-gray-200 text-gray-700">
        <tr>
          <th class="py-3 px-4 text-left">S.No.</th>
          <th class="py-3 px-4 text-left">Name</th>
          <th class="py-3 px-4 text-left">Gender</th>
          <th class="py-3 px-4 text-left">DOB</th>
          <th class="py-3 px-4 text-left">Class</th>
          <th class="py-3 px-4 text-left">Parent</th>
          <th class="py-3 px-4 text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $serial = 1; 
        while ($row = $students->fetch_assoc()):
          ?>
          <tr class="border-b hover:bg-gray-100">
            <td class="py-2 px-4"><?= $serial++ ?></td>
            <td class="py-2 px-4"><?= htmlspecialchars($row['full_name']) ?></td>
            <td class="py-2 px-4"><?= htmlspecialchars($row['gender']) ?></td>
            <td class="py-2 px-4"><?= htmlspecialchars($row['dob']) ?></td>
            <td class="py-2 px-4">Grade <?= htmlspecialchars($row['class_grade']) ?> -
              <?= htmlspecialchars($row['class_section']) ?></td>
            <td class="py-2 px-4"><?= htmlspecialchars($row['parent_name']) ?></td>
            <td class="py-2 px-4 text-center space-x-2">
              <button onclick='openEditModal(<?= json_encode($row) ?>)'
                class="bg-yellow-400 text-white px-3 py-1 rounded hover:bg-yellow-500">Edit</button>
              <button onclick='openDeleteModal(<?= $row['id'] ?>)'
                class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">Delete</button>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>

    </table>
  </div>

  <!-- Modals -->
  <div id="studentModal" onclick="closeModal(event)"
    class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center">
    <div onclick="event.stopPropagation();"
      class="bg-white/30 backdrop-blur-lg shadow-2xl hover:ring-4 hover:ring-blue-400 rounded-2xl p-6 w-full max-w-2xl animate-fade-in">
      <h2 id="modalTitle" class="text-2xl font-bold text-white mb-4 text-center">➕ Add Student</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-white">
        <input type="hidden" name="action" id="formAction" />
        <input type="hidden" name="student_id" id="student_id" />
        <div>
          <label>Full Name</label>
          <input type="text" name="full_name" id="full_name" required
            class="w-full px-3 py-2 rounded bg-white/80 text-black" />
        </div>
        <div>
          <label>Gender</label>
          <select name="gender" id="gender" required class="w-full px-3 py-2 rounded bg-white/80 text-black">
            <option value="">Select</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
          </select>
        </div>
        <div>
          <label>DOB</label>
          <input type="date" name="dob" id="dob" required class="w-full px-3 py-2 rounded bg-white/80 text-black" />
        </div>
        <div>
          <label>Class</label>
          <select name="class_id" id="class_id" required class="w-full px-3 py-2 rounded bg-white/80 text-black">
            <option value="">Select</option>
            <?php
            // Reset pointer to fetch from beginning
            $class_result->data_seek(0);
            while ($class = $class_result->fetch_assoc()):
              ?>
              <option value="<?= $class['id'] ?>"><?= "Grade {$class['grade']} - {$class['section']}" ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div>
          <label>Parent</label>
          <select name="parent" id="parent" required class="w-full px-3 py-2 rounded bg-white/80 text-black">
            <option value="">Select</option>
            <?php
            $parent_result->data_seek(0);
            while ($parent = $parent_result->fetch_assoc()):
              ?>
              <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['full_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>
        <div class="col-span-2 flex justify-end space-x-2 mt-4">
          <button type="button" onclick="closeAllModals()" class="bg-gray-400 px-4 py-2 rounded">Cancel</button>
          <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
        </div>
      </form>
    </div>
  </div>

  <div id="deleteModal" onclick="closeModal(event)"
    class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 flex items-center justify-center">
    <div onclick="event.stopPropagation();"
      class="bg-white/30 backdrop-blur-lg p-6 rounded-2xl shadow-2xl hover:ring-4 hover:ring-red-400 w-full max-w-md animate-fade-in text-white text-center">
      <form method="POST">
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" name="student_id" id="delete_id" />
        <h3 class="text-2xl font-bold mb-4">⚠️ Confirm Deletion</h3>
        <p class="mb-6">Are you sure you want to delete this student?</p>
        <div class="flex justify-center space-x-4">
          <button type="button" onclick="closeAllModals()"
            class="bg-gray-300 text-black px-4 py-2 rounded">Cancel</button>
          <button type="submit"
            class="bg-gradient-to-r from-red-600 to-pink-600 text-white px-6 py-2 rounded hover:scale-105">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openAddModal() {
      document.getElementById('formAction').value = 'add';
      document.getElementById('student_id').value = '';
      document.getElementById('full_name').value = '';
      document.getElementById('gender').value = '';
      document.getElementById('dob').value = '';
      document.getElementById('class_id').value = '';
      document.getElementById('parent').value = '';
      document.getElementById('modalTitle').innerText = '➕ Add Student';
      document.getElementById('studentModal').classList.remove('hidden');
    }

    function openEditModal(data) {
      document.getElementById('formAction').value = 'edit';
      document.getElementById('student_id').value = data.id;
      document.getElementById('full_name').value = data.full_name;
      document.getElementById('gender').value = data.gender;
      document.getElementById('dob').value = data.dob;
      document.getElementById('class_id').value = data.class_id;
      document.getElementById('parent').value = data.parent_id;
      document.getElementById('modalTitle').innerText = '✏️ Edit Student';
      document.getElementById('studentModal').classList.remove('hidden');
    }

    function openDeleteModal(id) {
      document.getElementById('delete_id').value = id;
      document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeModal(e) {
      if (e.target.classList.contains('fixed')) e.target.classList.add('hidden');
    }

    function closeAllModals() {
      document.getElementById('studentModal').classList.add('hidden');
      document.getElementById('deleteModal').classList.add('hidden');
    }

    function filterTable() {
      const filter = document.getElementById("searchInput").value.toLowerCase();
      const rows = document.querySelectorAll("#studentTable tbody tr");
      rows.forEach(row => {
        const name = row.querySelector("td").textContent.toLowerCase();
        row.style.display = name.includes(filter) ? "" : "none";
      });
    }
  </script>
</body>

</html>