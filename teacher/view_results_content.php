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
        $ids = implode(',', array_map('intval', $studentIds));
    } else {
        $ids = '0'; // safe fallback
    }

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

            // subjects for class
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

            $allSubIds = array_keys($subjectsById);
            $allSubIdsStr = !empty($allSubIds) ? implode(',', array_map('intval', $allSubIds)) : '0';

            // collect final full/pass marks per subject across terminal exams (last terminal overrides earlier if present)
            $finalFullMarks = [];
            $finalPassMarks = [];
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

            // collect marks from each terminal and sum them
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

            // average marks per subject (if multiple terminal exams)
            foreach ($results as &$st) {
                // ensure every subject key exists (so totals include subjects even if student has no mark)
                foreach ($subjects as $subName) {
                    if (!isset($st['marks'][$subName])) {
                        $st['marks'][$subName] = 0;
                    }
                    if (!isset($st['subject_full'][$subName])) {
                        // try to find subject id to get final full marks if exists
                        $st['subject_full'][$subName] = 0;
                        $st['subject_pass'][$subName] = 0;
                    }
                }

                foreach ($st['marks'] as $subject => &$mark) {
                    $count = $st['exam_count'][$subject] ?? 1;
                    $mark = $count ? $mark / $count : $mark;
                }
                unset($mark);
            }
            unset($st);
        }
    } else {
        // specific exam selected
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

        $allSubIds = array_keys($subjectsById);
        $allSubIdsStr = !empty($allSubIds) ? implode(',', array_map('intval', $allSubIds)) : '0';

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

        // ensure all subjects exist in student structures (so display order consistent)
        foreach ($results as &$st) {
            foreach ($subjects as $subName) {
                if (!isset($st['marks'][$subName])) $st['marks'][$subName] = 0;
                if (!isset($st['subject_full'][$subName])) $st['subject_full'][$subName] = $marksInfo[$subName]['full_marks'] ?? 0;
                if (!isset($st['subject_pass'][$subName])) $st['subject_pass'][$subName] = $marksInfo[$subName]['pass_marks'] ?? 0;
            }
        }
        unset($st);
    }

    // ===== New totals/fail/letter/gpa logic (implements your rules) =====
    foreach ($results as &$st) {
        $totalMarks = 0;
        $totalFull = 0;
        $pass = true;
        $totalGPA = 0;
        $subjectCount = 0;

        // iterate over canonical subject list so missing marks are treated consistently
        foreach ($subjects as $sub) {
            $mark = $st['marks'][$sub] ?? 0;
            $full = $st['subject_full'][$sub] ?? 0;
            $passMark = $st['subject_pass'][$sub] ?? 0;

            $totalMarks += $mark;
            $totalFull += $full;

            $percentSub = $full ? ($mark / $full) * 100 : 0;
            [$letterSub, $gpaSub] = getGradeAndGPA($percentSub);
            $totalGPA += $gpaSub;
            $subjectCount++;

            // only consider fail if pass mark is defined (non-zero) OR full>0
            if ($full > 0 && $mark < $passMark) {
                $pass = false;
            }
        }

        $st['total'] = round($totalMarks, 2);
        $percentValue = $totalFull ? round(($totalMarks / $totalFull) * 100, 2) : 0;
        $st['gpa'] = $subjectCount ? round($totalGPA / $subjectCount, 2) : 0;

        if (!$pass) {
            // rule 4: failed in at least one subject -> NG, no percentage, result = Fail
            $st['letter'] = 'NG';
            $st['percent'] = '-';
            $st['result'] = 'Fail';
        } else {
            [$letterTotal, ] = getGradeAndGPA($percentValue);
            $st['letter'] = $letterTotal;
            $st['percent'] = $percentValue;
            $st['result'] = 'Pass';
        }
    }
    unset($st);

    // ===== Sorting: marks view -> percent; grades/consolidated -> letter then percent =====
    if ($view_mode === 'marks') {
        uasort($results, function($a, $b) {
            $pa = ($a['percent'] === '-') ? -1 : (float)$a['percent'];
            $pb = ($b['percent'] === '-') ? -1 : (float)$b['percent'];
            // highest percent first
            return $pb <=> $pa;
        });
    } else { // grades & consolidated: sort by grade order then percent
        $gradeOrder = ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'NG'];
        uasort($results, function($a, $b) use ($gradeOrder) {
            $aPos = array_search($a['letter'], $gradeOrder);
            $bPos = array_search($b['letter'], $gradeOrder);
            if ($aPos === false) $aPos = count($gradeOrder); // unknown -> last
            if ($bPos === false) $bPos = count($gradeOrder);
            if ($aPos !== $bPos) {
                return $aPos <=> $bPos; // smaller index = better grade (A+ first)
            }
            // same grade -> compare percent (higher first). Treat '-' as -1 so fails are last.
            $pa = ($a['percent'] === '-') ? -1 : (float)$a['percent'];
            $pb = ($b['percent'] === '-') ? -1 : (float)$b['percent'];
            return $pb <=> $pa;
        });
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
        /* Basic page styling */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            margin: 0;
            /* padding: 20px; */
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 20px 30px 40px;
            border-radius: 8px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #007BFF;
            margin-bottom: 20px;
            font-weight: 700;
            text-align: center;
        }

        form.form-exam {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            justify-content: center;
        }

        form label {
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
            color: #444;
        }

        form select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1.8px solid #ccc;
            min-width: 160px;
            font-size: 15px;
            cursor: pointer;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            background: white;
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="gray" height="12" viewBox="0 0 24 24" width="12" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
        }

        form select:focus {
            outline: none;
            border-color: #007BFF;
            box-shadow: 0 0 6px #007BFFaa;
            background-color: #fff;
        }

        .result-btn {
            background: #007BFF;
            border: none;
            padding: 10px 18px;
            color: white;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.25s ease;
            min-width: 140px;
            box-shadow: 0 2px 8px #007BFFaa;
            user-select: none;
        }

        .result-btn:hover {
            background: #0056b3;
            box-shadow: 0 4px 12px #0056b3bb;
        }

        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 14px;
        }

        thead tr {
            background-color: #007BFF;
            color: #fff;
            font-weight: 700;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
            vertical-align: middle;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #e6f2ff;
        }

        .fail-mark {
            background-color: #fddede;
            color: #a40000;
            font-weight: 600;
            text-align: center;
        }

        .pass {
            color: #007B00;
        }

        .fail {
            color: #b00000;
        }

        /* Responsive tweaks */
        @media (max-width: 900px) {
            form.form-exam {
                flex-direction: column;
                align-items: center;
            }

            form select, .result-btn {
                min-width: 250px;
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

                            <?php if ($view_mode === 'marks' || $view_mode === 'consolidated'): ?>
                                <th>Total</th>
                                <th>Percent</th>
                            <?php endif; ?>

                            <?php if ($view_mode === 'grades' || $view_mode === 'consolidated'): ?>
                                <th>Grade</th>
                                <th>GPA</th>
                            <?php endif; ?>

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
                                    $fail_class = ($full > 0 && $mark < $pass) ? 'fail-mark' : '';
                                    $percent_sub = $full ? ($mark / $full) * 100 : 0;
                                    [$letter_sub, $gpa_sub] = getGradeAndGPA($percent_sub);

                                    // Format mark as int if no decimals, else two decimals
                                    $displayMark = (floor($mark) == $mark) ? intval($mark) : number_format($mark, 2);
                                    ?>
                                    <td class="<?= $fail_class ?>">
                                        <?php
                                        if ($view_mode === 'grades') {
                                            // show only letter per subject
                                            echo $letter_sub;
                                        } elseif ($view_mode === 'consolidated') {
                                            // show mark with letter grade, e.g. 45(A+)
                                            // only show GPA if overall student is NOT NG and gpa_sub > 0 (optional)
                                            if ($st['letter'] === 'NG') {
                                                echo $displayMark . '(' . $letter_sub . ')'; // no GPA if fail overall
                                            } else {
                                                echo $displayMark . '(' . $letter_sub . ')';
                                            }
                                        } else {
                                            // marks view: show raw mark
                                            echo $displayMark;
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>

                                <?php if ($view_mode === 'marks' || $view_mode === 'consolidated'): ?>
                                    <td><?= $st['total'] ?></td>
                                    <td><?= $st['percent'] !== '-' ? $st['percent'] . '%' : '-' ?></td>
                                <?php endif; ?>

                                <?php if ($view_mode === 'grades' || $view_mode === 'consolidated'): ?>
                                    <td><?= $st['letter'] ?></td>
                                    <td><?= ($st['letter'] === 'NG') ? '-' : $st['gpa'] ?></td>
                                <?php endif; ?>

                                <td class="<?= strtolower($st['result']) ?>"><?= $st['result'] ?></td>
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