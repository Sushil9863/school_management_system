<?php
include '../partials/dbconnect.php';
include '../partials/algorithms.php';

if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}


// Initialize error messages
$errors = [
    'school_name' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'admin_name' => '',
    'admin_password' => ''
];

// Handle delete
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

// Handle Add Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['edit_school_id'])) {
    $name = trim($_POST['school_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $admin_name = trim($_POST['admin_name'] ?? '');
    $admin_password_raw = trim($_POST['admin_password'] ?? '');

    if (strlen($name) < 3)
        $errors['school_name'] = "School name must be at least 3 characters.";
    if (strlen($address) < 3)
        $errors['address'] = "Address must be at least 3 characters.";
    if (!preg_match('/^(?:97\d{8}|98\d{8}|0\d{6,8})$/', $phone))
        $errors['phone'] = "Phone must be a valid mobile (97/98) or landline (0xxxxxxx) number.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z][a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email))
        $errors['email'] = "Email must be a valid format.";
    if (strlen($admin_name) < 3)
        $errors['admin_name'] = "Admin username must be at least 3 characters.";
    if (strlen($admin_password_raw) < 3)
        $errors['admin_password'] = "Password must be at least 3 characters.";

    if (!array_filter($errors)) {
        $admin_password = custom_password_hash($admin_password_raw);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO schools (name, address, phone, email, admin_name, admin_password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $name, $address, $phone, $email, $admin_name, $admin_password);
            $stmt->execute();
            $school_id = $conn->insert_id;
            $stmt->close();

            $stmt2 = $conn->prepare("INSERT INTO users (school_id, username, type, password, email) VALUES (?, ?, 'admin', ?, ?)");
            $stmt2->bind_param("isss", $school_id, $admin_name, $admin_password, $email);
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
}

// Handle Edit Form Submission
$edit_errors = [
    'edit_school_name' => '',
    'edit_address' => '',
    'edit_phone' => '',
    'edit_email' => '',
    'edit_admin_name' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_school_id'])) {
    $edit_id = intval($_POST['edit_school_id']);
    $name = trim($_POST['edit_school_name'] ?? '');
    $address = trim($_POST['edit_address'] ?? '');
    $phone = trim($_POST['edit_phone'] ?? '');
    $email = trim($_POST['edit_email'] ?? '');
    $admin_name = trim($_POST['edit_admin_name'] ?? '');

    if (strlen($name) < 3)
        $edit_errors['edit_school_name'] = "School name must be at least 3 characters.";
    if (strlen($address) < 3)
        $edit_errors['edit_address'] = "Address must be at least 3 characters.";
    if (!preg_match('/^(?:97\d{8}|98\d{8}|0\d{6,8})$/', $phone))
        $edit_errors['edit_phone'] = "Phone must be a valid mobile (97/98) or landline (0xxxxxxx) number.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z][a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email))
        $edit_errors['edit_email'] = "Email must be a valid format.";
    if (strlen($admin_name) < 3)
        $edit_errors['edit_admin_name'] = "Admin username must be at least 3 characters.";

    if (empty(array_filter($edit_errors))) {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("UPDATE schools SET name=?, address=?, phone=?, email=?, admin_name=? WHERE id=?");
            $stmt->bind_param("sssssi", $name, $address, $phone, $email, $admin_name, $edit_id);
            $stmt->execute();
            $stmt->close();

            $stmt2 = $conn->prepare("UPDATE users SET username=?, email=? WHERE school_id=? AND type='admin'");
            $stmt2->bind_param("ssi", $admin_name, $email, $edit_id);
            $stmt2->execute();
            $stmt2->close();

            $conn->commit();
            header("Location: manage_schools.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo "<p style='color:red;'>Error updating school: " . $e->getMessage() . "</p>";
        }
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

    .error {
        color: #ff6b6b;
        font-size: 0.875rem;
        min-height: 1rem;
        display: block;
    }

    .modalBox {
        max-height: 80vh;
        width: 100%;
        max-width: 40rem;
        overflow-y: auto;
    }
</style>

<main class="p-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-blue-700">🏫 Manage Schools</h1>
        <button onclick="showSchoolModal()"
            class="px-5 py-2 bg-gradient-to-r from-blue-500 to-indigo-500 text-white font-semibold rounded-xl shadow hover:scale-105">
            ➕ Add School
        </button>
    </div>

    <div class="glass bg-white/30 p-6 rounded-xl shadow-xl overflow-auto">
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
                    <td class="py-2 px-4 space-x-4">
                        <button
                            onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>', '<?= htmlspecialchars($row['address']) ?>', '<?= htmlspecialchars($row['phone']) ?>', '<?= htmlspecialchars($row['email']) ?>', '<?= htmlspecialchars($row['admin_name']) ?>')"
                            class="text-blue-600 font-semibold hover:underline">Edit</button>
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

<!-- Add Modal -->
<div id="schoolModal" onclick="outsideClick(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="modalBox">
        <div class="glass p-8 rounded-2xl shadow-2xl">
            <h2 class="text-2xl font-bold text-white mb-4">➕ Add New School</h2>
            <form id="schoolForm" method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php
                    function field($label, $name, $type, $value, $error, $extra = '') {
                        echo "<div>
                            <label class='block text-white font-medium mb-1'>$label</label>
                            <input type='$type' name='$name' value='" . htmlspecialchars($value ?? '') . "' $extra
                                class='w-full px-4 py-2 rounded-lg bg-white/70 border border-white placeholder-gray-600 
                                focus:outline-none focus:ring-4 focus:ring-blue-500 hover:ring-2 hover:ring-blue-400 transition duration-300' />
                            <span class='error'>$error</span>
                        </div>";
                    }
                    field('School Name', 'school_name', 'text', $_POST['school_name'] ?? '', $errors['school_name'], "required");
                    field('Address', 'address', 'text', $_POST['address'] ?? '', $errors['address'], "required");
                    field('Phone', 'phone', 'text', $_POST['phone'] ?? '', $errors['phone'], "required");
                    field('Email', 'email', 'email', $_POST['email'] ?? '', $errors['email'], "required");
                    field('Admin Username', 'admin_name', 'text', $_POST['admin_name'] ?? '', $errors['admin_name'], "required");
                    field('Admin Password', 'admin_password', 'password', '', $errors['admin_password'], "required");
                    ?>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="hideSchoolModal()" class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-6 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:scale-105">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" onclick="outsideClickEdit(event)"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="modalBox">
        <div class="glass p-8 rounded-2xl shadow-2xl">
            <h2 class="text-2xl font-bold text-white mb-4">✏ Edit School</h2>
            <form id="editForm" method="POST">
                <input type="hidden" name="edit_school_id" id="edit_school_id">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php
                    field('School Name', 'edit_school_name', 'text', $_POST['edit_school_name'] ?? '', $edit_errors['edit_school_name'], "required");
                    field('Address', 'edit_address', 'text', $_POST['edit_address'] ?? '', $edit_errors['edit_address'], "required");
                    field('Phone', 'edit_phone', 'text', $_POST['edit_phone'] ?? '', $edit_errors['edit_phone'], "required");
                    field('Email', 'edit_email', 'email', $_POST['edit_email'] ?? '', $edit_errors['edit_email'], "required");
                    field('Admin Username', 'edit_admin_name', 'text', $_POST['edit_admin_name'] ?? '', $edit_errors['edit_admin_name'], "required");
                    ?>
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="hideEditModal()" class="px-4 py-2 rounded-lg bg-gray-300 hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-6 py-2 rounded-lg bg-gradient-to-r from-green-600 to-teal-600 text-white hover:scale-105">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showSchoolModal(){ document.getElementById('schoolModal').classList.remove('hidden'); }
function hideSchoolModal(){ document.getElementById('schoolModal').classList.add('hidden'); }
function outsideClick(e){ if(!document.querySelector('#schoolModal .modalBox').contains(e.target)) hideSchoolModal(); }

function openEditModal(id, name, address, phone, email, admin){
    document.getElementById('edit_school_id').value = id;
    document.querySelector('[name="edit_school_name"]').value = name;
    document.querySelector('[name="edit_address"]').value = address;
    document.querySelector('[name="edit_phone"]').value = phone;
    document.querySelector('[name="edit_email"]').value = email;
    document.querySelector('[name="edit_admin_name"]').value = admin;
    document.getElementById('editModal').classList.remove('hidden');
}
function hideEditModal(){ document.getElementById('editModal').classList.add('hidden'); }
function outsideClickEdit(e){ if(!document.querySelector('#editModal .modalBox').contains(e.target)) hideEditModal(); }

// Live validation for Add and Edit

const phoneRegex = /^(?:97\d{8}|98\d{8}|0\d{6,8})$/;
const emailRegex=/^[a-zA-Z0-9._%+-]+@[a-zA-Z][a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
function attachValidation(formId, fields){
    const form=document.getElementById(formId);
    Object.keys(fields).forEach(name=>{
        const input=form[name];
        const errorSpan=input.nextElementSibling;
        input.addEventListener('keyup',()=>{
            const msg=fields[name](input.value);
            errorSpan.textContent=msg;
        });
    });
    form.addEventListener('submit',e=>{
        let valid=true;
        Object.keys(fields).forEach(name=>{
            const input=form[name];
            const msg=fields[name](input.value);
            input.nextElementSibling.textContent=msg;
            if(msg) valid=false;
        });
        if(!valid) e.preventDefault();
    });
}
document.addEventListener('DOMContentLoaded',()=>{
    attachValidation('schoolForm',{
        school_name:v=>v.trim().length>=3?'':'School name must be at least 3 characters.',
        address:v=>v.trim().length>=3?'':'Address must be at least 3 characters.',
        phone:v=>phoneRegex.test(v)?'':'Phone must be a valid mobile (97/98) or landline (0xxxxxxx) number.',
        email:v=>emailRegex.test(v)?'':'Must be a valid email.',
        admin_name:v=>v.trim().length>=3?'':'Admin username must be at least 3 characters.',
        admin_password:v=>v.trim().length>=3?'':'Password must be at least 3 characters.'
    });
    attachValidation('editForm',{
        edit_school_name:v=>v.trim().length>=3?'':'School name must be at least 3 characters.',
        edit_address:v=>v.trim().length>=3?'':'Address must be at least 3 characters.',
        edit_phone:v=>phoneRegex.test(v)?'':'Phone must be a valid mobile (97/98) or landline (0xxxxxxx) number.',
        edit_email:v=>emailRegex.test(v)?'':'Must be a valid email.',
        edit_admin_name:v=>v.trim().length>=3?'':'Admin username must be at least 3 characters.'
    });
});
</script>
