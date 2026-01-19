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

function buildCustomerName($row)
{
    $firstName = trim((string)(isset($row['firstName']) ? $row['firstName'] : ''));
    $middleName = trim((string)(isset($row['middleName']) ? $row['middleName'] : ''));
    $lastName = trim((string)(isset($row['lastName']) ? $row['lastName'] : ''));

    $parts = [];
    if ($firstName !== '') {
        $parts[] = $firstName;
    }
    if ($middleName !== '') {
        $parts[] = $middleName;
    }
    if ($lastName !== '') {
        $parts[] = $lastName;
    }

    $name = trim(implode(' ', $parts));
    if ($name === '') {
        $storeName = trim((string)(isset($row['storeName']) ? $row['storeName'] : ''));
        if ($storeName !== '') {
            $name = $storeName;
        }
    }

    return $name;
}

function fetchDailyTransactions($mysqli, $reportDate)
{
    $rows = [];
    $sql = "SELECT s.order_no,
                   s.customer_code,
                   s.lineitem,
                   s.product_code,
                   s.product_description,
                   s.lineitem_discount,
                   s.discount,
                   s.unit_price,
                   s.qty,
                   s.amount,
                   c.firstName,
                   c.middleName,
                   c.lastName,
                   c.storeName
            FROM sales s
            LEFT JOIN customer_register c ON c.customer_code = s.customer_code
            WHERE DATE(s.date_created) = ?
            ORDER BY s.order_no, s.lineitem";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'order_no' => isset($row['order_no']) ? (string)$row['order_no'] : '',
                    'customer_code' => isset($row['customer_code']) ? (string)$row['customer_code'] : '',
                    'lineitem' => isset($row['lineitem']) ? (int)$row['lineitem'] : 0,
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => isset($row['product_description']) ? (string)$row['product_description'] : '',
                    'lineitem_discount' => isset($row['lineitem_discount']) ? (float)$row['lineitem_discount'] : 0,
                    'discount' => isset($row['discount']) ? (float)$row['discount'] : 0,
                    'unit_price' => isset($row['unit_price']) ? (float)$row['unit_price'] : 0,
                    'qty' => isset($row['qty']) ? (float)$row['qty'] : 0,
                    'amount' => isset($row['amount']) ? (float)$row['amount'] : 0,
                    'firstName' => isset($row['firstName']) ? (string)$row['firstName'] : '',
                    'middleName' => isset($row['middleName']) ? (string)$row['middleName'] : '',
                    'lastName' => isset($row['lastName']) ? (string)$row['lastName'] : '',
                    'storeName' => isset($row['storeName']) ? (string)$row['storeName'] : ''
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function fetchDailySalesByProduct($mysqli, $reportDate)
{
    $rows = [];
    $sql = "SELECT COALESCE(p.product_type, 'Unspecified') AS product_type,
                   p.product_code AS product_code,
                   p.product_description AS product_description,
                   p.unit_price AS unit_price,
                   COALESCE(SUM(s.lineitem_discount), 0) AS lineitem_discount_total,
                   COALESCE(SUM(s.discount), 0) AS discount_total,
                   COALESCE(SUM(s.qty), 0) AS qty_total,
                   COALESCE(SUM(s.amount), 0) AS amount_total
            FROM ice_product p
            LEFT JOIN sales s
                ON s.product_code = p.product_code
                AND DATE(s.date_created) = ?
            WHERE COALESCE(p.status, 0) <> 1
            GROUP BY COALESCE(p.product_type, 'Unspecified'),
                     p.product_code,
                     p.product_description,
                     p.unit_price
            ORDER BY COALESCE(p.product_type, 'Unspecified'),
                     p.product_code";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'product_type' => isset($row['product_type']) ? (string)$row['product_type'] : 'Unspecified',
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => isset($row['product_description']) ? (string)$row['product_description'] : '',
                    'unit_price' => isset($row['unit_price']) ? (float)$row['unit_price'] : 0,
                    'lineitem_discount_total' => isset($row['lineitem_discount_total']) ? (float)$row['lineitem_discount_total'] : 0,
                    'discount_total' => isset($row['discount_total']) ? (float)$row['discount_total'] : 0,
                    'qty_total' => isset($row['qty_total']) ? (float)$row['qty_total'] : 0,
                    'amount_total' => isset($row['amount_total']) ? (float)$row['amount_total'] : 0
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

$transactions = fetchDailyTransactions($mysqli, $reportDate);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Transactions');

$sheet->setCellValue('A1', 'Daily Transaction Report');
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

$headerRow = 8;
$dataRow = 9;

$headers = [
    'No',
    'Lineitem No',
    'Order No',
    'Customer Code',
    'Customer',
    'Product Code',
    'Product Description',
    'Line Item Discount',
    'Discount',
    'Unit Price',
    'Qty',
    'Amount'
];

$sheet->fromArray($headers, null, 'A' . $headerRow);
$sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$headerRow}:L{$headerRow}")
    ->getAlignment()
    ->setWrapText(true)
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$currentRow = $dataRow;
$currentOrderNo = null;
$orderSubtotal = 0;
$grandTotal = 0;
$orderCounter = 0;
$lineNumber = 0;

foreach ($transactions as $row) {
    $orderNo = $row['order_no'] !== '' ? $row['order_no'] : 'UNKNOWN';

    if ($currentOrderNo !== null && $orderNo !== $currentOrderNo) {
        $sheet->setCellValue("C{$currentRow}", 'Subtotal');
        $sheet->setCellValue("L{$currentRow}", $orderSubtotal);
        $sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray($headerStyle);
        $sheet->getStyle("A{$currentRow}:L{$currentRow}")->getFont()->setBold(true);
        $sheet->getStyle("L{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        $currentRow++;
        $orderSubtotal = 0;
        $lineNumber = 0;
    }

    if ($currentOrderNo === null || $orderNo !== $currentOrderNo) {
        $currentOrderNo = $orderNo;
        $orderCounter++;
    }

    $lineNumber++;
    $customerName = buildCustomerName($row);
    $lineItemNo = $row['lineitem'] !== '' ? $row['lineitem'] : $lineNumber;
    $orderNumberCell = $lineNumber === 1 ? $orderCounter : '';

    $sheet->setCellValue("A{$currentRow}", $orderNumberCell);
    $sheet->setCellValue("B{$currentRow}", $lineItemNo);
    $sheet->setCellValueExplicit("C{$currentRow}", $orderNo, DataType::TYPE_STRING);
    $sheet->setCellValueExplicit("D{$currentRow}", $row['customer_code'], DataType::TYPE_STRING);
    $sheet->setCellValue("E{$currentRow}", $customerName);
    $sheet->setCellValueExplicit("F{$currentRow}", $row['product_code'], DataType::TYPE_STRING);
    $sheet->setCellValue("G{$currentRow}", $row['product_description']);
    $sheet->setCellValue("H{$currentRow}", $row['lineitem_discount']);
    $sheet->setCellValue("I{$currentRow}", $row['discount']);
    $sheet->setCellValue("J{$currentRow}", $row['unit_price']);
    $sheet->setCellValue("K{$currentRow}", $row['qty']);
    $sheet->setCellValue("L{$currentRow}", $row['amount']);
    $sheet->getStyle("H{$currentRow}:J{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $sheet->getStyle("L{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

    $orderSubtotal += $row['amount'];
    $grandTotal += $row['amount'];
    $currentRow++;
}

if ($currentOrderNo !== null) {
    $sheet->setCellValue("C{$currentRow}", 'Subtotal');
    $sheet->setCellValue("L{$currentRow}", $orderSubtotal);
    $sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$currentRow}:L{$currentRow}")->getFont()->setBold(true);
    $sheet->getStyle("L{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $currentRow++;
}

$sheet->setCellValue("C{$currentRow}", 'Grand Total');
$sheet->setCellValue("L{$currentRow}", $grandTotal);
$sheet->getStyle("A{$currentRow}:L{$currentRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$currentRow}:L{$currentRow}")->getFont()->setBold(true);
$sheet->getStyle("L{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

if ($currentRow >= $headerRow) {
    $sheet->getStyle("A{$headerRow}:L{$currentRow}")->applyFromArray($borderStyle);
}

foreach (range('A', 'L') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}
$sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
$sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(5);

$productRows = fetchDailySalesByProduct($mysqli, $reportDate);
$productSheet = $spreadsheet->createSheet();
$productSheet->setTitle('Daily Sales by Product');

$productSheet->setCellValue('A1', 'Daily Sales by Product');
$productSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$productSheet->setCellValue('A2', 'Report Date: ' . $reportDateLabel);
$productSheet->setCellValue('A3', 'Generated: ' . $generatedAt);

$productHeaderRow = 8;
$productDataRow = 9;
$productHeaders = [
    'Product Code',
    'Product Description',
    'Line Item Discount',
    'Discount',
    'Unit Price',
    'Qty',
    'Amount'
];

$productSheet->fromArray($productHeaders, null, 'A' . $productHeaderRow);
$productSheet->getStyle("A{$productHeaderRow}:G{$productHeaderRow}")->applyFromArray($headerStyle);
$productSheet->getStyle("A{$productHeaderRow}:G{$productHeaderRow}")
    ->getAlignment()
    ->setWrapText(true)
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$currentRow = $productDataRow;
$currentType = null;
$typeTotals = [
    'lineitem_discount' => 0,
    'discount' => 0,
    'qty' => 0,
    'amount' => 0
];
$grandTotals = [
    'lineitem_discount' => 0,
    'discount' => 0,
    'qty' => 0,
    'amount' => 0
];

foreach ($productRows as $row) {
    $productType = $row['product_type'] !== '' ? $row['product_type'] : 'Unspecified';

    if ($currentType !== null && $productType !== $currentType) {
        $productSheet->setCellValue("B{$currentRow}", 'Subtotal - ' . $currentType);
        $productSheet->setCellValue("C{$currentRow}", $typeTotals['lineitem_discount']);
        $productSheet->setCellValue("D{$currentRow}", $typeTotals['discount']);
        $productSheet->setCellValue("F{$currentRow}", $typeTotals['qty']);
        $productSheet->setCellValue("G{$currentRow}", $typeTotals['amount']);
        $productSheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray($headerStyle);
        $productSheet->getStyle("A{$currentRow}:G{$currentRow}")->getFont()->setBold(true);
        $productSheet->getStyle("C{$currentRow}:E{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        $productSheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        $currentRow++;
        $typeTotals = [
            'lineitem_discount' => 0,
            'discount' => 0,
            'qty' => 0,
            'amount' => 0
        ];
    }

    if ($currentType === null || $productType !== $currentType) {
        $currentType = $productType;
    }

    $productSheet->setCellValueExplicit("A{$currentRow}", $row['product_code'], DataType::TYPE_STRING);
    $productSheet->setCellValue("B{$currentRow}", $row['product_description']);
    $productSheet->setCellValue("C{$currentRow}", $row['lineitem_discount_total']);
    $productSheet->setCellValue("D{$currentRow}", $row['discount_total']);
    $productSheet->setCellValue("E{$currentRow}", $row['unit_price']);
    $productSheet->setCellValue("F{$currentRow}", $row['qty_total']);
    $productSheet->setCellValue("G{$currentRow}", $row['amount_total']);
    $productSheet->getStyle("C{$currentRow}:E{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $productSheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

    $typeTotals['lineitem_discount'] += $row['lineitem_discount_total'];
    $typeTotals['discount'] += $row['discount_total'];
    $typeTotals['qty'] += $row['qty_total'];
    $typeTotals['amount'] += $row['amount_total'];

    $grandTotals['lineitem_discount'] += $row['lineitem_discount_total'];
    $grandTotals['discount'] += $row['discount_total'];
    $grandTotals['qty'] += $row['qty_total'];
    $grandTotals['amount'] += $row['amount_total'];

    $currentRow++;
}

if ($currentType !== null) {
    $productSheet->setCellValue("B{$currentRow}", 'Subtotal - ' . $currentType);
    $productSheet->setCellValue("C{$currentRow}", $typeTotals['lineitem_discount']);
    $productSheet->setCellValue("D{$currentRow}", $typeTotals['discount']);
    $productSheet->setCellValue("F{$currentRow}", $typeTotals['qty']);
    $productSheet->setCellValue("G{$currentRow}", $typeTotals['amount']);
    $productSheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray($headerStyle);
    $productSheet->getStyle("A{$currentRow}:G{$currentRow}")->getFont()->setBold(true);
    $productSheet->getStyle("C{$currentRow}:E{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $productSheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $currentRow++;
}

$productSheet->setCellValue("B{$currentRow}", 'Grand Total');
$productSheet->setCellValue("C{$currentRow}", $grandTotals['lineitem_discount']);
$productSheet->setCellValue("D{$currentRow}", $grandTotals['discount']);
$productSheet->setCellValue("F{$currentRow}", $grandTotals['qty']);
$productSheet->setCellValue("G{$currentRow}", $grandTotals['amount']);
$productSheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray($headerStyle);
$productSheet->getStyle("A{$currentRow}:G{$currentRow}")->getFont()->setBold(true);
$productSheet->getStyle("C{$currentRow}:E{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
$productSheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

if ($currentRow >= $productHeaderRow) {
    $productSheet->getStyle("A{$productHeaderRow}:G{$currentRow}")->applyFromArray($borderStyle);
}

foreach (range('A', 'G') as $column) {
    $productSheet->getColumnDimension($column)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'daily_transaction_' . $reportDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
