<?php
session_start();
if (!isset($_SESSION['Admin-name'])) exit();
require 'connectDB.php';

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=attendance_summary.csv");

$department_id = $_GET['department_id'] ?? '';
$batch         = $_GET['batch'] ?? '';
$level         = $_GET['level'] ?? '';
$term          = $_GET['term'] ?? '';
$course_id     = $_GET['course_id'] ?? '';

$out = fopen("php://output", "w");

fputcsv($out, ["Attendance Summary"]);
fputcsv($out, ["Department",$department_id,"Batch",$batch,"Level",$level,"Term",$term,"Course",$course_id]);
fputcsv($out, []);

fputcsv($out, ["Student","Course","Attended","Total","Percentage"]);

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
    fputcsv($out, [
        $r['name'],
        $r['course_code'],
        $r['attended'],
        $r['total_sessions'],
        $r['percentage']."%"
    ]);
}
fclose($out);