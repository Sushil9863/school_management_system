<?php
include '../partials/dbconnect.php';

$school_id = $_SESSION['school_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

if (!$school_id || !$user_id) {
    die("Access denied. Missing school or user session.");
}

$teacherQuery = $conn->prepare("SELECT id FROM teachers WHERE user_id = ? LIMIT 1");
$teacherQuery->bind_param("i", $user_id);
$teacherQuery->execute();
$teacherResult = $teacherQuery->get_result();

if ($teacherResult->num_rows === 0) {
    die("You are not assigned as a teacher in the system.");
}

$teacher = $teacherResult->fetch_assoc();
$teacher_id = $teacher['id'];

$classQuery = $conn->prepare("SELECT id, grade, section FROM classes WHERE class_teacher_id = ? AND school_id = ? LIMIT 1");
$classQuery->bind_param("ii", $teacher_id, $school_id);
$classQuery->execute();
$classResult = $classQuery->get_result();

if ($classResult->num_rows === 0) {
    die("No class assigned to you as class teacher.");
}

$class = $classResult->fetch_assoc();
$class_id = $class['id'];
$grade = $class['grade'];
$section = $class['section'];

$exams = [];
$examQuery = $conn->query("
    SELECT DISTINCT exam_name, id, exam_type 
    FROM exams 
    WHERE school_id = $school_id
    ORDER BY exam_name
");
while ($row = $examQuery->fetch_assoc()) {
    $exams[] = $row;
}
array_unshift($exams, ['id' => '0', 'exam_name' => 'Final Exam', 'exam_type' => 'Final']);

function getGradeAndGPA($percent)
{
    if ($percent >= 90) return ['A+', 4.0];
    if ($percent >= 80) return ['A', 3.6];
    if ($percent >= 70) return ['B+', 3.2];
    if ($percent >= 60) return ['B', 2.8];
    if ($percent >= 50) return ['C+', 2.4];
    if ($percent >= 40) return ['C', 2.0];
    if ($percent >= 35) return ['D', 1.6];
    return ['NG', 0.0];
}

$view_mode = $_GET['view_mode'] ?? 'marks';
$results = [];
$subjects = [];
$subjectsById = [];
$marksInfo = [];

if (isset($_GET['exam_id'])) {
    $exam_id = $_GET['exam_id'];

    $studentsRes = $conn->prepare("
        SELECT st.id, st.full_name, c.grade, c.section
        FROM students st
        JOIN classes c ON st.class_id = c.id
        WHERE c.id = ? AND c.school_id = ? AND c.section = ?
        ORDER BY st.full_name
    ");
    $studentsRes->bind_param("iis", $class_id, $school_id, $section);
    $studentsRes->execute();
    $studentsResult = $studentsRes->get_result();

    $studentIds = [];
    while ($st = $studentsResult->fetch_assoc()) {
        $studentIds[] = $st['id'];
        $results[$st['id']] = [
            'full_name' => $st['full_name'],
            'grade' => $st['grade'],
            'section' => $st['section'],
            'marks' => [],
            'subject_full' => [],
            'subject_pass' => [],
            'total' => 0,
            'percent' => 0,
            'result' => 'Fail',
            'letter' => 'NG',
            'gpa' => 0.0,
            'exam_count' => []
        ];
    }

    if (!empty($studentIds)) {
        $ids = implode(',', $studentIds);

        if ($exam_id === '0') {
            $terminalExams = [];
            $examRes = $conn->query("
                SELECT id FROM exams
                WHERE school_id = $school_id
                AND exam_type = 'Terminal'
            ");
            while ($exam = $examRes->fetch_assoc()) {
                $terminalExams[] = $exam['id'];
            }

            if (!empty($terminalExams)) {
                $finalFullMarks = [];
                $finalPassMarks = [];

                $subRes = $conn->prepare("
                    SELECT id, name FROM subjects
                    WHERE class_id = ?
                    ORDER BY name
                ");
                $subRes->bind_param("i", $class_id);
                $subRes->execute();
                $subResult = $subRes->get_result();
                while ($sub = $subResult->fetch_assoc()) {
                    $subjectsById[$sub['id']] = $sub['name'];
                }
                foreach ($subjectsById as $name) {
                    if (!in_array($name, $subjects)) $subjects[] = $name;
                }

                $allSubIdsStr = implode(',', array_keys($subjectsById));

                foreach ($terminalExams as $terminalExamId) {
                    $fullMarksRes = $conn->query("
                        SELECT subject_id, full_marks, pass_marks
                        FROM exam_subjects es
                        JOIN subjects s ON es.subject_id = s.id
                        WHERE es.exam_id = $terminalExamId
                        AND s.class_id = $class_id
                        AND subject_id IN ($allSubIdsStr)
                    ");
                    while ($row = $fullMarksRes->fetch_assoc()) {
                        $finalFullMarks[$row['subject_id']] = floatval($row['full_marks']);
                        $finalPassMarks[$row['subject_id']] = floatval($row['pass_marks']);
                    }
                }

                foreach ($terminalExams as $terminalExamId) {
                    $marksRes = $conn->query("
                        SELECT student_id, subject_id, marks
                        FROM marks
                        WHERE exam_id = $terminalExamId
                        AND student_id IN ($ids)
                        AND subject_id IN ($allSubIdsStr)
                    ");
                    while ($m = $marksRes->fetch_assoc()) {
                        $subjectName = $subjectsById[$m['subject_id']] ?? 'Unknown';

                        if (!isset($results[$m['student_id']]['marks'][$subjectName])) {
                            $results[$m['student_id']]['marks'][$subjectName] = 0;
                        }
                        $results[$m['student_id']]['marks'][$subjectName] += (float)$m['marks'];

                        if (!isset($results[$m['student_id']]['subject_full'][$subjectName])) {
                            $results[$m['student_id']]['subject_full'][$subjectName] = $finalFullMarks[$m['subject_id']] ?? 0;
                            $results[$m['student_id']]['subject_pass'][$subjectName] = $finalPassMarks[$m['subject_id']] ?? 0;
                        }

                        $results[$m['student_id']]['exam_count'][$subjectName] = ($results[$m['student_id']]['exam_count'][$subjectName] ?? 0) + 1;
                    }
                }

                foreach ($results as &$st) {
                    foreach ($st['marks'] as $subject => &$mark) {
                        $count = $st['exam_count'][$subject] ?? 1;
                        $mark = $count ? $mark / $count : $mark;
                    }
                    unset($mark);
                }
                unset($st);
            }
        } else {
            $subRes = $conn->prepare("
                SELECT id, name FROM subjects
                WHERE class_id = ?
                ORDER BY name
            ");
            $subRes->bind_param("i", $class_id);
            $subRes->execute();
            $subResult = $subRes->get_result();
            while ($sub = $subResult->fetch_assoc()) {
                $subjectsById[$sub['id']] = $sub['name'];
            }
            foreach ($subjectsById as $name) {
                if (!in_array($name, $subjects)) $subjects[] = $name;
            }

            $allSubIdsStr = implode(',', array_keys($subjectsById));

            $res = $conn->query("
                SELECT subject_id, full_marks, pass_marks 
                FROM exam_subjects 
                WHERE exam_id = $exam_id AND subject_id IN ($allSubIdsStr)
            ");
            while ($row = $res->fetch_assoc()) {
                $subName = $subjectsById[$row['subject_id']] ?? 'Unknown';
                $marksInfo[$subName] = [
                    'full_marks' => floatval($row['full_marks']),
                    'pass_marks' => floatval($row['pass_marks'])
                ];
            }

            $marksRes = $conn->query("
                SELECT student_id, subject_id, marks 
                FROM marks
                WHERE exam_id = $exam_id AND student_id IN ($ids) AND subject_id IN ($allSubIdsStr)
            ");
            while ($m = $marksRes->fetch_assoc()) {
                $subjectName = $subjectsById[$m['subject_id']] ?? 'Unknown';

                $results[$m['student_id']]['marks'][$subjectName] = (float)$m['marks'];

                if (!isset($results[$m['student_id']]['subject_full'][$subjectName])) {
                    $results[$m['student_id']]['subject_full'][$subjectName] = $marksInfo[$subjectName]['full_marks'] ?? 0;
                    $results[$m['student_id']]['subject_pass'][$subjectName] = $marksInfo[$subjectName]['pass_marks'] ?? 0;
                }
            }
        }

        foreach ($results as &$st) {
            $totalMarks = 0;
            $totalFull = 0;
            $pass = true;
            $totalGPA = 0;
            $subjectCount = 0;

            foreach ($st['marks'] as $sub => $mark) {
                $full = $st['subject_full'][$sub] ?? 0;
                $passMark = $st['subject_pass'][$sub] ?? 0;

                $totalMarks += $mark;
                $totalFull += $full;

                $percent = $full ? ($mark / $full) * 100 : 0;
                [$letter, $gpa] = getGradeAndGPA($percent);
                $totalGPA += $gpa;
                $subjectCount++;

                if ($mark < $passMark) $pass = false;
            }

            $st['total'] = round($totalMarks, 2);
            $st['percent'] = $totalFull ? round(($totalMarks / $totalFull) * 100, 2) : 0;
            $st['result'] = ($view_mode === 'grades') ? '-' : ($pass ? 'Pass' : 'Fail');
            [$letter, ] = getGradeAndGPA($st['percent']);
            $st['letter'] = $letter;
            $st['gpa'] = $subjectCount ? round($totalGPA / $subjectCount, 2) : 0;
        }
        unset($st);

        if ($view_mode === 'grades') {
            uasort($results, fn($a, $b) => $b['gpa'] <=> $a['gpa']);
        } else {
            uasort($results, fn($a, $b) => $b['percent'] <=> $a['percent']);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Class Teacher Results</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        /* Reset & base */
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #f0f4ff 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            /* padding: 40px 20px; */
            color: #344054;
        }
        .container {
            max-width: 1100px;
            width: 100%;
            background: #ffffff;
            border-radius: 16px;
            box-shadow:
                0 1px 3px rgba(0,0,0,0.12),
                0 4px 6px rgba(0,0,0,0.06);
            padding: 30px 40px 40px;
        }
        h2 {
            text-align: center;
            color: #334155;
            margin-bottom: 35px;
            font-weight: 700;
            font-size: 2.1rem;
        }

        /* Form styling */
        form.form-exam {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px 30px;
            margin-bottom: 35px;
        }
        label {
            font-weight: 600;
            font-size: 1rem;
            align-self: center;
            color: #475569;
            white-space: nowrap;
        }
        select {
            min-width: 180px;
            padding: 10px 15px;
            font-size: 1rem;
            border-radius: 10px;
            border: 1.8px solid #cbd5e1;
            background: #f9fafb;
            transition: all 0.25s ease;
            color: #334155;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
            cursor: pointer;
            appearance: none;
            background-image:
              linear-gradient(45deg, transparent 50%, #64748b 50%),
              linear-gradient(135deg, #64748b 50%, transparent 50%),
              linear-gradient(to right, #cbd5e1, #cbd5e1);
            background-position:
              calc(100% - 20px) calc(1em + 2px),
              calc(100% - 15px) calc(1em + 2px),
              calc(100% - 25px) 0.5em;
            background-size: 5px 5px, 5px 5px, 1px 1.5em;
            background-repeat: no-repeat;
        }
        select:hover, select:focus {
            border-color: #2563eb;
            background: #e0e7ff;
            outline: none;
            box-shadow: 0 0 6px #2563ebaa;
            color: #1e40af;
        }
        button.result-btn {
            background-color: #2563eb;
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            border-radius: 12px;
            padding: 12px 28px;
            box-shadow: 0 4px 10px #2563ebaa;
            transition: background-color 0.3s ease, box-shadow 0.3s ease, transform 0.15s ease;
            align-self: center;
            user-select: none;
        }
        button.result-btn:hover {
            background-color: #1e40af;
            box-shadow: 0 6px 16px #1e40afcc;
            transform: translateY(-2px);
        }
        button.result-btn:active {
            transform: translateY(0);
            box-shadow: 0 3px 7px #1e40afbb;
        }

        /* Table styling */
        .table-wrapper {
            overflow-x: auto;
            box-shadow:
                0 8px 24px rgba(0,0,0,0.12);
            border-radius: 14px;
            border: 1.5px solid #e2e8f0;
            background: #fff;
        }
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            min-width: 700px;
        }
        thead tr th {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #f9fafb;
            font-weight: 700;
            text-transform: uppercase;
            padding: 14px 16px;
            position: sticky;
            top: 0;
            z-index: 1;
            font-size: 0.9rem;
            user-select: none;
            border-right: 1px solid #60a5fa;
        }
        thead tr th:last-child {
            border-right: none;
        }
        tbody tr td {
            padding: 12px 14px;
            font-size: 0.95rem;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }
        tbody tr:nth-child(even) td {
            background: #f9fafb;
        }
        tbody tr:hover td {
            background: #eff6ff;
            color: #1e40af;
            font-weight: 600;
        }

        /* Fail/Pass colors */
        .fail-mark {
            background-color: #fee2e2 !important;
            color: #b91c1c !important;
            font-weight: 700;
            border-radius: 6px;
        }
        .pass {
            color: #15803d;
            font-weight: 700;
        }
        .fail {
            color: #b91c1c;
            font-weight: 700;
        }

        /* Responsive adjustments */
        @media (max-width: 900px) {
            form.form-exam {
                flex-direction: column;
                align-items: center;
                gap: 15px;
            }
            select, button.result-btn {
                width: 100%;
                max-width: 320px;
            }
        }
        @media (max-width: 500px) {
            table {
                min-width: 600px;
            }
            thead tr th, tbody tr td {
                padding: 10px 8px;
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Results for Grade <?= htmlspecialchars($grade) ?> Section <?= htmlspecialchars($section) ?></h2>
        <form method="GET" class="form-exam" style="justify-content:center;">
            <label for="exam_id">Exam:</label>
            <select name="exam_id" id="exam_id" required>
                <option value="">-- Select Exam --</option>
                <?php foreach ($exams as $exam): ?>
                    <option value="<?= $exam['id'] ?>" <?= isset($_GET['exam_id']) && $_GET['exam_id'] == $exam['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($exam['exam_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="view_mode">View Mode:</label>
            <select name="view_mode" id="view_mode" required>
                <option value="marks" <?= $view_mode === 'marks' ? 'selected' : '' ?>>Marks</option>
                <option value="grades" <?= $view_mode === 'grades' ? 'selected' : '' ?>>Grades</option>
                <option value="consolidated" <?= $view_mode === 'consolidated' ? 'selected' : '' ?>>Consolidated</option>
            </select>

            <button class="result-btn" type="submit" aria-label="Show Results">Show Results</button>
        </form>

        <div class="table-wrapper" role="region" aria-live="polite" aria-relevant="additions">
            <?php if (!empty($results) && !empty($subjects)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Name</th>
                            <th>Section</th>
                            <?php foreach ($subjects as $subject): ?>
                                <th><?= htmlspecialchars($subject) ?></th>
                            <?php endforeach; ?>
                            <th>Total</th>
                            <th>Percent</th>
                            <th>Grade</th>
                            <th>GPA</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $i = 1;
                        foreach ($results as $student_id => $st):
                            ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($st['full_name']) ?></td>
                                <td><?= htmlspecialchars($st['section']) ?></td>
                                <?php foreach ($subjects as $subject):
                                    $mark = $st['marks'][$subject] ?? 0;
                                    $full = $st['subject_full'][$subject] ?? 0;
                                    $pass = $st['subject_pass'][$subject] ?? 0;
                                    $fail_class = ($mark < $pass) ? 'fail-mark' : '';
                                    ?>
                                    <td class="<?= $fail_class ?>">
                                        <?php
                                        if ($view_mode === 'grades') {
                                            $percent = $full ? ($mark / $full) * 100 : 0;
                                            [$letter,] = getGradeAndGPA($percent);
                                            echo $letter;
                                        } elseif ($view_mode === 'consolidated') {
                                            echo sprintf("%.2f/%d", $mark, $full);
                                        } else {
                                            echo sprintf("%.2f", $mark);
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                <td><?= $st['total'] ?></td>
                                <td><?= $st['percent'] ?>%</td>
                                <td><?= $st['letter'] ?></td>
                                <td><?= $st['gpa'] ?></td>
                                <td class="<?= $st['result'] === 'Pass' ? 'pass' : 'fail' ?>"><?= $st['result'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (isset($_GET['exam_id'])): ?>
                <p style="text-align:center; font-weight:600; color:#64748b; margin-top: 30px;">
                    No results found for the selected exam.
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

