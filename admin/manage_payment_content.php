<?php
include '../partials/dbconnect.php';

$tab = $_GET['tab'] ?? 'view';

// Add Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fee'])) {
  $class_id = $_POST['class_id'];
  $title = $_POST['title'];
  $amount = $_POST['amount'];
  $stmt = $conn->prepare("INSERT INTO payments (class_id, title, amount) VALUES (?, ?, ?)");
  $stmt->bind_param("isd", $class_id, $title, $amount);
  $stmt->execute();
  header("Location: manage_payments.php?class_id=$class_id&msg=added&tab=view");
  exit;
}

// Edit Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_fee'])) {
  $id = $_POST['id'];
  $class_id = $_POST['class_id'];
  $title = $_POST['title'];
  $amount = $_POST['amount'];
  $stmt = $conn->prepare("UPDATE payments SET title = ?, amount = ? WHERE id = ?");
  $stmt->bind_param("sdi", $title, $amount, $id);
  $stmt->execute();
  header("Location: manage_payments.php?class_id=$class_id&msg=edited&tab=view");
  exit;
}

// Delete Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_fee'])) {
  $id = $_POST['id'];
  $class_id = $_POST['class_id'];
  $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  header("Location: manage_payments.php?class_id=$class_id&msg=deleted&tab=view");
  exit;
}

$class_result = $conn->query("SELECT * FROM classes ORDER BY grade ASC");
$selected_class_id = $_GET['class_id'] ?? null;

$fees = null;
$total = 0;
if ($selected_class_id) {
  $stmt = $conn->prepare("SELECT * FROM payments WHERE class_id = ? ORDER BY id DESC");
  $stmt->bind_param("i", $selected_class_id);
  $stmt->execute();
  $fees = $stmt->get_result();

  $total_stmt = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE class_id = ?");
  $total_stmt->bind_param("i", $selected_class_id);
  $total_stmt->execute();
  $total = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Manage Payments</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .glass {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.1);
    }
  </style>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-4xl mx-auto bg-white p-6 rounded-lg shadow">
  <h1 class="text-2xl font-bold text-gray-700 mb-6">💰 Manage Payments</h1>

  <?php if (isset($_GET['msg'])): ?>
    <div id="msgBox" class="mb-4 px-4 py-2 rounded bg-green-100 text-green-700 flex justify-between items-center transition-opacity duration-500">
      <span>✅ Fee <?= htmlspecialchars($_GET['msg']) ?> successfully.</span>
      <button onclick="fadeOutMsg()" class="text-green-700 font-bold text-xl leading-none hover:text-green-900" aria-label="Close message">&times;</button>
    </div>
    <script>
      function fadeOutMsg() {
        const msgBox = document.getElementById('msgBox');
        msgBox.style.opacity = '0';
        setTimeout(() => msgBox.style.display = 'none', 500);
      }
    </script>
  <?php endif; ?>

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
    <div class="mb-6">
      <ul class="flex gap-6 border-b">
        <li><a href="?class_id=<?= $selected_class_id ?>&tab=view" class="py-2 px-4 border-b-4 <?= $tab === 'view' ? 'border-blue-600 font-bold text-blue-700' : 'border-transparent text-gray-600 hover:text-blue-700' ?>">📋 View Fees</a></li>
        <li><a href="?class_id=<?= $selected_class_id ?>&tab=add" class="py-2 px-4 border-b-4 <?= $tab === 'add' ? 'border-blue-600 font-bold text-blue-700' : 'border-transparent text-gray-600 hover:text-blue-700' ?>">➕ Add Fee</a></li>
      </ul>
    </div>

    <?php if ($tab === 'view'): ?>
      <h2 class="text-xl font-semibold mb-4">📋 Fee List</h2>
      <?php if ($fees && $fees->num_rows > 0): ?>
        <table class="w-full text-sm border">
          <thead class="bg-gray-100">
            <tr>
              <th class="py-2 px-4 text-left">Title</th>
              <th class="py-2 px-4 text-left">Amount (Rs)</th>
              <th class="py-2 px-4 text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($f = $fees->fetch_assoc()): ?>
              <tr class="border-t">
                <td class="py-2 px-4"><?= htmlspecialchars($f['title']) ?></td>
                <td class="py-2 px-4">Rs. <?= htmlspecialchars($f['amount']) ?></td>
                <td class="py-2 px-4 text-center space-x-2">
                  <button onclick='openEditModal(<?= json_encode($f) ?>)' class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">Edit</button>
                  <button onclick='openDeleteModal(<?= $f["id"] ?>)' class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">Delete</button>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <p class="mt-4 font-bold text-right">Total Fees: Rs. <?= $total ?></p>
      <?php else: ?>
        <p class="text-gray-500">No fee records found.</p>
      <?php endif; ?>
    <?php elseif ($tab === 'add'): ?>
      <h2 class="text-xl font-semibold mb-4">➕ Add New Fee</h2>
      <form method="POST" class="space-y-4 max-w-md">
        <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
        <input type="hidden" name="add_fee" value="1" />
        <input type="text" name="title" placeholder="Fee Title" required class="w-full border px-4 py-2 rounded" />
        <input type="number" step="0.01" name="amount" placeholder="Amount (Rs)" required class="w-full border px-4 py-2 rounded" />
        <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Add Fee</button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Edit Modal -->
