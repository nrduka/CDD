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
require_once __DIR__ . '/report_export_runtime.php';

if (
    !isset($mysqli)
    || !($mysqli instanceof mysqli)
    || (function_exists('mysqli_ping') && !@mysqli_ping($mysqli))
) {
    $fallbackHost = isset($databaseHost) ? (string)$databaseHost : 'localhost';
    $fallbackName = isset($databaseName) ? (string)$databaseName : 'cdd';
    $fallbackUser = isset($databaseUsername) ? (string)$databaseUsername : 'root';
    $fallbackPass = isset($databasePassword) ? (string)$databasePassword : '';
    $fallbackMysqli = @mysqli_connect($fallbackHost, $fallbackUser, $fallbackPass, $fallbackName);
    if ($fallbackMysqli instanceof mysqli) {
        $mysqli = $fallbackMysqli;
    }
}

$today = date('Y-m-d');
$startInput = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
$endInput = isset($_GET['end']) ? trim((string)$_GET['end']) : '';
$reportTypeInput = isset($_GET['report_type']) ? trim((string)$_GET['report_type']) : '';
$reportType = in_array($reportTypeInput, ['by_product', 'by_customer', 'by_source_channel'], true) ? $reportTypeInput : 'by_product';

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

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    $pageError = 'Database connection unavailable.';
    $connectError = trim((string)mysqli_connect_error());
    if ($connectError !== '') {
        $pageError .= ' ' . $connectError;
    }
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

function buildSourceChannelLabel($row)
{
    $channel = trim((string)(isset($row['source_channel']) ? $row['source_channel'] : ''));
    if ($channel !== '') {
        return $channel;
    }

    $customerSource = trim((string)(isset($row['customer_source']) ? $row['customer_source'] : ''));
    if ($customerSource !== '' && $customerSource !== '0') {
        return 'SOURCE ' . $customerSource;
    }

    return 'UNASSIGNED';
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
                   p.product_type AS master_product_type,
                   p.product_description AS master_product_description,
                   p.uom,
                   c.storeName,
                   c.firstName,
                   c.middleName,
                   c.lastName,
                   c.customer_source,
                   sc.customer_channel AS source_channel
             FROM sales s
             LEFT JOIN ice_product p ON p.product_code = s.product_code
             LEFT JOIN customer_register c ON c.customer_code = s.customer_code
             LEFT JOIN source_channel sc ON sc.customer_source = c.customer_source
             WHERE DATE(COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created)) BETWEEN ? AND ?
              AND (s.void_status IS NULL OR s.void_status = 0)
             ORDER BY c.customer_source, c.storeName, c.firstName, c.lastName,
                      COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created),
                      s.order_no, s.lineitem";

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
                $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
                if ($productDescription === '') {
                    $productDescription = trim((string)(isset($row['master_product_description']) ? $row['master_product_description'] : ''));
                }
                $productType = trim((string)(isset($row['master_product_type']) ? $row['master_product_type'] : ''));
                if ($productType === '') {
                    $productType = resolveProductTypeGroup(
                        $productDescription,
                        isset($row['product_code']) ? $row['product_code'] : ''
                    );
                }

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
                    'product_description' => $productDescription,
                    'product_type' => $productType,
                    'uom' => trim((string)(isset($row['uom']) ? $row['uom'] : '')),
                    'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                    'customer_code' => isset($row['customer_code']) ? (string)$row['customer_code'] : '',
                    'customer_name' => buildCustomerDisplayName($row),
                    'customer_source' => isset($row['customer_source']) ? (string)$row['customer_source'] : '',
                    'source_channel' => buildSourceChannelLabel($row),
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

function fetchToDateSummaryByDate($registerRows)
{
    $totals = [];
    $transactionsPerDate = [];

    foreach ($registerRows as $index => $row) {
        $saleDate = trim((string)(isset($row['date']) ? $row['date'] : ''));
        if ($saleDate === '') {
            continue;
        }

        if (!isset($totals[$saleDate])) {
            $totals[$saleDate] = [
                'transactions' => 0,
                'qty' => 0.0,
                'amount' => 0.0,
                'cash' => 0.0,
                'on_account' => 0.0
            ];
            $transactionsPerDate[$saleDate] = [];
        }

        $totals[$saleDate]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $totals[$saleDate]['amount'] += isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $totals[$saleDate]['cash'] += isset($row['cash_once']) ? (float)$row['cash_once'] : 0.0;
        $totals[$saleDate]['on_account'] += isset($row['on_account_once']) ? (float)$row['on_account_once'] : 0.0;

        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        $orderKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;
        $transactionsPerDate[$saleDate][$orderKey] = true;
    }

    foreach ($totals as $saleDate => &$values) {
        $values['transactions'] = isset($transactionsPerDate[$saleDate]) ? count($transactionsPerDate[$saleDate]) : 0;
    }
    unset($values);

    ksort($totals, SORT_STRING);
    return $totals;
}

