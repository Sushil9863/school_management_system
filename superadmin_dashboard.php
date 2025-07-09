<?php
session_start();

// Dummy session check
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Super Admin Dashboard</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Optional JS for sidebar toggle -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }
    </script>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <!-- Sidebar -->
    <aside id="sidebar" class="w-64 bg-white shadow-lg transform transition-transform duration-300 md:translate-x-0 -translate-x-full md:relative fixed z-30 h-full">
        <div class="p-6 border-b bg-blue-600 text-white">
            <h1 class="text-xl font-bold">Super Admin</h1>
        </div>
        <nav class="mt-4">
            <ul class="space-y-2 text-gray-700 font-medium">
                <li><a href="#" class="block px-6 py-3 hover:bg-gray-200">🏠 Dashboard</a></li>
                <li><a href="manage_schools.php" class="block px-6 py-3 hover:bg-gray-200">🏫 Manage Schools</a></li>
                <li><a href="manage_users.php" class="block px-6 py-3 hover:bg-gray-200">👥 Manage Users</a></li>
                <li><a href="settings.php" class="block px-6 py-3 hover:bg-gray-200">⚙️ Settings</a></li>
                <li>
                    <form method="POST" action="partials/logout.php">
                        <button class="w-full text-left px-6 py-3 hover:bg-gray-200">🚪 Logout</button>
                    </form>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-y-auto">
        <!-- Top bar -->
        <header class="bg-white shadow-md p-4 flex justify-between items-center">
            <button onclick="toggleSidebar()" class="md:hidden px-3 py-2 bg-blue-600 text-white rounded">
                ☰ Menu
            </button>
            <h2 class="text-xl font-semibold">Dashboard</h2>
            <span class="text-gray-600">👋 Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>
        </header>

        <!-- Content -->
        <main class="p-6">
            <h1 class="text-2xl font-bold mb-6">Welcome, Super Admin</h1>

            <!-- Dashboard Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="p-6 bg-white shadow rounded">
                    <h2 class="text-lg font-semibold text-gray-700">Total Schools</h2>
                    <p class="text-3xl font-bold text-blue-600 mt-2">12</p>
                </div>
                <div class="p-6 bg-white shadow rounded">
                    <h2 class="text-lg font-semibold text-gray-700">Active Users</h2>
                    <p class="text-3xl font-bold text-green-600 mt-2">65</p>
                </div>
                <div class="p-6 bg-white shadow rounded">
                    <h2 class="text-lg font-semibold text-gray-700">System Logs</h2>
                    <p class="text-3xl font-bold text-red-600 mt-2">8</p>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white shadow rounded p-6">
                <h2 class="text-xl font-semibold mb-4">Recent Activities</h2>
                <ul class="space-y-3 text-gray-700">
                    <li>✔️ Added new school: Sunrise Academy</li>
                    <li>✔️ Removed user: teacher1@example.com</li>
                    <li>✔️ Updated fee title configuration</li>
                </ul>
            </div>
        </main>
    </div>
</div>

</body>
</html>
