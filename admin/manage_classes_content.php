<?php
include '../partials/dbconnect.php';

// Fetch teachers
$teacher_result = $conn->query("SELECT id, username, full_name FROM teachers ORDER BY full_name ASC");

// Handle Edit Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_section'])) {
  $class_id = $_POST['class_id'];
  $new_section_name = $_POST['section_name'];

  // Update section name in classes table
  $stmt = $conn->prepare("UPDATE classes SET section = ? WHERE id = ?");
  $stmt->bind_param("si", $new_section_name, $class_id);
  $stmt->execute();

  // Also update sections table if needed
  $stmt = $conn->prepare("UPDATE sections SET section_name = ? WHERE class_id = ?");
  $stmt->bind_param("si", $new_section_name, $class_id);
  $stmt->execute();

  // Reload class data to reflect changes
  header("Location: " . $_SERVER['REQUEST_URI']);
  exit;
}



// Handle Change Class Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_teacher'])) {
  $class_id = $_POST['class_id'];
  $new_teacher_id = $_POST['teacher_id'];

  // Get the teacher name
  $stmt = $conn->prepare("SELECT full_name FROM teachers WHERE id = ?");
  $stmt->bind_param("i", $new_teacher_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $teacher = $result->fetch_assoc();
  $new_teacher_name = $teacher['full_name'] ?? '';

  // Update the class teacher in classes table
  $stmt = $conn->prepare("UPDATE classes SET class_teacher = ? WHERE id = ?");
  $stmt->bind_param("si", $new_teacher_name, $class_id);
  $stmt->execute();

  // Refresh class data
  // if ($selected_class_id) {
  //   $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
  //   $stmt->bind_param("i", $selected_class_id);
  //   $stmt->execute();
  //   $class_data = $stmt->get_result()->fetch_assoc();
  // }
}





// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
  $subject_name = $_POST['subject_name'];
  $class_id = $_POST['class_id'];
  $teacher_id = $_POST['teacher_id'];

  $stmt = $conn->prepare("INSERT INTO subjects (name, class_id, teacher_id) VALUES (?, ?, ?)");
  $stmt->bind_param("sii", $subject_name, $class_id, $teacher_id);
  $stmt->execute();
}

// Handle Edit Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_subject'])) {
  $subject_id = $_POST['subject_id'];
  $subject_name = $_POST['subject_name'];
  $teacher_id = $_POST['teacher_id'];

  $stmt = $conn->prepare("UPDATE subjects SET name = ?, teacher_id = ? WHERE id = ?");
  $stmt->bind_param("sii", $subject_name, $teacher_id, $subject_id);
  $stmt->execute();
}

// Handle Delete Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subject'])) {
  $subject_id = $_POST['subject_id'];

  $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
  $stmt->bind_param("i", $subject_id);
  $stmt->execute();
}



// Handle Add Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
  $grade = $_POST['grade'];
  $section = $_POST['section'];
  $class_type = $_POST['class_type'];
  $teacher_id = $_POST['class_teacher_id'];

  $stmt = $conn->prepare("SELECT full_name FROM teachers WHERE id = ?");
  $stmt->bind_param("i", $teacher_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $teacher = $result->fetch_assoc();
  $class_teacher = $teacher['full_name'] ?? '';

  $stmt = $conn->prepare("INSERT INTO classes (grade, section, type, class_teacher) VALUES (?, ?, ?, ?)");
  $stmt->bind_param("ssss", $grade, $section, $class_type, $class_teacher);
  $stmt->execute();

  $class_id = $conn->insert_id;

  $stmt = $conn->prepare("INSERT INTO sections (class_id, section_name) VALUES (?, ?)");
  $stmt->bind_param("is", $class_id, $section);
  $stmt->execute();
}

$class_result = $conn->query("SELECT * FROM classes ORDER BY grade ASC");

