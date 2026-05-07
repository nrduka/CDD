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
$reportTypeInput = isset($_GET['report_type']) ? trim((string)$_GET['report_type']) : '';
$reportType = in_array($reportTypeInput, ['detailed'], true) ? $reportTypeInput : 'detailed';

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
    $registerRows = enrichRegisterRowsWithOrderPayments($registerRows);
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

function buildCustomerDisplayName($row)
{
    $storeName = trim((string)(isset($row['storeName']) ? $row['storeName'] : ''));
    if ($storeName !== '') {
        return $storeName;
    }

    $firstName = trim((string)(isset($row['firstName']) ? $row['firstName'] : ''));
    $middleName = trim((string)(isset($row['middleName']) ? $row['middleName'] : ''));
    $lastName = trim((string)(isset($row['lastName']) ? $row['lastName'] : ''));
    $nameParts = [];
    if ($firstName !== '') {
        $nameParts[] = $firstName;
    }
    if ($middleName !== '') {
        $nameParts[] = $middleName;
    }
    if ($lastName !== '') {
        $nameParts[] = $lastName;
    }

    $displayName = trim(implode(' ', $nameParts));
    if ($displayName !== '') {
        return $displayName;
    }

    return trim((string)(isset($row['customer_code']) ? $row['customer_code'] : ''));
}

function resolveProductTypeGroup($productDescription, $productCode)
{
    $description = trim((string)$productDescription);
    if ($description !== '') {
        $parts = explode('-', $description, 2);
        $groupLabel = strtoupper(trim($parts[0]));
        if ($groupLabel !== '') {
            return $groupLabel;
        }
    }

    $code = trim((string)$productCode);
    if ($code !== '') {
        $parts = explode('-', $code);
        if (isset($parts[1]) && trim((string)$parts[1]) !== '') {
            return strtoupper(trim((string)$parts[1]));
        }

        return strtoupper($code);
    }

    return 'UNCLASSIFIED';
}

