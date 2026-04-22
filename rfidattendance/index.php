<?php
// -------- SESSION CHECK --------
session_start();
if (!isset($_SESSION['Admin-name'])) {
  header("location: login.php");
  exit();
}

// -------- DATABASE CONNECTION --------
require 'connectDB.php';
?>
<!DOCTYPE html>
<html>

<head>
  <title>Students</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="icon" type="image/png" href="images/favicon.png">

  <!-- JS & CSS -->
  <script src="js/jquery-2.2.3.min.js"></script>
  <script src="js/bootstrap.js"></script>
  <link rel="stylesheet" type="text/css" href="css/Users.css">

  <!-- UI scroll fix -->
  <script>
    $(window).on("load resize", function() {
      var scrollWidth = $('.tbl-content').width() - $('.tbl-content table').width();
      $('.tbl-header').css({
        'padding-right': scrollWidth
      });
    }).resize();
  </script>
</head>

<body>

  <?php include 'header.php'; ?>

  <main>
    <section>

      <h1 class="slideInDown animated">Registered Students</h1>

      <!-- =====================================================
     FILTER BAR (Department + Batch)
     ===================================================== -->
      <div class="form-style-5" style="margin-bottom:20px;">
        <form method="GET">

          <!-- -------- DEPARTMENT FILTER -------- -->
          <label><b>Department:</b></label>
          <select name="department_id">
            <?php
            /*
         * Default department = ETE
         * If no filter applied, auto-select ETE
         */
            $selected_dept = $_GET['department_id'] ?? null;

            $deptRes = mysqli_query($conn, "SELECT * FROM departments ORDER BY department_name");
            while ($d = mysqli_fetch_assoc($deptRes)) {

              // default selection logic
              $selected = '';
              if ($selected_dept == $d['department_id']) {
                $selected = 'selected';
              } elseif (!$selected_dept && $d['department_name'] === 'ETE') {
                $selected = 'selected';
                $selected_dept = $d['department_id'];
              }

              echo "<option value='{$d['department_id']}' $selected>
                    {$d['department_name']}
                  </option>";
            }
            ?>
          </select>

          <!-- -------- BATCH FILTER -------- -->
          <label><b>Batch:</b></label>
          <select name="batch">
            <?php
            // Default batch = 21
            $selected_batch = $_GET['batch'] ?? 21;

            for ($i = 12; $i <= 30; $i++) {
              $sel = ($i == $selected_batch) ? 'selected' : '';
              echo "<option value='$i' $sel>$i</option>";
            }
            ?>
          </select>

          <button type="submit">Filter</button>
        </form>
      </div>

      <!-- =====================================================
     STUDENT TABLE
     ===================================================== -->
      <div class="table-responsive slideInRight animated" style="max-height: 400px;">
        <table class="table">

          <thead class="table-primary">
            <tr>
              <th>Student ID</th>
              <th>Name</th>
              <th>Gender</th>
              <th>Card UID</th>
              <th>Department</th>
              <th>Batch</th>
              <th>Level-Term</th>
              <th>Registered</th>
            </tr>
          </thead>

          <tbody class="table-secondary">

            <?php
            // =====================================================
            // DATA QUERY (FILTERED)
            // =====================================================

            // Safety fallback (should never be null)
            if (!$selected_dept) {
              echo "<tr><td colspan='8'>Department not found</td></tr>";
              exit();
            }

            $sql = "
                    SELECT
                        s.student_id,
                        s.name,
                        s.roll_no,
                        s.gender,
                        s.created_at,
                        s.batch,
                        s.level,
                        s.term,
                        r.card_uid,
                        d.department_name,
                        s.department_id
                    FROM students s
                    JOIN departments d ON d.department_id = s.department_id
                    LEFT JOIN rfid_cards r ON r.student_id = s.student_id
                    WHERE s.department_id = ?
                      AND s.batch = ?
                    ORDER BY s.roll_no ASC
                ";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $selected_dept, $selected_batch);
            $stmt->execute();
            $res = $stmt->get_result();

            if ($res->num_rows > 0) {
              while ($row = $res->fetch_assoc()) {
            ?>
                <tr class="student-row"
                  data-id="<?php echo $row['student_id']; ?>"
                  data-roll="<?php echo $row['roll_no']; ?>"
                  data-name="<?php echo htmlspecialchars($row['name']); ?>"
                  data-gender="<?php echo $row['gender']; ?>"
                  data-card="<?php echo $row['card_uid']; ?>"
                  data-department="<?php echo $row['department_name']; ?>"
                  data-deptid="<?php echo $row['department_id']; ?>"
                  data-batch="<?php echo $row['batch']; ?>"
                  data-level="<?php echo $row['level']; ?>"
                  data-term="<?php echo $row['term']; ?>"
                  data-date="<?php echo $row['created_at']; ?>">

                  <!-- Student ID -->
                  <td><?php echo $row['roll_no']; ?></td>

                  <!-- Student Name -->
                  <td><?php echo htmlspecialchars($row['name']); ?></td>

                  <td><?php echo $row['gender']; ?></td>
                  <td><?php echo $row['card_uid'] ?? '—'; ?></td>
                  <td><?php echo $row['department_name']; ?></td>
                  <td><?php echo $row['batch']; ?></td>
                  <td><?php echo "L{$row['level']} - T{$row['term']}"; ?></td>
                  <td><?php echo $row['created_at']; ?></td>
                </tr>

            <?php
              }
            } else {
              echo "<tr><td colspan='8'>No students found</td></tr>";
            }
            ?>

          </tbody>
        </table>
      </div>

    </section>
  </main>

  <!-- ================= STUDENT INFO MODAL ================= -->
  <div class="modal fade" id="studentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">

        <div class="modal-header">
          <h4 class="modal-title">Student Information</h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <p><b>Student ID:</b> <span id="m_roll"></span></p>
          <p><b>Name:</b> <span id="m_name"></span></p>
          <p><b>Gender:</b> <span id="m_gender"></span></p>
          <p><b>Card UID:</b> <span id="m_card"></span></p>
          <p><b>Department:</b> <span id="m_department"></span></p>
          <p><b>Batch:</b> <span id="m_batch"></span></p>
          <p><b>Level / Term:</b> <span id="m_level_term"></span></p>
          <p><b>Registered:</b> <span id="m_date"></span></p>
          <hr>
          <h4>Enrolled Courses</h4>

          <table class="table table-bordered">
            <thead class="table-primary">
              <tr>
                <th>Course Code</th>
                <th>Course Title</th>
              </tr>
            </thead>
            <tbody id="course_table">
              <tr>
                <td colspan="2">Loading...</td>
              </tr>
            </tbody>
          </table>

        </div>

        <div class="modal-footer">
          <button id="editStudent" class="btn btn-warning">Edit</button>
          <button id="deleteStudent" class="btn btn-danger">Delete</button>
          <button class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>

      </div>
    </div>
  </div>

  <!-- ================= DELETE CONFIRM MODAL ================= -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">

        <div class="modal-header bg-danger text-white">
          <h4 class="modal-title">Confirm Deletion</h4>
          <button type="button" class="close text-white" data-dismiss="modal">
            &times;
          </button>
        </div>

        <div class="modal-body">
          <p style="font-size:16px;">
            This will <b>permanently delete</b> the student and:
          </p>
          <ul>
            <li>Remove student record</li>
            <li>Unassign RFID card</li>
            <li>Remove course enrollments</li>
            <li>Delete attendance data</li>
          </ul>

          <p class="text-danger">
            This action <b>cannot be undone</b>.
          </p>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-dismiss="modal">
            Cancel
          </button>
          <button class="btn btn-danger" id="confirmDeleteStudent">
            Yes, Delete
          </button>
        </div>

      </div>
    </div>
  </div>

  <script src="js/students_popup.js"></script>

</body>

</html>