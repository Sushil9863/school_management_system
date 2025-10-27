<?php
include '../partials/dbconnect.php';
include 'check_admin.php'; // assume session & admin check done here

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
  die("Access denied. School not identified.");
}

$assigned_teachers = [];
$res = $conn->query("SELECT class_teacher_id FROM classes WHERE class_teacher_id IS NOT NULL AND school_id = $school_id");
while ($row = $res->fetch_assoc()) {
  $assigned_teachers[] = $row['class_teacher_id'];
}

$existingClassPairs = [];
$result = $conn->query("SELECT grade, section FROM classes WHERE school_id = $school_id");
while ($row = $result->fetch_assoc()) {
  $existingClassPairs[] = ['grade' => strtolower($row['grade']), 'section' => strtolower($row['section'])];
}

// Fetch teachers for this school
$stmt = $conn->prepare("SELECT id, username, full_name FROM teachers WHERE school_id = ? ORDER BY full_name ASC");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$teacher_result = $stmt->get_result();

// Handle Edit Section
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_section'])) {
  $class_id = $_POST['class_id'];
  $new_section_name = trim($_POST['section_name']);
  
  // Server-side validation
  $errors = [];
  
  if (empty($new_section_name)) {
    $errors[] = "Section name cannot be empty.";
  } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $new_section_name)) {
    $errors[] = "Section name can only contain letters and numbers.";
  } elseif (strlen($new_section_name) > 10) {
    $errors[] = "Section name cannot exceed 10 characters.";
  }
  
  // Verify class belongs to this school
  $stmtCheck = $conn->prepare("SELECT id, grade FROM classes WHERE id = ? AND school_id = ?");
  $stmtCheck->bind_param("ii", $class_id, $school_id);
  $stmtCheck->execute();
  $classResult = $stmtCheck->get_result();
  
  if ($classResult->num_rows === 0) {
    $errors[] = "Unauthorized section edit.";
  } else {
    $classData = $classResult->fetch_assoc();
    $grade = $classData['grade'];
    
    // Check if section already exists for this grade (case-insensitive)
    $stmtCheck = $conn->prepare("SELECT id FROM classes WHERE LOWER(grade) = LOWER(?) AND LOWER(section) = LOWER(?) AND school_id = ? AND id != ?");
    $stmtCheck->bind_param("ssii", $grade, $new_section_name, $school_id, $class_id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
      $errors[] = "This section already exists for grade $grade.";
    }
  }
  
  if (empty($errors)) {
    $stmt = $conn->prepare("UPDATE classes SET section = ? WHERE id = ?");
    $stmt->bind_param("si", $new_section_name, $class_id);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE sections SET section_name = ? WHERE class_id = ?");
    $stmt->bind_param("si", $new_section_name, $class_id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . urlencode($class_id) . "&success=Section updated successfully");
    exit;
  } else {
    $error_message = implode("<br>", $errors);
    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . urlencode($class_id) . "&error=" . urlencode($error_message));
    exit;
  }
}

// Handle Change Class Teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_teacher'])) {
  $class_id = $_POST['class_id'];
  $new_teacher_id = $_POST['teacher_id'];
  
  $errors = [];
  
  if (empty($new_teacher_id)) {
    $errors[] = "Please select a teacher.";
  }

  // Check that the class belongs to the current school
  $stmtCheck = $conn->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ?");
  $stmtCheck->bind_param("ii", $class_id, $school_id);
  $stmtCheck->execute();
  if ($stmtCheck->get_result()->num_rows === 0) {
    $errors[] = "Unauthorized teacher change.";
  }

  // Check that the teacher belongs to the current school
  $stmt = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND school_id = ?");
  $stmt->bind_param("ii", $new_teacher_id, $school_id);
  $stmt->execute();
  $result = $stmt->get_result();
  if ($result->num_rows === 0) {
    $errors[] = "Invalid teacher selected.";
  }

  // Check if teacher is already assigned as class teacher to another class
  $stmtCheck = $conn->prepare("SELECT id FROM classes WHERE class_teacher_id = ? AND id != ? AND school_id = ?");
  $stmtCheck->bind_param("iii", $new_teacher_id, $class_id, $school_id);
  $stmtCheck->execute();
  if ($stmtCheck->get_result()->num_rows > 0) {
    $errors[] = "This teacher is already assigned as class teacher to another class.";
  }

  if (empty($errors)) {
    // Store teacher ID in class_teacher field
    $stmt = $conn->prepare("UPDATE classes SET class_teacher_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_teacher_id, $class_id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . urlencode($class_id) . "&success=Class teacher updated successfully");
    exit;
  } else {
    $error_message = implode("<br>", $errors);
    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . urlencode($class_id) . "&error=" . urlencode($error_message));
    exit;
  }
}

