<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: index.php");
    exit;
}

include_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
  <!-- Sidebar -->
  <aside id="sidebar" class="w-64 bg-white shadow-lg fixed z-30 h-full transform transition-transform duration-300 translate-x-0">
    <div class="p-6 border-b bg-blue-600 text-white">
      <h1 class="text-xl font-bold">Admin</h1>
    </div>
    <nav class="mt-4">
      <ul class="space-y-2 text-gray-700 font-medium">
        <li><a href="<?= BASE_URL ?>/admin/admin_dashboard.php" class="block px-6 py-3 hover:bg-gray-200">🏠 Dashboard</a></li>
        <li><a href="<?= BASE_URL ?>/admin/manage_students.php" class="block px-6 py-3 hover:bg-gray-200">👨‍🏫 Manage Students</a></li>
        <li><a href="<?= BASE_URL ?>/admin/manage_teacher.php" class="block px-6 py-3 hover:bg-gray-200">👨‍🏫 Manage Teachers></li>
        <li><a href="<?= BASE_URL ?>/admin/manage_classes.php" class="block px-6 py-3 hover:bg-gray-200">🏫 Manage Classes</a></li>
        <li><a href="<?= BASE_URL ?>/admim/manage_exams.php" class="block px-6 py-3 hover:bg-gray-200">🏫 Manage Exam</a></li>
        <li><a href="<?= BASE_URL ?>/admin/manage_results.php" class="block px-6 py-3 hover:bg-gray-200">🏫 Manage Result</a></li>
        <li><a href="<?= BASE_URL ?>/a_settings.php" class="block px-6 py-3 hover:bg-gray-200">⚙️ Settings</a></li>
        <li>
          <button onclick="showLogoutModal()" class="w-full text-left px-6 py-3 hover:bg-gray-200">🚪 Logout</button>
        </li>
      </ul>
    </nav>
  </aside>

  <!-- Main Content -->
  <div id="main-content" class="flex-1 flex flex-col overflow-y-auto transition-all duration-300" style="margin-left:16rem; width:calc(100vw - 16rem);">
    <header class="bg-white shadow-md p-4 flex justify-between items-center">
      <button onclick="toggleSidebar()" class="px-3 py-2 bg-blue-600 text-white rounded">☰ Menu</button>
      <h2 class="text-xl font-semibold"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h2>
      <span class="text-gray-600">👋 Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
    </header>

    <main class="p-6">
      <?php
        if (isset($contentFile) && file_exists($contentFile)) {
          include $contentFile;
        } else {
          echo "<h1>Welcome, Super Admin</h1>";
        }
      ?>
    </main>
  </div>
</div>

<?php include 'logoutmodal.php'; ?>

<script>
  function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    sidebar.classList.toggle('-translate-x-full');
    if (sidebar.classList.contains('-translate-x-full')) {
      mainContent.style.marginLeft = '0';
      mainContent.style.width = '100vw';
    } else {
      mainContent.style.marginLeft = '16rem';
      mainContent.style.width = 'calc(100vw - 16rem)';
    }
  }

  function showLogoutModal() {
    document.getElementById('logoutModal').classList.remove('hidden');
  }
  function hideLogoutModal() {
    document.getElementById('logoutModal').classList.add('hidden');
  }

  function onResize() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    if(window.innerWidth < 768) {
      if (!sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.add('-translate-x-full');
      }
      mainContent.style.marginLeft = '0';
      mainContent.style.width = '100vw';
    } else {
      if (sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
      }
      mainContent.style.marginLeft = '16rem';
      mainContent.style.width = 'calc(100vw - 16rem)';
    }
  }

  window.addEventListener('resize', onResize);
  window.addEventListener('load', onResize);
</script>

<style>
  #sidebar {
    transition: transform 0.3s ease-in-out;
  }
</style>

</body>
</html>
