<?php
session_start();
$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://')
         . $_SERVER['HTTP_HOST']
         . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

include 'dbconnection.php';
date_default_timezone_set('Asia/Manila');

$empno = isset($_GET['empno']) ? $_GET['empno'] : '';
$employee_fullname = 'Employee Not Found';

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
<style>
tr.approved {
    background-color: #d4edda !important;
    color: #155724;
}
</style>
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="container-fluid">
    <h1 class="page-title fw-semibold fs-18 mb-3 mt-3">Manage Attendance</h1>

    <!-- Alert messages -->
    <?php if (isset($_GET['status']) && $_GET['status'] === 'approved'): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Selected rows have been approved.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php elseif (isset($_GET['status']) && $_GET['status'] === 'empty'): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            No rows were selected.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search form -->
    <form method="post" action="time_sheet_manage.php?empno=<?php echo urlencode($empno); ?>" id="attendance-form">
        <div class="row mb-4">
            <div class="col-md-3">
                <label for="startDate" class="fw-semibold mb-1">Start Date</label>
                <input type="date" class="form-control" id="startDate" name="startDate" required
                    value="<?php echo isset($_POST['startDate']) ? $_POST['startDate'] : ''; ?>">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="fw-semibold mb-1">End Date</label>
                <input type="date" class="form-control" id="endDate" name="endDate" required
                    value="<?php echo isset($_POST['endDate']) ? $_POST['endDate'] : ''; ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit">
                    <i class="fas fa-search me-2"></i>Search
                </button>
            </div>
        </div>
    </form>

    <?php if (isset($_POST['startDate']) && isset($_POST['endDate'])): ?>
        <?php
        $start_date = mysql_real_escape_string($_POST['startDate']);
        $end_date = mysql_real_escape_string($_POST['endDate']);
        $query = "
            SELECT v.*, 
                   CASE 
                       WHEN a.empno IS NOT NULL THEN 1 
                       ELSE 0 
                   END AS is_approved
            FROM v_employee_attendance v
            LEFT JOIN approved_timesheet a 
                ON v.empno = a.empno AND v.log_date = a.log_date
            WHERE v.empno = '$empno' 
              AND v.log_date BETWEEN '$start_date' AND '$end_date'
            ORDER BY v.log_date ASC
        ";
        $result = mysql_query($query, $link) or die(mysql_error());
        if (mysql_num_rows($result) > 0):
        ?>
        <form method="post" action="time_sheet_manage_submit.php">
            <input type="hidden" name="empno" value="<?php echo htmlspecialchars($empno); ?>">
            <div class="card custom-card">
                <div class="card-header">
                    Attendance for <strong><?php echo htmlspecialchars($employee_fullname); ?> (<?php echo htmlspecialchars($empno); ?>)</strong>
                </div>
<!--                 <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover"> -->




 <div class="card-body">
                            <div class="table-responsive">
                                <table id="datatable-basic" class="table table-bordered text-nowrap w-100">


                            <thead class="bg-light">
                                <tr>
                                    <th><input type="checkbox" id="select-all"></th>
                                    <th>Log Date</th>
                                    <th>Logout Date</th>
                                    <th>Shift</th>
                                    <th>Duty Start</th>
                                    <th>Duty End</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Lunch Start</th>
                                    <th>Lunch End</th>
                                    <th>Break Start</th>
                                    <th>Break End</th>
                                    <th>Hours Worked</th>
                                    <th>Hours Lunch</th>
                                    <th>Hours Break</th>
                                    <th>Net Hours</th>
                                    <th>Net Decimal</th>
                                    <th>Regular Hours</th>
                                    <th>Regular Decimal</th>
                                    <th>Tardy Decimal</th>
                                    <th>Overtime Decimal</th>
                                    <th>Night Hours</th>
                                    <th>Night Decimal</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php while ($row = mysql_fetch_assoc($result)):
                                $id = htmlspecialchars($row['log_date']);
                                $approved = $row['is_approved'];
                            ?>
                            <tr class="<?php echo $approved ? 'approved' : ''; ?>">
                                <td>
                                    <?php if ($approved): ?>
                                        <input type="checkbox" checked disabled>
                                    <?php else: ?>
                                        <input type="checkbox" class="row-check" name="selected[]" value="<?php echo $id; ?>">
                                        <?php foreach ($row as $key => $val): ?>
                                            <input type="hidden" name="<?php echo $key; ?>[<?php echo $id; ?>]" value="<?php echo htmlspecialchars($val); ?>">
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['log_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['logout_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['shift_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['duty_start']); ?></td>
                                <td><?php echo htmlspecialchars($row['duty_end']); ?></td>
                                <td><?php echo htmlspecialchars($row['time_in']); ?></td>
                                <td><?php echo htmlspecialchars($row['time_out']); ?></td>
                                <td><?php echo htmlspecialchars($row['lunch_start']); ?></td>
                                <td><?php echo htmlspecialchars($row['lunch_end']); ?></td>
                                <td><?php echo htmlspecialchars($row['break_start']); ?></td>
                                <td><?php echo htmlspecialchars($row['break_end']); ?></td>
         
                                  <td><?php echo htmlspecialchars($row['total_hours']); ?></td>
                              <td><?php echo htmlspecialchars($row['lunch_hours']); ?></td>
                             <td><?php echo htmlspecialchars($row['break_hours']); ?></td>
                                <td><?php echo htmlspecialchars($row['net_hours']); ?></td>
                                <td><?php echo htmlspecialchars($row['net_decimal']); ?></td>
                                <td><?php echo htmlspecialchars($row['regular_hours']); ?></td>
                                <td><?php echo htmlspecialchars($row['regular_decimal']); ?></td>
                                <td><?php echo htmlspecialchars($row['tardiness_decimal']); ?></td>

                                <td style="display:none;"> <?php echo htmlspecialchars($row['shift_type']); ?></td>



                                <td>
                                    <?php if ($approved): ?>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['overtime_decimal']); ?>" disabled>
                                    <?php else: ?>
                                        <input type="text" class="form-control" name="overtime_decimal[<?php echo $id; ?>]" value="<?php echo htmlspecialchars($row['overtime_decimal']); ?>">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['night_hours']); ?></td>
                                <td>
                                    <?php if ($approved): ?>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($row['night_decimal']); ?>" disabled>
                                    <?php else: ?>
                                        <input type="text" class="form-control" name="night_decimal[<?php echo $id; ?>]" value="<?php echo htmlspecialchars($row['night_decimal']); ?>">
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" id="approve-selected" class="btn btn-success" disabled>
                            <i class="fas fa-check me-2"></i>Approve Selected
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php else: ?>
            <div class="alert alert-info mt-4">No attendance records found for the selected date range.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var submitBtn = document.getElementById("approve-selected");
    var checkboxes = document.querySelectorAll(".row-check");
    var selectAll = document.getElementById("select-all");

    function toggleSubmit() {
        const checked = document.querySelectorAll(".row-check:checked").length > 0;
        submitBtn.disabled = !checked;
    }

    checkboxes.forEach(cb => cb.addEventListener("change", toggleSubmit));
    if (selectAll) {
        selectAll.addEventListener("change", function() {
            checkboxes.forEach(cb => {
                if (!cb.disabled) cb.checked = selectAll.checked;
            });
            toggleSubmit();
        });
    }

    toggleSubmit();
});
</script>
<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
<?php include 'process_payroll_settings.php'; ?>
