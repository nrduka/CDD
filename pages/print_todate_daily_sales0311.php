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
require_once __DIR__ . '/report_export_runtime.php';

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
$reportType = in_array($reportTypeInput, ['detailed', 'by_product', 'by_customer'], true) ? $reportTypeInput : 'detailed';

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

if (!isset($mysqli) || !$mysqli) {
    http_response_code(500);
    echo 'Database connection unavailable.';
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
                    'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                    'customer_code' => isset($row['customer_code']) ? (string)$row['customer_code'] : '',
                    'customer_name' => buildCustomerDisplayName($row),
                    'qty' => isset($row['qty']) ? (float)$row['qty'] : 0.0,
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => isset($row['product_description']) ? (string)$row['product_description'] : '',
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

$registerRows = fetchToDateRegisterRows($mysqli, $startDate, $endDate);
$registerRows = enrichRegisterRowsWithOrderPayments($registerRows);
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

$summarySheet->setCellValue('A1', 'Sales Summary by Date');
$summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$summarySheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
$summarySheet->setCellValue('A3', 'Generated: ' . $generatedAt);

$summaryHeaderRow = 8;
$summaryDataRow = 9;
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

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filenameSuffix = '_detailed';
if ($reportType === 'by_product') {
    $filenameSuffix = '_by_product';
} elseif ($reportType === 'by_customer') {
    $filenameSuffix = '_by_customer';
}
$filename = 'todate_daily_sales_' . $startDate . '_to_' . $endDate . $filenameSuffix . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->setUseDiskCaching(true, getSpreadsheetExportTempDirectory());
$writer->save('php://output');
exit;
