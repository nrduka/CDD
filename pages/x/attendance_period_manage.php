<?php
session_start();
$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
         . $_SERVER['HTTP_HOST']
         . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

// Include old-style MySQL connection
include 'dbconnection.php';

// Set default timezone
date_default_timezone_set('Asia/Manila');

// Get empno
$empno = isset($_GET['empno']) ? $_GET['empno'] : '';
$employee_fullname = 'Employee Not Found';

// Fetch employee full name
if ($empno != '') {
    $emp_query = "SELECT firstName, lastName FROM manpoweremployee WHERE empno = '$empno'";
    $emp_result = mysql_query($emp_query, $link) or die(mysql_error());
    if ($row = mysql_fetch_assoc($emp_result)) {
        $employee_fullname = $row['firstName'] . ' ' . $row['lastName'];
    }
}
?>

<?php ob_start(); ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<style>
.dt-buttons {
    margin-bottom: 10px;
}
</style>
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-md-flex d-block align-items-center justify-content-between mb-4 mt-2 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Manage Attendance</h1>
    </div>

    <form class="form-inline" method="post" action="attendance_period_manage.php?empno=<?php echo urlencode($empno); ?>" id="attendance-form">
        <div class="row mb-3">
            <div class="col-md-3">
                <label for="startDate" class="fw-semibold">Start Date</label>
                <input type="date" class="form-control" id="startDate" name="startDate" required
                    value="<?php echo isset($_POST['startDate']) ? $_POST['startDate'] : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="fw-semibold">End Date</label>
                <input type="date" class="form-control" id="endDate" name="endDate" required
                    value="<?php echo isset($_POST['endDate']) ? $_POST['endDate'] : ''; ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit" name="Submit">Search</button>
            </div>
        </div>
    </form>

    <?php if (isset($_POST['startDate']) && isset($_POST['endDate'])): ?>
        <div class="card custom-card">
            <div class="card-header">
                <div class="card-title">
                    Attendance for <strong class="text-primary"><?php echo htmlspecialchars($employee_fullname); ?> (<?php echo htmlspecialchars($empno); ?>)</strong>
                </div>
            </div>
            <div class="card-body table-responsive">
                <table id="datatable-basic" class="table table-bordered text-nowrap w-100 table-striped">
                    <thead>
                        <tr>
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
                        $start_date = mysql_real_escape_string($_POST['startDate']);
                        $end_date = mysql_real_escape_string($_POST['endDate']);
                        $query = "
                            SELECT 
                                e.empno,
                                e.firstName,
                                e.lastName,
                                DATE(l.log_date) AS log_date,
                                MIN(CASE WHEN l.log_type = 'TIME IN' THEN l.log_time END) AS time_in,
                                MIN(CASE WHEN l.log_type = 'TIME OUT' THEN l.log_time END) AS time_out,
                                MIN(CASE WHEN l.log_type = 'LUNCH START' THEN l.log_time END) AS lunch_start,
                                MIN(CASE WHEN l.log_type = 'LUNCH END' THEN l.log_time END) AS lunch_end,
                                MIN(CASE WHEN l.log_type = 'BREAK START' THEN l.log_time END) AS break_start,
                                MIN(CASE WHEN l.log_type = 'BREAK END' THEN l.log_time END) AS break_end
                            FROM manpoweremployee e
                            JOIN employee_logs l ON e.empno = l.empno
                            WHERE e.empno = '$empno' AND DATE(l.log_date) BETWEEN '$start_date' AND '$end_date'
                            GROUP BY e.empno, e.firstName, e.lastName, DATE(l.log_date)
                            ORDER BY l.log_date ASC
                        ";

                        $result = mysql_query($query, $link) or die(mysql_error());
                        while ($row = mysql_fetch_assoc($result)):
                        ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($row['log_date'])); ?></td>
                                <td><?php echo $row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-'; ?></td>
                                <td><?php echo $row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-'; ?></td>
                                <td><?php echo $row['lunch_start'] ? date('h:i A', strtotime($row['lunch_start'])) : '-'; ?></td>
                                <td><?php echo $row['lunch_end'] ? date('h:i A', strtotime($row['lunch_end'])) : '-'; ?></td>
                                <td><?php echo $row['break_start'] ? date('h:i A', strtotime($row['break_start'])) : '-'; ?></td>
                                <td><?php echo $row['break_end'] ? date('h:i A', strtotime($row['break_end'])) : '-'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <div class="card-body">
                    <a href="print_attendance_period_by_employee.php?from=<?php echo urlencode($_POST['startDate']); ?>&to=<?php echo urlencode($_POST['endDate']); ?>&empno=<?php echo urlencode($empno); ?>" class="btn btn-success mb-3" target="_blank">Export to Excel</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Pass employee name and empno to JS -->
<script>
    const employeeName = "<?php echo addslashes($employee_fullname); ?>";
    const employeeNo = "<?php echo addslashes($empno); ?>";
</script>

<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>

<!-- Buttons scripts -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
