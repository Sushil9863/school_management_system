<?php
include '../partials/dbconnect.php';

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../index.php');
    exit;
}

// First, get the parent ID from the user ID
$stmt = $conn->prepare("SELECT id, full_name FROM parents WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$parent_result = $stmt->get_result()->fetch_assoc();

if (!$parent_result) {
    echo "No parent found for user ID: $user_id";
    exit;
}

$parent_id = $parent_result['id'];
$parent_name = $parent_result['full_name'];

// Fetch children using the parent ID
$stmt = $conn->prepare("SELECT s.id, s.full_name, s.class_id FROM students s WHERE s.parent_id = ?");
$stmt->bind_param("i", $parent_id);
$stmt->execute();
$children = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// For each child, fetch extra info
foreach ($children as &$child) {
    $student_id = $child['id'];
    $class_id = $child['class_id'];

    // Attendance %
    $stmt = $conn->prepare("SELECT 
        ROUND(100 * SUM(status = 'present') / COUNT(*), 1) AS attendance_percent 
        FROM attendance WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $att = $stmt->get_result()->fetch_assoc();
    $child['attendance_percent'] = $att['attendance_percent'] ?? null;

    // Get exam results with all subjects - UPDATED QUERY FOR YOUR DB STRUCTURE
    $stmt = $conn->prepare("
        SELECT e.id as exam_id, e.exam_name, e.exam_type, s.name, m.marks,
               es.full_marks, es.pass_marks
        FROM marks m 
        JOIN exams e ON m.exam_id = e.id 
        JOIN subjects s ON m.subject_id = s.id
        LEFT JOIN exam_subjects es ON e.id = es.exam_id AND s.id = es.subject_id
        WHERE m.student_id = ? 
        ORDER BY e.created_at DESC, s.name ASC
    ");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $allResults = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Organize results by exam
    $examResults = [];
    foreach ($allResults as $result) {
        $examId = $result['exam_id'];
        if (!isset($examResults[$examId])) {
            $examResults[$examId] = [
                'exam_name' => $result['exam_name'],
                'exam_type' => $result['exam_type'],
                'subjects' => []
            ];
        }
        
        // Calculate percentage if we have full_marks
        $percentage = null;
        if (!empty($result['full_marks']) && $result['full_marks'] > 0) {
            $percentage = round(($result['marks'] / $result['full_marks']) * 100, 1);
        }
        
        $examResults[$examId]['subjects'][] = [
            'subject_name' => $result['name'],
            'marks' => $result['marks'],
            'full_marks' => $result['full_marks'],
            'pass_marks' => $result['pass_marks'],
            'percentage' => $percentage,
            'passed' => !empty($result['pass_marks']) ? ($result['marks'] >= $result['pass_marks']) : null
        ];
    }
    
    // Calculate overall exam statistics
    foreach ($examResults as $examId => $exam) {
        $total_marks = 0;
        $total_full_marks = 0;
        $passed_subjects = 0;
        $total_subjects = count($exam['subjects']);
        
        foreach ($exam['subjects'] as $subject) {
            $total_marks += $subject['marks'];
            if (!empty($subject['full_marks'])) {
                $total_full_marks += $subject['full_marks'];
            }
            if ($subject['passed'] === true) {
                $passed_subjects++;
            }
        }
        
        $overall_percentage = ($total_full_marks > 0) ? round(($total_marks / $total_full_marks) * 100, 1) : null;
        $examResults[$examId]['total_marks'] = $total_marks;
        $examResults[$examId]['total_full_marks'] = $total_full_marks;
        $examResults[$examId]['overall_percentage'] = $overall_percentage;
        $examResults[$examId]['passed_subjects'] = $passed_subjects;
        $examResults[$examId]['total_subjects'] = $total_subjects;
    }
    
    $child['all_exams'] = $examResults;

    // UPDATED: Outstanding fees calculation using the new fees table
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_fees,
            COALESCE(SUM(paid_amount), 0) as total_paid,
            COALESCE(SUM(due_amount), 0) as total_due
        FROM fees 
        WHERE student_id = ? AND school_id = ?
    ");
    $stmt->bind_param("ii", $student_id, $_SESSION['school_id']);
    $stmt->execute();
    $fees = $stmt->get_result()->fetch_assoc();
    $child['outstanding_fees'] = $fees['total_due'] ?? 0;
    $child['total_fees'] = $fees['total_fees'] ?? 0;
    $child['paid_fees'] = $fees['total_paid'] ?? 0;

    // Class teacher info
    $stmt = $conn->prepare("SELECT t.id, t.full_name, t.email FROM teachers t
        JOIN classes c ON c.class_teacher_id = t.id
        WHERE c.id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $teacher = $stmt->get_result()->fetch_assoc();
    $child['teacher_name'] = $teacher['full_name'] ?? 'N/A';
    $child['teacher_email'] = $teacher['email'] ?? '';
    $child['teacher_id'] = $teacher['id'] ?? null;
}
unset($child);

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $teacher_id = $_POST['teacher_id'];
    $student_id = $_POST['student_id'];
    $message = trim($_POST['message']);
    
    if (!empty($message) && !empty($teacher_id)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, student_id, message, sender_type) 
                               VALUES (?, ?, ?, ?, 'parent')");
        $stmt->bind_param("iiis", $parent_id, $teacher_id, $student_id, $message);
        $stmt->execute();
        
        $message_sent = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Parent Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function openMessageModal(teacherId, teacherName, studentId, studentName) {
            document.getElementById('teacher_id').value = teacherId;
            document.getElementById('student_id').value = studentId;
            document.getElementById('modalTeacherName').textContent = teacherName;
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('messageModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('messageModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Function to view fee details
        function viewFeeDetails(studentId, studentName) {
            window.location.href = `student_payments.php?student_id=${studentId}`;
        }
    </script>
</head>

<body class="bg-gray-50 min-h-screen font-sans text-gray-800">

    <div class="max-w-7xl mx-auto p-6">
        <!-- Greeting -->
        <header class="mb-8">
            <h1 class="text-3xl font-bold">Welcome, <?= htmlspecialchars($parent_name ?? 'Parent') ?>!</h1>
            <p class="text-gray-600 mt-1">Here's the latest update for your
                child<?= count($children) > 1 ? 'ren' : '' ?>.</p>
        </header>

        <?php if (isset($message_sent) && $message_sent): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6">
                Your message has been sent successfully!
            </div>
        <?php endif; ?>

        <?php if (empty($children)): ?>
            <p class="text-center text-red-500">No children linked to your account.</p>
        <?php else: ?>
            <div class="space-y-8">
                <?php foreach ($children as $child): ?>
                    <section class="bg-white rounded-lg shadow p-6 border border-gray-200">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4">
                            <div class="flex items-center space-x-4">
                                <div>
                                    <h2 class="text-xl font-semibold"><?= htmlspecialchars($child['full_name']) ?></h2>
                                    <!-- You can add class/section name here if you want -->
                                </div>
                            </div>

                            <div class="mt-4 md:mt-0 flex space-x-4 text-sm font-medium">
                                <div>
                                    <span class="block text-gray-500">Attendance</span>
                                    <span class="inline-block mt-1 px-3 py-1 rounded-full
                                        <?= ($child['attendance_percent'] ?? 0) >= 90 ? 'bg-green-100 text-green-800' : 
                                           (($child['attendance_percent'] ?? 0) >= 75 ? 'bg-yellow-100 text-yellow-800' : 
                                           'bg-red-100 text-red-800') ?>">
                                        <?= $child['attendance_percent'] !== null ? $child['attendance_percent'] . '%' : 'N/A' ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="block text-gray-500">Outstanding Fees</span>
                                    <span class="inline-block mt-1 px-3 py-1 rounded-full
                                        <?= ($child['outstanding_fees'] ?? 0) > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                        Rs <?= number_format($child['outstanding_fees'] ?? 0, 0) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Fee Summary Section -->
                        <div class="border-t pt-4 mt-4">
                            <h3 class="text-lg font-semibold mb-3">Fee Summary</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div class="bg-blue-50 p-4 rounded-lg border border-blue-100">
                                    <h4 class="text-sm font-medium text-blue-800">Total Fees</h4>
                                    <p class="text-xl font-bold text-blue-900">Rs. <?= number_format($child['total_fees'], 2) ?></p>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg border border-green-100">
                                    <h4 class="text-sm font-medium text-green-800">Total Paid</h4>
                                    <p class="text-xl font-bold text-green-900">Rs. <?= number_format($child['paid_fees'], 2) ?></p>
                                </div>
                                <div class="bg-red-50 p-4 rounded-lg border border-red-100">
                                    <h4 class="text-sm font-medium text-red-800">Total Due</h4>
                                    <p class="text-xl font-bold text-red-900">Rs. <?= number_format($child['outstanding_fees'], 2) ?></p>
                                </div>
                            </div>
                            <button onclick="viewFeeDetails(<?= $child['id'] ?>, '<?= htmlspecialchars($child['full_name']) ?>')" 
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                                View Fee Details
                            </button>
                        </div>

                        <!-- Exam Results Section -->
                        <div class="border-t pt-4 mt-4">
                            <h3 class="text-lg font-semibold mb-3">Exam Results</h3>
                            <?php if (!empty($child['all_exams'])): ?>
                                <?php foreach ($child['all_exams'] as $examId => $exam): ?>
                                    <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                                        <div class="flex justify-between items-center mb-3">
                                            <h4 class="font-medium text-blue-700 text-lg">
                                                <?= htmlspecialchars($exam['exam_name']) ?> 
                                                <span class="text-sm text-gray-600">(<?= htmlspecialchars($exam['exam_type']) ?>)</span>
                                            </h4>
                                            <?php if (isset($exam['overall_percentage'])): ?>
                                                <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-800 font-medium">
                                                    Overall: <?= $exam['overall_percentage'] ?>%
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (isset($exam['passed_subjects'])): ?>
                                            <div class="mb-3 text-sm text-gray-600">
                                                Passed: <?= $exam['passed_subjects'] ?> of <?= $exam['total_subjects'] ?> subjects
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="overflow-x-auto">
                                            <table class="w-full border-collapse">
                                                <thead>
                                                    <tr class="bg-gray-200">
                                                        <th class="p-2 text-left border">Subject</th>
                                                        <th class="p-2 text-center border">Marks Obtained</th>
                                                        <th class="p-2 text-center border">Full Marks</th>
                                                        <th class="p-2 text-center border">Percentage</th>
                                                        <th class="p-2 text-center border">Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($exam['subjects'] as $subject): ?>
                                                        <tr class="hover:bg-gray-100">
                                                            <td class="p-2 border font-medium"><?= htmlspecialchars($subject['subject_name']) ?></td>
                                                            <td class="p-2 border text-center"><?= htmlspecialchars($subject['marks']) ?></td>
                                                            <td class="p-2 border text-center"><?= !empty($subject['full_marks']) ? htmlspecialchars($subject['full_marks']) : 'N/A' ?></td>
                                                            <td class="p-2 border text-center">
                                                                <?= $subject['percentage'] !== null ? $subject['percentage'] . '%' : 'N/A' ?>
                                                            </td>
                                                            <td class="p-2 border text-center">
                                                                <?php if ($subject['passed'] === true): ?>
                                                                    <span class="text-green-600 font-medium">Passed</span>
                                                                <?php elseif ($subject['passed'] === false): ?>
                                                                    <span class="text-red-600 font-medium">Failed</span>
                                                                <?php else: ?>
                                                                    <span class="text-gray-500">N/A</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                    <?php if (isset($exam['total_marks']) && isset($exam['total_full_marks'])): ?>
                                                        <tr class="bg-gray-50 font-medium">
                                                            <td class="p-2 border">Total</td>
                                                            <td class="p-2 border text-center"><?= $exam['total_marks'] ?></td>
                                                            <td class="p-2 border text-center"><?= $exam['total_full_marks'] ?></td>
                                                            <td class="p-2 border text-center"><?= $exam['overall_percentage'] ?>%</td>
                                                            <td class="p-2 border text-center">
                                                                <?= $exam['passed_subjects'] == $exam['total_subjects'] ? 
                                                                    '<span class="text-green-600">Overall Pass</span>' : 
                                                                    '<span class="text-red-600">Needs Improvement</span>' ?>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-gray-500">No exam results available yet.</p>
                            <?php endif; ?>
                        </div>

                        <div class="border-t pt-4 flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                            <div class="text-gray-700">
                                <p><strong>Class Teacher:</strong> <?= htmlspecialchars($child['teacher_name']) ?></p>
                                <p><strong>Teacher Email:</strong> 
                                    <a href="mailto:<?= htmlspecialchars($child['teacher_email']) ?>"
                                       class="text-blue-600 hover:underline">
                                        <?= htmlspecialchars($child['teacher_email']) ?: '-' ?>
                                    </a>
                                </p>
                            </div>
                            <div class="flex space-x-4">
                                <?php if (!empty($child['teacher_id'])): ?>
                                    <button onclick="openMessageModal(
                                        '<?= $child['teacher_id'] ?>', 
                                        '<?= htmlspecialchars($child['teacher_name']) ?>',
                                        '<?= $child['id'] ?>',
                                        '<?= htmlspecialchars($child['full_name']) ?>'
                                    )" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                                        Message Teacher
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Message Teacher</h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <p class="text-gray-600 mb-2">Teacher: <span id="modalTeacherName" class="font-medium"></span></p>
            <p class="text-gray-600 mb-4">Student: <span id="modalStudentName" class="font-medium"></span></p>
            
            <form method="POST">
                <input type="hidden" name="teacher_id" id="teacher_id">
                <input type="hidden" name="student_id" id="student_id">
                
                <div class="mb-4">
                    <label for="message" class="block text-gray-700 mb-2">Your Message:</label>
                    <textarea name="message" id="message" rows="4" 
                              class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              required placeholder="Type your message here..."></textarea>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" 
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400 transition">
                        Cancel
                    </button>
                    <button type="submit" name="send_message" 
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition">
                        Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>