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
require_once __DIR__ . '/production_vs_sales_common.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/report_export_runtime.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

list($startDate, $endDate) = prodSalesResolveDateRange(
    isset($_GET['start']) ? $_GET['start'] : '',
    isset($_GET['end']) ? $_GET['end'] : ''
);
$prodSalesClass = prodSalesNormalizeClass(isset($_GET['prod_sales_class']) ? $_GET['prod_sales_class'] : '');

if (!isset($mysqli) || !$mysqli) {
    http_response_code(500);
    echo 'Database connection unavailable.';
    exit;
}
if (!prodSalesEnsureBeginningInventoryTable($mysqli)) {
    http_response_code(500);
    echo 'Beginning inventory table unavailable.';
    exit;
}

$openingInventory = prodSalesCalculateOpeningInventory($mysqli, $prodSalesClass, $startDate);
$openingInventoryQty = isset($openingInventory['qty_kg']) ? (float)$openingInventory['qty_kg'] : 0.0;
$openingBalanceDate = prodSalesResolvePreviousDate($startDate);
$openingBalanceDateLabel = prodSalesFormatShortDateLabel($openingBalanceDate);
$productionRows = prodSalesFetchProductionRows($mysqli, $startDate, $endDate, $prodSalesClass);
$machineColumns = prodSalesFetchMachineColumns($mysqli, $productionRows, $prodSalesClass);
$productionMatrixRows = prodSalesBuildProductionMatrix($productionRows, $startDate, $endDate, $machineColumns);
$salesRows = prodSalesFetchSalesRows($mysqli, $startDate, $endDate, $prodSalesClass);
$salesProductColumns = prodSalesFetchSalesProductColumns($mysqli, $prodSalesClass, $salesRows);
$salesProductGroups = prodSalesBuildSalesProductGroups($salesProductColumns);
$salesMatrixRows = prodSalesBuildSalesMatrix($salesRows, $startDate, $endDate, $salesProductColumns);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Prod Sales Analysis');

$measureColumnCount = count($machineColumns) * 2 + 1;
$productionFirstColumnIndex = 2;
$productionLastColumnIndex = $productionFirstColumnIndex + $measureColumnCount - 1;
$productionLastColumn = Coordinate::stringFromColumnIndex($productionLastColumnIndex);
$salesColumnCount = count($salesProductColumns) + count($salesProductGroups) + 2;
$salesFirstColumnIndex = $productionLastColumnIndex + 1;
$salesLastColumnIndex = $salesFirstColumnIndex + $salesColumnCount - 1;
$salesFirstColumn = Coordinate::stringFromColumnIndex($salesFirstColumnIndex);
$salesLastColumn = Coordinate::stringFromColumnIndex($salesLastColumnIndex);
$lastColumnIndex = $salesLastColumnIndex;
$lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex);
$decimalFormat = '#,##0.00';
$salesMatrixByDate = [];
foreach ($salesMatrixRows as $salesMatrixRow) {
    $salesDateKey = isset($salesMatrixRow['date']) ? (string)$salesMatrixRow['date'] : '';
    if ($salesDateKey !== '') {
        $salesMatrixByDate[$salesDateKey] = $salesMatrixRow;
    }
}

$sheet->setCellValue('A1', 'DAILY PROD SALES ANALYSIS - BY PRODUCT CLASS');
$sheet->setCellValue('A2', prodSalesFormatHeading($startDate, $endDate));
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);

if ($lastColumn !== 'A') {
    $sheet->mergeCells("A1:{$lastColumn}1");
    $sheet->mergeCells("A2:{$lastColumn}2");
}

$sheet->mergeCells('A5:A9');
$sheet->mergeCells("B5:{$productionLastColumn}5");
$sheet->mergeCells("B6:{$productionLastColumn}6");
$sheet->mergeCells("B7:{$productionLastColumn}7");
$sheet->setCellValue('B5', 'IN (IN KG)');
$sheet->setCellValue('B6', 'PRODUCTION');
$sheet->setCellValue('B7', $prodSalesClass);
$sheet->setCellValue('A5', 'DATE');

