<?php
session_start();
if (!isset($_SESSION['Admin-name'])) {
    header("location: login.php");
    exit();
}
require 'connectDB.php';
?>
<!DOCTYPE html>
<html>

<head>
    <title>Manage Classes</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSS -->
    <link rel="icon" type="image/png" href="images/favicon.png">
    <link rel="stylesheet" href="css/bootstrap.css">
    <link rel="stylesheet" href="css/manageusers.css">
    <style>
        /* Improve table text readability */
        .table th,
        .table td {
            color: #000 !important;
            /* solid black text */
        }

        /* Optional: header slightly darker */
        .table thead th {
            background-color: #cfe8ff;
            color: #000;
        }

        /* Optional: row hover clarity */
        .table tbody tr:hover {
            background-color: #e6f2f5;
        }
    </style>


    <!-- JS -->
    <script src="js/jquery-2.2.3.min.js"></script>
</head>

<body>

    <?php include 'header.php'; ?>

    <main class="container-fluid">

        <h1 class="slideInDown animated">Class Management</h1>

        <div class="row" style="margin-top:30px;">

            <!-- ================= LEFT: ADD CLASS FORM ================= -->
            <div class="col-md-4 col-sm-12">
                <div class="form-style-5">

                    <h3>Add New Class</h3>

                    <form method="POST">

                        <label><b>Class Name</b></label>
                        <input
                            type="text"
                            name="class_name"
                            required>

                        <label><b>Section</b></label>
                        <input
                            type="text"
                            name="section"
                            required>

                        <button type="submit" name="add_class">
                            Add Class
                        </button>

                    </form>

                    <?php
                    // ================= INSERT CLASS =================
                    if (isset($_POST['add_class'])) {

                        $class_name = trim($_POST['class_name']);
                        $section    = trim($_POST['section']);

                        if ($class_name && $section) {

                            // prevent duplicate class + section
                            $chk = $conn->prepare(
                                "SELECT class_id FROM classes WHERE class_name=? AND section=?"
                            );
                            $chk->bind_param("ss", $class_name, $section);
                            $chk->execute();
                            $chk->store_result();

                            if ($chk->num_rows > 0) {
                                echo "
                            <p class='alert alert-danger' style='margin-top:10px;color:#000;'>
                                Class already exists
                            </p>
                            ";
                            } else {

                                $stmt = $conn->prepare(
                                    "INSERT INTO classes (class_name, section) VALUES (?, ?)"
                                );
                                $stmt->bind_param("ss", $class_name, $section);
                                $stmt->execute();

                                echo "
                            <p class='alert alert-success' style='margin-top:10px;color:#000;'>
                                Class added successfully
                            </p>

                            <script>
                                setTimeout(function(){
                                    var el = document.querySelector('.alert-success');
                                    if(el){ el.style.display = 'none'; }
                                }, 3000);
                            </script>
                            ";
                            }
                        }
                    }
                    ?>

                </div>
            </div>

            <!-- ================= RIGHT: CLASS LIST TABLE ================= -->
            <div class="col-md-8 col-sm-12">
                <div class="table-responsive">

                    <h3>Existing Classes</h3>

                    <table class="table table-bordered table-striped">
                        <thead class="table-primary">
                            <tr>
                                <th>ID</th>
                                <th>Class Name</th>
                                <th>Section</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php
                            $res = $conn->query(
                                "SELECT * FROM classes ORDER BY class_name, section"
                            );

                            if ($res->num_rows > 0) {
                                while ($c = $res->fetch_assoc()) {
                                    echo "<tr>
                                        <td>{$c['class_id']}</td>
                                        <td>{$c['class_name']}</td>
                                        <td>{$c['section']}</td>
                                      </tr>";
                                }
                            } else {
                                echo "<tr>
                                    <td colspan='3'>No classes added yet</td>
                                  </tr>";
                            }
                            ?>

                        </tbody>
                    </table>

                </div>
            </div>

        </div>

    </main>

</body>

</html>