<?php
include '../partials/dbconnect.php';

// Ensure admin is logged in
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$school_id = $_SESSION['school_id'] ?? null;
if (!$school_id) {
    die("No school selected.");
}

// Handle add announcement form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {
        $error = "Title and content are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO announcements (school_id, title, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $school_id, $title, $content);
        if ($stmt->execute()) {
            $success = "Announcement added successfully.";
        } else {
            $error = "Failed to add announcement.";
        }
        $stmt->close();
    }
}

// Handle delete announcement form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $del_id = intval($_POST['delete_id'] ?? 0);
    if ($del_id > 0) {
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND school_id = ?");
        $stmt->bind_param("ii", $del_id, $school_id);
        if ($stmt->execute()) {
            $success = "Announcement deleted successfully.";
        } else {
            $error = "Failed to delete announcement.";
        }
        $stmt->close();
    } else {
        $error = "Invalid announcement ID.";
    }
}

// Fetch existing announcements for this school
$stmt = $conn->prepare("SELECT id, title, content, created_at FROM announcements WHERE school_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>Manage Announcements</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .scrollbar-thin::-webkit-scrollbar {
      width: 8px;
    }

    .scrollbar-thin::-webkit-scrollbar-track {
      background: transparent;
    }

    .scrollbar-thin::-webkit-scrollbar-thumb {
      background-color: #4f46e5;
      border-radius: 10px;
    }

    /* Modal background overlay */
    .modal-bg {
      background-color: rgba(0, 0, 0, 0.5);
    }
  </style>
  <script>
    // Client-side form validation
    function validateForm(event) {
      const title = document.getElementById('title').value.trim();
      const content = document.getElementById('content').value.trim();
      const errorBox = document.getElementById('client-error');

      if (!title || !content) {
        event.preventDefault();
        errorBox.textContent = "Please fill in both title and content.";
        errorBox.classList.remove('hidden');
        return false;
      }
      errorBox.classList.add('hidden');
      return true;
    }

    // Open delete confirmation modal and set id
    function openDeleteModal(id, title) {
      document.getElementById('modal-delete').classList.remove('hidden');
      document.getElementById('delete-id').value = id;
      document.getElementById('modal-delete-title').textContent = title;
    }

    // Close modal
    function closeDeleteModal() {
      document.getElementById('modal-delete').classList.add('hidden');
      document.getElementById('delete-id').value = '';
    }
  </script>
</head>

<body class="bg-gradient-to-tr from-indigo-50 via-white to-indigo-50 min-h-screen p-8 font-sans">
  <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg p-8">
    <h1 class="text-4xl font-extrabold text-indigo-700 mb-8 text-center tracking-tight">
      Manage Announcements
    </h1>

    <!-- PHP error/success messages -->
    <?php if (!empty($error)): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6" role="alert">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6" role="alert">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <!-- Client-side validation error -->
    <div id="client-error" class="hidden bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6"></div>

    <!-- Add Announcement Form -->
    <form method="POST" class="space-y-6" onsubmit="return validateForm(event)">
      <input type="hidden" name="action" value="add" />
      <div>
        <label for="title" class="block mb-2 font-semibold text-indigo-600">Announcement Title</label>
        <input type="text" id="title" name="title" placeholder="Enter announcement title"
          class="w-full px-4 py-3 rounded border border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500" required />
      </div>

      <div>
        <label for="content" class="block mb-2 font-semibold text-indigo-600">Announcement Content</label>
        <textarea id="content" name="content" rows="5" placeholder="Write your announcement here..."
          class="w-full px-4 py-3 rounded border border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y" required></textarea>
      </div>

      <button type="submit"
        class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded shadow transition duration-300">
        Add Announcement
      </button>
    </form>

    <hr class="my-10 border-indigo-300" />

    <h2 class="text-3xl font-bold text-indigo-700 mb-6 text-center tracking-wide">Existing Announcements</h2>

    <?php if ($result->num_rows > 0): ?>
      <ul class="space-y-6 max-h-[500px] overflow-y-auto scrollbar-thin">
        <?php while ($row = $result->fetch_assoc()): ?>
          <li
            class="bg-indigo-50 border border-indigo-200 rounded-lg p-6 shadow-sm hover:shadow-md transition-shadow duration-300 relative">
            <h3 class="text-xl font-semibold text-indigo-800 mb-2"><?= htmlspecialchars($row['title']) ?></h3>
            <p class="text-indigo-700 whitespace-pre-wrap mb-4"><?= htmlspecialchars($row['content']) ?></p>
            <small
              class="text-indigo-500 italic"><?= htmlspecialchars(date('F j, Y, g:i a', strtotime($row['created_at']))) ?></small>

            <!-- Delete Button -->
            <button
              onclick="openDeleteModal(<?= (int)$row['id'] ?>, '<?= htmlspecialchars(addslashes($row['title'])) ?>')"
              class="absolute top-4 right-4 text-red-600 hover:text-red-800 font-bold focus:outline-none"
              aria-label="Delete announcement <?= htmlspecialchars($row['title']) ?>">
              &#10060;
            </button>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <p class="text-center text-indigo-400 italic">No announcements yet.</p>
    <?php endif; ?>
  </div>

  <!-- Delete Confirmation Modal -->
<!-- Delete Confirmation Modal -->
<div id="modal-delete" class="hidden fixed inset-0 flex items-center justify-center modal-bg z-50"
     onclick="if(event.target === this) closeDeleteModal()">
  <div
    class="bg-white rounded-xl p-8 max-w-lg w-full shadow-2xl text-center relative ring-4 ring-indigo-300 ring-offset-4 ring-offset-indigo-50"
    >
    <!-- modal content -->
    <h3 class="text-2xl font-bold mb-4 text-red-600">Confirm Deletion</h3>
    <p class="mb-6 text-gray-700">Are you sure you want to delete the announcement:</p>
    <p class="mb-6 font-semibold text-indigo-800" id="modal-delete-title"></p>

    <form method="POST" class="inline">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="delete_id" id="delete-id" value="" />
      <button type="submit"
        class="bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-3 rounded mr-4 shadow transition duration-300">
        Yes, Delete
      </button>
    </form>x 

    <button onclick="closeDeleteModal()"
      class="bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold px-6 py-3 rounded shadow transition duration-300">
      Cancel
    </button>

    <button onclick="closeDeleteModal()"
      class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 font-bold text-3xl leading-none focus:outline-none"
      aria-label="Close modal">&times;</button>
  </div>
</div>

  </div>
</body>

</html>
