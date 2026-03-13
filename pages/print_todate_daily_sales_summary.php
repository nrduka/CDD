<?php
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}

session_start();

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartments = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$allowedRoles = ['ADMIN', 'CASHIER', 'REGISTRATION', 'COUNTER', 'DEPARTMENT'];
if (!in_array($role, $allowedRoles, true) && $assignedDepartments === '') {
    http_response_code(403);
    echo 'Access denied.';
    exit;
}

require_once __DIR__ . '/db2.php';
require_once __DIR__ . '/../vendor/autoload.php';

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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$today = date('Y-m-d');
$startInput = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
$endInput = isset($_GET['end']) ? trim((string)$_GET['end']) : '';
$reportTypeInput = isset($_GET['report_type']) ? trim((string)$_GET['report_type']) : '';
$reportType = in_array($reportTypeInput, ['by_product', 'by_customer'], true) ? $reportTypeInput : 'by_product';

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

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    $connectError = trim((string)mysqli_connect_error());
    echo $connectError !== '' ? 'Database connection unavailable. ' . $connectError : 'Database connection unavailable.';
    exit;
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
                   p.umu,
                   p.umu_per,
                   c.storeName,
                   c.firstName,
                   c.middleName,
                   c.lastName
             FROM sales s
             LEFT JOIN ice_product p ON p.product_code = s.product_code
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
                    'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                    'customer_code' => isset($row['customer_code']) ? (string)$row['customer_code'] : '',
                    'customer_name' => buildCustomerDisplayName($row),
                    'qty' => isset($row['qty']) ? (float)$row['qty'] : 0.0,
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => $productDescription,
                    'product_type' => $productType,
                    'uom' => trim((string)(isset($row['uom']) ? $row['uom'] : '')),
                    'umu' => trim((string)(isset($row['umu']) ? $row['umu'] : '')),
                    'umu_per' => isset($row['umu_per']) ? (float)$row['umu_per'] : 0.0,
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
                'amt_received' => 0.0,
                'amt_change' => 0.0,
                'cash' => 0.0,
                'on_account' => 0.0
            ];
            $transactionsPerDate[$saleDate] = [];
        }

        $totals[$saleDate]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $totals[$saleDate]['amount'] += isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $totals[$saleDate]['amt_received'] += isset($row['amt_received_once']) ? (float)$row['amt_received_once'] : 0.0;
        $totals[$saleDate]['amt_change'] += isset($row['amt_change_once']) ? (float)$row['amt_change_once'] : 0.0;
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

function columnIndexToLetter($columnIndex)
{
    $index = (int)$columnIndex;
    if ($index <= 0) {
        return 'A';
    }

    $letter = '';
    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $letter = chr(65 + $remainder) . $letter;
        $index = (int)(($index - $remainder - 1) / 26);
    }

    return $letter;
}

function isExcludedFromIceProductPivot($productType)
{
    $type = strtoupper(trim((string)$productType));

    return $type === 'OTHERS';
}

function resolvePivotProductMeta($row)
{
    $productCode = trim((string)(isset($row['product_code']) ? $row['product_code'] : ''));
    $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
    $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));

    if ($productType === '') {
        $productType = resolveProductTypeGroup($productDescription, $productCode);
    }

    if (isExcludedFromIceProductPivot($productType)) {
        return [
            'pivot_key' => '',
            'product_type' => $productType,
            'product_code' => $productCode,
            'product_description' => $productDescription,
            'exclude' => true,
        ];
    }

    return [
        'pivot_key' => $productCode !== '' ? strtoupper($productCode) : '',
        'product_type' => $productType,
        'product_code' => $productCode,
        'product_description' => $productDescription,
        'exclude' => false,
    ];
}