$currentColumnIndex = 2;
foreach ($machineColumns as $machineColumn) {
    $startColumn = Coordinate::stringFromColumnIndex($currentColumnIndex);
    $endColumn = Coordinate::stringFromColumnIndex($currentColumnIndex + 1);
    $sheet->mergeCells("{$startColumn}8:{$endColumn}8");
    $sheet->setCellValue("{$startColumn}8", (string)$machineColumn['machine_label']);
    $sheet->setCellValue("{$startColumn}9", 'HRS');
    $sheet->setCellValue("{$endColumn}9", 'QTY');
    $currentColumnIndex += 2;
}

$totalColumn = Coordinate::stringFromColumnIndex($currentColumnIndex);
$sheet->mergeCells("{$totalColumn}8:{$totalColumn}9");
$sheet->setCellValue("{$totalColumn}8", 'TOTAL');

$salesProductColumnMap = [];
$salesGroupSubtotalColumns = [];
$salesTotalColumn = '';
$inventoryVarianceColumn = '';
$sheet->mergeCells("{$salesFirstColumn}5:{$salesLastColumn}5");
$sheet->setCellValue("{$salesFirstColumn}5", 'SALES');
$sheet->mergeCells("{$salesFirstColumn}6:{$salesLastColumn}6");
$sheet->setCellValue("{$salesFirstColumn}6", $prodSalesClass);

$salesCurrentColumnIndex = $salesFirstColumnIndex;
foreach ($salesProductGroups as $salesGroup) {
    $groupStartColumnIndex = $salesCurrentColumnIndex;
    foreach ($salesGroup['products'] as $salesProduct) {
        $productColumn = Coordinate::stringFromColumnIndex($salesCurrentColumnIndex);
        $sheet->setCellValueExplicit("{$productColumn}8", (string)$salesProduct['product_code'], DataType::TYPE_STRING);
        $sheet->setCellValue("{$productColumn}9", prodSalesFormatSalesProductHeader($salesProduct));
        $salesProductColumnMap[$salesProduct['key']] = $productColumn;
        $salesCurrentColumnIndex++;
    }

    $groupEndColumnIndex = $salesCurrentColumnIndex - 1;
    if ($groupEndColumnIndex >= $groupStartColumnIndex) {
        $groupStartColumn = Coordinate::stringFromColumnIndex($groupStartColumnIndex);
        $groupEndColumn = Coordinate::stringFromColumnIndex($groupEndColumnIndex);
        if ($groupStartColumn !== $groupEndColumn) {
            $sheet->mergeCells("{$groupStartColumn}7:{$groupEndColumn}7");
        }
        $sheet->setCellValue("{$groupStartColumn}7", (string)$salesGroup['label']);
    }

    $subtotalColumn = Coordinate::stringFromColumnIndex($salesCurrentColumnIndex);
    $sheet->mergeCells("{$subtotalColumn}8:{$subtotalColumn}9");
    $sheet->setCellValue("{$subtotalColumn}8", 'SUB-TOTAL');
    $salesGroupSubtotalColumns[] = [
        'column' => $subtotalColumn,
        'product_start_column' => Coordinate::stringFromColumnIndex($groupStartColumnIndex),
        'product_end_column' => Coordinate::stringFromColumnIndex($groupEndColumnIndex)
    ];
    $salesCurrentColumnIndex++;
}

$salesTotalColumn = Coordinate::stringFromColumnIndex($salesCurrentColumnIndex);
$sheet->mergeCells("{$salesTotalColumn}8:{$salesTotalColumn}9");
$sheet->setCellValue("{$salesTotalColumn}8", 'TOTAL');

$salesCurrentColumnIndex++;
$inventoryVarianceColumn = Coordinate::stringFromColumnIndex($salesCurrentColumnIndex);
$sheet->mergeCells("{$inventoryVarianceColumn}8:{$inventoryVarianceColumn}9");
$sheet->setCellValue("{$inventoryVarianceColumn}8", 'STORAGE BALANCE');