function enrichRegisterRowsWithOrderPayments($rows)
{
    $orderPayments = [];
    foreach ($rows as $index => $row) {
        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        $orderKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;
        $amount = isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $amtReceived = isset($row['amt_received']) ? (float)$row['amt_received'] : 0.0;
        $amtChange = isset($row['amt_change']) ? (float)$row['amt_change'] : 0.0;

        if (!isset($orderPayments[$orderKey])) {
            $orderPayments[$orderKey] = [
                'first_index' => $index,
                'order_amount' => 0.0,
                'amt_received' => $amtReceived,
                'amt_change' => $amtChange,
                'cash' => 0.0,
                'on_account' => 0.0
            ];
        }

        $orderPayments[$orderKey]['order_amount'] += $amount;
        if ($amtReceived > $orderPayments[$orderKey]['amt_received']) {
            $orderPayments[$orderKey]['amt_received'] = $amtReceived;
        }
        if ($amtChange > $orderPayments[$orderKey]['amt_change']) {
            $orderPayments[$orderKey]['amt_change'] = $amtChange;
        }
    }

    foreach ($orderPayments as &$payment) {
        $cash = $payment['amt_received'] - $payment['amt_change'];
        if ($cash < 0) {
            $cash = 0.0;
        }
        $payment['cash'] = $cash;
        $payment['on_account'] = $payment['amt_received'] <= 0 ? $payment['order_amount'] : 0.0;
    }
    unset($payment);

    foreach ($rows as $index => &$row) {
        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        $orderKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;
        $payment = $orderPayments[$orderKey];
        $isPaymentRow = $payment['first_index'] === $index;
        $lineAmount = isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $orderAmount = (float)$payment['order_amount'];
        $allocationRatio = 0.0;
        if ($orderAmount > 0) {
            $allocationRatio = $lineAmount / $orderAmount;
        } elseif ($isPaymentRow) {
            $allocationRatio = 1.0;
        }

        $row['amt_received_once'] = $isPaymentRow ? (float)$payment['amt_received'] : 0.0;
        $row['amt_change_once'] = $isPaymentRow ? (float)$payment['amt_change'] : 0.0;
        $row['cash_once'] = $isPaymentRow ? (float)$payment['cash'] : 0.0;
        $row['on_account_once'] = $isPaymentRow ? (float)$payment['on_account'] : 0.0;
        $row['cash_allocated'] = (float)$payment['cash'] * $allocationRatio;
        $row['on_account_allocated'] = (float)$payment['on_account'] * $allocationRatio;
    }
    unset($row);

    return $rows;
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
                   s.amt_change,
                   c.storeName,
                   c.firstName,
                   c.middleName,
                   c.lastName
             FROM sales s
             LEFT JOIN customer_register c ON c.customer_code = s.customer_code
             WHERE DATE(COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created)) BETWEEN ? AND ?
              AND (s.void_status IS NULL OR s.void_status = 0)
             ORDER BY COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created), s.order_no, s.lineitem";

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
                    'customer_name' => buildCustomerDisplayName($row),
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
$productSummaryGroups = [];
$productSummaryRecordCount = 0;
if ($reportType === 'by_product') {
    foreach ($registerRows as $row) {
        $productCode = isset($row['product_code']) ? (string)$row['product_code'] : '';
        $productDescription = isset($row['product_description']) ? (string)$row['product_description'] : '';
        $unitPrice = isset($row['unit_price']) ? (float)$row['unit_price'] : 0.0;
        $groupLabel = resolveProductTypeGroup($productDescription, $productCode);
        $itemKey = $productCode . '|' . $productDescription . '|' . number_format($unitPrice, 4, '.', '');

        if (!isset($productSummaryGroups[$groupLabel])) {
            $productSummaryGroups[$groupLabel] = [
                'subtotal_qty' => 0.0,
                'subtotal_amount' => 0.0,
                'subtotal_cash' => 0.0,
                'subtotal_on_account' => 0.0,
                'items' => []
            ];
        }

        if (!isset($productSummaryGroups[$groupLabel]['items'][$itemKey])) {
            $productSummaryGroups[$groupLabel]['items'][$itemKey] = [
                'product_code' => $productCode,
                'product_description' => $productDescription,
                'unit_price' => $unitPrice,
                'qty' => 0.0,
                'amount' => 0.0,
                'cash' => 0.0,
                'on_account' => 0.0
            ];
        }

        $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $amount = isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $cash = isset($row['cash_allocated']) ? (float)$row['cash_allocated'] : 0.0;
        $onAccount = isset($row['on_account_allocated']) ? (float)$row['on_account_allocated'] : 0.0;

        $productSummaryGroups[$groupLabel]['items'][$itemKey]['qty'] += $qty;
        $productSummaryGroups[$groupLabel]['items'][$itemKey]['amount'] += $amount;
        $productSummaryGroups[$groupLabel]['items'][$itemKey]['cash'] += $cash;
        $productSummaryGroups[$groupLabel]['items'][$itemKey]['on_account'] += $onAccount;
        $productSummaryGroups[$groupLabel]['subtotal_qty'] += $qty;
        $productSummaryGroups[$groupLabel]['subtotal_amount'] += $amount;
        $productSummaryGroups[$groupLabel]['subtotal_cash'] += $cash;
        $productSummaryGroups[$groupLabel]['subtotal_on_account'] += $onAccount;
    }

    if (!empty($productSummaryGroups)) {
        ksort($productSummaryGroups, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($productSummaryGroups as &$groupData) {
            uasort($groupData['items'], function ($left, $right) {
                $leftCode = strtoupper(trim((string)$left['product_code']));
                $rightCode = strtoupper(trim((string)$right['product_code']));
                if ($leftCode === $rightCode) {
                    return strcasecmp((string)$left['product_description'], (string)$right['product_description']);
                }

                return strcasecmp($leftCode, $rightCode);
            });
            $productSummaryRecordCount += count($groupData['items']);
        }
        unset($groupData);
    }
}
$customerSummaryRows = [];
if ($reportType === 'by_customer') {
    $customerBuckets = [];
    foreach ($registerRows as $row) {
        $rawCustomerCode = isset($row['customer_code']) ? (string)$row['customer_code'] : '';
        $customerCode = trim($rawCustomerCode);
        if ($customerCode === '') {
            $customerCode = 'UNASSIGNED';
        }
        $customerName = trim((string)(isset($row['customer_name']) ? $row['customer_name'] : ''));
        if ($customerName === '' && $customerCode !== 'UNASSIGNED') {
            $customerName = $customerCode;
        }

        if (!isset($customerBuckets[$customerCode])) {
            $customerBuckets[$customerCode] = [
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'transaction_orders' => [],
                'qty' => 0.0,
                'amount' => 0.0,
                'cash' => 0.0,
                'on_account' => 0.0
            ];
        }

        if ($customerBuckets[$customerCode]['customer_name'] === '' && $customerName !== '') {
            $customerBuckets[$customerCode]['customer_name'] = $customerName;
        }

        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        if ($orderNo !== '') {
            $customerBuckets[$customerCode]['transaction_orders'][$orderNo] = true;
        }

        $customerBuckets[$customerCode]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $customerBuckets[$customerCode]['amount'] += isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $customerBuckets[$customerCode]['cash'] += isset($row['cash_once']) ? (float)$row['cash_once'] : 0.0;
        $customerBuckets[$customerCode]['on_account'] += isset($row['on_account_once']) ? (float)$row['on_account_once'] : 0.0;
    }

    foreach ($customerBuckets as $bucket) {
        $bucket['transaction_count'] = count($bucket['transaction_orders']);
        unset($bucket['transaction_orders']);
        if ($bucket['customer_name'] === '' && $bucket['customer_code'] !== 'UNASSIGNED') {
            $bucket['customer_name'] = $bucket['customer_code'];
        }
        $customerSummaryRows[] = $bucket;
    }

    usort($customerSummaryRows, function ($left, $right) {
        $codeCompare = strcasecmp((string)$left['customer_code'], (string)$right['customer_code']);
        if ($codeCompare !== 0) {
            return $codeCompare;
        }

        return strcasecmp((string)$left['customer_name'], (string)$right['customer_name']);
    });
}
$reportMeta = [
    'start_date' => $startDate,
    'end_date' => $endDate,
    'report_type' => $reportType,
    'generated_at' => $generatedAt,
    'records' => count($registerRows),
    'total_sales' => $totalSales,
    'transaction_count' => $transactionCount,
    'average_daily_sales' => $averageDailySales,
    'average_daily_transactions' => $averageDailyTransactions
];
if ($reportType === 'by_customer') {
    $reportMeta['records'] = count($customerSummaryRows);
} elseif ($reportType === 'by_product') {
    $reportMeta['records'] = $productSummaryRecordCount;
}

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
    .todate-daily-sales-table .dataTables_scrollBody {
        overflow-x: scroll !important;
        overflow-y: auto !important;
        scrollbar-gutter: stable both-edges;
        scrollbar-width: auto;
        scrollbar-color: #4b5563 #d1d5db;
    }
    .todate-daily-sales-table .dataTables_scrollBody::-webkit-scrollbar {
        height: 12px;
    }
    .todate-daily-sales-table .dataTables_scrollBody::-webkit-scrollbar-track {
        background: #d1d5db;
    }
    .todate-daily-sales-table .dataTables_scrollBody::-webkit-scrollbar-thumb {
        background: #4b5563;
        border-radius: 10px;
        border: 2px solid #d1d5db;
    }
    .todate-daily-sales-table .dataTables_scrollBody::-webkit-scrollbar-thumb:hover {
        background: #374151;
    }
    .todate-daily-sales-table table {
        min-width: 1400px;
        white-space: nowrap;
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
                    <div class="col-sm-5 col-md-4">
                        <label for="start-date" class="form-label text-muted small mb-1">Start Date</label>
                        <input type="date" id="start-date" name="start" class="form-control" value="<?php echo htmlspecialchars($reportMeta['start_date']); ?>">
                    </div>
                    <div class="col-sm-5 col-md-4">
                        <label for="end-date" class="form-label text-muted small mb-1">End Date</label>
                        <input type="date" id="end-date" name="end" class="form-control" value="<?php echo htmlspecialchars($reportMeta['end_date']); ?>">
                    </div>
                    <div class="col-sm-5 col-md-4">
                        <label for="report-type" class="form-label text-muted small mb-1">Report Type</label>
                        <select id="report-type" name="report_type" class="form-select">
                            <option value="detailed" <?php echo $reportMeta['report_type'] === 'detailed' ? 'selected' : ''; ?>>Detailed</option>
                        </select>
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
                    <?php if ($reportMeta['report_type'] === 'by_customer'): ?>
                        <?php
                        $summaryTotalTransactions = 0;
                        $summaryTotalQty = 0.0;
                        $summaryTotalAmount = 0.0;
                        $summaryTotalCash = 0.0;
                        $summaryTotalOnAccount = 0.0;
                        ?>
                        <div class="table-responsive todate-daily-sales-table">
                            <table id="todate-daily-sales-table" class="table table-striped align-middle mb-0 w-100">
                                <thead>
                                    <tr class="daily-register-head-bottom text-center align-middle">
                                        <th>No.</th>
                                        <th>Customer Code</th>
                                        <th>Customer Name</th>
                                        <th class="text-end">Transactions</th>
                                        <th class="text-end">Total Quantity</th>
                                        <th class="text-end">Total Amount</th>
                                        <th class="text-end">Cash</th>
                                        <th class="text-end">On Account</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customerSummaryRows as $index => $row): ?>
                                        <?php
                                        $summaryTotalTransactions += (int)$row['transaction_count'];
                                        $summaryTotalQty += (float)$row['qty'];
                                        $summaryTotalAmount += (float)$row['amount'];
                                        $summaryTotalCash += (float)$row['cash'];
                                        $summaryTotalOnAccount += (float)$row['on_account'];
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($row['customer_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['transaction_count']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['qty'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['amount'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['cash'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['on_account'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <td colspan="3" class="text-end">TOTAL</td>
                                        <td class="text-end"><?php echo number_format($summaryTotalTransactions); ?></td>
                                        <td class="text-end"><?php echo number_format($summaryTotalQty, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($summaryTotalAmount, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($summaryTotalCash, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($summaryTotalOnAccount, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php elseif ($reportMeta['report_type'] === 'by_product'): ?>
                        <div class="table-responsive todate-daily-sales-table">
                            <table id="todate-daily-sales-table" class="table table-striped align-middle mb-0 w-100">
                                <thead>
                                    <tr class="daily-register-head-bottom text-center align-middle">
                                        <th>No.</th>
                                        <th>Product Type</th>
                                        <th>Product Code</th>
                                        <th>Product Description</th>
                                        <th class="text-end">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Cash</th>
                                        <th class="text-end">On Account</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $lineNumber = 1;
                                    $grandTotalQty = 0.0;
                                    $grandTotalAmount = 0.0;
                                    $grandTotalCash = 0.0;
                                    $grandTotalOnAccount = 0.0;
                                    ?>
                                    <?php foreach ($productSummaryGroups as $groupLabel => $groupData): ?>
                                        <?php foreach ($groupData['items'] as $item): ?>
                                            <tr>
                                                <td class="text-center"><?php echo $lineNumber; ?></td>
                                                <td><?php echo htmlspecialchars($groupLabel); ?></td>
                                                <td><?php echo htmlspecialchars($item['product_code']); ?></td>
                                                <td><?php echo htmlspecialchars($item['product_description']); ?></td>
                                                <td class="text-end"><?php echo number_format($item['qty'], 2); ?></td>
                                                <td class="text-end"><?php echo number_format($item['unit_price'], 2); ?></td>
                                                <td class="text-end"><?php echo number_format($item['amount'], 2); ?></td>
                                                <td class="text-end"><?php echo number_format($item['cash'], 2); ?></td>
                                                <td class="text-end"><?php echo number_format($item['on_account'], 2); ?></td>
                                            </tr>
                                            <?php $lineNumber++; ?>
                                        <?php endforeach; ?>
                                        <tr class="fw-semibold">
                                            <td></td>
                                            <td colspan="3">SUBTOTAL - <?php echo htmlspecialchars($groupLabel); ?></td>
                                            <td class="text-end"><?php echo number_format($groupData['subtotal_qty'], 2); ?></td>
                                            <td></td>
                                            <td class="text-end"><?php echo number_format($groupData['subtotal_amount'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($groupData['subtotal_cash'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($groupData['subtotal_on_account'], 2); ?></td>
                                        </tr>
                                        <?php
                                        $grandTotalQty += (float)$groupData['subtotal_qty'];
                                        $grandTotalAmount += (float)$groupData['subtotal_amount'];
                                        $grandTotalCash += (float)$groupData['subtotal_cash'];
                                        $grandTotalOnAccount += (float)$groupData['subtotal_on_account'];
                                        ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td></td>
                                        <td colspan="3" class="text-end">TOTAL</td>
                                        <td class="text-end"><?php echo number_format($grandTotalQty, 2); ?></td>
                                        <td></td>
                                        <td class="text-end"><?php echo number_format($grandTotalAmount, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($grandTotalCash, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($grandTotalOnAccount, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
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
                                        <th>Customer Name</th>
                                        <th class="text-end">Quantity</th>
                                        <th>Product Code</th>
                                        <th>Product Description</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">Cash</th>
                                        <th class="text-end">On Account</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $transactionSequence = 0;
                                    $transactionNumberByOrder = [];
                                    $transactionRenderedByOrder = [];
                                    ?>
                                    <?php foreach ($registerRows as $index => $row): ?>
                                        <?php
                                        $orderNoRaw = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
                                        $transactionKey = $orderNoRaw !== '' ? $orderNoRaw : '__ROW_' . $index;
                                        if (!isset($transactionNumberByOrder[$transactionKey])) {
                                            $transactionSequence++;
                                            $transactionNumberByOrder[$transactionKey] = $transactionSequence;
                                        }
                                        $showTransactionNumber = !isset($transactionRenderedByOrder[$transactionKey]);
                                        $transactionRenderedByOrder[$transactionKey] = true;
                                        $orderNoDisplay = $row['order_no'] !== '' ? 'DR' . $row['order_no'] : '';
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $showTransactionNumber ? $transactionNumberByOrder[$transactionKey] : ''; ?></td>
                                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                                            <td><?php echo htmlspecialchars($row['time']); ?></td>
                                            <td><?php echo htmlspecialchars($orderNoDisplay); ?></td>
                                            <td><?php echo htmlspecialchars($row['customer_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['qty'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($row['product_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['product_description']); ?></td>
                                            <td class="text-end"><?php echo number_format($row['unit_price'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['amount'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['cash_once'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['on_account_once'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
