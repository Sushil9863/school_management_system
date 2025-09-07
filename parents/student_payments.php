<?php
session_start();
include '../partials/dbconnect.php';

// Check authentication
if (!isset($_SESSION['user_type'])) {
    header("Location: ../login.php");
    exit;
}

$user_type = $_SESSION['user_type'];
$school_id = $_SESSION['school_id'] ?? 0;

// Get student ID from URL
$student_id = $_GET['student_id'] ?? 0;
if (!$student_id) {
    header("Location: parents_dashboard.php");
    exit;
}

// Get student details
$student_stmt = $conn->prepare("
    SELECT s.*, c.grade, c.section 
    FROM students s 
    JOIN classes c ON s.class_id = c.id 
    WHERE s.id = ? AND s.school_id = ?
");
$student_stmt->bind_param("ii", $student_id, $school_id);
$student_stmt->execute();
$student = $student_stmt->get_result()->fetch_assoc();

if (!$student) {
    die("Student not found or you don't have permission to view this student.");
}

$class_id = $student['class_id'];

// Handle payment submission
$errors = [];
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $fee_id = $_POST['fee_id'] ?? 0;
    $amount = $_POST['amount'] ?? 0;
    $payment_method = 'online'; // Force online payment for parents
    $notes = $_POST['notes'] ?? '';
    
    // Use current date automatically
    $payment_date = date('Y-m-d');
    
    // Validation
    if (empty($fee_id)) {
        $errors['fee_id'] = "Please select a fee";
    }
    
    if (empty($amount) || $amount <= 0) {
        $errors['amount'] = "Please enter a valid payment amount";
    }
    
    // Get fee details for validation
    $fee_stmt = $conn->prepare("SELECT * FROM fees WHERE id = ? AND student_id = ?");
    $fee_stmt->bind_param("ii", $fee_id, $student_id);
    $fee_stmt->execute();
    $fee = $fee_stmt->get_result()->fetch_assoc();
    
    if (!$fee) {
        $errors['fee_id'] = "Invalid fee selected";
    } elseif ($amount > $fee['due_amount']) {
        $errors['amount'] = "Payment amount cannot exceed due amount (Rs. " . number_format($fee['due_amount'], 2) . ")";
    }
    
    if (empty($errors)) {
        try {
            $conn->begin_transaction();
            
            // Update the fee record
            $new_paid = $fee['paid_amount'] + $amount;
            $new_due = $fee['due_amount'] - $amount;
            
            // Determine new status
            if ($new_due <= 0) {
                $status = 'paid';
            } elseif ($new_paid > 0) {
                $status = 'partial';
            } else {
                $status = 'pending';
            }
            
            $update_stmt = $conn->prepare("
                UPDATE fees 
                SET paid_amount = ?, due_amount = ?, status = ?, last_payment_date = NOW() 
                WHERE id = ?
            ");
            $update_stmt->bind_param("ddsi", $new_paid, $new_due, $status, $fee_id);
            $update_stmt->execute();
            
            // Record payment transaction
            $payment_stmt = $conn->prepare("
                INSERT INTO payment_transactions 
                (school_id, student_id, fee_id, amount, payment_date, payment_method, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $created_by = $_SESSION['username'] ?? 'system';
            $payment_stmt->bind_param("iiidssss", $school_id, $student_id, $fee_id, $amount, $payment_date, $payment_method, $notes, $created_by);
            $payment_stmt->execute();
            
            $conn->commit();
            $success_message = "Payment of Rs. " . number_format($amount, 2) . " recorded successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors['database'] = "Error recording payment: " . $e->getMessage();
        }
    }
}

// Get all fees for this student
$fees_stmt = $conn->prepare("
    SELECT * FROM fees 
    WHERE student_id = ? AND class_id = ? AND school_id = ?
    ORDER BY status, fee_title
");
$fees_stmt->bind_param("iii", $student_id, $class_id, $school_id);
$fees_stmt->execute();
$fees = $fees_stmt->get_result();

// Get payment history
$payments_stmt = $conn->prepare("
    SELECT pt.*, f.fee_title 
    FROM payment_transactions pt
    JOIN fees f ON pt.fee_id = f.id
    WHERE pt.student_id = ? AND pt.school_id = ?
    ORDER BY pt.payment_date DESC, pt.created_at DESC
    LIMIT 50
");
$payments_stmt->bind_param("ii", $student_id, $school_id);
$payments_stmt->execute();
$payments = $payments_stmt->get_result();

// Calculate totals
$total_fees = 0;
$total_paid = 0;
$total_due = 0;

while ($fee = $fees->fetch_assoc()) {
    $total_fees += $fee['total_amount'];
    $total_paid += $fee['paid_amount'];
    $total_due += $fee['due_amount'];
}

// Reset pointer for reuse
$fees->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Payments - <?= htmlspecialchars($student['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
</head>

<body class="bg-gray-100 p-6">
    <div class="max-w-6xl mx-auto bg-white p-6 rounded-lg shadow">
        <!-- Header with student info -->
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-700">💰 Student Payments</h1>
                <h2 class="text-xl text-gray-600 mt-1">
                    <?= htmlspecialchars($student['full_name']) ?> 
                    (Grade <?= $student['grade'] ?> - <?= $student['section'] ?>)
                </h2>
            </div>
            <a href="parents_dashboard.php" 
               class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                ← Back to Dashboard
            </a>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                <h3 class="text-sm font-medium text-blue-800">Total Fees</h3>
                <p class="text-2xl font-bold text-blue-900">Rs. <?= number_format($total_fees, 2) ?></p>
            </div>
            <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                <h3 class="text-sm font-medium text-green-800">Total Paid</h3>
                <p class="text-2xl font-bold text-green-900">Rs. <?= number_format($total_paid, 2) ?></p>
            </div>
            <div class="bg-red-50 p-4 rounded-lg border border-red-100">
                <h3 class="text-sm font-medium text-red-800">Total Due</h3>
                <p class="text-2xl font-bold text-red-900">Rs. <?= number_format($total_due, 2) ?></p>
            </div>
        </div>

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

        <!-- Tabs -->
        <ul class="flex gap-6 border-b mb-4">
            <li>
                <a href="#fees" class="py-2 px-4 border-b-4 border-blue-600 font-bold text-blue-700 tab-link" data-tab="fees">
                    📋 Fee Details
                </a>
            </li>
            <li>
                <a href="#add-payment" class="py-2 px-4 border-transparent text-gray-600 tab-link" data-tab="add-payment">
                    💳 Make Payment
                </a>
            </li>
            <li>
                <a href="#payment-history" class="py-2 px-4 border-transparent text-gray-600 tab-link" data-tab="payment-history">
                    📊 Payment History
                </a>
            </li>
        </ul>

        <!-- Fee Details Tab -->
        <div id="fees-tab" class="tab-content">
            <h2 class="text-xl font-semibold mb-4">Fee Details</h2>
            
            <?php if ($fees->num_rows > 0): ?>
                <table class="w-full text-sm border mb-6">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-4 text-left">Fee Title</th>
                            <th class="py-2 px-4 text-center">Total Amount</th>
                            <th class="py-2 px-4 text-center">Paid Amount</th>
                            <th class="py-2 px-4 text-center">Due Amount</th>
                            <th class="py-2 px-4 text-center">Status</th>
                            <th class="py-2 px-4 text-center">Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($fee = $fees->fetch_assoc()): ?>
                            <tr class="border-t">
                                <td class="py-2 px-4"><?= htmlspecialchars($fee['fee_title']) ?></td>
                                <td class="py-2 px-4 text-center">Rs. <?= number_format($fee['total_amount'], 2) ?></td>
                                <td class="py-2 px-4 text-center">Rs. <?= number_format($fee['paid_amount'], 2) ?></td>
                                <td class="py-2 px-4 text-center">
                                    <span class="<?= $fee['due_amount'] > 0 ? 'text-red-600 font-bold' : 'text-green-600' ?>">
                                        Rs. <?= number_format($fee['due_amount'], 2) ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 text-center">
                                    <?php 
                                    $status_class = [
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'partial' => 'bg-blue-100 text-blue-800',
                                        'paid' => 'bg-green-100 text-green-800'
                                    ];
                                    ?>
                                    <span class="px-2 py-1 rounded text-xs <?= $status_class[$fee['status']] ?>">
                                        <?= ucfirst($fee['status']) ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 text-center">
                                    <?= $fee['due_date'] ? date('M d, Y', strtotime($fee['due_date'])) : 'N/A' ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-500">No fees found for this student.</p>
            <?php endif; ?>
        </div>

        <!-- Add Payment Tab -->
        <div id="add-payment-tab" class="tab-content hidden">
            <h2 class="text-xl font-semibold mb-4">Make Payment</h2>
            
            <form method="POST" class="space-y-4 max-w-md">
                <input type="hidden" name="add_payment" value="1">
                
                <div>
                    <label for="fee_id" class="block text-sm font-medium text-gray-700 mb-1">Select Fee</label>
                    <select name="fee_id" id="fee_id" class="w-full border px-4 py-2 rounded <?= isset($errors['fee_id']) ? 'border-red-500' : '' ?>" required>
                        <option value="">-- Select Fee --</option>
                        <?php 
                        // Reset pointer again
                        $fees->data_seek(0);
                        while ($fee = $fees->fetch_assoc()): 
                            if ($fee['due_amount'] > 0): ?>
                                <option value="<?= $fee['id'] ?>" <?= (isset($_POST['fee_id']) && $_POST['fee_id'] == $fee['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fee['fee_title']) ?> 
                                    (Due: Rs. <?= number_format($fee['due_amount'], 2) ?>)
                                </option>
                            <?php endif;
                        endwhile; ?>
                    </select>
                    <?php if (isset($errors['fee_id'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?= $errors['fee_id'] ?></p>
                    <?php endif; ?>
                </div>
                
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">Amount (Rs)</label>
                    <input type="number" step="0.01" name="amount" id="amount" 
                           value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
                           class="w-full border px-4 py-2 rounded <?= isset($errors['amount']) ? 'border-red-500' : '' ?>" 
                           required min="0.01" placeholder="0.00" />
                    <?php if (isset($errors['amount'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?= $errors['amount'] ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Hidden payment method field (set to online) -->
                <input type="hidden" name="payment_method" value="online">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                    <div class="w-full border px-4 py-2 rounded bg-gray-100">
                        Online Payment
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Only online payments are available for parents.</p>
                </div>
                
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="3" 
                              class="w-full border px-4 py-2 rounded" 
                              placeholder="Any additional information about this payment"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                    Process Payment
                </button>
            </form>
        </div>

        <!-- Payment History Tab -->
        <div id="payment-history-tab" class="tab-content hidden">
            <h2 class="text-xl font-semibold mb-4">Payment History</h2>
            
            <?php if ($payments->num_rows > 0): ?>
                <table class="w-full text-sm border">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-4 text-left">Date</th>
                            <th class="py-2 px-4 text-left">Fee</th>
                            <th class="py-2 px-4 text-center">Amount</th>
                            <th class="py-2 px-4 text-center">Method</th>
                            <th class="py-2 px-4 text-left">Notes</th>
                            <th class="py-2 px-4 text-left">Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $payments->fetch_assoc()): ?>
                            <tr class="border-t">
                                <td class="py-2 px-4"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
                                <td class="py-2 px-4"><?= htmlspecialchars($payment['fee_title']) ?></td>
                                <td class="py-2 px-4 text-center">Rs. <?= number_format($payment['amount'], 2) ?></td>
                                <td class="py-2 px-4 text-center"><?= ucfirst(str_replace('_', ' ', $payment['payment_method'])) ?></td>
                                <td class="py-2 px-4"><?= htmlspecialchars($payment['notes'] ?: 'N/A') ?></td>
                                <td class="py-2 px-4"><?= htmlspecialchars($payment['created_by']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-gray-500">No payment history found for this student.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Tab functionality
        document.querySelectorAll('.tab-link').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Update active tab
                document.querySelectorAll('.tab-link').forEach(t => {
                    t.classList.remove('border-blue-600', 'font-bold', 'text-blue-700');
                    t.classList.add('border-transparent', 'text-gray-600');
                });
                
                this.classList.add('border-blue-600', 'font-bold', 'text-blue-700');
                this.classList.remove('border-transparent', 'text-gray-600');
                
                // Show selected tab content
                const tabName = this.getAttribute('data-tab');
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                
                document.getElementById(tabName + '-tab').classList.remove('hidden');
            });
        });

        // Update max amount based on selected fee
        document.getElementById('fee_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.text) {
                // Extract due amount from option text
                const dueMatch = selectedOption.text.match(/Due: Rs\. ([\d,]+\.\d{2})/);
                if (dueMatch) {
                    const dueAmount = parseFloat(dueMatch[1].replace(/,/g, ''));
                    document.getElementById('amount').max = dueAmount;
                    
                    // Set amount to due amount by default
                    if (!document.getElementById('amount').value) {
                        document.getElementById('amount').value = dueAmount.toFixed(2);
                    }
                }
            }
        });
    </script>
</body>
</html>