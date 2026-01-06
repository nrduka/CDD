<!-- Base URL setup -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php
session_start();

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$allowedRoles = ['REGISTRATION', 'COUNTER', 'ADMIN', 'CASHIER', 'DEPARTMENT'];
$assignedDepartments = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
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
    'waiting_total' => 0,
    'waiting_by_category' => [],
    'active_total' => 0,
    'active_list' => [],
    'queue_list' => [],
    'generated_at' => date('Y-m-d H:i:s'),
    'report_date' => $reportDate
];

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $summary['waiting_total'] = fetchWaitingCount($mysqli, $reportDate);
    $summary['waiting_by_category'] = fetchWaitingByCategory($mysqli, $reportDate);
    $activeData = fetchActiveCalls($mysqli, $reportDate);
    $summary['active_total'] = count($activeData);
    $summary['active_list'] = $activeData;
    $summary['queue_list'] = fetchQueueReportRows($mysqli, $reportDate);
}

function fetchWaitingCount($mysqli, $reportDate)
{
    $sql = "SELECT COUNT(*) AS total
            FROM daily_queue dq
            LEFT JOIN queue_calls qc ON qc.queue_id = dq.id AND qc.status = 'ACTIVE'
            LEFT JOIN registration_queue rq ON rq.queue_id = dq.id AND rq.status = 'WAITING'
            WHERE dq.queue_date = ?
              AND qc.id IS NULL
              AND rq.id IS NULL";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute()) {
            $stmt->bind_result($total);
            if ($stmt->fetch()) {
                $stmt->close();
                return (int)$total;
            }
        }
        $stmt->close();
    }
    return 0;
}

function fetchWaitingByCategory($mysqli, $reportDate)
{
    $data = [];
    $sql = "SELECT dq.category, COUNT(*) AS total
            FROM daily_queue dq
            LEFT JOIN queue_calls qc ON qc.queue_id = dq.id AND qc.status = 'ACTIVE'
            LEFT JOIN registration_queue rq ON rq.queue_id = dq.id AND rq.status = 'WAITING'
            WHERE dq.queue_date = ?
              AND qc.id IS NULL
              AND rq.id IS NULL
            GROUP BY dq.category";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $category = isset($row['category']) ? (string)$row['category'] : '';
                $data[] = [
                    'category' => $category,
                    'label' => describe_queue_category($category),
                    'total' => isset($row['total']) ? (int)$row['total'] : 0
                ];
            }
            $result->free();
        }
        $stmt->close();
    }
    return $data;
}

function fetchActiveCalls($mysqli, $reportDate)
{
    $rows = [];
    $sql = "SELECT qc.id,
                   qc.registration_number,
                   qc.called_at,
                   dq.category,
                   dq.queue_number,
                   dq.unique_code
            FROM queue_calls qc
            INNER JOIN daily_queue dq ON dq.id = qc.queue_id
            WHERE qc.status = 'ACTIVE'
              AND dq.queue_date = ?
            ORDER BY qc.called_at DESC";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'call_id' => (int)$row['id'],
                    'ticket' => format_ticket_number_value($row['category'], $row['queue_number']),
                    'reference' => format_queue_reference_label($row['category'], $row['queue_number'], $row['unique_code']),
                    'registration' => $row['registration_number'],
                    'called_at' => $row['called_at']
                ];
            }
            $result->free();
        }
        $stmt->close();
    }
    return $rows;
}

function tableHasColumn($mysqli, $tableName, $columnName)
{
    static $cache = [];
    $cacheKey = strtolower($tableName . '.' . $columnName);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!$mysqli) {
        $cache[$cacheKey] = false;
        return false;
    }

    $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $cache[$cacheKey] = false;
        return false;
    }
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $stmt->store_result();
    $cache[$cacheKey] = $stmt->num_rows > 0;
    $stmt->close();
    return $cache[$cacheKey];
}

function resolvePatientLastNameColumn($mysqli)
{
    static $cached = null;
    if ($cached !== null) {
        return $cached ?: false;
    }

    $candidates = ['lastname', 'last_name'];
    foreach ($candidates as $column) {
        if (tableHasColumn($mysqli, 'patient_info', $column)) {
            $cached = $column;
            return $cached;
        }
    }

    $cached = '';
    return false;
}

