<?php
session_start();
include '../partials/dbconnect.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
        throw new Exception('Unauthorized');
    }

    if (!isset($_GET['class_id'], $_GET['exam_id'], $_GET['subject_id'])) {
        throw new Exception('Missing class_id, exam_id or subject_id');
    }

    $teacher_username = $_SESSION['username'];
    $class_id = (int) $_GET['class_id'];
    $exam_id = (int) $_GET['exam_id'];
    $subject_id = (int) $_GET['subject_id'];

    // Get teacher id from username
    $stmt = $conn->prepare("SELECT id FROM teachers WHERE username = ?");
    $stmt->bind_param("s", $teacher_username);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    if (!$teacher) throw new Exception('Teacher not found');
    $teacher_id = $teacher['id'];

    // Check teacher is assigned this subject and class
    $check = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND class_id = ? AND teacher_id = ?");
    $check->bind_param("iii", $subject_id, $class_id, $teacher_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        throw new Exception('You are not assigned to this subject or class');
    }

    // Get full_marks for this exam + subject
    $stmtFull = $conn->prepare("SELECT full_marks FROM exam_subjects WHERE exam_id = ? AND subject_id = ?");
    $stmtFull->bind_param("ii", $exam_id, $subject_id);
    $stmtFull->execute();
    $resultFull = $stmtFull->get_result();
    $fullRow = $resultFull->fetch_assoc();
    $full_marks = $fullRow ? (int)$fullRow['full_marks'] : 100; // default to 100 if not found

    // Get students in this class (matching by grade)
    $stmtStudents = $conn->prepare("
        SELECT s.id, s.full_name, 
          (SELECT marks FROM marks WHERE exam_id = ? AND subject_id = ? AND student_id = s.id) AS marks 
        FROM students s 
        WHERE s.grade = ? 
        ORDER BY s.full_name ASC
    ");
    $stmtStudents->bind_param("iii", $exam_id, $subject_id, $class_id);
    $stmtStudents->execute();
    $resultStudents = $stmtStudents->get_result();

    $students = [];
    while ($row = $resultStudents->fetch_assoc()) {
        $students[] = [
            'id' => $row['id'],
            'full_name' => $row['full_name'],
            'marks' => $row['marks'] !== null ? $row['marks'] : '',
        ];
    }

    echo json_encode([
        'success' => true,
        'full_marks' => $full_marks,
        'students' => $students
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
