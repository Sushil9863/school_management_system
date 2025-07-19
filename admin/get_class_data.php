<?php
include '../partials/dbconnect.php';

$class_id = $_GET['class_id'] ?? null;

if (!$class_id) {
    echo json_encode(['error' => 'No class_id provided']);
    exit;
}

// Get class info
$stmt = $conn->prepare("SELECT * FROM classes WHERE id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$class_data = $stmt->get_result()->fetch_assoc();

if (!$class_data) {
    echo json_encode(['error' => 'Class not found']);
    exit;
}

// Get students
$stmt = $conn->prepare("SELECT id, full_name, gender FROM students WHERE class_id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get subjects with teacher names
$stmt = $conn->prepare("SELECT s.id, s.name, t.full_name as teacher_name FROM subjects s JOIN teachers t ON s.teacher_id = t.id WHERE s.class_id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'class_data' => $class_data,
    'students' => $students,
    'subjects' => $subjects,
]);
?>
