<?php
require '../partials/dbconnect.php';
require '../vendor/autoload.php';

use Dompdf\Dompdf;

// Fetch parents and classes
$parent_result = $conn->query("SELECT id, full_name FROM parents ORDER BY full_name ASC");
$class_result = $conn->query("SELECT id, grade, section FROM classes ORDER BY grade ASC");

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'add') {
        $stmt = $conn->prepare("INSERT INTO students (full_name, gender, dob, grade, parent) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $_POST['full_name'], $_POST['gender'], $_POST['dob'], $_POST['class_id'], $_POST['parent']);
        $stmt->execute();
    } elseif ($action === 'edit') {
        $stmt = $conn->prepare("UPDATE students SET full_name=?, gender=?, dob=?, grade=?, parent=? WHERE id=?");
        $stmt->bind_param("sssiii", $_POST['full_name'], $_POST['gender'], $_POST['dob'], $_POST['class_id'], $_POST['parent'], $_POST['student_id']);
        $stmt->execute();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $_POST['student_id']);
        $stmt->execute();
    }
}

// Fetch students
$class_id_filter = $_GET['class_id'] ?? null;
if ($class_id_filter) {
    $stmt = $conn->prepare("SELECT s.*, p.full_name AS parent_name, c.grade AS class_grade, c.section AS class_section 
        FROM students s 
        LEFT JOIN parents p ON s.parent = p.id 
        LEFT JOIN classes c ON s.grade = c.id 
        WHERE c.id = ? ORDER BY s.id DESC");
    $stmt->bind_param("i", $class_id_filter);
    $stmt->execute();
    $students = $stmt->get_result();
} else {
    $students = $conn->query("SELECT s.*, p.full_name AS parent_name, c.grade AS class_grade, c.section AS class_section 
        FROM students s 
        LEFT JOIN parents p ON s.parent = p.id 
        LEFT JOIN classes c ON s.grade = c.id 
        ORDER BY s.id DESC");
}

// PDF Export
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    ob_start();
    echo "<h2 style='text-align:center;'>Student List</h2><table border='1' cellpadding='10' cellspacing='0' style='width:100%; font-size:14px;'>";
    echo "<thead><tr><th>Full Name</th><th>Gender</th><th>DOB</th><th>Class</th><th>Parent</th></tr></thead><tbody>";
    foreach ($students as $row) {
        echo "<tr><td>{$row['full_name']}</td><td>{$row['gender']}</td><td>{$row['dob']}</td><td>Grade {$row['class_grade']} - {$row['class_section']}</td><td>{$row['parent_name']}</td></tr>";
    }
    echo "</tbody></table>";
    $html = ob_get_clean();

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    header("Content-type: application/pdf");
    header("Content-Disposition: inline; filename=student_list.pdf");
    echo $dompdf->output();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 1rem;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.2);
        }
        .modalBox {
            animation: fadeIn 0.3s ease-in-out;
        }
        @keyframes fadeIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-100 to-purple-200 min-h-screen p-6">

