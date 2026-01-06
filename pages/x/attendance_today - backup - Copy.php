<!-- Generate the base URL for the website -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . 
               $_SERVER['HTTP_HOST'] . 
               str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<!-- Start session and assign user details -->
<?php 
    session_start();
    $role = $_SESSION['sess_userrole'];
    $fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];
?>

<!-- Internal Styles -->
<?php ob_start(); ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<?php $styles = ob_get_clean(); ?>

<!-- Page Content -->
<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-md-flex d-block align-items-center justify-content-between mb-4 mt-2 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Today's Attendance</h1>
    </div>

    <!-- Employee Table -->
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">Employees</div>
                    <div class="prism-toggle">
                        <a onclick="window.location.reload(); return false;" class="btn btn-info">Refresh</a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="datatable-basic" class="table table-bordered text-nowrap w-100 table-striped">
                            <thead>
                                <tr>
                                    <th>Employee No</th>
                                    <th>Full Name</th>
                                    <th>Date</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Lunch Start</th>
                                    <th>Lunch End</th>
                                    <th>Break Start</th>
                                    <th>Break End</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                include 'dbconnection.php';

                                $query = "
                                SELECT
                                  m.empno,
                                  m.lastName,
                                  m.firstName,
                                  l.log_date,
                                  l.time_in,
                                  l.time_out,
                                  l.lunch_start,
                                  l.lunch_end,
                                  l.break_start,
                                  l.break_end
                                FROM
                                  manpoweremployee AS m
                                  JOIN (
                                    SELECT
                                      empno,
                                      log_date,
                                      MIN(CASE WHEN log_type = 'TIME IN'   THEN log_time END) AS time_in,
                                      MIN(CASE WHEN log_type = 'TIME OUT'  THEN log_time END) AS time_out,
                                      MIN(CASE WHEN log_type = 'LUNCH START'  THEN log_time END) AS lunch_start,
                                      MIN(CASE WHEN log_type = 'LUNCH END' THEN log_time END) AS lunch_end,
                                      MIN(CASE WHEN log_type = 'BREAK START'  THEN log_time END) AS break_start,
                                      MIN(CASE WHEN log_type = 'BREAK END' THEN log_time END) AS break_end
                                    FROM
                                      employee_logs
                                    WHERE log_date = CURDATE()
                                    GROUP BY empno, log_date
                                  ) AS l ON l.empno = m.empno
                                WHERE m.empstatus = 'active'
                                ORDER BY m.lastName, l.log_date";

                                $result = mysql_query($query, $link) or die(mysql_error());

                                while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td>{$row['empno']}</td>";
                                    echo "<td>{$row['firstName']} {$row['lastName']}</td>";
                                    echo "<td>{$row['log_date']}</td>";
                                    echo "<td>{$row['time_in']}</td>";
                                    echo "<td>{$row['time_out']}</td>";
                                    echo "<td>{$row['lunch_start']}</td>";
                                    echo "<td>{$row['lunch_end']}</td>";
                                    echo "<td>{$row['break_start']}</td>";
                                    echo "<td>{$row['break_end']}</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- DataTable Initialization -->
<script>
    $(document).ready(function() {
        $('#datatable-basic').DataTable({
            scrollX: true,
            responsive: true,
            paging: true,
            searching: true,
            info: true,
            autoWidth: false
        });
    });
</script>
<?php $content = ob_get_clean(); ?>

<!-- Internal Scripts -->
<?php ob_start(); ?>
    <script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/js/datatables.js"></script>
<?php $scripts = ob_get_clean(); ?>

<!-- Include base layout and logic -->
<?php 
    include 'layouts/base.php'; 
    include 'process_payroll_settings.php'; 
?>
