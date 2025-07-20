<?php
include '../partials/dbconnect.php';

if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
    die("Unauthorized access");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : null;
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
    $student_ids = $_POST['student_id'] ?? [];
    $marks_array = $_POST['marks'] ?? [];

    if (!$exam_id || !$subject_id || empty($student_ids) || empty($marks_array)) {
        echo "<script>alert('Missing data.'); window.history.back();</script>";
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
        die("Teacher not found");
    }
    $teacher_id = $teacher['id'];
    $school_id = $teacher['school_id'];

    // Validate that this teacher is assigned this subject (and class) in the same school
    $check = $conn->prepare("
        SELECT c.id AS class_id 
        FROM subjects s 
        JOIN classes c ON s.class_id = c.id
        WHERE s.id = ? AND s.teacher_id = ? AND c.school_id = ?
    ");
    $check->bind_param("iii", $subject_id, $teacher_id, $school_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        die("Unauthorized: You cannot modify marks for this subject/class.");
    }
    $check->bind_result($class_id);
    $check->fetch();

    // Validate exam also belongs to the same school and class
    $examCheck = $conn->prepare("
        SELECT id FROM exams 
        WHERE id = ? AND class_id = ? AND school_id = ?
    ");
    $examCheck->bind_param("iii", $exam_id, $class_id, $school_id);
    $examCheck->execute();
    $examCheck->store_result();
    if ($examCheck->num_rows === 0) {
        die("Unauthorized: Invalid exam for this class/school.");
    }

    // Save marks for each student
    foreach ($student_ids as $index => $student_id) {
        $student_id = (int)$student_id;
        $mark = isset($marks_array[$index]) ? (int)$marks_array[$index] : 0;

        // Ensure student is from same class and school
        $studentCheck = $conn->prepare("SELECT id FROM students WHERE id = ? AND class_id = ? AND school_id = ?");
        $studentCheck->bind_param("iii", $student_id, $class_id, $school_id);
        $studentCheck->execute();
        $studentCheck->store_result();
        if ($studentCheck->num_rows === 0) {
            continue; // Skip unauthorized students
        }

        // Check if record already exists
        $check_stmt = $conn->prepare("SELECT id FROM marks WHERE exam_id = ? AND subject_id = ? AND student_id = ?");
        $check_stmt->bind_param("iii", $exam_id, $subject_id, $student_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            // Update
            $update = $conn->prepare("UPDATE marks SET marks = ? WHERE exam_id = ? AND subject_id = ? AND student_id = ?");
            $update->bind_param("iiii", $mark, $exam_id, $subject_id, $student_id);
            $update->execute();
        } else {
            // Insert
            $insert = $conn->prepare("INSERT INTO marks (exam_id, subject_id, student_id, marks) VALUES (?, ?, ?, ?)");
            $insert->bind_param("iiii", $exam_id, $subject_id, $student_id, $mark);
            $insert->execute();
        }
    }

    echo "<script>alert('✅ Marks saved successfully.'); window.location.href = 'view_exam_content.php';</script>";
    exit;
}
