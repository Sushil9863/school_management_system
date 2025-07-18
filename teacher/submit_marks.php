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

$exam_id = $_POST['exam_id'] ?? null;
$class_id = $_POST['class_id'] ?? null;
$subject_id = $_POST['subject_id'] ?? null;
$student_ids = $_POST['student_id'] ?? [];
$marks = $_POST['marks'] ?? [];

if (!$exam_id || !$class_id || !$subject_id || empty($student_ids) || empty($marks) || count($student_ids) !== count($marks)) {
    echo "<script>alert('Missing or invalid data'); window.history.back();</script>";
    exit;
}

// Clean inputs
$exam_id = (int)$exam_id;
$class_id = (int)$class_id;
$subject_id = (int)$subject_id;

for ($i = 0; $i < count($student_ids); $i++) {
    $student_id = (int)$student_ids[$i];
    $mark = (int)$marks[$i];

    // Insert or update marks
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