$currentRow = 10;
$sheet->setCellValueExplicit("A{$currentRow}", $openingBalanceDateLabel . ' BEGINNING BALANCE', DataType::TYPE_STRING);
if ($inventoryVarianceColumn !== '') {
    $sheet->setCellValue("{$inventoryVarianceColumn}{$currentRow}", $openingInventoryQty);
}
$previousStorageBalanceCell = "{$inventoryVarianceColumn}{$currentRow}";
$currentRow++;
$detailStartRow = $currentRow;
foreach ($productionMatrixRows as $matrixRow) {
    $sheet->setCellValueExplicit("A{$currentRow}", (string)$matrixRow['date_label'], DataType::TYPE_STRING);
    $currentColumnIndex = 2;
    $dailyTotal = 0.0;
    foreach ($machineColumns as $machineColumn) {
        $hoursColumn = Coordinate::stringFromColumnIndex($currentColumnIndex);
        $qtyColumn = Coordinate::stringFromColumnIndex($currentColumnIndex + 1);
        $cell = isset($matrixRow['machines'][$machineColumn['key']]) ? $matrixRow['machines'][$machineColumn['key']] : ['hours' => 0.0, 'qty' => 0.0];

        if (abs((float)$cell['hours']) >= 0.005) {
            $sheet->setCellValue("{$hoursColumn}{$currentRow}", (float)$cell['hours']);
        }
        if (abs((float)$cell['qty']) >= 0.005) {
            $sheet->setCellValue("{$qtyColumn}{$currentRow}", (float)$cell['qty']);
        }

        $dailyTotal += isset($cell['qty']) ? (float)$cell['qty'] : 0.0;
        $currentColumnIndex += 2;
    }
    if (abs($dailyTotal) >= 0.005) {
        $sheet->setCellValue("{$totalColumn}{$currentRow}", $dailyTotal);
    }

    $salesMatrixRow = isset($salesMatrixByDate[$matrixRow['date']]) ? $salesMatrixByDate[$matrixRow['date']] : ['products' => []];
    foreach ($salesProductColumns as $salesProduct) {
        $productColumn = isset($salesProductColumnMap[$salesProduct['key']]) ? $salesProductColumnMap[$salesProduct['key']] : '';
        if ($productColumn === '') {
            continue;
        }
        $qty = isset($salesMatrixRow['products'][$salesProduct['key']]) ? (float)$salesMatrixRow['products'][$salesProduct['key']] : 0.0;
        if (abs($qty) >= 0.005) {
            $sheet->setCellValue("{$productColumn}{$currentRow}", $qty);
        }
    }

    $totalFormulaParts = [];
    foreach ($salesGroupSubtotalColumns as $subtotalMeta) {
        $subtotalColumn = $subtotalMeta['column'];
        $sheet->setCellValue(
            "{$subtotalColumn}{$currentRow}",
            "=SUM({$subtotalMeta['product_start_column']}{$currentRow}:{$subtotalMeta['product_end_column']}{$currentRow})"
        );
        $totalFormulaParts[] = "{$subtotalColumn}{$currentRow}";
    }
    if ($salesTotalColumn !== '') {
        if (!empty($totalFormulaParts)) {
            $sheet->setCellValue("{$salesTotalColumn}{$currentRow}", '=SUM(' . implode(',', $totalFormulaParts) . ')');
        } else {
            $sheet->setCellValue("{$salesTotalColumn}{$currentRow}", 0);
        }
    }
    if ($inventoryVarianceColumn !== '' && $salesTotalColumn !== '') {
        $sheet->setCellValue("{$inventoryVarianceColumn}{$currentRow}", "={$previousStorageBalanceCell}+{$totalColumn}{$currentRow}-{$salesTotalColumn}{$currentRow}");
        $previousStorageBalanceCell = "{$inventoryVarianceColumn}{$currentRow}";
    }
    $currentRow++;
}

