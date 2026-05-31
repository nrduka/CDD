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

function fetchDailySales($mysqli, $reportDate)
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
            WHERE DATE(COALESCE(ds.date_paid, ds.date_created)) = ?
            ORDER BY pr.department, ds.date_paid, ds.date_created, ds.id";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
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

$salesRows = fetchDailySales($mysqli, $reportDate);
$departmentTotals = [];
$grandTotal = 0;
$groupedSales = [];

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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Sales');

$sheet->setCellValue('A1', 'Daily Sales Report');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', 'Report Date: ' . $reportDateLabel);
$sheet->setCellValue('A3', 'Generated: ' . $generatedAt);

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '1f2937']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'e5e7eb']
    ]
];

$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'cbd5e1']
        ]
    ]
];

$currencyFormat = '#,##0.00';

$headers = ['Department', 'Retrieval Code', 'Item Code', 'Item Description', 'Line Item', 'Amount', 'Status', 'Sale Date'];
$sheet->fromArray($headers, null, 'A5');
$sheet->getStyle('A5:H5')->applyFromArray($headerStyle);

$currentRow = 6;
foreach ($groupedSales as $department => $items) {
    foreach ($items as $item) {
        $sheet->setCellValue("A{$currentRow}", $department !== '' ? $department : 'Unassigned');
        $sheet->setCellValueExplicit(
            "B{$currentRow}",
            $item['retrieval_code'] !== '' ? $item['retrieval_code'] : 'N/A',
            DataType::TYPE_STRING
        );
        $sheet->setCellValue("C{$currentRow}", $item['item_code']);
        $sheet->setCellValue("D{$currentRow}", $item['item_description'] !== '' ? $item['item_description'] : 'Unspecified');
        $sheet->setCellValue("E{$currentRow}", $item['line_item'] !== '' ? $item['line_item'] : '-');
        $sheet->setCellValue("F{$currentRow}", $item['amount']);
        $sheet->setCellValue("G{$currentRow}", formatPaymentStatus($item['payment_status']));
        $sheet->setCellValue("H{$currentRow}", $item['sale_date']);
        $sheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        $currentRow++;
    }

    $sheet->setCellValue("A{$currentRow}", "Subtotal - {$department}");
    $sheet->setCellValue("F{$currentRow}", $departmentTotals[$department]);
    $sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $currentRow++;
}

$sheet->setCellValue("A{$currentRow}", 'Grand Total');
$sheet->setCellValue("F{$currentRow}", $grandTotal);
$sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$currentRow}:H{$currentRow}")->getFont()->setBold(true);
$sheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

if ($currentRow >= 6) {
    $sheet->getStyle("A6:H{$currentRow}")->applyFromArray($borderStyle);
}

foreach (range('A', 'H') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'daily_sales_' . $reportDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->setUseDiskCaching(true, getSpreadsheetExportTempDirectory());
$writer->save('php://output');
exit;
