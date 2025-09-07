<?php
include '../partials/dbconnect.php';

$user_type = $_SESSION['user_type'] ?? '';
$school_id = $_SESSION['school_id'] ?? 0;

$tab = $_GET['tab'] ?? 'view';
$selected_class_id = $_GET['class_id'] ?? null;

// Validation and error handling variables
$errors = [];
$form_data = [];
$success_message = '';

// Handle Add/Edit/Delete Fee Template
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = $_POST['class_id'] ?? 0;
    
    // Validate common fields
    if (isset($_POST['title'])) {
        $title = trim($_POST['title']);
        if (empty($title)) {
            $errors['title'] = "Fee title is required";
        } elseif (strlen($title) > 100) {
            $errors['title'] = "Title must be less than 100 characters";
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-\.\,\/]+$/', $title)) {
            $errors['title'] = "Title contains invalid characters";
        }
        $form_data['title'] = $title;
    }
    
    if (isset($_POST['amount'])) {
        $amount = $_POST['amount'];
        if (empty($amount)) {
            $errors['amount'] = "Amount is required";
        } elseif (!is_numeric($amount)) {
            $errors['amount'] = "Amount must be a number";
        } elseif ($amount <= 0) {
            $errors['amount'] = "Amount must be greater than 0";
        } elseif ($amount > 100000) {
            $errors['amount'] = "Amount is too large";
        }
        $form_data['amount'] = $amount;
    }

    // Add new fee template
    if (isset($_POST['add_fee']) && empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // Insert into fee_templates
            $stmt = $conn->prepare("INSERT INTO fee_templates (school_id, class_id, title, amount) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisd", $school_id, $class_id, $title, $amount);
            $stmt->execute();
            $template_id = $conn->insert_id;
            
            // Get all students in this class
            $students_stmt = $conn->prepare("SELECT id FROM students WHERE class_id = ? AND school_id = ?");
            $students_stmt->bind_param("ii", $class_id, $school_id);
            $students_stmt->execute();
            $students = $students_stmt->get_result();
            
            // Create fee records for each student
            $fee_stmt = $conn->prepare("INSERT INTO fees (school_id, class_id, student_id, fee_title, total_amount, due_amount) VALUES (?, ?, ?, ?, ?, ?)");
            
            while ($student = $students->fetch_assoc()) {
                $fee_stmt->bind_param("iiisdd", $school_id, $class_id, $student['id'], $title, $amount, $amount);
                $fee_stmt->execute();
            }
            
            $conn->commit();
            $success_message = "Fee template added and applied to all students in the class";
            header("Location: manage_payments.php?class_id=$class_id&msg=added&tab=view");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors['database'] = "Error adding fee: " . $e->getMessage();
        }
    }

    // Edit fee template
    if (isset($_POST['edit_fee']) && empty($errors)) {
        try {
            $id = $_POST['id'];
            $old_amount = $_POST['old_amount'];
            
            $conn->begin_transaction();
            
            // Update template
            $stmt = $conn->prepare("UPDATE fee_templates SET title=?, amount=? WHERE id=? AND school_id=?");
            $stmt->bind_param("sdii", $title, $amount, $id, $school_id);
            $stmt->execute();
            
            // Update all student fees for this template
            if ($amount != $old_amount) {
                $amount_diff = $amount - $old_amount;
                
                $update_stmt = $conn->prepare("
                    UPDATE fees 
                    SET total_amount = total_amount + ?, 
                        due_amount = due_amount + ?,
                        status = CASE 
                            WHEN (due_amount + ?) = 0 THEN 'paid'
                            WHEN (due_amount + ?) < total_amount THEN 'partial'
                            ELSE 'pending'
                        END
                    WHERE class_id = ? AND school_id = ? AND fee_title = ?
                ");
                $update_stmt->bind_param("ddddiis", $amount_diff, $amount_diff, $amount_diff, $amount_diff, $class_id, $school_id, $title);
                $update_stmt->execute();
            } else {
                // Only title changed
                $update_stmt = $conn->prepare("UPDATE fees SET fee_title = ? WHERE class_id = ? AND school_id = ? AND fee_title = ?");
                $old_title = $_POST['old_title'];
                $update_stmt->bind_param("siis", $title, $class_id, $school_id, $old_title);
                $update_stmt->execute();
            }
            
            $conn->commit();
            $success_message = "Fee template updated successfully";
            header("Location: manage_payments.php?class_id=$class_id&msg=updated&tab=view");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors['database'] = "Error updating fee: " . $e->getMessage();
        }
    }

    // Delete fee template
    if (isset($_POST['delete_fee'])) {
        try {
            $id = $_POST['id'];
            $title = $_POST['title'];
            
            $conn->begin_transaction();
            
            // Delete template
            $stmt = $conn->prepare("DELETE FROM fee_templates WHERE id=? AND school_id=?");
            $stmt->bind_param("ii", $id, $school_id);
            $stmt->execute();
            
            // Delete associated student fees
            $delete_stmt = $conn->prepare("DELETE FROM fees WHERE class_id=? AND school_id=? AND fee_title=?");
            $delete_stmt->bind_param("iis", $class_id, $school_id, $title);
            $delete_stmt->execute();
            
            $conn->commit();
            $success_message = "Fee template and associated student fees deleted successfully";
            header("Location: manage_payments.php?class_id=$class_id&msg=deleted&tab=view");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors['database'] = "Error deleting fee: " . $e->getMessage();
        }
    }
    
    // If there are errors, stay on the same tab
    if (!empty($errors)) {
        $tab = isset($_POST['add_fee']) ? 'add' : 'view';
    }
}

