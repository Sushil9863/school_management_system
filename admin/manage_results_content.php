<?php
include '../partials/dbconnect.php';
require '../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

$school_id = $_SESSION['school_id'] ?? 1;

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
// Add virtual Final Exam entry
array_unshift($exams, ['id' => '0', 'exam_name' => 'Final Exam', 'exam_type' => 'Final']);

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

// Group classes by grade
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

// Grade/GPA function
function getGradeAndGPA($percent, $isPass = true) {
    if (!$isPass || $percent < 35) return ['NG', 0.0];
    if ($percent >= 90) return ['A+', 4.0];
    if ($percent >= 80) return ['A', 3.6];
    if ($percent >= 70) return ['B+', 3.2];
    if ($percent >= 60) return ['B', 2.8];
    if ($percent >= 50) return ['C+', 2.4];
    if ($percent >= 40) return ['C', 2.0];
    return ['D', 1.6];
}

// Table rendering function - used both in page and export
function renderResultsTable($results, $subjects, $view_mode) {
    echo "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse: collapse; width: 100%;'>";
    echo "<thead style='background: #007BFF; color: white;'>";
    echo "<tr>";
    echo "<th style='text-align:center;'>No.</th><th>Name</th><th>Section</th>";
    foreach ($subjects as $subject) {
        echo "<th>" . htmlspecialchars($subject) . "</th>";
    }
    
    // Show columns based on view mode
    if ($view_mode !== 'grades') {
        echo "<th>Total</th><th>Percent</th>";
    }
    if ($view_mode !== 'marks') {
        echo "<th>Grade</th><th>GPA</th>";
    }
    if ($view_mode === 'marks') {
        echo "<th>Result</th>";
    }
    
    echo "</tr></thead><tbody>";

    $i = 1;
    foreach ($results as $student_id => $st) {
        $resultClass = strtolower($st['result']) === 'pass' ? 'pass' : 'fail';
        echo "<tr style='background: " . ($i % 2 === 0 ? '#f9f9f9' : 'white') . "'>";
        echo "<td style='text-align:center;'>" . $i++ . "</td>";
        echo "<td>" . htmlspecialchars($st['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($st['section']) . "</td>";
        
        foreach ($subjects as $subject) {
            $mark = $st['marks'][$subject] ?? 0;
            $full = $st['subject_full'][$subject] ?? 0;
            $passMark = $st['subject_pass'][$subject] ?? 0;
            $fail_mark_class = ($mark < $passMark) ? "fail-mark" : "";
            echo "<td class='{$fail_mark_class}' style='text-align:center;'>";
            
            if ($view_mode === 'grades') {
    $percent = $full ? ($mark / $full) * 100 : 0;
    $subjectPassed = ($mark >= $passMark);
    [$letter,] = getGradeAndGPA($percent, $subjectPassed);
    echo $letter;
} elseif ($view_mode === 'consolidated') {
    $percent = $full ? ($mark / $full) * 100 : 0;
    $subjectPassed = ($mark >= $passMark);
    [$letter,] = getGradeAndGPA($percent, $subjectPassed);
    echo sprintf("%.2f (%s)", $mark, $letter);
} else {
                echo sprintf("%.2f", $mark);
            }
            echo "</td>";
        }
        
        // Show columns based on view mode
        if ($view_mode !== 'grades') {
            echo "<td style='text-align:center;'>" . $st['total'] . "</td>";
            echo "<td style='text-align:center;'>" . $st['percent'] . "%</td>";
        }
        if ($view_mode !== 'marks') {
            echo "<td style='text-align:center;'>" . $st['letter'] . "</td>";
            echo "<td style='text-align:center;'>" . $st['gpa'] . "</td>";
        }
        if ($view_mode === 'marks') {
            echo "<td class='{$resultClass}' style='text-align:center;font-weight:bold;'>" . $st['result'] . "</td>";
        }
        
        echo "</tr>";
    }
    echo "</tbody></table>";
}

$view_mode = $_GET['view_mode'] ?? 'marks';
$results = [];
$subjects = [];       // Final array of unique subject names (for table header)
$subjectsById = [];   // ID → Name mapping (for marks processing)
$marksInfo = [];

if (isset($_GET['exam_id']) && isset($_GET['class_id']) && isset($_GET['section'])) {
    $exam_id = $_GET['exam_id'];
    $class_id = intval($_GET['class_id']);
    $section = $_GET['section'];

    $gradeRow = $conn->query("SELECT grade FROM classes WHERE id = $class_id AND school_id = $school_id")->fetch_assoc();
    $grade = $gradeRow ? $gradeRow['grade'] : null;

    if ($grade) {
        $classIds = [];
        $res = $conn->query("
            SELECT id FROM classes 
            WHERE grade = '" . $conn->real_escape_string($grade) . "' 
            AND school_id = $school_id
        ");
        while ($c = $res->fetch_assoc()) $classIds[] = $c['id'];

        if (!empty($classIds)) {
            $classIdList = implode(',', $classIds);

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
                    'exam_count' => []
                ];
            }

            if (!empty($studentIds)) {
                $ids = implode(',', $studentIds);

                // ---------- FINAL EXAM (Average of Terminal Exams) ----------
                if ($exam_id === '0') {
                    $terminalExams = [];
                    $examRes = $conn->query("
                        SELECT id FROM exams
                        WHERE school_id = $school_id
                        AND exam_type = 'Terminal'
                    ");
                    while ($exam = $examRes->fetch_assoc()) $terminalExams[] = $exam['id'];

                    if (!empty($terminalExams)) {
                        $finalFullMarks = [];
                        $finalPassMarks = [];

                        // Fetch subjects for all classes in grade
                        $subRes = $conn->query("
                            SELECT id, name FROM subjects
                            WHERE class_id IN ($classIdList)
                            ORDER BY name
                        ");
                        while ($sub = $subRes->fetch_assoc()) {
                            $subjectsById[$sub['id']] = $sub['name'];
                        }
                        // Create unique subject list
                        foreach ($subjectsById as $name) {
                            if (!in_array($name, $subjects)) $subjects[] = $name;
                        }

                        $allSubIdsStr = implode(',', array_keys($subjectsById));

                        // Fetch full/pass marks from all terminal exams
                        foreach ($terminalExams as $terminalExamId) {
                            $fullMarksRes = $conn->query("
                                SELECT subject_id, full_marks, pass_marks
                                FROM exam_subjects es
                                JOIN subjects s ON es.subject_id = s.id
                                WHERE es.exam_id = $terminalExamId
                                AND s.class_id IN ($classIdList)
                                AND subject_id IN ($allSubIdsStr)
                            ");
                            while ($row = $fullMarksRes->fetch_assoc()) {
                                $finalFullMarks[$row['subject_id']] = floatval($row['full_marks']);
                                $finalPassMarks[$row['subject_id']] = floatval($row['pass_marks']);
                            }
                        }

                        // Fetch marks from all terminal exams
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

                        // Average marks per subject
                        foreach ($results as &$st) {
                            foreach ($st['marks'] as $subject => &$mark) {
                                $count = $st['exam_count'][$subject] ?? 1;
                                $mark = $count ? $mark / $count : $mark;
                            }
                            unset($mark);
                        }
                        unset($st);
                    }
                }
                // ---------- SPECIFIC EXAM ----------
                else {
                    $subRes = $conn->query("
                        SELECT id, name FROM subjects
                        WHERE class_id IN ($classIdList)
                        ORDER BY name
                    ");
                    while ($sub = $subRes->fetch_assoc()) {
                        $subjectsById[$sub['id']] = $sub['name'];
                    }
                    foreach ($subjectsById as $name) {
                        if (!in_array($name, $subjects)) $subjects[] = $name;
                    }

                    $allSubIdsStr = implode(',', array_keys($subjectsById));

                    // Full & pass marks
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

                    // Marks for this exam
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

                // ---------- CALCULATE TOTALS ----------
foreach ($results as &$st) {
    $totalMarks = 0;
    $totalFull = 0;
    $pass = true;
    $totalGPA = 0;
    $subjectCount = 0;
    $hasNG = false;

    foreach ($st['marks'] as $sub => $mark) {
        $full = $st['subject_full'][$sub] ?? 0;
        $passMark = $st['subject_pass'][$sub] ?? 0;

        $totalMarks += $mark;
        $totalFull += $full;

        $subjectPassed = ($mark >= $passMark);
        if (!$subjectPassed) {
            $pass = false;
            $hasNG = true;
        }

        $percent = $full ? ($mark / $full) * 100 : 0;
        [$letter, $gpa] = getGradeAndGPA($percent, $subjectPassed);
        $totalGPA += $gpa;
        $subjectCount++;
    }

    $st['total'] = round($totalMarks, 2);
    $st['percent'] = $totalFull ? round(($totalMarks / $totalFull) * 100, 2) : 0;
    $st['result'] = ($view_mode === 'grades') ? '-' : ($pass ? 'Pass' : 'Fail');
    
    // Handle NG condition for grade and consolidated views
    if (($view_mode === 'grades' || $view_mode === 'consolidated') && $hasNG) {
        $st['letter'] = 'NG';
        $st['gpa'] = 0.0;
    } else {
        [$letter, ] = getGradeAndGPA($st['percent'], $pass);
        $st['letter'] = $letter;
        $st['gpa'] = $subjectCount ? round($totalGPA / $subjectCount, 2) : 0;
    }
}
unset($st);

                // ---------- SORT RESULTS ----------
                // Separate passed and failed students
                $passedStudents = [];
                $failedStudents = [];
                
                foreach ($results as $student_id => $student) {
                    if (strtolower($student['result']) === 'pass') {
                        $passedStudents[$student_id] = $student;
                    } else {
                        $failedStudents[$student_id] = $student;
                    }
                }

                // Sort passed students
                if ($view_mode === 'grades') {
                    uasort($passedStudents, function($a, $b) {
                        // First by GPA (descending)
                        $gpaCompare = $b['gpa'] <=> $a['gpa'];
                        if ($gpaCompare !== 0) return $gpaCompare;
                        
                        // If GPA is same, then by percentage (descending)
                        return $b['percent'] <=> $a['percent'];
                    });
                } 
                elseif ($view_mode === 'consolidated') {
                    uasort($passedStudents, function($a, $b) {
                        // First by GPA (descending)
                        $gpaCompare = $b['gpa'] <=> $a['gpa'];
                        if ($gpaCompare !== 0) return $gpaCompare;
                        
                        // If GPA is same, then by percentage (descending)
                        return $b['percent'] <=> $a['percent'];
                    });
                } 
                else { // marks view
                    uasort($passedStudents, fn($a, $b) => $b['percent'] <=> $a['percent']);
                }

                // Sort failed students (same logic as passed but they'll appear after)
                if ($view_mode === 'grades') {
                    uasort($failedStudents, function($a, $b) {
                        $gpaCompare = $b['gpa'] <=> $a['gpa'];
                        if ($gpaCompare !== 0) return $gpaCompare;
                        return $b['percent'] <=> $a['percent'];
                    });
                } 
                elseif ($view_mode === 'consolidated') {
                    uasort($failedStudents, function($a, $b) {
                        $gpaCompare = $b['gpa'] <=> $a['gpa'];
                        if ($gpaCompare !== 0) return $gpaCompare;
                        return $b['percent'] <=> $a['percent'];
                    });
                } 
                else { // marks view
                    uasort($failedStudents, fn($a, $b) => $b['percent'] <=> $a['percent']);
                }

                // Combine results with passed students first
                $results = $passedStudents + $failedStudents;
            }
        }
    }
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    // Check if required parameters are present
    if (!isset($_GET['exam_id']) || !isset($_GET['class_id'])) {
        die("Required parameters missing for PDF export.");
    }

    // Reinitialize parameters from GET
    $view_mode = $_GET['view_mode'] ?? 'marks';
    $exam_id = $_GET['exam_id'];
    $class_id = intval($_GET['class_id']);
    $section = $_GET['section'] ?? 'all';
    $school_id = $_SESSION['school_id'] ?? 1;

    // Initialize variables
    $results = [];
    $subjects = [];
    $subjectsById = [];
    $marksInfo = [];

    // Get grade from class_id
    $gradeRow = $conn->query("SELECT grade FROM classes WHERE id = $class_id AND school_id = $school_id")->fetch_assoc();
    $grade = $gradeRow ? $gradeRow['grade'] : null;

    if ($grade) {
        $classIds = [];
        $res = $conn->query("
            SELECT id FROM classes 
            WHERE grade = '" . $conn->real_escape_string($grade) . "' 
            AND school_id = $school_id
        ");
        while ($c = $res->fetch_assoc()) $classIds[] = $c['id'];

        if (!empty($classIds)) {
            $classIdList = implode(',', $classIds);

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
                    'exam_count' => []
                ];
            }

            if (!empty($studentIds)) {
                $ids = implode(',', $studentIds);

                // Handle Final Exam (Average of Terminal Exams)
                if ($exam_id === '0') {
                    $terminalExams = [];
                    $examRes = $conn->query("
                        SELECT id FROM exams
                        WHERE school_id = $school_id
                        AND exam_type = 'Terminal'
                    ");
                    while ($exam = $examRes->fetch_assoc()) $terminalExams[] = $exam['id'];

                    if (!empty($terminalExams)) {
                        $finalFullMarks = [];
                        $finalPassMarks = [];

                        // Fetch subjects
                        $subRes = $conn->query("
                            SELECT id, name FROM subjects
                            WHERE class_id IN ($classIdList)
                            ORDER BY name
                        ");
                        while ($sub = $subRes->fetch_assoc()) {
                            $subjectsById[$sub['id']] = $sub['name'];
                            if (!in_array($sub['name'], $subjects)) $subjects[] = $sub['name'];
                        }

                        $allSubIdsStr = implode(',', array_keys($subjectsById));

                        // Fetch full/pass marks
                        foreach ($terminalExams as $terminalExamId) {
                            $fullMarksRes = $conn->query("
                                SELECT subject_id, full_marks, pass_marks
                                FROM exam_subjects es
                                JOIN subjects s ON es.subject_id = s.id
                                WHERE es.exam_id = $terminalExamId
                                AND s.class_id IN ($classIdList)
                                AND subject_id IN ($allSubIdsStr)
                            ");
                            while ($row = $fullMarksRes->fetch_assoc()) {
                                $finalFullMarks[$row['subject_id']] = floatval($row['full_marks']);
                                $finalPassMarks[$row['subject_id']] = floatval($row['pass_marks']);
                            }
                        }

                        // Fetch marks
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
                                $results[$m['student_id']]['marks'][$subjectName] = ($results[$m['student_id']]['marks'][$subjectName] ?? 0) + (float)$m['marks'];
                                $results[$m['student_id']]['subject_full'][$subjectName] = $finalFullMarks[$m['subject_id']] ?? 0;
                                $results[$m['student_id']]['subject_pass'][$subjectName] = $finalPassMarks[$m['subject_id']] ?? 0;
                                $results[$m['student_id']]['exam_count'][$subjectName] = ($results[$m['student_id']]['exam_count'][$subjectName] ?? 0) + 1;
                            }
                        }

                        // Calculate averages
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
                    // Handle specific exam
                    $subRes = $conn->query("
                        SELECT id, name FROM subjects
                        WHERE class_id IN ($classIdList)
                        ORDER BY name
                    ");
                    while ($sub = $subRes->fetch_assoc()) {
                        $subjectsById[$sub['id']] = $sub['name'];
                        if (!in_array($sub['name'], $subjects)) $subjects[] = $sub['name'];
                    }

                    $allSubIdsStr = implode(',', array_keys($subjectsById));

                    // Get full/pass marks
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

                    // Get marks
                    $marksRes = $conn->query("
                        SELECT student_id, subject_id, marks 
                        FROM marks
                        WHERE exam_id = $exam_id AND student_id IN ($ids) AND subject_id IN ($allSubIdsStr)
                    ");
                    while ($m = $marksRes->fetch_assoc()) {
                        $subjectName = $subjectsById[$m['subject_id']] ?? 'Unknown';
                        $results[$m['student_id']]['marks'][$subjectName] = (float)$m['marks'];
                        $results[$m['student_id']]['subject_full'][$subjectName] = $marksInfo[$subjectName]['full_marks'] ?? 0;
                        $results[$m['student_id']]['subject_pass'][$subjectName] = $marksInfo[$subjectName]['pass_marks'] ?? 0;
                    }
                }

                // Calculate totals and results
                foreach ($results as &$st) {
                    $totalMarks = 0;
                    $totalFull = 0;
                    $pass = true;
                    $totalGPA = 0;
                    $subjectCount = 0;
                    $hasNG = false;

                    foreach ($st['marks'] as $sub => $mark) {
                        $full = $st['subject_full'][$sub] ?? 0;
                        $passMark = $st['subject_pass'][$sub] ?? 0;

                        $totalMarks += $mark;
                        $totalFull += $full;

                        $subjectPassed = ($mark >= $passMark);
                        if (!$subjectPassed) {
                            $pass = false;
                            $hasNG = true;
                        }

                        $percent = $full ? ($mark / $full) * 100 : 0;
                        [$letter, $gpa] = getGradeAndGPA($percent, $subjectPassed);
                        $totalGPA += $gpa;
                        $subjectCount++;
                    }

                    $st['total'] = round($totalMarks, 2);
                    $st['percent'] = $totalFull ? round(($totalMarks / $totalFull) * 100, 2) : 0;
                    $st['result'] = ($view_mode === 'grades') ? '-' : ($pass ? 'Pass' : 'Fail');
                    
                    if (($view_mode === 'grades' || $view_mode === 'consolidated') && $hasNG) {
                        $st['letter'] = 'NG';
                        $st['gpa'] = 0.0;
                    } else {
                        [$letter, ] = getGradeAndGPA($st['percent'], $pass);
                        $st['letter'] = $letter;
                        $st['gpa'] = $subjectCount ? round($totalGPA / $subjectCount, 2) : 0;
                    }
                }
                unset($st);

                // Sort results
                $passedStudents = [];
                $failedStudents = [];
                
                foreach ($results as $student_id => $student) {
                    if (strtolower($student['result']) === 'pass') {
                        $passedStudents[$student_id] = $student;
                    } else {
                        $failedStudents[$student_id] = $student;
                    }
                }

                if ($view_mode === 'grades') {
                    uasort($passedStudents, function($a, $b) {
                        $gpaCompare = $b['gpa'] <=> $a['gpa'];
                        return $gpaCompare !== 0 ? $gpaCompare : $b['percent'] <=> $a['percent'];
                    });
                    uasort($failedStudents, function($a, $b) {
                        $gpaCompare = $b['gpa'] <=> $a['gpa'];
                        return $gpaCompare !== 0 ? $gpaCompare : $b['percent'] <=> $a['percent'];
                    });
                } else {
                    uasort($passedStudents, fn($a, $b) => $b['percent'] <=> $a['percent']);
                    uasort($failedStudents, fn($a, $b) => $b['percent'] <=> $a['percent']);
                }

                $results = $passedStudents + $failedStudents;
            }
        }
    }

    // Generate PDF only if we have results
    if (empty($results) || empty($subjects)) {
        die("No results found for the selected exam/class/section.");
    }

    ob_start();
    ?>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { text-align: center; color: #007BFF; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10px; }
        th { background-color: #007BFF; color: white; padding: 6px; text-align: center; }
        td { padding: 5px; border: 1px solid #ddd; text-align: center; }
        .fail-mark { background-color: #ffdddd; }
        .pass { color: #007B00; font-weight: bold; }
        .fail { color: #b00000; font-weight: bold; }
        .header-info { margin-bottom: 15px; text-align: center; font-size: 12px; }
    </style>
    
    <h2>Student Results - <?= htmlspecialchars($view_mode) ?> View</h2>
    <div class="header-info">
        <div><strong>Class:</strong> Grade <?= htmlspecialchars($grade) ?> 
        <?= ($section !== 'all') ? 'Section '.htmlspecialchars($section) : 'All Sections' ?></div>
        <div><strong>Exam:</strong> <?= htmlspecialchars($exam_id === '0' ? 'Final Exam' : 
            $conn->query("SELECT exam_name FROM exams WHERE id = $exam_id")->fetch_assoc()['exam_name']) ?></div>
    </div>
    
    <table border="1" cellspacing="0" cellpadding="4">
        <thead>
            <tr>
                <th style="width: 30px;">No.</th>
                <th>Name</th>
                <th>Section</th>
                <?php foreach ($subjects as $subject): ?>
                    <th><?= htmlspecialchars($subject) ?></th>
                <?php endforeach; ?>
                
                <?php if ($view_mode !== 'grades'): ?>
                    <th>Total</th>
                    <th>%</th>
                <?php endif; ?>
                
                <?php if ($view_mode !== 'marks'): ?>
                    <th>Grade</th>
                    <th>GPA</th>
                <?php endif; ?>
                
                <?php if ($view_mode === 'marks'): ?>
                    <th>Result</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php $i = 1; foreach ($results as $student_id => $st): ?>
                <?php $resultClass = strtolower($st['result']) === 'pass' ? 'pass' : 'fail'; ?>
                <tr>
                    <td><?= $i++ ?></td>
                    <td style="text-align: left;"><?= htmlspecialchars($st['full_name']) ?></td>
                    <td><?= htmlspecialchars($st['section']) ?></td>
                    
                    <?php foreach ($subjects as $subject): ?>
                        <?php
                        $mark = $st['marks'][$subject] ?? 0;
                        $full = $st['subject_full'][$subject] ?? 0;
                        $passMark = $st['subject_pass'][$subject] ?? 0;
                        $fail_mark_class = ($mark < $passMark) ? "fail-mark" : "";
                        ?>
                        <td class="<?= $fail_mark_class ?>">
                            <?php
                            if ($view_mode === 'grades') {
                                $percent = $full ? ($mark / $full) * 100 : 0;
                                [$letter,] = getGradeAndGPA($percent, ($mark >= $passMark));
                                echo $letter;
                            } elseif ($view_mode === 'consolidated') {
                                $percent = $full ? ($mark / $full) * 100 : 0;
                                [$letter,] = getGradeAndGPA($percent, ($mark >= $passMark));
                                echo sprintf("%.2f (%s)", $mark, $letter);
                            } else {
                                echo sprintf("%.2f", $mark);
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                    
                    <?php if ($view_mode !== 'grades'): ?>
                        <td><?= $st['total'] ?></td>
                        <td><?= $st['percent'] ?>%</td>
                    <?php endif; ?>
                    
                    <?php if ($view_mode !== 'marks'): ?>
                        <td><?= $st['letter'] ?></td>
                        <td><?= $st['gpa'] ?></td>
                    <?php endif; ?>
                    
                    <?php if ($view_mode === 'marks'): ?>
                        <td class="<?= $resultClass ?>"><?= $st['result'] ?></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    $html = ob_get_clean();

    // Configure Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $options->set('isPhpEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    if (ob_get_length()) ob_end_clean();

    // Output the PDF
    $dompdf->stream("results_".date('Ymd_His').".pdf", [
        'Attachment' => false
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Manage Results</title>
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
        <h2>Student Results</h2>
        <form class="form-exam" method="GET">
            <div>
                <label for="exam_id">Exam:</label>
                <select name="exam_id" id="exam_id" required>
                    <option value="">-- Select Exam --</option>
                    <?php foreach ($exams as $exam): ?>
                        <option value="<?= $exam['id'] ?>" <?= isset($_GET['exam_id']) && $_GET['exam_id'] == $exam['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($exam['exam_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="class_id">Class:</label>
                <select name="class_id" id="class_id" required>
                    <option value="">-- Select Class --</option>
                    <?php foreach ($groupedClasses as $grade => $class): ?>
                        <option value="<?= $class['class_id'] ?>"
                            data-sections="<?= htmlspecialchars(implode(',', $class['sections'])) ?>"
                            <?= isset($_GET['class_id']) && $_GET['class_id'] == $class['class_id'] ? 'selected' : '' ?>>
                            Grade <?= htmlspecialchars($grade) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="section_id">Section:</label>
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
                            WHERE grade = '" . $conn->real_escape_string($selectedClass['grade']) . "' 
                            AND school_id = $school_id
                            ORDER BY section
                        ");
                            while ($sec = $sections->fetch_assoc()) {
                                $selected = ($_GET['section'] ?? '') == $sec['section'] ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($sec['section']) . "' $selected>" . htmlspecialchars($sec['section']) . "</option>";
                            }
                        }
                    }
                    ?>
                </select>
            </div>

            <div>
                <label for="view_mode">View Mode:</label>
                <select name="view_mode" id="view_mode" required>
                    <option value="marks" <?= $view_mode === 'marks' ? 'selected' : '' ?>>Marks</option>
                    <option value="grades" <?= $view_mode === 'grades' ? 'selected' : '' ?>>Grades</option>
                    <option value="consolidated" <?= $view_mode === 'consolidated' ? 'selected' : '' ?>>Consolidated</option>
                </select>
            </div>

            <div style="align-self: flex-end; display:flex; gap: 10px;">
    <button class="result-btn" type="submit">Show Results</button>
    <?php if (isset($_GET['exam_id']) && isset($_GET['class_id'])): ?>
        <a href="?<?= 
            http_build_query([
                'export' => 'pdf',
                'exam_id' => $_GET['exam_id'],
                'class_id' => $_GET['class_id'],
                'section' => $_GET['section'] ?? 'all',
                'view_mode' => $_GET['view_mode'] ?? 'marks'
            ]) 
        ?>" target="_blank" class="result-btn" style="background-color: #dc3545;">Export PDF</a>
    <?php endif; ?>
</div>
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
                $('#class_id').on('change', populateSections);
                populateSections();
            });
        </script>

        <div class="table-wrapper">
            <?php if (!empty($results) && !empty($subjects)): ?>
                <?php renderResultsTable($results, $subjects, $view_mode); ?>
            <?php elseif (isset($_GET['exam_id'])): ?>
                <p>No results found for the selected exam/class/section.</p>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>