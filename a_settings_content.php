<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'partials/dbconnect.php';

// Custom hash function
function custom_hash($password)
{
    $salt = 'XyZ@2025!abc123';
    $rounds = 3;
    $result = $password;

    for ($r = 0; $r < $rounds; $r++) {
        $temp = '';
        for ($i = 0; $i < strlen($result); $i++) {
            $char = ord($result[$i]);
            $saltChar = ord($salt[$i % strlen($salt)]);
            $mix = ($char ^ $saltChar) + ($char << 1);
            $hex = dechex($mix);
            $temp .= $hex;
        }
        $base64 = base64_encode($temp);
        $result = substr($temp, 0, 16) . substr($base64, -16);
    }

    return strtoupper($result);
}

// Initialize
$message = '';
$user_id = $_SESSION['user_id'] ?? null;
$current_username = '';

if ($user_id) {
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($current_username);
    $stmt->fetch();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['username'], $_POST['password']) && $user_id) {
        $new_username = $conn->real_escape_string($_POST['username']);
        $new_password = custom_hash($_POST['password']);

        $sql = "UPDATE users SET username = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $new_username, $new_password, $user_id);

        if ($stmt->execute()) {
            $message = "<p class='text-green-600 mb-4'>Credentials updated successfully.</p>";
            $current_username = $new_username;
            $_SESSION['username'] = $new_username;
        } else {
            $message = "<p class='text-red-600 mb-4'>Error: " . $stmt->error . "</p>";
        }

        $stmt->close();
    } else {
        $message = "<p class='text-red-600 mb-4'>Invalid input or user not logged in.</p>";
    }
}

$conn->close();
?>

<!-- SETTINGS CONTENT -->
<div class="max-w-xl mx-auto mt-10 bg-white p-8 rounded shadow">
    <h2 class="text-2xl font-semibold mb-6 text-gray-800">Change Username and Password</h2>

    <?= $message ?>

    <form action="" method="post" class="space-y-6">
        <div>
            <label for="username" class="block mb-2 font-medium text-gray-700">Username:</label>
            <input
                type="text"
                id="username"
                name="username"
                required
                value="<?= htmlspecialchars($current_username) ?>"
                class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>

        <div>
            <label for="password" class="block mb-2 font-medium text-gray-700">New Password:</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
        </div>

        <button
            type="submit"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 rounded transition-colors duration-300"
        >
            Update Credentials
        </button>
    </form>
</div>
