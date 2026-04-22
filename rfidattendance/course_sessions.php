<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
  header("location: login.php");
  exit();
}

require 'connectDB.php';

/* ---------------- Flash helper ---------------- */
function flash($msg, $type = "info") {
  $_SESSION['flash'] = ["msg" => $msg, "type" => $type];
}
function read_flash() {
  if (!isset($_SESSION['flash'])) return null;
  $f = $_SESSION['flash'];
  unset($_SESSION['flash']);
  return $f;
}

/* ---------------- Common validation ---------------- */
function is_valid_time($t) { return preg_match('/^\d{2}:\d{2}$/', $t); }       // HH:MM
function is_valid_date($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d); } // YYYY-MM-DD
function time_less($a, $b) { return strtotime($a) < strtotime($b); }

/* ---------------- Status refresh ----------------
   course_sessions.status enum:
   scheduled, closed, attendance_generated

   We'll treat "scheduled" as "attendance window open right now".
-------------------------------------------------- */
function refresh_session_status(mysqli $conn): void {
  $conn->query("
    UPDATE course_sessions
    SET status = CASE
      WHEN session_date = CURDATE()
       AND CURTIME() >= start_time
       AND CURTIME() <= ADDTIME(end_time, SEC_TO_TIME(COALESCE(grace_minutes,10)*60))
      THEN 'scheduled'
      ELSE 'closed'
    END
    WHERE status <> 'attendance_generated'
  ");
}

/* ---------------- Load Rooms (class_rooms table) ----------------
   Assumes: class_rooms(room_id, room_name)
-------------------------------------------------- */
$rooms = [];
$qRooms = $conn->query("SELECT room_id, room_name FROM class_rooms ORDER BY room_name");
if ($qRooms) {
  while ($row = $qRooms->fetch_assoc()) $rooms[] = $row;
}

/* ---------------- Load Courses ---------------- */
$courses = [];
$q1 = $conn->query("SELECT course_id, course_code, course_name FROM courses ORDER BY course_code");
if ($q1) while ($row = $q1->fetch_assoc()) $courses[] = $row;

/* ---------------- POST handler ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $course_id     = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
  $room_id       = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;
  $date          = $_POST['session_date'] ?? '';
  $start_time    = $_POST['start_time'] ?? '';
  $end_time      = $_POST['end_time'] ?? '';
  $grace_minutes = isset($_POST['grace_minutes']) ? (int)$_POST['grace_minutes'] : 10;

  if ($course_id <= 0 || $room_id <= 0 || !is_valid_date($date) || !is_valid_time($start_time) || !is_valid_time($end_time)) {
    flash("Missing/invalid data (course/room/date/time).", "danger");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  if (!time_less($start_time, $end_time)) {
    flash("Start time must be before end time.", "danger");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  if ($grace_minutes < 0) $grace_minutes = 0;
  if ($grace_minutes > 60) $grace_minutes = 60;

  // Keep statuses consistent before doing anything
  refresh_session_status($conn);

  /* -------- Overlap check (room-based) --------
     overlap if: new_start < (old_end + old_grace) AND new_end > old_start
  --------------------------------------------- */
  $check = $conn->prepare("
    SELECT session_id
    FROM course_sessions
    WHERE room_id = ?
      AND session_date = ?
      AND status <> 'attendance_generated'
      AND (? < ADDTIME(end_time, SEC_TO_TIME(COALESCE(grace_minutes,10)*60)))
      AND (? > start_time)
    LIMIT 1
  ");
  if (!$check) {
    flash("DB prepare failed (overlap check): " . $conn->error, "danger");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  $check->bind_param("isss", $room_id, $date, $start_time, $end_time);
  $check->execute();
  $res = $check->get_result();

  if ($res && $res->num_rows > 0) {
    $check->close();
    flash("Overlapping session exists in this room for this date/time.", "danger");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
  $check->close();

  /* -------- Insert session -------- */
  $status = 'closed'; // refresh_session_status() will set to scheduled if time matches

  $stmt = $conn->prepare("
    INSERT INTO course_sessions (course_id, room_id, session_date, start_time, end_time, grace_minutes, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
  ");
  if (!$stmt) {
    flash("DB prepare failed (insert): " . $conn->error, "danger");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }

  $stmt->bind_param("iisssis", $course_id, $room_id, $date, $start_time, $end_time, $grace_minutes, $status);

  if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    flash("Failed to create session: " . $err, "danger");
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
  }
  $stmt->close();

  // Update statuses again (in case this session is for "now")
  refresh_session_status($conn);

  flash("Session created successfully (saved to course_sessions).", "success");
  header("Location: " . $_SERVER['PHP_SELF']);
  exit();
}

/* ---------------- Always refresh statuses before showing list ---------------- */
refresh_session_status($conn);

/* ---------------- Load existing sessions (join gives course_code + room_name) ---------------- */
$sessions = [];
$q2 = $conn->query("
  SELECT
    cs.session_id,
    c.course_code,
    r.room_name,
    cs.session_date,
    cs.start_time,
    cs.end_time,
    cs.grace_minutes,
    cs.status
  FROM course_sessions cs
  JOIN courses c       ON c.course_id = cs.course_id
  JOIN class_rooms r   ON r.room_id   = cs.room_id
  ORDER BY cs.session_date DESC, cs.start_time DESC
  LIMIT 50
");
if ($q2) while ($row = $q2->fetch_assoc()) $sessions[] = $row;

$flash = read_flash();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Course Sessions</title>
  <meta charset="utf-8">
  <link rel="stylesheet" href="css/manageusers.css">
  <style>
    :root { --card:#fff; --text:#0f172a; --muted:#64748b; --line:#e5e7eb; --shadow:0 10px 30px rgba(0,0,0,.10); --radius:14px; }
    .wrap{ max-width:1050px; margin:0 auto; padding:22px 16px 40px; }
    h2{ margin:8px 0 18px; font-size:28px; color:rgba(0,0,0,.45); font-weight:700; }
    .card{ background:var(--card); border-radius:var(--radius); box-shadow:var(--shadow); border:1px solid rgba(0,0,0,.06); overflow:hidden; }
    .card-header{ padding:18px 22px; border-bottom:1px solid var(--line); display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .card-header h3{ margin:0; font-size:20px; color:var(--text); }
    .sub{ margin:0; font-size:13px; color:var(--muted); }
    .card-body{ padding:18px 22px 22px; }

    .msg{ padding:12px 14px; border-radius:12px; margin:10px 0 16px; border:1px solid transparent; font-size:14px; }
    .success{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
    .danger{ background:#fef2f2; color:#991b1b; border-color:#fecaca; }
    .info{ background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }

    .form-grid{ display:grid; grid-template-columns:1fr 1fr; gap:14px 16px; margin-top:10px; }
    .field{ display:flex; flex-direction:column; gap:6px; }
    .field label{ font-size:13px; font-weight:700; color:var(--text); }
    input,select{ width:100%; height:44px; padding:10px 12px; border-radius:10px; border:1px solid var(--line); outline:none; background:#fff; color:var(--text); }
    .full{ grid-column:1/-1; }
    .actions{ grid-column:1/-1; display:flex; justify-content:flex-end; margin-top:4px; }
    button{ height:44px; padding:0 18px; border:none; border-radius:12px; background:#0ea5a4; color:#fff; font-weight:800; cursor:pointer; }

    .table-wrap{ margin-top:18px; border:1px solid var(--line); border-radius:12px; overflow:hidden; }
    .table-title{ padding:12px 14px; border-bottom:1px solid var(--line); background:#f8fafc; }
    .table-title h4{ margin:0; font-size:16px; color:var(--text); }
    .table-scroll{ overflow:auto; max-height:360px; }
    table{ width:100%; border-collapse:collapse; min-width:920px; }
    thead th{ position:sticky; top:0; background:#f1f5f9; color:#000; font-size:12px; letter-spacing:.04em; text-transform:uppercase; padding:10px 12px; text-align:left; z-index:1; border-bottom:1px solid #e5e7eb; }
    tbody td{ padding:10px 12px; border-bottom:1px solid var(--line); font-size:14px; color:#0f172a; background:#fff; white-space:nowrap; }
    tbody tr:nth-child(even) td{ background:#f8fafc; }
    .mono{ font-variant-numeric:tabular-nums; font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace; }
    .status{ display:inline-flex; align-items:center; gap:8px; padding:5px 10px; border-radius:999px; font-size:12px; font-weight:800; border:1px solid; }
    .status-open{ background:#ecfdf5; color:#065f46; border-color:#a7f3d0; }
    .status-closed{ background:#fef2f2; color:#991b1b; border-color:#fecaca; }
    .dot{ width:8px; height:8px; border-radius:999px; background:currentColor; opacity:.8; }

    @media (max-width:900px){
      h2{ font-size:22px; }
      .card-body{ padding:16px; }
      .card-header{ padding:14px 16px; }
      .form-grid{ grid-template-columns:1fr; }
      .actions{ justify-content:stretch; }
      button{ width:100%; }
      table{ min-width:860px; }
    }
  </style>
</head>

<body>
<?php include 'header.php'; ?>

<div class="wrap">
  <h2>Session Management</h2>

  <?php if ($flash): ?>
    <div class="msg <?= htmlspecialchars($flash['type']) ?>">
      <?= htmlspecialchars($flash['msg']) ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-header">
      <div>
        <h3>Create Session</h3>
        <p class="sub">Rooms loaded from <b>class_rooms</b> table. Sessions saved to <b>course_sessions</b>.</p>
      </div>
    </div>

    <div class="card-body">

      <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
        <div class="form-grid">

          <div class="field full">
            <label>Course</label>
            <select name="course_id" required>
              <option value="">-- Select Course --</option>
              <?php foreach ($courses as $c): ?>
                <option value="<?= (int)$c['course_id'] ?>">
                  <?= htmlspecialchars($c['course_code']) ?> — <?= htmlspecialchars($c['course_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field full">
            <label>Room</label>
            <select name="room_id" required>
              <option value="">-- Select Room --</option>
              <?php foreach ($rooms as $r): ?>
                <option value="<?= (int)$r['room_id'] ?>">
                  <?= htmlspecialchars($r['room_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="field">
            <label>Date</label>
            <input type="date" name="session_date" required>
          </div>

          <div class="field">
            <label>Grace Minutes</label>
            <input type="number" name="grace_minutes" value="10" min="0" max="60">
          </div>

          <div class="field">
            <label>Start Time</label>
            <input type="time" name="start_time" required>
          </div>

          <div class="field">
            <label>End Time</label>
            <input type="time" name="end_time" required>
          </div>

          <div class="actions">
            <button type="submit">Create Session</button>
          </div>

        </div>
      </form>

      <?php if (count($sessions) > 0): ?>
        <div class="table-wrap">
          <div class="table-title">
            <h4>Recent Sessions</h4>
          </div>

          <div class="table-scroll">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Course Code</th>
                  <th>Room</th>
                  <th>Date</th>
                  <th>Start</th>
                  <th>End</th>
                  <th>Grace</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sessions as $s): ?>
                  <?php $isOpen = ($s['status'] === 'scheduled'); ?>
                  <tr>
                    <td class="mono"><?= (int)$s['session_id'] ?></td>
                    <td class="mono"><?= htmlspecialchars($s['course_code']) ?></td>
                    <td><?= htmlspecialchars($s['room_name']) ?></td>
                    <td class="mono"><?= htmlspecialchars($s['session_date']) ?></td>
                    <td class="mono"><?= htmlspecialchars($s['start_time']) ?></td>
                    <td class="mono"><?= htmlspecialchars($s['end_time']) ?></td>
                    <td class="mono"><?= (int)$s['grace_minutes'] ?></td>
                    <td>
                      <span class="status <?= $isOpen ? 'status-open' : 'status-closed' ?>">
                        <span class="dot"></span>
                        <?= htmlspecialchars($s['status']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

</body>
</html>
