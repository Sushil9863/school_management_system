<?php
include '../partials/dbconnect.php';

header('Content-Type: application/json');
$exam_id = $_GET['exam_id'] ?? 0;
$exam_id = (int)$exam_id;

if ($exam_id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("SELECT subject_id, full_marks, pass_marks FROM exam_subjects WHERE exam_id = ?");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