// Handle Add Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_subject'])) {
  $subject_name = trim($_POST['subject_name']);
  $class_id = $_POST['class_id'];
  $teacher_id = $_POST['teacher_id'];
  
  $errors = [];
  
  if (empty($subject_name)) {
    $errors[] = "Subject name cannot be empty.";
  } elseif (!preg_match('/^[a-zA-Z0-9 ]+$/', $subject_name)) {
    $errors[] = "Subject name can only contain letters, numbers and spaces.";
  } elseif (strlen($subject_name) > 50) {
    $errors[] = "Subject name cannot exceed 50 characters.";
  }
  
  if (empty($teacher_id)) {
    $errors[] = "Please select a teacher.";
  }

  // Check class belongs to school
  $stmtCheck = $conn->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ?");
  $stmtCheck->bind_param("ii", $class_id, $school_id);
  $stmtCheck->execute();
  if ($stmtCheck->get_result()->num_rows === 0) {
    $errors[] = "Unauthorized action.";
  }

  // Check teacher belongs to school
  $stmtCheck2 = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND school_id = ?");
  $stmtCheck2->bind_param("ii", $teacher_id, $school_id);
  $stmtCheck2->execute();
  if ($stmtCheck2->get_result()->num_rows === 0) {
    $errors[] = "Invalid teacher selected.";
  }
  
  // Check if subject already exists for this class
  $stmtCheck = $conn->prepare("SELECT id FROM subjects WHERE class_id = ? AND LOWER(name) = LOWER(?)");
  $stmtCheck->bind_param("is", $class_id, $subject_name);
  $stmtCheck->execute();
  if ($stmtCheck->get_result()->num_rows > 0) {
    $errors[] = "This subject already exists for this class.";
  }

  if (empty($errors)) {
    $stmt = $conn->prepare("INSERT INTO subjects (school_id, name, class_id, teacher_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isii", $school_id, $subject_name, $class_id, $teacher_id);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . $class_id . "&success=Subject added successfully");
    exit;
  } else {
    $error_message = implode("<br>", $errors);
    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . $class_id . "&error=" . urlencode($error_message));
    exit;
  }
}

// Handle Edit Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_subject'])) {
  $subject_id = $_POST['subject_id'];
  $subject_name = trim($_POST['subject_name']);
  $teacher_id = $_POST['teacher_id'];
  
  $errors = [];
  
  if (empty($subject_name)) {
    $errors[] = "Subject name cannot be empty.";
  } elseif (!preg_match('/^[a-zA-Z0-9 ]+$/', $subject_name)) {
    $errors[] = "Subject name can only contain letters, numbers and spaces.";
  } elseif (strlen($subject_name) > 50) {
    $errors[] = "Subject name cannot exceed 50 characters.";
  }
  
  if (empty($teacher_id)) {
    $errors[] = "Please select a teacher.";
  }

  // Check subject belongs to this school via class
  $stmtCheck = $conn->prepare("SELECT s.id, s.class_id FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.id = ? AND c.school_id = ?");
  $stmtCheck->bind_param("ii", $subject_id, $school_id);
  $stmtCheck->execute();
  $subjectResult = $stmtCheck->get_result();
  
  if ($subjectResult->num_rows === 0) {
    $errors[] = "Unauthorized action.";
  } else {
    $subjectData = $subjectResult->fetch_assoc();
    $class_id = $subjectData['class_id'];
    
    // Check if subject name already exists for this class (excluding current subject)
    $stmtCheck = $conn->prepare("SELECT id FROM subjects WHERE class_id = ? AND LOWER(name) = LOWER(?) AND id != ?");
    $stmtCheck->bind_param("isi", $class_id, $subject_name, $subject_id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
      $errors[] = "This subject already exists for this class.";
    }
  }

  // Check teacher belongs to school
  $stmtCheck2 = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND school_id = ?");
  $stmtCheck2->bind_param("ii", $teacher_id, $school_id);
  $stmtCheck2->execute();
  if ($stmtCheck2->get_result()->num_rows === 0) {
    $errors[] = "Invalid teacher selected.";
  }

  if (empty($errors)) {
    $stmt = $conn->prepare("UPDATE subjects SET name = ?, teacher_id = ? WHERE id = ?");
    $stmt->bind_param("sii", $subject_name, $teacher_id, $subject_id);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . $_GET['class_id'] . "&success=Subject updated successfully");
    exit;
  } else {
    $error_message = implode("<br>", $errors);
    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . $_GET['class_id'] . "&error=" . urlencode($error_message));
    exit;
  }
}

// Handle Delete Subject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subject'])) {
  $subject_id = $_POST['subject_id'];

  // Check subject belongs to this school
  $stmtCheck = $conn->prepare("SELECT s.id FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.id = ? AND c.school_id = ?");
  $stmtCheck->bind_param("ii", $subject_id, $school_id);
  $stmtCheck->execute();
  if ($stmtCheck->get_result()->num_rows === 0) {
    die("Unauthorized action.");
  }

  $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
  $stmt->bind_param("i", $subject_id);
  $stmt->execute();
  header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . $_GET['class_id'] . "&success=Subject deleted successfully");
  exit;
}

