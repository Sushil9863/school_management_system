<?php
include '../partials/dbconnect.php';

$class_id = $_GET['class_id'] ?? 0;
$result = $conn->prepare("SELECT id, name FROM subjects WHERE class_id = ?");
$result->bind_param("i", $class_id);
$result->execute();
$subjects = $result->get_result()->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($subjects);
?>
