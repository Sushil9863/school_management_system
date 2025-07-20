<?php
session_start();
include '../partials/dbconnect.php';

if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
    echo "<script>alert('Unauthorized'); window.location.href = '../index.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "<script>alert('Invalid request'); window.history.back();</script>";
    exit;
}

// Validate POST data
$exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : null;
$class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : null;
$subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
$student_ids = $_POST['student_id'] ?? [];
$marks = $_POST['marks'] ?? [];

if (!$exam_id || !$class_id || !$subject_id || empty($student_ids) || empty($marks) || count($student_ids) !== count($marks)) {
    echo "<script>alert('Missing or invalid data'); window.history.back();</script>";
    exit;
}

// Get teacher info
$teacher_username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT id, school_id FROM teachers WHERE username = ?");
$stmt->bind_param("s", $teacher_username);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

if (!$teacher) {
    echo "<script>alert('Teacher not found'); window.location.href = '../index.php';</script>";
    exit;
}
$teacher_id = $teacher['id'];
$school_id = $teacher['school_id'];

// Check teacher is allowed to handle this subject/class
$check = $conn->prepare("
    SELECT c.id AS class_id
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.id = ? AND c.id = ? AND s.teacher_id = ? AND c.school_id = ?
");
$check->bind_param("iiii", $subject_id, $class_id, $teacher_id, $school_id);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    echo "<script>alert('Unauthorized access to this class/subject.'); window.location.href = 'view_exam_content.php';</script>";
    exit;
}

// Check that exam belongs to the same class and school
$examCheck = $conn->prepare("SELECT id FROM exams WHERE id = ? AND class_id = ? AND school_id = ?");
$examCheck->bind_param("iii", $exam_id, $class_id, $school_id);
$examCheck->execute();
$examCheck->store_result();
if ($examCheck->num_rows === 0) {
    echo "<script>alert('Unauthorized exam access.'); window.location.href = 'view_exam_content.php';</script>";
    exit;
}

// Process marks safely
for ($i = 0; $i < count($student_ids); $i++) {
    $student_id = (int)$student_ids[$i];
    $mark = (int)$marks[$i];

    // Ensure student is part of this class and school
    $studentCheck = $conn->prepare("SELECT id FROM students WHERE id = ? AND class_id = ? AND school_id = ?");
    $studentCheck->bind_param("iii", $student_id, $class_id, $school_id);
    $studentCheck->execute();
    $studentCheck->store_result();
    if ($studentCheck->num_rows === 0) {
        continue; // Skip invalid student
    }

    // Insert or update marks securely
    $stmt = $conn->prepare("
        INSERT INTO marks (exam_id, subject_id, student_id, marks)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE marks = VALUES(marks)
    ");
    $stmt->bind_param("iiii", $exam_id, $subject_id, $student_id, $mark);
    $stmt->execute();
}

echo "<script>alert('✅ Marks submitted successfully'); window.location.href = 'view_exams.php';</script>";
exit;
