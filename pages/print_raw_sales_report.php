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

if (!function_exists('intdiv')) {
    // Polyfill for PHP < 7.
    function intdiv($dividend, $divisor)
    {
        if ($divisor == 0) {
            trigger_error('Division by zero', E_USER_WARNING);
            return 0;
        }

        return (int)($dividend / $divisor);
    }
}

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

$columns = [
    'sales_id' => 'SALES ID',
    'sales_order_no' => 'SALES ORDER NO',
    'sales_customer_code' => 'SALES CUSTOMER CODE',
    'sales_delivery_instruction' => 'SALES DELIVERY INSTRUCTION',
    'sales_lineitem' => 'SALES LINEITEM',
    'sales_qty' => 'SALES QTY',
    'sales_product_code' => 'SALES PRODUCT CODE',
    'sales_product_description' => 'SALES PRODUCT DESCRIPTION',
    'sales_lineitem_discount' => 'SALES LINEITEM DISCOUNT',
    'sales_discount' => 'SALES DISCOUNT',
    'sales_unit_price' => 'SALES UNIT PRICE',
    'sales_amount' => 'SALES AMOUNT',
    'sales_date_delivery' => 'SALES DATE DELIVERY',
    'sales_time_delivery' => 'SALES TIME DELIVERY',
    'sales_date_created' => 'SALES DATE CREATED',
    'sales_amt_received' => 'SALES AMT RECEIVED',
    'sales_amt_change' => 'SALES AMT CHANGE',
    'product_id' => 'PRODUCT ID',
    'product_code' => 'PRODUCT CODE',
    'product_description' => 'PRODUCT DESCRIPTION',
    'product_uom' => 'PRODUCT UOM',
    'product_unit_price' => 'PRODUCT UNIT PRICE',
    'product_date_created' => 'PRODUCT DATE CREATED',
    'product_type' => 'PRODUCT TYPE',
    'product_status' => 'PRODUCT STATUS',
    'customer_id' => 'CUSTOMER ID',
    'customer_code' => 'CUSTOMER CODE',
    'customer_first_name' => 'CUSTOMER FIRST NAME',
    'customer_middle_name' => 'CUSTOMER MIDDLE NAME',
    'customer_last_name' => 'CUSTOMER LAST NAME',
    'customer_address' => 'CUSTOMER ADDRESS',
    'customer_store_name' => 'CUSTOMER STORE NAME',
    'customer_type' => 'CUSTOMER TYPE',
    'customer_series' => 'CUSTOMER SERIES',
    'customer_source' => 'CUSTOMER SOURCE',
    'customer_area' => 'CUSTOMER AREA',
    'customer_sales_channel' => 'CUSTOMER SALES CHANNEL'
];

function columnLetterFromIndex($index)
{
    $index = (int)$index;
    $letter = '';
    while ($index > 0) {
        $remainder = ($index - 1) % 26;
        $letter = chr(65 + $remainder) . $letter;
        $index = intdiv($index - 1, 26);
    }
    return $letter;
}

function fetchRawSalesRows($mysqli, $reportDate)
{
    $rows = [];
    $sql = "SELECT s.id AS sales_id,
                   s.order_no AS sales_order_no,
                   s.customer_code AS sales_customer_code,
                   s.delivery_instruction AS sales_delivery_instruction,
                   s.lineitem AS sales_lineitem,
                   s.qty AS sales_qty,
                   s.product_code AS sales_product_code,
                   s.product_description AS sales_product_description,
                   s.lineitem_discount AS sales_lineitem_discount,
                   s.discount AS sales_discount,
                   s.unit_price AS sales_unit_price,
                   s.amount AS sales_amount,
                   s.date_delivery AS sales_date_delivery,
                   s.time_delivery AS sales_time_delivery,
                   s.date_created AS sales_date_created,
                   s.amt_received AS sales_amt_received,
                   s.amt_change AS sales_amt_change,
                   p.id AS product_id,
                   p.product_code AS product_code,
                   p.product_description AS product_description,
                   p.uom AS product_uom,
                   p.unit_price AS product_unit_price,
                   p.date_created AS product_date_created,
                   p.product_type AS product_type,
                   p.status AS product_status,
                   c.id AS customer_id,
                   c.customer_code AS customer_code,
                   c.firstName AS customer_first_name,
                   c.middleName AS customer_middle_name,
                   c.lastName AS customer_last_name,
                   c.address AS customer_address,
                   c.storeName AS customer_store_name,
                   c.type AS customer_type,
                   c.customer_series AS customer_series,
                   c.customer_source AS customer_source,
                   c.area AS customer_area,
                   c.sales_channel AS customer_sales_channel
            FROM sales s
            LEFT JOIN ice_product p ON p.product_code = s.product_code
            LEFT JOIN customer c ON c.customer_code = s.customer_code
            WHERE DATE(s.date_created) = ?
            ORDER BY s.date_created, s.order_no, s.lineitem";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

$salesRows = fetchRawSalesRows($mysqli, $reportDate);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Raw Sales');

$sheet->setCellValue('A1', 'Raw Sales Report');
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

$headerRow = 8;
$dataRow = 9;

$headers = array_values($columns);
$keys = array_keys($columns);
$lastColumn = columnLetterFromIndex(count($headers));

$sheet->fromArray($headers, null, 'A' . $headerRow);
$sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")
    ->getAlignment()
    ->setWrapText(true)
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$stringColumns = [
    'sales_order_no',
    'sales_customer_code',
    'sales_product_code',
    'product_code',
    'customer_code'
];

$currentRow = $dataRow;
$lastDataRow = $headerRow;

foreach ($salesRows as $row) {
    $columnIndex = 1;
    foreach ($keys as $key) {
        $cell = columnLetterFromIndex($columnIndex) . $currentRow;
        $value = isset($row[$key]) ? $row[$key] : '';
        if (in_array($key, $stringColumns, true)) {
            $sheet->setCellValueExplicit($cell, (string)$value, DataType::TYPE_STRING);
        } else {
            $sheet->setCellValue($cell, $value);
        }
        $columnIndex++;
    }
    $lastDataRow = $currentRow;
    $currentRow++;
}

if ($lastDataRow >= $headerRow) {
    $sheet->getStyle("A{$headerRow}:{$lastColumn}{$lastDataRow}")->applyFromArray($borderStyle);
}

for ($index = 1; $index <= count($headers); $index++) {
    $columnLetter = columnLetterFromIndex($index);
    $sheet->getColumnDimension($columnLetter)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'raw_sales_report_' . $reportDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
