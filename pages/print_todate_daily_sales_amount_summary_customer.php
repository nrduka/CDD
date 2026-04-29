<?php
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}

function memoryLimitToBytesForExport($memoryLimit)
{
    $memoryLimit = trim((string)$memoryLimit);
    if ($memoryLimit === '' || $memoryLimit === '-1') {
        return -1;
    }

    $unit = strtolower(substr($memoryLimit, -1));
    $bytes = (int)$memoryLimit;
    switch ($unit) {
        case 'g':
            $bytes *= 1024;
            // no break
        case 'm':
            $bytes *= 1024;
            // no break
        case 'k':
            $bytes *= 1024;
            break;
    }

    return $bytes;
}

if (function_exists('ini_get') && function_exists('ini_set')) {
    $currentMemoryLimit = memoryLimitToBytesForExport(ini_get('memory_limit'));
    if ($currentMemoryLimit > 0 && $currentMemoryLimit < 536870912) {
        @ini_set('memory_limit', '512M');
    }
}

if (function_exists('set_time_limit')) {
    @set_time_limit(300);
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

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    $connectError = trim((string)mysqli_connect_error());
    echo $connectError !== '' ? 'Database connection unavailable. ' . $connectError : 'Database connection unavailable.';
    exit;
}

function resolveSalesDateTime($primaryValue, $fallbackValue)
{
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

function columnLetterToIndex($columnLetter)
{
    $letters = strtoupper(trim((string)$columnLetter));
    if ($letters === '') {
        return 0;
    }

    $index = 0;
    $length = strlen($letters);
    for ($i = 0; $i < $length; $i++) {
        $charCode = ord($letters[$i]);
        if ($charCode < 65 || $charCode > 90) {
            return 0;
        }
        $index = ($index * 26) + ($charCode - 64);
    }

    return $index;
}

function normalizeMergeRange($range)
{
    $range = strtoupper(trim((string)$range));
    if ($range === '' || strpos($range, ':') === false) {
        return null;
    }

    $parts = explode(':', $range, 2);
    if (count($parts) !== 2) {
        return null;
    }

    if (!preg_match('/^([A-Z]+)(\d+)$/', $parts[0], $startMatch)) {
        return null;
    }
    if (!preg_match('/^([A-Z]+)(\d+)$/', $parts[1], $endMatch)) {
        return null;
    }

    $startCol = columnLetterToIndex($startMatch[1]);
    $endCol = columnLetterToIndex($endMatch[1]);
    $startRow = (int)$startMatch[2];
    $endRow = (int)$endMatch[2];
    if ($startCol <= 0 || $endCol <= 0 || $startRow <= 0 || $endRow <= 0) {
        return null;
    }

    $left = min($startCol, $endCol);
    $right = max($startCol, $endCol);
    $top = min($startRow, $endRow);
    $bottom = max($startRow, $endRow);

    return [
        'left' => $left,
        'right' => $right,
        'top' => $top,
        'bottom' => $bottom,
        'range' => columnIndexToLetter($left) . $top . ':' . columnIndexToLetter($right) . $bottom
    ];
}

function mergeRangesOverlap($leftRange, $rightRange)
{
    return !(
        $leftRange['right'] < $rightRange['left']
        || $rightRange['right'] < $leftRange['left']
        || $leftRange['bottom'] < $rightRange['top']
        || $rightRange['bottom'] < $leftRange['top']
    );
}

function safeMergeCells($sheet, $range, &$mergeRegistry)
{
    $normalizedRange = normalizeMergeRange($range);
    if ($normalizedRange === null) {
        return false;
    }

    foreach ($mergeRegistry as $existingRange) {
        if (mergeRangesOverlap($existingRange, $normalizedRange)) {
            return false;
        }
    }

    $sheet->mergeCells($normalizedRange['range']);
    $mergeRegistry[] = $normalizedRange;

    return true;
}

function formatRangeLabel($startDate, $endDate)
{
    $startLabel = date('F d, Y', strtotime($startDate));
    $endLabel = date('F d, Y', strtotime($endDate));

    return $startLabel === $endLabel ? $startLabel : $startLabel . ' - ' . $endLabel;
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
                   s.amount,
                   s.amt_received,
                   s.amt_change,
                   s.product_code,
                   s.product_description,
                   s.date_created,
                   s.date_time_ordered,
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
            ORDER BY c.storeName, c.firstName, c.lastName,
                     COALESCE(NULLIF(s.date_time_ordered, '0000-00-00 00:00:00'), s.date_created),
                     s.order_no, s.lineitem";

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

                $rows[] = [
                    'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                    'customer_code' => isset($row['customer_code']) ? (string)$row['customer_code'] : '',
                    'customer_name' => buildCustomerDisplayName($row),
                    'date' => $dateLabel,
                    'qty' => isset($row['qty']) ? (float)$row['qty'] : 0.0,
                    'amount' => isset($row['amount']) ? (float)$row['amount'] : 0.0,
                    'amt_received' => isset($row['amt_received']) ? (float)$row['amt_received'] : 0.0,
                    'amt_change' => isset($row['amt_change']) ? (float)$row['amt_change'] : 0.0,
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => $productDescription,
                    'product_type' => $productType,
                    'uom' => trim((string)(isset($row['uom']) ? $row['uom'] : '')),
                    'umu' => trim((string)(isset($row['umu']) ? $row['umu'] : '')),
                    'umu_per' => isset($row['umu_per']) ? (float)$row['umu_per'] : 0.0,
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

function convertRegisterRowsToUmu($registerRows)
{
    // Amount report: keep values as-is (no UMU conversion needed).
    return $registerRows;
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

function resolvePivotSection($row)
{
    $productCode = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
    if ($productCode === '') {
        return [
            'section' => '',
            'product_key' => ''
        ];
    }

    $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
    if ($productType === '') {
        $productType = resolveProductTypeGroup(
            isset($row['product_description']) ? $row['product_description'] : '',
            isset($row['product_code']) ? $row['product_code'] : ''
        );
    }

    return [
        'section' => strtoupper($productType) === 'OTHERS' ? 'others' : 'ice',
        'product_key' => $productCode,
    ];
}

function buildPivotColumns($registerRows, $section)
{
    $columns = [];

    foreach ($registerRows as $row) {
        $pivotMeta = resolvePivotSection($row);
        if ($pivotMeta['section'] !== $section || $pivotMeta['product_key'] === '') {
            continue;
        }

        $productKey = $pivotMeta['product_key'];
        $productCode = trim((string)(isset($row['product_code']) ? $row['product_code'] : ''));
        $productDescription = trim((string)(isset($row['product_description']) ? $row['product_description'] : ''));
        $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
        $uom = trim((string)(isset($row['uom']) ? $row['uom'] : ''));

        if (!isset($columns[$productKey])) {
            $columns[$productKey] = [
                'product_key' => $productKey,
                'product_code' => $productCode,
                'product_description' => $productDescription,
                'product_type' => $productType,
                'uom' => $uom,
            ];
        }

        if ($columns[$productKey]['product_description'] === '' && $productDescription !== '') {
            $columns[$productKey]['product_description'] = $productDescription;
        }
        if ($columns[$productKey]['product_type'] === '' && $productType !== '') {
            $columns[$productKey]['product_type'] = $productType;
        }
        if ($columns[$productKey]['uom'] === '' && $uom !== '') {
            $columns[$productKey]['uom'] = $uom;
        }
    }

    $pivotRows = array_values($columns);

    usort($pivotRows, function ($left, $right) use ($section) {
        if ($section === 'ice') {
            $leftType = strtoupper(trim((string)$left['product_type']));
            $rightType = strtoupper(trim((string)$right['product_type']));
            if ($leftType !== $rightType) {
                return strcmp($leftType, $rightType);
            }
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

function mergePivotColumnsForUmu($basePivotRows, $umuPivotRows)
{
    $umuByCode = [];
    foreach ($umuPivotRows as $row) {
        $codeKey = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
        if ($codeKey === '') {
            continue;
        }
        $umuByCode[$codeKey] = $row;
    }

    $merged = [];
    foreach ($basePivotRows as $row) {
        $codeKey = strtoupper(trim((string)(isset($row['product_code']) ? $row['product_code'] : '')));
        if ($codeKey !== '' && isset($umuByCode[$codeKey])) {
            $umuUnit = trim((string)(isset($umuByCode[$codeKey]['uom']) ? $umuByCode[$codeKey]['uom'] : ''));
            if ($umuUnit !== '') {
                $row['uom'] = $umuUnit;
            }
        }
        $merged[] = $row;
    }

    return $merged;
}

function buildCustomerDateSummary($registerRows, $metricKey = 'qty')
{
    $customerBuckets = [];

    foreach ($registerRows as $index => $row) {
        $customerCode = trim((string)(isset($row['customer_code']) ? $row['customer_code'] : ''));
        if ($customerCode === '') {
            $customerCode = 'UNASSIGNED';
        }

        $customerName = trim((string)(isset($row['customer_name']) ? $row['customer_name'] : ''));
        if ($customerName === '' && $customerCode !== 'UNASSIGNED') {
            $customerName = $customerCode;
        }

        $saleDate = trim((string)(isset($row['date']) ? $row['date'] : ''));
        if ($saleDate === '') {
            $saleDate = 'NO DATE';
        }

        $orderNo = trim((string)(isset($row['order_no']) ? $row['order_no'] : ''));
        $orderKey = $orderNo !== '' ? $orderNo : '__ROW_' . $index;

        $qty = isset($row[$metricKey]) ? (float)$row[$metricKey] : 0.0;
        $pivotMeta = resolvePivotSection($row);
        $section = $pivotMeta['section'];
        $productKey = $pivotMeta['product_key'];

        if (!isset($customerBuckets[$customerCode])) {
            $customerBuckets[$customerCode] = [
                'customer_code' => $customerCode,
                'customer_name' => $customerName,
                'subtotal_qty' => 0.0,
                'subtotal_transactions' => 0,
                'subtotal_products' => ['ice' => [], 'others' => []],
                'transaction_orders' => [],
                'dates' => []
            ];
        }

        if ($customerBuckets[$customerCode]['customer_name'] === '' && $customerName !== '') {
            $customerBuckets[$customerCode]['customer_name'] = $customerName;
        }

        $customerBuckets[$customerCode]['transaction_orders'][$orderKey] = true;
        $customerBuckets[$customerCode]['subtotal_qty'] += $qty;

        if ($section !== '' && $productKey !== '') {
            if (!isset($customerBuckets[$customerCode]['subtotal_products'][$section][$productKey])) {
                $customerBuckets[$customerCode]['subtotal_products'][$section][$productKey] = 0.0;
            }
            $customerBuckets[$customerCode]['subtotal_products'][$section][$productKey] += $qty;
        }

        if (!isset($customerBuckets[$customerCode]['dates'][$saleDate])) {
            $customerBuckets[$customerCode]['dates'][$saleDate] = [
                'sale_date' => $saleDate,
                'transaction_count' => 0,
                'qty' => 0.0,
                'products' => ['ice' => [], 'others' => []],
                'transaction_orders' => []
            ];
        }

        $customerBuckets[$customerCode]['dates'][$saleDate]['transaction_orders'][$orderKey] = true;
        $customerBuckets[$customerCode]['dates'][$saleDate]['qty'] += $qty;

        if ($section !== '' && $productKey !== '') {
            if (!isset($customerBuckets[$customerCode]['dates'][$saleDate]['products'][$section][$productKey])) {
                $customerBuckets[$customerCode]['dates'][$saleDate]['products'][$section][$productKey] = 0.0;
            }
            $customerBuckets[$customerCode]['dates'][$saleDate]['products'][$section][$productKey] += $qty;
        }
    }

    $customerRows = array_values($customerBuckets);

    foreach ($customerRows as &$customerRow) {
        $customerRow['subtotal_transactions'] = count($customerRow['transaction_orders']);
        unset($customerRow['transaction_orders']);

        $dateRows = array_values($customerRow['dates']);
        foreach ($dateRows as &$dateRow) {
            $dateRow['transaction_count'] = count($dateRow['transaction_orders']);
            unset($dateRow['transaction_orders']);
        }
        unset($dateRow);

        usort($dateRows, function ($left, $right) {
            $leftDate = isset($left['sale_date']) ? (string)$left['sale_date'] : '';
            $rightDate = isset($right['sale_date']) ? (string)$right['sale_date'] : '';

            if ($leftDate === 'NO DATE' && $rightDate !== 'NO DATE') {
                return 1;
            }
            if ($leftDate !== 'NO DATE' && $rightDate === 'NO DATE') {
                return -1;
            }

            return strcmp($leftDate, $rightDate);
        });

        $customerRow['dates'] = $dateRows;
    }
    unset($customerRow);

    usort($customerRows, function ($left, $right) {
        $codeCompare = strcasecmp((string)$left['customer_code'], (string)$right['customer_code']);
        if ($codeCompare !== 0) {
            return $codeCompare;
        }

        return strcasecmp((string)$left['customer_name'], (string)$right['customer_name']);
    });

    return $customerRows;
}

function buildCustomerDateValueMap($customerSummaryRows)
{
    $valueMap = [];

    foreach ($customerSummaryRows as $customerRow) {
        $customerCode = trim((string)(isset($customerRow['customer_code']) ? $customerRow['customer_code'] : ''));
        if ($customerCode === '') {
            continue;
        }

        if (!isset($valueMap[$customerCode])) {
            $valueMap[$customerCode] = [];
        }

        foreach ((array)(isset($customerRow['dates']) ? $customerRow['dates'] : []) as $dateRow) {
            $saleDate = trim((string)(isset($dateRow['sale_date']) ? $dateRow['sale_date'] : ''));
            if ($saleDate === '') {
                continue;
            }

            $valueMap[$customerCode][$saleDate] = isset($dateRow['qty']) ? (float)$dateRow['qty'] : 0.0;
        }
    }

    return $valueMap;
}

function writeCustomerSummarySheet(
    $sheet,
    $sheetTitle,
    $rangeLabel,
    $generatedAt,
    $customerSummaryRows,
    $icePivotRows,
    $otherPivotRows,
    $headerStyle,
    $borderStyle,
    $valueHeader,
    $cashByCustomerDate = [],
    $onAccountByCustomerDate = [],
    $showPaymentColumns = false
) {
    $sheet->setCellValue('A1', $sheetTitle);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
    $sheet->setCellValue('A3', 'Generated: ' . $generatedAt);

    $sectionRow = 6;
    $productTypeRow = 7;
    $headerRow = 8;
    $dataStartRow = 9;
    $mergeRegistry = [];

    $baseHeaders = [
        'NO.',
        'CUSTOMER CODE',
        'CUSTOMER NAME',
        'DATE',
        'TRANSACTION COUNT',
        $valueHeader
    ];

    $sheet->fromArray($baseHeaders, null, 'A' . $headerRow);
    $sheet->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$headerRow}:F{$headerRow}")
        ->getFont()
        ->getColor()
        ->setRGB('000000');
    $sheet->getStyle("A{$headerRow}:F{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(6);
    $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(18);
    $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(32);
    $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(14);
    $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(14);
    $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(14);

    $currentColumnIndex = 7; // G
    $productColumnsBySection = ['ice' => [], 'others' => []];
    $iceTypeSubtotalColumns = [];
    $iceTypeGroups = [];
    $iceFirstColumnIndex = null;
    $iceLastProductColumnIndex = null;

    $currentIceTypeKey = '';
    $currentIceTypeLabel = '';
    $currentIceTypeStartColumnIndex = null;
    $currentIceTypeLastProductColumnIndex = null;

    foreach ($icePivotRows as $pivotData) {
        $productCode = trim((string)(isset($pivotData['product_code']) ? $pivotData['product_code'] : ''));
        if ($productCode === '') {
            continue;
        }

        $productType = trim((string)(isset($pivotData['product_type']) ? $pivotData['product_type'] : ''));
        if ($productType === '') {
            $productType = 'UNCLASSIFIED';
        }
        $typeKey = strtoupper($productType);

        if ($currentIceTypeKey !== '' && $typeKey !== $currentIceTypeKey && $currentIceTypeStartColumnIndex !== null && $currentIceTypeLastProductColumnIndex !== null) {
            $typeSubtotalColumnIndex = $currentColumnIndex++;
            $typeSubtotalColumnLetter = columnIndexToLetter($typeSubtotalColumnIndex);
            $sheet->setCellValue("{$typeSubtotalColumnLetter}{$headerRow}", 'SUB-TOTAL');
            $sheet->getStyle("{$typeSubtotalColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
            $sheet->getStyle("{$typeSubtotalColumnLetter}{$headerRow}")
                ->getAlignment()
                ->setWrapText(true)
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getColumnDimension($typeSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

            $iceTypeSubtotalColumns[] = [
                'column' => $typeSubtotalColumnLetter,
                'start_column' => columnIndexToLetter($currentIceTypeStartColumnIndex),
                'end_column' => columnIndexToLetter($currentIceTypeLastProductColumnIndex)
            ];
            $iceTypeGroups[] = [
                'label' => $currentIceTypeLabel,
                'start_column_index' => $currentIceTypeStartColumnIndex,
                'end_column_index' => $typeSubtotalColumnIndex
            ];
            $currentIceTypeStartColumnIndex = null;
            $currentIceTypeLastProductColumnIndex = null;
        }

        if ($currentIceTypeKey === '' || $currentIceTypeStartColumnIndex === null || $typeKey !== $currentIceTypeKey) {
            $currentIceTypeKey = $typeKey;
            $currentIceTypeLabel = $productType;
            $currentIceTypeStartColumnIndex = $currentColumnIndex;
        }

        $columnLetter = columnIndexToLetter($currentColumnIndex);
        if ($iceFirstColumnIndex === null) {
            $iceFirstColumnIndex = $currentColumnIndex;
        }
        $iceLastProductColumnIndex = $currentColumnIndex;
        $currentIceTypeLastProductColumnIndex = $currentColumnIndex;

        $productDescription = trim((string)(isset($pivotData['product_description']) ? $pivotData['product_description'] : ''));
        $uom = trim((string)(isset($pivotData['uom']) ? $pivotData['uom'] : ''));
        $label = $productDescription !== '' ? $productDescription : $productCode;
        if ($uom !== '') {
            $label .= "\n(" . $uom . ')';
        }

        $sheet->setCellValue($columnLetter . $headerRow, $label);
        $sheet->getStyle("{$columnLetter}{$productTypeRow}:{$columnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$columnLetter}{$productTypeRow}:{$columnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(false)->setWidth(14);

        $productColumnsBySection['ice'][strtoupper($productCode)] = ['column' => $columnLetter];
        $currentColumnIndex++;
    }

    if ($currentIceTypeKey !== '' && $currentIceTypeStartColumnIndex !== null && $currentIceTypeLastProductColumnIndex !== null) {
        $typeSubtotalColumnIndex = $currentColumnIndex++;
        $typeSubtotalColumnLetter = columnIndexToLetter($typeSubtotalColumnIndex);
        $sheet->setCellValue("{$typeSubtotalColumnLetter}{$headerRow}", 'SUB-TOTAL');
        $sheet->getStyle("{$typeSubtotalColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$typeSubtotalColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($typeSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

        $iceTypeSubtotalColumns[] = [
            'column' => $typeSubtotalColumnLetter,
            'start_column' => columnIndexToLetter($currentIceTypeStartColumnIndex),
            'end_column' => columnIndexToLetter($currentIceTypeLastProductColumnIndex)
        ];
        $iceTypeGroups[] = [
            'label' => $currentIceTypeLabel,
            'start_column_index' => $currentIceTypeStartColumnIndex,
            'end_column_index' => $typeSubtotalColumnIndex
        ];
    }

    $iceTotalColumnLetter = '';
    if ($iceFirstColumnIndex !== null) {
        foreach ($iceTypeGroups as $typeGroup) {
            $typeStart = columnIndexToLetter($typeGroup['start_column_index']);
            $typeEnd = columnIndexToLetter($typeGroup['end_column_index']);
            if ($typeStart !== $typeEnd) {
                safeMergeCells($sheet, "{$typeStart}{$productTypeRow}:{$typeEnd}{$productTypeRow}", $mergeRegistry);
            }
            $sheet->setCellValue("{$typeStart}{$productTypeRow}", $typeGroup['label']);
            $sheet->getStyle("{$typeStart}{$productTypeRow}:{$typeEnd}{$productTypeRow}")->applyFromArray($headerStyle);
        }

        $iceTotalColumnLetter = columnIndexToLetter($currentColumnIndex++);
        safeMergeCells($sheet, "{$iceTotalColumnLetter}{$productTypeRow}:{$iceTotalColumnLetter}{$headerRow}", $mergeRegistry);
        $sheet->setCellValue("{$iceTotalColumnLetter}{$productTypeRow}", 'TOTAL');
        $sheet->getStyle("{$iceTotalColumnLetter}{$productTypeRow}:{$iceTotalColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$iceTotalColumnLetter}{$productTypeRow}:{$iceTotalColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($iceTotalColumnLetter)->setAutoSize(false)->setWidth(14);

        $iceSectionEndColumnIndex = $currentColumnIndex - 1;
        $iceSectionStartLetter = columnIndexToLetter($iceFirstColumnIndex);
        $iceSectionEndLetter = columnIndexToLetter($iceSectionEndColumnIndex);
        if ($iceSectionStartLetter !== $iceSectionEndLetter) {
            safeMergeCells($sheet, "{$iceSectionStartLetter}{$sectionRow}:{$iceSectionEndLetter}{$sectionRow}", $mergeRegistry);
        }
        $sheet->setCellValue("{$iceSectionStartLetter}{$sectionRow}", 'ICE PRODUCTS');
        $sheet->getStyle("{$iceSectionStartLetter}{$sectionRow}:{$iceSectionEndLetter}{$sectionRow}")->applyFromArray($headerStyle);
    }

    $othersFirstColumnIndex = null;
    foreach ($otherPivotRows as $otherData) {
        $productCode = trim((string)(isset($otherData['product_code']) ? $otherData['product_code'] : ''));
        if ($productCode === '') {
            continue;
        }

        $columnLetter = columnIndexToLetter($currentColumnIndex);
        if ($othersFirstColumnIndex === null) {
            $othersFirstColumnIndex = $currentColumnIndex;
        }
        $othersLastProductColumnIndex = $currentColumnIndex;

        $otherDescription = trim((string)(isset($otherData['product_description']) ? $otherData['product_description'] : ''));
        $otherUom = trim((string)(isset($otherData['uom']) ? $otherData['uom'] : ''));
        $otherLabel = $otherDescription !== '' ? $otherDescription : $productCode;
        if ($otherUom !== '') {
            $otherLabel .= "\n(" . $otherUom . ')';
        }

        $sheet->setCellValue($columnLetter . $headerRow, $otherLabel);
        $sheet->getStyle("{$columnLetter}{$productTypeRow}:{$columnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$columnLetter}{$productTypeRow}:{$columnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($columnLetter)->setAutoSize(false)->setWidth(14);

        $productColumnsBySection['others'][strtoupper($productCode)] = ['column' => $columnLetter];
        $currentColumnIndex++;
    }

    $othersSubtotalColumnLetter = '';
    if ($othersFirstColumnIndex !== null) {
        $othersSubtotalColumnLetter = columnIndexToLetter($currentColumnIndex++);
        $sheet->setCellValue("{$othersSubtotalColumnLetter}{$headerRow}", 'SUB-TOTAL');
        $sheet->getStyle("{$othersSubtotalColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$othersSubtotalColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($othersSubtotalColumnLetter)->setAutoSize(false)->setWidth(14);

        $othersSectionEndColumnIndex = $currentColumnIndex - 1;
        $othersStartLetter = columnIndexToLetter($othersFirstColumnIndex);
        $othersEndLetter = columnIndexToLetter($othersSectionEndColumnIndex);
        if ($othersStartLetter !== $othersEndLetter) {
            safeMergeCells($sheet, "{$othersStartLetter}{$sectionRow}:{$othersEndLetter}{$sectionRow}", $mergeRegistry);
            safeMergeCells($sheet, "{$othersStartLetter}{$productTypeRow}:{$othersEndLetter}{$productTypeRow}", $mergeRegistry);
        }
        $sheet->setCellValue("{$othersStartLetter}{$sectionRow}", 'OTHERS PRODUCTS');
        $sheet->setCellValue("{$othersStartLetter}{$productTypeRow}", 'OTHERS');
        $sheet->getStyle("{$othersStartLetter}{$sectionRow}:{$othersEndLetter}{$productTypeRow}")->applyFromArray($headerStyle);
    }

    $grandTotalColumnLetter = columnIndexToLetter($currentColumnIndex++);
    safeMergeCells($sheet, "{$grandTotalColumnLetter}{$sectionRow}:{$grandTotalColumnLetter}{$headerRow}", $mergeRegistry);
    $sheet->setCellValue("{$grandTotalColumnLetter}{$sectionRow}", 'GRAND TOTAL');
    $sheet->getStyle("{$grandTotalColumnLetter}{$sectionRow}:{$grandTotalColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("{$grandTotalColumnLetter}{$sectionRow}:{$grandTotalColumnLetter}{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getColumnDimension($grandTotalColumnLetter)->setAutoSize(false)->setWidth(14);

    $cashColumnLetter = '';
    $onAccountColumnLetter = '';
    if ($showPaymentColumns) {
        $cashColumnLetter = columnIndexToLetter($currentColumnIndex++);
        safeMergeCells($sheet, "{$cashColumnLetter}{$sectionRow}:{$cashColumnLetter}{$headerRow}", $mergeRegistry);
        $sheet->setCellValue("{$cashColumnLetter}{$sectionRow}", 'CASH');
        $sheet->getStyle("{$cashColumnLetter}{$sectionRow}:{$cashColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$cashColumnLetter}{$sectionRow}:{$cashColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($cashColumnLetter)->setAutoSize(false)->setWidth(14);

        $onAccountColumnLetter = columnIndexToLetter($currentColumnIndex++);
        safeMergeCells($sheet, "{$onAccountColumnLetter}{$sectionRow}:{$onAccountColumnLetter}{$headerRow}", $mergeRegistry);
        $sheet->setCellValue("{$onAccountColumnLetter}{$sectionRow}", 'ON ACCOUNT');
        $sheet->getStyle("{$onAccountColumnLetter}{$sectionRow}:{$onAccountColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("{$onAccountColumnLetter}{$sectionRow}:{$onAccountColumnLetter}{$headerRow}")
            ->getAlignment()
            ->setWrapText(true)
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getColumnDimension($onAccountColumnLetter)->setAutoSize(false)->setWidth(14);
    }

    $lastColumnIndex = max(6, $currentColumnIndex - 1);
    $lastColumnLetter = columnIndexToLetter($lastColumnIndex);

    $sheet->getStyle("A{$sectionRow}:{$lastColumnLetter}{$headerRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $grandTransactions = 0;
    $grandQty = 0.0;
    $grandCash = 0.0;
    $grandOnAccount = 0.0;
    $grandProductTotals = ['ice' => [], 'others' => []];
    foreach ($productColumnsBySection['ice'] as $productKey => $meta) {
        $grandProductTotals['ice'][$productKey] = 0.0;
    }
    foreach ($productColumnsBySection['others'] as $productKey => $meta) {
        $grandProductTotals['others'][$productKey] = 0.0;
    }

    $currentRow = $dataStartRow;
    $lineNumber = 1;

    if (empty($customerSummaryRows)) {
        safeMergeCells($sheet, "A{$currentRow}:{$lastColumnLetter}{$currentRow}", $mergeRegistry);
        $sheet->setCellValue("A{$currentRow}", 'No sales were recorded for the selected date range.');
        $sheet->getStyle("A{$currentRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $currentRow++;
    } else {
        foreach ($customerSummaryRows as $customerRow) {
            $customerCode = isset($customerRow['customer_code']) ? (string)$customerRow['customer_code'] : '';
            $customerName = isset($customerRow['customer_name']) ? (string)$customerRow['customer_name'] : '';
            $customerDataStartRow = $currentRow;

            foreach ($customerRow['dates'] as $dateRow) {
                $transactionCount = isset($dateRow['transaction_count']) ? (int)$dateRow['transaction_count'] : 0;
                $totalQty = isset($dateRow['qty']) ? (float)$dateRow['qty'] : 0.0;
                $sheet->setCellValue("A{$currentRow}", $lineNumber);
                $sheet->setCellValueExplicit("B{$currentRow}", $customerCode, DataType::TYPE_STRING);
                $sheet->setCellValue("C{$currentRow}", $customerName);
                $sheet->setCellValueExplicit("D{$currentRow}", isset($dateRow['sale_date']) ? (string)$dateRow['sale_date'] : '', DataType::TYPE_STRING);
                $sheet->setCellValue("E{$currentRow}", $transactionCount);
                $sheet->setCellValue("F{$currentRow}", $totalQty);
                $saleDate = isset($dateRow['sale_date']) ? (string)$dateRow['sale_date'] : '';

                if ($showPaymentColumns) {
                    $cashValue = 0.0;
                    $onAccountValue = 0.0;
                    if (isset($cashByCustomerDate[$customerCode]) && isset($cashByCustomerDate[$customerCode][$saleDate])) {
                        $cashValue = (float)$cashByCustomerDate[$customerCode][$saleDate];
                    }
                    if (isset($onAccountByCustomerDate[$customerCode]) && isset($onAccountByCustomerDate[$customerCode][$saleDate])) {
                        $onAccountValue = (float)$onAccountByCustomerDate[$customerCode][$saleDate];
                    }

                    $sheet->setCellValue($cashColumnLetter . $currentRow, $cashValue);
                    $sheet->setCellValue($onAccountColumnLetter . $currentRow, $onAccountValue);
                    $grandCash += $cashValue;
                    $grandOnAccount += $onAccountValue;
                }

                foreach (['ice', 'others'] as $sectionKey) {
                    foreach ($productColumnsBySection[$sectionKey] as $productKey => $columnMeta) {
                        $qtyValue = 0.0;
                        if (
                            isset($dateRow['products'][$sectionKey])
                            && isset($dateRow['products'][$sectionKey][$productKey])
                        ) {
                            $qtyValue = (float)$dateRow['products'][$sectionKey][$productKey];
                        }

                        if ($qtyValue != 0.0) {
                            $sheet->setCellValue($columnMeta['column'] . $currentRow, $qtyValue);
                            $grandProductTotals[$sectionKey][$productKey] += $qtyValue;
                        }
                    }
                }

                foreach ($iceTypeSubtotalColumns as $typeSubtotalColumn) {
                    $sheet->setCellValue(
                        $typeSubtotalColumn['column'] . $currentRow,
                        "=SUM({$typeSubtotalColumn['start_column']}{$currentRow}:{$typeSubtotalColumn['end_column']}{$currentRow})"
                    );
                }
                if ($iceTotalColumnLetter !== '') {
                    if (!empty($iceTypeSubtotalColumns)) {
                        $iceSubtotalRefs = [];
                        foreach ($iceTypeSubtotalColumns as $typeSubtotalColumn) {
                            $iceSubtotalRefs[] = $typeSubtotalColumn['column'] . $currentRow;
                        }
                        $sheet->setCellValue($iceTotalColumnLetter . $currentRow, '=' . implode('+', $iceSubtotalRefs));
                    } elseif ($iceFirstColumnIndex !== null && $iceLastProductColumnIndex !== null) {
                        $iceFirstLetter = columnIndexToLetter($iceFirstColumnIndex);
                        $iceLastLetter = columnIndexToLetter($iceLastProductColumnIndex);
                        $sheet->setCellValue($iceTotalColumnLetter . $currentRow, "=SUM({$iceFirstLetter}{$currentRow}:{$iceLastLetter}{$currentRow})");
                    } else {
                        $sheet->setCellValue($iceTotalColumnLetter . $currentRow, 0);
                    }
                }
                if ($othersSubtotalColumnLetter !== '') {
                    $othersFirstLetter = columnIndexToLetter($othersFirstColumnIndex);
                    $othersLastLetter = columnIndexToLetter($othersLastProductColumnIndex);
                    $sheet->setCellValue($othersSubtotalColumnLetter . $currentRow, "=SUM({$othersFirstLetter}{$currentRow}:{$othersLastLetter}{$currentRow})");
                }
                $sheet->setCellValue(
                    $grandTotalColumnLetter . $currentRow,
                    $othersSubtotalColumnLetter !== '' && $iceTotalColumnLetter !== ''
                        ? "={$iceTotalColumnLetter}{$currentRow}+{$othersSubtotalColumnLetter}{$currentRow}"
                        : ($iceTotalColumnLetter !== '' ? "={$iceTotalColumnLetter}{$currentRow}" : ($othersSubtotalColumnLetter !== '' ? "={$othersSubtotalColumnLetter}{$currentRow}" : '=0'))
                );

                $grandTransactions += $transactionCount;
                $grandQty += $totalQty;

                $currentRow++;
                $lineNumber++;
            }
            $customerDataEndRow = $currentRow - 1;

            $sheet->setCellValue("B{$currentRow}", 'SUBTOTAL');
            $sheet->setCellValue("C{$currentRow}", $customerName !== '' ? $customerName : $customerCode);
            if ($customerDataEndRow >= $customerDataStartRow) {
                $sheet->setCellValue("E{$currentRow}", "=SUM(E{$customerDataStartRow}:E{$customerDataEndRow})");
                $sheet->setCellValue("F{$currentRow}", "=SUM(F{$customerDataStartRow}:F{$customerDataEndRow})");
            } else {
                $sheet->setCellValue("E{$currentRow}", isset($customerRow['subtotal_transactions']) ? (int)$customerRow['subtotal_transactions'] : 0);
                $sheet->setCellValue("F{$currentRow}", isset($customerRow['subtotal_qty']) ? (float)$customerRow['subtotal_qty'] : 0.0);
            }
            if ($showPaymentColumns) {
                if ($customerDataEndRow >= $customerDataStartRow) {
                    $sheet->setCellValue(
                        $cashColumnLetter . $currentRow,
                        "=SUM({$cashColumnLetter}{$customerDataStartRow}:{$cashColumnLetter}{$customerDataEndRow})"
                    );
                    $sheet->setCellValue(
                        $onAccountColumnLetter . $currentRow,
                        "=SUM({$onAccountColumnLetter}{$customerDataStartRow}:{$onAccountColumnLetter}{$customerDataEndRow})"
                    );
                } else {
                    $sheet->setCellValue($cashColumnLetter . $currentRow, 0);
                    $sheet->setCellValue($onAccountColumnLetter . $currentRow, 0);
                }
            }

            foreach (['ice', 'others'] as $sectionKey) {
                foreach ($productColumnsBySection[$sectionKey] as $productKey => $columnMeta) {
                    if ($customerDataEndRow >= $customerDataStartRow) {
                        $sheet->setCellValue(
                            $columnMeta['column'] . $currentRow,
                            "=SUM({$columnMeta['column']}{$customerDataStartRow}:{$columnMeta['column']}{$customerDataEndRow})"
                        );
                    } else {
                        $subtotalQty = 0.0;
                        if (
                            isset($customerRow['subtotal_products'][$sectionKey])
                            && isset($customerRow['subtotal_products'][$sectionKey][$productKey])
                        ) {
                            $subtotalQty = (float)$customerRow['subtotal_products'][$sectionKey][$productKey];
                        }
                        if ($subtotalQty != 0.0) {
                            $sheet->setCellValue($columnMeta['column'] . $currentRow, $subtotalQty);
                        }
                    }
                }
            }
            foreach ($iceTypeSubtotalColumns as $typeSubtotalColumn) {
                $sheet->setCellValue(
                    $typeSubtotalColumn['column'] . $currentRow,
                    "=SUM({$typeSubtotalColumn['start_column']}{$currentRow}:{$typeSubtotalColumn['end_column']}{$currentRow})"
                );
            }
            if ($iceTotalColumnLetter !== '') {
                if (!empty($iceTypeSubtotalColumns)) {
                    $iceSubtotalRefs = [];
                    foreach ($iceTypeSubtotalColumns as $typeSubtotalColumn) {
                        $iceSubtotalRefs[] = $typeSubtotalColumn['column'] . $currentRow;
                    }
                    $sheet->setCellValue($iceTotalColumnLetter . $currentRow, '=' . implode('+', $iceSubtotalRefs));
                } elseif ($iceFirstColumnIndex !== null && $iceLastProductColumnIndex !== null) {
                    $iceFirstLetter = columnIndexToLetter($iceFirstColumnIndex);
                    $iceLastLetter = columnIndexToLetter($iceLastProductColumnIndex);
                    $sheet->setCellValue($iceTotalColumnLetter . $currentRow, "=SUM({$iceFirstLetter}{$currentRow}:{$iceLastLetter}{$currentRow})");
                } else {
                    $sheet->setCellValue($iceTotalColumnLetter . $currentRow, 0);
                }
            }
            if ($othersSubtotalColumnLetter !== '') {
                $othersFirstLetter = columnIndexToLetter($othersFirstColumnIndex);
                $othersLastLetter = columnIndexToLetter($othersLastProductColumnIndex);
                $sheet->setCellValue($othersSubtotalColumnLetter . $currentRow, "=SUM({$othersFirstLetter}{$currentRow}:{$othersLastLetter}{$currentRow})");
            }
            $sheet->setCellValue(
                $grandTotalColumnLetter . $currentRow,
                $othersSubtotalColumnLetter !== '' && $iceTotalColumnLetter !== ''
                    ? "={$iceTotalColumnLetter}{$currentRow}+{$othersSubtotalColumnLetter}{$currentRow}"
                    : ($iceTotalColumnLetter !== '' ? "={$iceTotalColumnLetter}{$currentRow}" : ($othersSubtotalColumnLetter !== '' ? "={$othersSubtotalColumnLetter}{$currentRow}" : '=0'))
            );

            $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")
                ->getFont()
                ->setBold(true);
            $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB('F3F4F6');

            $currentRow++;
        }
    }

    $sheet->setCellValue("B{$currentRow}", 'GRAND TOTAL');
    $sheet->setCellValue("E{$currentRow}", $grandTransactions);
    $sheet->setCellValue("F{$currentRow}", $grandQty);
    if ($showPaymentColumns) {
        $sheet->setCellValue($cashColumnLetter . $currentRow, $grandCash);
        $sheet->setCellValue($onAccountColumnLetter . $currentRow, $grandOnAccount);
    }

    foreach (['ice', 'others'] as $sectionKey) {
        foreach ($productColumnsBySection[$sectionKey] as $productKey => $columnMeta) {
            $sheet->setCellValue(
                $columnMeta['column'] . $currentRow,
                isset($grandProductTotals[$sectionKey][$productKey]) ? (float)$grandProductTotals[$sectionKey][$productKey] : 0.0
            );
        }
    }
    foreach ($iceTypeSubtotalColumns as $typeSubtotalColumn) {
        $sheet->setCellValue(
            $typeSubtotalColumn['column'] . $currentRow,
            "=SUM({$typeSubtotalColumn['start_column']}{$currentRow}:{$typeSubtotalColumn['end_column']}{$currentRow})"
        );
    }
    if ($iceTotalColumnLetter !== '') {
        if (!empty($iceTypeSubtotalColumns)) {
            $iceSubtotalRefs = [];
            foreach ($iceTypeSubtotalColumns as $typeSubtotalColumn) {
                $iceSubtotalRefs[] = $typeSubtotalColumn['column'] . $currentRow;
            }
            $sheet->setCellValue($iceTotalColumnLetter . $currentRow, '=' . implode('+', $iceSubtotalRefs));
        } elseif ($iceFirstColumnIndex !== null && $iceLastProductColumnIndex !== null) {
            $iceFirstLetter = columnIndexToLetter($iceFirstColumnIndex);
            $iceLastLetter = columnIndexToLetter($iceLastProductColumnIndex);
            $sheet->setCellValue($iceTotalColumnLetter . $currentRow, "=SUM({$iceFirstLetter}{$currentRow}:{$iceLastLetter}{$currentRow})");
        } else {
            $sheet->setCellValue($iceTotalColumnLetter . $currentRow, 0);
        }
    }
    if ($othersSubtotalColumnLetter !== '') {
        $othersFirstLetter = columnIndexToLetter($othersFirstColumnIndex);
        $othersLastLetter = columnIndexToLetter($othersLastProductColumnIndex);
        $sheet->setCellValue($othersSubtotalColumnLetter . $currentRow, "=SUM({$othersFirstLetter}{$currentRow}:{$othersLastLetter}{$currentRow})");
    }
    $sheet->setCellValue(
        $grandTotalColumnLetter . $currentRow,
        $othersSubtotalColumnLetter !== '' && $iceTotalColumnLetter !== ''
            ? "={$iceTotalColumnLetter}{$currentRow}+{$othersSubtotalColumnLetter}{$currentRow}"
            : ($iceTotalColumnLetter !== '' ? "={$iceTotalColumnLetter}{$currentRow}" : ($othersSubtotalColumnLetter !== '' ? "={$othersSubtotalColumnLetter}{$currentRow}" : '=0'))
    );

    $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$currentRow}:{$lastColumnLetter}{$currentRow}")
        ->getFont()
        ->setBold(true)
        ->getColor()
        ->setRGB('000000');

    $lastDataRow = $currentRow;

    if ($lastDataRow >= $headerRow) {
        $sheet->getStyle("A{$headerRow}:{$lastColumnLetter}{$lastDataRow}")->applyFromArray($borderStyle);
    }
    if ($lastColumnIndex >= 7) {
        $sheet->getStyle("G{$sectionRow}:{$lastColumnLetter}{$headerRow}")->applyFromArray($borderStyle);
    }

    if ($lastDataRow >= $dataStartRow) {
        $sheet->getStyle("E{$dataStartRow}:E{$lastDataRow}")->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle("F{$dataStartRow}:{$lastColumnLetter}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
    }

    $sheet->getStyle("A{$headerRow}:A{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("B{$headerRow}:C{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("D{$headerRow}:E{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    if ($lastDataRow >= $dataStartRow) {
        $sheet->getStyle("F{$dataStartRow}:{$lastColumnLetter}{$lastDataRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }

    $sheet->freezePane('A9');
}

$registerRows = fetchToDateRegisterRows($mysqli, $startDate, $endDate);
$registerRows = enrichRegisterRowsWithOrderPayments($registerRows);

$customerSummaryRows = buildCustomerDateSummary($registerRows, 'amount');
$customerSummaryCashRows = buildCustomerDateSummary($registerRows, 'cash_allocated');
$customerSummaryOnAccountRows = buildCustomerDateSummary($registerRows, 'on_account_allocated');
$customerCashByDate = buildCustomerDateValueMap($customerSummaryCashRows);
$customerOnAccountByDate = buildCustomerDateValueMap($customerSummaryOnAccountRows);
$icePivotRows = buildPivotColumns($registerRows, 'ice');
$otherPivotRows = buildPivotColumns($registerRows, 'others');
unset($registerRows);

$rangeLabel = formatRangeLabel($startDate, $endDate);

$headerStyle = [
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E5E7EB']
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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Customer Amount Summary');

writeCustomerSummarySheet(
    $sheet,
    'CUSTOMER ORDER AMOUNT SUMMARY BY DATE',
    $rangeLabel,
    $generatedAt,
    $customerSummaryRows,
    $icePivotRows,
    $otherPivotRows,
    $headerStyle,
    $borderStyle,
    'TOTAL AMOUNT',
    $customerCashByDate,
    $customerOnAccountByDate,
    true
);

$cashSheet = $spreadsheet->createSheet();
$cashSheet->setTitle('Cash Summary');

writeCustomerSummarySheet(
    $cashSheet,
    'CUSTOMER CASH SUMMARY BY DATE',
    $rangeLabel,
    $generatedAt,
    $customerSummaryCashRows,
    $icePivotRows,
    $otherPivotRows,
    $headerStyle,
    $borderStyle,
    'CASH TOTAL'
);

$onAccountSheet = $spreadsheet->createSheet();
$onAccountSheet->setTitle('On Account Summary');

writeCustomerSummarySheet(
    $onAccountSheet,
    'CUSTOMER ON ACCOUNT SUMMARY BY DATE',
    $rangeLabel,
    $generatedAt,
    $customerSummaryOnAccountRows,
    $icePivotRows,
    $otherPivotRows,
    $headerStyle,
    $borderStyle,
    'ON ACCOUNT TOTAL'
);

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'todate_daily_sales_amount_summary_' . $startDate . '_to_' . $endDate . '_by_customer.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save('php://output');
exit;
