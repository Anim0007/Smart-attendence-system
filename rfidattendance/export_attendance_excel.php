<?php
session_start();
if (!isset($_SESSION['Admin-name'])) exit();
require 'connectDB.php';

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=attendance_summary.xls");

$department_id = $_GET['department_id'] ?? '';
$batch         = $_GET['batch'] ?? '';
$level         = $_GET['level'] ?? '';
$term          = $_GET['term'] ?? '';
$course_id     = $_GET['course_id'] ?? '';

echo "Attendance Summary\n";
echo "Department:\t$department_id\tBatch:\t$batch\tLevel:\t$level\tTerm:\t$term\tCourse:\t$course_id\n\n";

echo "Student\tCourse\tAttended\tTotal\tPercentage\n";

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
while ($r = $res->fetch_assoc()) {
    echo "{$r['name']}\t{$r['course_code']}\t{$r['attended']}\t{$r['total_sessions']}\t{$r['percentage']}%\n";
}