function fillMissingSummaryDates($summaryByDate, $startDate, $endDate)
{
    $filled = [];
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);

    foreach ($period as $date) {
        $key = $date->format('Y-m-d');
        if (isset($summaryByDate[$key])) {
            $filled[$key] = $summaryByDate[$key];
            continue;
        }
        $filled[$key] = [
            'transactions' => 0,
            'qty' => 0.0,
            'amount' => 0.0,
            'cash' => 0.0,
            'on_account' => 0.0
        ];
    }

    return $filled;
}

function buildProductPivotRows($registerRows)
{
    $pivotByProduct = [];
    foreach ($registerRows as $row) {
        $productCode = trim((string)(isset($row['product_code']) ? $row['product_code'] : ''));
        if ($productCode === '') {
            continue;
        }

        $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
        $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
        $uom = trim((string)(isset($row['uom']) ? $row['uom'] : ''));
        $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;

        if ($productType === '') {
            $productType = resolveProductTypeGroup($productDescription, $productCode);
        }

        $key = strtoupper($productCode);
        if (!isset($pivotByProduct[$key])) {
            $pivotByProduct[$key] = [
                'product_type' => $productType,
                'product_code' => $productCode,
                'product_description' => $productDescription,
                'uom' => $uom,
                'qty' => 0.0
            ];
        }

        $pivotByProduct[$key]['qty'] += $qty;
        if ($pivotByProduct[$key]['product_type'] === '' && $productType !== '') {
            $pivotByProduct[$key]['product_type'] = $productType;
        }
        if ($pivotByProduct[$key]['product_description'] === '' && $productDescription !== '') {
            $pivotByProduct[$key]['product_description'] = $productDescription;
        }
        if ($pivotByProduct[$key]['uom'] === '' && $uom !== '') {
            $pivotByProduct[$key]['uom'] = $uom;
        }
    }

    $pivotRows = array_values($pivotByProduct);
    usort($pivotRows, function ($left, $right) {
        $leftType = strtoupper(trim((string)$left['product_type']));
        $rightType = strtoupper(trim((string)$right['product_type']));
        if ($leftType !== $rightType) {
            return strcmp($leftType, $rightType);
        }

        $leftCode = strtoupper(trim((string)$left['product_code']));
        $rightCode = strtoupper(trim((string)$right['product_code']));
        if ($leftCode !== $rightCode) {
            return strcmp($leftCode, $rightCode);
        }

        return strcmp(
            strtoupper(trim((string)$left['product_description'])),
            strtoupper(trim((string)$right['product_description']))
        );
    });

    return $pivotRows;
}

function buildProductPivotByDate($registerRows)
{
    $pivotByDate = [];

    foreach ($registerRows as $row) {
        $saleDate = trim((string)(isset($row['date']) ? $row['date'] : ''));
        $productCode = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
        if ($saleDate === '' || $productCode === '') {
            continue;
        }

        if (!isset($pivotByDate[$saleDate])) {
            $pivotByDate[$saleDate] = [];
        }
        if (!isset($pivotByDate[$saleDate][$productCode])) {
            $pivotByDate[$saleDate][$productCode] = [
                'qty' => 0.0,
                'uom' => trim((string)(isset($row['uom']) ? $row['uom'] : ''))
            ];
        }

        $pivotByDate[$saleDate][$productCode]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        if ($pivotByDate[$saleDate][$productCode]['uom'] === '') {
            $pivotByDate[$saleDate][$productCode]['uom'] = trim((string)(isset($row['uom']) ? $row['uom'] : ''));
        }
    }

    return $pivotByDate;
}

