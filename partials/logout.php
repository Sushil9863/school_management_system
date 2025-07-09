<?php
session_start();

// If confirmed, destroy the session
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    $_SESSION = array();
    session_destroy();
    header("Location: ../index.php"); 
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Logout</title>
    <script>
        window.onload = function () {
            if (confirm("Are you sure you want to logout?")) {
                window.location.href = "logout.php?confirm=yes";
            } else {
                const previousPage = document.referrer;
                if (previousPage) {
                    window.location.href = previousPage;
                } else {
                    window.location.href = "../index.php"; 
                }
            }
        };
    </script>
</head>
<body>
</body>
</html>
