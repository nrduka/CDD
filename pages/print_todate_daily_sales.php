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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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

function fetchToDateDailySales($mysqli, $startDate, $endDate)
{
    $rows = [];
    $sql = "SELECT DATE(s.date_created) AS sale_date,
                   s.product_code,
                   COALESCE(p.product_description, s.product_description) AS product_description,
                   COALESCE(p.unit_price, s.unit_price) AS unit_price,
                   COUNT(DISTINCT s.order_no) AS total_transactions,
                   SUM(COALESCE(s.lineitem_discount, 0)) AS lineitem_discount_total,
                   SUM(COALESCE(s.discount, 0)) AS discount_total,
                   SUM(COALESCE(s.qty, 0)) AS qty_total,
                   SUM(COALESCE(s.amount, 0)) AS amount_total
            FROM sales s
            LEFT JOIN ice_product p ON p.product_code = s.product_code
            WHERE DATE(s.date_created) BETWEEN ? AND ?
            GROUP BY DATE(s.date_created),
                     s.product_code,
                     COALESCE(p.product_description, s.product_description),
                     COALESCE(p.unit_price, s.unit_price)
            ORDER BY DATE(s.date_created), s.product_code";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'sale_date' => isset($row['sale_date']) ? (string)$row['sale_date'] : '',
                    'product_code' => isset($row['product_code']) ? (string)$row['product_code'] : '',
                    'product_description' => isset($row['product_description']) ? (string)$row['product_description'] : '',
                    'total_transactions' => isset($row['total_transactions']) ? (int)$row['total_transactions'] : 0,
                    'lineitem_discount' => isset($row['lineitem_discount_total']) ? (float)$row['lineitem_discount_total'] : 0,
                    'discount' => isset($row['discount_total']) ? (float)$row['discount_total'] : 0,
                    'qty' => isset($row['qty_total']) ? (float)$row['qty_total'] : 0,
                    'unit_price' => isset($row['unit_price']) ? (float)$row['unit_price'] : 0,
                    'amount' => isset($row['amount_total']) ? (float)$row['amount_total'] : 0
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function fetchIceProducts($mysqli)
{
    $products = [];
    $sql = "SELECT product_code, product_description, product_type
            FROM ice_product
            ORDER BY product_type, product_code";
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $productCode = isset($row['product_code']) ? (string)$row['product_code'] : '';
            if ($productCode === '') {
                continue;
            }
            $productType = trim((string)(isset($row['product_type']) ? $row['product_type'] : ''));
            if ($productType === '') {
                $productType = 'Unspecified';
            }
            if (!isset($products[$productType])) {
                $products[$productType] = [];
            }
            $products[$productType][] = [
                'code' => $productCode,
                'description' => isset($row['product_description']) ? (string)$row['product_description'] : ''
            ];
        }
        $result->free();
    }

    if (!empty($products)) {
        ksort($products);
        foreach ($products as $type => $list) {
            usort($products[$type], function ($a, $b) {
                return strcmp($a['code'], $b['code']);
            });
        }
    }

    return $products;
}

