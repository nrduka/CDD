<?php
session_start();
$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];

$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php ob_start(); ?>
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php
include 'dbconnection.php'; // should define $link

$query = "
SELECT
    m.empno,
    m.firstName,
    m.lastName,
    l.log_date,
    l.time_in,
    l.time_out,
    l.lunch_start,
    l.lunch_end,
    l.break_start,
    l.break_end
FROM manpoweremployee AS m
LEFT JOIN (
    SELECT
        empno,
        DATE(log_date) AS log_date,
        MIN(CASE WHEN log_type = 'TIME IN' THEN log_time END) AS time_in,
        MIN(CASE WHEN log_type = 'TIME OUT' THEN log_time END) AS time_out,
        MIN(CASE WHEN log_type = 'LUNCH START' THEN log_time END) AS lunch_start,
        MIN(CASE WHEN log_type = 'LUNCH END' THEN log_time END) AS lunch_end,
        MIN(CASE WHEN log_type = 'BREAK START' THEN log_time END) AS break_start,
        MIN(CASE WHEN log_type = 'BREAK END' THEN log_time END) AS break_end
    FROM employee_logs
    WHERE DATE(log_date) = CURDATE()
    GROUP BY empno, DATE(log_date)
) AS l ON l.empno = m.empno
WHERE m.empstatus = 'active'
ORDER BY m.lastName ASC";

$result = mysql_query($query, $link) or die(mysql_error());
?>
<div class="container-fluid">
    <div class="d-md-flex d-block align-items-center justify-content-between mb-4 mt-2 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Today's Attendance</h1>
    </div>

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
                                <?php while ($row = mysql_fetch_array($result, MYSQL_ASSOC)): ?>
                                    <tr>
                                        <td><?= $row['empno'] ?></td>
                                        <td><?= $row['firstName'] . ' ' . $row['lastName'] ?></td>
                                        <td><?= $row['log_date'] ?></td>
                                        <td><?= $row['time_in'] ?></td>
                                        <td><?= $row['time_out'] ?></td>
                                        <td><?= $row['lunch_start'] ?></td>
                                        <td><?= $row['lunch_end'] ?></td>
                                        <td><?= $row['break_start'] ?></td>
                                        <td><?= $row['break_end'] ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
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

<style>
::-webkit-scrollbar {
    height: 10px;
    background-color: #f0f0f0;
    border-radius: 10px;
}
::-webkit-scrollbar-thumb {
    background-color: #888;
    border-radius: 10px;
    border: 1px solid #f0f0f0;
}
::-webkit-scrollbar-thumb:hover {
    background-color: #555;
}
.dataTables_wrapper:hover ::-webkit-scrollbar-thumb {
    background-color: #888;
}
.dataTables_wrapper .dataTables_scrollBody:hover::-webkit-scrollbar-thumb:hover {
    background-color: #555;
}
.dataTables_wrapper .dataTables_scrollBody {
    padding-bottom: 1vw;
    overflow-x: scroll;
    margin-bottom: 1vw;
}
</style>
<?php $content = ob_get_clean(); ?>

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

<?php include 'layouts/base.php'; ?>
