<?php
include '../partials/dbconnect.php';

if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

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


if (isset($_GET['delete'])) {
    $school_id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT admin_name FROM schools WHERE id = ?");
    $stmt->bind_param("i", $school_id);
    $stmt->execute();
    $stmt->bind_result($admin_username);
    $stmt->fetch();
    $stmt->close();

    $conn->begin_transaction();
    try {
        $stmt1 = $conn->prepare("DELETE FROM schools WHERE id = ?");
        $stmt1->bind_param("i", $school_id);
        $stmt1->execute();
        $stmt1->close();

        $stmt2 = $conn->prepare("DELETE FROM users WHERE username = ?");
        $stmt2->bind_param("s", $admin_username);
        $stmt2->execute();
        $stmt2->close();

        $conn->commit();
        header("Location: manage_schools.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo "<p class='text-red-600'>Error: " . $e->getMessage() . "</p>";
    }
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
<style>
    .glass {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(15px);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.25);
    }
</style>
<main class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-blue-700">🏫 Manage Schools</h1>
        <button onclick="showSchoolModal()"
            class="px-5 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-white font-semibold rounded-xl shadow hover:scale-105">➕
            Add School</button>
    </div>

    <div class="glass bg-white/30 backdrop-blur-md p-6 rounded-xl shadow-xl overflow-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="bg-blue-800 text-white">
                    <th class="py-2 px-4">ID</th>
                    <th class="py-2 px-4">School Name</th>
                    <th class="py-2 px-4">Address</th>
                    <th class="py-2 px-4">Phone</th>
                    <th class="py-2 px-4">Admin</th>
                    <th class="py-2 px-4">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $schools->fetch_assoc()): ?>
                    <tr class="hover:bg-blue-50">
                        <td class="py-2 px-4"><?= $row['id'] ?></td>
                        <td class="py-2 px-4"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="py-2 px-4"><?= htmlspecialchars($row['address']) ?></td>
                        <td class="py-2 px-4"><?= htmlspecialchars($row['phone']) ?></td>
                        <td class="py-2 px-4"><?= htmlspecialchars($row['admin_name']) ?></td>
                        <td class="py-2 px-4">
                            <a href="?delete=<?= $row['id'] ?>"
                                onclick="return confirm('Are you sure you want to delete this school?')"
                                class="text-red-600 font-semibold hover:underline">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<div id="schoolModal" onclick="outsideClick(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="modalBox">
        <div class="glass animate-fade-in p-8 rounded-2xl shadow-2xl w-full max-w-2xl 
         transition duration-300 ease-in-out
         hover:ring-4 hover:ring-blue-400 hover:ring-offset-2
         hover:shadow-[0_0_30px_rgba(59,130,246,0.6)] filter hover:brightness-110">
            <h2 class="text-2xl font-bold text-white mb-4">➕ Add New School</h2>
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class='block text-white font-medium mb-1'>School Name</label>
                        <input type='text' name='school_name' placeholder='School Name' required pattern='.{3,}'
                            title='Minimum 3 characters' class='w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
       focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
       hover:ring-2 hover:ring-blue-400 transition duration-300' />
                    </div>
                    <div>
                        <label class='block text-white font-medium mb-1'>Address</label>
                        <input type='text' name='address' placeholder='Address' required pattern='.{3,}'
                            title='Minimum 3 characters' class='w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
       focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
       hover:ring-2 hover:ring-blue-400 transition duration-300' />
                    </div>
                    <div>
                        <label class='block text-white font-medium mb-1'>Phone</label>
                        <input type='text' name='phone' placeholder='Phone' required pattern='^(97|98)\d{8}$'
                            title='Phone must start with 97 or 98 and contain 10 digits' class='w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
       focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
       hover:ring-2 hover:ring-blue-400 transition duration-300' />
                    </div>
                    <div>
                        <label class='block text-white font-medium mb-1'>Email</label>
                        <input type='email' name='email' placeholder='Email' required
                            pattern='^[a-zA-Z0-9._%+-]+@gmail\.com$' title='Email must be a valid @gmail.com address'
                            class='w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
       focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
       hover:ring-2 hover:ring-blue-400 transition duration-300' />
                    </div>
                    <div>
                        <label class='block text-white font-medium mb-1'>Admin Username</label>
                        <input type='text' name='admin_name' placeholder='Admin Username' required pattern='.{3,}'
                            title='Minimum 3 characters' class='w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
       focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
       hover:ring-2 hover:ring-blue-400 transition duration-300' />
                    </div>
                    <div>
                        <label class='block text-white font-medium mb-1'>Admin Password</label>
                        <input type='password' name='admin_password' placeholder='Admin Password' required
                            pattern='.{3,}' title='Minimum 3 characters' class='w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
       focus:outline-none focus:ring-4 focus:ring-blue-500 focus:shadow-[0_0_20px_rgba(59,130,246,0.6)] 
       hover:ring-2 hover:ring-blue-400 transition duration-300' />
                    </div>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="hideSchoolModal()"
                        class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400 transition font-semibold">Cancel</button>
                    <button type="submit"
                        class="px-6 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:scale-105 hover:shadow-xl transition font-semibold">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function showSchoolModal() {
        document.getElementById('schoolModal').classList.remove('hidden');
    }
    function hideSchoolModal() {
        document.getElementById('schoolModal').classList.add('hidden');
    }

    function outsideClick(event) {
    const modal = document.querySelector('.modalBox');
    if (!modal.contains(event.target)) {
      hideSchoolModal();
    }
  }
</script>