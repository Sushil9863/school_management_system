<?php
include '../partials/dbconnect.php';
$school_id = $_SESSION['school_id'] ?? 1;
$class_id = intval($_GET['class_id'] ?? 0);

$sections = [];
$q = $conn->query("SELECT id, section_name FROM sections WHERE class_id = $class_id AND school_id = $school_id");
while ($r = $q->fetch_assoc()) {
    $sections[] = $r;
}
header('Content-Type: application/json');
echo json_encode($sections);
