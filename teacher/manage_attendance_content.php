<?php
include '../partials/dbconnect.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Load composer autoload for dompdf
require_once '../vendor/autoload.php';

// Check teacher login
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../index.php");
    exit;
}

$teacher_username = $_SESSION['username'];

// Get teacher info
$stmt = $conn->prepare("SELECT id, full_name FROM teachers WHERE username = ?");
$stmt->bind_param("s", $teacher_username);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

if (!$teacher) {
    die("Unauthorized: Teacher not found.");
}
$teacher_id = $teacher['id'];

// Fetch classes where this teacher is class teacher
$stmt = $conn->prepare("SELECT id, grade, section FROM classes WHERE class_teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($classes)) {
    die("You are not assigned as a class teacher for any class.");
}

// Since only one class per teacher, pick first
$class = $classes[0];
$selected_class_id = $class['id'];

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Handle attendance save
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $class_id = intval($_POST['class_id']);
    $date = $_POST['date'];
    $statuses = $_POST['status'] ?? [];

    if ($class_id !== $selected_class_id) {
        die("Invalid class selected.");
    }
    if (!$date) {
        die("Date is required.");
    }

    foreach ($statuses as $student_id => $status) {
        $student_id = intval($student_id);
        $status = in_array($status, ['present', 'absent', 'leave']) ? $status : 'present';

        $check = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND class_id = ? AND date = ?");
        $check->bind_param("iis", $student_id, $class_id, $date);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $check->bind_result($attendance_id);
            $check->fetch();

            $update = $conn->prepare("UPDATE attendance SET status = ? WHERE id = ?");
            $update->bind_param("si", $status, $attendance_id);
            $update->execute();
            $update->close();
        } else {
            $insert = $conn->prepare("INSERT INTO attendance (student_id, class_id, date, status) VALUES (?, ?, ?, ?)");
            $insert->bind_param("iiss", $student_id, $class_id, $date, $status);
            $insert->execute();
            $insert->close();
        }
        $check->close();
    }
    $message = "Attendance saved successfully for " . htmlspecialchars($date);
}

// Fetch students for selected class
$stmt = $conn->prepare("SELECT id, full_name FROM students WHERE class_id = ? ORDER BY full_name ASC");
$stmt->bind_param("i", $selected_class_id);
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch existing attendance for selected class and date
$attendance_map = [];
$stmt = $conn->prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND date = ?");
$stmt->bind_param("is", $selected_class_id, $selected_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $attendance_map[$row['student_id']] = $row['status'];
}

// Helper function to display symbols
function markSymbol($status)
{
    switch (strtolower($status)) {
        case 'present':
            return '✔';   // check mark
        case 'absent':
            return '✘';   // cross mark
        case 'leave':
            return '||';   // pause symbol
        default:
            return '';    // empty if unknown status
    }
}

// Helper: get number of days in selected month
// If $selected_month is 'YYYY-MM', get days count
$daysInMonth = 0;
if (preg_match('/^\d{4}-\d{2}$/', $selected_month)) {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, intval(substr($selected_month, 5, 2)), intval(substr($selected_month, 0, 4)));
} else {
    $daysInMonth = date('t'); // fallback current month days
}

// Prepare attendance data for the whole month (for PDF)
$monthly_attendance = [];

// Query all attendance for this class and month
$start_date = $selected_month . '-01';
$end_date = $selected_month . '-' . $daysInMonth;
$stmt = $conn->prepare("SELECT student_id, date, status FROM attendance WHERE class_id = ? AND date BETWEEN ? AND ?");
$stmt->bind_param("iss", $selected_class_id, $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $day = intval(substr($row['date'], 8, 2)); // get day from date
    $monthly_attendance[$row['student_id']][$day] = $row['status'];
}

