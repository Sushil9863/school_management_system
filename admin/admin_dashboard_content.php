<?php
include 'check_admin.php'; // Handles session, admin check, and $school_id

// Fetch admin username from session
$admin_username = $_SESSION['username'] ?? 'Admin';

// Fetch school info (name, address)
$stmt = $conn->prepare("SELECT name, address FROM schools WHERE id = ?");
$stmt->bind_param("i", $school_id);
$stmt->execute();
$schoolRes = $stmt->get_result();
$school = $schoolRes->fetch_assoc() ?? ['name' => 'Unknown School', 'address' => ''];

// Fetch counts for cards
$counts = [
  'teachers' => 0,
  'students' => 0,
  'fees' => 0
];

// Teachers
$res = $conn->query("SELECT COUNT(*) AS c FROM teachers WHERE school_id = $school_id");
$counts['teachers'] = $res->fetch_assoc()['c'] ?? 0;

// Students
$res = $conn->query("SELECT COUNT(*) AS c FROM students WHERE school_id = $school_id");
$counts['students'] = $res->fetch_assoc()['c'] ?? 0;

// Total Fees collected
$res = $conn->query("SELECT SUM(amount) AS total FROM payments WHERE school_id = $school_id");
$counts['fees'] = $res->fetch_assoc()['total'] ?? 0;

// Prepare data for charts (last 6 months payments & student enrollment trend)
$months = [];
$studentData = [];
$paymentData = [];

// Generate last 6 months dynamically
for ($i = 5; $i >= 0; $i--) {
  $monthLabel = date("M", strtotime("-$i month"));
  $months[] = $monthLabel;

  $monthStart = date("Y-m-01 00:00:00", strtotime("-$i month"));
  $monthEnd = date("Y-m-t 23:59:59", strtotime("-$i month"));

  // Students admitted this month
  $res = $conn->query("
        SELECT COUNT(*) AS c 
        FROM students 
        WHERE school_id = $school_id
        AND admitted_at BETWEEN '$monthStart' AND '$monthEnd'
    ");
  $studentData[] = (int) ($res->fetch_assoc()['c'] ?? 0);

  // Payments collected this month
  $res = $conn->query("
        SELECT SUM(amount) AS total 
        FROM payments 
        WHERE school_id = $school_id
    ");
  $paymentData[] = (float) ($res->fetch_assoc()['total'] ?? 0);
}

// Class distribution (student counts by grade)
$classLabels = [];
$classCounts = [];

$res = $conn->query("
  SELECT c.grade AS class_name, COUNT(s.id) AS c
  FROM students s
  JOIN classes c ON s.class_id = c.id
  WHERE s.school_id = $school_id
  GROUP BY c.grade
  ORDER BY c.grade ASC
");

while ($row = $res->fetch_assoc()) {
  $classLabels[] = $row['class_name']; // grade from classes table
  $classCounts[] = $row['c'];          // student count
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>School Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gradient-to-tr from-blue-50 to-indigo-100 font-sans min-h-screen">

  <div class="max-w-7xl mx-auto p-6">
    <!-- Header -->
    <!-- Header -->
    <header class="mb-10 text-center">
      <h1 class="text-5xl font-extrabold text-indigo-900 drop-shadow-md">
        <?= ucwords(htmlspecialchars($school['name'])) ?>
      </h1>

      <?php if ($school['address']): ?>
        <p class="text-indigo-700 text-lg mt-1 font-semibold"><?= htmlspecialchars($school['address']) ?></p>
      <?php endif; ?>
      <p class="mt-6 text-xl text-gray-700">
        Welcome back, <span class="text-blue-700 font-semibold"><?= htmlspecialchars($admin_username) ?></span> 👋
      </p>
      <div class="mt-2 text-gray-500 text-sm" id="current-date"></div>
    </header>


    <!-- Stats Cards -->
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-8 mb-10">
      <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
        <p class="text-gray-500 uppercase font-semibold tracking-wide">Total Teachers</p>
        <p class="text-4xl font-bold text-green-600 mt-2"><?= $counts['teachers'] ?></p>
        <p class="mt-2 text-sm text-green-700">Active educators in your school</p>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
        <p class="text-gray-500 uppercase font-semibold tracking-wide">Total Students</p>
        <p class="text-4xl font-bold text-purple-600 mt-2"><?= $counts['students'] ?></p>
        <p class="mt-2 text-sm text-purple-700">Learners currently enrolled</p>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow">
        <p class="text-gray-500 uppercase font-semibold tracking-wide">Fees Collected</p>
        <p class="text-4xl font-bold text-yellow-600 mt-2">Rs. <?= number_format($counts['fees']) ?></p>
        <p class="mt-2 text-sm text-yellow-700">Total fees received to date</p>
      </div>
    </section>

    <!-- Charts Section -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-10">
      <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-semibold mb-6 text-gray-800">Enrollment & Payments (Last 6 Months)</h2>
        <canvas id="enrollmentChart" class="w-full"></canvas>
      </div>

      <div class="bg-white rounded-xl shadow-lg p-6">
        <h2 class="text-xl font-semibold mb-6 text-gray-800">Students by Grade</h2>
        <canvas id="classChart" class="w-full"></canvas>
      </div>
    </section>
  </div>

  <script>
    // Current Date Display
    const dateSpan = document.getElementById('current-date');
    const today = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    dateSpan.textContent = `📅 ${today.toLocaleDateString('en-US', options)}`;

    // Enrollment & Payments Chart
    const ctx1 = document.getElementById('enrollmentChart').getContext('2d');
    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: <?= json_encode($months) ?>,
        datasets: [
          {
            label: 'Students Enrolled',
            data: <?= json_encode($studentData) ?>,
            backgroundColor: '#3B82F6',
            yAxisID: 'yStudents'
          },
          {
            label: 'Fees Collected (Rs.)',
            data: <?= json_encode($paymentData) ?>,
            backgroundColor: '#F59E0B',
            yAxisID: 'yFees'
          }
        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
          yStudents: {
            type: 'linear',
            position: 'left',
            beginAtZero: true,
            ticks: { precision: 0 }
          },
          yFees: {
            type: 'linear',
            position: 'right',
            beginAtZero: true,
            grid: { drawOnChartArea: false }
          }
        },
        plugins: {
          legend: { position: 'top' },
          tooltip: { enabled: true }
        }
      }
    });

    // Students by Grade Donut Chart
    const ctx2 = document.getElementById('classChart').getContext('2d');
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: <?= json_encode($classLabels) ?>,
        datasets: [{
          data: <?= json_encode($classCounts) ?>,
          backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#6366F1', '#DC2626']
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
      }
    });
  </script>


<?php include '../partials/footer.php'; ?>

  </div>

  <script>
    // Responsive adjustments
    function adjustLayout() {
      const sidebar = document.getElementById('sidebar');
      const mainContent = document.getElementById('main-content');
      if (window.innerWidth < 768) {
        sidebar.classList.add('-translate-x-full');
        mainContent.style.marginLeft = '0';
        mainContent.style.width = '100vw';
      } else {
        sidebar.classList.remove('-translate-x-full');
        mainContent.style.marginLeft = '16rem';
        mainContent.style.width = 'calc(100vw - 16rem)';
      }
    }

    window.addEventListener('resize', adjustLayout);
    window.addEventListener('load', adjustLayout);
  </script>
</body>

</html>