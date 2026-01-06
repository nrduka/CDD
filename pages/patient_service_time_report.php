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
$reportDateLabel = date('F d, Y', strtotime($reportDate));
$generatedAt = date('Y-m-d H:i:s');

$pageError = null;
$reportRows = [];
$summaryStats = [
    'completed' => 0,
    'in_progress' => 0,
    'average_minutes' => 0,
    'report_date' => $reportDate,
    'generated_at' => $generatedAt
];

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $reportRows = fetchPatientServiceTimes($mysqli, $reportDate);
    $durationSum = 0;
    $durationCount = 0;
    foreach ($reportRows as $row) {
        if ($row['service_minutes'] === null) {
            $summaryStats['in_progress']++;
        } else {
            $summaryStats['completed']++;
            $durationSum += $row['service_minutes'];
            $durationCount++;
        }
    }
    if ($durationCount > 0) {
        $summaryStats['average_minutes'] = round($durationSum / $durationCount, 2);
    }
}

function fetchPatientServiceTimes($mysqli, $reportDate)
{
    $data = [];
    $sql = "SELECT qc.id AS call_id,
                   qc.registration_number,
                   qc.called_at,
                   qc.completed_at AS registration_completed,
                   daily.category,
                   daily.queue_number,
                   daily.unique_code,
                   daily.retrieval_code,
                   (SELECT MAX(completed_at) FROM department_queue WHERE queue_call_id = qc.id) AS department_completed,
                   (SELECT MAX(completed_at) FROM registration_queue WHERE queue_call_id = qc.id) AS counter_completed
            FROM queue_calls qc
            INNER JOIN daily_queue daily ON daily.id = qc.queue_id
            WHERE daily.queue_date = ?
            ORDER BY qc.called_at ASC";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
            $ticket = format_ticket_number_value($row['category'], $row['queue_number']);
            $reference = format_queue_reference_label($row['category'], $row['queue_number'], $row['unique_code']);

            $start = !empty($row['called_at']) ? $row['called_at'] : null;
            $completionCandidates = array_filter([
                $row['department_completed'],
                $row['registration_completed'],
                $row['counter_completed']
            ]);
            $finish = !empty($completionCandidates) ? max($completionCandidates) : null;

            $serviceMinutes = null;
            if ($start !== null && $finish !== null) {
                $serviceMinutes = max(0, round((strtotime($finish) - strtotime($start)) / 60, 2));
            }

                $data[] = [
                    'ticket' => $ticket,
                    'reference' => $reference,
                    'registration' => $row['registration_number'],
                    'start_time' => $start,
                    'finish_time' => $finish,
                    'service_minutes' => $serviceMinutes,
                    'status' => $finish === null ? 'In Progress' : 'Completed'
                ];
            }
            $result->free();
        }
        $stmt->close();
    }
    return $data;
}

function format_ticket_number_value($category, $queueNumber)
{
    $category = strtoupper(trim((string)$category));
    $number = (int)$queueNumber;
    return sprintf('%s%02d', $category !== '' ? $category : 'Q', $number);
}

function format_queue_reference_label($category, $queueNumber, $uniqueCode)
{
    $ticket = format_ticket_number_value($category, $queueNumber);
    $code = trim((string)$uniqueCode);
    return $code !== '' ? $ticket . ' (' . $code . ')' : $ticket;
}

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
        <div class="col-lg-6">
            <h3 class="mb-1">Patient Service Time Report</h3>
            <p class="text-muted mb-0">Tracks service duration for every ticket served on <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
            <form class="row g-2 align-items-end justify-content-end" method="get" novalidate>
                <div class="col-sm-7 col-md-6">
                    <label for="report-date" class="form-label text-muted small mb-1">Report Date</label>
                    <input type="date" id="report-date" name="date" class="form-control" value="<?php echo htmlspecialchars($summaryStats['report_date']); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">View</button>
                </div>
                <div class="col-auto">
                    <a class="btn btn-outline-secondary" href="print_patient_service_time_report.php?date=<?php echo urlencode($summaryStats['report_date']); ?>">Export Excel</a>
                </div>
                <div class="col-12 text-end">
                    <span class="text-muted small">Generated: <?php echo htmlspecialchars($summaryStats['generated_at']); ?></span>
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
                    <span class="label">Completed Patients</span>
                    <h4><?php echo number_format($summaryStats['completed']); ?></h4>
                    <p class="text-muted mb-0">Finished all assigned services on <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">In Progress</span>
                    <h4><?php echo number_format($summaryStats['in_progress']); ?></h4>
                    <p class="text-muted mb-0">Still undergoing services as of <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">Average Duration (mins)</span>
                    <h4><?php echo number_format($summaryStats['average_minutes'], 2); ?></h4>
                    <p class="text-muted mb-0">Among completed patients for the day.</p>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Service Time Detail &mdash; <?php echo htmlspecialchars($reportDateLabel); ?></h5>
                <span class="text-muted small"><?php echo count($reportRows); ?> record(s)</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="patient-service-table" class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Registration</th>
                                <th>First Call</th>
                                <th>Completed At</th>
                                <th>Status</th>
                                <th>Service Time (mins)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportRows as $row): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($row['ticket']); ?></div>
                                        <span class="text-muted small"><?php echo htmlspecialchars($row['reference']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['registration'] ?: 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($row['start_time'] ?: '—'); ?></td>
                                    <td><?php echo htmlspecialchars($row['finish_time'] ?: '—'); ?></td>
                                    <td>
                                        <?php if ($row['status'] === 'Completed'): ?>
                                            <span class="badge bg-success-subtle text-success">Completed</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning">In Progress</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['service_minutes'] !== null ? number_format($row['service_minutes'], 2) : '—'; ?></td>
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
            var table = document.getElementById('patient-service-table');
            if (!table || !window.jQuery || !jQuery.fn.DataTable) { return; }
            jQuery(table).DataTable({
                pageLength: 10,
                lengthChange: false,
                ordering: false,
                language: {
                    search: 'Filter:',
                    emptyTable: 'No patient records available.',
                    info: 'Showing _START_ to _END_ of _TOTAL_ patients',
                    infoEmpty: 'Showing 0 to 0 of 0 patients',
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