// PDF Generation block
if (isset($_GET['download_pdf'])) {
    // Build the HTML report string with DejaVu Sans font
    ob_start();
    ?>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            font-family: "DejaVu Sans", sans-serif;
        }

        th,
        td {
            border: 1px solid black;
            padding: 5px;
            text-align: center;
        }
    </style>
    <h3 style="text-align:center;">Attendance Report for <?= htmlspecialchars($selected_month) ?></h3>
    <table>
        <thead>
            <tr>
                <th>Student Name</th>
                <?php for ($d = 1; $d <= $daysInMonth; $d++): ?>
                    <th><?= $d ?></th>
                <?php endfor; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $stu): ?>
                <tr>
                    <td style="text-align:left;"><?= htmlspecialchars($stu['full_name']) ?></td>
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $s = $monthly_attendance[$stu['id']][$d] ?? '';
                        ?>
                        <td><?= markSymbol($s) ?></td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    $html = ob_get_clean();

    $dompdf = new Dompdf();

    // Enable remote to load fonts if needed (not always necessary)
    $options = $dompdf->getOptions();
    $options->set('isRemoteEnabled', true);
    $dompdf->setOptions($options);

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    if (ob_get_length())
        ob_end_clean();

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="attendance_report_' . $selected_month . '.pdf"');
    echo $dompdf->output();
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Daily Attendance</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 p-6 min-h-screen">
    <div class="max-w-3xl mx-auto bg-white p-8 rounded-lg shadow-lg">
        <h1 class="text-3xl font-bold mb-6 text-center text-gray-800">
            Attendance of: Grade <?= htmlspecialchars($class['grade']) ?> - Section <?= htmlspecialchars($class['section']) ?>
        </h1>

        <?php if (!empty($message)): ?>
            <div id="successMsg" class="mb-6 p-4 bg-green-100 text-green-700 rounded flex justify-between items-center shadow">
                <span><?= $message ?></span>
                <button onclick="document.getElementById('successMsg').style.display='none'" aria-label="Close success message" class="font-bold px-3 hover:text-green-900">×</button>
            </div>
        <?php endif; ?>

        <!-- Date Selection Form -->
        <form method="GET" class="mb-6 flex flex-wrap gap-4 items-center justify-center">
            <label for="date" class="font-semibold text-gray-700">Select Date:</label>
            <input type="date" name="date" id="date" value="<?= htmlspecialchars($selected_date) ?>" class="border border-gray-300 p-2 rounded shadow-sm" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()" />
        </form>

        <!-- Attendance form -->
        <form method="POST" class="mb-6" id="attendanceForm">
            <input type="hidden" name="class_id" value="<?= $selected_class_id ?>" />
            <input type="hidden" name="date" value="<?= htmlspecialchars($selected_date) ?>" />
            <input type="hidden" name="save_attendance" value="1" />

            <table class="w-full border-collapse border border-gray-300">
                <thead>
                    <tr class="bg-gray-100 text-gray-700">
                        <th class="border border-gray-300 p-3 text-left">Student Name</th>
                        <th class="border border-gray-300 p-3 text-center">Attendance Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student):
                        $status = $attendance_map[$student['id']] ?? 'present';
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 p-3"><?= htmlspecialchars($student['full_name']) ?></td>
                            <td class="border border-gray-300 p-3 text-center">
                                <button
                                    type="button"
                                    class="status-btn rounded px-4 py-1 w-32 font-semibold transition-colors duration-200"
                                    data-student-id="<?= $student['id'] ?>"
                                    data-status="<?= $status ?>"
                                    style="<?= $status === 'present' ? 'background-color:#22c55e;color:white;' : ($status === 'absent' ? 'background-color:#ef4444;color:white;' : 'background-color:#fbbf24;color:black;') ?>"
                                    >
                                    <?= markSymbol($status) . ' ' . ucfirst($status) ?>
                                </button>
                                <input type="hidden" name="status[<?= $student['id'] ?>]" id="status-<?= $student['id'] ?>" value="<?= $status ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="2" class="text-center p-4 text-gray-500">No students found in this class.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="mt-6 text-right">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-8 rounded shadow">
                    Save Attendance
                </button>
            </div>
        </form>

        <!-- PDF Download Form -->
        <form method="GET" target="_blank" class="flex flex-wrap items-center gap-4 justify-center">
            <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
            <label for="month" class="font-semibold text-gray-700">Select Month for PDF:</label>
            <input type="month" id="month" name="month" value="<?= htmlspecialchars($selected_month) ?>" max="<?= date('Y-m') ?>" class="border border-gray-300 p-2 rounded shadow-sm" required />
            <button type="submit" name="download_pdf" value="1" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded shadow">
                Download Monthly Attendance PDF
            </button>
        </form>
    </div>

    <script>
        const statuses = ['present', 'absent', 'leave'];

        // Map status to symbol and text
        function markSymbol(status) {
            switch (status) {
                case 'present': return '✔ Present';
                case 'absent': return '✘ Absent';
                case 'leave': return '⏸ Leave';
                default: return '';
            }
        }

        // Map status to color styles
        function statusColor(status) {
            switch (status) {
                case 'present': return ['#22c55e', 'white']; // green bg, white text
                case 'absent': return ['#ef4444', 'white'];  // red bg, white text
                case 'leave': return ['#fbbf24', 'black'];   // amber bg, black text
                default: return ['gray', 'black'];
            }
        }

        document.querySelectorAll('.status-btn').forEach(button => {
            button.addEventListener('click', () => {
                let currentStatus = button.getAttribute('data-status');
                let studentId = button.getAttribute('data-student-id');
                let currentIndex = statuses.indexOf(currentStatus);
                let nextIndex = (currentIndex + 1) % statuses.length;
                let nextStatus = statuses[nextIndex];

                // Update button display
                button.setAttribute('data-status', nextStatus);
                button.textContent = markSymbol(nextStatus);

                // Update button colors
                const [bgColor, textColor] = statusColor(nextStatus);
                button.style.backgroundColor = bgColor;
                button.style.color = textColor;

                // Update hidden input value
                const hiddenInput = document.getElementById('status-' + studentId);
                if (hiddenInput) {
                    hiddenInput.value = nextStatus;
                }
            });
        });
    </script>
</body>

</html>
