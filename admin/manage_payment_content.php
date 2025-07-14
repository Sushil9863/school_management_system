<?php
include '../partials/dbconnect.php';

// Determine selected tab, default to 'view'
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
if (isset($_GET['delete'])) {
  $id = $_GET['delete'];
  $class_id = $_GET['class_id'];
  $tab = 'view'; // Show fees tab after delete
  $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  header("Location: manage_payments.php?class_id=$class_id&msg=deleted&tab=view");
  exit;
}

// Load classes
$class_result = $conn->query("SELECT * FROM classes ORDER BY grade ASC");
$selected_class_id = $_GET['class_id'] ?? null;

// Load fees
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
  <script>
    // Simple tab switcher that changes URL param 'tab' without AJAX
    function switchTab(tabName) {
      const params = new URLSearchParams(window.location.search);
      params.set('tab', tabName);
      window.location.search = params.toString();
    }
  </script>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-4xl mx-auto bg-white p-6 rounded-lg shadow">
  <h1 class="text-2xl font-bold text-gray-700 mb-6">💰 Manage Payments</h1>

  <?php if (isset($_GET['msg'])): ?>
  <div
    id="msgBox"
    class="mb-4 px-4 py-2 rounded bg-green-100 text-green-700 flex justify-between items-center transition-opacity duration-500"
    style="opacity: 1;"
  >
    <span>✅ Fee <?= htmlspecialchars($_GET['msg']) ?> successfully.</span>
    <button
      onclick="fadeOutMsg()"
      class="text-green-700 font-bold text-xl leading-none hover:text-green-900"
      aria-label="Close message"
      type="button"
    >&times;</button>
  </div>

  <script>
    function fadeOutMsg() {
      const msgBox = document.getElementById('msgBox');
      msgBox.style.opacity = '0';
      setTimeout(() => {
        msgBox.style.display = 'none';
      }, 500); // match the CSS transition duration
    }
  </script>
<?php endif; ?>



  <!-- Class Select Form -->
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
    <div class="mb-6">
      <ul class="flex gap-6 border-b">
        <li>
          <button
            class="py-2 px-4 border-b-4 <?= $tab === 'view' ? 'border-blue-600 font-bold text-blue-700' : 'border-transparent text-gray-600 hover:text-blue-700' ?>"
            onclick="switchTab('view')"
            type="button"
          >📋 View Fees</button>
        </li>
        <li>
          <button
            class="py-2 px-4 border-b-4 <?= $tab === 'add' ? 'border-blue-600 font-bold text-blue-700' : 'border-transparent text-gray-600 hover:text-blue-700' ?>"
            onclick="switchTab('add')"
            type="button"
          >➕ Add Fee</button>
        </li>
      </ul>
    </div>

    <!-- Tab Contents -->
    <div>
      <!-- View Fees Tab -->
      <div style="display: <?= $tab === 'view' ? 'block' : 'none' ?>;">
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
                    <!-- Inline Edit Form -->
                    <form method="POST" class="inline-flex items-center space-x-2">
                      <input type="hidden" name="edit_fee" value="1" />
                      <input type="hidden" name="id" value="<?= $f['id'] ?>" />
                      <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
                      <input
                        type="text" name="title" value="<?= htmlspecialchars($f['title']) ?>"
                        required class="border rounded px-2 py-1 w-32"
                      />
                      <input
                        type="number" step="0.01" name="amount" value="<?= $f['amount'] ?>"
                        required class="border rounded px-2 py-1 w-24"
                      />
                      <button type="submit" class="text-blue-600 font-semibold hover:underline">Update</button>
                    </form>
                    <a
                      href="?class_id=<?= $selected_class_id ?>&delete=<?= $f['id'] ?>&tab=view"
                      class="text-red-600 font-semibold hover:underline"
                      onclick="return confirm('Delete this fee?')"
                    >Delete</a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <p class="mt-4 font-bold text-right">Total Fees: Rs. <?= $total ?></p>
        <?php else: ?>
          <p class="text-gray-500">No fee records found.</p>
        <?php endif; ?>
      </div>

      <!-- Add Fee Tab -->
      <div style="display: <?= $tab === 'add' ? 'block' : 'none' ?>;">
        <h2 class="text-xl font-semibold mb-4">➕ Add New Fee</h2>
        <form method="POST" class="space-y-4 max-w-md">
          <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
          <input type="hidden" name="add_fee" value="1" />
          <input
            type="text" name="title" placeholder="Fee Title"
            required class="w-full border px-4 py-2 rounded"
          />
          <input
            type="number" step="0.01" name="amount" placeholder="Amount (Rs)"
            required class="w-full border px-4 py-2 rounded"
          />
          <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">Add Fee</button>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
