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
            $message = "<p class='text-green-600 font-medium mb-4'>✅ Credentials updated successfully.</p>";
            $current_username = $new_username;
            $_SESSION['username'] = $new_username;
        } else {
            $message = "<p class='text-red-600 font-medium mb-4'>❌ Error: " . $stmt->error . "</p>";
        }

        $stmt->close();
    } else {
        $message = "<p class='text-red-600 font-medium mb-4'>⚠️ Invalid input or user not logged in.</p>";
    }
}

$conn->close();
?>

<!-- Tailwind Settings Dialog -->
<div class="max-w-xl mx-auto mt-16 px-6">
    <div class="glass bg-white/30 backdrop-blur-md border border-gray-200 shadow-xl rounded-2xl p-8 transition hover:shadow-2xl hover:scale-[1.01]">
        <h2 class="text-3xl font-bold text-blue-700 mb-6">🔐 Update Credentials</h2>

        <?= $message ?>

        <form action="" method="post" class="space-y-6">
            <div>
                <label for="username" class="block text-gray-800 font-medium mb-2">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    value="<?= htmlspecialchars($current_username) ?>"
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-all hover:border-blue-400"
                >
            </div>

            <div>
                <label for="password" class="block text-gray-800 font-medium mb-2">New Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    class="w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-400 transition-all hover:border-blue-400"
                >
            </div>

            <button
                type="submit"
                class="w-full py-2 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg hover:scale-[1.02] transition-all duration-300"
            >
                💾 Save Changes
            </button>
        </form>
    </div>
</div>