function fetchTotalTransactionsByDate($mysqli, $startDate, $endDate)
{
    $totals = [];
    $sql = "SELECT DATE(s.date_created) AS sale_date,
                   COUNT(DISTINCT s.order_no) AS total_transactions
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
                $totals[$saleDate] = isset($row['total_transactions']) ? (int)$row['total_transactions'] : 0;
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

$salesRows = fetchToDateDailySales($mysqli, $startDate, $endDate);
$groupedSales = [];
$dateTotals = [];
$grandTotals = [
    'transactions' => 0,
    'lineitem_discount' => 0,
    'discount' => 0,
    'qty' => 0,
    'amount' => 0
];

foreach ($salesRows as $row) {
    $dateKey = $row['sale_date'];
    if (!isset($groupedSales[$dateKey])) {
        $groupedSales[$dateKey] = [];
        $dateTotals[$dateKey] = [
            'transactions' => 0,
            'lineitem_discount' => 0,
            'discount' => 0,
            'qty' => 0,
            'amount' => 0
        ];
    }
    $groupedSales[$dateKey][] = $row;
    $dateTotals[$dateKey]['transactions'] += $row['total_transactions'];
    $dateTotals[$dateKey]['lineitem_discount'] += $row['lineitem_discount'];
    $dateTotals[$dateKey]['discount'] += $row['discount'];
    $dateTotals[$dateKey]['qty'] += $row['qty'];
    $dateTotals[$dateKey]['amount'] += $row['amount'];

    $grandTotals['transactions'] += $row['total_transactions'];
    $grandTotals['lineitem_discount'] += $row['lineitem_discount'];
    $grandTotals['discount'] += $row['discount'];
$grandTotals['qty'] += $row['qty'];
    $grandTotals['amount'] += $row['amount'];
}
ksort($groupedSales);
ksort($dateTotals);

$rangeLabel = formatRangeLabel($startDate, $endDate);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('To-Date Daily Sales');

$sheet->setCellValue('A1', 'To-Date Daily Sales');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
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
$numberFormat = '#,##0.00';

$headerRow = 8;
$dataRow = 9;

$headers = [
    'Date',
    'Product Code',
    'Product Description',
    'Total Transaction',
    'Line Item Discount',
    'Discount',
    'Qty',
    'Unit Price',
    'Amount'
];

$sheet->fromArray($headers, null, 'A' . $headerRow);
$sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$headerRow}:I{$headerRow}")
    ->getAlignment()
    ->setWrapText(true)
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$currentRow = $dataRow;
foreach ($groupedSales as $saleDate => $items) {
    foreach ($items as $item) {
        $sheet->setCellValue("A{$currentRow}", $saleDate);
        $sheet->setCellValueExplicit("B{$currentRow}", $item['product_code'], DataType::TYPE_STRING);
        $sheet->setCellValue("C{$currentRow}", $item['product_description']);
        $sheet->setCellValue("D{$currentRow}", $item['total_transactions']);
        $sheet->setCellValue("E{$currentRow}", $item['lineitem_discount']);
        $sheet->setCellValue("F{$currentRow}", $item['discount']);
        $sheet->setCellValue("G{$currentRow}", $item['qty']);
        $sheet->setCellValue("H{$currentRow}", $item['unit_price']);
        $sheet->setCellValue("I{$currentRow}", $item['amount']);
        $sheet->getStyle("E{$currentRow}:F{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($numberFormat);
        $sheet->getStyle("H{$currentRow}:I{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
        $currentRow++;
    }

    $sheet->setCellValue("B{$currentRow}", 'Subtotal');
    $sheet->setCellValue("D{$currentRow}", $dateTotals[$saleDate]['transactions']);
    $sheet->setCellValue("E{$currentRow}", $dateTotals[$saleDate]['lineitem_discount']);
    $sheet->setCellValue("F{$currentRow}", $dateTotals[$saleDate]['discount']);
    $sheet->setCellValue("G{$currentRow}", $dateTotals[$saleDate]['qty']);
    $sheet->setCellValue("I{$currentRow}", $dateTotals[$saleDate]['amount']);
    $sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getFont()->setBold(true);
    $sheet->getStyle("E{$currentRow}:F{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($numberFormat);
    $sheet->getStyle("I{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $currentRow++;
}

$sheet->setCellValue("B{$currentRow}", 'Grand Total');
$sheet->setCellValue("D{$currentRow}", $grandTotals['transactions']);
$sheet->setCellValue("E{$currentRow}", $grandTotals['lineitem_discount']);
$sheet->setCellValue("F{$currentRow}", $grandTotals['discount']);
$sheet->setCellValue("G{$currentRow}", $grandTotals['qty']);
$sheet->setCellValue("I{$currentRow}", $grandTotals['amount']);
$sheet->getStyle("A{$currentRow}:I{$currentRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$currentRow}:I{$currentRow}")->getFont()->setBold(true);
$sheet->getStyle("E{$currentRow}:F{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
$sheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($numberFormat);
$sheet->getStyle("I{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

if ($currentRow >= $headerRow) {
    $sheet->getStyle("A{$headerRow}:I{$currentRow}")->applyFromArray($borderStyle);
}

foreach (range('A', 'I') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
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
    'No',
    'Date',
    'Total Transaction',
    'Line Item Discount',
    'Discount',
    'Qty',
    'Amount'
];

$summarySheet->fromArray($summaryHeaders, null, 'A' . $summaryHeaderRow);
$summarySheet->getStyle("A{$summaryHeaderRow}:G{$summaryHeaderRow}")->applyFromArray($headerStyle);
$summarySheet->getStyle("A{$summaryHeaderRow}:G{$summaryHeaderRow}")
    ->getAlignment()
    ->setWrapText(true)
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$currentRow = $summaryDataRow;
$counter = 1;
foreach ($dateTotals as $saleDate => $totals) {
    $summarySheet->setCellValue("A{$currentRow}", $counter);
    $summarySheet->setCellValue("B{$currentRow}", $saleDate);
    $summarySheet->setCellValue("C{$currentRow}", $totals['transactions']);
    $summarySheet->setCellValue("D{$currentRow}", $totals['lineitem_discount']);
    $summarySheet->setCellValue("E{$currentRow}", $totals['discount']);
    $summarySheet->setCellValue("F{$currentRow}", $totals['qty']);
    $summarySheet->setCellValue("G{$currentRow}", $totals['amount']);
    $summarySheet->getStyle("D{$currentRow}:E{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $summarySheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode($numberFormat);
    $summarySheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
    $currentRow++;
    $counter++;
}

$summarySheet->setCellValue("B{$currentRow}", 'Total');
$summarySheet->setCellValue("C{$currentRow}", $grandTotals['transactions']);
$summarySheet->setCellValue("D{$currentRow}", $grandTotals['lineitem_discount']);
$summarySheet->setCellValue("E{$currentRow}", $grandTotals['discount']);
$summarySheet->setCellValue("F{$currentRow}", $grandTotals['qty']);
$summarySheet->setCellValue("G{$currentRow}", $grandTotals['amount']);
$summarySheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray($headerStyle);
$summarySheet->getStyle("A{$currentRow}:G{$currentRow}")->getFont()->setBold(true);
$summarySheet->getStyle("D{$currentRow}:E{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);
$summarySheet->getStyle("F{$currentRow}")->getNumberFormat()->setFormatCode($numberFormat);
$summarySheet->getStyle("G{$currentRow}")->getNumberFormat()->setFormatCode($currencyFormat);

if ($currentRow >= $summaryHeaderRow) {
    $summarySheet->getStyle("A{$summaryHeaderRow}:G{$currentRow}")->applyFromArray($borderStyle);
}

foreach (range('A', 'G') as $column) {
    $summarySheet->getColumnDimension($column)->setAutoSize(true);
}

$productsByType = fetchIceProducts($mysqli);
$salesByDateProduct = [];
foreach ($salesRows as $row) {
    $saleDate = $row['sale_date'];
    $productCode = $row['product_code'];
    if ($saleDate === '' || $productCode === '') {
        continue;
    }
    if (!isset($salesByDateProduct[$saleDate])) {
        $salesByDateProduct[$saleDate] = [];
    }
    if (!isset($salesByDateProduct[$saleDate][$productCode])) {
        $salesByDateProduct[$saleDate][$productCode] = ['qty' => 0, 'amount' => 0];
    }
    $salesByDateProduct[$saleDate][$productCode]['qty'] += $row['qty'];
    $salesByDateProduct[$saleDate][$productCode]['amount'] += $row['amount'];
}

$totalTransactionsByDate = fetchTotalTransactionsByDate($mysqli, $startDate, $endDate);
$dateKeys = array_unique(array_merge(array_keys($totalTransactionsByDate), array_keys($salesByDateProduct)));
sort($dateKeys);

$thirdSheet = $spreadsheet->createSheet();
$thirdSheet->setTitle('Total Sales by Product');

$thirdSheet->setCellValue('A1', 'Total Sales by Product');
$thirdSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$thirdSheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
$thirdSheet->setCellValue('A3', 'Generated: ' . $generatedAt);

$thirdHeaderTop = 7;
$thirdHeaderCode = 8;
$thirdHeaderDesc = 9;
$thirdHeaderSub = 10;
$thirdDataRow = 11;

$thirdSheet->setCellValue("A{$thirdHeaderTop}", 'Date');
$thirdSheet->mergeCells("A{$thirdHeaderTop}:A{$thirdHeaderSub}");
$thirdSheet->setCellValue("B{$thirdHeaderTop}", 'Total Transaction');
$thirdSheet->mergeCells("B{$thirdHeaderTop}:B{$thirdHeaderSub}");

$colIndex = 3;
$columnMeta = [
    'products' => [],
    'typeSubtotals' => [],
    'totalAmount' => null
];
$qtyColumns = [];
$amountColumns = [];

foreach ($productsByType as $productType => $products) {
    $groupStartCol = $colIndex;
    foreach ($products as $product) {
        $startCol = $colIndex;
        $endCol = $colIndex + 1;
        $startLetter = Coordinate::stringFromColumnIndex($startCol);
        $endLetter = Coordinate::stringFromColumnIndex($endCol);
        $label = $product['code'];
        $thirdSheet->setCellValue($startLetter . $thirdHeaderCode, $label);
        $thirdSheet->mergeCells($startLetter . $thirdHeaderCode . ':' . $endLetter . $thirdHeaderCode);
        $thirdSheet->setCellValue($startLetter . $thirdHeaderDesc, $product['description']);
        $thirdSheet->mergeCells($startLetter . $thirdHeaderDesc . ':' . $endLetter . $thirdHeaderDesc);
        $thirdSheet->setCellValue($startLetter . $thirdHeaderSub, 'Qty');
        $thirdSheet->setCellValue($endLetter . $thirdHeaderSub, 'Amount');
        $columnMeta['products'][$product['code']] = [
            'qty' => $startCol,
            'amount' => $endCol,
            'type' => $productType
        ];
        $qtyColumns[] = $startCol;
        $amountColumns[] = $endCol;
        $colIndex += 2;
    }

    $subtotalStart = $colIndex;
    $subtotalEnd = $colIndex + 1;
    $subtotalStartLetter = Coordinate::stringFromColumnIndex($subtotalStart);
    $subtotalEndLetter = Coordinate::stringFromColumnIndex($subtotalEnd);
    $thirdSheet->setCellValue($subtotalStartLetter . $thirdHeaderCode, 'Subtotal');
    $thirdSheet->mergeCells($subtotalStartLetter . $thirdHeaderCode . ':' . $subtotalEndLetter . $thirdHeaderCode);
    $thirdSheet->mergeCells($subtotalStartLetter . $thirdHeaderDesc . ':' . $subtotalEndLetter . $thirdHeaderDesc);
    $thirdSheet->setCellValue($subtotalStartLetter . $thirdHeaderSub, 'Qty');
    $thirdSheet->setCellValue($subtotalEndLetter . $thirdHeaderSub, 'Amount');
    $columnMeta['typeSubtotals'][$productType] = [
        'qty' => $subtotalStart,
        'amount' => $subtotalEnd
    ];
    $qtyColumns[] = $subtotalStart;
    $amountColumns[] = $subtotalEnd;
    $colIndex += 2;

    $groupStartLetter = Coordinate::stringFromColumnIndex($groupStartCol);
    $groupEndLetter = Coordinate::stringFromColumnIndex($subtotalEnd);
    $thirdSheet->setCellValue($groupStartLetter . $thirdHeaderTop, $productType);
    $thirdSheet->mergeCells($groupStartLetter . $thirdHeaderTop . ':' . $groupEndLetter . $thirdHeaderTop);
}

$totalAmountCol = $colIndex;
$totalAmountLetter = Coordinate::stringFromColumnIndex($totalAmountCol);
$thirdSheet->setCellValue($totalAmountLetter . $thirdHeaderTop, 'Total Amount');
$thirdSheet->mergeCells($totalAmountLetter . $thirdHeaderTop . ':' . $totalAmountLetter . $thirdHeaderSub);
$columnMeta['totalAmount'] = $totalAmountCol;
$amountColumns[] = $totalAmountCol;

$lastHeaderLetter = Coordinate::stringFromColumnIndex($totalAmountCol);
$thirdSheet->getStyle("A{$thirdHeaderTop}:{$lastHeaderLetter}{$thirdHeaderSub}")->applyFromArray($headerStyle);
$thirdSheet->getStyle("A{$thirdHeaderTop}:{$lastHeaderLetter}{$thirdHeaderSub}")
    ->getAlignment()
    ->setWrapText(true)
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$grandTotalsByProduct = [];
$grandTotalsByType = [];
$grandTotalAmount = 0;
$grandTotalTransactions = 0;

$currentRow = $thirdDataRow;
foreach ($dateKeys as $saleDate) {
    $thirdSheet->setCellValue("A{$currentRow}", $saleDate);
    $totalTransactions = isset($totalTransactionsByDate[$saleDate]) ? $totalTransactionsByDate[$saleDate] : 0;
    $thirdSheet->setCellValue("B{$currentRow}", $totalTransactions);
    $grandTotalTransactions += $totalTransactions;

    $totalAmountForDate = 0;
    foreach ($productsByType as $productType => $products) {
        $typeQty = 0;
        $typeAmount = 0;
        foreach ($products as $product) {
            $code = $product['code'];
            $qty = 0;
            $amount = 0;
            if (isset($salesByDateProduct[$saleDate][$code])) {
                $qty = $salesByDateProduct[$saleDate][$code]['qty'];
                $amount = $salesByDateProduct[$saleDate][$code]['amount'];
            }
            $qtyCol = $columnMeta['products'][$code]['qty'];
            $amountCol = $columnMeta['products'][$code]['amount'];
            $thirdSheet->setCellValue(Coordinate::stringFromColumnIndex($qtyCol) . $currentRow, $qty);
            $thirdSheet->setCellValue(Coordinate::stringFromColumnIndex($amountCol) . $currentRow, $amount);

            if (!isset($grandTotalsByProduct[$code])) {
                $grandTotalsByProduct[$code] = ['qty' => 0, 'amount' => 0];
            }
            $grandTotalsByProduct[$code]['qty'] += $qty;
            $grandTotalsByProduct[$code]['amount'] += $amount;

            $typeQty += $qty;
            $typeAmount += $amount;
            $totalAmountForDate += $amount;
        }

        $subtotalCols = $columnMeta['typeSubtotals'][$productType];
        $thirdSheet->setCellValue(Coordinate::stringFromColumnIndex($subtotalCols['qty']) . $currentRow, $typeQty);
        $thirdSheet->setCellValue(Coordinate::stringFromColumnIndex($subtotalCols['amount']) . $currentRow, $typeAmount);

        if (!isset($grandTotalsByType[$productType])) {
            $grandTotalsByType[$productType] = ['qty' => 0, 'amount' => 0];
        }
        $grandTotalsByType[$productType]['qty'] += $typeQty;
        $grandTotalsByType[$productType]['amount'] += $typeAmount;
    }

    $thirdSheet->setCellValue($totalAmountLetter . $currentRow, $totalAmountForDate);
    $grandTotalAmount += $totalAmountForDate;

    $currentRow++;
}

$thirdSheet->setCellValue("A{$currentRow}", 'Total');
$thirdSheet->setCellValue("B{$currentRow}", $grandTotalTransactions);
foreach ($productsByType as $productType => $products) {
    foreach ($products as $product) {
        $code = $product['code'];
        $totals = isset($grandTotalsByProduct[$code]) ? $grandTotalsByProduct[$code] : ['qty' => 0, 'amount' => 0];
        $qtyCol = $columnMeta['products'][$code]['qty'];
        $amountCol = $columnMeta['products'][$code]['amount'];
        $thirdSheet->setCellValue(Coordinate::stringFromColumnIndex($qtyCol) . $currentRow, $totals['qty']);
        $thirdSheet->setCellValue(Coordinate::stringFromColumnIndex($amountCol) . $currentRow, $totals['amount']);
    }
    $typeTotals = isset($grandTotalsByType[$productType]) ? $grandTotalsByType[$productType] : ['qty' => 0, 'amount' => 0];
    $subtotalCols = $columnMeta['typeSubtotals'][$productType];
    $thirdSheet->setCellValue(Coordinate::stringFromColumnIndex($subtotalCols['qty']) . $currentRow, $typeTotals['qty']);
    $thirdSheet->setCellValue(Coordinate::stringFromColumnIndex($subtotalCols['amount']) . $currentRow, $typeTotals['amount']);
}
$thirdSheet->setCellValue($totalAmountLetter . $currentRow, $grandTotalAmount);
$thirdSheet->getStyle("A{$currentRow}:{$lastHeaderLetter}{$currentRow}")->applyFromArray($headerStyle);
$thirdSheet->getStyle("A{$currentRow}:{$lastHeaderLetter}{$currentRow}")->getFont()->setBold(true);

if ($currentRow >= $thirdHeaderTop) {
    $thirdSheet->getStyle("A{$thirdHeaderTop}:{$lastHeaderLetter}{$currentRow}")->applyFromArray($borderStyle);
}

foreach ($qtyColumns as $qtyCol) {
    $colLetter = Coordinate::stringFromColumnIndex($qtyCol);
    $thirdSheet->getStyle("{$colLetter}{$thirdDataRow}:{$colLetter}{$currentRow}")
        ->getNumberFormat()
        ->setFormatCode($numberFormat);
}
foreach ($amountColumns as $amountCol) {
    $colLetter = Coordinate::stringFromColumnIndex($amountCol);
    $thirdSheet->getStyle("{$colLetter}{$thirdDataRow}:{$colLetter}{$currentRow}")
        ->getNumberFormat()
        ->setFormatCode($currencyFormat);
}

$thirdSheet->getColumnDimension('A')->setAutoSize(true);
$thirdSheet->getColumnDimension('B')->setAutoSize(true);
for ($i = 3; $i <= $totalAmountCol; $i++) {
    $thirdSheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
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
