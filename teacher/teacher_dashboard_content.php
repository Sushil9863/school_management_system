<?php
include '../partials/dbconnect.php';

// Authentication & teacher info
if (!isset($_SESSION['username']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../index.php");
    exit;
}

$teacher_username = $_SESSION['username'];

// Get teacher info with school_id
$stmt = $conn->prepare("SELECT id, full_name, school_id FROM teachers WHERE username = ?");
$stmt->bind_param("s", $teacher_username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Unauthorized: Teacher not found.");
}

$teacher = $result->fetch_assoc();
$teacher_id = $teacher['id'];
$teacher_name = $teacher['full_name'];
$school_id = $teacher['school_id'];

// Fetch assigned classes and subjects (group subjects per class)
$query = "
    SELECT DISTINCT c.id as class_id, c.grade, c.section, c.type, 
           c.class_teacher_id, s.name AS subject_name
    FROM subjects s
    JOIN classes c ON s.class_id = c.id
    WHERE s.teacher_id = ? AND c.school_id = ?
    ORDER BY c.grade ASC, c.section ASC, s.name ASC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $teacher_id, $school_id);
$stmt->execute();
$assigned = $stmt->get_result();

$classes = [];
$allSubjects = [];
while ($row = $assigned->fetch_assoc()) {
    $cid = $row['class_id'];
    if (!isset($classes[$cid])) {
        $classes[$cid] = [
            'grade' => $row['grade'],
            'section' => $row['section'],
            'type' => $row['type'],
            'class_teacher_id' => $row['class_teacher_id'],
            'subjects' => [],
            'student_count' => 0,
            'avg_percent' => null, // placeholder for avg results
        ];
    }
    $classes[$cid]['subjects'][] = $row['subject_name'];
    $allSubjects[$row['subject_name']] = true;
}

// Fetch student count per class
if (!empty($classes)) {
    $classIds = implode(',', array_keys($classes));
    $res = $conn->query("SELECT class_id, COUNT(*) as cnt FROM students WHERE class_id IN ($classIds) GROUP BY class_id");
    while ($row = $res->fetch_assoc()) {
        $cid = $row['class_id'];
        if (isset($classes[$cid])) {
            $classes[$cid]['student_count'] = intval($row['cnt']);
        }
    }
}

// Placeholder: fetch recent exam average percentage per class (dummy example)
// You can replace with your actual query logic to fetch results data per class
foreach ($classes as $cid => &$class) {
    // Example: Random avg percent for demo (replace with real data)
    $class['avg_percent'] = rand(60, 95);
}
unset($class);

