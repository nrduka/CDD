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
$registerRows = [];
$totalSales = 0;
$uniqueOrders = [];

if (!isset($mysqli) || !$mysqli) {
    $pageError = 'Database connection unavailable.';
} else {
    $registerRows = fetchToDateRegisterRows($mysqli, $startDate, $endDate);
    foreach ($registerRows as $row) {
        $totalSales += isset($row['amount']) ? (float)$row['amount'] : 0;
        $orderKey = isset($row['order_no']) ? (string)$row['order_no'] : '';
        if ($orderKey !== '') {
            $uniqueOrders[$orderKey] = true;
        }
    }
}

function resolveSalesDateTime($primaryValue, $fallbackValue)
{
    // Resolve to the first valid sales timestamp.
    $primaryValue = trim((string)$primaryValue);
    $fallbackValue = trim((string)$fallbackValue);
    $dateTime = null;

    if ($primaryValue !== '' && $primaryValue !== '0000-00-00 00:00:00') {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $primaryValue);
    }
    if (!$dateTime && $fallbackValue !== '' && $fallbackValue !== '0000-00-00 00:00:00') {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $fallbackValue);
    }

    return $dateTime ?: null;
}

function fetchToDateRegisterRows($mysqli, $startDate, $endDate)
{
    $rows = [];
    $sql = "SELECT s.order_no,
                   s.customer_code,
                   s.qty,
                   s.product_code,
                   s.product_description,
                   s.unit_price,
                   s.amount,
                   s.date_created,
                   s.date_time_ordered,
                   s.amt_received,
                   s.amt_change
            FROM sales s
            WHERE DATE(s.date_created) BETWEEN ? AND ?
            ORDER BY s.date_created, s.order_no, s.lineitem";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $orderedAt = resolveSalesDateTime(
                    isset($row['date_time_ordered']) ? $row['date_time_ordered'] : '',
                    isset($row['date_created']) ? $row['date_created'] : ''
                );
                $dateLabel = $orderedAt ? $orderedAt->format('Y-m-d') : '';
                $timeLabel = $orderedAt ? $orderedAt->format('H:i:s') : '';

                $amount = isset($row['amount']) ? (float)$row['amount'] : 0.0;
                $amtReceived = isset($row['amt_received']) ? (float)$row['amt_received'] : 0.0;
                $amtChange = isset($row['amt_change']) ? (float)$row['amt_change'] : 0.0;
                $cash = $amtReceived - $amtChange;
                if ($cash < 0) {
                    $cash = 0;
                }
                $onAccount = $amtReceived <= 0 ? $amount : 0;

                $rows[] = [
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => isset($row['product_description']) ? (string)$row['product_description'] : '',
                    'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                    'customer_code' => isset($row['customer_code']) ? (string)$row['customer_code'] : '',
                    'qty' => isset($row['qty']) ? (float)$row['qty'] : 0.0,
                    'unit_price' => isset($row['unit_price']) ? (float)$row['unit_price'] : 0.0,
                    'amount' => $amount,
                    'amt_received' => $amtReceived,
                    'amt_change' => $amtChange,
                    'cash' => $cash,
                    'on_account' => $onAccount,
                    'date' => $dateLabel,
                    'time' => $timeLabel
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

$reportRangeLabel = 'from ' . date('F d', strtotime($startDate)) . ' to ' . date('d, Y', strtotime($endDate));
$transactionCount = count($uniqueOrders);
$dayCount = $startObject && $endObject ? $startObject->diff($endObject)->days + 1 : 1;
$averageDailySales = $dayCount > 0 ? ($totalSales / $dayCount) : 0;
$averageDailyTransactions = $dayCount > 0 ? ($transactionCount / $dayCount) : 0;
$reportMeta = [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'generated_at' => $generatedAt,
    'records' => count($registerRows),
    'total_sales' => $totalSales,
    'transaction_count' => $transactionCount,
    'average_daily_sales' => $averageDailySales,
    'average_daily_transactions' => $averageDailyTransactions
];

ob_start();
?>
<link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/libs/datatables.net-bs5/css/dataTables.bootstrap5.min.css">
<style>
    .report-card {
        border-radius: 0.75rem;
        border: 1px solid rgba(226, 232, 240, 0.9);
        padding: 1.25rem;
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
        font-size: 2rem;
        margin: 0;
    }
    .summary-table {
        width: auto;
        min-width: 280px;
    }
    .summary-table td,
    .summary-table th {
        padding: 0.35rem 0.6rem;
    }
    .summary-table .value {
        text-align: right;
        min-width: 140px;
    }
    .daily-register-head-bottom {
        background: #e5e7eb;
        color: #dc2626;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .todate-daily-sales-table .dataTables_wrapper,
    .todate-daily-sales-table .dataTables_scroll,
    .todate-daily-sales-table .dataTables_scrollHead,
    .todate-daily-sales-table .dataTables_scrollBody,
    .todate-daily-sales-table .dataTables_scrollHeadInner,
    .todate-daily-sales-table .dataTables_scrollHeadInner table,
    .todate-daily-sales-table .dataTables_scrollBody table {
        width: 100% !important;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-start">
        <div class="col-lg-6">
            <h3 class="mb-1">Daily Sales Register</h3>
            <p class="text-muted mb-1"><?php echo htmlspecialchars($reportRangeLabel); ?></p>
            <p class="text-muted small mb-0">Generated: <?php echo htmlspecialchars($reportMeta['generated_at']); ?></p>
        </div>
        <div class="col-lg-6 mt-3 mt-lg-0">
            <div class="d-flex flex-column gap-3 align-items-lg-end">
                <form class="row g-2 align-items-end justify-content-lg-end" method="get" novalidate>
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
                        <a class="btn btn-outline-secondary" href="print_todate_daily_sales.php?start=<?php echo urlencode($reportMeta['start_date']); ?>&end=<?php echo urlencode($reportMeta['end_date']); ?>">
                            Export Excel
                        </a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered summary-table mb-0">
                        <tbody>
                            <tr>
                                <th class="text-uppercase small text-muted">Total Sales</th>
                                <td class="value"><?php echo number_format($reportMeta['total_sales'], 2); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Total Transaction Count</th>
                                <td class="value"><?php echo number_format($reportMeta['transaction_count']); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Average Daily Sales</th>
                                <td class="value"><?php echo number_format($reportMeta['average_daily_sales'], 2); ?></td>
                            </tr>
                            <tr>
                                <th class="text-uppercase small text-muted">Average Daily Transaction Count</th>
                                <td class="value"><?php echo number_format($reportMeta['average_daily_transactions']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pageError !== null): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($pageError); ?></div>
    <?php else: ?>
        <div class="card custom-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Daily Sales Register</h5>
                <span class="text-muted small"><?php echo $reportMeta['records']; ?> record(s)</span>
            </div>
            <div class="card-body">
                <?php if (empty($registerRows)): ?>
                    <p class="text-muted mb-0">No sales were recorded for the selected date range.</p>
                <?php else: ?>
                    <div class="table-responsive todate-daily-sales-table">
                        <table id="todate-daily-sales-table" class="table table-striped align-middle mb-0 w-100">
                            <thead>
                                <tr class="daily-register-head-bottom text-center align-middle">
                                    <th>No.</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>DR No.</th>
                                    <th>Customer Code</th>
                                    <th class="text-end">Quantity</th>
                                    <th>Product Code</th>
                                    <th>Product Description</th>
                                    <th class="text-end">Unit Price</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Amount Received</th>
                                    <th class="text-end">Change</th>
                                    <th class="text-end">Cash</th>
                                    <th class="text-end">On Account</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registerRows as $index => $row): ?>
                                    <?php $orderNoDisplay = $row['order_no'] !== '' ? 'DR' . $row['order_no'] : ''; ?>
                                    <tr>
                                        <td class="text-center"><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                                        <td><?php echo htmlspecialchars($row['time']); ?></td>
                                        <td><?php echo htmlspecialchars($orderNoDisplay); ?></td>
                                        <td><?php echo htmlspecialchars($row['customer_code']); ?></td>
                                        <td class="text-end"><?php echo number_format($row['qty'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($row['product_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['product_description']); ?></td>
                                        <td class="text-end"><?php echo number_format($row['unit_price'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($row['amount'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($row['amt_received'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($row['amt_change'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($row['cash'], 2); ?></td>
                                        <td class="text-end"><?php echo number_format($row['on_account'], 2); ?></td>
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

$scriptsBuffer = function () use ($baseUrl) {
    ob_start();
    ?>
    <script src="<?php echo $baseUrl; ?>/assets/libs/jquery/jquery-3.6.1.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo $baseUrl; ?>/assets/libs/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var table = document.getElementById('todate-daily-sales-table');
            if (!table || !window.jQuery || !jQuery.fn.DataTable) { return; }
            jQuery(table).DataTable({
                autoWidth: false,
                pageLength: 25,
                lengthChange: false,
                ordering: false,
                paging: true,
                scrollX: true,
                scrollY: '60vh',
                scrollCollapse: true,
                dom: "<'row align-items-center mb-2'<'col-sm-6'f><'col-sm-6 text-end'>>rt<'row align-items-center mt-2'<'col-sm-6'i><'col-sm-6'p>>",
                language: {
                    search: 'Filter:',
                    emptyTable: 'No sales for this date range.',
                    info: 'Showing _START_ to _END_ of _TOTAL_ records',
                    infoEmpty: 'Showing 0 to 0 of 0 records',
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
