<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
    exit();
}
require 'connectDB.php';

/* -------- FILTERS -------- */
$department_id = $_GET['department_id'] ?? '';
$batch         = $_GET['batch'] ?? '';
$level         = $_GET['level'] ?? '';
$term          = $_GET['term'] ?? '';
$course_id     = $_GET['course_id'] ?? '';

/* -------- META INFO -------- */
$deptName = $courseCode = $courseName = "All";

if ($department_id) {
    $r = $conn->query("SELECT department_name FROM departments WHERE department_id=$department_id")->fetch_assoc();
    $deptName = $r['department_name'];
}
if ($course_id) {
    $r = $conn->query("SELECT course_code, course_name FROM courses WHERE course_id=$course_id")->fetch_assoc();
    $courseCode = $r['course_code'];
    $courseName = $r['course_name'];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Attendance Summary</title>
<style>
body { font-family: Arial; }
h1 { text-align:center; }
.meta { margin:15px 0; }
table { width:100%; border-collapse:collapse; }
th,td { border:1px solid #000; padding:6px; text-align:center; }
</style>
</head>
<body>

<h1>Attendance Summary</h1>

<div class="meta">
<b>Department:</b> <?= $deptName ?> |
<b>Batch:</b> <?= $batch ?: 'All' ?> |
<b>Level:</b> <?= $level ?: 'All' ?> |
<b>Term:</b> <?= $term ?: 'All' ?> |
<b>Course:</b> <?= $courseCode ?> <?= $courseName ?>
</div>

<table>
<tr>
<th>Student</th>
<th>Course</th>
<th>Attended</th>
<th>Total</th>
<th>Percentage</th>
</tr>

<?php
$sql = "
SELECT s.name, c.course_code, v.attended, v.total_sessions, v.percentage
FROM attendance_summary v
JOIN students s ON s.student_id = v.student_id
JOIN courses c ON c.course_id = v.course_id
WHERE 1=1
";

if ($department_id) $sql .= " AND s.department_id=$department_id";
if ($batch)         $sql .= " AND s.batch='$batch'";
if ($level)         $sql .= " AND s.level=$level";
if ($term)          $sql .= " AND s.term=$term";
if ($course_id)     $sql .= " AND c.course_id=$course_id";

$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    echo "<tr>
        <td>{$row['name']}</td>
        <td>{$row['course_code']}</td>
        <td>{$row['attended']}</td>
        <td>{$row['total_sessions']}</td>
        <td>{$row['percentage']}%</td>
    </tr>";
}
?>
</table>

<script>
window.print();
</script>

</body>
</html>