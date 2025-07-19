<?php
include '../partials/dbconnect.php';

$user_type = $_SESSION['user_type'] ?? '';
$school_id = $_SESSION['school_id'] ?? 0;

$tab = $_GET['tab'] ?? 'view';
$selected_class_id = $_GET['class_id'] ?? null;

// Handle Add/Edit/Delete Fee
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $class_id = $_POST['class_id'] ?? 0;

  if (isset($_POST['add_fee'])) {
    $title = $_POST['title'];
    $amount = $_POST['amount'];
    $stmt = $conn->prepare("INSERT INTO payments (school_id,class_id,title,amount) VALUES (?,?,?,?)");
    $stmt->bind_param("iisd", $school_id, $class_id, $title, $amount);
    $stmt->execute();
    header("Location: manage_payments.php?class_id=$class_id&msg=added&tab=view");
    exit;
  }

  if (isset($_POST['edit_fee'])) {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $amount = $_POST['amount'];
    $stmt = $conn->prepare("UPDATE payments SET title=?, amount=? WHERE id=? AND school_id=?");
    $stmt->bind_param("sdii", $title, $amount, $id, $school_id);
    $stmt->execute();
    header("Location: manage_payments.php?class_id=$class_id&msg=edited&tab=view");
    exit;
  }

  if (isset($_POST['delete_fee'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM payments WHERE id=? AND school_id=?");
    $stmt->bind_param("ii", $id, $school_id);
    $stmt->execute();
    header("Location: manage_payments.php?class_id=$class_id&msg=deleted&tab=view");
    exit;
  }
}

// Fetch classes
$class_result = $conn->query("SELECT * FROM classes " . ($user_type !== 'superadmin' ? "WHERE school_id=$school_id" : "") . " ORDER BY grade ASC");

// Fetch fee list if class selected
$fees = null;
$total = 0;
if ($selected_class_id) {
  $stmt = $conn->prepare("SELECT * FROM payments WHERE class_id=? AND school_id=? ORDER BY id DESC");
  $stmt->bind_param("ii", $selected_class_id, $school_id);
  $stmt->execute();
  $fees = $stmt->get_result();

  $t = $conn->prepare("SELECT SUM(amount) as total FROM payments WHERE class_id=? AND school_id=?");
  $t->bind_param("ii", $selected_class_id, $school_id);
  $t->execute();
  $total = $t->get_result()->fetch_assoc()['total'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Payments</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
</head>

<body class="bg-gray-100 p-6">
  <div class="max-w-5xl mx-auto bg-white p-6 rounded-lg shadow">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">💰 Manage Payments</h1>

    <?php if (isset($_GET['msg'])): ?>
      <div id="successAlert"
        class="relative mb-4 px-4 py-2 rounded bg-green-100 text-green-700 flex items-center justify-between transition-opacity duration-500 opacity-100">
        <span>✅ Fee <?= htmlspecialchars($_GET['msg']) ?> successfully.</span>
        <button onclick="closeAlert()" class="ml-4 text-green-700 font-bold hover:text-red-600">
          ✕
        </button>
      </div>

      <script>
        function closeAlert() {
          const alertBox = document.getElementById('successAlert');
          if (alertBox) {
            alertBox.classList.remove('opacity-100');
            alertBox.classList.add('opacity-0');
            setTimeout(() => alertBox.remove(), 500); // 0.5s बादमा DOM बाट हटाउँछ
          }
        }
      </script>
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
            📋 View Fees
          </a>
        </li>
        <li>
          <a href="?class_id=<?= $selected_class_id ?>&tab=add"
            class="py-2 px-4 border-b-4 <?= $tab === 'add' ? 'border-blue-600 font-bold text-blue-700' : 'border-transparent text-gray-600' ?>">
            ➕ Add Fee
          </a>
        </li>
      </ul>

      <?php if ($tab === 'view'): ?>
        <h2 class="text-xl font-semibold mb-4">📋 Fee List</h2>
        <?php if ($fees && $fees->num_rows > 0): ?>
          <table class="w-full text-sm border mb-4">
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
                  <td class="py-2 px-4 text-center">
                    <button class="px-3 py-1 bg-yellow-500 text-white rounded"
                      onclick="openEditModal(<?= $f['id'] ?>, '<?= addslashes($f['title']) ?>', <?= $f['amount'] ?>, <?= $selected_class_id ?>)">✏️</button>
                    <button class="px-3 py-1 bg-red-500 text-white rounded"
                      onclick="openDeleteModal(<?= $f['id'] ?>, <?= $selected_class_id ?>)">🗑️</button>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <p class="text-right font-bold">Total Fees: Rs. <?= $total ?></p>
        <?php else: ?>
          <p class="text-gray-500">No fees found.</p>
        <?php endif; ?>

      <?php elseif ($tab === 'add'): ?>
        <h2 class="text-xl font-semibold mb-4">➕ Add New Fee</h2>
        <form method="POST" class="space-y-4 max-w-md">
          <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
          <input type="hidden" name="add_fee" value="1" />
          <input type="text" name="title" placeholder="Fee Title" required class="w-full border px-4 py-2 rounded" />
          <input type="number" step="0.01" name="amount" placeholder="Amount (Rs)" required
            class="w-full border px-4 py-2 rounded" />
          <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Add Fee</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center"
    onclick="closeEditModal(event)">
    <div onclick="event.stopPropagation();" class="bg-white max-w-md w-full p-6 rounded-xl shadow-xl">
      <h2 class="text-xl font-bold mb-4">✏️ Edit Fee</h2>
      <form method="POST">
        <input type="hidden" name="edit_fee" value="1">
        <input type="hidden" name="id" id="edit_id">
        <input type="hidden" name="class_id" id="edit_class_id">
        <input type="text" name="title" id="edit_title" class="w-full border px-3 py-2 rounded mb-3" required>
        <input type="number" step="0.01" name="amount" id="edit_amount" class="w-full border px-3 py-2 rounded mb-3"
          required>
        <div class="flex justify-end gap-4">
          <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-yellow-500 text-white rounded">Update</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center"
    onclick="closeDeleteModal(event)">
    <div onclick="event.stopPropagation();" class="bg-white max-w-md w-full p-6 rounded-xl shadow-xl">
      <h2 class="text-xl font-bold mb-4 text-red-600">🗑️ Confirm Delete</h2>
      <form method="POST">
        <p>Are you sure you want to delete this fee?</p>
        <input type="hidden" name="delete_fee" value="1">
        <input type="hidden" name="id" id="delete_id">
        <input type="hidden" name="class_id" id="delete_class_id">
        <div class="flex justify-end gap-4 mt-6">
          <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-300 rounded">Cancel</button>
          <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Delete</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openEditModal(id, title, amount, class_id) {
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_title').value = title;
      document.getElementById('edit_amount').value = amount;
      document.getElementById('edit_class_id').value = class_id;
      document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal(event) {
      if (!event || event.target.id === 'editModal') {
        document.getElementById('editModal').classList.add('hidden');
      }
    }
    function openDeleteModal(id, class_id) {
      document.getElementById('delete_id').value = id;
      document.getElementById('delete_class_id').value = class_id;
      document.getElementById('deleteModal').classList.remove('hidden');
    }
    function closeDeleteModal(event) {
      if (!event || event.target.id === 'deleteModal') {
        document.getElementById('deleteModal').classList.add('hidden');
      }
    }
  </script>
</body>

</html>