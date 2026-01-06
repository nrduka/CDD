<!-- Base URL setup -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php
session_start();

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartments = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$allowedRoles = ['ADMIN', 'REGISTRATION', 'COUNTER', 'DEPARTMENT', 'CASHIER'];
if (!in_array($role, $allowedRoles, true) && $assignedDepartments === '') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db2.php';

$reportDateInput = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$reportDate = $reportDateInput !== '' ? $reportDateInput : date('Y-m-d');
$reportDateObject = DateTime::createFromFormat('Y-m-d', $reportDate);
if (!$reportDateObject || $reportDateObject->format('Y-m-d') !== $reportDate) {
    $reportDate = date('Y-m-d');
}

$pageError = null;
$summary = [
    'generated_at' => date('Y-m-d H:i:s'),
    'report_date' => $reportDate,
    'departments' => [],
    'totals' => [
        'waiting' => 0,
        'called' => 0,
        'done' => 0
    ]
];

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $summary['departments'] = fetchDepartmentQueueSummary($mysqli, $reportDate);
    foreach ($summary['departments'] as $dept) {
        $summary['totals']['waiting'] += $dept['waiting_count'];
        $summary['totals']['called'] += $dept['called_count'];
        $summary['totals']['done'] += $dept['done_count'];
    }
}

function fetchDepartmentQueueSummary($mysqli, $reportDate)
{
    $results = [];
$sql = "SELECT d.id,
               d.department,
               SUM(CASE WHEN daily.queue_date IS NOT NULL AND dq.status = 'WAITING' THEN 1 ELSE 0 END) AS waiting_count,
               SUM(CASE WHEN daily.queue_date IS NOT NULL AND dq.status = 'CALLED' THEN 1 ELSE 0 END) AS called_count,
               SUM(CASE WHEN daily.queue_date IS NOT NULL AND dq.status = 'DONE' THEN 1 ELSE 0 END) AS done_count
        FROM department d
        LEFT JOIN department_queue dq
            ON dq.department_id = d.id
        LEFT JOIN queue_calls qc ON qc.id = dq.queue_call_id
        LEFT JOIN daily_queue daily
            ON daily.id = qc.queue_id
           AND daily.queue_date = ?
        GROUP BY d.id, d.department
        ORDER BY d.department ASC";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $results[] = [
                    'department_id' => isset($row['id']) ? (int)$row['id'] : 0,
                    'department_name' => $row['department'],
                    'waiting_count' => isset($row['waiting_count']) ? (int)$row['waiting_count'] : 0,
                    'called_count' => isset($row['called_count']) ? (int)$row['called_count'] : 0,
                    'done_count' => isset($row['done_count']) ? (int)$row['done_count'] : 0
                ];
            }
            $result->free();
        }
        $stmt->close();
    }
    return $results;
}

 $reportDateLabel = date('F d, Y', strtotime($summary['report_date']));

ob_start();
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<style>
    .report-card {
        border-radius: 0.75rem;
        border: 1px solid rgba(226, 232, 240, 0.9);
        padding: 1.5rem;
        background-color: #fff;
        height: 100%;
    }
    .report-card .label {
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.75rem;
        color: #94a3b8;
    }
    .report-card h4 {
        font-size: 2.5rem;
        margin: 0;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col">
            <h3 class="mb-1">Department Queue Summary</h3>
            <p class="text-muted mb-0">Status of routed customers per department for <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
        </div>
        <div class="col-lg-5 col-xl-4 mt-3 mt-lg-0">
            <form class="row g-2 align-items-end" method="get" novalidate>
                <div class="col-12 col-sm-7">
                    <label for="report-date" class="form-label text-muted small mb-1">Report Date</label>
                    <input type="date" id="report-date" name="date" class="form-control" value="<?php echo htmlspecialchars($summary['report_date']); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">View</button>
                </div>
                <div class="col-auto">
                    <a class="btn btn-outline-secondary" href="print_department_queue_summary.php?date=<?php echo urlencode($summary['report_date']); ?>">
                        Export Excel
                    </a>
                </div>
                <div class="col-12 text-end">
                    <span class="text-muted small">Generated: <?php echo htmlspecialchars($summary['generated_at']); ?></span>
                </div>
            </form>
        </div>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($pageError); ?></div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">Queued (Waiting)</span>
                    <h4><?php echo number_format($summary['totals']['waiting']); ?></h4>
                    <p class="text-muted mb-0">Customers waiting to be called on <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">Calling</span>
                    <h4><?php echo number_format($summary['totals']['called']); ?></h4>
                    <p class="text-muted mb-0">Customers currently being served on <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">Completed</span>
                    <h4><?php echo number_format($summary['totals']['done']); ?></h4>
                    <p class="text-muted mb-0">Customers marked done on <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Per Department Detail &mdash; <?php echo htmlspecialchars($reportDateLabel); ?></h5>
                <span class="text-muted small"><?php echo count($summary['departments']); ?> department(s)</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="department-summary-table" class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Waiting</th>
                                <th>Calling</th>
                                <th>Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summary['departments'] as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                                    <td><?php echo number_format($dept['waiting_count']); ?></td>
                                    <td><?php echo number_format($dept['called_count']); ?></td>
                                    <td><?php echo number_format($dept['done_count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();

$scriptsBuffer = function () {
    ob_start();
    ?>
    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var table = document.getElementById('department-summary-table');
            if (!table || !window.jQuery || !jQuery.fn.DataTable) { return; }
            jQuery(table).DataTable({
                pageLength: 10,
                lengthChange: false,
                ordering: false,
                language: {
                    search: 'Filter:',
                    emptyTable: 'No department records available.',
                    info: 'Showing _START_ to _END_ of _TOTAL_ departments',
                    infoEmpty: 'Showing 0 to 0 of 0 departments',
                    paginate: { previous: 'Previous', next: 'Next' }
                }
            });
        });
    </script>
    <?php
    return ob_get_clean();
};

$scripts = $scriptsBuffer();

include 'layouts/base.php';
