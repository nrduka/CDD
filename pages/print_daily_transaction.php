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

$reportDateInput = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$reportDate = $reportDateInput !== '' ? $reportDateInput : date('Y-m-d');
$reportDateObject = DateTime::createFromFormat('Y-m-d', $reportDate);
if (!$reportDateObject || $reportDateObject->format('Y-m-d') !== $reportDate) {
    $reportDate = date('Y-m-d');
}

$reportDateLabel = date('F d, Y', strtotime($reportDate));
$generatedAt = date('Y-m-d H:i:s');

if (!isset($mysqli) || !$mysqli) {
    http_response_code(500);
    echo 'Database connection unavailable.';
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

function fetchDailyRegisterRows($mysqli, $reportDate)
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
            WHERE DATE(s.date_created) = ?
            ORDER BY s.date_created, s.order_no, s.lineitem";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $orderedAt = resolveSalesDateTime(
                    isset($row['date_time_ordered']) ? $row['date_time_ordered'] : '',
                    isset($row['date_created']) ? $row['date_created'] : ''
                );
                $dateLabel = $orderedAt ? $orderedAt->format('Y-m-d') : '';
                $timeLabel = $orderedAt ? $orderedAt->format('H:i:s') : '';

                $rows[] = [
                    'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                    'customer_code' => isset($row['customer_code']) ? (string)$row['customer_code'] : '',
                    'qty' => isset($row['qty']) ? (float)$row['qty'] : 0,
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => isset($row['product_description']) ? (string)$row['product_description'] : '',
                    'unit_price' => isset($row['unit_price']) ? (float)$row['unit_price'] : 0,
                    'amount' => isset($row['amount']) ? (float)$row['amount'] : 0,
                    'amt_received' => isset($row['amt_received']) ? (float)$row['amt_received'] : 0,
                    'amt_change' => isset($row['amt_change']) ? (float)$row['amt_change'] : 0,
                    'date_label' => $dateLabel,
                    'time_label' => $timeLabel
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

$registerRows = fetchDailyRegisterRows($mysqli, $reportDate);
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
$dayCount = 1;
$averageDailySales = $dayCount > 0 ? $totalSales / $dayCount : 0;
$averageDailyTransactions = $dayCount > 0 ? $transactionCount / $dayCount : 0;
$reportRangeLabel = 'from ' . date('F d', strtotime($reportDate)) . ' to ' . date('d, Y', strtotime($reportDate));

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
    $sheet->setCellValueExplicit("B{$currentRow}", $row['date_label'], DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("C{$currentRow}", $row['time_label'], DataType::TYPE_STRING);
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

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'daily_sales_register_' . $reportDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
