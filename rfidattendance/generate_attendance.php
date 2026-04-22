<?php
require 'connectDB.php';

$session_id = $_POST['session_id'];

// lock session
$conn->query("
  UPDATE course_sessions
  SET locked = 1
  WHERE session_id = $session_id
");

// get session info
$session = $conn->query("
  SELECT cs.*, c.course_id
  FROM course_sessions cs
  JOIN courses c ON c.course_id = cs.course_id
  WHERE cs.session_id = $session_id
")->fetch_assoc();

$start = $session['start_time'];
$late  = date("H:i:s", strtotime($start) + 600); // +10 min
$end   = $session['end_time'];
$date  = $session['session_date'];
$course_id = $session['course_id'];

// get enrolled students
$students = $conn->query("
  SELECT sc.student_id
  FROM student_courses sc
  WHERE sc.course_id = $course_id
    AND sc.status = 'active'
");

// process each student
while ($s = $students->fetch_assoc()) {

  $student_id = $s['student_id'];

  $log = $conn->query("
    SELECT timein
    FROM users_logs
    WHERE serialnumber = $student_id
      AND checkindate = '$date'
      AND timein BETWEEN '$start' AND '$end'
    ORDER BY timein ASC
    LIMIT 1
  ");

  if ($log->num_rows == 0) {
    $status = 'absent';
  } else {
    $timein = $log->fetch_assoc()['timein'];
    $status = ($timein <= $late) ? 'present' : 'late';
  }

  $stmt = $conn->prepare("
    INSERT INTO attendance (session_id, student_id, status)
    VALUES (?, ?, ?)
  ");
  $stmt->bind_param("iis", $session_id, $student_id, $status);
  $stmt->execute();
}

echo "Attendance generated";
