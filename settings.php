<?php
session_start();

// Example user login for demo/testing (Replace in production)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Use actual user ID from login session
}

// Your custom hashing algorithm
function custom_hash($password)
{
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


// Database connection
include 'partials/dbconnect.php';

// Form submission handling
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username'], $_POST['password']) && isset($_SESSION['user_id'])) {
        $new_username = $conn->real_escape_string($_POST['username']);
        $new_password = custom_hash($_POST['password']);
        $user_id = $_SESSION['user_id'];

        $sql = "UPDATE users SET username = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $new_username, $new_password, $user_id);

        if ($stmt->execute()) {
            $message = "<p style='color:green;'>Credentials updated successfully.</p>";
        } else {
            $message = "<p style='color:red;'>Error: " . $stmt->error . "</p>";
        }

        $stmt->close();
    } else {
        $message = "<p style='color:red;'>Invalid input or user not logged in.</p>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Change Credentials</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            /* margin: 10vh 35vw; */
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
    </style>
</head>

<body>
    <?= $message ?>
    <div class="login-container">
        <h2>Change Username and Password</h2>
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