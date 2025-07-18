<?php
include '../partials/dbconnect.php';

$class_filter = $_GET['filter_class'] ?? 'all';
$where = ($class_filter !== 'all') ? "WHERE classes.id = " . (int)$class_filter : '';
$exams = $conn->query("
  SELECT exams.*, classes.grade, classes.section 
  FROM exams 
  JOIN classes ON exams.class_id = classes.id
  $where
  ORDER BY exams.id DESC
");

if ($exams->num_rows > 0):
  $i = 1;
  while ($row = $exams->fetch_assoc()):
?>
    <tr class="hover:bg-gray-100">
      <td class="p-3 border"><?= $i++ ?></td>
      <td class="p-3 border"><?= htmlspecialchars($row['exam_name']) ?></td>
      <td class="p-3 border">Grade <?= $row['grade'] ?> - <?= $row['section'] ?></td>
      <td class="p-3 border"><?= htmlspecialchars($row['exam_type']) ?></td>
      <td class="p-3 border space-x-2">
        <button onclick='openEditModal(<?= json_encode($row) ?>)' class="px-3 py-1 bg-yellow-400 text-white rounded">✏️ Edit</button>
        <button onclick='openDeleteModal(<?= $row["id"] ?>, "<?= addslashes($row["exam_name"]) ?>")' class="px-3 py-1 bg-red-500 text-white rounded">🗑️ Delete</button>
      </td>
    </tr>
<?php
  endwhile;
else:
?>
  <tr><td colspan="5" class="text-center text-gray-500 p-4">No exams found.</td></tr>
<?php endif; ?>