function buildProductPivotRows($registerRows)
{
    $pivotByProduct = [];
    foreach ($registerRows as $index => $row) {
        $productMeta = resolvePivotProductMeta($row);
        if (!empty($productMeta['exclude'])) {
            continue;
        }
        $productCode = $productMeta['product_code'];
        $productDescription = $productMeta['product_description'];
        $productType = $productMeta['product_type'];
        $uom = trim((string)(isset($row['uom']) ? $row['uom'] : ''));
        $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;

        $key = $productMeta['pivot_key'] !== '' ? $productMeta['pivot_key'] : '__NO_CODE_' . $index;
        if (!isset($pivotByProduct[$key])) {
            $pivotByProduct[$key] = [
                'product_type' => $productType,
                'product_code' => $productCode,
                'product_description' => $productDescription,
                'qty' => 0.0,
                'uom' => $uom
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
        $productMeta = resolvePivotProductMeta($row);
        if (!empty($productMeta['exclude'])) {
            continue;
        }
        $productCodeKey = strtoupper(trim((string)$productMeta['pivot_key']));
        if ($saleDate === '' || $productCodeKey === '') {
            continue;
        }

        if (!isset($pivotByDate[$saleDate])) {
            $pivotByDate[$saleDate] = [];
        }
        if (!isset($pivotByDate[$saleDate][$productCodeKey])) {
            $pivotByDate[$saleDate][$productCodeKey] = [
                'qty' => 0.0,
                'uom' => trim((string)(isset($row['uom']) ? $row['uom'] : ''))
            ];
        }

        $pivotByDate[$saleDate][$productCodeKey]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        if ($pivotByDate[$saleDate][$productCodeKey]['uom'] === '') {
            $pivotByDate[$saleDate][$productCodeKey]['uom'] = trim((string)(isset($row['uom']) ? $row['uom'] : ''));
        }
    }

    return $pivotByDate;
}

function buildOthersPivotRows($registerRows)
{
    $pivotByProduct = [];
    foreach ($registerRows as $index => $row) {
        $productCode = trim((string)(isset($row['product_code']) ? $row['product_code'] : ''));
        $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
        $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
        if ($productType === '') {
            $productType = resolveProductTypeGroup($productDescription, $productCode);
        }
        if (strtoupper($productType) !== 'OTHERS') {
            continue;
        }

        $uom = trim((string)(isset($row['uom']) ? $row['uom'] : ''));
        $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $key = $productCode !== '' ? strtoupper($productCode) : '__NO_CODE_' . $index;

        if (!isset($pivotByProduct[$key])) {
            $pivotByProduct[$key] = [
                'product_type' => $productType,
                'product_code' => $productCode,
                'product_description' => $productDescription,
                'qty' => 0.0,
                'uom' => $uom
            ];
        }

        $pivotByProduct[$key]['qty'] += $qty;
        if ($pivotByProduct[$key]['product_description'] === '' && $productDescription !== '') {
            $pivotByProduct[$key]['product_description'] = $productDescription;
        }
        if ($pivotByProduct[$key]['uom'] === '' && $uom !== '') {
            $pivotByProduct[$key]['uom'] = $uom;
        }
    }

    $pivotRows = array_values($pivotByProduct);
    usort($pivotRows, function ($left, $right) {
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

function buildOthersPivotByDate($registerRows)
{
    $pivotByDate = [];

    foreach ($registerRows as $row) {
        $saleDate = trim((string)(isset($row['date']) ? $row['date'] : ''));
        $productCode = trim((string)(isset($row['product_code']) ? $row['product_code'] : ''));
        $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
        $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
        if ($productType === '') {
            $productType = resolveProductTypeGroup($productDescription, $productCode);
        }
        if (strtoupper($productType) !== 'OTHERS') {
            continue;
        }

        $productCodeKey = strtoupper($productCode);
        if ($saleDate === '' || $productCodeKey === '') {
            continue;
        }

        if (!isset($pivotByDate[$saleDate])) {
            $pivotByDate[$saleDate] = [];
        }
        if (!isset($pivotByDate[$saleDate][$productCodeKey])) {
            $pivotByDate[$saleDate][$productCodeKey] = [
                'qty' => 0.0,
                'uom' => trim((string)(isset($row['uom']) ? $row['uom'] : ''))
            ];
        }

        $pivotByDate[$saleDate][$productCodeKey]['qty'] += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        if ($pivotByDate[$saleDate][$productCodeKey]['uom'] === '') {
            $pivotByDate[$saleDate][$productCodeKey]['uom'] = trim((string)(isset($row['uom']) ? $row['uom'] : ''));
        }
    }

    return $pivotByDate;
}

function formatRangeLabel($startDate, $endDate)
{
    $startLabel = date('F d, Y', strtotime($startDate));
    $endLabel = date('F d, Y', strtotime($endDate));
    return $startLabel === $endLabel ? $startLabel : $startLabel . ' - ' . $endLabel;
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
            'amt_received' => 0.0,
            'amt_change' => 0.0,
            'cash' => 0.0,
            'on_account' => 0.0
        ];
    }

    return $filled;
}

function convertRegisterRowsToUmu($registerRows)
{
    $convertedRows = [];
    foreach ($registerRows as $row) {
        $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $umuPer = isset($row['umu_per']) ? (float)$row['umu_per'] : 0.0;
        if ($umuPer <= 0) {
            // Fall back to 1:1 when UMU conversion is not configured.
            $umuPer = 1.0;
        }

        $umuUnit = trim((string)(isset($row['umu']) ? $row['umu'] : ''));
        if ($umuUnit === '') {
            $umuUnit = trim((string)(isset($row['uom']) ? $row['uom'] : ''));
        }

        $row['qty'] = $qty * $umuPer;
        $row['uom'] = $umuUnit;
        $convertedRows[] = $row;
    }

    return $convertedRows;
}

$registerRows = fetchToDateRegisterRows($mysqli, $startDate, $endDate);
$registerRows = enrichRegisterRowsWithOrderPayments($registerRows);
$productPivotRows = buildProductPivotRows($registerRows);
$productPivotByDate = buildProductPivotByDate($registerRows);
$totalSales = 0;
$uniqueOrders = [];
foreach ($registerRows as $row) {
    $totalSales += isset($row['amount']) ? (float)$row['amount'] : 0;
    $orderKey = isset($row['order_no']) ? (string)$row['order_no'] : '';
    if ($orderKey !== '') {
        $uniqueOrders[$orderKey] = true;
    }
}

$transactionCount = count($uniqueOrders);
$dayCount = $startObject && $endObject ? $startObject->diff($endObject)->days + 1 : 1;
$averageDailySales = $dayCount > 0 ? ($totalSales / $dayCount) : 0;
$averageDailyTransactions = $dayCount > 0 ? ($transactionCount / $dayCount) : 0;
$summaryByDate = fetchToDateSummaryByDate($registerRows);
$summaryByDate = fillMissingSummaryDates($summaryByDate, $startDate, $endDate);

$rangeLabel = formatRangeLabel($startDate, $endDate);
$reportRangeLabel = 'from ' . date('F d', strtotime($startDate)) . ' to ' . date('d, Y', strtotime($endDate));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Sales Register');

$sheetTitle = 'DAILY SALES REGISTER';
if ($reportType === 'by_product') {
    $sheetTitle = 'DAILY SALES REGISTER (BY PRODUCT)';
} elseif ($reportType === 'by_customer') {
    $sheetTitle = 'DAILY SALES REGISTER (BY CUSTOMER)';
}
$sheet->setCellValue('A1', $sheetTitle);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', $reportRangeLabel);
$sheet->setCellValue('A3', 'Generated: ' . $generatedAt);

$headerStyle = [
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'e5e7eb']
    ]
];

$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];
$summaryBorderStyle = [
    'borders' => [
        'outline' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

$currencyFormat = '#,##0.00';

$sheet->setCellValue('I1', 'TOTAL SALES');
$sheet->setCellValue('K1', $totalSales);
$sheet->setCellValue('I2', 'TOTAL TRANSACTION COUNT');
$sheet->setCellValue('K2', $transactionCount);
$sheet->setCellValue('I3', 'AVERAGE DAILY SALES');
$sheet->setCellValue('K3', $averageDailySales);
$sheet->setCellValue('I4', 'AVERAGE DAILY TRANSACTION COUNT');
$sheet->setCellValue('K4', $averageDailyTransactions);
$sheet->getStyle('I1:K1')->applyFromArray($summaryBorderStyle);
$sheet->getStyle('I2:K2')->applyFromArray($summaryBorderStyle);
$sheet->getStyle('I3:K3')->applyFromArray($summaryBorderStyle);
$sheet->getStyle('I4:K4')->applyFromArray($summaryBorderStyle);
$sheet->getStyle('I1:I4')->getFont()->setBold(true);
$sheet->getStyle('K1')->getNumberFormat()->setFormatCode($currencyFormat);
$sheet->getStyle('K2')->getNumberFormat()->setFormatCode('0');
$sheet->getStyle('K3')->getNumberFormat()->setFormatCode($currencyFormat);
$sheet->getStyle('K4')->getNumberFormat()->setFormatCode('0');
$sheet->getStyle('K1:K4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$headerRow = 9;
$dataRow = 10;

if ($reportType === 'by_product') {
    $headers = [
        'NO.',
        'PRODUCT TYPE',
        'PRODUCT CODE',
        'PRODUCT DESCRIPTION',
        'QUANTITY',
        'UNIT PRICE',
        'AMOUNT',
        'CASH',
        'ON ACCOUNT'
    ];

    $groupedRows = [];
    foreach ($registerRows as $row) {
        $productCode = isset($row['product_code']) ? (string)$row['product_code'] : '';
        $productDescription = isset($row['product_description']) ? (string)$row['product_description'] : '';
        $unitPrice = isset($row['unit_price']) ? (float)$row['unit_price'] : 0;
        $groupLabel = resolveProductTypeGroup($productDescription, $productCode);
        $itemKey = $productCode . '|' . $productDescription . '|' . number_format($unitPrice, 4, '.', '');

        if (!isset($groupedRows[$groupLabel])) {
            $groupedRows[$groupLabel] = [
                'subtotal_qty' => 0,
                'subtotal_amount' => 0,
                'subtotal_cash' => 0,
                'subtotal_on_account' => 0,
                'items' => []
            ];
        }

        if (!isset($groupedRows[$groupLabel]['items'][$itemKey])) {
            $groupedRows[$groupLabel]['items'][$itemKey] = [
                'product_code' => $productCode,
                'product_description' => $productDescription,
                'unit_price' => $unitPrice,
                'qty' => 0,
                'amount' => 0,
                'cash' => 0,
                'on_account' => 0
            ];
        }

        $qty = isset($row['qty']) ? (float)$row['qty'] : 0;
        $amount = isset($row['amount']) ? (float)$row['amount'] : 0;
        $cash = isset($row['cash_allocated']) ? (float)$row['cash_allocated'] : 0.0;
        $onAccount = isset($row['on_account_allocated']) ? (float)$row['on_account_allocated'] : 0.0;
        $groupedRows[$groupLabel]['items'][$itemKey]['qty'] += $qty;
        $groupedRows[$groupLabel]['items'][$itemKey]['amount'] += $amount;
        $groupedRows[$groupLabel]['items'][$itemKey]['cash'] += $cash;
        $groupedRows[$groupLabel]['items'][$itemKey]['on_account'] += $onAccount;
        $groupedRows[$groupLabel]['subtotal_qty'] += $qty;
        $groupedRows[$groupLabel]['subtotal_amount'] += $amount;
        $groupedRows[$groupLabel]['subtotal_cash'] += $cash;
        $groupedRows[$groupLabel]['subtotal_on_account'] += $onAccount;
    }

    if (!empty($groupedRows)) {
        ksort($groupedRows, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($groupedRows as &$groupData) {
            uasort($groupData['items'], function ($left, $right) {
                $leftCode = strtoupper(trim((string)$left['product_code']));
                $rightCode = strtoupper(trim((string)$right['product_code']));
                if ($leftCode === $rightCode) {
                    return strcasecmp((string)$left['product_description'], (string)$right['product_description']);
                }

                return strcasecmp($leftCode, $rightCode);
            });
        }
        unset($groupData);
    }

    $sheet->fromArray($headers, null, 'A' . $headerRow);
    $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$headerRow}:I{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("A{$headerRow}:I{$headerRow}")->getFont()->getColor()->setRGB('000000');

    $currentRow = $dataRow;
    $lineNumber = 1;
    $grandTotalQty = 0;
    $grandTotalAmount = 0;
    $grandTotalCash = 0;
    $grandTotalOnAccount = 0;

    foreach ($groupedRows as $groupLabel => $groupData) {
        foreach ($groupData['items'] as $item) {
            $sheet->setCellValue("A{$currentRow}", $lineNumber);
            $sheet->setCellValue("B{$currentRow}", $groupLabel);
            $sheet->setCellValueExplicit("C{$currentRow}", (string)$item['product_code'], DataType::TYPE_STRING);
            $sheet->setCellValue("D{$currentRow}", (string)$item['product_description']);
            $sheet->setCellValue("E{$currentRow}", (float)$item['qty']);
            $sheet->setCellValue("F{$currentRow}", (float)$item['unit_price']);
            $sheet->setCellValue("G{$currentRow}", (float)$item['amount']);
            $sheet->setCellValue("H{$currentRow}", (float)$item['cash']);
            $sheet->setCellValue("I{$currentRow}", (float)$item['on_account']);
            $currentRow++;
            $lineNumber++;
        }

        $sheet->setCellValue("B{$currentRow}", 'SUBTOTAL - ' . $groupLabel);
        $sheet->setCellValue("E{$currentRow}", (float)$groupData['subtotal_qty']);
        $sheet->setCellValue("G{$currentRow}", (float)$groupData['subtotal_amount']);
        $sheet->setCellValue("H{$currentRow}", (float)$groupData['subtotal_cash']);
        $sheet->setCellValue("I{$currentRow}", (float)$groupData['subtotal_on_account']);
        $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getFont()->setBold(true);
        $currentRow++;

        $grandTotalQty += (float)$groupData['subtotal_qty'];
        $grandTotalAmount += (float)$groupData['subtotal_amount'];
        $grandTotalCash += (float)$groupData['subtotal_cash'];
        $grandTotalOnAccount += (float)$groupData['subtotal_on_account'];
    }

    $totalRow = $currentRow;
    $sheet->setCellValue("D{$totalRow}", 'TOTAL');
    $sheet->setCellValue("E{$totalRow}", $grandTotalQty);
    $sheet->setCellValue("G{$totalRow}", $grandTotalAmount);
    $sheet->setCellValue("H{$totalRow}", $grandTotalCash);
    $sheet->setCellValue("I{$totalRow}", $grandTotalOnAccount);
    $sheet->getStyle("A{$totalRow}:I{$totalRow}")->getFont()->setBold(true);

    $lastDataRow = $totalRow;
    if ($lastDataRow >= $headerRow) {
        $sheet->getStyle("A{$headerRow}:I{$lastDataRow}")->applyFromArray($borderStyle);
    }
    if ($lastDataRow >= $dataRow) {
        $sheet->getStyle("E{$dataRow}:E{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("F{$dataRow}:I{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    }
    $sheet->getStyle("A{$headerRow}:C{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("E{$headerRow}:I{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
    $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(18);
    $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(16);
    $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(42);
    $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(14);
    $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(14);
    $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(16);
    $sheet->getColumnDimension('H')->setAutoSize(false)->setWidth(16);
    $sheet->getColumnDimension('I')->setAutoSize(false)->setWidth(16);
} elseif ($reportType === 'by_customer') {
    $headers = [
        'NO.',
        'CUSTOMER CODE',
        'CUSTOMER NAME',
        'TRANSACTION COUNT',
        'QUANTITY',
        'AMOUNT',
        'CASH',
        'ON ACCOUNT'
    ];

    $groupedRows = [];
    foreach ($registerRows as $row) {
        $customerCode = trim((string)(isset($row['customer_code']) ? $row['customer_code'] : ''));
        if ($customerCode === '') {
            $customerCode = 'UNASSIGNED';
        }
        $customerName = trim((string)(isset($row['customer_name']) ? $row['customer_name'] : ''));
        if ($customerName === '' && $customerCode !== 'UNASSIGNED') {
            $customerName = $customerCode;
        }

        if (!isset($groupedRows[$customerCode])) {
            $groupedRows[$customerCode] = [
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'transaction_orders' => [],
                'qty' => 0.0,
                'amount' => 0.0,
                'cash' => 0.0,
                'on_account' => 0.0
            ];
        }

        if ($groupedRows[$customerCode]['customer_name'] === '' && $customerName !== '') {
            $groupedRows[$customerCode]['customer_name'] = $customerName;
        }

        $orderNo = trim((string)(isset($row['order_no']) ? $row['order_no'] : ''));
        if ($orderNo !== '') {
            $groupedRows[$customerCode]['transaction_orders'][$orderNo] = true;
        }

        $qty = isset($row['qty']) ? (float)$row['qty'] : 0;
        $amount = isset($row['amount']) ? (float)$row['amount'] : 0;
        $cash = isset($row['cash_once']) ? (float)$row['cash_once'] : 0.0;
        $onAccount = isset($row['on_account_once']) ? (float)$row['on_account_once'] : 0.0;

        $groupedRows[$customerCode]['qty'] += $qty;
        $groupedRows[$customerCode]['amount'] += $amount;
        $groupedRows[$customerCode]['cash'] += $cash;
        $groupedRows[$customerCode]['on_account'] += $onAccount;
    }

    foreach ($groupedRows as &$groupData) {
        $groupData['transaction_count'] = count($groupData['transaction_orders']);
        unset($groupData['transaction_orders']);
    }
    unset($groupData);

    if (!empty($groupedRows)) {
        uasort($groupedRows, function ($left, $right) {
            $codeCompare = strcasecmp((string)$left['customer_code'], (string)$right['customer_code']);
            if ($codeCompare !== 0) {
                return $codeCompare;
            }

            return strcasecmp((string)$left['customer_name'], (string)$right['customer_name']);
        });
    }

    $sheet->fromArray($headers, null, 'A' . $headerRow);
    $sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$headerRow}:H{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("A{$headerRow}:H{$headerRow}")->getFont()->getColor()->setRGB('000000');

    $currentRow = $dataRow;
    $lineNumber = 1;
    $grandTotalTransactions = 0;
    $grandTotalQty = 0.0;
    $grandTotalAmount = 0.0;
    $grandTotalCash = 0.0;
    $grandTotalOnAccount = 0.0;

    foreach ($groupedRows as $row) {
        $transactionCountPerCustomer = isset($row['transaction_count']) ? (int)$row['transaction_count'] : 0;
        $sheet->setCellValue("A{$currentRow}", $lineNumber);
        $sheet->setCellValueExplicit("B{$currentRow}", (string)$row['customer_code'], DataType::TYPE_STRING);
        $sheet->setCellValue("C{$currentRow}", (string)$row['customer_name']);
        $sheet->setCellValue("D{$currentRow}", $transactionCountPerCustomer);
        $sheet->setCellValue("E{$currentRow}", (float)$row['qty']);
        $sheet->setCellValue("F{$currentRow}", (float)$row['amount']);
        $sheet->setCellValue("G{$currentRow}", (float)$row['cash']);
        $sheet->setCellValue("H{$currentRow}", (float)$row['on_account']);
        $currentRow++;
        $lineNumber++;

        $grandTotalTransactions += $transactionCountPerCustomer;
        $grandTotalQty += isset($row['qty']) ? (float)$row['qty'] : 0.0;
        $grandTotalAmount += isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $grandTotalCash += isset($row['cash']) ? (float)$row['cash'] : 0.0;
        $grandTotalOnAccount += isset($row['on_account']) ? (float)$row['on_account'] : 0.0;
    }

    $totalRow = $currentRow;
    $sheet->setCellValue("C{$totalRow}", 'TOTAL');
    $sheet->setCellValue("D{$totalRow}", $grandTotalTransactions);
    $sheet->setCellValue("E{$totalRow}", $grandTotalQty);
    $sheet->setCellValue("F{$totalRow}", $grandTotalAmount);
    $sheet->setCellValue("G{$totalRow}", $grandTotalCash);
    $sheet->setCellValue("H{$totalRow}", $grandTotalOnAccount);
    $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getFont()->setBold(true);

    $lastDataRow = $totalRow;
    if ($lastDataRow >= $headerRow) {
        $sheet->getStyle("A{$headerRow}:H{$lastDataRow}")->applyFromArray($borderStyle);
    }
    if ($lastDataRow >= $dataRow) {
        $sheet->getStyle("D{$dataRow}:D{$lastDataRow}")->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle("E{$dataRow}:E{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("F{$dataRow}:H{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    }
    $sheet->getStyle("A{$headerRow}:D{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("E{$headerRow}:H{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
    $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(18);
    $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(38);
    $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(16);
    $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(14);
    $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(18);
    $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(18);
    $sheet->getColumnDimension('H')->setAutoSize(false)->setWidth(18);
} else {
    $headers = [
        'NO.',
        'DATE',
        'TIME',
        'DR NO.',
        'CUSTOMER CODE',
        'CUSTOMER NAME',
        'QUANTITY',
        'PRODUCT CODE',
        'PRODUCT DESCRIPTION',
        'UNIT PRICE',
        'AMOUNT',
        'AMOUNT RECEIVED',
        'CHANGE',
        'CASH',
        'ON ACCOUNT'
    ];

    $sheet->fromArray($headers, null, 'A' . $headerRow);
    $sheet->getStyle("A{$headerRow}:O{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$headerRow}:O{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("A{$headerRow}:O{$headerRow}")->getFont()->getColor()->setRGB('000000');

    $currentRow = $dataRow;
    $transactionSequence = 0;
    $transactionNumberByOrder = [];
    $transactionRenderedByOrder = [];
    foreach ($registerRows as $index => $row) {
        $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
        $transactionKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;
        if (!isset($transactionNumberByOrder[$transactionKey])) {
            $transactionSequence++;
            $transactionNumberByOrder[$transactionKey] = $transactionSequence;
        }
        $showTransactionNumber = !isset($transactionRenderedByOrder[$transactionKey]);
        $transactionRenderedByOrder[$transactionKey] = true;
        $orderNoDisplay = $orderNo !== '' ? 'DR' . $orderNo : '';
        $sheet->setCellValue("A{$currentRow}", $showTransactionNumber ? $transactionNumberByOrder[$transactionKey] : '');
        $sheet->setCellValueExplicit("B{$currentRow}", $row['date'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("C{$currentRow}", $row['time'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("D{$currentRow}", $orderNoDisplay, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("E{$currentRow}", $row['customer_code'], DataType::TYPE_STRING);
        $sheet->setCellValue("F{$currentRow}", isset($row['customer_name']) ? (string)$row['customer_name'] : '');
        $sheet->setCellValue("G{$currentRow}", $row['qty']);
        $sheet->setCellValueExplicit("H{$currentRow}", $row['product_code'], DataType::TYPE_STRING);
        $sheet->setCellValue("I{$currentRow}", $row['product_description']);
        $sheet->setCellValue("J{$currentRow}", $row['unit_price']);
        $sheet->setCellValue("K{$currentRow}", $row['amount']);
        $sheet->setCellValue("L{$currentRow}", isset($row['amt_received_once']) ? (float)$row['amt_received_once'] : 0.0);
        $sheet->setCellValue("M{$currentRow}", isset($row['amt_change_once']) ? (float)$row['amt_change_once'] : 0.0);
        $sheet->setCellValue("N{$currentRow}", isset($row['cash_once']) ? (float)$row['cash_once'] : 0.0);
        $sheet->setCellValue("O{$currentRow}", isset($row['on_account_once']) ? (float)$row['on_account_once'] : 0.0);

        $currentRow++;
    }

    $lastDetailRow = $currentRow - 1;
    $totalRow = $currentRow;
    $sheet->setCellValue("I{$totalRow}", 'TOTAL');
    $sheet->getStyle("A{$totalRow}:O{$totalRow}")->getFont()->setBold(true);
    if ($lastDetailRow >= $dataRow) {
        $sheet->setCellValue("K{$totalRow}", "=SUM(K{$dataRow}:K{$lastDetailRow})");
        $sheet->setCellValue("L{$totalRow}", "=SUM(L{$dataRow}:L{$lastDetailRow})");
        $sheet->setCellValue("M{$totalRow}", "=SUM(M{$dataRow}:M{$lastDetailRow})");
        $sheet->setCellValue("N{$totalRow}", "=SUM(N{$dataRow}:N{$lastDetailRow})");
        $sheet->setCellValue("O{$totalRow}", "=SUM(O{$dataRow}:O{$lastDetailRow})");
    } else {
        $sheet->setCellValue("K{$totalRow}", 0);
        $sheet->setCellValue("L{$totalRow}", 0);
        $sheet->setCellValue("M{$totalRow}", 0);
        $sheet->setCellValue("N{$totalRow}", 0);
        $sheet->setCellValue("O{$totalRow}", 0);
    }

    $lastDataRow = $totalRow;
    if ($lastDataRow >= $headerRow) {
        $sheet->getStyle("A{$headerRow}:O{$lastDataRow}")->applyFromArray($borderStyle);
    }
    if ($lastDataRow >= $dataRow) {
        $sheet->getStyle("G{$dataRow}:G{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("J{$dataRow}:O{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    }
    $sheet->getStyle("B{$headerRow}:H{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    foreach (range('A', 'O') as $column) {
        if (in_array($column, ['B', 'C', 'D', 'E', 'G', 'H'], true)) {
            $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth(15);
            continue;
        }
        if ($column === 'F') {
            $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth(26);
            continue;
        }
        if (in_array($column, ['J', 'K', 'L', 'M', 'N', 'O'], true)) {
            $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth(20);
            continue;
        }
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
    $sheet->getColumnDimension('I')->setAutoSize(false)->setWidth(40);
}

$summarySheet = $spreadsheet->createSheet();
$summarySheet->setTitle('Sales Summary');

$summaryTitle = 'Sales Summary by Date';
if ($reportType === 'by_product') {
    $summaryTitle = 'Sales Summary by Date - By Product';
}
$summarySheet->setCellValue('A1', $summaryTitle);
$summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$summarySheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
$summarySheet->setCellValue('A3', 'Generated: ' . $generatedAt);

$summaryHeaderRow = 8;
$summaryDataRow = $reportType === 'by_product' ? 10 : 9;
$summaryHeaders = [
    'NO.',
    'DATE',
    'TOTAL TRANSACTION',
    'TOTAL QTY',
    'TOTAL AMOUNT',
    'AMOUNT RECEIVED',
    'CHANGE',
    'CASH',
    'ON ACCOUNT'
];

$summarySheet->fromArray($summaryHeaders, null, 'A' . $summaryHeaderRow);
$summarySheet->getStyle("A{$summaryHeaderRow}:I{$summaryHeaderRow}")->applyFromArray($headerStyle);
$summarySheet->getStyle("A{$summaryHeaderRow}:I{$summaryHeaderRow}")
    ->getAlignment()
    ->setWrapText(true)
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$currentRow = $summaryDataRow;
$counter = 1;
$summaryTotals = [
    'transactions' => 0,
    'qty' => 0,
    'amount' => 0,
    'amt_received' => 0,
    'amt_change' => 0,
    'cash' => 0,
    'on_account' => 0
];
foreach ($summaryByDate as $saleDate => $totals) {
    $summarySheet->setCellValue("A{$currentRow}", $counter);
    $summarySheet->setCellValue("B{$currentRow}", $saleDate);
    $summarySheet->setCellValue("C{$currentRow}", $totals['transactions']);
    $summarySheet->setCellValue("D{$currentRow}", $totals['qty']);
    $summarySheet->setCellValue("E{$currentRow}", $totals['amount']);
    $summarySheet->setCellValue("F{$currentRow}", $totals['amt_received']);
    $summarySheet->setCellValue("G{$currentRow}", $totals['amt_change']);
    $summarySheet->setCellValue("H{$currentRow}", $totals['cash']);
    $summarySheet->setCellValue("I{$currentRow}", $totals['on_account']);
    $summarySheet->getStyle("D{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $summarySheet->getStyle("E{$currentRow}:I{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $summaryTotals['transactions'] += $totals['transactions'];
    $summaryTotals['qty'] += $totals['qty'];
    $summaryTotals['amount'] += $totals['amount'];
    $summaryTotals['amt_received'] += $totals['amt_received'];
    $summaryTotals['amt_change'] += $totals['amt_change'];
    $summaryTotals['cash'] += $totals['cash'];
    $summaryTotals['on_account'] += $totals['on_account'];
    $currentRow++;
    $counter++;
}

$lastSummaryDetailRow = $currentRow - 1;
$summarySheet->setCellValue("B{$currentRow}", 'Total');
$summarySheet->setCellValue("C{$currentRow}", $summaryTotals['transactions']);
$summarySheet->setCellValue("D{$currentRow}", $summaryTotals['qty']);
$summarySheet->setCellValue("E{$currentRow}", $summaryTotals['amount']);
$summarySheet->setCellValue("F{$currentRow}", $summaryTotals['amt_received']);
$summarySheet->setCellValue("G{$currentRow}", $summaryTotals['amt_change']);
$summarySheet->setCellValue("H{$currentRow}", $summaryTotals['cash']);
$summarySheet->setCellValue("I{$currentRow}", $summaryTotals['on_account']);
$summarySheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray($headerStyle);
$summarySheet->getStyle("A{$currentRow}:I{$currentRow}")->getFont()->setBold(true);
$summarySheet->getStyle("D{$currentRow}:I{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

$summaryTotalRow = $currentRow;
if ($reportType === 'by_product' && !empty($productPivotRows)) {
    $summaryPivotHeaderRow = 6;
    $summaryPivotCodeRow = 7;
    $summaryPivotDescriptionRow = 8;
    $summaryPivotSubHeaderRow = 9;
    $summaryPivotTitleRow = 5;
    $summaryPivotStartColumnIndex = 10; // J
    $summaryPivotLastColumnIndex = $summaryPivotStartColumnIndex - 1;
    $summaryProductColumnsByCode = [];
    $summaryProductTypeGroups = [];
    foreach ($productPivotRows as $pivotIndex => $pivotData) {
        $qtyColumnIndex = $summaryPivotStartColumnIndex + ($pivotIndex * 2);
        $uomColumnIndex = $qtyColumnIndex + 1;
        $qtyColumnLetter = columnIndexToLetter($qtyColumnIndex);
        $uomColumnLetter = columnIndexToLetter($uomColumnIndex);
        $summaryPivotLastColumnIndex = $uomColumnIndex;

        $productTypeLabel = isset($pivotData['product_type']) ? trim((string)$pivotData['product_type']) : '';
        $productTypeKey = strtoupper($productTypeLabel);
        if (empty($summaryProductTypeGroups) || $summaryProductTypeGroups[count($summaryProductTypeGroups) - 1]['key'] !== $productTypeKey) {
            $summaryProductTypeGroups[] = [
                'key' => $productTypeKey,
                'label' => $productTypeLabel,
                'start_column_index' => $qtyColumnIndex,
                'end_column_index' => $uomColumnIndex
            ];
        } else {
            $lastTypeGroupIndex = count($summaryProductTypeGroups) - 1;
            $summaryProductTypeGroups[$lastTypeGroupIndex]['end_column_index'] = $uomColumnIndex;
            if ($summaryProductTypeGroups[$lastTypeGroupIndex]['label'] === '' && $productTypeLabel !== '') {
                $summaryProductTypeGroups[$lastTypeGroupIndex]['label'] = $productTypeLabel;
            }
        }

        $summarySheet->mergeCells("{$qtyColumnLetter}{$summaryPivotCodeRow}:{$uomColumnLetter}{$summaryPivotCodeRow}");
        $summarySheet->mergeCells("{$qtyColumnLetter}{$summaryPivotDescriptionRow}:{$uomColumnLetter}{$summaryPivotDescriptionRow}");
        $summarySheet->setCellValue("{$qtyColumnLetter}{$summaryPivotCodeRow}", isset($pivotData['product_code']) ? (string)$pivotData['product_code'] : '');
        $summarySheet->setCellValue("{$qtyColumnLetter}{$summaryPivotDescriptionRow}", isset($pivotData['product_description']) ? (string)$pivotData['product_description'] : '');
        $summarySheet->setCellValue("{$qtyColumnLetter}{$summaryPivotSubHeaderRow}", 'QTY');
        $summarySheet->setCellValue("{$uomColumnLetter}{$summaryPivotSubHeaderRow}", 'UOM');

        $summarySheet->getStyle("{$qtyColumnLetter}{$summaryPivotHeaderRow}:{$uomColumnLetter}{$summaryPivotSubHeaderRow}")
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setRGB('000000');
        $summarySheet->getStyle("{$qtyColumnLetter}{$summaryPivotHeaderRow}:{$uomColumnLetter}{$summaryPivotSubHeaderRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $summarySheet->getStyle("{$qtyColumnLetter}{$summaryPivotHeaderRow}:{$uomColumnLetter}{$summaryPivotSubHeaderRow}")
            ->applyFromArray($borderStyle);

        $summarySheet->getColumnDimension($qtyColumnLetter)->setAutoSize(false)->setWidth(10);
        $summarySheet->getColumnDimension($uomColumnLetter)->setAutoSize(false)->setWidth(8);

        $productCodeKey = strtoupper(trim((string)(isset($pivotData['product_code']) ? $pivotData['product_code'] : '')));
        if ($productCodeKey !== '') {
            $summaryProductColumnsByCode[$productCodeKey] = [
                'qty_column' => $qtyColumnLetter,
                'uom_column' => $uomColumnLetter,
                'uom' => isset($pivotData['uom']) ? (string)$pivotData['uom'] : ''
            ];
        }
    }

    foreach ($summaryProductTypeGroups as $typeGroup) {
        $typeStartColumnLetter = columnIndexToLetter($typeGroup['start_column_index']);
        $typeEndColumnLetter = columnIndexToLetter($typeGroup['end_column_index']);
        if ($typeStartColumnLetter !== $typeEndColumnLetter) {
            $summarySheet->mergeCells("{$typeStartColumnLetter}{$summaryPivotHeaderRow}:{$typeEndColumnLetter}{$summaryPivotHeaderRow}");
        }
        $summarySheet->setCellValue("{$typeStartColumnLetter}{$summaryPivotHeaderRow}", $typeGroup['label']);
    }

    if ($summaryPivotLastColumnIndex >= $summaryPivotStartColumnIndex) {
        $summaryPivotFirstColumnLetter = columnIndexToLetter($summaryPivotStartColumnIndex);
        $summaryPivotLastColumnLetter = columnIndexToLetter($summaryPivotLastColumnIndex);
        if ($summaryPivotFirstColumnLetter !== $summaryPivotLastColumnLetter) {
            $summarySheet->mergeCells("{$summaryPivotFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryPivotLastColumnLetter}{$summaryPivotTitleRow}");
        }
        $summarySheet->setCellValue("{$summaryPivotFirstColumnLetter}{$summaryPivotTitleRow}", 'PRODUCT');
        $summarySheet->getStyle("{$summaryPivotFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryPivotLastColumnLetter}{$summaryPivotTitleRow}")
            ->getFont()
            ->setBold(true);
        $summarySheet->getStyle("{$summaryPivotFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryPivotLastColumnLetter}{$summaryPivotTitleRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $summaryPivotRowPointer = $summaryDataRow;
        foreach ($summaryByDate as $saleDate => $totals) {
            foreach ($summaryProductColumnsByCode as $productCodeKey => $columnMeta) {
                if (!isset($productPivotByDate[$saleDate][$productCodeKey])) {
                    continue;
                }
                $pivotDateValue = $productPivotByDate[$saleDate][$productCodeKey];
                $qtyValue = isset($pivotDateValue['qty']) ? (float)$pivotDateValue['qty'] : 0.0;
                $uomValue = trim((string)(isset($pivotDateValue['uom']) ? $pivotDateValue['uom'] : ''));
                if ($uomValue === '') {
                    $uomValue = trim((string)$columnMeta['uom']);
                }
                if ($qtyValue != 0.0) {
                    $summarySheet->setCellValue($columnMeta['qty_column'] . $summaryPivotRowPointer, $qtyValue);
                }
                if ($uomValue !== '') {
                    $summarySheet->setCellValue($columnMeta['uom_column'] . $summaryPivotRowPointer, $uomValue);
                }
            }
            $summaryPivotRowPointer++;
        }

        foreach ($summaryProductColumnsByCode as $columnMeta) {
            $qtyColumnLetter = $columnMeta['qty_column'];
            $uomColumnLetter = $columnMeta['uom_column'];
            if ($lastSummaryDetailRow >= $summaryDataRow) {
                $summarySheet->setCellValue("{$qtyColumnLetter}{$summaryTotalRow}", "=SUM({$qtyColumnLetter}{$summaryDataRow}:{$qtyColumnLetter}{$lastSummaryDetailRow})");
            } else {
                $summarySheet->setCellValue("{$qtyColumnLetter}{$summaryTotalRow}", 0);
            }
            $summarySheet->setCellValue("{$uomColumnLetter}{$summaryTotalRow}", isset($columnMeta['uom']) ? (string)$columnMeta['uom'] : '');
        }

        $summarySheet->getStyle("{$summaryPivotFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryPivotLastColumnLetter}{$summaryTotalRow}")
            ->applyFromArray($borderStyle);
        $summarySheet->getStyle("{$summaryPivotFirstColumnLetter}{$summaryPivotSubHeaderRow}:{$summaryPivotLastColumnLetter}{$summaryTotalRow}")
            ->applyFromArray($borderStyle);
        $summarySheet->getStyle("{$summaryPivotFirstColumnLetter}{$summaryPivotSubHeaderRow}:{$summaryPivotLastColumnLetter}{$summaryTotalRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $summarySheet->getStyle("{$summaryPivotFirstColumnLetter}{$summaryTotalRow}:{$summaryPivotLastColumnLetter}{$summaryTotalRow}")
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setRGB('000000');
        foreach ($summaryProductColumnsByCode as $columnMeta) {
            $qtyColumnLetter = $columnMeta['qty_column'];
            $summarySheet->getStyle("{$qtyColumnLetter}{$summaryDataRow}:{$qtyColumnLetter}{$summaryTotalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }
    }
}

if ($currentRow >= $summaryHeaderRow) {
    $summarySheet->getStyle("A{$summaryHeaderRow}:I{$currentRow}")->applyFromArray($borderStyle);
}

foreach (range('A', 'I') as $column) {
    if ($column === 'A') {
        $summarySheet->getColumnDimension($column)->setAutoSize(false)->setWidth(5);
        continue;
    }
    if ($column === 'B') {
        $summarySheet->getColumnDimension($column)->setAutoSize(false)->setWidth(15);
        continue;
    }
    if (in_array($column, ['E', 'F', 'G', 'H', 'I'], true)) {
        $summarySheet->getColumnDimension($column)->setAutoSize(false)->setWidth(20);
        continue;
    }
    $summarySheet->getColumnDimension($column)->setAutoSize(true);
}

if ($reportType === 'by_product') {
    $registerRowsUmu = convertRegisterRowsToUmu($registerRows);
    $summaryByDateUmu = fetchToDateSummaryByDate($registerRowsUmu);
    $summaryByDateUmu = fillMissingSummaryDates($summaryByDateUmu, $startDate, $endDate);
    $productPivotRowsUmu = buildProductPivotRows($registerRowsUmu);
    $productPivotByDateUmu = buildProductPivotByDate($registerRowsUmu);
    $otherPivotRowsUmu = buildOthersPivotRows($registerRowsUmu);
    $otherPivotByDateUmu = buildOthersPivotByDate($registerRowsUmu);

    $summaryUmuSheet = $spreadsheet->createSheet();
    $summaryUmuSheet->setTitle('Sales Summary UMU');
    $summaryUmuSheet->setCellValue('A1', 'Sales Summary by Date - By Product (UMU)');
    $summaryUmuSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $summaryUmuSheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
    $summaryUmuSheet->setCellValue('A3', 'Generated: ' . $generatedAt);

    $summaryUmuHeaderRow = 8;
    $summaryUmuDataRow = 10;
    $summaryUmuHeaders = [
        'NO.',
        'DATE',
        'TOTAL TRANSACTION',
        'TOTAL QTY (UMU)',
        'TOTAL AMOUNT',
        'AMOUNT RECEIVED',
        'CHANGE',
        'CASH',
        'ON ACCOUNT'
    ];

    $summaryUmuSheet->fromArray($summaryUmuHeaders, null, 'A' . $summaryUmuHeaderRow);
    $summaryUmuSheet->getStyle("A{$summaryUmuHeaderRow}:I{$summaryUmuHeaderRow}")->applyFromArray($headerStyle);
    $summaryUmuSheet->getStyle("A{$summaryUmuHeaderRow}:I{$summaryUmuHeaderRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $summaryUmuCurrentRow = $summaryUmuDataRow;
    $summaryUmuCounter = 1;
    $summaryUmuTotals = [
        'transactions' => 0,
        'qty' => 0.0,
        'amount' => 0.0,
        'amt_received' => 0.0,
        'amt_change' => 0.0,
        'cash' => 0.0,
        'on_account' => 0.0
    ];

    foreach ($summaryByDateUmu as $saleDate => $totals) {
        $summaryUmuSheet->setCellValue("A{$summaryUmuCurrentRow}", $summaryUmuCounter);
        $summaryUmuSheet->setCellValue("B{$summaryUmuCurrentRow}", $saleDate);
        $summaryUmuSheet->setCellValue("C{$summaryUmuCurrentRow}", $totals['transactions']);
        $summaryUmuSheet->setCellValue("D{$summaryUmuCurrentRow}", $totals['qty']);
        $summaryUmuSheet->setCellValue("E{$summaryUmuCurrentRow}", $totals['amount']);
        $summaryUmuSheet->setCellValue("F{$summaryUmuCurrentRow}", $totals['amt_received']);
        $summaryUmuSheet->setCellValue("G{$summaryUmuCurrentRow}", $totals['amt_change']);
        $summaryUmuSheet->setCellValue("H{$summaryUmuCurrentRow}", $totals['cash']);
        $summaryUmuSheet->setCellValue("I{$summaryUmuCurrentRow}", $totals['on_account']);
        $summaryUmuSheet->getStyle("D{$summaryUmuCurrentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $summaryUmuSheet->getStyle("E{$summaryUmuCurrentRow}:I{$summaryUmuCurrentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        $summaryUmuTotals['transactions'] += $totals['transactions'];
        $summaryUmuTotals['qty'] += $totals['qty'];
        $summaryUmuTotals['amount'] += $totals['amount'];
        $summaryUmuTotals['amt_received'] += $totals['amt_received'];
        $summaryUmuTotals['amt_change'] += $totals['amt_change'];
        $summaryUmuTotals['cash'] += $totals['cash'];
        $summaryUmuTotals['on_account'] += $totals['on_account'];
        $summaryUmuCurrentRow++;
        $summaryUmuCounter++;
    }

    $lastSummaryUmuDetailRow = $summaryUmuCurrentRow - 1;
    $summaryUmuSheet->setCellValue("B{$summaryUmuCurrentRow}", 'Total');
    $summaryUmuSheet->setCellValue("C{$summaryUmuCurrentRow}", $summaryUmuTotals['transactions']);
    $summaryUmuSheet->setCellValue("D{$summaryUmuCurrentRow}", $summaryUmuTotals['qty']);
    $summaryUmuSheet->setCellValue("E{$summaryUmuCurrentRow}", $summaryUmuTotals['amount']);
    $summaryUmuSheet->setCellValue("F{$summaryUmuCurrentRow}", $summaryUmuTotals['amt_received']);
    $summaryUmuSheet->setCellValue("G{$summaryUmuCurrentRow}", $summaryUmuTotals['amt_change']);
    $summaryUmuSheet->setCellValue("H{$summaryUmuCurrentRow}", $summaryUmuTotals['cash']);
    $summaryUmuSheet->setCellValue("I{$summaryUmuCurrentRow}", $summaryUmuTotals['on_account']);
    $summaryUmuSheet->getStyle("A{$summaryUmuCurrentRow}:I{$summaryUmuCurrentRow}")->applyFromArray($headerStyle);
    $summaryUmuSheet->getStyle("A{$summaryUmuCurrentRow}:I{$summaryUmuCurrentRow}")->getFont()->setBold(true);
    $summaryUmuSheet->getStyle("D{$summaryUmuCurrentRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    $summaryUmuSheet->getStyle("E{$summaryUmuCurrentRow}:I{$summaryUmuCurrentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

    $summaryUmuTotalRow = $summaryUmuCurrentRow;
    if (!empty($productPivotRowsUmu)) {
        $summaryUmuPivotHeaderRow = 6;
        $summaryUmuPivotCodeRow = 7;
        $summaryUmuPivotDescriptionRow = 8;
        $summaryUmuPivotSubHeaderRow = 9;
        $summaryUmuPivotTitleRow = 5;
        $summaryUmuPivotStartColumnIndex = 10; // J
        $summaryUmuPivotLastColumnIndex = $summaryUmuPivotStartColumnIndex - 1;
        $summaryUmuProductColumnsByCode = [];
        $summaryUmuProductTypeGroups = [];
        $summaryUmuFirstQtyColumnLetter = '';

        foreach ($productPivotRowsUmu as $pivotIndex => $pivotData) {
            $qtyColumnIndex = $summaryUmuPivotStartColumnIndex + ($pivotIndex * 2);
            $uomColumnIndex = $qtyColumnIndex + 1;
            $qtyColumnLetter = columnIndexToLetter($qtyColumnIndex);
            $uomColumnLetter = columnIndexToLetter($uomColumnIndex);
            $summaryUmuPivotLastColumnIndex = $uomColumnIndex;
            if ($summaryUmuFirstQtyColumnLetter === '') {
                $summaryUmuFirstQtyColumnLetter = $qtyColumnLetter;
            }

            $productTypeLabel = isset($pivotData['product_type']) ? trim((string)$pivotData['product_type']) : '';
            $productTypeKey = strtoupper($productTypeLabel);
            if (empty($summaryUmuProductTypeGroups) || $summaryUmuProductTypeGroups[count($summaryUmuProductTypeGroups) - 1]['key'] !== $productTypeKey) {
                $summaryUmuProductTypeGroups[] = [
                    'key' => $productTypeKey,
                    'label' => $productTypeLabel,
                    'start_column_index' => $qtyColumnIndex,
                    'end_column_index' => $uomColumnIndex
                ];
            } else {
                $lastTypeGroupIndex = count($summaryUmuProductTypeGroups) - 1;
                $summaryUmuProductTypeGroups[$lastTypeGroupIndex]['end_column_index'] = $uomColumnIndex;
                if ($summaryUmuProductTypeGroups[$lastTypeGroupIndex]['label'] === '' && $productTypeLabel !== '') {
                    $summaryUmuProductTypeGroups[$lastTypeGroupIndex]['label'] = $productTypeLabel;
                }
            }

            $summaryUmuSheet->mergeCells("{$qtyColumnLetter}{$summaryUmuPivotCodeRow}:{$uomColumnLetter}{$summaryUmuPivotCodeRow}");
            $summaryUmuSheet->mergeCells("{$qtyColumnLetter}{$summaryUmuPivotDescriptionRow}:{$uomColumnLetter}{$summaryUmuPivotDescriptionRow}");
            $summaryUmuSheet->setCellValue("{$qtyColumnLetter}{$summaryUmuPivotCodeRow}", isset($pivotData['product_code']) ? (string)$pivotData['product_code'] : '');
            $summaryUmuSheet->setCellValue("{$qtyColumnLetter}{$summaryUmuPivotDescriptionRow}", isset($pivotData['product_description']) ? (string)$pivotData['product_description'] : '');
            $summaryUmuSheet->setCellValue("{$qtyColumnLetter}{$summaryUmuPivotSubHeaderRow}", 'QTY');
            $summaryUmuSheet->setCellValue("{$uomColumnLetter}{$summaryUmuPivotSubHeaderRow}", 'UOM');

            $summaryUmuSheet->getStyle("{$qtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$uomColumnLetter}{$summaryUmuPivotSubHeaderRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            $summaryUmuSheet->getStyle("{$qtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$uomColumnLetter}{$summaryUmuPivotSubHeaderRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryUmuSheet->getStyle("{$qtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$uomColumnLetter}{$summaryUmuPivotSubHeaderRow}")
                ->applyFromArray($borderStyle);

            $summaryUmuSheet->getColumnDimension($qtyColumnLetter)->setAutoSize(false)->setWidth(10);
            $summaryUmuSheet->getColumnDimension($uomColumnLetter)->setAutoSize(false)->setWidth(8);

            $productCodeKey = strtoupper(trim((string)(isset($pivotData['product_code']) ? $pivotData['product_code'] : '')));
            if ($productCodeKey !== '') {
                $summaryUmuProductColumnsByCode[$productCodeKey] = [
                    'qty_column' => $qtyColumnLetter,
                    'uom_column' => $uomColumnLetter,
                    'uom' => isset($pivotData['uom']) ? (string)$pivotData['uom'] : ''
                ];
            }
        }

        foreach ($summaryUmuProductTypeGroups as $typeGroup) {
            $typeStartColumnLetter = columnIndexToLetter($typeGroup['start_column_index']);
            $typeEndColumnLetter = columnIndexToLetter($typeGroup['end_column_index']);
            if ($typeStartColumnLetter !== $typeEndColumnLetter) {
                $summaryUmuSheet->mergeCells("{$typeStartColumnLetter}{$summaryUmuPivotHeaderRow}:{$typeEndColumnLetter}{$summaryUmuPivotHeaderRow}");
            }
            $summaryUmuSheet->setCellValue("{$typeStartColumnLetter}{$summaryUmuPivotHeaderRow}", $typeGroup['label']);
        }

        if ($summaryUmuPivotLastColumnIndex >= $summaryUmuPivotStartColumnIndex) {
            $summaryUmuPivotFirstColumnLetter = columnIndexToLetter($summaryUmuPivotStartColumnIndex);
            $summaryUmuPivotLastColumnLetter = columnIndexToLetter($summaryUmuPivotLastColumnIndex);
            $summaryUmuGrandTotalColumnIndex = $summaryUmuPivotLastColumnIndex + 1;
            $summaryUmuGrandTotalColumnLetter = columnIndexToLetter($summaryUmuGrandTotalColumnIndex);
            if ($summaryUmuPivotFirstColumnLetter !== $summaryUmuPivotLastColumnLetter) {
                $summaryUmuSheet->mergeCells("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuPivotLastColumnLetter}{$summaryUmuPivotTitleRow}");
            }
            $summaryUmuSheet->setCellValue("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}", 'PRODUCT');
            $summaryUmuSheet->getStyle("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuPivotLastColumnLetter}{$summaryUmuPivotTitleRow}")
                ->getFont()
                ->setBold(true);
            $summaryUmuSheet->getStyle("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuPivotLastColumnLetter}{$summaryUmuPivotTitleRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $summaryUmuSheet->mergeCells("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotSubHeaderRow}");
            $summaryUmuSheet->setCellValue("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotHeaderRow}", 'TOTAL');
            $summaryUmuSheet->getStyle("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotSubHeaderRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            $summaryUmuSheet->getStyle("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotSubHeaderRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryUmuSheet->getStyle("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotSubHeaderRow}")
                ->applyFromArray($borderStyle);
            $summaryUmuSheet->getColumnDimension($summaryUmuGrandTotalColumnLetter)->setAutoSize(false)->setWidth(14);

            $summaryUmuFinalLastColumnLetter = $summaryUmuGrandTotalColumnLetter;
            $summaryUmuOtherColumnsByCode = [];
            if (!empty($otherPivotRowsUmu)) {
                $summaryUmuOtherStartColumnIndex = $summaryUmuGrandTotalColumnIndex + 1;
                $summaryUmuOtherLastColumnIndex = $summaryUmuOtherStartColumnIndex - 1;
                $summaryUmuOtherFirstColumnLetter = '';

                foreach ($otherPivotRowsUmu as $otherIndex => $otherData) {
                    $otherQtyColumnIndex = $summaryUmuOtherStartColumnIndex + ($otherIndex * 2);
                    $otherUomColumnIndex = $otherQtyColumnIndex + 1;
                    $otherQtyColumnLetter = columnIndexToLetter($otherQtyColumnIndex);
                    $otherUomColumnLetter = columnIndexToLetter($otherUomColumnIndex);
                    $summaryUmuOtherLastColumnIndex = $otherUomColumnIndex;
                    if ($summaryUmuOtherFirstColumnLetter === '') {
                        $summaryUmuOtherFirstColumnLetter = $otherQtyColumnLetter;
                    }

                    $summaryUmuSheet->mergeCells("{$otherQtyColumnLetter}{$summaryUmuPivotCodeRow}:{$otherUomColumnLetter}{$summaryUmuPivotCodeRow}");
                    $summaryUmuSheet->mergeCells("{$otherQtyColumnLetter}{$summaryUmuPivotDescriptionRow}:{$otherUomColumnLetter}{$summaryUmuPivotDescriptionRow}");
                    $summaryUmuSheet->setCellValue("{$otherQtyColumnLetter}{$summaryUmuPivotCodeRow}", isset($otherData['product_code']) ? (string)$otherData['product_code'] : '');
                    $summaryUmuSheet->setCellValue("{$otherQtyColumnLetter}{$summaryUmuPivotDescriptionRow}", isset($otherData['product_description']) ? (string)$otherData['product_description'] : '');
                    $summaryUmuSheet->setCellValue("{$otherQtyColumnLetter}{$summaryUmuPivotSubHeaderRow}", 'QTY');
                    $summaryUmuSheet->setCellValue("{$otherUomColumnLetter}{$summaryUmuPivotSubHeaderRow}", 'UOM');
                    $summaryUmuSheet->getStyle("{$otherQtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$otherUomColumnLetter}{$summaryUmuPivotSubHeaderRow}")
                        ->getFont()
                        ->setBold(true)
                        ->getColor()
                        ->setRGB('000000');
                    $summaryUmuSheet->getStyle("{$otherQtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$otherUomColumnLetter}{$summaryUmuPivotSubHeaderRow}")
                        ->getAlignment()
                        ->setWrapText(true)
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $summaryUmuSheet->getStyle("{$otherQtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$otherUomColumnLetter}{$summaryUmuPivotSubHeaderRow}")
                        ->applyFromArray($borderStyle);
                    $summaryUmuSheet->getColumnDimension($otherQtyColumnLetter)->setAutoSize(false)->setWidth(10);
                    $summaryUmuSheet->getColumnDimension($otherUomColumnLetter)->setAutoSize(false)->setWidth(8);

                    $otherProductCodeKey = strtoupper(trim((string)(isset($otherData['product_code']) ? $otherData['product_code'] : '')));
                    if ($otherProductCodeKey !== '') {
                        $summaryUmuOtherColumnsByCode[$otherProductCodeKey] = [
                            'qty_column' => $otherQtyColumnLetter,
                            'uom_column' => $otherUomColumnLetter,
                            'uom' => isset($otherData['uom']) ? (string)$otherData['uom'] : ''
                        ];
                    }
                }

                if ($summaryUmuOtherFirstColumnLetter !== '') {
                    $summaryUmuOtherLastColumnLetter = columnIndexToLetter($summaryUmuOtherLastColumnIndex);
                    if ($summaryUmuOtherFirstColumnLetter !== $summaryUmuOtherLastColumnLetter) {
                        $summaryUmuSheet->mergeCells("{$summaryUmuOtherFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOtherLastColumnLetter}{$summaryUmuPivotTitleRow}");
                    }
                    $summaryUmuSheet->setCellValue("{$summaryUmuOtherFirstColumnLetter}{$summaryUmuPivotTitleRow}", 'OTHERS PRODUCT');
                    $summaryUmuSheet->getStyle("{$summaryUmuOtherFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOtherLastColumnLetter}{$summaryUmuPivotTitleRow}")
                        ->getFont()
                        ->setBold(true);
                    $summaryUmuSheet->getStyle("{$summaryUmuOtherFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOtherLastColumnLetter}{$summaryUmuPivotTitleRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $summaryUmuFinalLastColumnLetter = $summaryUmuOtherLastColumnLetter;
                }
            }

            $summaryUmuPivotRowPointer = $summaryUmuDataRow;
            foreach ($summaryByDateUmu as $saleDate => $totals) {
                foreach ($summaryUmuProductColumnsByCode as $productCodeKey => $columnMeta) {
                    if (!isset($productPivotByDateUmu[$saleDate][$productCodeKey])) {
                        continue;
                    }
                    $pivotDateValue = $productPivotByDateUmu[$saleDate][$productCodeKey];
                    $qtyValue = isset($pivotDateValue['qty']) ? (float)$pivotDateValue['qty'] : 0.0;
                    $uomValue = trim((string)(isset($pivotDateValue['uom']) ? $pivotDateValue['uom'] : ''));
                    if ($uomValue === '') {
                        $uomValue = trim((string)$columnMeta['uom']);
                    }
                    if ($qtyValue != 0.0) {
                        $summaryUmuSheet->setCellValue($columnMeta['qty_column'] . $summaryUmuPivotRowPointer, $qtyValue);
                    }
                    if ($uomValue !== '') {
                        $summaryUmuSheet->setCellValue($columnMeta['uom_column'] . $summaryUmuPivotRowPointer, $uomValue);
                    }
                }
                if ($summaryUmuFirstQtyColumnLetter !== '') {
                    $summaryUmuSheet->setCellValue(
                        "{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotRowPointer}",
                        "=SUM({$summaryUmuFirstQtyColumnLetter}{$summaryUmuPivotRowPointer}:{$summaryUmuPivotLastColumnLetter}{$summaryUmuPivotRowPointer})"
                    );
                }
                foreach ($summaryUmuOtherColumnsByCode as $otherProductCodeKey => $otherColumnMeta) {
                    if (!isset($otherPivotByDateUmu[$saleDate][$otherProductCodeKey])) {
                        continue;
                    }
                    $otherPivotDateValue = $otherPivotByDateUmu[$saleDate][$otherProductCodeKey];
                    $otherQtyValue = isset($otherPivotDateValue['qty']) ? (float)$otherPivotDateValue['qty'] : 0.0;
                    $otherUomValue = trim((string)(isset($otherPivotDateValue['uom']) ? $otherPivotDateValue['uom'] : ''));
                    if ($otherUomValue === '') {
                        $otherUomValue = trim((string)$otherColumnMeta['uom']);
                    }
                    if ($otherQtyValue != 0.0) {
                        $summaryUmuSheet->setCellValue($otherColumnMeta['qty_column'] . $summaryUmuPivotRowPointer, $otherQtyValue);
                    }
                    if ($otherUomValue !== '') {
                        $summaryUmuSheet->setCellValue($otherColumnMeta['uom_column'] . $summaryUmuPivotRowPointer, $otherUomValue);
                    }
                }
                $summaryUmuPivotRowPointer++;
            }

            foreach ($summaryUmuProductColumnsByCode as $columnMeta) {
                $qtyColumnLetter = $columnMeta['qty_column'];
                $uomColumnLetter = $columnMeta['uom_column'];
                if ($lastSummaryUmuDetailRow >= $summaryUmuDataRow) {
                    $summaryUmuSheet->setCellValue("{$qtyColumnLetter}{$summaryUmuTotalRow}", "=SUM({$qtyColumnLetter}{$summaryUmuDataRow}:{$qtyColumnLetter}{$lastSummaryUmuDetailRow})");
                } else {
                    $summaryUmuSheet->setCellValue("{$qtyColumnLetter}{$summaryUmuTotalRow}", 0);
                }
                $summaryUmuSheet->setCellValue("{$uomColumnLetter}{$summaryUmuTotalRow}", isset($columnMeta['uom']) ? (string)$columnMeta['uom'] : '');
            }
            if ($lastSummaryUmuDetailRow >= $summaryUmuDataRow) {
                $summaryUmuSheet->setCellValue(
                    "{$summaryUmuGrandTotalColumnLetter}{$summaryUmuTotalRow}",
                    "=SUM({$summaryUmuGrandTotalColumnLetter}{$summaryUmuDataRow}:{$summaryUmuGrandTotalColumnLetter}{$lastSummaryUmuDetailRow})"
                );
            } else {
                $summaryUmuSheet->setCellValue("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuTotalRow}", 0);
            }
            foreach ($summaryUmuOtherColumnsByCode as $otherColumnMeta) {
                $otherQtyColumnLetter = $otherColumnMeta['qty_column'];
                $otherUomColumnLetter = $otherColumnMeta['uom_column'];
                if ($lastSummaryUmuDetailRow >= $summaryUmuDataRow) {
                    $summaryUmuSheet->setCellValue("{$otherQtyColumnLetter}{$summaryUmuTotalRow}", "=SUM({$otherQtyColumnLetter}{$summaryUmuDataRow}:{$otherQtyColumnLetter}{$lastSummaryUmuDetailRow})");
                } else {
                    $summaryUmuSheet->setCellValue("{$otherQtyColumnLetter}{$summaryUmuTotalRow}", 0);
                }
                $summaryUmuSheet->setCellValue("{$otherUomColumnLetter}{$summaryUmuTotalRow}", isset($otherColumnMeta['uom']) ? (string)$otherColumnMeta['uom'] : '');
            }

            $summaryUmuSheet->getStyle("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuFinalLastColumnLetter}{$summaryUmuTotalRow}")
                ->applyFromArray($borderStyle);
            $summaryUmuSheet->getStyle("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotSubHeaderRow}:{$summaryUmuFinalLastColumnLetter}{$summaryUmuTotalRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryUmuSheet->getStyle("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuTotalRow}:{$summaryUmuFinalLastColumnLetter}{$summaryUmuTotalRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            foreach ($summaryUmuProductColumnsByCode as $columnMeta) {
                $qtyColumnLetter = $columnMeta['qty_column'];
                $summaryUmuSheet->getStyle("{$qtyColumnLetter}{$summaryUmuDataRow}:{$qtyColumnLetter}{$summaryUmuTotalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
            $summaryUmuSheet->getStyle("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuDataRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuTotalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            foreach ($summaryUmuOtherColumnsByCode as $otherColumnMeta) {
                $otherQtyColumnLetter = $otherColumnMeta['qty_column'];
                $summaryUmuSheet->getStyle("{$otherQtyColumnLetter}{$summaryUmuDataRow}:{$otherQtyColumnLetter}{$summaryUmuTotalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
        }
    }

    if ($summaryUmuCurrentRow >= $summaryUmuHeaderRow) {
        $summaryUmuSheet->getStyle("A{$summaryUmuHeaderRow}:I{$summaryUmuCurrentRow}")->applyFromArray($borderStyle);
    }

    foreach (range('A', 'I') as $column) {
        if ($column === 'A') {
            $summaryUmuSheet->getColumnDimension($column)->setAutoSize(false)->setWidth(5);
            continue;
        }
        if ($column === 'B') {
            $summaryUmuSheet->getColumnDimension($column)->setAutoSize(false)->setWidth(15);
            continue;
        }
        if (in_array($column, ['E', 'F', 'G', 'H', 'I'], true)) {
            $summaryUmuSheet->getColumnDimension($column)->setAutoSize(false)->setWidth(20);
            continue;
        }
        $summaryUmuSheet->getColumnDimension($column)->setAutoSize(true);
    }

    foreach (['E', 'F', 'G', 'H', 'I'] as $hiddenColumn) {
        $summaryUmuSheet->getColumnDimension($hiddenColumn)->setVisible(false);
    }
}

if ($reportType === 'by_product') {
    foreach (['E', 'F', 'G', 'H', 'I'] as $hiddenColumn) {
        $summarySheet->getColumnDimension($hiddenColumn)->setVisible(false);
    }
    $spreadsheet->removeSheetByIndex(0);
    $spreadsheet->setActiveSheetIndex(0);
} else {
    $spreadsheet->setActiveSheetIndex(1);
}

if (ob_get_length()) {
    ob_end_clean();
}

$filenameSuffix = $reportType === 'by_customer' ? '_by_customer' : '_by_product';
$filename = 'todate_daily_sales_summary_' . $startDate . '_to_' . $endDate . $filenameSuffix . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
