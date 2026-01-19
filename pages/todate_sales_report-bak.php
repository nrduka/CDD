<!-- Base URL setup -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php
session_start();

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartments = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$allowedRoles = ['ADMIN', 'CASHIER', 'REGISTRATION', 'COUNTER', 'DEPARTMENT'];
if (!in_array($role, $allowedRoles, true) && $assignedDepartments === '') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/db2.php';

$today = date('Y-m-d');
$startInput = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
$endInput = isset($_GET['end']) ? trim((string)$_GET['end']) : '';

$startDate = $startInput !== '' ? $startInput : $today;
$endDate = $endInput !== '' ? $endInput : $today;

$startObject = DateTime::createFromFormat('Y-m-d', $startDate);
if (!$startObject || $startObject->format('Y-m-d') !== $startDate) {
    $startDate = $today;
    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
}

$endObject = DateTime::createFromFormat('Y-m-d', $endDate);
if (!$endObject || $endObject->format('Y-m-d') !== $endDate) {
    $endDate = $today;
    $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
}

if ($startDate > $endDate) {
    $temp = $startDate;
    $startDate = $endDate;
    $endDate = $temp;
    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
}

$generatedAt = date('Y-m-d H:i:s');
$pageError = null;
$salesRows = [];
$departmentTotals = [];
$grandTotal = 0;
$groupedSales = [];

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $salesRows = fetchSalesRange($mysqli, $startDate, $endDate);
    foreach ($salesRows as $row) {
        $departmentKey = $row['department'] !== '' ? $row['department'] : 'Unassigned';
        if (!isset($departmentTotals[$departmentKey])) {
            $departmentTotals[$departmentKey] = 0;
        }
        $departmentTotals[$departmentKey] += $row['amount'];
        $grandTotal += $row['amount'];
        $groupedSales[$departmentKey][] = $row;
    }
    ksort($groupedSales);
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

function detectRetrievalColumn($mysqli)
{
    $candidates = ['retrieval_code'];
    foreach ($candidates as $column) {
        if (tableHasColumn($mysqli, 'daily_sales', $column)) {
            return $column;
        }
    }
    return '';
}

