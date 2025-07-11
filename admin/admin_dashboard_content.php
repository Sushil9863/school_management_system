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

  <!-- Main content -->
  <div class="flex-1 p-6 space-y-6 overflow-auto">
    <header class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-800">Admin Dashboard</h1>
      <span class="text-sm text-gray-600" id="current-date">📅 </span>
    </header>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
      <div class="bg-white p-6 rounded-xl shadow-md">
        <p class="text-gray-500 text-sm">Total Schools</p>
        <h2 class="text-3xl font-bold text-blue-600 mt-2">4</h2>
      </div>
      <div class="bg-white p-6 rounded-xl shadow-md">
        <p class="text-gray-500 text-sm">Total Teachers</p>
        <h2 class="text-3xl font-bold text-green-600 mt-2">58</h2>
      </div>
      <div class="bg-white p-6 rounded-xl shadow-md">
        <p class="text-gray-500 text-sm">Total Students</p>
        <h2 class="text-3xl font-bold text-purple-600 mt-2">1,240</h2>
      </div>
      <div class="bg-white p-6 rounded-xl shadow-md">
        <p class="text-gray-500 text-sm">Fees Collected</p>
        <h2 class="text-3xl font-bold text-yellow-600 mt-2">₹ 2,40,000</h2>
      </div>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Bar Chart -->
      <div class="bg-white p-6 rounded-xl shadow-md">
        <h3 class="text-lg font-semibold mb-4">Enrollment (Last 6 Months)</h3>
        <canvas id="enrollmentChart"></canvas>
      </div>

      <!-- Donut Chart -->
      <div class="bg-white p-6 rounded-xl shadow-md">
        <h3 class="text-lg font-semibold mb-4">Class Distribution</h3>
        <canvas id="classChart"></canvas>
      </div>
    </div>
  </div>
</div>

<script>

  const dateSpan = document.getElementById('current-date');
  const today = new Date();

  const options = { year: 'numeric', month: 'long', day: 'numeric' };
  const formattedDate = today.toLocaleDateString('en-US', options);

  dateSpan.innerHTML = `📅 ${formattedDate}`;




  // Bar Chart
  const ctx1 = document.getElementById('enrollmentChart').getContext('2d');
  new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: ['Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
      datasets: [{
        label: 'Students Enrolled',
        data: [120, 150, 200, 180, 240, 210],
        backgroundColor: '#3B82F6'
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } }
    }
  });

  // Donut Chart
  const ctx2 = document.getElementById('classChart').getContext('2d');
  new Chart(ctx2, {
    type: 'doughnut',
    data: {
      labels: ['Grade 1-3', 'Grade 4-6', 'Grade 7-10', 'Grade 11-12'],
      datasets: [{
        data: [300, 400, 320, 220],
        backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444']
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom' }
      }
    }
  });
</script>

</body>
</html>