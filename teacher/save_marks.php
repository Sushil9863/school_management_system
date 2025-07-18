<?php
include '../partials/dbconnect.php';

if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
    die("Unauthorized access");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id = $_POST['exam_id'] ?? null;
    $subject_id = $_POST['subject_id'] ?? null;
    $marks = $_POST['marks'] ?? [];

    if (!$exam_id || !$subject_id || empty($marks)) {
        echo "<script>alert('Missing data.'); window.history.back();</script>";
        exit;
    }

    foreach ($marks as $student_id => $mark) {
        $student_id = (int)$student_id;
        $mark = (int)$mark;

        // Check if record already exists
        $check_stmt = $conn->prepare("SELECT id FROM marks WHERE exam_id = ? AND subject_id = ? AND student_id = ?");
        $check_stmt->bind_param("iii", $exam_id, $subject_id, $student_id);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            // Update existing
            $update = $conn->prepare("UPDATE marks SET marks = ? WHERE exam_id = ? AND subject_id = ? AND student_id = ?");
            $update->bind_param("iiii", $mark, $exam_id, $subject_id, $student_id);
            $update->execute();
        } else {
            // Insert new
            $insert = $conn->prepare("INSERT INTO marks (exam_id, subject_id, student_id, marks) VALUES (?, ?, ?, ?)");
            $insert->bind_param("iiii", $exam_id, $subject_id, $student_id, $mark);
            $insert->execute();
        }
    }

    echo "<script>alert('✅ Marks saved successfully.'); window.location.href = 'view_exams.php';</script>";
    exit;
}