<div id="editModal" onclick="closeModal(event)" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
  <div class="glass p-8 rounded-2xl shadow-2xl w-full max-w-md hover:ring-4 hover:ring-green-400 hover:shadow-[0_0_30px_rgba(34,197,94,0.6)]" onclick="event.stopPropagation();">
    <form method="POST">
      <input type="hidden" name="edit_fee" value="1">
      <input type="hidden" name="id" id="edit_id">
      <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
      <h3 class="text-xl font-bold text-white mb-4 text-center">✏️ Edit Fee</h3>
      <input type="text" name="title" id="edit_title" placeholder="Fee Title" class="w-full px-4 py-2 rounded bg-white/80 border border-white mb-4" required>
      <input type="number" name="amount" id="edit_amount" step="0.01" placeholder="Amount (Rs)" class="w-full px-4 py-2 rounded bg-white/80 border border-white mb-6" required>
      <div class="flex justify-center gap-4">
        <button type="button" onclick="closeAllModals()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Cancel</button>
        <button type="submit" class="px-6 py-2 rounded bg-green-600 text-white hover:scale-105 hover:shadow-xl">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" onclick="closeModal(event)" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
  <div class="glass p-8 rounded-2xl shadow-2xl w-full max-w-md hover:ring-4 hover:ring-red-400 hover:shadow-[0_0_30px_rgba(220,38,38,0.6)]" onclick="event.stopPropagation();">
    <form method="POST">
      <input type="hidden" name="delete_fee" value="1">
      <input type="hidden" name="id" id="delete_id">
      <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
      <h3 class="text-xl font-bold text-white mb-4 text-center">⚠️ Confirm Deletion</h3>
      <p class="text-white mb-6 text-center">Are you sure you want to delete this fee?</p>
      <div class="flex justify-center gap-4">
        <button type="button" onclick="closeAllModals()" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Cancel</button>
        <button type="submit" class="px-6 py-2 rounded bg-red-600 text-white hover:scale-105 hover:shadow-xl">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(data) {
  document.getElementById('edit_id').value = data.id;
  document.getElementById('edit_title').value = data.title;
  document.getElementById('edit_amount').value = data.amount;
  document.getElementById('editModal').classList.remove('hidden');
}
function openDeleteModal(id) {
  document.getElementById('delete_id').value = id;
  document.getElementById('deleteModal').classList.remove('hidden');
}
function closeModal(e) {
  if (e.target.id === 'editModal' || e.target.id === 'deleteModal') {
    e.target.classList.add('hidden');
  }
}
function closeAllModals() {
  document.getElementById('editModal').classList.add('hidden');
  document.getElementById('deleteModal').classList.add('hidden');
}
</script>
</body>
</html>
