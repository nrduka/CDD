<?php
session_start();
$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];

if (!isset($_POST['empno']) || empty($_POST['empno'])) {
    echo "<script>alert('No employees selected.'); window.location.href='create_employee_schedule.php';</script>";
    exit;
}

if (!isset($_POST['startDate'], $_POST['endDate'])) {
    echo "<script>alert('Start and end dates are required.'); window.location.href='create_employee_schedule.php';</script>";
    exit;
}
?>

<?php ob_start(); ?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/libs/sweetalert2/sweetalert2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>
<?php
include 'database-config.php';

$start_date = new DateTime($_POST['startDate']);
$end_date = new DateTime($_POST['endDate']);
$empno = $_POST['empno'];

$options = '';
$shiftOptions = '';

$shift_sql = "SELECT id,shift_name, start_time, end_time FROM shift_templates ORDER BY id ASC";
$shift_result = $conn->query($shift_sql);
while ($shift_row = $shift_result->fetch_assoc()) {

    $shiftOptions .= '<option value="' . htmlspecialchars($shift_row['id']) . '">' .
    strtoupper($shift_row["shift_name"]) . ' (' .
    date("h:i a", strtotime($shift_row["start_time"])) . ' - ' .
    date("h:i a", strtotime($shift_row["end_time"])) . ')</option>';
    
}

$existingSchedules = [];
foreach ($empno as $emp) {
    $empEscaped = $conn->real_escape_string($emp);

    $emp_result = $conn->query("SELECT * FROM manpoweremployee WHERE empno='$empEscaped'");
    while ($row = $emp_result->fetch_assoc()) {
        $options .= '<option value="' . htmlspecialchars($row['empno']) . '" selected>' .
            htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) . '</option>';
    }

    $sched_result = $conn->query("SELECT date_duty, firstName, lastName FROM scheduling
        LEFT JOIN manpoweremployee ON manpoweremployee.empno = scheduling.empno
        WHERE scheduling.empno = '$empEscaped'
        AND date_duty BETWEEN '" . $start_date->format('Y-m-d') . "' AND '" . $end_date->format('Y-m-d') . "'");
    while ($sched = $sched_result->fetch_assoc()) {
        $existingSchedules[$sched['date_duty']][] = $sched['firstName'] . ' ' . $sched['lastName'];
    }
}

$date_interval = new DateInterval('P1D');
$date_period = new DatePeriod($start_date, $date_interval, $end_date->modify('+1 day'));
?>

<form action="set_employee_schedule_submit.php" method="POST">
    <div class="container mt-4">
        <h3>Set Schedule</h3>

        <label>Selected Employees</label>
        <select class="form-control js-example-basic-multiple" name="empno[]" multiple>
            <?= $options ?>
        </select>

        <hr>

        <table class="table table-bordered mt-4">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Schedule</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($date_period as $date) : ?>
                    <tr>
                        <td>
                            <?= $date->format('Y-m-d') ?>
                            <input type="hidden" name="date[]" value="<?= $date->format('Y-m-d') ?>">
                        </td>
                        <td>
                            <select name="schedule[]" class="form-control" required>
                                <option value="">Select Shift</option>
                                <?= $shiftOptions ?>
                            </select>
                        </td>
                        <td>
                            <?php if (isset($existingSchedules[$date->format('Y-m-d')])) : ?>
                                <span class="text-danger">Existing for: <?= implode(', ', $existingSchedules[$date->format('Y-m-d')]) ?></span>
                            <?php else : ?>
                                <span class="text-success">No existing schedule</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <button type="submit" class="btn btn-primary mt-3">Save Schedule</button>
    </div>
</form>

<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $('.js-example-basic-multiple').select2();
</script>
<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
