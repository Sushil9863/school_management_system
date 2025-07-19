<?php
include '../partials/dbconnect.php'; // adjust path as needed

// Make sure only logged-in admins can access
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Fetch this admin’s school_id (so every query can use it)
$stmt = $conn->prepare("SELECT school_id FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();
$school_id = $school ? (int)$school['school_id'] : 0;

// If somehow no school is assigned, deny access
if ($school_id === 0) {
    echo "No school assigned to this admin.";
    exit;
}