function fetchQueueReportRows($mysqli, $reportDate)
{
    $rows = [];
    if (!$mysqli) {
        return $rows;
    }

    $retrievalColumn = '';
    if (tableHasColumn($mysqli, 'daily_queue', 'retrieval_code')) {
        $retrievalColumn = 'retrieval_code';
    }

    $queueRetrievalSelect = $retrievalColumn !== '' ? "dq.`{$retrievalColumn}` AS queue_retrieval_code" : "'' AS queue_retrieval_code";

    $joinPatient = $retrievalColumn !== '' && tableHasColumn($mysqli, 'patient_info', 'retrieval_code');
    $lastNameColumn = $joinPatient ? resolvePatientLastNameColumn($mysqli) : '';
    $lastNameSelect = $lastNameColumn ? "pi.`{$lastNameColumn}` AS patient_lastname" : "'' AS patient_lastname";

    $sql = "SELECT dq.category,
                   dq.queue_number,
                   dq.unique_code,
                   {$queueRetrievalSelect}";
    if ($joinPatient) {
        $sql .= ",
                   pi.firstname AS patient_firstname,
                   {$lastNameSelect},
                   pi.retrieval_code AS patient_retrieval_code";
    }
    $sql .= "
            FROM daily_queue dq";
    if ($joinPatient) {
        $sql .= " LEFT JOIN patient_info pi ON pi.retrieval_code = dq.`{$retrievalColumn}`";
    }
    $sql .= "
            WHERE dq.queue_date = ?
            ORDER BY dq.queue_number ASC, dq.id ASC";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $firstName = $joinPatient && isset($row['patient_firstname']) ? (string)$row['patient_firstname'] : '';
                $lastName = $joinPatient && isset($row['patient_lastname']) ? (string)$row['patient_lastname'] : '';
                $queueRetrieval = isset($row['queue_retrieval_code']) ? (string)$row['queue_retrieval_code'] : '';
                $patientRetrieval = $joinPatient && isset($row['patient_retrieval_code']) ? (string)$row['patient_retrieval_code'] : '';
                $activeRetrieval = $patientRetrieval !== '' ? $patientRetrieval : $queueRetrieval;

                $rows[] = [
                    'ticket' => format_ticket_number_value($row['category'], $row['queue_number']),
                    'category' => isset($row['category']) ? (string)$row['category'] : '',
                    'category_label' => describe_queue_category($row['category']),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'retrieval_code' => $activeRetrieval
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function describe_queue_category($category)
{
    static $map = [
        'P' => 'Priority',
        'R' => 'Regular'
    ];
    $key = strtoupper(trim((string)$category));
    return isset($map[$key]) ? $map[$key] : ($key !== '' ? $key : 'Unassigned');
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
    .report-card h4 {
        font-size: 2.5rem;
        margin: 0;
    }
    .report-card .label {
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.75rem;
        color: #94a3b8;
    }
    .report-table th {
        background-color: #f8fafc;
        font-size: 0.8rem;
        letter-spacing: 0.05em;
        color: #475569;
        text-transform: uppercase;
    }
    .report-table td {
        vertical-align: middle;
    }
    .report-meta {
        font-size: 0.85rem;
        color: #94a3b8;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-3 align-items-center">
        <div class="col-lg-6">
            <h3 class="mb-1">Queue Summary</h3>
            <p class="text-muted mb-0">Snapshot of queue activity for <?php echo htmlspecialchars($reportDateLabel); ?>.</p>
        </div>
        <div class="col-lg-6">
            <form class="row g-2 align-items-end justify-content-end" method="get" novalidate>
                <div class="col-sm-7 col-md-6 col-lg-6">
                    <label for="report-date" class="form-label text-muted small mb-1">Report Date</label>
                    <input type="date" id="report-date" name="date" class="form-control" value="<?php echo htmlspecialchars($summary['report_date']); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">View</button>
                </div>
                <div class="col-auto">
                    <a class="btn btn-outline-secondary" href="print_queue_summary.php?date=<?php echo urlencode($summary['report_date']); ?>">
                        Export Excel
                    </a>
                </div>
                <div class="col-12 text-end">
                    <span class="report-meta">Generated: <?php echo htmlspecialchars($summary['generated_at']); ?></span>
                </div>
            </form>
        </div>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($pageError); ?></div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <div class="col-lg-4 col-md-6">
                <div class="report-card">
                    <span class="label">Waiting Tickets</span>
                    <h4><?php echo number_format($summary['waiting_total']); ?></h4>
                    <p class="text-muted mb-0">Customers waiting to be called.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="report-card">
                    <span class="label">Now Serving</span>
                    <h4><?php echo number_format($summary['active_total']); ?></h4>
                    <p class="text-muted mb-0">Customers currently being served.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="report-card">
                    <span class="label">Waiting Breakdown</span>
                    <?php if (empty($summary['waiting_by_category'])): ?>
                        <p class="text-muted mb-0">No waiting customers.</p>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($summary['waiting_by_category'] as $category): ?>
                                <li class="d-flex justify-content-between">
                                    <span><?php echo htmlspecialchars($category['label']); ?></span>
                                    <strong><?php echo number_format($category['total']); ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card custom-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Queue Details &mdash; <?php echo htmlspecialchars($reportDateLabel); ?></h5>
                <span class="report-meta"><?php echo count($summary['queue_list']) > 0 ? count($summary['queue_list']) . ' record(s)' : 'No records for this date'; ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($summary['queue_list'])): ?>
                    <p class="text-muted mb-0">No queue entries were generated for the selected date.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="queue-report-table" class="table table-striped report-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Queue No.</th>
                                    <th scope="col">First Name</th>
                                    <th scope="col">Last Name</th>
                                    <th scope="col">Retrieval Code</th>
                                    <th scope="col">Customer Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['queue_list'] as $queueRow): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($queueRow['ticket']); ?></td>
                                        <td><?php echo htmlspecialchars($queueRow['first_name'] !== '' ? $queueRow['first_name'] : 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($queueRow['last_name'] !== '' ? $queueRow['last_name'] : 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($queueRow['retrieval_code'] !== '' ? $queueRow['retrieval_code'] : 'N/A'); ?></td>
                                        <td>
                                            <?php
                                                $badgeClass = 'bg-secondary';
                                                if (strtoupper($queueRow['category']) === 'P') {
                                                    $badgeClass = 'bg-danger';
                                                } elseif (strtoupper($queueRow['category']) === 'R') {
                                                    $badgeClass = 'bg-info text-dark';
                                                }
                                            ?>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo htmlspecialchars($queueRow['category_label']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Active Calls Detail</h5>
                <span class="report-meta"><?php echo $summary['active_total'] > 0 ? $summary['active_total'] . ' record(s)' : 'No active calls for this date'; ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($summary['active_list'])): ?>
                    <p class="text-muted mb-0">No active calls were recorded for the selected date.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="queue-summary-table" class="table table-striped report-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Ticket</th>
                                    <th scope="col">Registration</th>
                                    <th scope="col">Last Called</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($summary['active_list'] as $active): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($active['ticket']); ?></div>
                                            <span class="text-muted small"><?php echo htmlspecialchars($active['reference']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($active['registration'] ?: 'Unassigned'); ?></td>
                                        <td><?php echo htmlspecialchars($active['called_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
            if (!window.jQuery || !jQuery.fn.DataTable) {
                return;
            }

            var configs = [
                {
                    selector: '#queue-report-table',
                    options: {
                        pageLength: 10,
                        lengthChange: true,
                        ordering: true,
                        order: [[0, 'asc']],
                        info: true,
                            language: {
                                search: 'Filter:',
                                emptyTable: 'No queue records for this date.',
                                info: 'Showing _START_ to _END_ of _TOTAL_ queue records',
                                infoEmpty: 'Showing 0 to 0 of 0 queue records',
                                paginate: {
                                    previous: 'Previous',
                                    next: 'Next'
                            }
                        }
                    }
                },
                {
                    selector: '#queue-summary-table',
                    options: {
                        pageLength: 10,
                        lengthChange: false,
                        ordering: false,
                        info: true,
                            language: {
                                search: 'Filter:',
                                emptyTable: 'No active calls for this date.',
                                info: 'Showing _START_ to _END_ of _TOTAL_ calls',
                                infoEmpty: 'Showing 0 to 0 of 0 calls',
                                paginate: {
                                    previous: 'Previous',
                                    next: 'Next'
                            }
                        }
                    }
                }
            ];

            configs.forEach(function (item) {
                var table = document.querySelector(item.selector);
                if (table) {
                    jQuery(table).DataTable(item.options);
                }
            });
        });
    </script>
    <?php
    return ob_get_clean();
};

$scripts = $scriptsBuffer();

include 'layouts/base.php';