$students = $subjects = null;
$selected_class_id = $_GET['class_id'] ?? null;
if ($selected_class_id) {
  $stmt = $conn->prepare("SELECT * FROM students WHERE grade = ?");
  $stmt->bind_param("s", $selected_class_id);
  $stmt->execute();
  $students = $stmt->get_result();

  $stmt = $conn->prepare("SELECT s.*, t.full_name as teacher_name FROM subjects s 
                          JOIN teachers t ON s.teacher_id = t.id WHERE s.class_id = ?");
  $stmt->bind_param("i", $selected_class_id);
  $stmt->execute();
  $subjects = $stmt->get_result();

  // Get the selected class data for display
  $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
  $stmt->bind_param("i", $selected_class_id);
  $stmt->execute();
  $class_data = $stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Classes</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 p-6">
  <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow space-y-8">
    <div class="flex justify-between items-center">
      <h1 class="text-3xl font-bold text-gray-800">📘 Manage Classes</h1>
      <button onclick="document.getElementById('addModal').classList.remove('hidden')"
        class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg">+ Add Class</button>
    </div>

    <form method="GET" class="flex space-x-4">
      <select name="class_id" class="border px-4 py-2 rounded w-64">
        <option value="">-- Select a Class --</option>
        <?php while ($class = $class_result->fetch_assoc()): ?>
          <option value="<?= $class['id'] ?>" <?= $selected_class_id == $class['id'] ? 'selected' : '' ?>>
            Grade <?= $class['grade'] ?> - <?= $class['section'] ?>
          </option>
        <?php endwhile; ?>
      </select>
      <button class="bg-green-600 text-white px-4 py-2 rounded">Load</button>
    </form>

    <?php if ($selected_class_id && $class_data): ?>
      <div>
        <div class="border-b border-gray-200 mb-4">
          <nav class="-mb-px flex space-x-8" id="tabs">
            <button class="tab-btn text-gray-500 py-2 px-4 border-b-2" onclick="openTab('students')">Students</button>
            <button class="tab-btn text-gray-500 py-2 px-4 border-b-2" onclick="openTab('subjects')">Subjects</button>
            <button class="tab-btn text-gray-500 py-2 px-4 border-b-2" onclick="openTab('teacher')">Class Teacher</button>
            <button class="tab-btn text-gray-500 py-2 px-4 border-b-2" onclick="openTab('sections')">Sections</button>
          </nav>
        </div>

        <div id="students" class="tab-content hidden">
          <div class="flex justify-between mb-2">
            <h2 class="text-xl font-semibold">👩‍🎓 Students</h2>
          </div>
          <?php if ($students && $students->num_rows > 0): ?>
            <ul class="space-y-2">
              <?php while ($s = $students->fetch_assoc()): ?>
                <li class="flex justify-between items-center bg-gray-50 p-3 rounded">
                  <span><?= htmlspecialchars($s['full_name']) ?> (<?= $s['gender'] ?>)</span>
                  <div class="space-x-2">
                  </div>
                </li>
              <?php endwhile; ?>
            </ul>
          <?php else: ?>
            <p class="text-gray-500">No students found.</p>
          <?php endif; ?>
        </div>

        <div id="subjects" class="tab-content hidden">
          <div class="flex justify-between mb-2">
            <h2 class="text-xl font-semibold">📚 Subjects</h2>
            <button type="button" onclick="document.getElementById('subjectModal').classList.remove('hidden')"
              class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 transition">+ Add Subject</button>


          </div>
          <?php if ($subjects && $subjects->num_rows > 0): ?>
            <table class="w-full text-sm border mt-2">
              <thead class="bg-gray-100">
                <tr>
                  <th class="text-left py-2 px-4">Subject</th>
                  <th class="text-left py-2 px-4">Teacher</th>
                  <th class="text-left py-2 px-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php while ($sub = $subjects->fetch_assoc()): ?>
                  <tr class="border-t">
                    <td class="py-2 px-4"><?= htmlspecialchars($sub['name']) ?></td>
                    <td class="py-2 px-4"><?= htmlspecialchars($sub['teacher_name']) ?></td>
                    <td class="py-2 px-4 space-x-2">
                      <button type="button" class="text-blue-600"
                        onclick="openEditSubject(<?= $sub['id'] ?>, '<?= htmlspecialchars($sub['name'], ENT_QUOTES) ?>', <?= $sub['teacher_id'] ?>)">
                        Edit
                      </button>
                      <button type="button" class="text-red-600" onclick="openDeleteSubject(<?= $sub['id'] ?>)">
                        Remove
                      </button>
                    </td>

                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="text-gray-500">No subjects assigned.</p>
          <?php endif; ?>
        </div>

        <div id="teacher" class="tab-content hidden">
          <h2 class="text-xl font-semibold mb-2">👨‍🏫 Class Teacher</h2>
          <p class="text-gray-700">Currently assigned:
            <strong><?= htmlspecialchars($class_data['class_teacher'] ?? 'N/A') ?></strong>
          </p>
          <button class="mt-2 bg-blue-500 text-white px-3 py-1 rounded"
            onclick="document.getElementById('changeTeacherModal').classList.remove('hidden')">
            Change Teacher
          </button>

        </div>

        <div id="sections" class="tab-content hidden">
          <h2 class="text-xl font-semibold mb-2">🏷️ Sections</h2>
          <p>Section: <strong><?= htmlspecialchars($class_data['section'] ?? '-') ?></strong></p>
          <button class="mt-2 bg-blue-500 text-white px-3 py-1 rounded"
            onclick="document.getElementById('editSectionModal').classList.remove('hidden')">
            Edit Section
          </button>

        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Add Class Modal -->
  <div id="addModal" onclick="closeOutside(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 modal-background">
    <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-2xl 
              transition duration-300 ease-in-out
              hover:ring-4 hover:ring-blue-400 hover:ring-offset-2
              hover:shadow-[0_0_30px_rgba(59,130,246,0.6)] filter hover:brightness-110"
      onclick="event.stopPropagation();">

      <h2 class="text-2xl font-bold text-white mb-4">➕ Add New Class</h2>

      <form method="POST">
        <input type="hidden" name="add_class" value="1" />
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-white font-medium mb-1">Grade</label>
            <input type="text" name="grade" placeholder="Grade" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
                   focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
                   hover:ring-2 hover:ring-blue-400 transition duration-300" />
          </div>
          <div>
            <label class="block text-white font-medium mb-1">Section</label>
            <input type="text" name="section" placeholder="Section" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
                   focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
                   hover:ring-2 hover:ring-blue-400 transition duration-300" />
          </div>
          <div class="md:col-span-2">
            <label class="block text-white font-medium mb-1">Class Type</label>
            <select name="class_type" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white text-gray-700 
                   focus:outline-none focus:ring-4 focus:ring-blue-500">
              <option value="">-- Select Class Type --</option>
              <option value="Pre-Primary">Pre-Primary</option>
              <option value="Primary">Primary</option>
              <option value="Secondary">Secondary</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-white font-medium mb-1">Class Teacher</label>
            <select name="class_teacher_id" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white text-gray-700 
                   focus:outline-none focus:ring-4 focus:ring-blue-500">
              <option value="">-- Select Class Teacher --</option>
              <?php $teacher_result->data_seek(0);
              while ($t = $teacher_result->fetch_assoc()): ?>
                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= $t['username'] ?>)</option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>

        <div class="flex justify-end space-x-4 mt-6">
          <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
            class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400 transition font-semibold">Cancel</button>
          <button type="submit"
            class="px-6 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:scale-105 hover:shadow-xl transition font-semibold">
            Add
          </button>
        </div>
      </form>
    </div>
  </div>




  <!-- Edit Subject Modal -->
  <div id="editSubjectModal" onclick="closeOutside(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="modalBox">
      <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-xl transition duration-300 ease-in-out
      hover:ring-4 hover:ring-green-400 hover:ring-offset-2
      hover:shadow-[0_0_30px_rgba(34,197,94,0.6)] filter hover:brightness-105" onclick="event.stopPropagation();">

        <h2 class="text-2xl font-bold text-white mb-4">✏️ Edit Subject</h2>

        <form method="POST">
          <input type="hidden" name="edit_subject" value="1">
          <input type="hidden" id="edit_subject_id" name="subject_id">

          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-white font-medium mb-1">Subject Name</label>
              <input type="text" name="subject_name" id="edit_subject_name" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
              focus:outline-none focus:ring-4 focus:ring-green-400 focus:shadow-[0_0_20px_rgba(34,197,94,0.6)] 
              hover:ring-2 hover:ring-green-300 transition duration-300">
            </div>

            <div>
              <label class="block text-white font-medium mb-1">Assign Teacher</label>
              <select name="teacher_id" id="edit_teacher_id" required class="w-full px-4 py-2 rounded-lg bg-white/70 border border-white text-gray-700 
              focus:outline-none focus:ring-4 focus:ring-green-400 focus:shadow-[0_0_20px_rgba(34,197,94,0.6)] 
              hover:ring-2 hover:ring-green-300 transition duration-300">
                <option value="">-- Select Teacher --</option>
                <?php $teacher_result->data_seek(0);
                while ($t = $teacher_result->fetch_assoc()): ?>
                  <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= $t['username'] ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div class="flex justify-end space-x-3 mt-6">
            <button type="button" onclick="document.getElementById('editSubjectModal').classList.add('hidden')"
              class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400 font-semibold">Cancel</button>
            <button type="submit"
              class="px-6 py-2 rounded-lg bg-gradient-to-r from-green-600 to-lime-500 text-white hover:scale-105 hover:shadow-xl font-semibold">
              Update Subject
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>


  <!-- Delete Subject Modal -->
  <div id="deleteSubjectModal" onclick="closeOutside(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="glass bg-white p-6 rounded-2xl shadow-xl w-full max-w-md" onclick="event.stopPropagation();">
      <h2 class="text-xl font-bold text-gray-800 mb-4">❌ Confirm Delete</h2>
      <p class="text-gray-700 mb-6">Are you sure you want to delete this subject?</p>
      <form method="POST" class="flex justify-end gap-4">
        <input type="hidden" name="delete_subject" value="1">
        <input type="hidden" id="delete_subject_id" name="subject_id">
        <button type="button" onclick="document.getElementById('deleteSubjectModal').classList.add('hidden')"
          class="bg-gray-300 px-4 py-2 rounded font-semibold">Cancel</button>
        <button type="submit"
          class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 font-semibold">Delete</button>
      </form>
    </div>
  </div>





  <!-- Change Class Teacher Modal -->
  <div id="changeTeacherModal" onclick="closeOutside(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="modalBox">
      <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-md
      transition duration-300 ease-in-out
      hover:ring-4 hover:ring-blue-400 hover:ring-offset-2
      hover:shadow-[0_0_30px_rgba(59,130,246,0.6)] filter hover:brightness-110" onclick="event.stopPropagation();">

        <h2 class="text-2xl font-bold text-white mb-6 text-center">👨‍🏫 Change Class Teacher</h2>
        <form method="POST" class="space-y-6">
          <input type="hidden" name="change_teacher" value="1">
          <input type="hidden" name="class_id" value="<?= htmlspecialchars($selected_class_id) ?>">

          <label class="block font-medium text-white mb-2">Select New Teacher:</label>
          <select name="teacher_id" required class="w-full px-4 py-2 rounded-lg bg-white/80 border border-white placeholder-gray-600
            focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)]
            hover:ring-2 hover:ring-blue-400 transition duration-300">
            <option value="">-- Select Teacher --</option>
            <?php $teacher_result->data_seek(0);
            while ($t = $teacher_result->fetch_assoc()): ?>
              <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= $t['username'] ?>)</option>
            <?php endwhile; ?>
          </select>

          <div class="flex justify-end space-x-4 pt-4">
            <button type="button" onclick="document.getElementById('changeTeacherModal').classList.add('hidden')"
              class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400 transition font-semibold">
              Cancel
            </button>
            <button type="submit"
              class="px-6 py-2 rounded-lg bg-gradient-to-r from-green-600 to-lime-600 text-white hover:scale-105 hover:shadow-xl transition font-semibold">
              Change
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>







  <!-- Add Subject Modal -->
  <div id="subjectModal" onclick="closeOutside(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="modalBox">
      <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-lg transition duration-300 ease-in-out
      hover:ring-4 hover:ring-blue-400 hover:ring-offset-2
      hover:shadow-[0_0_30px_rgba(59,130,246,0.6)] filter hover:brightness-110" onclick="event.stopPropagation();">

        <h2 class="text-2xl font-bold text-white mb-6 text-center">📚 Add Subject</h2>
        <form method="POST">
          <input type="hidden" name="add_subject" value="1">
          <input type="hidden" name="class_id" value="<?= htmlspecialchars($selected_class_id) ?>">

          <div class="space-y-5">
            <div>
              <label class="block font-medium text-white mb-1">Subject Name</label>
              <input type="text" name="subject_name" required placeholder="e.g. Mathematics" class="w-full px-4 py-2 rounded-lg bg-white/80 border border-white placeholder-gray-600
              focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
              hover:ring-2 hover:ring-blue-400 transition duration-300" />
            </div>

            <div>
              <label class="block font-medium text-white mb-1">Assign Teacher</label>
              <select name="teacher_id" required class="w-full px-4 py-2 rounded-lg bg-white/80 border border-white text-gray-700
              focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)]
              hover:ring-2 hover:ring-blue-400 transition duration-300">
                <option value="">-- Select Teacher --</option>
                <?php $teacher_result->data_seek(0);
                while ($t = $teacher_result->fetch_assoc()): ?>
                  <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= $t['username'] ?>)
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>

          <div class="flex justify-end space-x-4 mt-6">
            <button type="button" onclick="document.getElementById('subjectModal').classList.add('hidden')"
              class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400 transition font-semibold">
              Cancel
            </button>
            <button type="submit"
              class="px-6 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:scale-105 hover:shadow-xl transition font-semibold">
              Add Subject
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>




  <!-- Edit Section Modal -->
  <div id="editSectionModal" onclick="closeOutside(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="modalBox">
      <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-md
      transition duration-300 ease-in-out
      hover:ring-4 hover:ring-blue-400 hover:ring-offset-2
      hover:shadow-[0_0_30px_rgba(59,130,246,0.6)] filter hover:brightness-110" onclick="event.stopPropagation();">

        <h2 class="text-2xl font-bold text-white mb-6 text-center">✏️ Edit Section</h2>
        <form method="POST" class="space-y-6">
          <input type="hidden" name="edit_section" value="1">
          <input type="hidden" name="class_id" value="<?= htmlspecialchars($selected_class_id) ?>">

          <div>
            <label for="section_name" class="block font-medium text-white mb-2">Section Name</label>
            <input type="text" id="section_name" name="section_name" required
              value="<?= htmlspecialchars($class_data['section'] ?? '') ?>" class="w-full px-4 py-2 rounded-lg bg-white/80 border border-white placeholder-gray-600
            focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)]
            hover:ring-2 hover:ring-blue-400 transition duration-300" />
          </div>

          <div class="flex justify-end space-x-4 pt-4">
            <button type="button" onclick="document.getElementById('editSectionModal').classList.add('hidden')"
              class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400 transition font-semibold">
              Cancel
            </button>
            <button type="submit"
              class="px-6 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:scale-105 hover:shadow-xl transition font-semibold">
              Save
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>





  <script>
    function openTab(id) {
      document.querySelectorAll('.tab-content').forEach(tab => tab.classList.add('hidden'));
      document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('border-blue-500', 'text-blue-600'));
      const button = document.querySelector(`[onclick="openTab('${id}')"]`);
      const tab = document.getElementById(id);
      if (tab && button) {
        tab.classList.remove('hidden');
        button.classList.add('border-blue-500', 'text-blue-600');
      }
    }
    <?php if ($selected_class_id): ?>
      window.addEventListener('DOMContentLoaded', () => openTab('students'));
    <?php endif; ?>

    function closeOutside(e) {
      if (e.target.id === 'subjectModal') {
        document.getElementById('subjectModal').classList.add('hidden');
      }
    }


    function openEditSubject(id, name, teacher_id) {
      document.getElementById('edit_subject_id').value = id;
      document.getElementById('edit_subject_name').value = name;
      document.getElementById('edit_teacher_id').value = teacher_id;
      document.getElementById('editSubjectModal').classList.remove('hidden');
    }

    function openDeleteSubject(id) {
      document.getElementById('delete_subject_id').value = id;
      document.getElementById('deleteSubjectModal').classList.remove('hidden');
    }

    function closeOutside(e) {
      if (e.target.id === "addModal") document.getElementById("addModal").classList.add("hidden");
      if (e.target.id === "editSubjectModal") document.getElementById("editSubjectModal").classList.add("hidden");
      if (e.target.id === "deleteSubjectModal") document.getElementById("deleteSubjectModal").classList.add("hidden");
      if (e.target.id === "changeTeacherModal") document.getElementById("changeTeacherModal").classList.add("hidden");
      if (e.target.id === "subjectModal") document.getElementById("subjectModal").classList.add("hidden");
      if (e.target.id === "editSectionModal") document.getElementById("editSectionModal").classList.add("hidden");
    }



  </script>
</body>

</html>