// Handle Add Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
  $grade = trim($_POST['grade']);
  $section = trim($_POST['section']);
  $class_type = $_POST['class_type'];
  $teacher_id = $_POST['class_teacher_id'];
  
  $errors = [];
  
  // Validate grade
  $validGrades = ['nursery', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
  if (empty($grade)) {
    $errors[] = "Grade cannot be empty.";
  } elseif (!in_array(strtolower($grade), $validGrades)) {
    $errors[] = "Grade must be between Nursery and 12.";
  }
  
  // Validate section
  if (empty($section)) {
    $errors[] = "Section cannot be empty.";
  } elseif (!preg_match('/^[a-zA-Z0-9]+$/', $section)) {
    $errors[] = "Section can only contain letters and numbers.";
  } elseif (strlen($section) > 10) {
    $errors[] = "Section cannot exceed 10 characters.";
  }
  
  // Validate class type
  $validTypes = ['pre-primary', 'primary', 'secondary'];
  if (empty($class_type) || !in_array($class_type, $validTypes)) {
    $errors[] = "Invalid class type selected.";
  }
  
  // Validate teacher
  if (empty($teacher_id)) {
    $errors[] = "Please select a class teacher.";
  }

  // Verify teacher belongs to school
  $stmtCheck = $conn->prepare("SELECT full_name FROM teachers WHERE id = ? AND school_id = ?");
  $stmtCheck->bind_param("ii", $teacher_id, $school_id);
  $stmtCheck->execute();
  $result = $stmtCheck->get_result();
  if ($result->num_rows === 0) {
    $errors[] = "Invalid class teacher selected.";
  } else {
    // Check if teacher is already assigned to another class
    $stmtCheck = $conn->prepare("SELECT id FROM classes WHERE class_teacher_id = ? AND school_id = ?");
    $stmtCheck->bind_param("ii", $teacher_id, $school_id);
    $stmtCheck->execute();
    if ($stmtCheck->get_result()->num_rows > 0) {
      $errors[] = "This teacher is already assigned as class teacher to another class.";
    }
  }
  
  // Check if class already exists (case-insensitive)
  $stmtCheck = $conn->prepare("SELECT id FROM classes WHERE LOWER(grade) = LOWER(?) AND LOWER(section) = LOWER(?) AND school_id = ?");
  $stmtCheck->bind_param("ssi", $grade, $section, $school_id);
  $stmtCheck->execute();
  if ($stmtCheck->get_result()->num_rows > 0) {
    $errors[] = "A class with grade '$grade' and section '$section' already exists.";
  }

  if (empty($errors)) {
    $stmt = $conn->prepare("INSERT INTO classes (grade, section, type, class_teacher_id, school_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssii", $grade, $section, $class_type, $teacher_id, $school_id);
    $stmt->execute();

    $class_id = $conn->insert_id;

    $stmt = $conn->prepare("INSERT INTO sections (class_id, section_name, school_id) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $class_id, $section, $school_id);
    $stmt->execute();

    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . $class_id . "&success=Class added successfully");
    exit;
  } else {
    $error_message = implode("<br>", $errors);
    header("Location: " . $_SERVER['PHP_SELF'] . "?error=" . urlencode($error_message));
    exit;
  }
}