function buildSourceChannelProductSummary($registerRows)
{
    $sourceRows = [];

    foreach ($registerRows as $index => $row) {
        $sourceChannel = trim((string)(isset($row['source_channel']) ? $row['source_channel'] : ''));
        if ($sourceChannel === '') {
            $sourceChannel = 'UNASSIGNED';
        }
        $sourceSort = isset($row['customer_source']) && is_numeric($row['customer_source']) ? (int)$row['customer_source'] : 999999;
        $sourceKey = $sourceSort . '|' . strtoupper($sourceChannel);

        if (!isset($sourceRows[$sourceKey])) {
            $sourceRows[$sourceKey] = [
                'source_channel' => $sourceChannel,
                'source_sort' => $sourceSort,
                'transaction_orders' => [],
                'qty' => 0.0,
                'amount' => 0.0,
                'cash' => 0.0,
                'on_account' => 0.0,
                'products' => []
            ];
        }

        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        $orderKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;
        $sourceRows[$sourceKey]['transaction_orders'][$orderKey] = true;

        $sourceRows[$sourceKey]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $sourceRows[$sourceKey]['amount'] += isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $sourceRows[$sourceKey]['cash'] += isset($row['cash_once']) ? (float)$row['cash_once'] : 0.0;
        $sourceRows[$sourceKey]['on_account'] += isset($row['on_account_once']) ? (float)$row['on_account_once'] : 0.0;

        $productCode = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
        if ($productCode !== '') {
            if (!isset($sourceRows[$sourceKey]['products'][$productCode])) {
                $sourceRows[$sourceKey]['products'][$productCode] = 0.0;
            }
            $sourceRows[$sourceKey]['products'][$productCode] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        }
    }

    foreach ($sourceRows as &$sourceRow) {
        $sourceRow['transaction_count'] = count($sourceRow['transaction_orders']);
        unset($sourceRow['transaction_orders']);
    }
    unset($sourceRow);

    $sourceRows = array_values($sourceRows);
    usort($sourceRows, function ($left, $right) {
        $leftSourceSort = isset($left['source_sort']) ? (int)$left['source_sort'] : 999999;
        $rightSourceSort = isset($right['source_sort']) ? (int)$right['source_sort'] : 999999;
        if ($leftSourceSort !== $rightSourceSort) {
            return $leftSourceSort < $rightSourceSort ? -1 : 1;
        }

        return strcasecmp((string)$left['source_channel'], (string)$right['source_channel']);
    });

    return $sourceRows;
}

function buildPivotTypeHeaderGroups($productPivotRows)
{
    $groups = [];
    foreach ($productPivotRows as $index => $pivotData) {
        $productTypeLabel = trim((string)(isset($pivotData['product_type']) ? $pivotData['product_type'] : ''));
        if ($productTypeLabel === '') {
            $productTypeLabel = 'UNCLASSIFIED';
        }
        $productTypeKey = strtoupper($productTypeLabel);
        if (empty($groups) || $groups[count($groups) - 1]['key'] !== $productTypeKey) {
            $groups[] = [
                'key' => $productTypeKey,
                'label' => $productTypeLabel,
                'colspan' => 1
            ];
            continue;
        }

        $groups[count($groups) - 1]['colspan']++;
    }

    return $groups;
}

