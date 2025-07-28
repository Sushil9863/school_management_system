<?php
include '../partials/dbconnect.php';

// Ensure user is superadmin (for access)
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'superadmin') {
    header('Location: ../login.php');
    exit();
}

// Fetch total schools
$result = $conn->query("SELECT COUNT(*) AS total_schools FROM schools");
$total_schools = $result->fetch_assoc()['total_schools'] ?? 0;

// Fetch active users (last 30 days) EXCLUDING superadmin
$activeUsersQuery = "
    SELECT COUNT(DISTINCT user_id) AS active_users
    FROM login_logs ll
    JOIN users u ON ll.user_id = u.id
    WHERE ll.login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND u.type IN ('teacher', 'admin', 'parent')
";
$result = $conn->query($activeUsersQuery);
$total_active_users = $result->fetch_assoc()['active_users'] ?? 0;

// Fetch user counts by role (exclude superadmin)
$userRolesQuery = "
    SELECT type, COUNT(*) as count
    FROM users
    WHERE type IN ('teacher', 'admin', 'parent')
    GROUP BY type
";
$userRolesData = ['teacher' => 0, 'admin' => 0, 'parent' => 0];
$result = $conn->query($userRolesQuery);
while ($row = $result->fetch_assoc()) {
    $userRolesData[$row['type']] = (int)$row['count'];
}

// Fetch most active schools (last 30 days, top 5)
$activeSchoolsQuery = "
    SELECT s.name, COUNT(ll.id) AS login_count
    FROM schools s
    LEFT JOIN login_logs ll 
        ON s.id = ll.school_id AND ll.login_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY s.id
    ORDER BY login_count DESC
    LIMIT 5
";
$activeSchoolsLabels = [];
$activeSchoolsCounts = [];
$result = $conn->query($activeSchoolsQuery);
while ($row = $result->fetch_assoc()) {
    $activeSchoolsLabels[] = $row['name'];
    $activeSchoolsCounts[] = (int)$row['login_count'];
}

// Fetch monthly new users (last 12 months, exclude superadmin)
$monthlyUsersQuery = "
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND type IN ('teacher', 'admin', 'parent')
    GROUP BY month
    ORDER BY month
";
$monthlyLabels = [];
$monthlyCounts = [];
$result = $conn->query($monthlyUsersQuery);
while ($row = $result->fetch_assoc()) {
    $dateObj = DateTime::createFromFormat('Y-m', $row['month']);
    $monthlyLabels[] = $dateObj->format('M Y');
    $monthlyCounts[] = (int)$row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Super Admin Dashboard - Shikshalaya</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100 min-h-screen">

  <div class="max-w-7xl mx-auto p-6">

    <h1 class="text-3xl font-bold mb-6 text-gray-800">Welcome, Super Admin</h1>

    <!-- Stats cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="p-6 bg-white rounded shadow text-center">
        <h2 class="text-lg font-semibold text-gray-700">Total Schools</h2>
        <p class="text-3xl font-bold text-blue-600 mt-2"><?= $total_schools ?></p>
      </div>
      <div class="p-6 bg-white rounded shadow text-center">
        <h2 class="text-lg font-semibold text-gray-700">Active Users (last 30 days)</h2>
        <p class="text-3xl font-bold text-green-600 mt-2"><?= $total_active_users ?></p>
      </div>
      <div class="p-6 bg-white rounded shadow text-center">
        <h2 class="text-lg font-semibold text-gray-700">Total Users</h2>
        <p class="text-3xl font-bold text-purple-600 mt-2"><?= array_sum($userRolesData) ?></p>
      </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <!-- User Roles Pie Chart -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="text-xl font-semibold mb-4 text-gray-700">Users by Role</h2>
        <canvas id="userRolesChart" width="250" height="250"></canvas>
      </div>

      <!-- Active Schools Bar Chart -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="text-xl font-semibold mb-4 text-gray-700">Most Active Schools (30 days)</h2>
        <canvas id="activeSchoolsChart" width="250" height="250"></canvas>
      </div>

      <!-- Monthly New Users Line Chart -->
      <div class="bg-white rounded shadow p-4">
        <h2 class="text-xl font-semibold mb-4 text-gray-700">Monthly New Users (12 months)</h2>
        <canvas id="monthlyUsersChart" width="250" height="250"></canvas>
      </div>
    </div>
  </div>

  <script>
    // User Roles Pie Chart
    const userRolesCtx = document.getElementById('userRolesChart').getContext('2d');
    new Chart(userRolesCtx, {
      type: 'pie',
      data: {
        labels: ['Teacher', 'Admin', 'Parent'],
        datasets: [{
          data: <?= json_encode(array_values($userRolesData)) ?>,
          backgroundColor: ['#3b82f6', '#fbbf24', '#10b981'],
        }],
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
      }
    });

    // Active Schools Bar Chart
    const activeSchoolsCtx = document.getElementById('activeSchoolsChart').getContext('2d');
    new Chart(activeSchoolsCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($activeSchoolsLabels) ?>,
        datasets: [{
          label: 'Logins',
          data: <?= json_encode($activeSchoolsCounts) ?>,
          backgroundColor: '#3b82f6',
        }],
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
        plugins: { legend: { display: false } }
      }
    });

    // Monthly New Users Line Chart
    const monthlyUsersCtx = document.getElementById('monthlyUsersChart').getContext('2d');
    new Chart(monthlyUsersCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode($monthlyLabels) ?>,
        datasets: [{
          label: 'New Users',
          data: <?= json_encode($monthlyCounts) ?>,
          fill: true,
          borderColor: '#f59e0b',
          backgroundColor: 'rgba(245, 158, 11, 0.3)',
          tension: 0.3,
          pointRadius: 4,
          pointHoverRadius: 7,
        }],
      },
      options: {
        responsive: true,
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
        plugins: { legend: { position: 'top' } }
      }
    });
  </script>
</body>
</html>