// Handle Delete Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_class'])) {
  $class_id = $_POST['class_id'];

  // Verify class belongs to this school
  $stmtCheck = $conn->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ?");
  $stmtCheck->bind_param("ii", $class_id, $school_id);
  $stmtCheck->execute();
  if ($stmtCheck->get_result()->num_rows === 0) {
    die("Unauthorized class delete.");
  }

  // Start transaction
  $conn->begin_transaction();

  try {
    // Delete related students
    $stmt = $conn->prepare("DELETE FROM students WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();

    // Delete related subjects
    $stmt = $conn->prepare("DELETE FROM subjects WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();

    // Delete related sections
    $stmt = $conn->prepare("DELETE FROM sections WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();

    // Delete related payments
    $stmt = $conn->prepare("DELETE FROM payments WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();

    // Delete the class itself
    $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();

    $conn->commit();
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=Class deleted successfully");
    exit;
  } catch (Exception $e) {
    $conn->rollback();
    header("Location: " . $_SERVER['PHP_SELF'] . "?class_id=" . $class_id . "&error=Failed to delete class: " . urlencode($e->getMessage()));
    exit;
  }
}

// Fetch classes for this school
$stmt = $conn->prepare("SELECT * FROM classes WHERE school_id = ? ORDER BY grade ASC, section ASC");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$class_result = $stmt->get_result();

$students = $subjects = null;
$selected_class_id = $_GET['class_id'] ?? null;

if ($selected_class_id) {
  // Validate class belongs to this school
  $stmtCheck = $conn->prepare("SELECT * FROM classes WHERE id = ? AND school_id = ?");
  $stmtCheck->bind_param("ii", $selected_class_id, $school_id);
  $stmtCheck->execute();
  $classDataResult = $stmtCheck->get_result();
  if ($classDataResult->num_rows === 0) {
    die("Unauthorized class access.");
  }
  $class_data = $classDataResult->fetch_assoc();

  $stmt = $conn->prepare("SELECT st.*, p.full_name as parent_name 
                        FROM students st 
                        LEFT JOIN parents p ON st.parent_id = p.id 
                        WHERE st.class_id = ?");
  $stmt->bind_param("i", $selected_class_id);
  $stmt->execute();
  $students = $stmt->get_result();

  $stmt = $conn->prepare("SELECT s.*, t.full_name as teacher_name FROM subjects s JOIN teachers t ON s.teacher_id = t.id WHERE s.class_id = ?");
  $stmt->bind_param("i", $selected_class_id);
  $stmt->execute();
  $subjects = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Manage Classes</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .glass {
      background: rgba(255, 255, 255, 0.7);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
    }

    /* New styles for glow effects */
    .modal-glow-blue {
      transition: all 0.3s ease;
      box-shadow: 0 0 15px rgba(59, 130, 246, 0);
    }

    .modal-glow-blue:hover {
      box-shadow: 0 0 25px rgba(59, 130, 246, 0.8);
    }

    .modal-glow-green {
      transition: all 0.3s ease;
      box-shadow: 0 0 15px rgba(34, 197, 94, 0);
    }

    .modal-glow-green:hover {
      box-shadow: 0 0 25px rgba(34, 197, 94, 0.8);
    }

    .modal-glow-red {
      transition: all 0.3s ease;
      box-shadow: 0 0 15px rgba(239, 68, 68, 0);
    }

    .modal-glow-red:hover {
      box-shadow: 0 0 25px rgba(239, 68, 68, 0.8);
    }

    /* Professional search bar */
    .search-container {
      position: relative;
      width: 100%;
      max-width: 400px;
    }

    .search-input {
      padding-left: 40px;
      border-radius: 8px;
      border: 1px solid #d1d5db;
      transition: all 0.3s ease;
      height: 42px;
    }

    .search-input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }

    .search-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
    }

    /* Modal transparency */
    .modal-transparent {
      background: rgba(255, 255, 255, 0.85);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    /* Toast notification */
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      border-radius: 8px;
      color: white;
      z-index: 1000;
      opacity: 0;
      transition: opacity 0.5s ease-in-out;
      max-width: 400px;
    }
    
    .toast-success {
      background-color: #10B981;
    }
    
    .toast-error {
      background-color: #EF4444;
    }
    
    .toast-show {
      opacity: 1;
    }

    /* Button spacing fix for responsive */
    .space-x-2 {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      justify-content: center;
    }
  </style>
</head>

<body class="bg-gray-100 p-6">
  <!-- Toast Notification -->
  <?php if (isset($_GET['success'])): ?>
    <div id="toast" class="toast toast-success">
      <?= htmlspecialchars($_GET['success']) ?>
    </div>
  <?php endif; ?>
  
  <?php if (isset($_GET['error'])): ?>
    <div id="toast" class="toast toast-error">
      <?= htmlspecialchars($_GET['error']) ?>
    </div>
  <?php endif; ?>

  <div class="max-w-7xl mx-auto bg-white p-6 rounded-lg shadow space-y-8">
    <div class="flex justify-between items-center">
      <h1 class="text-3xl font-bold text-gray-800">📘 Manage Classes</h1>
      <div>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')"
          class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg">+ Add Class</button>
        <?php if ($selected_class_id): ?>
          <button onclick="document.getElementById('deleteClassModal').classList.remove('hidden')"
            class="ml-4 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">Delete Class</button>
        <?php endif; ?>
      </div>
    </div>

    <div class="flex items-center space-x-4">
      <!-- Class dropdown -->
      <select id="classSelect"
        class="border px-4 py-2 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
        <option value="">-- Select a Class --</option>
        <?php
        $class_result->data_seek(0);
        while ($class = $class_result->fetch_assoc()): ?>
          <option value="<?= $class['id'] ?>" <?= ($selected_class_id == $class['id']) ? 'selected' : '' ?>>
            Grade <?= htmlspecialchars($class['grade']) ?> - <?= htmlspecialchars($class['section']) ?>
          </option>
        <?php endwhile; ?>
      </select>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 mt-6">
      <nav class="-mb-px flex space-x-8" id="tabs">
        <button class="tab-btn border-b-2 border-blue-600 text-blue-600 font-semibold py-2 px-4 cursor-pointer"
          data-tab="students" onclick="openTab('students')">Students</button>
        <button class="tab-btn border-b-2 border-transparent text-gray-500 py-2 px-4 cursor-pointer" data-tab="subjects"
          onclick="openTab('subjects')">Subjects</button>
        <button class="tab-btn border-b-2 border-transparent text-gray-500 py-2 px-4 cursor-pointer" data-tab="teacher"
          onclick="openTab('teacher')">Class Teacher</button>
        <button class="tab-btn border-b-2 border-transparent text-gray-500 py-2 px-4 cursor-pointer" data-tab="sections"
          onclick="openTab('sections')">Sections</button>
      </nav>
    </div>

    <!-- Tab Contents -->
    <div id="students" class="tab-content mt-6">
      <?php if ($students && $students->num_rows > 0): ?>
        <table class="w-full border border-gray-300 rounded-md">
          <thead class="bg-gray-200">
            <tr>
              <th class="p-3 border border-gray-300">Full Name</th>
              <th class="p-3 border border-gray-300">Gender</th>
              <th class="p-3 border border-gray-300">DOB</th>
              <th class="p-3 border border-gray-300">Parent</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($student = $students->fetch_assoc()): ?>
              <tr>
                <td class="border p-2"><?= htmlspecialchars($student['full_name']) ?></td>
                <td class="border p-2"><?= htmlspecialchars($student['gender']) ?></td>
                <td class="border p-2"><?= htmlspecialchars($student['dob']) ?></td>
                <td class="border p-2"><?= htmlspecialchars($student['parent_name'] ?? 'N/A') ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-500">No students found for this class.</p>
      <?php endif; ?>
    </div>

    <div id="subjects" class="tab-content mt-6 hidden">
      <button onclick="document.getElementById('addSubjectModal').classList.remove('hidden')"
        class="mb-4 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">+ Add Subject</button>
      <?php if ($subjects && $subjects->num_rows > 0): ?>
        <table class="w-full border border-gray-300 rounded-md">
          <thead class="bg-gray-200">
            <tr>
              <th class="p-3 border border-gray-300">Subject Name</th>
              <th class="p-3 border border-gray-300">Teacher</th>
              <th class="p-3 border border-gray-300">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($subject = $subjects->fetch_assoc()): ?>
              <tr>
                <td class="border p-2"><?= htmlspecialchars($subject['name']) ?></td>
                <td class="border p-2"><?= htmlspecialchars($subject['teacher_name']) ?></td>
                <td class="border p-2 space-x-2">
                  <button
                    onclick="openEditSubjectModal(<?= $subject['id'] ?>, '<?= addslashes(htmlspecialchars($subject['name'])) ?>', <?= $subject['teacher_id'] ?>)"
                    class="text-blue-600 hover:underline">Edit</button>
                  <form method="post" class="inline" onsubmit="return confirm('Delete this subject?');">
                    <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                    <button type="submit" name="delete_subject" class="text-red-600 hover:underline">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-gray-500">No subjects found for this class.</p>
      <?php endif; ?>
    </div>

    <div id="teacher" class="tab-content mt-6 hidden">
      <?php if ($selected_class_id): ?>
        <?php
        // Get the class teacher ID
        $teacher_id = $class_data['class_teacher_id'] ?? null;
        $teacher_name = 'Not assigned';

        if ($teacher_id) {
          // Query to get the full name of the teacher
          $stmt = $conn->prepare("SELECT full_name FROM teachers WHERE id = ?");
          $stmt->bind_param("i", $teacher_id);
          $stmt->execute();
          $result = $stmt->get_result();
          if ($row = $result->fetch_assoc()) {
            $teacher_name = htmlspecialchars($row['full_name']);
          }
          $stmt->close();
        }
        ?>

        <p><strong>Current Class Teacher:</strong> <?= $teacher_name ?></p>
        <button onclick="document.getElementById('changeTeacherModal').classList.remove('hidden')"
          class="mt-4 bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">Change Class Teacher</button>
      <?php else: ?>
        <p class="text-gray-500">Select a class first.</p>
      <?php endif; ?>
    </div>

    <div id="sections" class="tab-content mt-6 hidden">
      <?php if ($selected_class_id): ?>
        <p><strong>Current Section:</strong> <?= htmlspecialchars($class_data['section']) ?></p>
        <button onclick="document.getElementById('editSectionModal').classList.remove('hidden')"
          class="mt-4 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">Edit Section</button>
      <?php else: ?>
        <p class="text-gray-500">Select a class first.</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Add Class Modal (Blue Glow) -->
  <div id="addModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    onclick="closeOutside(event, 'addModal')">
    <div class="modal-transparent modal-glow-blue p-6 rounded-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <h2 class="text-xl font-bold text-gray-800 mb-4 text-center">➕ Add New Class</h2>
      <form method="POST" action="" id="addClassForm" onsubmit="return validateAddClassForm()">
        <div class="mb-4">
          <label class="block mb-1 font-semibold text-gray-700">Grade</label>
          <input type="text" name="grade" id="grade" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 placeholder-gray-500 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Enter Grade (e.g., Nursery, 1-12)" />
          <span id="gradeError" class="text-red-500 text-sm hidden">Grade must be between Nursery and 12.</span>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold text-gray-700">Section</label>
          <input type="text" name="section" id="section" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 placeholder-gray-500 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Enter Section (e.g., A, B, 1)" />
          <span id="sectionError" class="text-red-500 text-sm hidden">Section must be 1-10 characters and contain only letters and numbers.</span>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold text-gray-700">Class Type</label>
          <select name="class_type" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="pre-primary">Pre Primary</option>
            <option value="primary">Primary</option>
            <option value="secondary">Secondary</option>
          </select>
        </div>
        <div class="mb-6">
          <label class="block mb-1 font-semibold text-gray-700">Class Teacher</label>
          <select name="class_teacher_id" id="classTeacher" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Select Teacher</option>
            <?php
            $teacher_result->data_seek(0);
            while ($teacher = $teacher_result->fetch_assoc()):
              // Show only teachers not assigned as class teachers
              if (!in_array($teacher['id'], $assigned_teachers)):
                ?>
                <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['full_name']) ?></option>
                <?php
              endif;
            endwhile;
            ?>
          </select>
          <span id="teacherError" class="text-red-500 text-sm hidden">Please select a teacher.</span>
        </div>
        <div class="flex justify-end space-x-4">
          <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')"
            class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100 transition">Cancel</button>
          <button type="submit" name="add_class"
            class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 shadow-lg transition">Add Class</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Section Modal (Green Glow) -->
  <div id="editSectionModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    onclick="closeOutside(event, 'editSectionModal')">
    <div class="modal-transparent modal-glow-green p-6 rounded-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <h2 class="text-xl font-bold text-gray-800 mb-4">✏️ Edit Section</h2>
      <form method="POST" action="" id="editSectionForm" onsubmit="return validateEditSectionForm()">
        <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
        <div class="mb-4">
          <label class="block mb-1 font-semibold text-gray-700">Section Name</label>
          <input type="text" name="section_name" id="editSectionName" value="<?= htmlspecialchars($class_data['section'] ?? '') ?>" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500" />
          <span id="editSectionError" class="text-red-500 text-sm hidden">Section name must be 1-10 characters and contain only letters and numbers.</span>
        </div>
        <div class="flex justify-end space-x-4">
          <button type="button" onclick="document.getElementById('editSectionModal').classList.add('hidden')"
            class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100 transition">Cancel</button>
          <button type="submit" name="edit_section"
            class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700 shadow-lg transition">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Change Class Teacher Modal (Blue Glow) -->
  <div id="changeTeacherModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    onclick="closeOutside(event, 'changeTeacherModal')">
    <div class="modal-transparent modal-glow-blue p-6 rounded-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <h2 class="text-xl font-bold text-gray-800 mb-4">👨‍🏫 Change Class Teacher</h2>
      <form method="POST" action="" id="changeTeacherForm" onsubmit="return validateChangeTeacherForm()">
        <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
        <div class="mb-4">
          <label class="block mb-1 font-semibold text-gray-700">Select Teacher</label>
          <select name="teacher_id" id="newTeacherId" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="">Select Teacher</option>
            <?php
            $teacher_result->data_seek(0);
            while ($teacher = $teacher_result->fetch_assoc()):
              $is_current_teacher = $teacher['id'] == $class_data['class_teacher_id'];
              $is_already_assigned = in_array($teacher['id'], $assigned_teachers);

              if (!$is_already_assigned || $is_current_teacher): ?>
                <option value="<?= $teacher['id'] ?>" <?= $is_current_teacher ? 'selected' : '' ?>>
                  <?= htmlspecialchars($teacher['full_name']) ?>
                </option>
              <?php endif; ?>
            <?php endwhile; ?>
          </select>
          <span id="teacherSelectError" class="text-red-500 text-sm hidden">Please select a teacher.</span>
        </div>
        <div class="flex justify-end space-x-4">
          <button type="button" onclick="document.getElementById('changeTeacherModal').classList.add('hidden')"
            class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100 transition">Cancel</button>
          <button type="submit" name="change_teacher"
            class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 shadow-lg transition">Change</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Subject Modal (Green Glow) -->
  <div id="addSubjectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    onclick="closeOutside(event, 'addSubjectModal')">
    <div class="modal-transparent modal-glow-green p-6 rounded-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <h2 class="text-xl font-bold text-gray-800 mb-4">➕ Add Subject</h2>
      <form method="POST" action="" id="addSubjectForm" onsubmit="return validateAddSubjectForm()">
        <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
        <div class="mb-4">
          <label class="block mb-1 font-semibold text-gray-700">Subject Name</label>
          <input type="text" name="subject_name" id="subjectName" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500" />
          <span id="subjectNameError" class="text-red-500 text-sm hidden">Subject name must be 1-50 characters and contain only letters, numbers and spaces.</span>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold text-gray-700">Assign Teacher</label>
          <select name="teacher_id" id="subjectTeacher" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500">
            <option value="">Select Teacher</option>
            <?php
            $teacher_result->data_seek(0);
            while ($teacher = $teacher_result->fetch_assoc()): ?>
              <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['full_name']) ?></option>
            <?php endwhile; ?>
          </select>
          <span id="subjectTeacherError" class="text-red-500 text-sm hidden">Please select a teacher.</span>
        </div>
        <div class="flex justify-end space-x-4">
          <button type="button" onclick="document.getElementById('addSubjectModal').classList.add('hidden')"
            class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100 transition">Cancel</button>
          <button type="submit" name="add_subject"
            class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700 shadow-lg transition">Add Subject</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Subject Modal (Green Glow) -->
  <div id="editSubjectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    onclick="closeOutside(event, 'editSubjectModal')">
    <div class="modal-transparent modal-glow-green p-6 rounded-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <h2 class="text-xl font-bold text-gray-800 mb-4">✏️ Edit Subject</h2>
      <form method="POST" action="" id="editSubjectForm" onsubmit="return validateEditSubjectForm()">
        <input type="hidden" name="subject_id" id="editSubjectId" />
        <div class="mb-4">
          <label class="block mb-1 font-semibold text-gray-700">Subject Name</label>
          <input type="text" name="subject_name" id="editSubjectName" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500" />
          <span id="editSubjectNameError" class="text-red-500 text-sm hidden">Subject name must be 1-50 characters and contain only letters, numbers and spaces.</span>
        </div>
        <div class="mb-4">
          <label class="block mb-1 font-semibold text-gray-700">Assign Teacher</label>
          <select name="teacher_id" id="editSubjectTeacherId" required
            class="w-full px-4 py-2 rounded-lg bg-white bg-opacity-70 text-gray-800 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-green-500">
            <option value="">Select Teacher</option>
            <?php
            $teacher_result->data_seek(0);
            while ($teacher = $teacher_result->fetch_assoc()): ?>
              <option value="<?= $teacher['id'] ?>"><?= htmlspecialchars($teacher['full_name']) ?></option>
            <?php endwhile; ?>
          </select>
          <span id="editSubjectTeacherError" class="text-red-500 text-sm hidden">Please select a teacher.</span>
        </div>
        <div class="flex justify-end space-x-4">
          <button type="button" onclick="document.getElementById('editSubjectModal').classList.add('hidden')"
            class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100 transition">Cancel</button>
          <button type="submit" name="edit_subject"
            class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700 shadow-lg transition">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Class Modal (Red Glow) -->
  <div id="deleteClassModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
    onclick="closeOutside(event, 'deleteClassModal')">
    <div class="modal-transparent modal-glow-red p-6 rounded-2xl w-full max-w-md" onclick="event.stopPropagation();">
      <h2 class="text-xl font-bold text-red-700 mb-4">❌ Confirm Delete Class</h2>
      <p class="mb-6 text-gray-700">Are you sure you want to delete this class? This action cannot be undone and will delete all associated students, subjects, and records.</p>
      <form method="POST" action="">
        <input type="hidden" name="delete_class" value="1" />
        <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
        <div class="flex justify-end space-x-4">
          <button type="button" onclick="document.getElementById('deleteClassModal').classList.add('hidden')"
            class="px-4 py-2 rounded border border-gray-300 text-gray-700 hover:bg-gray-100 transition">Cancel</button>
          <button type="submit" onclick="return confirm('Are you absolutely sure? This will permanently delete all class data.');"
            class="px-4 py-2 rounded bg-red-600 text-white hover:bg-red-700 shadow-lg transition">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Show toast notification
    const toast = document.getElementById('toast');
    if (toast) {
      toast.classList.add('toast-show');
      setTimeout(() => {
        toast.classList.remove('toast-show');
      }, 5000);
    }

    function closeOutside(event, modalId) {
      if (event.target.id === modalId) {
        document.getElementById(modalId).classList.add('hidden');
      }
    }

    // Tabs
    const tabs = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    function openTab(tab) {
      tabContents.forEach(c => c.classList.add('hidden'));
      tabs.forEach(t => {
        t.classList.remove('border-blue-600', 'text-blue-600', 'font-semibold');
        t.classList.add('border-transparent', 'text-gray-500');
      });

      document.getElementById(tab).classList.remove('hidden');
      const activeTab = [...tabs].find(t => t.dataset.tab === tab);
      activeTab.classList.add('border-blue-600', 'text-blue-600', 'font-semibold');
      activeTab.classList.remove('border-transparent', 'text-gray-500');
    }

    // Default open students tab
    openTab('students');

    // On class select change
    document.getElementById('classSelect').addEventListener('change', function () {
      const selectedId = this.value;
      if (selectedId) {
        window.location.href = "<?= $_SERVER['PHP_SELF'] ?>?class_id=" + selectedId;
      } else {
        window.location.href = "<?= $_SERVER['PHP_SELF'] ?>";
      }
    });

    // Open Edit Subject Modal and fill values
    function openEditSubjectModal(id, name, teacherId) {
      document.getElementById('editSubjectId').value = id;
      document.getElementById('editSubjectName').value = name;
      document.getElementById('editSubjectTeacherId').value = teacherId;
      document.getElementById('editSubjectModal').classList.remove('hidden');
      
      // Trigger validation for the opened modal
      validateEditSubjectName();
      validateEditSubjectTeacher();
    }

    // Validation Functions
    function validateGrade() {
      const grade = document.getElementById('grade').value.trim().toLowerCase();
      const validGrades = ['nursery', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
      const isValid = validGrades.includes(grade);
      
      if (grade === '') {
        document.getElementById('gradeError').classList.add('hidden');
        return false;
      }
      
      document.getElementById('gradeError').classList.toggle('hidden', isValid);
      return isValid;
    }

    function validateSection() {
      const section = document.getElementById('section').value.trim();
      const isValid = /^[a-zA-Z0-9]{1,10}$/.test(section);
      
      if (section === '') {
        document.getElementById('sectionError').classList.add('hidden');
        return false;
      }
      
      document.getElementById('sectionError').classList.toggle('hidden', isValid);
      return isValid;
    }

    function validateClassTeacher() {
      const teacher = document.getElementById('classTeacher').value;
      const isValid = teacher !== '';
      
      document.getElementById('teacherError').classList.toggle('hidden', isValid);
      return isValid;
    }

    function validateEditSectionName() {
      const sectionName = document.getElementById('editSectionName').value.trim();
      const isValid = /^[a-zA-Z0-9]{1,10}$/.test(sectionName);
      
      if (sectionName === '') {
        document.getElementById('editSectionError').classList.add('hidden');
        return false;
      }
      
      document.getElementById('editSectionError').classList.toggle('hidden', isValid);
      return isValid;
    }

    function validateNewTeacher() {
      const teacherId = document.getElementById('newTeacherId').value;
      const isValid = teacherId !== '';
      
      document.getElementById('teacherSelectError').classList.toggle('hidden', isValid);
      return isValid;
    }

    function validateSubjectName() {
      const subjectName = document.getElementById('subjectName').value.trim();
      const isValid = /^[a-zA-Z0-9 ]{1,50}$/.test(subjectName);
      
      if (subjectName === '') {
        document.getElementById('subjectNameError').classList.add('hidden');
        return false;
      }
      
      document.getElementById('subjectNameError').classList.toggle('hidden', isValid);
      return isValid;
    }

    function validateSubjectTeacher() {
      const teacherId = document.getElementById('subjectTeacher').value;
      const isValid = teacherId !== '';
      
      document.getElementById('subjectTeacherError').classList.toggle('hidden', isValid);
      return isValid;
    }

    function validateEditSubjectName() {
      const subjectName = document.getElementById('editSubjectName').value.trim();
      const isValid = /^[a-zA-Z0-9 ]{1,50}$/.test(subjectName);
      
      if (subjectName === '') {
        document.getElementById('editSubjectNameError').classList.add('hidden');
        return false;
      }
      
      document.getElementById('editSubjectNameError').classList.toggle('hidden', isValid);
      return isValid;
    }

    function validateEditSubjectTeacher() {
      const teacherId = document.getElementById('editSubjectTeacherId').value;
      const isValid = teacherId !== '';
      
      document.getElementById('editSubjectTeacherError').classList.toggle('hidden', isValid);
      return isValid;
    }

    // Form Validation Functions
    function validateAddClassForm() {
      const gradeValid = validateGrade();
      const sectionValid = validateSection();
      const teacherValid = validateClassTeacher();
      
      return gradeValid && sectionValid && teacherValid;
    }
    
    function validateEditSectionForm() {
      return validateEditSectionName();
    }
    
    function validateChangeTeacherForm() {
      return validateNewTeacher();
    }
    
    function validateAddSubjectForm() {
      const nameValid = validateSubjectName();
      const teacherValid = validateSubjectTeacher();
      
      return nameValid && teacherValid;
    }
    
    function validateEditSubjectForm() {
      const nameValid = validateEditSubjectName();
      const teacherValid = validateEditSubjectTeacher();
      
      return nameValid && teacherValid;
    }
    
    // Add real-time validation event listeners
    document.addEventListener('DOMContentLoaded', function() {
      // Add Class Form
      document.getElementById('grade').addEventListener('keyup', validateGrade);
      document.getElementById('grade').addEventListener('change', validateGrade);
      
      document.getElementById('section').addEventListener('keyup', validateSection);
      document.getElementById('section').addEventListener('change', validateSection);
      
      document.getElementById('classTeacher').addEventListener('change', validateClassTeacher);
      
      // Edit Section Form
      document.getElementById('editSectionName').addEventListener('keyup', validateEditSectionName);
      document.getElementById('editSectionName').addEventListener('change', validateEditSectionName);
      
      // Change Teacher Form
      document.getElementById('newTeacherId').addEventListener('change', validateNewTeacher);
      
      // Add Subject Form
      document.getElementById('subjectName').addEventListener('keyup', validateSubjectName);
      document.getElementById('subjectName').addEventListener('change', validateSubjectName);
      
      document.getElementById('subjectTeacher').addEventListener('change', validateSubjectTeacher);
      
      // Edit Subject Form
      document.getElementById('editSubjectName').addEventListener('keyup', validateEditSubjectName);
      document.getElementById('editSubjectName').addEventListener('change', validateEditSubjectName);
      
      document.getElementById('editSubjectTeacherId').addEventListener('change', validateEditSubjectTeacher);
    });
  </script>
</body>
</html>