$reportRangeLabel = 'from ' . date('F d', strtotime($startDate)) . ' to ' . date('d, Y', strtotime($endDate));
$transactionCount = count($uniqueOrders);
$dayCount = $startObject && $endObject ? $startObject->diff($endObject)->days + 1 : 1;
$averageDailySales = $dayCount > 0 ? ($totalSales / $dayCount) : 0;
$averageDailyTransactions = $dayCount > 0 ? ($transactionCount / $dayCount) : 0;
$summaryByDate = fetchToDateSummaryByDate($registerRows);
$summaryByDate = fillMissingSummaryDates($summaryByDate, $startDate, $endDate);
$productPivotRows = [];
$productPivotByDate = [];
if ($reportType === 'by_product' || $reportType === 'by_source_channel') {
    $productPivotRows = buildProductPivotRows($registerRows);
    $productPivotByDate = buildProductPivotByDate($registerRows);
}
$sourceChannelSummaryRows = [];
$sourceChannelPivotTypeGroups = [];
if ($reportType === 'by_source_channel') {
    $sourceChannelSummaryRows = buildSourceChannelProductSummary($registerRows);
    $sourceChannelPivotTypeGroups = buildPivotTypeHeaderGroups($productPivotRows);
}
$customerSummaryRows = [];
$customerSourceGroups = [];
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
        $sourceChannel = trim((string)(isset($row['source_channel']) ? $row['source_channel'] : ''));
        if ($sourceChannel === '') {
            $sourceChannel = 'UNASSIGNED';
        }
        $sourceSort = isset($row['customer_source']) && is_numeric($row['customer_source']) ? (int)$row['customer_source'] : 999999;
        $sourceKey = $sourceSort . '|' . strtoupper($sourceChannel);
        $bucketKey = $sourceKey . '|' . $customerCode;

        if (!isset($customerBuckets[$bucketKey])) {
            $customerBuckets[$bucketKey] = [
                'source_channel' => $sourceChannel,
                'source_sort' => $sourceSort,
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'transaction_orders' => [],
                'qty' => 0.0,
                'amount' => 0.0,
                'cash' => 0.0,
                'on_account' => 0.0
            ];
        }

        if ($customerBuckets[$bucketKey]['customer_name'] === '' && $customerName !== '') {
            $customerBuckets[$bucketKey]['customer_name'] = $customerName;
        }

        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        if ($orderNo !== '') {
            $customerBuckets[$bucketKey]['transaction_orders'][$orderNo] = true;
        }

        $customerBuckets[$bucketKey]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $customerBuckets[$bucketKey]['amount'] += isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $customerBuckets[$bucketKey]['cash'] += isset($row['cash_once']) ? (float)$row['cash_once'] : 0.0;
        $customerBuckets[$bucketKey]['on_account'] += isset($row['on_account_once']) ? (float)$row['on_account_once'] : 0.0;
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
        $leftSourceSort = isset($left['source_sort']) ? (int)$left['source_sort'] : 999999;
        $rightSourceSort = isset($right['source_sort']) ? (int)$right['source_sort'] : 999999;
        if ($leftSourceSort !== $rightSourceSort) {
            return $leftSourceSort < $rightSourceSort ? -1 : 1;
        }

        $sourceCompare = strcasecmp((string)$left['source_channel'], (string)$right['source_channel']);
        if ($sourceCompare !== 0) {
            return $sourceCompare;
        }

        $codeCompare = strcasecmp((string)$left['customer_code'], (string)$right['customer_code']);
        if ($codeCompare !== 0) {
            return $codeCompare;
        }

        return strcasecmp((string)$left['customer_name'], (string)$right['customer_name']);
    });

    foreach ($customerSummaryRows as $row) {
        $sourceChannel = trim((string)(isset($row['source_channel']) ? $row['source_channel'] : ''));
        if ($sourceChannel === '') {
            $sourceChannel = 'UNASSIGNED';
        }
        $sourceSort = isset($row['source_sort']) ? (int)$row['source_sort'] : 999999;
        $sourceKey = $sourceSort . '|' . strtoupper($sourceChannel);
        if (!isset($customerSourceGroups[$sourceKey])) {
            $customerSourceGroups[$sourceKey] = [
                'source_channel' => $sourceChannel,
                'source_sort' => $sourceSort,
                'rows' => [],
                'transaction_count' => 0,
                'qty' => 0.0,
                'amount' => 0.0,
                'cash' => 0.0,
                'on_account' => 0.0
            ];
        }

        $customerSourceGroups[$sourceKey]['rows'][] = $row;
        $customerSourceGroups[$sourceKey]['transaction_count'] += isset($row['transaction_count']) ? (int)$row['transaction_count'] : 0;
        $customerSourceGroups[$sourceKey]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $customerSourceGroups[$sourceKey]['amount'] += isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $customerSourceGroups[$sourceKey]['cash'] += isset($row['cash']) ? (float)$row['cash'] : 0.0;
        $customerSourceGroups[$sourceKey]['on_account'] += isset($row['on_account']) ? (float)$row['on_account'] : 0.0;
    }
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
} elseif ($reportType === 'by_source_channel') {
    $reportMeta['records'] = count($sourceChannelSummaryRows);
} elseif ($reportType === 'by_product') {
    $reportMeta['records'] = count($summaryByDate);
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
    .pivot-product-header {
        min-width: 100px;
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
        min-width: 1600px;
        white-space: nowrap;
    }
    .todate-daily-sales-table tr.source-channel-row td {
        background-color: #eef2ff;
    }
    .todate-daily-sales-table tr.source-channel-subtotal td {
        background-color: #f3f4f6;
    }
</style>
<?php
$styles = ob_get_clean();

ob_start();
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-start">
        <div class="col-lg-6">
            <h3 class="mb-1">Daily Sales Summary</h3>
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
                            <option value="by_product" <?php echo $reportMeta['report_type'] === 'by_product' ? 'selected' : ''; ?>>By Product</option>
                            <option value="by_customer" <?php echo $reportMeta['report_type'] === 'by_customer' ? 'selected' : ''; ?>>By Customer</option>
                            <option value="by_source_channel" <?php echo $reportMeta['report_type'] === 'by_source_channel' ? 'selected' : ''; ?>>By Source Channel</option>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">View</button>
                    </div>
                    <div class="col-auto">
                        <?php
                        if ($reportMeta['report_type'] === 'by_customer') {
                            $exportPrintPage = 'print_todate_daily_sales_summary_customer.php';
                        } elseif ($reportMeta['report_type'] === 'by_source_channel') {
                            $exportPrintPage = 'print_todate_daily_sales_summary_source_channel.php';
                        } else {
                            $exportPrintPage = 'print_todate_daily_sales_summary.php';
                        }
                        ?>
                        <a class="btn btn-outline-secondary" href="<?php echo $exportPrintPage; ?>?start=<?php echo urlencode($reportMeta['start_date']); ?>&end=<?php echo urlencode($reportMeta['end_date']); ?>&amp;report_type=<?php echo urlencode($reportMeta['report_type']); ?>">
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
                <h5 class="card-title mb-0">Daily Sales Summary</h5>
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
                                    <?php $customerLineNumber = 1; ?>
                                    <?php foreach ($customerSourceGroups as $sourceGroup): ?>
                                        <tr class="fw-semibold source-channel-row">
                                            <td><?php echo htmlspecialchars($sourceGroup['source_channel']); ?></td>
                                            <td></td>
                                            <td></td>
                                            <td class="text-end"></td>
                                            <td class="text-end"></td>
                                            <td class="text-end"></td>
                                            <td class="text-end"></td>
                                            <td class="text-end"></td>
                                        </tr>
                                        <?php foreach ($sourceGroup['rows'] as $row): ?>
                                            <?php
                                            $summaryTotalTransactions += (int)$row['transaction_count'];
                                            $summaryTotalQty += (float)$row['qty'];
                                            $summaryTotalAmount += (float)$row['amount'];
                                            $summaryTotalCash += (float)$row['cash'];
                                            $summaryTotalOnAccount += (float)$row['on_account'];
                                            ?>
                                            <tr>
                                                <td class="text-center"><?php echo $customerLineNumber++; ?></td>
                                                <td><?php echo htmlspecialchars($row['customer_code']); ?></td>
                                                <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                                <td class="text-end"><?php echo number_format($row['transaction_count']); ?></td>
                                                <td class="text-end"><?php echo number_format($row['qty'], 2); ?></td>
                                                <td class="text-end"><?php echo number_format($row['amount'], 2); ?></td>
                                                <td class="text-end"><?php echo number_format($row['cash'], 2); ?></td>
                                                <td class="text-end"><?php echo number_format($row['on_account'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="fw-semibold source-channel-subtotal">
                                            <td>TOTAL - <?php echo htmlspecialchars($sourceGroup['source_channel']); ?></td>
                                            <td></td>
                                            <td></td>
                                            <td class="text-end"><?php echo number_format($sourceGroup['transaction_count']); ?></td>
                                            <td class="text-end"><?php echo number_format($sourceGroup['qty'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($sourceGroup['amount'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($sourceGroup['cash'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($sourceGroup['on_account'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <td colspan="3" class="text-end">GRAND TOTAL</td>
                                        <td class="text-end"><?php echo number_format($summaryTotalTransactions); ?></td>
                                        <td class="text-end"><?php echo number_format($summaryTotalQty, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($summaryTotalAmount, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($summaryTotalCash, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($summaryTotalOnAccount, 2); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php elseif ($reportMeta['report_type'] === 'by_source_channel'): ?>
                        <div class="table-responsive todate-daily-sales-table">
                            <table id="todate-daily-sales-table" class="table table-striped align-middle mb-0 w-100">
                                <thead>
                                    <?php if (!empty($productPivotRows)): ?>
                                        <tr class="daily-register-head-bottom text-center align-middle">
                                            <th rowspan="3">No.</th>
                                            <th rowspan="3">Source Channel</th>
                                            <th rowspan="3" class="text-end">Transactions</th>
                                            <th rowspan="3" class="text-end">Total Qty</th>
                                            <th colspan="<?php echo count($productPivotRows); ?>" class="text-center">ICE PRODUCTS</th>
                                        </tr>
                                        <tr class="daily-register-head-bottom text-center align-middle">
                                            <?php foreach ($sourceChannelPivotTypeGroups as $typeGroup): ?>
                                                <th colspan="<?php echo (int)$typeGroup['colspan']; ?>"><?php echo htmlspecialchars($typeGroup['label']); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                        <tr class="daily-register-head-bottom text-center align-middle">
                                            <?php foreach ($productPivotRows as $pivotData): ?>
                                                <?php
                                                $headerLabel = trim((string)$pivotData['product_code']);
                                                if ($headerLabel === '') {
                                                    $headerLabel = 'PRODUCT';
                                                }
                                                $headerSuffix = trim((string)$pivotData['uom']);
                                                ?>
                                                <th class="text-end pivot-product-header"><?php echo htmlspecialchars($headerLabel); ?><?php echo $headerSuffix !== '' ? '<br>(' . htmlspecialchars($headerSuffix) . ')' : ''; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php else: ?>
                                        <tr class="daily-register-head-bottom text-center align-middle">
                                            <th>No.</th>
                                            <th>Source Channel</th>
                                            <th class="text-end">Transactions</th>
                                            <th class="text-end">Total Qty</th>
                                        </tr>
                                    <?php endif; ?>
                                </thead>
                                <tbody>
                                    <?php
                                    $lineNumber = 1;
                                    $grandTotalTransactions = 0;
                                    $grandTotalQty = 0.0;
                                    $grandProductQty = [];
                                    foreach ($productPivotRows as $pivotData) {
                                        $pivotCodeKey = strtoupper(trim((string)$pivotData['product_code']));
                                        if ($pivotCodeKey !== '') {
                                            $grandProductQty[$pivotCodeKey] = 0.0;
                                        }
                                    }
                                    ?>
                                    <?php foreach ($sourceChannelSummaryRows as $sourceRow): ?>
                                        <?php
                                        $grandTotalTransactions += (int)$sourceRow['transaction_count'];
                                        $grandTotalQty += (float)$sourceRow['qty'];
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $lineNumber++; ?></td>
                                            <td><?php echo htmlspecialchars($sourceRow['source_channel']); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$sourceRow['transaction_count']); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$sourceRow['qty'], 2); ?></td>
                                            <?php foreach ($productPivotRows as $pivotData): ?>
                                                <?php
                                                $productCodeKey = strtoupper(trim((string)$pivotData['product_code']));
                                                $pivotQty = $productCodeKey !== '' && isset($sourceRow['products'][$productCodeKey])
                                                    ? (float)$sourceRow['products'][$productCodeKey]
                                                    : 0.0;
                                                if (isset($grandProductQty[$productCodeKey])) {
                                                    $grandProductQty[$productCodeKey] += $pivotQty;
                                                }
                                                ?>
                                                <td class="text-end"><?php echo number_format($pivotQty, 2); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td></td>
                                        <td class="text-end">TOTAL</td>
                                        <td class="text-end"><?php echo number_format($grandTotalTransactions); ?></td>
                                        <td class="text-end"><?php echo number_format($grandTotalQty, 2); ?></td>
                                        <?php foreach ($productPivotRows as $pivotData): ?>
                                            <?php
                                            $productCodeKey = strtoupper(trim((string)$pivotData['product_code']));
                                            $totalProductQty = isset($grandProductQty[$productCodeKey]) ? (float)$grandProductQty[$productCodeKey] : 0.0;
                                            ?>
                                            <td class="text-end"><?php echo number_format($totalProductQty, 2); ?></td>
                                        <?php endforeach; ?>
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
                                        <th>Date</th>
                                        <th class="text-end">Transactions</th>
                                        <th class="text-end">Total Qty</th>
                                        <th class="text-end">Total Amount</th>
                                        <th class="text-end">Cash</th>
                                        <th class="text-end">On Account</th>
                                        <?php foreach ($productPivotRows as $pivotData): ?>
                                            <?php
                                            $headerLabel = trim((string)$pivotData['product_code']);
                                            if ($headerLabel === '') {
                                                $headerLabel = 'PRODUCT';
                                            }
                                            $headerSuffix = trim((string)$pivotData['uom']);
                                            if ($headerSuffix !== '') {
                                                $headerLabel .= ' (' . $headerSuffix . ')';
                                            }
                                            ?>
                                            <th class="text-end pivot-product-header"><?php echo htmlspecialchars($headerLabel); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $lineNumber = 1;
                                    $grandTotalTransactions = 0;
                                    $grandTotalQty = 0.0;
                                    $grandTotalAmount = 0.0;
                                    $grandTotalCash = 0.0;
                                    $grandTotalOnAccount = 0.0;
                                    $grandProductQty = [];
                                    foreach ($productPivotRows as $pivotData) {
                                        $pivotCodeKey = strtoupper(trim((string)$pivotData['product_code']));
                                        if ($pivotCodeKey === '') {
                                            continue;
                                        }
                                        $grandProductQty[$pivotCodeKey] = 0.0;
                                    }
                                    ?>
                                    <?php foreach ($summaryByDate as $saleDate => $summaryRow): ?>
                                        <?php
                                        $grandTotalTransactions += (int)$summaryRow['transactions'];
                                        $grandTotalQty += (float)$summaryRow['qty'];
                                        $grandTotalAmount += (float)$summaryRow['amount'];
                                        $grandTotalCash += (float)$summaryRow['cash'];
                                        $grandTotalOnAccount += (float)$summaryRow['on_account'];
                                        ?>
                                        <tr>
                                            <td class="text-center"><?php echo $lineNumber; ?></td>
                                            <td><?php echo htmlspecialchars($saleDate); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$summaryRow['transactions']); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$summaryRow['qty'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$summaryRow['amount'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$summaryRow['cash'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$summaryRow['on_account'], 2); ?></td>
                                            <?php foreach ($productPivotRows as $pivotData): ?>
                                                <?php
                                                $productCodeKey = strtoupper(trim((string)$pivotData['product_code']));
                                                $pivotQty = 0.0;
                                                if (
                                                    $productCodeKey !== ''
                                                    && isset($productPivotByDate[$saleDate])
                                                    && isset($productPivotByDate[$saleDate][$productCodeKey])
                                                ) {
                                                    $pivotQty = isset($productPivotByDate[$saleDate][$productCodeKey]['qty'])
                                                        ? (float)$productPivotByDate[$saleDate][$productCodeKey]['qty']
                                                        : 0.0;
                                                    if (isset($grandProductQty[$productCodeKey])) {
                                                        $grandProductQty[$productCodeKey] += $pivotQty;
                                                    }
                                                }
                                                ?>
                                                <td class="text-end"><?php echo number_format($pivotQty, 2); ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                        <?php $lineNumber++; ?>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fw-bold">
                                        <td></td>
                                        <td class="text-end">TOTAL</td>
                                        <td class="text-end"><?php echo number_format($grandTotalTransactions); ?></td>
                                        <td class="text-end"><?php echo number_format($grandTotalQty, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($grandTotalAmount, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($grandTotalCash, 2); ?></td>
                                        <td class="text-end"><?php echo number_format($grandTotalOnAccount, 2); ?></td>
                                        <?php foreach ($productPivotRows as $pivotData): ?>
                                            <?php
                                            $productCodeKey = strtoupper(trim((string)$pivotData['product_code']));
                                            $totalProductQty = isset($grandProductQty[$productCodeKey]) ? (float)$grandProductQty[$productCodeKey] : 0.0;
                                            ?>
                                            <td class="text-end"><?php echo number_format($totalProductQty, 2); ?></td>
                                        <?php endforeach; ?>
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
                                        <th class="text-end">Amount Received</th>
                                        <th class="text-end">Change</th>
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
                                            <td class="text-end"><?php echo number_format($row['amt_received_once'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format($row['amt_change_once'], 2); ?></td>
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