<div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">🎓 Manage Students</h1>
        <div class="space-x-2">
            <a href="?export=pdf<?= $class_id_filter ? '&class_id=' . $class_id_filter : '' ?>" target="_blank"
               class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">📄 Export PDF</a>
            <button onclick="openAddModal()"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add Student</button>
        </div>
    </div>

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
        <form method="GET" class="flex items-center gap-2">
            <label class="text-gray-700 font-medium">Class:</label>
            <select name="class_id" onchange="this.form.submit()" class="px-3 py-2 rounded border border-gray-300 shadow-sm">
                <option value="">-- All Classes --</option>
                <?php $class_result->data_seek(0); while ($class = $class_result->fetch_assoc()): ?>
                    <option value="<?= $class['id'] ?>" <?= ($class_id_filter == $class['id']) ? 'selected' : '' ?>>
                        Grade <?= htmlspecialchars($class['grade']) ?> - <?= htmlspecialchars($class['section']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </form>
        <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search by name..." class="px-4 py-2 rounded border border-gray-300 shadow-sm w-full md:w-64">
    </div>

    <table id="studentTable" class="min-w-full bg-white border text-sm">
        <thead class="bg-gray-100 text-gray-600 uppercase">
            <tr>
                <th class="py-3 px-6 text-left">Full Name</th>
                <th class="py-3 px-6 text-left">Gender</th>
                <th class="py-3 px-6 text-left">DOB</th>
                <th class="py-3 px-6 text-left">Class</th>
                <th class="py-3 px-6 text-left">Parent</th>
                <th class="py-3 px-6 text-center">Actions</th>
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
                    <button onclick='openEditModal(<?= json_encode($row) ?>)'
                            class="bg-yellow-400 text-white px-3 py-1 rounded text-sm hover:bg-yellow-500">Edit</button>
                    <button onclick='openDeleteModal(<?= $row['id'] ?>)'
                            class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">Delete</button>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Modal -->
<div id="studentModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden" onclick="closeModal(event)">
    <div class="bg-white glass p-6 rounded-lg w-full max-w-lg modalBox">
        <h2 class="text-lg font-bold mb-4" id="modalTitle"></h2>
        <form method="POST">
            <input type="hidden" name="action" id="formAction">
            <input type="hidden" name="student_id" id="student_id">
            <div class="grid gap-4">
                <input type="text" name="full_name" id="full_name" placeholder="Full Name" required class="px-4 py-2 border rounded">
                <select name="gender" id="gender" required class="px-4 py-2 border rounded">
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
                <input type="date" name="dob" id="dob" required class="px-4 py-2 border rounded">
                <select name="class_id" id="class_id" required class="px-4 py-2 border rounded">
                    <option value="">Select Class</option>
                    <?php $class_result->data_seek(0); while ($class = $class_result->fetch_assoc()): ?>
                        <option value="<?= $class['id'] ?>">Grade <?= $class['grade'] ?> - <?= $class['section'] ?></option>
                    <?php endwhile; ?>
                </select>
                <select name="parent" id="parent" required class="px-4 py-2 border rounded">
                    <option value="">Select Parent</option>
                    <?php $parent_result->data_seek(0); while ($parent = $parent_result->fetch_assoc()): ?>
                        <option value="<?= $parent['id'] ?>"><?= $parent['full_name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('studentModal').classList.add('hidden')" class="px-4 py-2 bg-gray-400 rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden" onclick="closeModal(event)">
    <div class="bg-white glass p-6 rounded-lg modalBox w-full max-w-sm">
        <h2 class="text-lg font-bold mb-4">⚠️ Confirm Deletion</h2>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="student_id" id="delete_id">
            <p class="mb-4">Are you sure you want to delete this student?</p>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')" class="px-4 py-2 bg-gray-400 rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    function filterTable() {
        const filter = document.getElementById("searchInput").value.toLowerCase();
        const rows = document.querySelectorAll("#studentTable tbody tr");
        rows.forEach(row => {
            const name = row.querySelector("td").textContent.toLowerCase();
            row.style.display = name.includes(filter) ? "" : "none";
        });
    }

    function openAddModal() {
        document.getElementById("formAction").value = "add";
        document.getElementById("student_id").value = "";
        document.getElementById("full_name").value = "";
        document.getElementById("gender").value = "";
        document.getElementById("dob").value = "";
        document.getElementById("class_id").value = "";
        document.getElementById("parent").value = "";
        document.getElementById("modalTitle").innerText = "➕ Add Student";
        document.getElementById("studentModal").classList.remove("hidden");
    }

    function openEditModal(data) {
        document.getElementById("formAction").value = "edit";
        document.getElementById("student_id").value = data.id;
        document.getElementById("full_name").value = data.full_name;
        document.getElementById("gender").value = data.gender;
        document.getElementById("dob").value = data.dob;
        document.getElementById("class_id").value = data.grade;
        document.getElementById("parent").value = data.parent;
        document.getElementById("modalTitle").innerText = "✏️ Edit Student";
        document.getElementById("studentModal").classList.remove("hidden");
    }

    function openDeleteModal(id) {
        document.getElementById("delete_id").value = id;
        document.getElementById("deleteModal").classList.remove("hidden");
    }

    function closeModal(e) {
        if (e.target.classList.contains("fixed")) {
            e.target.classList.add("hidden");
        }
    }
</script>
</body>
</html>
