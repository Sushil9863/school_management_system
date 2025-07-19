<?php
include 'check_admin.php'; // Handles session, admin check, and $school_id

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
  <meta charset="UTF-8">
  <title>School Admin Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 font-sans">
  <div class="flex h-screen">
    <!-- Main Content -->
    <div class="flex-1 p-6 space-y-6 overflow-auto">
      <header class="flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-800">Admin Dashboard</h1>
        <span class="text-sm text-gray-600" id="current-date">📅 </span>
      </header>

      <!-- Stat Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-xl shadow-md">
          <p class="text-gray-500 text-sm">Total Teachers</p>
          <h2 class="text-3xl font-bold text-green-600 mt-2"><?php echo $counts['teachers']; ?></h2>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md">
          <p class="text-gray-500 text-sm">Total Students</p>
          <h2 class="text-3xl font-bold text-purple-600 mt-2"><?php echo $counts['students']; ?></h2>
        </div>
        <div class="bg-white p-6 rounded-xl shadow-md">
          <p class="text-gray-500 text-sm">Fees Collected</p>
          <h2 class="text-3xl font-bold text-yellow-600 mt-2">Rs. <?php echo number_format($counts['fees']); ?></h2>
        </div>
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Bar Chart: Students & Payments (Last 6 Months) -->
        <div class="bg-white p-6 rounded-xl shadow-md">
          <h3 class="text-lg font-semibold mb-4">Enrollment & Payments (Last 6 Months)</h3>
          <canvas id="enrollmentChart"></canvas>
        </div>

        <!-- Donut Chart: Class Distribution -->
        <div class="bg-white p-6 rounded-xl shadow-md">
          <h3 class="text-lg font-semibold mb-4">Students by Grade</h3>
          <canvas id="classChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Current Date
    const dateSpan = document.getElementById('current-date');
    const today = new Date();
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    dateSpan.innerHTML = `📅 ${today.toLocaleDateString('en-US', options)}`;

    // Bar Chart (Students & Payments)
    const ctx1 = document.getElementById('enrollmentChart').getContext('2d');
    new Chart(ctx1, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [
          {
            label: 'Students Enrolled',
            data: <?php echo json_encode($studentData); ?>,
            backgroundColor: '#3B82F6',
            yAxisID: 'yStudents'
          },
          {
            label: 'Fees Collected (Rs.)',
            data: <?php echo json_encode($paymentData); ?>,
            backgroundColor: '#F59E0B',
            yAxisID: 'yFees'
          }
        ]
      },
      options: {
        responsive: true,
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
        }
      }
    });

    // Donut Chart (Students by Grade)
    const ctx2 = document.getElementById('classChart').getContext('2d');
    new Chart(ctx2, {
      type: 'doughnut',
      data: {
        labels: <?php echo json_encode($classLabels); ?>,
        datasets: [{
          data: <?php echo json_encode($classCounts); ?>,
          backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#6366F1', '#DC2626']
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
      }
    });
  </script>
</body>
</html>
