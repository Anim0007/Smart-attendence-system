<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
    exit();
}
require 'connectDB.php';

/* -------- FILTER DEFAULTS -------- */
$department_id = $_GET['department_id'] ?? '';
$level          = $_GET['level'] ?? '';
$term           = $_GET['term'] ?? '';
$course_id      = $_GET['course_id'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Attendance Summary</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="css/Users.css">
</head>

<body>
<?php include 'header.php'; ?>

<main>
<h1 class="slideInDown animated">Attendance Summary</h1>

<!-- ================= FILTER BAR ================= -->
<div class="form-style-5">
<form method="GET">

<label>Department</label>
<select name="department_id">
<option value="">All</option>
<?php
$res = $conn->query("SELECT * FROM departments");
while ($d = $res->fetch_assoc()) {
    $sel = ($department_id == $d['department_id']) ? 'selected' : '';
    echo "<option value='{$d['department_id']}' $sel>{$d['department_name']}</option>";
}
?>
</select>

<label>Level</label>
<select name="level">
<option value="">All</option>
<?php for ($i=1;$i<=4;$i++): ?>
<option value="<?= $i ?>" <?= ($level==$i?'selected':'') ?>>Level <?= $i ?></option>
<?php endfor; ?>
</select>

<label>Term</label>
<select name="term">
<option value="">All</option>
<option value="1" <?= ($term==1?'selected':'') ?>>Term 1</option>
<option value="2" <?= ($term==2?'selected':'') ?>>Term 2</option>
</select>

<label>Course</label>
<select name="course_id">
<option value="">All</option>
<?php
$res = $conn->query("SELECT course_id, course_code FROM courses");
while ($c = $res->fetch_assoc()) {
    $sel = ($course_id == $c['course_id']) ? 'selected' : '';
    echo "<option value='{$c['course_id']}' $sel>{$c['course_code']}</option>";
}
?>
</select>

<button type="submit">Filter</button>
</form>
</div>

<!-- ================= EXPORT BUTTONS ================= -->
<div style="margin:15px 0;">
<?php $qs = http_build_query($_GET); ?>

<a href="export_attendance_pdf.php?<?= $qs ?>" target="_blank" class="btn btn-danger">
Export PDF
</a>

<a href="export_attendance_excel.php?<?= $qs ?>" class="btn btn-success">
Export Excel
</a>

<a href="export_attendance_csv.php?<?= $qs ?>" class="btn btn-primary">
Export CSV
</a>
</div>

<!-- ================= SUMMARY TABLE ================= -->
<div class="table-responsive">
<table class="table">
<thead class="table-primary">
<tr>
<th>Student</th>
<th>Course</th>
<th>Attended</th>
<th>Total</th>
<th>Percentage</th>
</tr>
</thead>

<tbody class="table-secondary">
<?php
$sql = "
SELECT *
FROM attendance_summary v
JOIN students s ON s.student_id = v.student_id
JOIN courses c ON c.course_id = v.course_id
WHERE 1=1
";

$params = [];
$types  = '';

if ($department_id) {
    $sql .= " AND s.department_id = ?";
    $params[] = $department_id;
    $types .= 'i';
}
if ($level) {
    $sql .= " AND s.level = ?";
    $params[] = $level;
    $types .= 'i';
}
if ($term) {
    $sql .= " AND s.term = ?";
    $params[] = $term;
    $types .= 'i';
}
if ($course_id) {
    $sql .= " AND c.course_id = ?";
    $params[] = $course_id;
    $types .= 'i';
}

$sql .= " ORDER BY s.roll_no, c.course_code";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    echo "<tr><td colspan='5'>No data found</td></tr>";
}

while ($row = $res->fetch_assoc()):
?>
<tr>
<td><?= htmlspecialchars($row['name']) ?></td>
<td><?= $row['course_code'] ?></td>
<td><?= $row['attended'] ?></td>
<td><?= $row['total_sessions'] ?></td>
<td><?= $row['percentage'] ?>%</td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

</main>
</body>
</html>