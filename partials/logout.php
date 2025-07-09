<?php
session_start();

// Confirm that user has clicked "Yes" in the modal
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Clear all session data
    $_SESSION = array();
    session_destroy();

    // Redirect to login or homepage after logout
    header("Location: ../index.php");
    exit;
} else {
    // If accessed directly without confirmation, redirect to dashboard or prevent logout
    header("Location: ../index.php");
    exit;
}
