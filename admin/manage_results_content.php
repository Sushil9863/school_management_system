<?php
include '../partials/dbconnect.php';

$school_id = $_SESSION['school_id'] ?? 1;  // fallback

// Fetch unique exams for dropdown
$exams = [];
$examQuery = $conn->query("
    SELECT DISTINCT exam_name, id 
    FROM exams 
    WHERE school_id = $school_id
    ORDER BY exam_name
");
while ($row = $examQuery->fetch_assoc()) {
    $exams[] = $row;
}

// Add "Final" option to exams array
array_unshift($exams, ['id' => 'final', 'exam_name' => 'Final Result']);

// Fetch classes for dropdown
$classes = [];
$classQuery = $conn->query("
    SELECT id AS class_id, grade, section
    FROM classes
    WHERE school_id = $school_id
    ORDER BY grade, section
");
while ($row = $classQuery->fetch_assoc()) {
    $classes[] = $row;
}

// Group classes by grade for the dropdown
$groupedClasses = [];
foreach ($classes as $class) {
    $grade = $class['grade'];
    if (!isset($groupedClasses[$grade])) {
        $groupedClasses[$grade] = [
            'class_id' => $class['class_id'],
            'grade' => $grade,
            'sections' => []
        ];
    }
    $groupedClasses[$grade]['sections'][] = $class['section'];
}

// Grading function
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
$marksInfo = [];

if (isset($_GET['exam_id']) && isset($_GET['class_id']) && isset($_GET['section'])) {
    $exam_id = $_GET['exam_id'];
    $class_id = intval($_GET['class_id']);
    $section = $_GET['section'];

    // Find grade for this class
    $gradeRow = $conn->query("SELECT grade FROM classes WHERE id = $class_id AND school_id = $school_id")->fetch_assoc();
    $grade = $gradeRow ? $gradeRow['grade'] : null;

    if ($grade) {
        // Get all class IDs for this grade (regardless of section)
        $classIds = [];
        $res = $conn->query("
            SELECT id FROM classes 
            WHERE grade = '" . $conn->real_escape_string($grade) . "' 
            AND school_id = $school_id
        ");
        while ($c = $res->fetch_assoc()) $classIds[] = $c['id'];

        if (!empty($classIds)) {
            $classIdList = implode(',', $classIds);

            // Subjects grouped by name
            $subjectMap = [];
            $subRes = $conn->query("
                SELECT id, name FROM subjects
                WHERE class_id IN ($classIdList)
                ORDER BY name
            ");
            while ($sub = $subRes->fetch_assoc()) {
                $name = $sub['name'];
                if (!isset($subjectMap[$name])) $subjectMap[$name] = [];
                $subjectMap[$name][] = $sub['id'];
            }
            foreach ($subjectMap as $name => $ids) {
                $subjects[] = ['name' => $name, 'ids' => $ids];
            }

            // Students - apply section filter here
            $sectionFilter = ($section !== 'all') ? "AND c.section = '" . $conn->real_escape_string($section) . "'" : "";
            $studentsRes = $conn->query("
                SELECT st.id, st.full_name, c.grade, c.section
                FROM students st
                JOIN classes c ON st.class_id = c.id
                WHERE c.id IN ($classIdList) AND c.school_id = $school_id $sectionFilter
                ORDER BY st.full_name
            ");
            $studentIds = [];
            while ($st = $studentsRes->fetch_assoc()) {
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
                    'exam_count' => 0
                ];
            }

            if (!empty($studentIds) && !empty($subjects)) {
                $ids = implode(',', $studentIds);
                $allSubIds = [];
                foreach ($subjects as $s) $allSubIds = array_merge($allSubIds, $s['ids']);
                $allSubIds = array_unique($allSubIds);
                $allSubIdsStr = implode(',', $allSubIds);

                if ($exam_id === 'final') {
                    // FINAL RESULT - Aggregate all terminal exams
                    
                    // Get all terminal exams for this school
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
                        // Process each terminal exam
                        foreach ($terminalExams as $terminalExamId) {
                            // Get full marks for each subject in this exam
                            $fullMarksRes = $conn->query("
                                SELECT subject_id, full_marks, pass_marks 
                                FROM exam_subjects 
                                WHERE exam_id = $terminalExamId AND subject_id IN ($allSubIdsStr)
                            ");
                            $examMarksInfo = [];
                            while ($row = $fullMarksRes->fetch_assoc()) {
                                $examMarksInfo[$row['subject_id']] = [
                                    'full_marks' => floatval($row['full_marks']),
                                    'pass_marks' => floatval($row['pass_marks'])
                                ];
                            }

                            // Get marks for this exam
                            $marksRes = $conn->query("
                                SELECT student_id, subject_id, marks 
                                FROM marks
                                WHERE exam_id = $terminalExamId AND student_id IN ($ids) AND subject_id IN ($allSubIdsStr)
                            ");
                            while ($m = $marksRes->fetch_assoc()) {
                                foreach ($subjects as $subj) {
                                    if (in_array($m['subject_id'], $subj['ids'])) {
                                        if (!isset($results[$m['student_id']]['marks'][$subj['name']])) {
                                            $results[$m['student_id']]['marks'][$subj['name']] = 0;
                                        }
                                        // Add marks (we'll average later)
                                        $results[$m['student_id']]['marks'][$subj['name']] += (float)$m['marks'];
                                        
                                        // Store full and pass marks for each subject (use first exam's values)
                                        if (!isset($results[$m['student_id']]['subject_full'][$subj['name']])) {
                                            $results[$m['student_id']]['subject_full'][$subj['name']] = $examMarksInfo[$m['subject_id']]['full_marks'] ?? 0;
                                            $results[$m['student_id']]['subject_pass'][$subj['name']] = $examMarksInfo[$m['subject_id']]['pass_marks'] ?? 0;
                                        }
                                    }
                                }
                                // Increment exam count for each student who has marks
                                if (isset($m['student_id'])) {
                                    $results[$m['student_id']]['exam_count']++;
                                }
                            }
                        }
                        
                        // Calculate averages for final result
                        foreach ($results as &$st) {
                            $st['exam_count'] = $st['exam_count'] ?? 0;
                            if ($st['exam_count'] > 0) {
                                foreach ($st['marks'] as $subject => &$mark) {
                                    $mark = $mark / $st['exam_count']; // Average marks
                                }
                                unset($mark);
                            }
                        }
                        unset($st);
                    }
                } else {
                    // SINGLE EXAM RESULT
                    
                    // Full marks & pass marks for each subject in this exam
                    $res = $conn->query("
                        SELECT subject_id, full_marks, pass_marks 
                        FROM exam_subjects 
                        WHERE exam_id = $exam_id AND subject_id IN ($allSubIdsStr)
                    ");
                    while ($row = $res->fetch_assoc()) {
                        $marksInfo[$row['subject_id']] = [
                            'full_marks' => floatval($row['full_marks']),
                            'pass_marks' => floatval($row['pass_marks'])
                        ];
                    }

                    // Marks for this exam
                    $marksRes = $conn->query("
                        SELECT student_id, subject_id, marks 
                        FROM marks
                        WHERE exam_id = $exam_id AND student_id IN ($ids) AND subject_id IN ($allSubIdsStr)
                    ");
                    while ($m = $marksRes->fetch_assoc()) {
                        foreach ($subjects as $subj) {
                            if (in_array($m['subject_id'], $subj['ids'])) {
                                if (!isset($results[$m['student_id']]['marks'][$subj['name']])) {
                                    $results[$m['student_id']]['marks'][$subj['name']] = 0;
                                }
                                $results[$m['student_id']]['marks'][$subj['name']] += (float)$m['marks'];
                                
                                // Store full and pass marks for each subject
                                if (!isset($results[$m['student_id']]['subject_full'][$subj['name']])) {
                                    $results[$m['student_id']]['subject_full'][$subj['name']] = 0;
                                    $results[$m['student_id']]['subject_pass'][$subj['name']] = 0;
                                }
                                
                                // Add to the full and pass marks for this subject
                                $results[$m['student_id']]['subject_full'][$subj['name']] += $marksInfo[$m['subject_id']]['full_marks'] ?? 0;
                                $results[$m['student_id']]['subject_pass'][$subj['name']] += $marksInfo[$m['subject_id']]['pass_marks'] ?? 0;
                            }
                        }
                    }
                }

                // Final totals per student
                foreach ($results as &$st) {
                    $totalMarks = 0;
                    $totalFullMarks = 0;
                    $pass = true;

                    foreach ($subjects as $subj) {
                        $mark = $st['marks'][$subj['name']] ?? 0;
                        $subjFullMarks = $st['subject_full'][$subj['name']] ?? 0;
                        $subjPassMarks = $st['subject_pass'][$subj['name']] ?? 0;

                        $totalMarks += $mark;
                        $totalFullMarks += $subjFullMarks;

                        if ($mark < $subjPassMarks) $pass = false;
                    }

                    $st['total'] = $totalMarks;
                    $st['percent'] = $totalFullMarks ? round(($totalMarks / $totalFullMarks) * 100, 2) : 0;
                    $st['result'] = ($view_mode === 'grades') ? '-' : ($pass ? 'Pass' : 'Fail');
                    [$letter, $gpa] = getGradeAndGPA($st['percent']);
                    $st['letter'] = $letter;
                    $st['gpa'] = $gpa;
                }
                unset($st);

                // Sort results based on view mode
                if ($view_mode === 'grades') {
                    // Sort by GPA descending
                    uasort($results, function($a, $b) {
                        return $b['gpa'] <=> $a['gpa'];
                    });
                } else {
                    // Sort by percentage descending (for both marks and consolidated views)
                    uasort($results, function($a, $b) {
                        return $b['percent'] <=> $a['percent'];
                    });
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Results</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f6f9; margin: 0; padding: 0;}
        .container {max-width: 95%; margin: 30px auto; background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);}
        h2 {text-align: center; color: #2c3e50;}
        form {display: flex; justify-content: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;}
        label {font-weight: 600; align-self: center;}
        select, button {padding: 8px 15px; font-size: 15px; border-radius: 6px; border: 1px solid #ccc;}
        button {background: #3498db; color: #fff; font-weight: bold; cursor: pointer;}
        button:hover {background: #2980b9;}
        .table-wrapper {overflow-x: auto;}
        table {width: 100%; min-width: 900px; border-collapse: collapse;}
        th, td {padding: 10px; text-align: center; border-bottom: 1px solid #eaeaea;}
        th {background: #3498db; color: #fff;}
        td.pass {color: #27ae60; font-weight: bold;}
        td.fail {color: #e74c3c; font-weight: bold;}
        .fail-mark {color: #e74c3c; font-weight: bold;}
        .hidden {display: none;}
        .final-result {background-color: #f8f9fa; font-weight: bold;}
    </style>
</head>
<body>
<div class="container">
    <h2>Student Results</h2>
    <form method="GET">
        <label>Exam:</label>
        <select name="exam_id" id="exam_id" required>
            <option value="">-- Select Exam --</option>
            <?php foreach ($exams as $exam): ?>
                <option value="<?= $exam['id'] ?>" <?= isset($_GET['exam_id']) && $_GET['exam_id'] == $exam['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($exam['exam_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Class:</label>
        <select name="class_id" id="class_id" required>
            <option value="">-- Select Class --</option>
            <?php foreach ($groupedClasses as $grade => $class): ?>
                <option value="<?= $class['class_id'] ?>" data-sections="<?= htmlspecialchars(implode(',', $class['sections'])) ?>"
                    <?= isset($_GET['class_id']) && $_GET['class_id'] == $class['class_id'] ? 'selected' : '' ?>>
                    Grade <?= htmlspecialchars($grade) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label>Section:</label>
        <select name="section" id="section_id">
            <option value="all">All Sections</option>
            <?php 
            if (isset($_GET['class_id'])) {
                $selectedClassId = $_GET['class_id'];
                $selectedClass = null;
                foreach ($classes as $class) {
                    if ($class['class_id'] == $selectedClassId) {
                        $selectedClass = $class;
                        break;
                    }
                }
                if ($selectedClass) {
                    $sections = $conn->query("
                        SELECT DISTINCT section 
                        FROM classes 
                        WHERE grade = '".$conn->real_escape_string($selectedClass['grade'])."' 
                        AND school_id = $school_id
                        ORDER BY section
                    ");
                    while ($sec = $sections->fetch_assoc()) {
                        $selected = ($_GET['section'] ?? '') == $sec['section'] ? 'selected' : '';
                        echo "<option value='".htmlspecialchars($sec['section'])."' $selected>".htmlspecialchars($sec['section'])."</option>";
                    }
                }
            }
            ?>
        </select>
        <label>View Mode:</label>
        <select name="view_mode" id="view_mode" required>
            <option value="marks" <?= $view_mode === 'marks' ? 'selected' : '' ?>>Marks</option>
            <option value="grades" <?= $view_mode === 'grades' ? 'selected' : '' ?>>Grades</option>
            <option value="consolidated" <?= $view_mode === 'consolidated' ? 'selected' : '' ?>>Consolidated</option>
        </select>
        <button type="submit">Show Results</button>
    </form>
    <script>
        $(function () {
            function populateSections() {
                const sel = $('#class_id option:selected');
                const sections = sel.data('sections') || '';
                const chosen = '<?= $_GET['section'] ?? '' ?>';
                let opts = '<option value="all">All Sections</option>';
                if (sections) {
                    sections.split(',').forEach(sec => {
                        sec = sec.trim();
                        opts += `<option value="${sec}" ${chosen === sec ? 'selected' : ''}>${sec}</option>`;
                    });
                }
                $('#section_id').html(opts);
            }
            populateSections();
            $('#class_id').on('change', populateSections);
        });
    </script>
    <?php if (!empty($results)): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Section</th>
                        <?php foreach ($subjects as $sub): ?>
                            <th><?= htmlspecialchars($sub['name']) ?></th>
                        <?php endforeach; ?>
                        <th class="<?= $view_mode === 'grades' ? 'hidden' : '' ?>">Total</th>
                        <th class="<?= $view_mode === 'grades' ? 'hidden' : '' ?>">Percent</th>
                        <th>Grade</th>
                        <th>GPA</th>
                        <?php if ($view_mode !== 'grades'): ?><th>Result</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $sn=1; foreach ($results as $st): ?>
                        <tr class="<?= ($_GET['exam_id'] ?? '') === 'final' ? 'final-result' : '' ?>">
                            <td><?= $sn++ ?></td>
                            <td><?= htmlspecialchars($st['full_name']) ?></td>
                            <td><?= htmlspecialchars($st['section']) ?></td>
                            <?php foreach ($subjects as $sub):
                                $mark = $st['marks'][$sub['name']] ?? 0;
                                $full = $st['subject_full'][$sub['name']] ?? 0;
                                $pass = $st['subject_pass'][$sub['name']] ?? 0;
                                $percent = $full ? ($mark/$full)*100 : 0;
                                [$letter,$gpa]=getGradeAndGPA($percent);
                                $failClass=($mark < $pass && $view_mode !== 'grades')?'fail-mark':'';
                                ?>
                                <td class="<?= $failClass ?>">
                                    <?php if ($view_mode==='marks'): ?>
                                        <?= round($mark, 2) ?>/<?= $full ?>
                                    <?php elseif ($view_mode==='grades'): ?>
                                        <?= $letter ?> (<?= $gpa ?>)
                                    <?php else: ?>
                                        <?= round($mark, 2) ?>/<?= $full ?> (<?= $letter ?>, <?= $gpa ?>)
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="<?= $view_mode === 'grades' ? 'hidden' : '' ?>"><?= round($st['total'], 2) ?></td>
                            <td class="<?= $view_mode === 'grades' ? 'hidden' : '' ?>"><?= $st['percent'] ?>%</td>
                            <td><?= $st['letter'] ?></td>
                            <td><?= $st['gpa'] ?></td>
                            <?php if ($view_mode!=='grades'): ?>
                                <td class="<?= strtolower($st['result']) ?>"><?= $st['result'] ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php elseif (isset($_GET['exam_id']) && isset($_GET['class_id'])): ?>
        <p>No students found for this selection.</p>
    <?php endif; ?>
</div>
</body>
</html>