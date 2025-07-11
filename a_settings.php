<?php
error_reporting(0);  
// error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['user_type'])) {
    header("Location: index.php");
    exit;
}

// include 'partials/config.php';
$pageTitle = "Settings";
$contentFile = 'a_settings_content.php'; // This file holds the settings form and logic

switch ($_SESSION['user_type']) {
    case 'superadmin':
        include 'partials/layout_superadmin.php';
        break;
        case 'admin':
        include 'partials/layout_admin.php';
        break;
    // Add more user types if needed
    default:
        die("Unauthorized access.");
}
?>
