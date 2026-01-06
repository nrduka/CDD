<?php
// Start session and initialize variables
session_start();
$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];

// Generate base URL
$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') .
           $_SERVER['HTTP_HOST'] .
           str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<!-- Styles -->
<?php ob_start(); ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<?php $styles = ob_get_clean(); ?>

<!-- Page Content -->
<?php ob_start(); ?>
<div class="container-fluid">

    <div class="d-md-flex d-block align-items-center justify-content-between mb-4 mt-2 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">View Schedule</h1>
    </div>

    <!-- Search Form -->
    <form method="post" action="view_schedule.php" class="form-inline" id="payperiod">
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card mb-3">
                    <div class="card-body">
                        <div class="row gy-4 mb-3 align-bottom">
                            <div class="col-xl-2">
                                <p class="text-dark fw-semibold">Start Date</p>
                                <input type="date" class="form-control" name="startDate" required
                                    value="<?= isset($_POST['startDate']) ? htmlspecialchars($_POST['startDate']) : '' ?>">
                            </div>
                            <div class="col-xl-2">
                                <p class="text-dark fw-semibold">End Date</p>
                                <input type="date" class="form-control" name="endDate" required
                                    value="<?= isset($_POST['endDate']) ? htmlspecialchars($_POST['endDate']) : '' ?>">
                            </div>
                            <div class="col-xl-1 d-flex flex-column">
                                <button type="submit" name="Submit" class="btn btn-primary mt-auto">Search</button>
                            </div>
                        </div>
                    </div>    
                </div>
            </div>
        </div>
    </form>

    <!-- Schedule Table -->
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">Employees</div>
                    <div>
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
                                    <th>Department</th>
                                    <th>Date Range</th>
                                    <th>Total Worked Days</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tfoot>
                                <tr>
                                    <th>Employee No</th>
                                    <th>Full Name</th>
                                    <th>Department</th>
                                    <th>Date Range</th>
                                    <th>Total Worked Days</th>
                                    <th>Action</th>
                                </tr>
                            </tfoot>
                            <tbody>
                                <?php
                                if (isset($_POST['startDate'], $_POST['endDate'])):
                                    include 'database-config.php';

                                    $start_date = $_POST['startDate'];
                                    $end_date = $_POST['endDate'];

                                    $formatted_start_date = date("F j", strtotime($start_date));
                                    $formatted_end_date = date("F j, Y", strtotime($end_date));
                                    $date_range = "$formatted_start_date - $formatted_end_date";

                                    $query = "
                                        SELECT 
                                            s.empno, 
                                            me.firstName, 
                                            me.lastName, 
                                            me.department, 
                                            SUM(CASE WHEN s.status = 0 THEN 1 ELSE 0 END) AS active_sched_count
                                        FROM scheduling s
                                        LEFT JOIN manpoweremployee me ON me.empno = s.empno
                                        WHERE s.date_duty BETWEEN ? AND ?
                                        AND me.empstatus = 'active'
                                        GROUP BY s.empno, me.firstName, me.lastName, me.department
                                        ORDER BY me.lastName ASC
                                    ";

                                    $stmt = $conn->prepare($query);
                                    $stmt->bind_param("ss", $start_date, $end_date);
                                    $stmt->execute();
                                    $result = $stmt->get_result();

                                    while ($row = $result->fetch_assoc()):
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['empno']) ?></td>
                                        <td><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?></td>
                                        <td><?= htmlspecialchars($row['department']) ?></td>
                                        <td><?= $date_range ?></td>
                                        <td><?= htmlspecialchars($row['active_sched_count']) ?></td>
                                        <td>
                                            <a href="edit_employee_schedule.php?empno=<?= urlencode($row['empno']) ?>&firstName=<?= urlencode($row['firstName']) ?>&lastName=<?= urlencode($row['lastName']) ?>&startdate=<?= urlencode($start_date) ?>&enddate=<?= urlencode($end_date) ?>" class="btn btn-info" title="Edit"><i class="ri-edit-line"></i></a>
                                            <a target="_blank" href="print_employee_schedule.php?empno=<?= urlencode($row['empno']) ?>&startdate=<?= urlencode($start_date) ?>&enddate=<?= urlencode($end_date) ?>&no_of_sched=<?= $row['active_sched_count'] ?>" class="btn btn-success" title="Print"><i class="ri-printer-fill"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($start_date) && !empty($end_date)): ?>
        <a href="print_all_employee_schedule.php?startdate=<?= urlencode($start_date) ?>&enddate=<?= urlencode($end_date) ?>" target="_blank" class="btn btn-primary mb-3">Print All Schedule</a>
    <?php endif; ?>

</div>

<!-- DataTable Initialization -->
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
<?php $content = ob_get_clean(); ?>

<!-- Scripts -->
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
<script src="<?= $baseUrl ?>/assets/js/datatables.js"></script>
<?php $scripts = ob_get_clean(); ?>

<!-- Base Layout -->
<?php 
    include 'layouts/base.php'; 
    include 'process_payroll_settings.php'; 
?>
