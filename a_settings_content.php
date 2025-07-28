<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include_once 'partials/dbconnect.php';
include_once 'partials/algorithms.php';

// Custom hash function
// function custom_hash($password)
// {
//     $salt = 'XyZ@2025!abc123';
//     $rounds = 3;
//     $result = $password;
//     for ($r = 0; $r < $rounds; $r++) {
//         $temp = '';
//         for ($i = 0; $i < strlen($result); $i++) {
//             $char = ord($result[$i]);
//             $saltChar = ord($salt[$i % strlen($salt)]);
//             $mix = ($char ^ $saltChar) + ($char << 1);
//             $hex = dechex($mix);
//             $temp .= $hex;
//         }
//         $base64 = base64_encode($temp);
//         $result = substr($temp, 0, 16) . substr($base64, -16);
//     }
//     return strtoupper($result);
// }

// Initialize
$user_id = $_SESSION['user_id'] ?? null;
$user_type = $_SESSION['user_type'] ?? '';
$current_username = '';
$school_data = [];
$success_message = '';

if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $success_message = "<p id='success-msg' class='text-green-600 font-medium mb-4 animate-fade'>✅ Changes saved successfully.</p>";
}

// Fetch user info
if ($user_id) {
    $stmt = $conn->prepare("SELECT username, type, school_id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($current_username, $user_type_db, $school_id);
    $stmt->fetch();
    $stmt->close();
    if ($user_type === '') $user_type = $user_type_db;

    // If admin, fetch school details
    if ($user_type === 'admin' && $school_id) {
        $stmt2 = $conn->prepare("SELECT id, name, address, phone, email, admin_name FROM schools WHERE id=?");
        $stmt2->bind_param("i", $school_id);
        $stmt2->execute();
        $result = $stmt2->get_result();
        $school_data = $result->fetch_assoc();
        $stmt2->close();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $new_username = trim($_POST['username']);
    $new_password = custom_password_hash($_POST['password']);

    $conn->begin_transaction();
    try {
        // Update user credentials
        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_username, $new_password, $user_id);
        $stmt->execute();
        $stmt->close();

        // If admin, also update school details
        if ($user_type === 'admin' && isset($_POST['school_name'])) {
            $school_name = trim($_POST['school_name']);
            $address = trim($_POST['address']);
            $phone = trim($_POST['phone']);
            $email = trim($_POST['email']);
            $admin_name = $new_username;

            $stmt2 = $conn->prepare("UPDATE schools SET name=?, address=?, phone=?, email=?, admin_name=? WHERE id=?");
            $stmt2->bind_param("sssssi", $school_name, $address, $phone, $email, $admin_name, $school_id);
            $stmt2->execute();
            $stmt2->close();

            // Sync email/username in users table
            $stmt3 = $conn->prepare("UPDATE users SET username=?, email=? WHERE school_id=? AND type='admin'");
            $stmt3->bind_param("ssi", $admin_name, $email, $school_id);
            $stmt3->execute();
            $stmt3->close();
        }

        $conn->commit();

        $_SESSION['username'] = $new_username;
        header("Location: " . $_SERVER['REQUEST_URI'] . "?updated=1");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $success_message = "<p class='text-red-600 font-medium mb-4'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

$conn->close();
?>
<style>
    .glass {
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(15px);
        border: 1px solid rgba(255, 255, 255, 0.25);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .glass:hover {
        transform: scale(1.01);
        box-shadow: 0 12px 40px rgba(0,0,0,0.4);
    }
    .error { color: #ff6b6b; font-size: 0.875rem; min-height: 1rem; display: block; }
    .animate-fade { animation: fadeout 3s forwards; }
    @keyframes fadeout {
        0% { opacity: 1; }
        70% { opacity: 1; }
        100% { opacity: 0; display: none; }
    }
</style>

<div class="max-w-2xl mx-auto mt-16 px-6">
    <div class="glass rounded-2xl p-8">
        <h2 class="text-3xl font-bold text-blue-700 mb-6">🔐 Update Account</h2>

        <?= $success_message ?>

        <form id="updateForm" method="POST" class="space-y-6">
            <?php
            function field($label, $name, $type, $value = '', $error = '', $extra = '') {
                echo "<div>
                        <label class='block text-gray-800 font-medium mb-1'>$label</label>
                        <input type='$type' name='$name' value='" . htmlspecialchars($value) . "' $extra
                            class='w-full px-4 py-2 rounded-lg bg-white/70 border border-white focus:outline-none focus:ring-4 focus:ring-blue-500 hover:ring-2 hover:ring-blue-400 transition duration-300'>
                        <span class='error'>$error</span>
                      </div>";
            }

            // Always editable: username, password
            field('Username', 'username', 'text', $current_username, '', "required");
            field('New Password', 'password', 'password', '', '', "required");

            // If admin, add school fields
            if ($user_type === 'admin' && !empty($school_data)) {
                field('School Name', 'school_name', 'text', $school_data['name'], '');
                field('Address', 'address', 'text', $school_data['address'], '');
                field('Phone', 'phone', 'text', $school_data['phone'], '');
                field('Email', 'email', 'email', $school_data['email'], '');
            }
            ?>
            <button type="submit"
                class="w-full py-2 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold rounded-lg shadow-md hover:shadow-lg hover:scale-[1.02] transition-all duration-300">
                💾 Save Changes
            </button>
        </form>
    </div>
</div>

<script>
    const phoneRegex = /^(?:97\d{8}|98\d{8}|0\d{6,8})$/;
    const gmailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z][a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('updateForm');
        const fields = {
            username: v => v.trim().length >= 3 ? '' : 'Username must be at least 3 characters.',
            password: v => v.trim().length >= 3 ? '' : 'Password must be at least 3 characters.',
            <?php if ($user_type === 'admin'): ?>
            school_name: v => v.trim().length >= 3 ? '' : 'School name must be at least 3 characters.',
            address: v => v.trim().length >= 3 ? '' : 'Address must be at least 3 characters.',
            phone: v => phoneRegex.test(v) ? '' : 'Phone must be valid (landline or mobile).',
            email: v => gmailRegex.test(v) ? '' : 'Must be a valid email.',
            <?php endif; ?>
        };

        Object.keys(fields).forEach(name => {
            const input = form[name];
            if (!input) return;
            const errorSpan = input.nextElementSibling;
            input.addEventListener('keyup', () => {
                const msg = fields[name](input.value);
                errorSpan.textContent = msg;
            });
        });

        form.addEventListener('submit', e => {
            let valid = true;
            Object.keys(fields).forEach(name => {
                const input = form[name];
                if (!input) return;
                const msg = fields[name](input.value);
                input.nextElementSibling.textContent = msg;
                if (msg) valid = false;
            });
            if (!valid) e.preventDefault();
        });

        // Auto fade success message
        const success = document.getElementById('success-msg');
        if (success) {
            setTimeout(() => { success.style.display = 'none'; }, 3000);
        }
    });
</script>