// Count total classes, subjects, students
$totalClasses = count($classes);
$totalSubjects = count($allSubjects);
$totalStudents = 0;
foreach ($classes as $class) {
    $totalStudents += $class['student_count'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Teacher Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gradient-to-r from-blue-100 to-indigo-100 min-h-screen p-8 font-sans">

    <div class="max-w-7xl mx-auto">

        <!-- Greeting -->
        <div class="mb-10 text-center">
            <h1 class="text-4xl font-extrabold text-gray-900">Welcome back, <span
                    class="text-indigo-600"><?= htmlspecialchars($teacher_name) ?></span>!</h1>
            <p class="mt-2 text-gray-700 text-lg">Here is your classroom overview for today.</p>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">

            <div class="bg-white shadow-lg rounded-lg p-6 flex items-center space-x-4 border-l-8 border-indigo-600">
                <div class="text-indigo-600">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?= $totalClasses ?></p>
                    <p class="text-gray-500">Classes Assigned</p>
                </div>
            </div>

            <div class="bg-white shadow-lg rounded-lg p-6 flex items-center space-x-4 border-l-8 border-green-500">
                <div class="text-green-500">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M12 20l9-5-9-5-9 5 9 5z"></path>
                        <path d="M12 12v8"></path>
                        <path d="M3 8l9 5 9-5"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?= $totalSubjects ?></p>
                    <p class="text-gray-500">Subjects You Teach</p>
                </div>
            </div>

            <div class="bg-white shadow-lg rounded-lg p-6 flex items-center space-x-4 border-l-8 border-yellow-500">
                <div class="text-yellow-500">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="7" r="4"></circle>
                        <path d="M5.5 21a6.5 6.5 0 0113 0"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900"><?= $totalStudents ?></p>
                    <p class="text-gray-500">Total Students</p>
                </div>
            </div>

        </div>

        <!-- Classes table -->
        <section class="mb-12">
            <h2 class="text-2xl font-semibold mb-6 text-gray-800">Your Classes</h2>

            <div class="overflow-x-auto rounded-lg shadow-lg bg-white">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-indigo-50 text-indigo-700">
                        <tr>
                            <th class="px-6 py-3 text-left text-sm font-semibold uppercase tracking-wider">Grade</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold uppercase tracking-wider">Section</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold uppercase tracking-wider">Class Type
                            </th>
                            <th class="px-6 py-3 text-left text-sm font-semibold uppercase tracking-wider">Subjects</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-sm font-semibold uppercase tracking-wider">Students</th>
                            <!-- <th class="px-6 py-3 text-left text-sm font-semibold uppercase tracking-wider">Avg. Performance</th> -->
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <?php foreach ($classes as $cid => $class): ?>
                            <tr class="hover:bg-indigo-50 cursor-pointer">
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($class['grade']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($class['section']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($class['type']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap max-w-xs">
                                    <?= htmlspecialchars(implode(', ', $class['subjects'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($class['class_teacher_id'] == $teacher_id): ?>
                                        <span
                                            class="inline-block bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold">Class
                                            Teacher</span>
                                    <?php else: ?>
                                        <span
                                            class="inline-block bg-gray-200 text-gray-600 px-2 py-1 rounded text-xs">Teacher</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center font-semibold">
                                    <?= $class['student_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($classes)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-6 text-gray-500">No classes assigned yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Announcements section -->
        <?php
        // Assuming $conn is your mysqli connection and $school_id is set from session or context
        
        // Fetch the 3 most recent announcements for this school
        $stmt = $conn->prepare("
  SELECT title, content, created_at
  FROM announcements
  WHERE school_id = ?
  ORDER BY created_at DESC
  LIMIT 3
");
        $stmt->bind_param("i", $school_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // Function to pick border and bg color based on index for variety
        function announcementColors($index)
        {
            $colors = [
                ['border' => 'border-indigo-500', 'bg' => 'bg-indigo-50', 'text' => 'text-indigo-700'],
                ['border' => 'border-yellow-400', 'bg' => 'bg-yellow-50', 'text' => 'text-yellow-700'],
                ['border' => 'border-green-400', 'bg' => 'bg-green-50', 'text' => 'text-green-700'],
            ];
            return $colors[$index % count($colors)];
        }
        ?>

        <section>
            <h2 class="text-2xl font-semibold mb-6 text-gray-800">Announcements</h2>
            <div class="bg-white p-6 rounded-lg shadow space-y-4 max-w-xl">
                <?php if ($result->num_rows > 0): ?>
                    <?php $i = 0; ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        $colors = announcementColors($i);
                        $date = date('F j, Y', strtotime($row['created_at']));
                        ?>
                        <div class="p-4 border-l-4 <?= $colors['border'] ?> <?= $colors['bg'] ?> <?= $colors['text'] ?>">
                            <p><strong>📢 <?= htmlspecialchars($row['title']) ?></strong></p>
                            <p class="text-sm"><?= nl2br(htmlspecialchars($row['content'])) ?></p>
                            <time datetime="<?= date('Y-m-d', strtotime($row['created_at'])) ?>"
                                class="block mt-1 text-xs text-gray-500"><?= $date ?></time>
                        </div>
                        <?php $i++; ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-gray-500">No announcements at this time.</p>
                <?php endif; ?>
            </div>
        </section>

    </div>

   <?php include '../partials/footer.php'; ?>
</body>

</html>