// Fetch classes
$class_result = $conn->query("SELECT * FROM classes " . ($user_type !== 'superadmin' ? "WHERE school_id=$school_id" : "") . " ORDER BY grade ASC");

// Fetch fee templates if class selected
$fee_templates = null;
$student_fees = null;
if ($selected_class_id) {
    // Get fee templates
    $stmt = $conn->prepare("SELECT * FROM fee_templates WHERE class_id=? AND school_id=? ORDER BY id DESC");
    $stmt->bind_param("ii", $selected_class_id, $school_id);
    $stmt->execute();
    $fee_templates = $stmt->get_result();

    // Get student fee summary
    $summary_stmt = $conn->prepare("
        SELECT 
            s.id as student_id,
            s.full_name,
            COUNT(f.id) as total_fees,
            SUM(f.total_amount) as total_amount,
            SUM(f.paid_amount) as total_paid,
            SUM(f.due_amount) as total_due
        FROM students s
        LEFT JOIN fees f ON s.id = f.student_id AND s.class_id = f.class_id
        WHERE s.class_id = ? AND s.school_id = ?
        GROUP BY s.id, s.full_name
        ORDER BY s.full_name
    ");
    $summary_stmt->bind_param("ii", $selected_class_id, $school_id);
    $summary_stmt->execute();
    $student_fees = $summary_stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Fee Templates</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
</head>

<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow">
        <h1 class="text-2xl font-bold text-gray-700 mb-6">💰 Manage Fee Templates</h1>

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['database'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($errors['database']) ?>
            </div>
        <?php endif; ?>

        <!-- Class Selector -->
        <form method="GET" class="flex gap-4 mb-6">
            <select name="class_id" class="border px-4 py-2 rounded w-64" required>
                <option value="">-- Select Class --</option>
                <?php while ($c = $class_result->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= $selected_class_id == $c['id'] ? 'selected' : '' ?>>
                        Grade <?= htmlspecialchars($c['grade']) ?> - <?= htmlspecialchars($c['section']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <button class="bg-blue-600 text-white px-4 py-2 rounded">Load</button>
        </form>

        <?php if ($selected_class_id): ?>
            <!-- Tabs -->
            <ul class="flex gap-6 border-b mb-4">
                <li>
                    <a href="?class_id=<?= $selected_class_id ?>&tab=view"
                        class="py-2 px-4 border-b-4 <?= $tab === 'view' ? 'border-blue-600 font-bold text-blue-700' : 'border-transparent text-gray-600' ?>">
                        📋 View Fee Templates
                    </a>
                </li>
                <li>
                    <a href="?class_id=<?= $selected_class_id ?>&tab=add"
                        class="py-2 px-4 border-b-4 <?= $tab === 'add' ? 'border-blue-600 font-bold text-blue-700' : 'border-transparent text-gray-600' ?>">
                        ➕ Add Fee Template
                    </a>
                </li>
                <li>
                    <a href="?class_id=<?= $selected_class_id ?>&tab=students"
                        class="py-2 px-4 border-b-4 <?= $tab === 'students' ? 'border-blue-600 font-bold text-blue-700' : 'border-transparent text-gray-600' ?>">
                        👥 Student Fees
                    </a>
                </li>
            </ul>

            <?php if ($tab === 'view'): ?>
                <h2 class="text-xl font-semibold mb-4">📋 Fee Templates</h2>
                <?php if ($fee_templates && $fee_templates->num_rows > 0): ?>
                    <table class="w-full text-sm border mb-4">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 text-left">Title</th>
                                <th class="py-2 px-4 text-left">Amount (Rs)</th>
                                <th class="py-2 px-4 text-center">Status</th>
                                <th class="py-2 px-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($f = $fee_templates->fetch_assoc()): ?>
                                <tr class="border-t">
                                    <td class="py-2 px-4"><?= htmlspecialchars($f['title']) ?></td>
                                    <td class="py-2 px-4">Rs. <?= number_format($f['amount'], 2) ?></td>
                                    <td class="py-2 px-4 text-center">
                                        <span class="px-2 py-1 rounded text-xs <?= $f['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= $f['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <button class="px-3 py-1 bg-yellow-500 text-white rounded text-sm"
                                            onclick="openEditModal(<?= $f['id'] ?>, '<?= addslashes($f['title']) ?>', <?= $f['amount'] ?>, <?= $selected_class_id ?>)">
                                            ✏️ Edit
                                        </button>
                                        <button class="px-3 py-1 bg-red-500 text-white rounded text-sm"
                                            onclick="openDeleteModal(<?= $f['id'] ?>, '<?= addslashes($f['title']) ?>', <?= $selected_class_id ?>)">
                                            🗑️ Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-gray-500">No fee templates found for this class.</p>
                <?php endif; ?>

            <?php elseif ($tab === 'add'): ?>
                <h2 class="text-xl font-semibold mb-4">➕ Add New Fee Template</h2>
                <form method="POST" class="space-y-4 max-w-md" id="feeForm">
                    <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
                    <input type="hidden" name="add_fee" value="1" />
                    
                    <div>
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Fee Title</label>
                        <input type="text" name="title" id="title" placeholder="e.g. Tuition Fee, Exam Fee" 
                            value="<?= htmlspecialchars($form_data['title'] ?? '') ?>"
                            class="w-full border px-4 py-2 rounded <?= isset($errors['title']) ? 'border-red-500' : '' ?>" 
                            required maxlength="100" />
                        <?php if (isset($errors['title'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?= $errors['title'] ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount (Rs)</label>
                        <input type="number" step="0.01" name="amount" id="amount" placeholder="0.00"
                            value="<?= htmlspecialchars($form_data['amount'] ?? '') ?>"
                            class="w-full border px-4 py-2 rounded <?= isset($errors['amount']) ? 'border-red-500' : '' ?>" 
                            required min="0.01" max="100000" />
                        <?php if (isset($errors['amount'])): ?>
                            <p class="mt-1 text-sm text-red-600"><?= $errors['amount'] ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                        Add Fee Template
                    </button>
                </form>

            <?php elseif ($tab === 'students'): ?>
                <h2 class="text-xl font-semibold mb-4">👥 Student Fee Summary</h2>
                <?php if ($student_fees && $student_fees->num_rows > 0): ?>
                    <table class="w-full text-sm border">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="py-2 px-4 text-left">Student Name</th>
                                <th class="py-2 px-4 text-center">Total Fees</th>
                                <th class="py-2 px-4 text-center">Total Amount</th>
                                <th class="py-2 px-4 text-center">Paid Amount</th>
                                <th class="py-2 px-4 text-center">Due Amount</th>
                                <th class="py-2 px-4 text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $student_fees->fetch_assoc()): ?>
                                <tr class="border-t">
                                    <td class="py-2 px-4"><?= htmlspecialchars($student['full_name']) ?></td>
                                    <td class="py-2 px-4 text-center"><?= $student['total_fees'] ?: 0 ?></td>
                                    <td class="py-2 px-4 text-center">Rs. <?= number_format($student['total_amount'] ?: 0, 2) ?></td>
                                    <td class="py-2 px-4 text-center">Rs. <?= number_format($student['total_paid'] ?: 0, 2) ?></td>
                                    <td class="py-2 px-4 text-center">
                                        <span class="<?= ($student['total_due'] ?? 0) > 0 ? 'text-red-600 font-bold' : 'text-green-600' ?>">
                                            Rs. <?= number_format($student['total_due'] ?: 0, 2) ?>
                                        </span>
                                    </td>
                                    <td class="py-2 px-4 text-center">
                                        <a href="student_payments.php?student_id=<?= $student['student_id'] ?>" 
                                           class="px-3 py-1 bg-blue-500 text-white rounded text-sm">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-gray-500">No students found in this class.</p>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white max-w-md w-full p-6 rounded-xl shadow-xl">
            <h2 class="text-xl font-bold mb-4">✏️ Edit Fee Template</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="edit_fee" value="1">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="class_id" id="edit_class_id">
                <input type="hidden" name="old_title" id="edit_old_title">
                <input type="hidden" name="old_amount" id="edit_old_amount">
                
                <div class="mb-4">
                    <label for="edit_title" class="block text-sm font-medium text-gray-700 mb-1">Fee Title</label>
                    <input type="text" name="title" id="edit_title" class="w-full border px-3 py-2 rounded" required maxlength="100">
                </div>
                
                <div class="mb-4">
                    <label for="edit_amount" class="block text-sm font-medium text-gray-700 mb-1">Amount (Rs)</label>
                    <input type="number" step="0.01" name="amount" id="edit_amount" class="w-full border px-3 py-2 rounded" required min="0.01" max="100000">
                </div>
                
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
        <div class="bg-white max-w-md w-full p-6 rounded-xl shadow-xl">
            <h2 class="text-xl font-bold mb-4 text-red-600">🗑️ Confirm Delete</h2>
            <form method="POST">
                <p class="mb-4">Are you sure you want to delete this fee template? This will also remove all associated student fee records.</p>
                <input type="hidden" name="delete_fee" value="1">
                <input type="hidden" name="id" id="delete_id">
                <input type="hidden" name="class_id" id="delete_class_id">
                <input type="hidden" name="title" id="delete_title">
                <div class="flex justify-end gap-4">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openEditModal(id, title, amount, class_id) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_old_title').value = title;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_old_amount').value = amount;
            document.getElementById('edit_class_id').value = class_id;
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        
        function openDeleteModal(id, title, class_id) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_title').value = title;
            document.getElementById('delete_class_id').value = class_id;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.id === 'editModal') closeEditModal();
            if (event.target.id === 'deleteModal') closeDeleteModal();
        }
    </script>
</body>
</html>