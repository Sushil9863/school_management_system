<?php
include 'partials/dbconnect.php';

if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

function custom_hash($password) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $conn->real_escape_string($_POST['school_name']);
    $address = $conn->real_escape_string($_POST['address']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $admin_name = $conn->real_escape_string($_POST['admin_name']);
    $admin_password = custom_hash($_POST['admin_password']);

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO schools (name, address, phone, email, admin_name, admin_password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $name, $address, $phone, $email, $admin_name, $admin_password);
        $stmt->execute();
        $stmt->close();

        $stmt2 = $conn->prepare("INSERT INTO users (username, type, password, email) VALUES (?, 'admin', ?, ?)");
        $stmt2->bind_param("sss", $admin_name, $admin_password, $email);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
        header("Location: manage_schools.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
    }
}

$schools = $conn->query("SELECT * FROM schools ORDER BY id DESC");

?>

<main class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Manage Schools</h1>
        <button onclick="showSchoolModal()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            ➕ Add School
        </button>
    </div>

    <div class="bg-white shadow rounded p-4 overflow-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="border-b">
                    <th class="py-2 px-4">ID</th>
                    <th class="py-2 px-4">Name</th>
                    <th class="py-2 px-4">Address</th>
                    <th class="py-2 px-4">Phone</th>
                    <th class="py-2 px-4">Email</th>
                    <th class="py-2 px-4">Admin</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $schools->fetch_assoc()): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-2 px-4"><?= htmlspecialchars($row['id']) ?></td>
                        <td class="py-2 px-4"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="py-2 px-4"><?= htmlspecialchars($row['address']) ?></td>
                        <td class="py-2 px-4"><?= htmlspecialchars($row['phone']) ?></td>
                        <td class="py-2 px-4"><?= htmlspecialchars($row['email']) ?></td>
                        <td class="py-2 px-4"><?= htmlspecialchars($row['admin_name']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<div id="schoolModal" class="hidden fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded shadow-lg w-full max-w-lg">
        <h2 class="text-xl font-semibold mb-4">Add New School</h2>
        <form method="POST" action="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-gray-700 mb-1">School Name</label>
                    <input type="text" name="school_name" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Address</label>
                    <input type="text" name="address" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Phone</label>
                    <input type="text" name="phone" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Email</label>
                    <input type="email" name="email" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Admin Username</label>
                    <input type="text" name="admin_name" required class="w-full px-3 py-2 border rounded">
                </div>
                <div>
                    <label class="block text-gray-700 mb-1">Admin Password</label>
                    <input type="password" name="admin_password" required class="w-full px-3 py-2 border rounded">
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" onclick="hideSchoolModal()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 rounded">Add</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showSchoolModal() {
        document.getElementById('schoolModal').classList.remove('hidden');
    }
    function hideSchoolModal() {
        document.getElementById('schoolModal').classList.add('hidden');
    }
</script>
