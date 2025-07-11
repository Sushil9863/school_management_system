<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login-Shikshalaya</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #218838;
        }
        a {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: #007bff;
        }
    </style>
</head>
<body>

<?php
session_start();
include 'partials/dbconnect.php';

// Custom hashing function (same used when storing passwords)
function custom_hash($password) {
    $salt = 'XyZ@2025!abc123'; // more complex salt
    $rounds = 3; // how many times to re-process

    $result = $password;

    for ($r = 0; $r < $rounds; $r++) {
        $temp = '';
        for ($i = 0; $i < strlen($result); $i++) {
            $char = ord($result[$i]);
            $saltChar = ord($salt[$i % strlen($salt)]);
            $mix = ($char ^ $saltChar) + ($char << 1); // bitwise XOR and shift left
            $hex = dechex($mix);
            $temp .= $hex;
        }
        // Append a base64 version of part of the hash
        $base64 = base64_encode($temp);
        $result = substr($temp, 0, 16) . substr($base64, -16);
    }

    return strtoupper($result);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $hashed_password = custom_hash($password);

    // Use prepared statement for safety
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $hashed_password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        // User found
        $row = $result->fetch_assoc();
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['user_type'] = $row['type'];

        switch ($row['type']) {
            case 'superadmin':
                header("Location: superadmin/superadmin_dashboard.php");
                break;
            case 'admin':
                header("Location: admin/admin_dashboard.php");
                break;
            case 'teacher':
                header("Location: teacher_dashboard.php");
                break;
            case 'parents':
                header("Location: parents_dashboard.php");
                break;
            case 'accountant':
                header("Location: accountant_dashboard.php");
                break;
            default:
                echo "Invalid user type.";
        }
        exit();
    } else {
        echo "<script>alert('Invalid username or password');</script>";
    }

    $stmt->close();
}
?>

<div class="login-container">
    <h2>Login to Shikshalaya</h2>
    <form action="" method="post">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Login</button>
        <div class="form-group">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
    </form>
</div>

</body>
</html>
