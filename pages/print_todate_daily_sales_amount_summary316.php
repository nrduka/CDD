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

function fetchStmtAssocRows($stmt)
{
    $rows = [];

    if (method_exists($stmt, 'get_result')) {
        $result = @$stmt->get_result();
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
            return $rows;
        }
    }

    $meta = $stmt->result_metadata();
    if (!$meta) {
        return $rows;
    }

    $bindArgs = [];
    $rowData = [];
    while ($field = $meta->fetch_field()) {
        $rowData[$field->name] = null;
        $bindArgs[] = &$rowData[$field->name];
    }
    $meta->free_result();

    if (!empty($bindArgs)) {
        call_user_func_array([$stmt, 'bind_result'], $bindArgs);
        while ($stmt->fetch()) {
            $row = [];
            foreach ($rowData as $key => $value) {
                $row[$key] = $value;
            }
            $rows[] = $row;
        }
    }

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
        if ($stmt->execute()) {
            $resultRows = fetchStmtAssocRows($stmt);
            foreach ($resultRows as $row) {
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

function writePaymentSummarySheet(
    $sheet,
    $sheetTitle,
    $rangeLabel,
    $generatedAt,
    $summaryByDate,
    $paymentKey,
    $productPivotRows,
    $productPivotByDate,
    $otherPivotRows,
    $otherPivotByDate,
    $headerStyle,
    $borderStyle,
    $currencyFormat
) {
    $sheet->setTitle($sheetTitle);
    $sheet->setCellValue('A1', $sheetTitle . ' by Date - By Product');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
    $sheet->setCellValue('A3', 'Generated: ' . $generatedAt);

    $pivotTitleRow = 5;
    $pivotTypeRow = 6;
    $pivotCodeRow = 7;
    $headerRow = 8;
    $dataRow = 9;
    $baseHeaders = [
        'NO.',
        'DATE',
        'TRANSACTION COUNT',
        strtoupper(str_replace('_', ' ', $paymentKey)) . ' TOTAL'
    ];
    $sheet->fromArray($baseHeaders, null, "A{$headerRow}");
    $sheet->getStyle("A{$headerRow}:D{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$headerRow}:D{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(6);
    $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(15);
    $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(20);
    $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(22);

    $columnMetaIce = [];
    $columnMetaOthers = [];
    $currentColumnIndex = 5; // E
    $iceTypeGroups = [];
    $iceTypeSubtotalColumns = [];
    $iceFirstColumnIndex = null;
    $currentIceTypeKey = '';
    $currentIceTypeLabel = '';
    $currentIceTypeStartColumnIndex = null;
    $currentIceTypeLastProductColumnIndex = null;

    foreach ($productPivotRows as $pivotData) {
        $productCodeKey = strtoupper(trim((string)(isset($pivotData['product_code']) ? $pivotData['product_code'] : '')));
        if ($productCodeKey === '') {
            continue;
        }

        $productTypeLabel = trim((string)(isset($pivotData['product_type']) ? $pivotData['product_type'] : ''));
        if ($productTypeLabel === '') {
            $productTypeLabel = 'UNCLASSIFIED';
        }
        $typeKey = strtoupper($productTypeLabel);

        if ($currentIceTypeKey !== '' && $typeKey !== $currentIceTypeKey && $currentIceTypeStartColumnIndex !== null && $currentIceTypeLastProductColumnIndex !== null) {
            $typeSubtotalColumnIndex = $currentColumnIndex++;
            $typeSubtotalColumnLetter = columnIndexToLetter($typeSubtotalColumnIndex);
            $sheet->mergeCells("{$typeSubtotalColumnLetter}{$pivotCodeRow}:{$typeSubtotalColumnLetter}{$headerRow}");
            $sheet->setCellValue("{$typeSubtotalColumnLetter}{$pivotCodeRow}", 'SUB-TOTAL');
            $sheet->getStyle("{$typeSubtotalColumnLetter}{$pivotCodeRow}:{$typeSubtotalColumnLetter}{$headerRow}")
                ->applyFromArray($headerStyle);
            $sheet->getStyle("{$typeSubtotalColumnLetter}{$pivotCodeRow}:{$typeSubtotalColumnLetter}{$headerRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getColumnDimension($typeSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

            $iceTypeSubtotalColumns[] = [
                'column_letter' => $typeSubtotalColumnLetter,
                'start_column_letter' => columnIndexToLetter($currentIceTypeStartColumnIndex),
                'end_column_letter' => columnIndexToLetter($currentIceTypeLastProductColumnIndex)
            ];
            $iceTypeGroups[] = [
                'label' => $currentIceTypeLabel,
                'start_column_index' => $currentIceTypeStartColumnIndex,
                'end_column_index' => $typeSubtotalColumnIndex
            ];

            $currentIceTypeStartColumnIndex = null;
            $currentIceTypeLastProductColumnIndex = null;
        }

        if ($currentIceTypeKey === '' || $typeKey !== $currentIceTypeKey || $currentIceTypeStartColumnIndex === null) {
            $currentIceTypeKey = $typeKey;
            $currentIceTypeLabel = $productTypeLabel;
            $currentIceTypeStartColumnIndex = $currentColumnIndex;
        }

        $headerLabel = trim((string)(isset($pivotData['product_description']) ? $pivotData['product_description'] : ''));
        if ($headerLabel === '') {
            $headerLabel = trim((string)(isset($pivotData['product_code']) ? $pivotData['product_code'] : ''));
        }
        if ($headerLabel === '') {
            $headerLabel = 'PRODUCT';
        }
        $uomHeader = trim((string)(isset($pivotData['uom']) ? $pivotData['uom'] : ''));
        if ($uomHeader !== '') {
            $headerLabel .= "\n(" . $uomHeader . ')';
        }
        $columnLetter = columnIndexToLetter($currentColumnIndex);
        if ($iceFirstColumnIndex === null) {
            $iceFirstColumnIndex = $currentColumnIndex;
        }

        $sheet->setCellValue("{$columnLetter}{$pivotCodeRow}", isset($pivotData['product_code']) ? (string)$pivotData['product_code'] : '');
        $sheet->setCellValue("{$columnLetter}{$headerRow}", $headerLabel);
        $sheet->getStyle("{$columnLetter}{$pivotTypeRow}:{$columnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$columnLetter}{$pivotTypeRow}:{$columnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(false)->setWidth(14);

        $columnMetaIce[] = [
            'product_key' => $productCodeKey,
            'column_letter' => $columnLetter,
            'type_key' => $typeKey
        ];

        $currentIceTypeLastProductColumnIndex = $currentColumnIndex;
        $currentColumnIndex++;
    }

    if ($currentIceTypeKey !== '' && $currentIceTypeStartColumnIndex !== null && $currentIceTypeLastProductColumnIndex !== null) {
        $typeSubtotalColumnIndex = $currentColumnIndex++;
        $typeSubtotalColumnLetter = columnIndexToLetter($typeSubtotalColumnIndex);
        $sheet->mergeCells("{$typeSubtotalColumnLetter}{$pivotCodeRow}:{$typeSubtotalColumnLetter}{$headerRow}");
        $sheet->setCellValue("{$typeSubtotalColumnLetter}{$pivotCodeRow}", 'SUB-TOTAL');
        $sheet->getStyle("{$typeSubtotalColumnLetter}{$pivotCodeRow}:{$typeSubtotalColumnLetter}{$headerRow}")
            ->applyFromArray($headerStyle);
        $sheet->getStyle("{$typeSubtotalColumnLetter}{$pivotCodeRow}:{$typeSubtotalColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($typeSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

        $iceTypeSubtotalColumns[] = [
            'column_letter' => $typeSubtotalColumnLetter,
            'start_column_letter' => columnIndexToLetter($currentIceTypeStartColumnIndex),
            'end_column_letter' => columnIndexToLetter($currentIceTypeLastProductColumnIndex)
        ];
        $iceTypeGroups[] = [
            'label' => $currentIceTypeLabel,
            'start_column_index' => $currentIceTypeStartColumnIndex,
            'end_column_index' => $typeSubtotalColumnIndex
        ];
    }

    foreach ($iceTypeGroups as $typeGroup) {
        $typeStartLetter = columnIndexToLetter($typeGroup['start_column_index']);
        $typeEndLetter = columnIndexToLetter($typeGroup['end_column_index']);
        if ($typeStartLetter !== $typeEndLetter) {
            $sheet->mergeCells("{$typeStartLetter}{$pivotTypeRow}:{$typeEndLetter}{$pivotTypeRow}");
        }
        $sheet->setCellValue("{$typeStartLetter}{$pivotTypeRow}", $typeGroup['label']);
        $sheet->getStyle("{$typeStartLetter}{$pivotTypeRow}:{$typeEndLetter}{$pivotTypeRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$typeStartLetter}{$pivotTypeRow}:{$typeEndLetter}{$pivotTypeRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    $productSubtotalColumnLetter = '';
    if ($iceFirstColumnIndex !== null) {
        $productSubtotalColumnLetter = columnIndexToLetter($currentColumnIndex++);
        $sheet->mergeCells("{$productSubtotalColumnLetter}{$pivotTypeRow}:{$productSubtotalColumnLetter}{$headerRow}");
        $sheet->setCellValue("{$productSubtotalColumnLetter}{$pivotTypeRow}", 'TOTAL');
        $sheet->getStyle("{$productSubtotalColumnLetter}{$pivotTypeRow}:{$productSubtotalColumnLetter}{$headerRow}")
            ->applyFromArray($headerStyle);
        $sheet->getStyle("{$productSubtotalColumnLetter}{$pivotTypeRow}:{$productSubtotalColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($productSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

        $iceFirstLetter = columnIndexToLetter($iceFirstColumnIndex);
        $iceTitleLastLetter = $productSubtotalColumnLetter;
        if ($iceFirstLetter !== $iceTitleLastLetter) {
            $sheet->mergeCells("{$iceFirstLetter}{$pivotTitleRow}:{$iceTitleLastLetter}{$pivotTitleRow}");
        }
        $sheet->setCellValue("{$iceFirstLetter}{$pivotTitleRow}", 'PRODUCT');
        $sheet->getStyle("{$iceFirstLetter}{$pivotTitleRow}:{$iceTitleLastLetter}{$pivotTitleRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$iceFirstLetter}{$pivotTitleRow}:{$iceTitleLastLetter}{$pivotTitleRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    $othersFirstColumnIndex = null;
    $othersLastColumnIndex = null;
    foreach ($otherPivotRows as $otherData) {
        $productCodeKey = strtoupper(trim((string)(isset($otherData['product_code']) ? $otherData['product_code'] : '')));
        if ($productCodeKey === '') {
            continue;
        }
        $headerLabel = trim((string)(isset($otherData['product_description']) ? $otherData['product_description'] : ''));
        if ($headerLabel === '') {
            $headerLabel = trim((string)(isset($otherData['product_code']) ? $otherData['product_code'] : ''));
        }
        if ($headerLabel === '') {
            $headerLabel = 'OTHERS';
        }
        $uomHeader = trim((string)(isset($otherData['uom']) ? $otherData['uom'] : ''));
        if ($uomHeader !== '') {
            $headerLabel .= "\n(" . $uomHeader . ')';
        }
        $columnLetter = columnIndexToLetter($currentColumnIndex);
        if ($othersFirstColumnIndex === null) {
            $othersFirstColumnIndex = $currentColumnIndex;
        }
        $othersLastColumnIndex = $currentColumnIndex;

        $sheet->setCellValue("{$columnLetter}{$pivotCodeRow}", isset($otherData['product_code']) ? (string)$otherData['product_code'] : '');
        $sheet->setCellValue("{$columnLetter}{$headerRow}", $headerLabel);
        $sheet->getStyle("{$columnLetter}{$pivotTypeRow}:{$columnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$columnLetter}{$pivotTypeRow}:{$columnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(false)->setWidth(14);

        $columnMetaOthers[] = [
            'product_key' => $productCodeKey,
            'column_letter' => $columnLetter
        ];
        $currentColumnIndex++;
    }

    if ($othersFirstColumnIndex !== null && $othersLastColumnIndex !== null) {
        $othersFirstLetter = columnIndexToLetter($othersFirstColumnIndex);
        $othersLastLetter = columnIndexToLetter($othersLastColumnIndex);
        if ($othersFirstLetter !== $othersLastLetter) {
            $sheet->mergeCells("{$othersFirstLetter}{$pivotTypeRow}:{$othersLastLetter}{$pivotTypeRow}");
        }
        $sheet->setCellValue("{$othersFirstLetter}{$pivotTypeRow}", 'OTHERS');
        $sheet->getStyle("{$othersFirstLetter}{$pivotTypeRow}:{$othersLastLetter}{$pivotTypeRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$othersFirstLetter}{$pivotTypeRow}:{$othersLastLetter}{$pivotTypeRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    $othersSubtotalColumnLetter = '';
    if ($othersFirstColumnIndex !== null) {
        $othersSubtotalColumnLetter = columnIndexToLetter($currentColumnIndex++);
        $sheet->mergeCells("{$othersSubtotalColumnLetter}{$pivotTypeRow}:{$othersSubtotalColumnLetter}{$headerRow}");
        $sheet->setCellValue("{$othersSubtotalColumnLetter}{$pivotTypeRow}", 'SUBTOTAL');
        $sheet->getStyle("{$othersSubtotalColumnLetter}{$pivotTypeRow}:{$othersSubtotalColumnLetter}{$headerRow}")
            ->applyFromArray($headerStyle);
        $sheet->getStyle("{$othersSubtotalColumnLetter}{$pivotTypeRow}:{$othersSubtotalColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($othersSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

        if ($othersFirstColumnIndex !== null) {
            $othersFirstLetter = columnIndexToLetter($othersFirstColumnIndex);
            $othersTitleLastLetter = $othersSubtotalColumnLetter;
            if ($othersFirstLetter !== $othersTitleLastLetter) {
                $sheet->mergeCells("{$othersFirstLetter}{$pivotTitleRow}:{$othersTitleLastLetter}{$pivotTitleRow}");
            }
            $sheet->setCellValue("{$othersFirstLetter}{$pivotTitleRow}", 'OTHERS PRODUCT');
            $sheet->getStyle("{$othersFirstLetter}{$pivotTitleRow}:{$othersTitleLastLetter}{$pivotTitleRow}")->applyFromArray($headerStyle);
            $sheet->getStyle("{$othersFirstLetter}{$pivotTitleRow}:{$othersTitleLastLetter}{$pivotTitleRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    $grandTotalColumnLetter = columnIndexToLetter($currentColumnIndex++);
    $sheet->mergeCells("{$grandTotalColumnLetter}{$pivotTitleRow}:{$grandTotalColumnLetter}{$headerRow}");
    $sheet->setCellValue("{$grandTotalColumnLetter}{$pivotTitleRow}", 'GRAND TOTAL');
    $sheet->getStyle("{$grandTotalColumnLetter}{$pivotTitleRow}:{$grandTotalColumnLetter}{$headerRow}")
        ->applyFromArray($headerStyle);
    $sheet->getStyle("{$grandTotalColumnLetter}{$pivotTitleRow}:{$grandTotalColumnLetter}{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $lastColumnLetter = columnIndexToLetter($currentColumnIndex - 1);
    $sheet->getColumnDimension($grandTotalColumnLetter)->setAutoSize(false)->setWidth(14);

    $currentRow = $dataRow;
    $counter = 1;
    $firstIceProductColumnLetter = count($columnMetaIce) > 0 ? $columnMetaIce[0]['column_letter'] : '';
    $lastIceProductColumnLetter = count($columnMetaIce) > 0 ? $columnMetaIce[count($columnMetaIce) - 1]['column_letter'] : '';
    $firstOthersProductColumnLetter = count($columnMetaOthers) > 0 ? $columnMetaOthers[0]['column_letter'] : '';
    $lastOthersProductColumnLetter = count($columnMetaOthers) > 0 ? $columnMetaOthers[count($columnMetaOthers) - 1]['column_letter'] : '';

    foreach ($summaryByDate as $saleDate => $totals) {
        $sheet->setCellValue("A{$currentRow}", $counter);
        $sheet->setCellValue("B{$currentRow}", $saleDate);
        $sheet->setCellValue("C{$currentRow}", isset($totals['transactions']) ? (int)$totals['transactions'] : 0);
        $sheet->setCellValue("D{$currentRow}", isset($totals[$paymentKey]) ? (float)$totals[$paymentKey] : 0.0);

        foreach ($columnMetaIce as $columnMeta) {
            $value = 0.0;
            if (isset($productPivotByDate[$saleDate][$columnMeta['product_key']])) {
                $pivotDateValue = $productPivotByDate[$saleDate][$columnMeta['product_key']];
                $value = isset($pivotDateValue['qty']) ? (float)$pivotDateValue['qty'] : 0.0;
            }
            if ($value != 0.0) {
                $sheet->setCellValue($columnMeta['column_letter'] . $currentRow, $value);
            }
        }

        foreach ($columnMetaOthers as $columnMeta) {
            $value = 0.0;
            if (isset($otherPivotByDate[$saleDate][$columnMeta['product_key']])) {
                $pivotDateValue = $otherPivotByDate[$saleDate][$columnMeta['product_key']];
                $value = isset($pivotDateValue['qty']) ? (float)$pivotDateValue['qty'] : 0.0;
            }
            if ($value != 0.0) {
                $sheet->setCellValue($columnMeta['column_letter'] . $currentRow, $value);
            }
        }

        if ($productSubtotalColumnLetter !== '') {
            if (!empty($iceTypeSubtotalColumns)) {
                $iceSubtotalRefs = [];
                foreach ($iceTypeSubtotalColumns as $typeSubtotalColumn) {
                    $iceSubtotalRefs[] = $typeSubtotalColumn['column_letter'] . $currentRow;
                }
                $sheet->setCellValue($productSubtotalColumnLetter . $currentRow, '=' . implode('+', $iceSubtotalRefs));
            } elseif ($firstIceProductColumnLetter !== '' && $lastIceProductColumnLetter !== '') {
                $sheet->setCellValue(
                    $productSubtotalColumnLetter . $currentRow,
                    "=SUM({$firstIceProductColumnLetter}{$currentRow}:{$lastIceProductColumnLetter}{$currentRow})"
                );
            } else {
                $sheet->setCellValue($productSubtotalColumnLetter . $currentRow, 0);
            }
        }
        foreach ($iceTypeSubtotalColumns as $typeSubtotalColumn) {
            $sheet->setCellValue(
                $typeSubtotalColumn['column_letter'] . $currentRow,
                "=SUM({$typeSubtotalColumn['start_column_letter']}{$currentRow}:{$typeSubtotalColumn['end_column_letter']}{$currentRow})"
            );
        }
        if ($othersSubtotalColumnLetter !== '') {
            if ($firstOthersProductColumnLetter !== '' && $lastOthersProductColumnLetter !== '') {
                $sheet->setCellValue(
                    $othersSubtotalColumnLetter . $currentRow,
                    "=SUM({$firstOthersProductColumnLetter}{$currentRow}:{$lastOthersProductColumnLetter}{$currentRow})"
                );
            } else {
                $sheet->setCellValue($othersSubtotalColumnLetter . $currentRow, 0);
            }
        }
        if ($productSubtotalColumnLetter !== '' && $othersSubtotalColumnLetter !== '') {
            $sheet->setCellValue($grandTotalColumnLetter . $currentRow, "={$productSubtotalColumnLetter}{$currentRow}+{$othersSubtotalColumnLetter}{$currentRow}");
        } elseif ($productSubtotalColumnLetter !== '') {
            $sheet->setCellValue($grandTotalColumnLetter . $currentRow, "={$productSubtotalColumnLetter}{$currentRow}");
        } elseif ($othersSubtotalColumnLetter !== '') {
            $sheet->setCellValue($grandTotalColumnLetter . $currentRow, "={$othersSubtotalColumnLetter}{$currentRow}");
        } else {
            $sheet->setCellValue($grandTotalColumnLetter . $currentRow, 0);
        }
        $sheet->getStyle("D{$currentRow}:{$lastColumnLetter}{$currentRow}")
            ->getNumberFormat()
            ->setFormatCode($currencyFormat);

        $currentRow++;
        $counter++;
    }

    $lastDetailRow = $currentRow - 1;
    $sheet->setCellValue("B{$currentRow}", 'Total');
    if ($lastDetailRow >= $dataRow) {
        $sheet->setCellValue("C{$currentRow}", "=SUM(C{$dataRow}:C{$lastDetailRow})");
        $sheet->setCellValue("D{$currentRow}", "=SUM(D{$dataRow}:D{$lastDetailRow})");
    } else {
        $sheet->setCellValue("C{$currentRow}", 0);
        $sheet->setCellValue("D{$currentRow}", 0);
    }
    foreach (array_merge($columnMetaIce, $columnMetaOthers) as $columnMeta) {
        $columnLetter = $columnMeta['column_letter'];
        if ($lastDetailRow >= $dataRow) {
            $sheet->setCellValue("{$columnLetter}{$currentRow}", "=SUM({$columnLetter}{$dataRow}:{$columnLetter}{$lastDetailRow})");
        } else {
            $sheet->setCellValue("{$columnLetter}{$currentRow}", 0);
        }
    }
    foreach ($iceTypeSubtotalColumns as $typeSubtotalColumn) {
        if ($currentRow > $dataRow) {
            $sheet->setCellValue(
                $typeSubtotalColumn['column_letter'] . $currentRow,
                "=SUM({$typeSubtotalColumn['column_letter']}{$dataRow}:{$typeSubtotalColumn['column_letter']}" . ($currentRow - 1) . ')'
            );
        } else {
            $sheet->setCellValue($typeSubtotalColumn['column_letter'] . $currentRow, 0);
        }
    }
    if ($productSubtotalColumnLetter !== '') {
        if ($lastDetailRow >= $dataRow) {
            $sheet->setCellValue(
                $productSubtotalColumnLetter . $currentRow,
                "=SUM({$productSubtotalColumnLetter}{$dataRow}:{$productSubtotalColumnLetter}{$lastDetailRow})"
            );
        } else {
            $sheet->setCellValue($productSubtotalColumnLetter . $currentRow, 0);
        }
    }
    if ($othersSubtotalColumnLetter !== '') {
        if ($lastDetailRow >= $dataRow) {
            $sheet->setCellValue(
                $othersSubtotalColumnLetter . $currentRow,
                "=SUM({$othersSubtotalColumnLetter}{$dataRow}:{$othersSubtotalColumnLetter}{$lastDetailRow})"
            );
        } else {
            $sheet->setCellValue($othersSubtotalColumnLetter . $currentRow, 0);
        }
    }
    if ($lastDetailRow >= $dataRow) {
        $sheet->setCellValue($grandTotalColumnLetter . $currentRow, "=SUM({$grandTotalColumnLetter}{$dataRow}:{$grandTotalColumnLetter}{$lastDetailRow})");
    } else {
        $sheet->setCellValue($grandTotalColumnLetter . $currentRow, 0);
    }

    $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")->getFont()->setBold(true);
    $sheet->getStyle("D{$currentRow}:{$lastColumnLetter}{$currentRow}")
        ->getNumberFormat()
        ->setFormatCode($currencyFormat);

    if ($currentRow >= $headerRow) {
        $sheet->getStyle("A{$headerRow}:{$lastColumnLetter}{$currentRow}")->applyFromArray($borderStyle);
    }
    if ($lastColumnLetter !== 'D') {
        $sheet->getStyle("E{$pivotTitleRow}:{$lastColumnLetter}{$headerRow}")->applyFromArray($borderStyle);
    }

    $sheet->getStyle("A{$headerRow}:C{$currentRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("D{$headerRow}:{$lastColumnLetter}{$headerRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("D{$dataRow}:{$lastColumnLetter}{$currentRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->freezePane('A9');
}

function mapRegisterRowsToAmountMetric($registerRows)
{
    $mappedRows = [];
    foreach ($registerRows as $row) {
        $row['qty'] = isset($row['amount']) ? (float)$row['amount'] : 0.0;
        $mappedRows[] = $row;
    }

    return $mappedRows;
}

function mapRegisterRowsToMetric($registerRows, $metricKey)
{
    $mappedRows = [];
    foreach ($registerRows as $row) {
        $row['qty'] = isset($row[$metricKey]) ? (float)$row[$metricKey] : 0.0;
        $mappedRows[] = $row;
    }

    return $mappedRows;
}

function convertRegisterRowsToUmu($registerRows)
{
    // Amount report: keep values as-is (no UMU conversion needed).
    return $registerRows;
}

$registerRows = fetchToDateRegisterRows($mysqli, $startDate, $endDate);
$registerRows = enrichRegisterRowsWithOrderPayments($registerRows);
$registerRows = mapRegisterRowsToAmountMetric($registerRows);
$productPivotRows = buildProductPivotRows($registerRows);
$productPivotByDate = buildProductPivotByDate($registerRows);
$otherPivotRows = buildOthersPivotRows($registerRows);
$otherPivotByDate = buildOthersPivotByDate($registerRows);
$cashMetricRows = mapRegisterRowsToMetric($registerRows, 'cash_allocated');
$cashProductPivotByDate = buildProductPivotByDate($cashMetricRows);
$cashOtherPivotByDate = buildOthersPivotByDate($cashMetricRows);
$onAccountMetricRows = mapRegisterRowsToMetric($registerRows, 'on_account_allocated');
$onAccountProductPivotByDate = buildProductPivotByDate($onAccountMetricRows);
$onAccountOtherPivotByDate = buildOthersPivotByDate($onAccountMetricRows);
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

$summaryTitle = 'Sales Amount Summary by Date';
if ($reportType === 'by_product') {
    $summaryTitle = 'Sales Amount Summary by Date - By Product';
}
$summarySheet->setCellValue('A1', $summaryTitle);
$summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$summarySheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
$summarySheet->setCellValue('A3', 'Generated: ' . $generatedAt);

$summaryHeaderRow = 8;
$summaryDataRow = 9;
$summaryHeaders = [
    'NO.',
    'DATE',
    'TRANSACTION COUNT',
    'TOTAL AMOUNT',
    'ICE TOTAL AMOUNT',
    'OTHERS TOTAL AMOUNT',
    'AMOUNT RECEIVED',
    'CASH TOTAL',
    'ON ACCOUNT TOTAL'
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
    'ice_total' => 0,
    'others_total' => 0,
    'amount' => 0,
    'amt_received' => 0,
    'amt_change' => 0,
    'cash' => 0,
    'on_account' => 0
];
foreach ($summaryByDate as $saleDate => $totals) {
    $iceTotalAmount = 0.0;
    if (isset($productPivotByDate[$saleDate])) {
        foreach ($productPivotByDate[$saleDate] as $pivotDateValue) {
            $iceTotalAmount += isset($pivotDateValue['qty']) ? (float)$pivotDateValue['qty'] : 0.0;
        }
    }
    $othersTotalAmount = 0.0;
    if (isset($otherPivotByDate[$saleDate])) {
        foreach ($otherPivotByDate[$saleDate] as $otherPivotDateValue) {
            $othersTotalAmount += isset($otherPivotDateValue['qty']) ? (float)$otherPivotDateValue['qty'] : 0.0;
        }
    }

    $summarySheet->setCellValue("A{$currentRow}", $counter);
    $summarySheet->setCellValue("B{$currentRow}", $saleDate);
    $summarySheet->setCellValue("C{$currentRow}", $totals['transactions']);
    $summarySheet->setCellValue("D{$currentRow}", $totals['amount']);
    $summarySheet->setCellValue("E{$currentRow}", $iceTotalAmount);
    $summarySheet->setCellValue("F{$currentRow}", $othersTotalAmount);
    $summarySheet->setCellValue("G{$currentRow}", $totals['amt_received']);
    $summarySheet->setCellValue("H{$currentRow}", $totals['cash']);
    $summarySheet->setCellValue("I{$currentRow}", $totals['on_account']);
    $summarySheet->getStyle("D{$currentRow}:I{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $summaryTotals['transactions'] += $totals['transactions'];
    $summaryTotals['qty'] += $totals['amount'];
    $summaryTotals['ice_total'] += $iceTotalAmount;
    $summaryTotals['others_total'] += $othersTotalAmount;
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
$summarySheet->setCellValue("D{$currentRow}", $summaryTotals['amount']);
$summarySheet->setCellValue("E{$currentRow}", $summaryTotals['ice_total']);
$summarySheet->setCellValue("F{$currentRow}", $summaryTotals['others_total']);
$summarySheet->setCellValue("G{$currentRow}", $summaryTotals['amt_received']);
$summarySheet->setCellValue("H{$currentRow}", $summaryTotals['cash']);
$summarySheet->setCellValue("I{$currentRow}", $summaryTotals['on_account']);
$summarySheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray($headerStyle);
$summarySheet->getStyle("A{$currentRow}:I{$currentRow}")->getFont()->setBold(true);
$summarySheet->getStyle("D{$currentRow}:I{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

$summaryTotalRow = $currentRow;
if ($reportType === 'by_product' && (!empty($productPivotRows) || !empty($otherPivotRows))) {
    $summaryPivotHeaderRow = 6;
    $summaryPivotCodeRow = 7;
    $summaryPivotDescriptionRow = 8;
    $summaryPivotTitleRow = 5;
    $summaryPivotStartColumnIndex = 10; // J
    $summaryPivotLastColumnIndex = $summaryPivotStartColumnIndex - 1;
    $summaryProductColumnsByCode = [];
    $summaryProductTypeGroups = [];
    $summaryProductTypeSubtotalColumns = [];
    $summaryCurrentProductColumnIndex = $summaryPivotStartColumnIndex;
    $summaryCurrentTypeKey = '';
    $summaryCurrentTypeLabel = '';
    $summaryCurrentTypeStartColumnIndex = null;
    $summaryCurrentTypeLastProductColumnIndex = null;
    $summaryPivotFirstColumnIndex = null;

    foreach ($productPivotRows as $pivotData) {
        $productCodeKey = strtoupper(trim((string)(isset($pivotData['product_code']) ? $pivotData['product_code'] : '')));
        if ($productCodeKey === '') {
            continue;
        }

        $productTypeLabel = isset($pivotData['product_type']) ? trim((string)$pivotData['product_type']) : '';
        if ($productTypeLabel === '') {
            $productTypeLabel = 'UNCLASSIFIED';
        }
        $productTypeKey = strtoupper($productTypeLabel);

        if ($summaryCurrentTypeKey !== '' && $productTypeKey !== $summaryCurrentTypeKey && $summaryCurrentTypeStartColumnIndex !== null && $summaryCurrentTypeLastProductColumnIndex !== null) {
            $typeSubtotalColumnIndex = $summaryCurrentProductColumnIndex++;
            $typeSubtotalColumnLetter = columnIndexToLetter($typeSubtotalColumnIndex);
            $summarySheet->mergeCells("{$typeSubtotalColumnLetter}{$summaryPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryPivotDescriptionRow}");
            $summarySheet->setCellValue("{$typeSubtotalColumnLetter}{$summaryPivotCodeRow}", 'SUB-TOTAL');
            $summarySheet->getStyle("{$typeSubtotalColumnLetter}{$summaryPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryPivotDescriptionRow}")
                ->applyFromArray($headerStyle);
            $summarySheet->getStyle("{$typeSubtotalColumnLetter}{$summaryPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summarySheet->getColumnDimension($typeSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

            $summaryProductTypeGroups[] = [
                'label' => $summaryCurrentTypeLabel,
                'start_column_index' => $summaryCurrentTypeStartColumnIndex,
                'end_column_index' => $typeSubtotalColumnIndex
            ];
            $summaryProductTypeSubtotalColumns[] = [
                'column_letter' => $typeSubtotalColumnLetter,
                'start_column_letter' => columnIndexToLetter($summaryCurrentTypeStartColumnIndex),
                'end_column_letter' => columnIndexToLetter($summaryCurrentTypeLastProductColumnIndex)
            ];

            $summaryCurrentTypeStartColumnIndex = null;
            $summaryCurrentTypeLastProductColumnIndex = null;
        }

        if ($summaryCurrentTypeKey === '' || $productTypeKey !== $summaryCurrentTypeKey || $summaryCurrentTypeStartColumnIndex === null) {
            $summaryCurrentTypeKey = $productTypeKey;
            $summaryCurrentTypeLabel = $productTypeLabel;
            $summaryCurrentTypeStartColumnIndex = $summaryCurrentProductColumnIndex;
        }

        $qtyColumnIndex = $summaryCurrentProductColumnIndex;
        $qtyColumnLetter = columnIndexToLetter($qtyColumnIndex);
        if ($summaryPivotFirstColumnIndex === null) {
            $summaryPivotFirstColumnIndex = $qtyColumnIndex;
        }
        $summaryPivotLastColumnIndex = $qtyColumnIndex;

        $productDescriptionHeader = isset($pivotData['product_description']) ? (string)$pivotData['product_description'] : '';
        $uomHeader = trim((string)(isset($pivotData['uom']) ? $pivotData['uom'] : ''));
        if ($uomHeader !== '') {
            $productDescriptionHeader .= "\n(" . $uomHeader . ')';
        }

        $summarySheet->setCellValue("{$qtyColumnLetter}{$summaryPivotCodeRow}", isset($pivotData['product_code']) ? (string)$pivotData['product_code'] : '');
        $summarySheet->setCellValue("{$qtyColumnLetter}{$summaryPivotDescriptionRow}", $productDescriptionHeader);
        $summarySheet->getStyle("{$qtyColumnLetter}{$summaryPivotHeaderRow}:{$qtyColumnLetter}{$summaryPivotDescriptionRow}")
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setRGB('000000');
        $summarySheet->getStyle("{$qtyColumnLetter}{$summaryPivotHeaderRow}:{$qtyColumnLetter}{$summaryPivotDescriptionRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $summarySheet->getStyle("{$qtyColumnLetter}{$summaryPivotHeaderRow}:{$qtyColumnLetter}{$summaryPivotDescriptionRow}")
            ->applyFromArray($borderStyle);
        $summarySheet->getColumnDimension($qtyColumnLetter)->setAutoSize(false)->setWidth(14);

        $summaryProductColumnsByCode[$productCodeKey] = [
            'qty_column' => $qtyColumnLetter
        ];

        $summaryCurrentTypeLastProductColumnIndex = $qtyColumnIndex;
        $summaryCurrentProductColumnIndex++;
    }

    if ($summaryCurrentTypeKey !== '' && $summaryCurrentTypeStartColumnIndex !== null && $summaryCurrentTypeLastProductColumnIndex !== null) {
        $typeSubtotalColumnIndex = $summaryCurrentProductColumnIndex++;
        $typeSubtotalColumnLetter = columnIndexToLetter($typeSubtotalColumnIndex);
        $summarySheet->mergeCells("{$typeSubtotalColumnLetter}{$summaryPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryPivotDescriptionRow}");
        $summarySheet->setCellValue("{$typeSubtotalColumnLetter}{$summaryPivotCodeRow}", 'SUB-TOTAL');
        $summarySheet->getStyle("{$typeSubtotalColumnLetter}{$summaryPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryPivotDescriptionRow}")
            ->applyFromArray($headerStyle);
        $summarySheet->getStyle("{$typeSubtotalColumnLetter}{$summaryPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryPivotDescriptionRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $summarySheet->getColumnDimension($typeSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

        $summaryProductTypeGroups[] = [
            'label' => $summaryCurrentTypeLabel,
            'start_column_index' => $summaryCurrentTypeStartColumnIndex,
            'end_column_index' => $typeSubtotalColumnIndex
        ];
        $summaryProductTypeSubtotalColumns[] = [
            'column_letter' => $typeSubtotalColumnLetter,
            'start_column_letter' => columnIndexToLetter($summaryCurrentTypeStartColumnIndex),
            'end_column_letter' => columnIndexToLetter($summaryCurrentTypeLastProductColumnIndex)
        ];
    }

    foreach ($summaryProductTypeGroups as $typeGroup) {
        $typeStartColumnLetter = columnIndexToLetter($typeGroup['start_column_index']);
        $typeEndColumnLetter = columnIndexToLetter($typeGroup['end_column_index']);
        if ($typeStartColumnLetter !== $typeEndColumnLetter) {
            $summarySheet->mergeCells("{$typeStartColumnLetter}{$summaryPivotHeaderRow}:{$typeEndColumnLetter}{$summaryPivotHeaderRow}");
        }
        $summarySheet->setCellValue("{$typeStartColumnLetter}{$summaryPivotHeaderRow}", $typeGroup['label']);
    }

    $summaryFinalLastColumnLetter = '';
    $summaryOtherColumnsByCode = [];
    $summaryOtherFirstColumnIndex = null;
    $summaryProductSubtotalColumnIndex = null;
    $summaryProductSubtotalColumnLetter = '';
    $summaryOthersSubtotalColumnIndex = null;
    $summaryOthersSubtotalColumnLetter = '';
    $summaryGrandTotalColumnIndex = null;
    $summaryGrandTotalColumnLetter = '';
    $summaryLastValueColumnIndex = null;
    $summaryPivotFirstColumnLetter = '';
    $summaryPivotLastColumnLetter = '';
    $summaryOtherFirstColumnLetter = '';
    $summaryOtherLastColumnLetter = '';

    if ($summaryPivotLastColumnIndex >= $summaryPivotStartColumnIndex) {
        $summaryPivotFirstColumnLetter = columnIndexToLetter($summaryPivotFirstColumnIndex !== null ? $summaryPivotFirstColumnIndex : $summaryPivotStartColumnIndex);
        $summaryPivotLastColumnLetter = columnIndexToLetter($summaryPivotLastColumnIndex);
        $summaryFinalLastColumnLetter = $summaryPivotLastColumnLetter;
        $summaryProductSubtotalColumnIndex = $summaryCurrentProductColumnIndex++;
        $summaryProductSubtotalColumnLetter = columnIndexToLetter($summaryProductSubtotalColumnIndex);
        $summarySheet->mergeCells("{$summaryProductSubtotalColumnLetter}{$summaryPivotHeaderRow}:{$summaryProductSubtotalColumnLetter}{$summaryPivotDescriptionRow}");
        $summarySheet->setCellValue("{$summaryProductSubtotalColumnLetter}{$summaryPivotHeaderRow}", 'TOTAL');
        $summarySheet->getStyle("{$summaryProductSubtotalColumnLetter}{$summaryPivotHeaderRow}:{$summaryProductSubtotalColumnLetter}{$summaryPivotDescriptionRow}")
            ->applyFromArray($headerStyle);
        $summarySheet->getStyle("{$summaryProductSubtotalColumnLetter}{$summaryPivotHeaderRow}:{$summaryProductSubtotalColumnLetter}{$summaryPivotDescriptionRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $summarySheet->getColumnDimension($summaryProductSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

        if ($summaryPivotFirstColumnLetter !== $summaryProductSubtotalColumnLetter) {
            $summarySheet->mergeCells("{$summaryPivotFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryProductSubtotalColumnLetter}{$summaryPivotTitleRow}");
        }
        $summarySheet->setCellValue("{$summaryPivotFirstColumnLetter}{$summaryPivotTitleRow}", 'PRODUCT');
        $summarySheet->getStyle("{$summaryPivotFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryProductSubtotalColumnLetter}{$summaryPivotTitleRow}")
            ->getFont()
            ->setBold(true);
        $summarySheet->getStyle("{$summaryPivotFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryProductSubtotalColumnLetter}{$summaryPivotTitleRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $summaryFinalLastColumnLetter = $summaryProductSubtotalColumnLetter;
        $summaryLastValueColumnIndex = $summaryProductSubtotalColumnIndex;
    }

    if (!empty($otherPivotRows)) {
        $summaryOtherStartColumnIndex = $summaryProductSubtotalColumnIndex !== null
            ? $summaryProductSubtotalColumnIndex + 1
            : ($summaryPivotLastColumnIndex >= $summaryPivotStartColumnIndex
                ? $summaryPivotLastColumnIndex + 1
                : $summaryPivotStartColumnIndex);
        $summaryOtherLastColumnIndex = $summaryOtherStartColumnIndex - 1;

        foreach ($otherPivotRows as $otherIndex => $otherData) {
            $otherQtyColumnIndex = $summaryOtherStartColumnIndex + $otherIndex;
            $otherQtyColumnLetter = columnIndexToLetter($otherQtyColumnIndex);
            $summaryOtherLastColumnIndex = $otherQtyColumnIndex;
            if ($summaryOtherFirstColumnIndex === null) {
                $summaryOtherFirstColumnIndex = $otherQtyColumnIndex;
            }

            $otherDescriptionHeader = isset($otherData['product_description']) ? (string)$otherData['product_description'] : '';
            $otherUomHeader = trim((string)(isset($otherData['uom']) ? $otherData['uom'] : ''));
            if ($otherUomHeader !== '') {
                $otherDescriptionHeader .= "\n(" . $otherUomHeader . ')';
            }

            $summarySheet->setCellValue("{$otherQtyColumnLetter}{$summaryPivotCodeRow}", isset($otherData['product_code']) ? (string)$otherData['product_code'] : '');
            $summarySheet->setCellValue("{$otherQtyColumnLetter}{$summaryPivotDescriptionRow}", $otherDescriptionHeader);
            $summarySheet->getStyle("{$otherQtyColumnLetter}{$summaryPivotHeaderRow}:{$otherQtyColumnLetter}{$summaryPivotDescriptionRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            $summarySheet->getStyle("{$otherQtyColumnLetter}{$summaryPivotHeaderRow}:{$otherQtyColumnLetter}{$summaryPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summarySheet->getStyle("{$otherQtyColumnLetter}{$summaryPivotHeaderRow}:{$otherQtyColumnLetter}{$summaryPivotDescriptionRow}")
                ->applyFromArray($borderStyle);
            $summarySheet->getColumnDimension($otherQtyColumnLetter)->setAutoSize(false)->setWidth(14);

            $otherProductCodeKey = strtoupper(trim((string)(isset($otherData['product_code']) ? $otherData['product_code'] : '')));
            if ($otherProductCodeKey !== '') {
                $summaryOtherColumnsByCode[$otherProductCodeKey] = [
                    'qty_column' => $otherQtyColumnLetter
                ];
            }
        }

        if ($summaryOtherFirstColumnIndex !== null) {
            $summaryOtherFirstColumnLetter = columnIndexToLetter($summaryOtherFirstColumnIndex);
            $summaryOtherLastColumnLetter = columnIndexToLetter($summaryOtherLastColumnIndex);
            $summaryOthersSubtotalColumnIndex = $summaryOtherLastColumnIndex + 1;
            $summaryOthersSubtotalColumnLetter = columnIndexToLetter($summaryOthersSubtotalColumnIndex);
            $summarySheet->mergeCells("{$summaryOthersSubtotalColumnLetter}{$summaryPivotHeaderRow}:{$summaryOthersSubtotalColumnLetter}{$summaryPivotDescriptionRow}");
            $summarySheet->setCellValue("{$summaryOthersSubtotalColumnLetter}{$summaryPivotHeaderRow}", 'SUBTOTAL');
            $summarySheet->getStyle("{$summaryOthersSubtotalColumnLetter}{$summaryPivotHeaderRow}:{$summaryOthersSubtotalColumnLetter}{$summaryPivotDescriptionRow}")
                ->applyFromArray($headerStyle);
            $summarySheet->getStyle("{$summaryOthersSubtotalColumnLetter}{$summaryPivotHeaderRow}:{$summaryOthersSubtotalColumnLetter}{$summaryPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summarySheet->getColumnDimension($summaryOthersSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

            if ($summaryOtherFirstColumnLetter !== $summaryOthersSubtotalColumnLetter) {
                $summarySheet->mergeCells("{$summaryOtherFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryOthersSubtotalColumnLetter}{$summaryPivotTitleRow}");
            }
            $summarySheet->setCellValue("{$summaryOtherFirstColumnLetter}{$summaryPivotTitleRow}", 'OTHERS PRODUCT');
            $summarySheet->getStyle("{$summaryOtherFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryOthersSubtotalColumnLetter}{$summaryPivotTitleRow}")
                ->getFont()
                ->setBold(true);
            $summarySheet->getStyle("{$summaryOtherFirstColumnLetter}{$summaryPivotTitleRow}:{$summaryOthersSubtotalColumnLetter}{$summaryPivotTitleRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryFinalLastColumnLetter = $summaryOthersSubtotalColumnLetter;
            $summaryLastValueColumnIndex = $summaryOthersSubtotalColumnIndex;
        }
    }

    if ($summaryLastValueColumnIndex !== null) {
        $summaryGrandTotalColumnIndex = $summaryLastValueColumnIndex + 1;
        $summaryGrandTotalColumnLetter = columnIndexToLetter($summaryGrandTotalColumnIndex);
        $summarySheet->mergeCells("{$summaryGrandTotalColumnLetter}{$summaryPivotTitleRow}:{$summaryGrandTotalColumnLetter}{$summaryPivotDescriptionRow}");
        $summarySheet->setCellValue("{$summaryGrandTotalColumnLetter}{$summaryPivotTitleRow}", 'GRAND TOTAL');
        $summarySheet->getStyle("{$summaryGrandTotalColumnLetter}{$summaryPivotTitleRow}:{$summaryGrandTotalColumnLetter}{$summaryPivotDescriptionRow}")
            ->applyFromArray($headerStyle);
        $summarySheet->getStyle("{$summaryGrandTotalColumnLetter}{$summaryPivotTitleRow}:{$summaryGrandTotalColumnLetter}{$summaryPivotDescriptionRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $summarySheet->getColumnDimension($summaryGrandTotalColumnLetter)->setAutoSize(false)->setWidth(14);
        $summaryFinalLastColumnLetter = $summaryGrandTotalColumnLetter;
    }

    $summaryPivotRowPointer = $summaryDataRow;
    foreach ($summaryByDate as $saleDate => $totals) {
        foreach ($summaryProductColumnsByCode as $productCodeKey => $columnMeta) {
            if (!isset($productPivotByDate[$saleDate][$productCodeKey])) {
                continue;
            }
            $pivotDateValue = $productPivotByDate[$saleDate][$productCodeKey];
            $qtyValue = isset($pivotDateValue['qty']) ? (float)$pivotDateValue['qty'] : 0.0;
            if ($qtyValue != 0.0) {
                $summarySheet->setCellValue($columnMeta['qty_column'] . $summaryPivotRowPointer, $qtyValue);
            }
        }
        foreach ($summaryProductTypeSubtotalColumns as $typeSubtotalColumn) {
            $summarySheet->setCellValue(
                $typeSubtotalColumn['column_letter'] . $summaryPivotRowPointer,
                "=SUM({$typeSubtotalColumn['start_column_letter']}{$summaryPivotRowPointer}:{$typeSubtotalColumn['end_column_letter']}{$summaryPivotRowPointer})"
            );
        }
        if ($summaryProductSubtotalColumnLetter !== '') {
            if (!empty($summaryProductTypeSubtotalColumns)) {
                $summaryTypeSubtotalRefs = [];
                foreach ($summaryProductTypeSubtotalColumns as $typeSubtotalColumn) {
                    $summaryTypeSubtotalRefs[] = $typeSubtotalColumn['column_letter'] . $summaryPivotRowPointer;
                }
                $summarySheet->setCellValue(
                    $summaryProductSubtotalColumnLetter . $summaryPivotRowPointer,
                    '=' . implode('+', $summaryTypeSubtotalRefs)
                );
            } elseif ($summaryPivotFirstColumnLetter !== '' && $summaryPivotLastColumnLetter !== '') {
                $summarySheet->setCellValue(
                    $summaryProductSubtotalColumnLetter . $summaryPivotRowPointer,
                    "=SUM({$summaryPivotFirstColumnLetter}{$summaryPivotRowPointer}:{$summaryPivotLastColumnLetter}{$summaryPivotRowPointer})"
                );
            } else {
                $summarySheet->setCellValue($summaryProductSubtotalColumnLetter . $summaryPivotRowPointer, 0);
            }
        }

        foreach ($summaryOtherColumnsByCode as $otherProductCodeKey => $otherColumnMeta) {
            if (!isset($otherPivotByDate[$saleDate][$otherProductCodeKey])) {
                continue;
            }
            $otherPivotDateValue = $otherPivotByDate[$saleDate][$otherProductCodeKey];
            $otherQtyValue = isset($otherPivotDateValue['qty']) ? (float)$otherPivotDateValue['qty'] : 0.0;
            if ($otherQtyValue != 0.0) {
                $summarySheet->setCellValue($otherColumnMeta['qty_column'] . $summaryPivotRowPointer, $otherQtyValue);
            }
        }
        if ($summaryOthersSubtotalColumnLetter !== '') {
            if ($summaryOtherFirstColumnLetter !== '' && $summaryOtherLastColumnLetter !== '') {
                $summarySheet->setCellValue(
                    $summaryOthersSubtotalColumnLetter . $summaryPivotRowPointer,
                    "=SUM({$summaryOtherFirstColumnLetter}{$summaryPivotRowPointer}:{$summaryOtherLastColumnLetter}{$summaryPivotRowPointer})"
                );
            } else {
                $summarySheet->setCellValue($summaryOthersSubtotalColumnLetter . $summaryPivotRowPointer, 0);
            }
        }
        if ($summaryGrandTotalColumnLetter !== '') {
            if ($summaryProductSubtotalColumnLetter !== '' && $summaryOthersSubtotalColumnLetter !== '') {
                $summarySheet->setCellValue(
                    $summaryGrandTotalColumnLetter . $summaryPivotRowPointer,
                    "={$summaryProductSubtotalColumnLetter}{$summaryPivotRowPointer}+{$summaryOthersSubtotalColumnLetter}{$summaryPivotRowPointer}"
                );
            } elseif ($summaryProductSubtotalColumnLetter !== '') {
                $summarySheet->setCellValue(
                    $summaryGrandTotalColumnLetter . $summaryPivotRowPointer,
                    "={$summaryProductSubtotalColumnLetter}{$summaryPivotRowPointer}"
                );
            } elseif ($summaryOthersSubtotalColumnLetter !== '') {
                $summarySheet->setCellValue(
                    $summaryGrandTotalColumnLetter . $summaryPivotRowPointer,
                    "={$summaryOthersSubtotalColumnLetter}{$summaryPivotRowPointer}"
                );
            } else {
                $summarySheet->setCellValue($summaryGrandTotalColumnLetter . $summaryPivotRowPointer, 0);
            }
        }

        $summaryPivotRowPointer++;
    }

    foreach ($summaryProductColumnsByCode as $columnMeta) {
        $qtyColumnLetter = $columnMeta['qty_column'];
        if ($lastSummaryDetailRow >= $summaryDataRow) {
            $summarySheet->setCellValue("{$qtyColumnLetter}{$summaryTotalRow}", "=SUM({$qtyColumnLetter}{$summaryDataRow}:{$qtyColumnLetter}{$lastSummaryDetailRow})");
        } else {
            $summarySheet->setCellValue("{$qtyColumnLetter}{$summaryTotalRow}", 0);
        }
    }
    foreach ($summaryOtherColumnsByCode as $otherColumnMeta) {
        $otherQtyColumnLetter = $otherColumnMeta['qty_column'];
        if ($lastSummaryDetailRow >= $summaryDataRow) {
            $summarySheet->setCellValue("{$otherQtyColumnLetter}{$summaryTotalRow}", "=SUM({$otherQtyColumnLetter}{$summaryDataRow}:{$otherQtyColumnLetter}{$lastSummaryDetailRow})");
        } else {
            $summarySheet->setCellValue("{$otherQtyColumnLetter}{$summaryTotalRow}", 0);
        }
    }
    foreach ($summaryProductTypeSubtotalColumns as $typeSubtotalColumn) {
        $typeSubtotalColumnLetter = $typeSubtotalColumn['column_letter'];
        if ($lastSummaryDetailRow >= $summaryDataRow) {
            $summarySheet->setCellValue(
                "{$typeSubtotalColumnLetter}{$summaryTotalRow}",
                "=SUM({$typeSubtotalColumnLetter}{$summaryDataRow}:{$typeSubtotalColumnLetter}{$lastSummaryDetailRow})"
            );
        } else {
            $summarySheet->setCellValue("{$typeSubtotalColumnLetter}{$summaryTotalRow}", 0);
        }
    }
    if ($summaryProductSubtotalColumnLetter !== '') {
        if ($lastSummaryDetailRow >= $summaryDataRow) {
            $summarySheet->setCellValue(
                "{$summaryProductSubtotalColumnLetter}{$summaryTotalRow}",
                "=SUM({$summaryProductSubtotalColumnLetter}{$summaryDataRow}:{$summaryProductSubtotalColumnLetter}{$lastSummaryDetailRow})"
            );
        } else {
            $summarySheet->setCellValue("{$summaryProductSubtotalColumnLetter}{$summaryTotalRow}", 0);
        }
    }
    if ($summaryOthersSubtotalColumnLetter !== '') {
        if ($lastSummaryDetailRow >= $summaryDataRow) {
            $summarySheet->setCellValue(
                "{$summaryOthersSubtotalColumnLetter}{$summaryTotalRow}",
                "=SUM({$summaryOthersSubtotalColumnLetter}{$summaryDataRow}:{$summaryOthersSubtotalColumnLetter}{$lastSummaryDetailRow})"
            );
        } else {
            $summarySheet->setCellValue("{$summaryOthersSubtotalColumnLetter}{$summaryTotalRow}", 0);
        }
    }
    if ($summaryGrandTotalColumnLetter !== '') {
        if ($lastSummaryDetailRow >= $summaryDataRow) {
            $summarySheet->setCellValue(
                "{$summaryGrandTotalColumnLetter}{$summaryTotalRow}",
                "=SUM({$summaryGrandTotalColumnLetter}{$summaryDataRow}:{$summaryGrandTotalColumnLetter}{$lastSummaryDetailRow})"
            );
        } else {
            $summarySheet->setCellValue("{$summaryGrandTotalColumnLetter}{$summaryTotalRow}", 0);
        }
    }

    $summaryPivotFirstDataColumnIndex = null;
    if ($summaryPivotLastColumnIndex >= $summaryPivotStartColumnIndex) {
        $summaryPivotFirstDataColumnIndex = $summaryPivotStartColumnIndex;
    } elseif ($summaryOtherFirstColumnIndex !== null) {
        $summaryPivotFirstDataColumnIndex = $summaryOtherFirstColumnIndex;
    }

    if ($summaryPivotFirstDataColumnIndex !== null && $summaryFinalLastColumnLetter !== '') {
        $summaryPivotFirstDataColumnLetter = columnIndexToLetter($summaryPivotFirstDataColumnIndex);
        $summarySheet->getStyle("{$summaryPivotFirstDataColumnLetter}{$summaryPivotTitleRow}:{$summaryFinalLastColumnLetter}{$summaryTotalRow}")
            ->applyFromArray($borderStyle);
        $summarySheet->getStyle("{$summaryPivotFirstDataColumnLetter}{$summaryPivotTitleRow}:{$summaryFinalLastColumnLetter}{$summaryPivotDescriptionRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $summarySheet->getStyle("{$summaryPivotFirstDataColumnLetter}{$summaryTotalRow}:{$summaryFinalLastColumnLetter}{$summaryTotalRow}")
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
        foreach ($summaryOtherColumnsByCode as $otherColumnMeta) {
            $otherQtyColumnLetter = $otherColumnMeta['qty_column'];
            $summarySheet->getStyle("{$otherQtyColumnLetter}{$summaryDataRow}:{$otherQtyColumnLetter}{$summaryTotalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }
        foreach ($summaryProductTypeSubtotalColumns as $typeSubtotalColumn) {
            $typeSubtotalColumnLetter = $typeSubtotalColumn['column_letter'];
            $summarySheet->getStyle("{$typeSubtotalColumnLetter}{$summaryDataRow}:{$typeSubtotalColumnLetter}{$summaryTotalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }
        foreach ([$summaryProductSubtotalColumnLetter, $summaryOthersSubtotalColumnLetter, $summaryGrandTotalColumnLetter] as $summaryExtraColumnLetter) {
            if ($summaryExtraColumnLetter === '') {
                continue;
            }
            $summarySheet->getStyle("{$summaryExtraColumnLetter}{$summaryDataRow}:{$summaryExtraColumnLetter}{$summaryTotalRow}")
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
    $summaryUmuSheet->setTitle('Sales Amount Summary');
    $summaryUmuSheet->setCellValue('A1', 'Sales Amount Summary by Date - By Product');
    $summaryUmuSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $summaryUmuSheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
    $summaryUmuSheet->setCellValue('A3', 'Generated: ' . $generatedAt);

    $summaryUmuHeaderRow = 8;
    $summaryUmuDataRow = 9;
    $summaryUmuHeaders = [
        'NO.',
        'DATE',
        'TRANSACTION COUNT',
        'TOTAL AMOUNT'
    ];

    $summaryUmuSheet->fromArray($summaryUmuHeaders, null, 'A' . $summaryUmuHeaderRow);
    $summaryUmuSheet->getStyle("A{$summaryUmuHeaderRow}:D{$summaryUmuHeaderRow}")->applyFromArray($headerStyle);
    $summaryUmuSheet->getStyle("A{$summaryUmuHeaderRow}:D{$summaryUmuHeaderRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $summaryUmuCurrentRow = $summaryUmuDataRow;
    $summaryUmuCounter = 1;
    $summaryUmuTotals = [
        'transactions' => 0,
        'amount' => 0.0,
        'cash' => 0.0,
        'on_account' => 0.0
    ];

    foreach ($summaryByDateUmu as $saleDate => $totals) {
        $summaryUmuSheet->setCellValue("A{$summaryUmuCurrentRow}", $summaryUmuCounter);
        $summaryUmuSheet->setCellValue("B{$summaryUmuCurrentRow}", $saleDate);
        $summaryUmuSheet->setCellValue("C{$summaryUmuCurrentRow}", $totals['transactions']);
        $summaryUmuSheet->setCellValue("D{$summaryUmuCurrentRow}", $totals['amount']);
        $summaryUmuSheet->getStyle("D{$summaryUmuCurrentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        $summaryUmuTotals['transactions'] += $totals['transactions'];
        $summaryUmuTotals['amount'] += $totals['amount'];
        $summaryUmuTotals['cash'] += $totals['cash'];
        $summaryUmuTotals['on_account'] += $totals['on_account'];
        $summaryUmuCurrentRow++;
        $summaryUmuCounter++;
    }

    $lastSummaryUmuDetailRow = $summaryUmuCurrentRow - 1;
    $summaryUmuSheet->setCellValue("B{$summaryUmuCurrentRow}", 'Total');
    $summaryUmuSheet->setCellValue("C{$summaryUmuCurrentRow}", $summaryUmuTotals['transactions']);
    $summaryUmuSheet->setCellValue("D{$summaryUmuCurrentRow}", $summaryUmuTotals['amount']);
    $summaryUmuSheet->getStyle("A{$summaryUmuCurrentRow}:D{$summaryUmuCurrentRow}")->applyFromArray($headerStyle);
    $summaryUmuSheet->getStyle("A{$summaryUmuCurrentRow}:D{$summaryUmuCurrentRow}")->getFont()->setBold(true);
    $summaryUmuSheet->getStyle("D{$summaryUmuCurrentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

    $summaryUmuTotalRow = $summaryUmuCurrentRow;
    if (!empty($productPivotRowsUmu)) {
        $summaryUmuPivotHeaderRow = 6;
        $summaryUmuPivotCodeRow = 7;
        $summaryUmuPivotDescriptionRow = 8;
        $summaryUmuPivotTitleRow = 5;
        $summaryUmuPivotStartColumnIndex = 5; // E
        $summaryUmuPivotLastColumnIndex = $summaryUmuPivotStartColumnIndex - 1;
        $summaryUmuProductColumnsByCode = [];
        $summaryUmuProductTypeGroups = [];
        $summaryUmuProductTypeSubtotalColumns = [];
        $summaryUmuFirstQtyColumnLetter = '';
        $summaryUmuCurrentProductColumnIndex = $summaryUmuPivotStartColumnIndex;
        $summaryUmuPivotFirstColumnIndex = null;
        $summaryUmuCurrentTypeKey = '';
        $summaryUmuCurrentTypeLabel = '';
        $summaryUmuCurrentTypeStartColumnIndex = null;
        $summaryUmuCurrentTypeLastProductColumnIndex = null;

        foreach ($productPivotRowsUmu as $pivotData) {
            $productCodeKey = strtoupper(trim((string)(isset($pivotData['product_code']) ? $pivotData['product_code'] : '')));
            if ($productCodeKey === '') {
                continue;
            }

            $productTypeLabel = isset($pivotData['product_type']) ? trim((string)$pivotData['product_type']) : '';
            if ($productTypeLabel === '') {
                $productTypeLabel = 'UNCLASSIFIED';
            }
            $productTypeKey = strtoupper($productTypeLabel);

            if ($summaryUmuCurrentTypeKey !== '' && $productTypeKey !== $summaryUmuCurrentTypeKey && $summaryUmuCurrentTypeStartColumnIndex !== null && $summaryUmuCurrentTypeLastProductColumnIndex !== null) {
                $typeSubtotalColumnIndex = $summaryUmuCurrentProductColumnIndex++;
                $typeSubtotalColumnLetter = columnIndexToLetter($typeSubtotalColumnIndex);
                $summaryUmuSheet->mergeCells("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}");
                $summaryUmuSheet->setCellValue("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}", 'SUB-TOTAL');
                $summaryUmuSheet->getStyle("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                    ->getFont()
                    ->setBold(true)
                    ->getColor()
                    ->setRGB('000000');
                $summaryUmuSheet->getStyle("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                    ->getAlignment()
                    ->setWrapText(true)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER);
                $summaryUmuSheet->getStyle("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                    ->applyFromArray($borderStyle);
                $summaryUmuSheet->getColumnDimension($typeSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

                $summaryUmuProductTypeGroups[] = [
                    'label' => $summaryUmuCurrentTypeLabel,
                    'start_column_index' => $summaryUmuCurrentTypeStartColumnIndex,
                    'end_column_index' => $typeSubtotalColumnIndex
                ];
                $summaryUmuProductTypeSubtotalColumns[] = [
                    'column_letter' => $typeSubtotalColumnLetter,
                    'start_column_letter' => columnIndexToLetter($summaryUmuCurrentTypeStartColumnIndex),
                    'end_column_letter' => columnIndexToLetter($summaryUmuCurrentTypeLastProductColumnIndex)
                ];

                $summaryUmuCurrentTypeStartColumnIndex = null;
                $summaryUmuCurrentTypeLastProductColumnIndex = null;
            }

            if ($summaryUmuCurrentTypeKey === '' || $productTypeKey !== $summaryUmuCurrentTypeKey || $summaryUmuCurrentTypeStartColumnIndex === null) {
                $summaryUmuCurrentTypeKey = $productTypeKey;
                $summaryUmuCurrentTypeLabel = $productTypeLabel;
                $summaryUmuCurrentTypeStartColumnIndex = $summaryUmuCurrentProductColumnIndex;
            }

            $qtyColumnIndex = $summaryUmuCurrentProductColumnIndex;
            $qtyColumnLetter = columnIndexToLetter($qtyColumnIndex);
            if ($summaryUmuPivotFirstColumnIndex === null) {
                $summaryUmuPivotFirstColumnIndex = $qtyColumnIndex;
            }
            $summaryUmuPivotLastColumnIndex = $qtyColumnIndex;
            if ($summaryUmuFirstQtyColumnLetter === '') {
                $summaryUmuFirstQtyColumnLetter = $qtyColumnLetter;
            }

            $productDescriptionHeader = isset($pivotData['product_description']) ? (string)$pivotData['product_description'] : '';
            $uomHeader = trim((string)(isset($pivotData['uom']) ? $pivotData['uom'] : ''));
            if ($uomHeader !== '') {
                $productDescriptionHeader .= "\n(" . $uomHeader . ')';
            }

            $summaryUmuSheet->setCellValue("{$qtyColumnLetter}{$summaryUmuPivotCodeRow}", isset($pivotData['product_code']) ? (string)$pivotData['product_code'] : '');
            $summaryUmuSheet->setCellValue("{$qtyColumnLetter}{$summaryUmuPivotDescriptionRow}", $productDescriptionHeader);
            $summaryUmuSheet->getStyle("{$qtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$qtyColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            $summaryUmuSheet->getStyle("{$qtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$qtyColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryUmuSheet->getStyle("{$qtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$qtyColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->applyFromArray($borderStyle);
            $summaryUmuSheet->getColumnDimension($qtyColumnLetter)->setAutoSize(false)->setWidth(14);

            $summaryUmuProductColumnsByCode[$productCodeKey] = [
                'qty_column' => $qtyColumnLetter
            ];

            $summaryUmuCurrentTypeLastProductColumnIndex = $qtyColumnIndex;
            $summaryUmuCurrentProductColumnIndex++;
        }

        if ($summaryUmuCurrentTypeKey !== '' && $summaryUmuCurrentTypeStartColumnIndex !== null && $summaryUmuCurrentTypeLastProductColumnIndex !== null) {
            $typeSubtotalColumnIndex = $summaryUmuCurrentProductColumnIndex++;
            $typeSubtotalColumnLetter = columnIndexToLetter($typeSubtotalColumnIndex);
            $summaryUmuSheet->mergeCells("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}");
            $summaryUmuSheet->setCellValue("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}", 'SUB-TOTAL');
            $summaryUmuSheet->getStyle("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            $summaryUmuSheet->getStyle("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryUmuSheet->getStyle("{$typeSubtotalColumnLetter}{$summaryUmuPivotCodeRow}:{$typeSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->applyFromArray($borderStyle);
            $summaryUmuSheet->getColumnDimension($typeSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

            $summaryUmuProductTypeGroups[] = [
                'label' => $summaryUmuCurrentTypeLabel,
                'start_column_index' => $summaryUmuCurrentTypeStartColumnIndex,
                'end_column_index' => $typeSubtotalColumnIndex
            ];
            $summaryUmuProductTypeSubtotalColumns[] = [
                'column_letter' => $typeSubtotalColumnLetter,
                'start_column_letter' => columnIndexToLetter($summaryUmuCurrentTypeStartColumnIndex),
                'end_column_letter' => columnIndexToLetter($summaryUmuCurrentTypeLastProductColumnIndex)
            ];
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
            $summaryUmuPivotFirstColumnLetter = columnIndexToLetter($summaryUmuPivotFirstColumnIndex !== null ? $summaryUmuPivotFirstColumnIndex : $summaryUmuPivotStartColumnIndex);
            $summaryUmuPivotLastColumnLetter = columnIndexToLetter($summaryUmuPivotLastColumnIndex);
            $summaryUmuProductSubtotalColumnIndex = $summaryUmuCurrentProductColumnIndex++;
            $summaryUmuProductSubtotalColumnLetter = columnIndexToLetter($summaryUmuProductSubtotalColumnIndex);

            $summaryUmuSheet->mergeCells("{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}");
            $summaryUmuSheet->setCellValue("{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}", 'TOTAL');
            $summaryUmuSheet->getStyle("{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            $summaryUmuSheet->getStyle("{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryUmuSheet->getStyle("{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->applyFromArray($borderStyle);
            $summaryUmuSheet->getColumnDimension($summaryUmuProductSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

            if ($summaryUmuPivotFirstColumnLetter !== $summaryUmuProductSubtotalColumnLetter) {
                $summaryUmuSheet->mergeCells("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotTitleRow}");
            }
            $summaryUmuSheet->setCellValue("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}", 'PRODUCT');
            $summaryUmuSheet->getStyle("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotTitleRow}")
                ->getFont()
                ->setBold(true);
            $summaryUmuSheet->getStyle("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotTitleRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);

            $summaryUmuFinalLastColumnLetter = $summaryUmuProductSubtotalColumnLetter;
            $summaryUmuOtherColumnsByCode = [];
            $summaryUmuOthersSubtotalColumnLetter = '';
            $summaryUmuGrandTotalColumnLetter = '';
            $summaryUmuOtherFirstColumnLetter = '';
            $summaryUmuOtherLastColumnLetter = '';
            if (!empty($otherPivotRowsUmu)) {
                $summaryUmuOtherStartColumnIndex = $summaryUmuProductSubtotalColumnIndex + 1;
                $summaryUmuOtherLastColumnIndex = $summaryUmuOtherStartColumnIndex - 1;

                foreach ($otherPivotRowsUmu as $otherIndex => $otherData) {
                    $otherQtyColumnIndex = $summaryUmuOtherStartColumnIndex + $otherIndex;
                    $otherQtyColumnLetter = columnIndexToLetter($otherQtyColumnIndex);
                    $summaryUmuOtherLastColumnIndex = $otherQtyColumnIndex;
                    if ($summaryUmuOtherFirstColumnLetter === '') {
                        $summaryUmuOtherFirstColumnLetter = $otherQtyColumnLetter;
                    }

                    $otherDescriptionHeader = isset($otherData['product_description']) ? (string)$otherData['product_description'] : '';
                    $otherUomHeader = trim((string)(isset($otherData['uom']) ? $otherData['uom'] : ''));
                    if ($otherUomHeader !== '') {
                        $otherDescriptionHeader .= "\n(" . $otherUomHeader . ')';
                    }

                    $summaryUmuSheet->setCellValue("{$otherQtyColumnLetter}{$summaryUmuPivotCodeRow}", isset($otherData['product_code']) ? (string)$otherData['product_code'] : '');
                    $summaryUmuSheet->setCellValue("{$otherQtyColumnLetter}{$summaryUmuPivotDescriptionRow}", $otherDescriptionHeader);
                    $summaryUmuSheet->getStyle("{$otherQtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$otherQtyColumnLetter}{$summaryUmuPivotDescriptionRow}")
                        ->getFont()
                        ->setBold(true)
                        ->getColor()
                        ->setRGB('000000');
                    $summaryUmuSheet->getStyle("{$otherQtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$otherQtyColumnLetter}{$summaryUmuPivotDescriptionRow}")
                        ->getAlignment()
                        ->setWrapText(true)
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $summaryUmuSheet->getStyle("{$otherQtyColumnLetter}{$summaryUmuPivotHeaderRow}:{$otherQtyColumnLetter}{$summaryUmuPivotDescriptionRow}")
                        ->applyFromArray($borderStyle);
                    $summaryUmuSheet->getColumnDimension($otherQtyColumnLetter)->setAutoSize(false)->setWidth(14);

                    $otherProductCodeKey = strtoupper(trim((string)(isset($otherData['product_code']) ? $otherData['product_code'] : '')));
                    if ($otherProductCodeKey !== '') {
                        $summaryUmuOtherColumnsByCode[$otherProductCodeKey] = [
                            'qty_column' => $otherQtyColumnLetter
                        ];
                    }
                }

                if ($summaryUmuOtherFirstColumnLetter !== '') {
                    $summaryUmuOtherLastColumnLetter = columnIndexToLetter($summaryUmuOtherLastColumnIndex);
                    $summaryUmuOthersSubtotalColumnIndex = $summaryUmuOtherLastColumnIndex + 1;
                    $summaryUmuOthersSubtotalColumnLetter = columnIndexToLetter($summaryUmuOthersSubtotalColumnIndex);
                    $summaryUmuSheet->mergeCells("{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}");
                    $summaryUmuSheet->setCellValue("{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}", 'SUBTOTAL');
                    $summaryUmuSheet->getStyle("{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                        ->getFont()
                        ->setBold(true)
                        ->getColor()
                        ->setRGB('000000');
                    $summaryUmuSheet->getStyle("{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                        ->getAlignment()
                        ->setWrapText(true)
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $summaryUmuSheet->getStyle("{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotHeaderRow}:{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                        ->applyFromArray($borderStyle);
                    $summaryUmuSheet->getColumnDimension($summaryUmuOthersSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

                    if ($summaryUmuOtherFirstColumnLetter !== $summaryUmuOthersSubtotalColumnLetter) {
                        $summaryUmuSheet->mergeCells("{$summaryUmuOtherFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotTitleRow}");
                    }
                    $summaryUmuSheet->setCellValue("{$summaryUmuOtherFirstColumnLetter}{$summaryUmuPivotTitleRow}", 'OTHERS PRODUCT');
                    $summaryUmuSheet->getStyle("{$summaryUmuOtherFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotTitleRow}")
                        ->getFont()
                        ->setBold(true);
                    $summaryUmuSheet->getStyle("{$summaryUmuOtherFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotTitleRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                        ->setVertical(Alignment::VERTICAL_CENTER);
                    $summaryUmuFinalLastColumnLetter = $summaryUmuOthersSubtotalColumnLetter;
                }
            }

            $summaryUmuGrandTotalColumnIndex = (
                $summaryUmuOthersSubtotalColumnLetter !== ''
                    ? $summaryUmuOthersSubtotalColumnIndex
                    : $summaryUmuProductSubtotalColumnIndex
            ) + 1;
            $summaryUmuGrandTotalColumnLetter = columnIndexToLetter($summaryUmuGrandTotalColumnIndex);
            $summaryUmuSheet->mergeCells("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotDescriptionRow}");
            $summaryUmuSheet->setCellValue("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotTitleRow}", 'GRAND TOTAL');
            $summaryUmuSheet->getStyle("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            $summaryUmuSheet->getStyle("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryUmuSheet->getStyle("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->applyFromArray($borderStyle);
            $summaryUmuSheet->getColumnDimension($summaryUmuGrandTotalColumnLetter)->setAutoSize(false)->setWidth(14);
            $summaryUmuFinalLastColumnLetter = $summaryUmuGrandTotalColumnLetter;

            $summaryUmuCashColumnIndex = $summaryUmuGrandTotalColumnIndex + 1;
            $summaryUmuCashColumnLetter = columnIndexToLetter($summaryUmuCashColumnIndex);
            $summaryUmuOnAccountColumnIndex = $summaryUmuCashColumnIndex + 1;
            $summaryUmuOnAccountColumnLetter = columnIndexToLetter($summaryUmuOnAccountColumnIndex);

            $summaryUmuSheet->mergeCells("{$summaryUmuCashColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuCashColumnLetter}{$summaryUmuPivotDescriptionRow}");
            $summaryUmuSheet->setCellValue("{$summaryUmuCashColumnLetter}{$summaryUmuPivotTitleRow}", 'CASH');
            $summaryUmuSheet->getStyle("{$summaryUmuCashColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuCashColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            $summaryUmuSheet->getStyle("{$summaryUmuCashColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuCashColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryUmuSheet->getStyle("{$summaryUmuCashColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuCashColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->applyFromArray($borderStyle);
            $summaryUmuSheet->getColumnDimension($summaryUmuCashColumnLetter)->setAutoSize(false)->setWidth(14);

            $summaryUmuSheet->mergeCells("{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotDescriptionRow}");
            $summaryUmuSheet->setCellValue("{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotTitleRow}", 'ON ACCOUNT');
            $summaryUmuSheet->getStyle("{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getFont()
                ->setBold(true)
                ->getColor()
                ->setRGB('000000');
            $summaryUmuSheet->getStyle("{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summaryUmuSheet->getStyle("{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->applyFromArray($borderStyle);
            $summaryUmuSheet->getColumnDimension($summaryUmuOnAccountColumnLetter)->setAutoSize(false)->setWidth(14);
            $summaryUmuFinalLastColumnLetter = $summaryUmuOnAccountColumnLetter;

            $summaryUmuPivotRowPointer = $summaryUmuDataRow;
            foreach ($summaryByDateUmu as $saleDate => $totals) {
                foreach ($summaryUmuProductColumnsByCode as $productCodeKey => $columnMeta) {
                    if (!isset($productPivotByDateUmu[$saleDate][$productCodeKey])) {
                        continue;
                    }
                    $pivotDateValue = $productPivotByDateUmu[$saleDate][$productCodeKey];
                    $qtyValue = isset($pivotDateValue['qty']) ? (float)$pivotDateValue['qty'] : 0.0;
                    if ($qtyValue != 0.0) {
                        $summaryUmuSheet->setCellValue($columnMeta['qty_column'] . $summaryUmuPivotRowPointer, $qtyValue);
                    }
                }
                foreach ($summaryUmuProductTypeSubtotalColumns as $typeSubtotalColumn) {
                    $summaryUmuSheet->setCellValue(
                        "{$typeSubtotalColumn['column_letter']}{$summaryUmuPivotRowPointer}",
                        "=SUM({$typeSubtotalColumn['start_column_letter']}{$summaryUmuPivotRowPointer}:{$typeSubtotalColumn['end_column_letter']}{$summaryUmuPivotRowPointer})"
                    );
                }
                if (!empty($summaryUmuProductTypeSubtotalColumns)) {
                    $summaryUmuTypeSubtotalRefs = [];
                    foreach ($summaryUmuProductTypeSubtotalColumns as $typeSubtotalColumn) {
                        $summaryUmuTypeSubtotalRefs[] = $typeSubtotalColumn['column_letter'] . $summaryUmuPivotRowPointer;
                    }
                    $summaryUmuSheet->setCellValue(
                        "{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotRowPointer}",
                        '=' . implode('+', $summaryUmuTypeSubtotalRefs)
                    );
                } elseif ($summaryUmuFirstQtyColumnLetter !== '') {
                    $summaryUmuSheet->setCellValue(
                        "{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotRowPointer}",
                        "=SUM({$summaryUmuFirstQtyColumnLetter}{$summaryUmuPivotRowPointer}:{$summaryUmuPivotLastColumnLetter}{$summaryUmuPivotRowPointer})"
                    );
                } else {
                    $summaryUmuSheet->setCellValue("{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotRowPointer}", 0);
                }
                foreach ($summaryUmuOtherColumnsByCode as $otherProductCodeKey => $otherColumnMeta) {
                    if (!isset($otherPivotByDateUmu[$saleDate][$otherProductCodeKey])) {
                        continue;
                    }
                    $otherPivotDateValue = $otherPivotByDateUmu[$saleDate][$otherProductCodeKey];
                    $otherQtyValue = isset($otherPivotDateValue['qty']) ? (float)$otherPivotDateValue['qty'] : 0.0;
                    if ($otherQtyValue != 0.0) {
                        $summaryUmuSheet->setCellValue($otherColumnMeta['qty_column'] . $summaryUmuPivotRowPointer, $otherQtyValue);
                    }
                }
                if ($summaryUmuOthersSubtotalColumnLetter !== '' && $summaryUmuOtherFirstColumnLetter !== '' && $summaryUmuOtherLastColumnLetter !== '') {
                    $summaryUmuSheet->setCellValue(
                        "{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotRowPointer}",
                        "=SUM({$summaryUmuOtherFirstColumnLetter}{$summaryUmuPivotRowPointer}:{$summaryUmuOtherLastColumnLetter}{$summaryUmuPivotRowPointer})"
                    );
                }
                $summaryUmuSheet->setCellValue(
                    "{$summaryUmuGrandTotalColumnLetter}{$summaryUmuPivotRowPointer}",
                    $summaryUmuOthersSubtotalColumnLetter !== ''
                        ? "={$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotRowPointer}+{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuPivotRowPointer}"
                        : "={$summaryUmuProductSubtotalColumnLetter}{$summaryUmuPivotRowPointer}"
                );
                $summaryUmuSheet->setCellValue("{$summaryUmuCashColumnLetter}{$summaryUmuPivotRowPointer}", $totals['cash']);
                $summaryUmuSheet->setCellValue("{$summaryUmuOnAccountColumnLetter}{$summaryUmuPivotRowPointer}", $totals['on_account']);
                $summaryUmuPivotRowPointer++;
            }

            foreach ($summaryUmuProductColumnsByCode as $columnMeta) {
                $qtyColumnLetter = $columnMeta['qty_column'];
                if ($lastSummaryUmuDetailRow >= $summaryUmuDataRow) {
                    $summaryUmuSheet->setCellValue("{$qtyColumnLetter}{$summaryUmuTotalRow}", "=SUM({$qtyColumnLetter}{$summaryUmuDataRow}:{$qtyColumnLetter}{$lastSummaryUmuDetailRow})");
                } else {
                    $summaryUmuSheet->setCellValue("{$qtyColumnLetter}{$summaryUmuTotalRow}", 0);
                }
            }
            foreach ($summaryUmuProductTypeSubtotalColumns as $typeSubtotalColumn) {
                $typeSubtotalColumnLetter = $typeSubtotalColumn['column_letter'];
                if ($lastSummaryUmuDetailRow >= $summaryUmuDataRow) {
                    $summaryUmuSheet->setCellValue(
                        "{$typeSubtotalColumnLetter}{$summaryUmuTotalRow}",
                        "=SUM({$typeSubtotalColumnLetter}{$summaryUmuDataRow}:{$typeSubtotalColumnLetter}{$lastSummaryUmuDetailRow})"
                    );
                } else {
                    $summaryUmuSheet->setCellValue("{$typeSubtotalColumnLetter}{$summaryUmuTotalRow}", 0);
                }
            }
            if ($lastSummaryUmuDetailRow >= $summaryUmuDataRow) {
                $summaryUmuSheet->setCellValue(
                    "{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuTotalRow}",
                    "=SUM({$summaryUmuProductSubtotalColumnLetter}{$summaryUmuDataRow}:{$summaryUmuProductSubtotalColumnLetter}{$lastSummaryUmuDetailRow})"
                );
            } else {
                $summaryUmuSheet->setCellValue("{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuTotalRow}", 0);
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
                if ($lastSummaryUmuDetailRow >= $summaryUmuDataRow) {
                    $summaryUmuSheet->setCellValue("{$otherQtyColumnLetter}{$summaryUmuTotalRow}", "=SUM({$otherQtyColumnLetter}{$summaryUmuDataRow}:{$otherQtyColumnLetter}{$lastSummaryUmuDetailRow})");
                } else {
                    $summaryUmuSheet->setCellValue("{$otherQtyColumnLetter}{$summaryUmuTotalRow}", 0);
                }
            }
            if ($summaryUmuOthersSubtotalColumnLetter !== '') {
                if ($lastSummaryUmuDetailRow >= $summaryUmuDataRow) {
                    $summaryUmuSheet->setCellValue(
                        "{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuTotalRow}",
                        "=SUM({$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuDataRow}:{$summaryUmuOthersSubtotalColumnLetter}{$lastSummaryUmuDetailRow})"
                    );
                } else {
                    $summaryUmuSheet->setCellValue("{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuTotalRow}", 0);
                }
            }
            $summaryUmuSheet->setCellValue("{$summaryUmuCashColumnLetter}{$summaryUmuTotalRow}", $summaryUmuTotals['cash']);
            $summaryUmuSheet->setCellValue("{$summaryUmuOnAccountColumnLetter}{$summaryUmuTotalRow}", $summaryUmuTotals['on_account']);

            $summaryUmuSheet->getStyle("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuFinalLastColumnLetter}{$summaryUmuTotalRow}")
                ->applyFromArray($borderStyle);
            $summaryUmuSheet->getStyle("{$summaryUmuPivotFirstColumnLetter}{$summaryUmuPivotTitleRow}:{$summaryUmuFinalLastColumnLetter}{$summaryUmuPivotDescriptionRow}")
                ->getAlignment()
                ->setWrapText(true)
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
            foreach ($summaryUmuProductTypeSubtotalColumns as $typeSubtotalColumn) {
                $typeSubtotalColumnLetter = $typeSubtotalColumn['column_letter'];
                $summaryUmuSheet->getStyle("{$typeSubtotalColumnLetter}{$summaryUmuDataRow}:{$typeSubtotalColumnLetter}{$summaryUmuTotalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
            $summaryUmuSheet->getStyle("{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuDataRow}:{$summaryUmuProductSubtotalColumnLetter}{$summaryUmuTotalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $summaryUmuSheet->getStyle("{$summaryUmuGrandTotalColumnLetter}{$summaryUmuDataRow}:{$summaryUmuGrandTotalColumnLetter}{$summaryUmuTotalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $summaryUmuSheet->getStyle("{$summaryUmuCashColumnLetter}{$summaryUmuDataRow}:{$summaryUmuCashColumnLetter}{$summaryUmuTotalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $summaryUmuSheet->getStyle("{$summaryUmuOnAccountColumnLetter}{$summaryUmuDataRow}:{$summaryUmuOnAccountColumnLetter}{$summaryUmuTotalRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
            foreach ($summaryUmuOtherColumnsByCode as $otherColumnMeta) {
                $otherQtyColumnLetter = $otherColumnMeta['qty_column'];
                $summaryUmuSheet->getStyle("{$otherQtyColumnLetter}{$summaryUmuDataRow}:{$otherQtyColumnLetter}{$summaryUmuTotalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
            if ($summaryUmuOthersSubtotalColumnLetter !== '') {
                $summaryUmuSheet->getStyle("{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuDataRow}:{$summaryUmuOthersSubtotalColumnLetter}{$summaryUmuTotalRow}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }
        }
    }
    if ($summaryUmuCurrentRow >= $summaryUmuHeaderRow) {
        $summaryUmuSheet->getStyle("A{$summaryUmuHeaderRow}:D{$summaryUmuCurrentRow}")->applyFromArray($borderStyle);
    }

    foreach (range('A', 'D') as $column) {
        if ($column === 'A') {
            $summaryUmuSheet->getColumnDimension($column)->setAutoSize(false)->setWidth(5);
            continue;
        }
        if ($column === 'B') {
            $summaryUmuSheet->getColumnDimension($column)->setAutoSize(false)->setWidth(15);
            continue;
        }
        if ($column === 'D') {
            $summaryUmuSheet->getColumnDimension($column)->setAutoSize(false)->setWidth(20);
            continue;
        }
        $summaryUmuSheet->getColumnDimension($column)->setAutoSize(true);
    }

    $cashSummarySheet = $spreadsheet->createSheet();
    writePaymentSummarySheet(
        $cashSummarySheet,
        'Cash Summary',
        $rangeLabel,
        $generatedAt,
        $summaryByDate,
        'cash',
        $productPivotRows,
        $cashProductPivotByDate,
        $otherPivotRows,
        $cashOtherPivotByDate,
        $headerStyle,
        $borderStyle,
        $currencyFormat
    );

    $onAccountSummarySheet = $spreadsheet->createSheet();
    writePaymentSummarySheet(
        $onAccountSummarySheet,
        'On Account Summary',
        $rangeLabel,
        $generatedAt,
        $summaryByDate,
        'on_account',
        $productPivotRows,
        $onAccountProductPivotByDate,
        $otherPivotRows,
        $onAccountOtherPivotByDate,
        $headerStyle,
        $borderStyle,
        $currencyFormat
    );
}

if ($reportType === 'by_product') {
    foreach (['G', 'H', 'I'] as $hiddenColumn) {
        $summarySheet->getColumnDimension($hiddenColumn)->setVisible(false);
    }
    $salesSummarySheet = $spreadsheet->getSheetByName('Sales Summary');
    if ($salesSummarySheet !== null) {
        $spreadsheet->removeSheetByIndex($spreadsheet->getIndex($salesSummarySheet));
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
$filename = 'todate_daily_sales_amount_summary_' . $startDate . '_to_' . $endDate . $filenameSuffix . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;


