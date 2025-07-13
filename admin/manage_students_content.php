<?php
include '../partials/dbconnect.php';

// Fetch parents and classes for dropdowns
$parent_result = $conn->query("SELECT id, full_name FROM parents ORDER BY full_name ASC");
$class_result = $conn->query("SELECT id, grade, section FROM classes ORDER BY grade ASC");

// Handle Add Student
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $stmt = $conn->prepare("INSERT INTO students (full_name, gender, dob, grade, parent) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $_POST['full_name'], $_POST['gender'], $_POST['dob'], $_POST['class_id'], $_POST['parent']);
        $stmt->execute();
    }

    // Handle Edit Student
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $stmt = $conn->prepare("UPDATE students SET full_name=?, gender=?, dob=?, grade=?, parent=? WHERE id=?");
        $stmt->bind_param("sssiii", $_POST['full_name'], $_POST['gender'], $_POST['dob'], $_POST['class_id'], $_POST['parent'], $_POST['student_id']);
        $stmt->execute();
    }

    // Handle Delete Student
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $_POST['student_id']);
        $stmt->execute();
    }
}

// Fetch students list with class and parent info
$class_id_filter = $_GET['class_id'] ?? null;
if ($class_id_filter) {
    $stmt = $conn->prepare("SELECT s.*, p.full_name AS parent_name, c.grade AS class_grade, c.section AS class_section FROM students s LEFT JOIN parents p ON s.parent = p.id LEFT JOIN classes c ON s.grade = c.id WHERE c.id = ? ORDER BY s.id DESC");
    $stmt->bind_param("i", $class_id_filter);
    $stmt->execute();
    $students = $stmt->get_result();
} else {
    $students = $conn->query("SELECT s.*, p.full_name AS parent_name, c.grade AS class_grade, c.section AS class_section FROM students s LEFT JOIN parents p ON s.parent = p.id LEFT JOIN classes c ON s.grade = c.id ORDER BY s.id DESC");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Students</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-r from-blue-100 to-purple-100 min-h-screen p-6">
    <div class="max-w-6xl mx-auto bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">🎓 Manage Students</h1>
            <button onclick="openAddModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">+ Add
                Student</button>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
            <form method="GET" class="flex items-center gap-2">
                <label class="text-gray-700 font-medium">Class:</label>
                <select name="class_id" onchange="this.form.submit()"
                    class="px-3 py-2 rounded border border-gray-300 shadow-sm">
                    <option value="">-- All Classes --</option>
                    <?php $class_result->data_seek(0);
                    while ($class = $class_result->fetch_assoc()): ?>
                        <option value="<?= $class['id'] ?>" <?= (isset($_GET['class_id']) && $_GET['class_id'] == $class['id']) ? 'selected' : '' ?>>
                            Grade <?= htmlspecialchars($class['grade']) ?> - <?= htmlspecialchars($class['section']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </form>
            <input type="text" id="searchInput" onkeyup="filterTable()" placeholder="Search by name..."
                class="px-4 py-2 rounded border border-gray-300 shadow-sm w-full md:w-64">
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
                        <td class="py-3 px-6">Grade <?= htmlspecialchars($row['class_grade']) ?> -
                            <?= htmlspecialchars($row['class_section']) ?>
                        </td>
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
    <div id="studentModal" onclick="closeModal(event)"
        class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="modalBox">
            <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-2xl transition duration-300 ease-in-out
      hover:ring-4 hover:ring-blue-400 hover:ring-offset-2
      hover:shadow-[0_0_30px_rgba(59,130,246,0.6)] filter hover:brightness-110" onclick="event.stopPropagation();">

                <h2 id="modalTitle" class="text-2xl font-bold text-white mb-6 text-center">➕ Add Student</h2>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="student_id" id="student_id">
                    <input type="hidden" name="action" id="formAction" value="add">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-white font-medium mb-1">Full Name</label>
                            <input type="text" name="full_name" id="full_name" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
              focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
              hover:ring-2 hover:ring-blue-400 transition duration-300">
                        </div>

                        <div>
                            <label class="block text-white font-medium mb-1">Gender</label>
                            <select name="gender" id="gender" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white text-gray-800 
              focus:outline-none focus:ring-4 focus:ring-blue-500 transition duration-300">
                                <option value="">-- Select --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-white font-medium mb-1">Date of Birth</label>
                            <input type="date" name="dob" id="dob" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
              focus:outline-none focus:ring-4 focus:ring-blue-500 transition duration-300">
                        </div>

                        <div>
                            <label class="block text-white font-medium mb-1">Class</label>
                            <select name="class_id" id="class_id" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white text-gray-800 
              focus:outline-none focus:ring-4 focus:ring-blue-500 transition duration-300">
                                <option value="">-- Select --</option>
                                <?php $class_result->data_seek(0);
                                while ($class = $class_result->fetch_assoc()): ?>
                                    <option value="<?= $class['id'] ?>">Grade <?= htmlspecialchars($class['grade']) ?> -
                                        <?= htmlspecialchars($class['section']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-white font-medium mb-1">Parent</label>
                            <select name="parent" id="parent" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white text-gray-800 
              focus:outline-none focus:ring-4 focus:ring-blue-500 transition duration-300">
                                <option value="">-- Select --</option>
                                <?php $parent_result->data_seek(0);
                                while ($parent = $parent_result->fetch_assoc()): ?>
                                    <option value="<?= $parent['id'] ?>"><?= htmlspecialchars($parent['full_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" onclick="document.getElementById('studentModal').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400 transition font-semibold">Cancel</button>
                        <button type="submit"
                            class="px-6 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:scale-105 hover:shadow-xl font-semibold">
                            Save
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>


    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" onclick="closeModal(event)"
        class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
        <div class="modalBox">
            <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-sm text-center transition duration-300 ease-in-out
      hover:ring-4 hover:ring-red-400 hover:ring-offset-2
      hover:shadow-[0_0_30px_rgba(220,38,38,0.6)] filter hover:brightness-110" onclick="event.stopPropagation();">

                <form method="POST">
                    <input type="hidden" name="student_id" id="delete_id">
                    <input type="hidden" name="action" value="delete">

                    <h3 class="text-xl font-bold text-white mb-6">⚠️ Confirm Deletion</h3>
                    <p class="text-white mb-6">Are you sure you want to delete this student?</p>

                    <div class="flex justify-center space-x-4">
                        <button type="button" onclick="document.getElementById('deleteModal').classList.add('hidden')"
                            class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400 transition font-semibold">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-6 py-2 rounded-lg bg-gradient-to-r from-red-600 to-pink-600 text-white hover:scale-105 hover:shadow-xl font-semibold">
                            Delete
                        </button>
                    </div>
                </form>

            </div>
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

        function closeModal(e) {
            if (e.target.id === "studentModal" || e.target.id === "deleteModal") {
                e.target.classList.add("hidden");
            }
        }

        function openAddModal() {
            document.getElementById("modalTitle").innerText = "➕ Add Student";
            document.getElementById("formAction").value = "add";
            document.getElementById("student_id").value = "";
            document.getElementById("full_name").value = "";
            document.getElementById("gender").value = "";
            document.getElementById("dob").value = "";
            document.getElementById("class_id").value = "";
            document.getElementById("parent").value = "";
            document.getElementById("studentModal").classList.remove("hidden");
        }

        function openEditModal(data) {
            document.getElementById("modalTitle").innerText = "✏️ Edit Student";
            document.getElementById("formAction").value = "edit";
            document.getElementById("student_id").value = data.id;
            document.getElementById("full_name").value = data.full_name;
            document.getElementById("gender").value = data.gender;
            document.getElementById("dob").value = data.dob;
            document.getElementById("class_id").value = data.grade;
            document.getElementById("parent").value = data.parent;
            document.getElementById("studentModal").classList.remove("hidden");
        }

        function openDeleteModal(id) {
            document.getElementById("delete_id").value = id;
            document.getElementById("deleteModal").classList.remove("hidden");
        }
    </script>
</body>

</html>