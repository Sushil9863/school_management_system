<?php
session_start();
include '../partials/dbconnect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['class_id'], $_GET['exam_id'], $_GET['subject_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$teacher_username = $_SESSION['username'];
$class_id = (int)$_GET['class_id'];
$exam_id = (int)$_GET['exam_id'];
$subject_id = (int)$_GET['subject_id'];

// Get teacher and school
$stmt = $conn->prepare("SELECT id, school_id FROM teachers WHERE username = ?");
$stmt->bind_param("s", $teacher_username);
$stmt->execute();
$result = $stmt->get_result();
$teacher = $result->fetch_assoc();

if (!$teacher) {
    echo json_encode(['success' => false, 'error' => 'Teacher not found']);
    exit;
}

$teacher_id = $teacher['id'];
$school_id = $teacher['school_id'];

// Verify subject and class assigned to teacher and school
$stmt = $conn->prepare("
    SELECT s.id FROM subjects s 
    JOIN classes c ON s.class_id = c.id 
    WHERE s.id = ? AND c.id = ? AND s.teacher_id = ? AND c.school_id = ?
");
$stmt->bind_param("iiii", $subject_id, $class_id, $teacher_id, $school_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized subject/class']);
    exit;
}

// Verify exam belongs to same class and school
$stmt = $conn->prepare("SELECT id FROM exams WHERE id = ? AND class_id = ? AND school_id = ?");
$stmt->bind_param("iii", $exam_id, $class_id, $school_id);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized exam']);
    exit;
}

// Get full marks from exam_subjects table
$stmt = $conn->prepare("SELECT full_marks FROM exam_subjects WHERE exam_id = ? AND subject_id = ?");
$stmt->bind_param("ii", $exam_id, $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$full_marks = $row ? (int)$row['full_marks'] : 100;

// Fetch students for this class and school
$stmt = $conn->prepare("
    SELECT s.id, s.full_name,
    (SELECT marks FROM marks WHERE exam_id = ? AND subject_id = ? AND student_id = s.id) AS marks
    FROM students s
    WHERE s.class_id = ? AND s.school_id = ?
    ORDER BY s.full_name ASC
");
$stmt->bind_param("iiii", $exam_id, $subject_id, $class_id, $school_id);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'id' => $row['id'],
        'full_name' => $row['full_name'],
        'marks' => $row['marks'] !== null ? $row['marks'] : ''
    ];
}

echo json_encode([
    'success' => true,
    'full_marks' => $full_marks,
    'students' => $students
]);
exit;
