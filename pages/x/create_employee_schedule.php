<?php
session_start();

// Base URL
$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));

$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . ' ' . $_SESSION['sess_lastname'];
?>

<?php ob_start(); ?>

<!-- CSS Includes -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/libs/prismjs/themes/prism-coy.min.css">

<?php $styles = ob_get_clean(); ?>
<?php ob_start(); ?>

<div class="container-fluid">
    <form action="set_employee_schedule.php" method="POST" onsubmit="return validateForm(event)">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="page-title fw-semibold fs-18 mb-0">Create Employee Schedule</h1>
            <button type="submit" class="btn btn-success">Set Schedule</button>
        </div>

        <?php
        include 'database-config.php';
        $sql = "SELECT * FROM manpoweremployee WHERE empstatus='active' ORDER BY lastName ASC";
        $result = $conn->query($sql);
        ?>

        <div class="row">
            <div class="col-xl-3">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">Select Period</div>
                    </div>
                    <div class="card-body">
                        <label>Start Date</label>
                        <input type="date" class="form-control" name="startDate" required>
                        <label class="mt-3">End Date</label>
                        <input type="date" class="form-control" name="endDate" required>
                    </div>
                </div>
            </div>

            <div class="col-xl-9">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">Select Employees</div>
                        <label><input type="checkbox" id="selectAll" onclick="toggleAll(this)"> Select All</label>
                    </div>
                    <div class="card-body">
                        <table id="datatable-basic" class="table table-bordered text-nowrap w-100">
                            <thead>
                                <tr>
                                    <th>Select</th>
                                    <th>Empno</th>
                                    <th>Full Name</th>
                                    <th>Department</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()) : ?>
                                    <tr>
                                        <td onclick="toggleCheckbox(this)">
                                            <input name="empno[]" type="checkbox" value="<?php echo $row['empno']; ?>" class="employee-checkbox" onclick="event.stopPropagation();">
                                        </td>
                                        <td><?php echo htmlspecialchars($row['empno']); ?></td>
                                        <td><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function toggleCheckbox(td) {
    const checkbox = td.querySelector('input[type="checkbox"]');
    if (checkbox) checkbox.checked = !checkbox.checked;
}

function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.employee-checkbox');
    checkboxes.forEach(checkbox => checkbox.checked = source.checked);
}

function validateForm(event) {
    const startDate = document.querySelector('input[name="startDate"]').value;
    const endDate = document.querySelector('input[name="endDate"]').value;
    const selectedEmployees = document.querySelectorAll('input[name="empno[]"]:checked');

    if (!startDate || !endDate) {
        alert('Please select both start and end dates.');
        event.preventDefault();
        return false;
    }

    if (selectedEmployees.length === 0) {
        alert('Please select at least one employee.');
        event.preventDefault();
        return false;
    }

    return true;
}
</script>

<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="<?php echo $baseUrl; ?>/assets/js/datatables.js"></script>
<script src="<?php echo $baseUrl; ?>/assets/libs/prismjs/prism.js"></script>
<script src="<?php echo $baseUrl; ?>/assets/js/prism-custom.js"></script>
<?php $scripts = ob_get_clean(); ?>

<?php include 'layouts/base.php'; ?>
