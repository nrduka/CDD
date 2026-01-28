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

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

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
                    'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                    'customer_code' => isset($row['customer_code']) ? (string)$row['customer_code'] : '',
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

function fetchToDateSummaryByDate($mysqli, $startDate, $endDate)
{
    $totals = [];
    $sql = "SELECT DATE(s.date_created) AS sale_date,
                   COUNT(DISTINCT s.order_no) AS total_transactions,
                   SUM(COALESCE(s.qty, 0)) AS qty_total,
                   SUM(COALESCE(s.amount, 0)) AS amount_total,
                   SUM(COALESCE(s.amt_received, 0)) AS amt_received_total,
                   SUM(COALESCE(s.amt_change, 0)) AS amt_change_total,
                   SUM(CASE WHEN COALESCE(s.amt_received, 0) <= 0 THEN COALESCE(s.amount, 0) ELSE 0 END) AS on_account_total,
                   SUM(CASE WHEN (COALESCE(s.amt_received, 0) - COALESCE(s.amt_change, 0)) < 0 THEN 0 ELSE (COALESCE(s.amt_received, 0) - COALESCE(s.amt_change, 0)) END) AS cash_total
            FROM sales s
            WHERE DATE(s.date_created) BETWEEN ? AND ?
            GROUP BY DATE(s.date_created)
            ORDER BY DATE(s.date_created)";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $saleDate = isset($row['sale_date']) ? (string)$row['sale_date'] : '';
                if ($saleDate === '') {
                    continue;
                }
                $totals[$saleDate] = [
                    'transactions' => isset($row['total_transactions']) ? (int)$row['total_transactions'] : 0,
                    'qty' => isset($row['qty_total']) ? (float)$row['qty_total'] : 0.0,
                    'amount' => isset($row['amount_total']) ? (float)$row['amount_total'] : 0.0,
                    'amt_received' => isset($row['amt_received_total']) ? (float)$row['amt_received_total'] : 0.0,
                    'amt_change' => isset($row['amt_change_total']) ? (float)$row['amt_change_total'] : 0.0,
                    'cash' => isset($row['cash_total']) ? (float)$row['cash_total'] : 0.0,
                    'on_account' => isset($row['on_account_total']) ? (float)$row['on_account_total'] : 0.0
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $totals;
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
$summaryByDate = fetchToDateSummaryByDate($mysqli, $startDate, $endDate);
$summaryByDate = fillMissingSummaryDates($summaryByDate, $startDate, $endDate);

$rangeLabel = formatRangeLabel($startDate, $endDate);
$reportRangeLabel = 'from ' . date('F d', strtotime($startDate)) . ' to ' . date('d, Y', strtotime($endDate));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Sales Register');

$sheet->setCellValue('A1', 'DAILY SALES REGISTER');
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

$headers = [
    'NO.',
    'DATE',
    'TIME',
    'DR NO.',
    'CUSTOMER CODE',
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
$sheet->getStyle("A{$headerRow}:N{$headerRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$headerRow}:N{$headerRow}")
    ->getAlignment()
    ->setWrapText(true)
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle("A{$headerRow}:N{$headerRow}")->getFont()->getColor()->setRGB('000000');

$currentRow = $dataRow;
$lineNumber = 1;
foreach ($registerRows as $row) {
    $orderNo = isset($row['order_no']) ? trim((string)$row['order_no']) : '';
    $orderNoDisplay = $orderNo !== '' ? 'DR' . $orderNo : '';
    $sheet->setCellValue("A{$currentRow}", $lineNumber);
    $sheet->setCellValueExplicit("B{$currentRow}", $row['date'], DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("C{$currentRow}", $row['time'], DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("D{$currentRow}", $orderNoDisplay, DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("E{$currentRow}", $row['customer_code'], DataType::TYPE_STRING);
    $sheet->setCellValue("F{$currentRow}", $row['qty']);
    $sheet->setCellValueExplicit("G{$currentRow}", $row['product_code'], DataType::TYPE_STRING);
    $sheet->setCellValue("H{$currentRow}", $row['product_description']);
    $sheet->setCellValue("I{$currentRow}", $row['unit_price']);
    $sheet->setCellValue("J{$currentRow}", $row['amount']);
    $sheet->setCellValue("K{$currentRow}", $row['amt_received']);
    $sheet->setCellValue("L{$currentRow}", $row['amt_change']);
    $sheet->setCellValue("M{$currentRow}", "=K{$currentRow}-L{$currentRow}");
    $sheet->setCellValue("N{$currentRow}", "=IF(K{$currentRow}=0,J{$currentRow},0)");
    $currentRow++;
    $lineNumber++;
}

$lastDetailRow = $currentRow - 1;
$totalRow = $currentRow;
$sheet->setCellValue("H{$totalRow}", 'TOTAL');
$sheet->getStyle("A{$totalRow}:N{$totalRow}")->getFont()->setBold(true);
if ($lastDetailRow >= $dataRow) {
    $sheet->setCellValue("J{$totalRow}", "=SUM(J{$dataRow}:J{$lastDetailRow})");
    $sheet->setCellValue("K{$totalRow}", "=SUM(K{$dataRow}:K{$lastDetailRow})");
    $sheet->setCellValue("L{$totalRow}", "=SUM(L{$dataRow}:L{$lastDetailRow})");
    $sheet->setCellValue("M{$totalRow}", "=SUM(M{$dataRow}:M{$lastDetailRow})");
    $sheet->setCellValue("N{$totalRow}", "=SUM(N{$dataRow}:N{$lastDetailRow})");
} else {
    $sheet->setCellValue("J{$totalRow}", 0);
    $sheet->setCellValue("K{$totalRow}", 0);
    $sheet->setCellValue("L{$totalRow}", 0);
    $sheet->setCellValue("M{$totalRow}", 0);
    $sheet->setCellValue("N{$totalRow}", 0);
}

$lastDataRow = $totalRow;
if ($lastDataRow >= $headerRow) {
    $sheet->getStyle("A{$headerRow}:N{$lastDataRow}")->applyFromArray($borderStyle);
}
if ($lastDataRow >= $dataRow) {
    $sheet->getStyle("F{$dataRow}:F{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $sheet->getStyle("I{$dataRow}:N{$lastDataRow}")->getNumberFormat()->setFormatCode($currencyFormat);
}
$sheet->getStyle("B{$headerRow}:G{$lastDataRow}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

foreach (range('A', 'N') as $column) {
    if (in_array($column, ['B', 'C', 'D', 'E', 'F', 'G'], true)) {
        $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth(15);
        continue;
    }
    if (in_array($column, ['I', 'J', 'K', 'L', 'M', 'N'], true)) {
        $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth(20);
        continue;
    }
    $sheet->getColumnDimension($column)->setAutoSize(true);
}
$sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
$sheet->getColumnDimension('H')->setAutoSize(false)->setWidth(40);

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

$filename = 'todate_daily_sales_' . $startDate . '_to_' . $endDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