function fetchSalesRange($mysqli, $startDate, $endDate)
{
    $rows = [];
    $retrievalColumn = detectRetrievalColumn($mysqli);
    $retrievalSelect = $retrievalColumn !== '' ? "ds.`{$retrievalColumn}` AS retrieval_code" : "'' AS retrieval_code";

    $sql = "SELECT ds.id,
                   ds.queue_id,
                   ds.item_code,
                   ds.lineitem AS line_item,
                   ds.date_created,
                   ds.amount AS sale_amount,
                   ds.payment_status,
                   ds.date_paid,
                   {$retrievalSelect},
                   pr.department,
                   pr.department_code,
                   pr.item_description,
                   pr.amount AS product_amount
            FROM daily_sales ds
            LEFT JOIN product_register pr ON pr.item_code = ds.item_code
            WHERE DATE(COALESCE(ds.date_paid, ds.date_created)) BETWEEN ? AND ?
            ORDER BY pr.department, ds.date_paid, ds.date_created, ds.id";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $amount = isset($row['sale_amount']) && $row['sale_amount'] !== null
                    ? (float)$row['sale_amount']
                    : (isset($row['product_amount']) ? (float)$row['product_amount'] : 0);

                $saleDate = !empty($row['date_paid']) ? $row['date_paid'] : $row['date_created'];

                $rows[] = [
                    'department' => isset($row['department']) ? (string)$row['department'] : '',
                    'department_code' => isset($row['department_code']) ? (string)$row['department_code'] : '',
                    'retrieval_code' => isset($row['retrieval_code']) ? (string)$row['retrieval_code'] : '',
                    'item_code' => isset($row['item_code']) ? (string)$row['item_code'] : '',
                    'line_item' => isset($row['line_item']) ? (string)$row['line_item'] : '',
                    'item_description' => isset($row['item_description']) ? (string)$row['item_description'] : '',
                    'amount' => $amount,
                    'payment_status' => isset($row['payment_status']) ? (int)$row['payment_status'] : 0,
                    'sale_date' => $saleDate
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function formatPaymentStatus($status)
{
    return (int)$status === 1 ? 'Paid' : 'Unpaid';
}

function formatRangeLabel($startDate, $endDate)
{
    $startLabel = date('F d, Y', strtotime($startDate));
    $endLabel = date('F d, Y', strtotime($endDate));
    return $startLabel === $endLabel ? $startLabel : $startLabel . ' - ' . $endLabel;
}

$reportLabel = formatRangeLabel($startDate, $endDate);
$reportMeta = [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'generated_at' => $generatedAt,
    'grand_total' => $grandTotal,
    'records' => count($salesRows),
    'departments' => count($departmentTotals)
];

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
        font-size: 2.25rem;
        margin: 0;
    }
    .subtotal-row {
        background-color: #f8fafc;
    }
    .grand-total-row {
        background-color: #e2e8f0;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-lg-6">
            <h3 class="mb-1">To-Date Sales Report</h3>
            <p class="text-muted mb-0">Sales recorded from <?php echo htmlspecialchars($reportLabel); ?> grouped by department.</p>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
            <form class="row g-2 align-items-end justify-content-end" method="get" novalidate>
                <div class="col-sm-6 col-md-5">
                    <label for="start-date" class="form-label text-muted small mb-1">Start Date</label>
                    <input type="date" id="start-date" name="start" class="form-control" value="<?php echo htmlspecialchars($reportMeta['start_date']); ?>">
                </div>
                <div class="col-sm-6 col-md-5">
                    <label for="end-date" class="form-label text-muted small mb-1">End Date</label>
                    <input type="date" id="end-date" name="end" class="form-control" value="<?php echo htmlspecialchars($reportMeta['end_date']); ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">View</button>
                </div>
                <div class="col-auto">
                    <a class="btn btn-outline-secondary" href="print_todate_sales.php?start=<?php echo urlencode($reportMeta['start_date']); ?>&end=<?php echo urlencode($reportMeta['end_date']); ?>">
                        Export Excel
                    </a>
                </div>
                <div class="col-12 text-end">
                    <span class="text-muted small">Generated: <?php echo htmlspecialchars($reportMeta['generated_at']); ?></span>
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
                    <span class="label">Grand Total</span>
                    <h4><?php echo number_format($reportMeta['grand_total'], 2); ?></h4>
                    <p class="text-muted mb-0">Aggregate amount from <?php echo htmlspecialchars($reportLabel); ?>.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">Transactions</span>
                    <h4><?php echo number_format($reportMeta['records']); ?></h4>
                    <p class="text-muted mb-0">Total line items captured in range.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="report-card">
                    <span class="label">Departments</span>
                    <h4><?php echo number_format($reportMeta['departments']); ?></h4>
                    <p class="text-muted mb-0">Departments with activity in the selected range.</p>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Sales &mdash; <?php echo htmlspecialchars($reportLabel); ?></h5>
                <span class="text-muted small">
                    <?php echo $reportMeta['records']; ?> record(s)
                    <?php if ($reportMeta['grand_total'] > 0): ?>
                        &bull; Total: <?php echo number_format($reportMeta['grand_total'], 2); ?>
                    <?php endif; ?>
                </span>
            </div>
            <div class="card-body">
                <?php if (empty($salesRows)): ?>
                    <p class="text-muted mb-0">No sales were recorded for the selected date range.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="todate-sales-table" class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Retrieval Code</th>
                                    <th>Item Code</th>
                                    <th>Item Description</th>
                                    <th class="text-end">Line Item</th>
                                    <th class="text-end">Amount</th>
                                    <th>Status</th>
                                    <th>Sale Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedSales as $department => $items): ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($department); ?></td>
                                            <td><?php echo htmlspecialchars($item['retrieval_code'] !== '' ? $item['retrieval_code'] : 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                            <td><?php echo htmlspecialchars($item['item_description'] !== '' ? $item['item_description'] : 'Unspecified'); ?></td>
                                            <td class="text-end"><?php echo htmlspecialchars($item['line_item'] !== '' ? $item['line_item'] : '-'); ?></td>
                                            <td class="text-end"><?php echo number_format($item['amount'], 2); ?></td>
                                            <td>
                                                <?php
                                                    $statusLabel = formatPaymentStatus($item['payment_status']);
                                                    $statusClass = (int)$item['payment_status'] === 1
                                                        ? 'badge bg-success-subtle text-success'
                                                        : 'badge bg-secondary-subtle text-secondary';
                                                ?>
                                                <span class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['sale_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="subtotal-row fw-semibold">
                                        <td colspan="5" class="text-end">Subtotal - <?php echo htmlspecialchars($department); ?></td>
                                        <td class="text-end"><?php echo number_format($departmentTotals[$department], 2); ?></td>
                                        <td colspan="2"></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="grand-total-row fw-bold">
                                    <td colspan="5" class="text-end">Grand Total</td>
                                    <td class="text-end"><?php echo number_format($reportMeta['grand_total'], 2); ?></td>
                                    <td colspan="2"></td>
                                </tr>
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
            var table = document.getElementById('todate-sales-table');
            if (!table || !window.jQuery || !jQuery.fn.DataTable) { return; }
            jQuery(table).DataTable({
                pageLength: 25,
                lengthChange: false,
                ordering: false,
                language: {
                    search: 'Filter:',
                    emptyTable: 'No sales for this date range.',
                    info: 'Showing _START_ to _END_ of _TOTAL_ sales',
                    infoEmpty: 'Showing 0 to 0 of 0 sales',
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
