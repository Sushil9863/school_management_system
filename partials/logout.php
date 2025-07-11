<?php
session_start();
include 'config.php';

// Confirm that user has clicked "Yes" in the modal
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Clear all session data
    $_SESSION = array();
    session_destroy();
    header("Location: " . BASE_URL . "/index.php");
    // Redirect to login or homepage after logout
    // echo "done";
    exit;
} else {
    // If accessed directly without confirmation, redirect to dashboard or prevent logout
    header("Location: " . BASE_URL ."/index.php");
    exit;
}
