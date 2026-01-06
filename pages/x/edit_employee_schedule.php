<?php
$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') 
    . $_SERVER['HTTP_HOST'] 
    . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

session_start();
$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];
?>

<?php ob_start(); ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/libs/prismjs/themes/prism-coy.min.css">
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-md-flex d-block align-items-center justify-content-between mb-4 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Edit Employee Schedule</h1>
    </div>

    <?php
    include 'database-config.php';

    $shifts_result = $conn->query("SELECT shift_name FROM shift_templates");
    $shifts = array();
    while ($shift = $shifts_result->fetch_assoc()) {
        $shifts[] = $shift['shift_name'];
    }

    $empno = isset($_GET['empno']) ? $_GET['empno'] : '';
    $startdate = isset($_GET['startdate']) ? $_GET['startdate'] : '';
    $enddate = isset($_GET['enddate']) ? $_GET['enddate'] : '';
    $firstName = isset($_GET['firstName']) ? $_GET['firstName'] : '';
    $lastName = isset($_GET['lastName']) ? $_GET['lastName'] : '';

    $sql = "SELECT scheduling.empno as empno, firstName, middleName, lastName, date_duty, shiftid, start_time, end_time, shift_templates.shift_name
            FROM scheduling
            LEFT JOIN manpoweremployee ON manpoweremployee.empno = scheduling.empno
            LEFT JOIN shift_templates ON shift_templates.id = scheduling.shiftid
            WHERE date_duty BETWEEN '$startdate' AND '$enddate' AND scheduling.empno='$empno'
            AND manpoweremployee.empstatus='active' and scheduling.status=0
            ORDER BY date_duty ASC";

    $result = $conn->query($sql);

    $shiftTimesJS = '';
    $shift_result = $conn->query("SELECT shift_name, start_time, end_time FROM shift_templates");
    while ($shift = $shift_result->fetch_assoc()) {
        $name = $shift['shift_name'];
        $start = date("h:i a", strtotime($shift['start_time']));
        $end = date("h:i a", strtotime($shift['end_time']));
        $shiftTimesJS .= '"' . $name . '": {start: "' . $start . '", end: "' . $end . '"},' . "\n";
    }
    ?>

    <script>
    var shiftTimes = {
        <?php echo $shiftTimesJS; ?>
    };
    </script>

    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">
                        <?php echo $firstName . " " . $lastName . " (" . $empno . ")"; ?>
                    </div>
                </div>
                <div class="card-body">
                    <form action="edit_employee_schedule_submit.php" method="POST" novalidate>
                        <div class="table-responsive">
                            <table class="table text-nowrap">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Schedule</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && $result->num_rows > 0): ?>
                                        <?php $count = 1; ?>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $count++; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($row['date_duty']); ?>
                                                    <input type="hidden" name="date_duty[]" value="<?php echo $row['date_duty']; ?>">
                                                    <input type="hidden" name="empno[]" value="<?php echo $row['empno']; ?>">
                                                </td>
                                                <td id="time-<?php echo $row['empno'] . '-' . $row['date_duty']; ?>">
                                                    <?php echo date("h:i a", strtotime($row["start_time"])) . ' - ' . date("h:i a", strtotime($row["end_time"])); ?>
                                                </td>
                                                <td>
                                                    <select class="form-select form-select-sm" name="shiftid[]" data-empno="<?php echo $row['empno']; ?>" data-date="<?php echo $row['date_duty']; ?>">
                                                        <?php
                                                        $current_shift = isset($row['shift_name']) ? $row['shift_name'] : '';
                                                        foreach ($shifts as $shift) {
                                                            $selected = ($shift == $current_shift) ? 'selected' : '';
                                                            echo '<option value="' . htmlspecialchars($shift) . '" ' . $selected . '>' . htmlspecialchars($shift) . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4">No schedule found for this employee.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <button class="btn btn-md btn-primary mt-3" type="submit" name="submit">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var selects = document.getElementsByName('shiftid[]');
        for (var i = 0; i < selects.length; i++) {
            selects[i].onchange = function () {
                var schedule = this.value;
                var empno = this.getAttribute('data-empno');
                var date = this.getAttribute('data-date');
                var cellId = 'time-' + empno + '-' + date;
                var timeCell = document.getElementById(cellId);

                if (shiftTimes[schedule]) {
                    timeCell.textContent = shiftTimes[schedule].start + ' - ' + shiftTimes[schedule].end;
                }
            };
        }
    });
    </script>
<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="<?php echo $baseUrl; ?>/assets/js/datatables.js"></script>
<script src="<?php echo $baseUrl; ?>/assets/libs/prismjs/prism.js"></script>
<script src="<?php echo $baseUrl; ?>/assets/js/prism-custom.js"></script>
<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