$lastDetailRow = max($detailStartRow, $currentRow - 1);
$totalRow = $currentRow;
$sheet->setCellValue("A{$totalRow}", 'TOTAL');

foreach ($machineColumns as $machineColumnIndex => $machineColumn) {
    $hoursColumn = Coordinate::stringFromColumnIndex(2 + ($machineColumnIndex * 2));
    $qtyColumn = Coordinate::stringFromColumnIndex(3 + ($machineColumnIndex * 2));
    $sheet->setCellValue("{$hoursColumn}{$totalRow}", "=SUM({$hoursColumn}{$detailStartRow}:{$hoursColumn}{$lastDetailRow})");
    $sheet->setCellValue("{$qtyColumn}{$totalRow}", "=SUM({$qtyColumn}{$detailStartRow}:{$qtyColumn}{$lastDetailRow})");
}
$sheet->setCellValue("{$totalColumn}{$totalRow}", "=SUM({$totalColumn}{$detailStartRow}:{$totalColumn}{$lastDetailRow})");

foreach ($salesProductColumns as $salesProduct) {
    $productColumn = isset($salesProductColumnMap[$salesProduct['key']]) ? $salesProductColumnMap[$salesProduct['key']] : '';
    if ($productColumn === '') {
        continue;
    }
    $sheet->setCellValue("{$productColumn}{$totalRow}", "=SUM({$productColumn}{$detailStartRow}:{$productColumn}{$lastDetailRow})");
}

$totalFormulaParts = [];
foreach ($salesGroupSubtotalColumns as $subtotalMeta) {
    $subtotalColumn = $subtotalMeta['column'];
    $sheet->setCellValue(
        "{$subtotalColumn}{$totalRow}",
        "=SUM({$subtotalMeta['product_start_column']}{$totalRow}:{$subtotalMeta['product_end_column']}{$totalRow})"
    );
    $totalFormulaParts[] = "{$subtotalColumn}{$totalRow}";
}
if ($salesTotalColumn !== '') {
    if (!empty($totalFormulaParts)) {
        $sheet->setCellValue("{$salesTotalColumn}{$totalRow}", '=SUM(' . implode(',', $totalFormulaParts) . ')');
    } else {
        $sheet->setCellValue("{$salesTotalColumn}{$totalRow}", "=SUM({$salesTotalColumn}{$detailStartRow}:{$salesTotalColumn}{$lastDetailRow})");
    }
}
if ($inventoryVarianceColumn !== '' && $salesTotalColumn !== '') {
    $openingBalanceCell = "{$inventoryVarianceColumn}10";
    $sheet->setCellValue("{$inventoryVarianceColumn}{$totalRow}", "={$openingBalanceCell}+{$totalColumn}{$totalRow}-{$salesTotalColumn}{$totalRow}");
}

$lastDataRow = $totalRow;

$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

$sheet->getStyle("A5:{$lastColumn}{$lastDataRow}")->applyFromArray($borderStyle);
$sheet->getStyle("A5:{$lastColumn}9")->getFont()->setBold(true);
$sheet->getStyle("A5:{$lastColumn}9")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);
$sheet->getStyle("A10:A{$lastDataRow}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_LEFT)
    ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle("B10:{$lastColumn}{$lastDataRow}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
    ->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle("B10:{$lastColumn}{$lastDataRow}")->getNumberFormat()->setFormatCode($decimalFormat);
$sheet->getStyle("A10:{$lastColumn}10")->getFont()->setBold(true);
$sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->getFont()->setBold(true);

$sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(14);
for ($columnIndex = 2; $columnIndex <= $lastColumnIndex; $columnIndex++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(false)->setWidth(18);
}
$sheet->getRowDimension(8)->setRowHeight(30);
$sheet->getRowDimension(9)->setRowHeight(48);

$sheet->freezePane('B10');

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'production_vs_sales_' . strtolower($prodSalesClass) . '_' . $startDate . '_to_' . $endDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->setUseDiskCaching(true, getSpreadsheetExportTempDirectory());
$writer->save('php://output');
exit;
