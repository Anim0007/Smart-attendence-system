<?php
require 'connectDB.php';

$department_id = $_GET['department_id'];
$level = $_GET['level'];
$term  = $_GET['term'];

$sql = "
SELECT
    s.student_id,
    s.roll_no,
    s.name,
    c.course_code,
    c.course_name,
    c.credit,

    COUNT(DISTINCT cs.session_id) AS total_sessions,

    COUNT(DISTINCT a.session_id) AS attended_sessions,

    ROUND(
        (COUNT(DISTINCT a.session_id) / COUNT(DISTINCT cs.session_id)) * 100,
        2
    ) AS attendance_percent

FROM students s

JOIN student_courses sc
    ON sc.student_id = s.student_id
    AND sc.status = 'active'

JOIN courses c
    ON c.course_id = sc.course_id

LEFT JOIN course_sessions cs
    ON cs.course_id = c.course_id

LEFT JOIN attendance a
    ON a.session_id = cs.session_id
    AND a.student_id = s.student_id
    AND a.status IN ('present','late')

WHERE
    s.department_id = ?
    AND s.level = ?
    AND s.term = ?

GROUP BY
    s.student_id, c.course_id

ORDER BY
    s.roll_no, c.course_code
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $department_id, $level, $term);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['expected_sessions'] = $row['credit'] * 13;
    $data[] = $row;
}

echo json_encode($data);