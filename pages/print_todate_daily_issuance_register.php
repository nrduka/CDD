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
require_once __DIR__ . '/daily_issuance_register_common.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/report_export_runtime.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

list($startDate, $endDate) = validateDailyIssuanceDateRange(
    isset($_GET['start']) ? $_GET['start'] : '',
    isset($_GET['end']) ? $_GET['end'] : ''
);

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection unavailable.';
    exit;
}

$generatedAt = date('Y-m-d H:i:s');
$rangeLabel = formatDailyIssuanceRangeLabel($startDate, $endDate);
$reportData = fetchDailyIssuanceRegisterData($mysqli, $startDate, $endDate);
$dateColumns = isset($reportData['date_columns']) ? $reportData['date_columns'] : [];
$reportColumns = isset($reportData['report_columns']) ? $reportData['report_columns'] : buildDailyIssuanceReportColumns($dateColumns);
$lastColumnIndex = 5 + count($reportColumns);
$lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Issuance');

$sheet->setCellValue('A1', 'DAILY ISSUANCE REGISTER');
$sheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
$sheet->setCellValue('A3', 'Generated: ' . $generatedAt);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'f3f4f6']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ]
];
$groupStyle = [
    'font' => ['bold' => true, 'size' => 13],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'ffffff']
    ]
];
$sourceStyle = [
    'font' => ['bold' => true, 'size' => 13],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'eef2ff']
    ]
];
$subtotalStyle = [
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'f3f4f6']
    ]
];
$productSubtotalStyle = [
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'fafafa']
    ]
];
$totalStyle = [
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
$decimalFormat = '#,##0.00';

function buildDailyIssuanceSumFormula($cells)
{
    $cells = array_values(array_filter($cells, function ($cell) {
        return trim((string)$cell) !== '';
    }));

    if (empty($cells)) {
        return 0;
    }

    return '=SUM(' . implode(',', $cells) . ')';
}

function buildDailyIssuanceRangeFormula($columnLetter, $rowNumbers)
{
    $rowNumbers = array_values(array_unique(array_map('intval', $rowNumbers)));
    sort($rowNumbers, SORT_NUMERIC);

    if (empty($rowNumbers)) {
        return 0;
    }

    $ranges = [];
    $rangeStart = $rowNumbers[0];
    $previous = $rowNumbers[0];
    $count = count($rowNumbers);
    for ($index = 1; $index < $count; $index++) {
        $rowNumber = $rowNumbers[$index];
        if ($rowNumber === $previous + 1) {
            $previous = $rowNumber;
            continue;
        }

        $ranges[] = $rangeStart === $previous
            ? $columnLetter . $rangeStart
            : $columnLetter . $rangeStart . ':' . $columnLetter . $previous;
        $rangeStart = $rowNumber;
        $previous = $rowNumber;
    }

    $ranges[] = $rangeStart === $previous
        ? $columnLetter . $rangeStart
        : $columnLetter . $rangeStart . ':' . $columnLetter . $previous;

    return '=SUM(' . implode(',', $ranges) . ')';
}

function buildDailyIssuanceRowFormula($rowNumber, $columnMeta, $dateColumnByKey)
{
    $type = isset($columnMeta['type']) ? (string)$columnMeta['type'] : '';
    if ($type === 'weekly_subtotal') {
        $cells = [];
        $dateKeys = isset($columnMeta['date_keys']) && is_array($columnMeta['date_keys']) ? $columnMeta['date_keys'] : [];
        foreach ($dateKeys as $dateKey) {
            if (isset($dateColumnByKey[$dateKey])) {
                $cells[] = $dateColumnByKey[$dateKey] . $rowNumber;
            }
        }
        return buildDailyIssuanceSumFormula($cells);
    }

    if ($type === 'month_total') {
        $cells = [];
        foreach ($dateColumnByKey as $columnLetter) {
            $cells[] = $columnLetter . $rowNumber;
        }
        return buildDailyIssuanceSumFormula($cells);
    }

    return null;
}

function setDailyIssuanceSummaryFormulas($sheet, $summaryRow, $sourceRows, $dynamicColumns)
{
    foreach ($dynamicColumns as $columnData) {
        $columnLetter = $columnData['letter'];
        $sheet->setCellValue("{$columnLetter}{$summaryRow}", buildDailyIssuanceRangeFormula($columnLetter, $sourceRows));
    }
}

function setDailyIssuanceSummaryValues($sheet, $summaryRow, $summaryData, $dynamicColumns)
{
    foreach ($dynamicColumns as $columnData) {
        $columnLetter = $columnData['letter'];
        $columnMeta = $columnData['meta'];
        $qty = calculateDailyIssuanceColumnQty($summaryData, $columnMeta);
        if ($qty != 0.0) {
            $sheet->setCellValue("{$columnLetter}{$summaryRow}", $qty);
        }
    }
}

function styleDailyIssuanceAggregateColumns($sheet, $rowNumber, $dynamicColumns)
{
    foreach ($dynamicColumns as $columnData) {
        $columnMeta = $columnData['meta'];
        if (isset($columnMeta['type']) && $columnMeta['type'] !== 'date') {
            $columnLetter = $columnData['letter'];
            $sheet->getStyle("{$columnLetter}{$rowNumber}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB('f9fafb');
            $sheet->getStyle("{$columnLetter}{$rowNumber}")->getFont()->setBold(true);
        }
    }
}

$headerTopRow = 7;
$headerBottomRow = 8;
$dataStartRow = 9;

$sheet->mergeCells("A{$headerTopRow}:A{$headerBottomRow}");
$sheet->mergeCells("B{$headerTopRow}:B{$headerBottomRow}");
$sheet->mergeCells("C{$headerTopRow}:C{$headerBottomRow}");
$sheet->mergeCells("D{$headerTopRow}:D{$headerBottomRow}");
$sheet->mergeCells("E{$headerTopRow}:E{$headerBottomRow}");
$sheet->setCellValue("A{$headerTopRow}", 'NO');
$sheet->setCellValue("B{$headerTopRow}", 'ITEM CODE');
$sheet->setCellValue("C{$headerTopRow}", 'ITEM DESCRIPTION');
$sheet->setCellValue("D{$headerTopRow}", 'UNIT PRICE');
$sheet->setCellValue("E{$headerTopRow}", 'UOM');

$dynamicColumnIndex = 6;
$dynamicColumns = [];
$dateColumnByKey = [];
foreach ($reportColumns as $columnMeta) {
    $dynamicColumn = Coordinate::stringFromColumnIndex($dynamicColumnIndex);
    $dynamicColumns[] = [
        'letter' => $dynamicColumn,
        'meta' => $columnMeta
    ];
    if (isset($columnMeta['type']) && $columnMeta['type'] === 'date' && isset($columnMeta['date_key'])) {
        $dateColumnByKey[(string)$columnMeta['date_key']] = $dynamicColumn;
    }
    $sheet->setCellValue("{$dynamicColumn}{$headerTopRow}", isset($columnMeta['date_label']) ? (string)$columnMeta['date_label'] : '');
    $sheet->setCellValue("{$dynamicColumn}{$headerBottomRow}", isset($columnMeta['day_label']) ? (string)$columnMeta['day_label'] : '');
    if (isset($columnMeta['type']) && $columnMeta['type'] !== 'date') {
        $sheet->getStyle("{$dynamicColumn}{$headerTopRow}:{$dynamicColumn}{$headerBottomRow}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('e5e7eb');
    }
    $dynamicColumnIndex++;
}
$sheet->getStyle("A{$headerTopRow}:{$lastColumn}{$headerBottomRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$headerTopRow}:{$lastColumn}{$headerBottomRow}")->applyFromArray($borderStyle);

$currentRow = $dataStartRow;
$sourceRows = [];
$othersSourceRows = [];
$groupRows = [];
$productSubtotalRows = [];
$subtotalRows = [];
$othersSubtotalRows = [];
$othersSectionRow = null;
$sourceGroups = isset($reportData['source_groups']) ? $reportData['source_groups'] : [];
foreach ($sourceGroups as $sourceGroup) {
    $sourceRow = $currentRow;
    $sourceRows[] = $sourceRow;
    $sheet->mergeCells("A{$sourceRow}:{$lastColumn}{$sourceRow}");
    $sheet->setCellValue("A{$sourceRow}", (string)$sourceGroup['source_channel']);
    $sheet->getStyle("A{$sourceRow}:{$lastColumn}{$sourceRow}")->applyFromArray($sourceStyle);
    $currentRow++;

    foreach ($sourceGroup['product_groups'] as $group) {
        $groupRow = $currentRow;
        $groupRows[] = $groupRow;
        $sheet->mergeCells("A{$groupRow}:{$lastColumn}{$groupRow}");
        $sheet->setCellValue("A{$groupRow}", (string)$group['product_type']);
        $sheet->getStyle("A{$groupRow}:{$lastColumn}{$groupRow}")->applyFromArray($groupStyle);
        $currentRow++;
        $lineNumber = 1;
        $itemRows = [];
        foreach ($group['rows'] as $row) {
            $itemRows[] = $currentRow;
            $sheet->setCellValue("A{$currentRow}", $lineNumber);
            $sheet->setCellValueExplicit("B{$currentRow}", (string)$row['product_code'], DataType::TYPE_STRING);
            $sheet->setCellValue("C{$currentRow}", (string)$row['item_description']);
            $sheet->setCellValue("D{$currentRow}", (float)$row['unit_price']);
            $sheet->setCellValue("E{$currentRow}", (string)$row['uom']);

            foreach ($dynamicColumns as $columnData) {
                $dynamicColumn = $columnData['letter'];
                $columnMeta = $columnData['meta'];
                if (isset($columnMeta['type']) && $columnMeta['type'] === 'date') {
                    $qty = calculateDailyIssuanceColumnQty($row, $columnMeta);
                    if ($qty != 0.0) {
                        $sheet->setCellValue("{$dynamicColumn}{$currentRow}", $qty);
                    }
                } else {
                    $sheet->setCellValue("{$dynamicColumn}{$currentRow}", buildDailyIssuanceRowFormula($currentRow, $columnMeta, $dateColumnByKey));
                }
                if (isset($columnMeta['type']) && $columnMeta['type'] !== 'date') {
                    $sheet->getStyle("{$dynamicColumn}{$currentRow}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('f9fafb');
                    $sheet->getStyle("{$dynamicColumn}{$currentRow}")->getFont()->setBold(true);
                }
            }

            $currentRow++;
            $lineNumber++;
        }

        $productSubtotalRow = $currentRow;
        $productSubtotalRows[] = $productSubtotalRow;
        $sheet->setCellValue("C{$productSubtotalRow}", 'SUBTOTAL - ' . (string)$group['product_type']);
        setDailyIssuanceSummaryFormulas($sheet, $productSubtotalRow, $itemRows, $dynamicColumns);
        $sheet->getStyle("A{$productSubtotalRow}:{$lastColumn}{$productSubtotalRow}")->applyFromArray($productSubtotalStyle);
        $currentRow++;
    }

    $subtotalRow = $currentRow;
    $subtotalRows[] = $subtotalRow;
    $sheet->setCellValue("C{$subtotalRow}", 'TOTAL ' . (string)$sourceGroup['source_channel']);
    setDailyIssuanceSummaryFormulas($sheet, $subtotalRow, array_slice($productSubtotalRows, -count($sourceGroup['product_groups'])), $dynamicColumns);
    $sheet->getStyle("A{$subtotalRow}:{$lastColumn}{$subtotalRow}")->applyFromArray($subtotalStyle);
    $currentRow++;
}

$totalRow = $currentRow;
$sheet->setCellValue("C{$totalRow}", 'GRAND TOTAL');
setDailyIssuanceSummaryFormulas($sheet, $totalRow, $subtotalRows, $dynamicColumns);
$sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->applyFromArray($totalStyle);
$currentRow++;

$othersSourceGroups = isset($reportData['others_source_groups']) ? $reportData['others_source_groups'] : [];
if (!empty($othersSourceGroups)) {
    $othersSectionRow = $currentRow;
    $sheet->mergeCells("A{$othersSectionRow}:{$lastColumn}{$othersSectionRow}");
    $sheet->setCellValue("A{$othersSectionRow}", 'OTHERS');
    $sheet->getStyle("A{$othersSectionRow}:{$lastColumn}{$othersSectionRow}")->applyFromArray($sourceStyle);
    $currentRow++;

    foreach ($othersSourceGroups as $sourceGroup) {
        $sourceRow = $currentRow;
        $othersSourceRows[] = $sourceRow;
        $sheet->mergeCells("A{$sourceRow}:{$lastColumn}{$sourceRow}");
        $sheet->setCellValue("A{$sourceRow}", (string)$sourceGroup['source_channel']);
        $sheet->getStyle("A{$sourceRow}:{$lastColumn}{$sourceRow}")->applyFromArray($sourceStyle);
        $currentRow++;

        $sourceItemRows = [];
        foreach ($sourceGroup['product_groups'] as $group) {
            $lineNumber = 1;
            foreach ($group['rows'] as $row) {
                $sourceItemRows[] = $currentRow;
                $sheet->setCellValue("A{$currentRow}", $lineNumber);
                $sheet->setCellValueExplicit("B{$currentRow}", (string)$row['product_code'], DataType::TYPE_STRING);
                $sheet->setCellValue("C{$currentRow}", (string)$row['item_description']);
                $sheet->setCellValue("D{$currentRow}", (float)$row['unit_price']);
                $sheet->setCellValue("E{$currentRow}", (string)$row['uom']);

                foreach ($dynamicColumns as $columnData) {
                    $dynamicColumn = $columnData['letter'];
                    $columnMeta = $columnData['meta'];
                    if (isset($columnMeta['type']) && $columnMeta['type'] === 'date') {
                        $qty = calculateDailyIssuanceColumnQty($row, $columnMeta);
                        if ($qty != 0.0) {
                            $sheet->setCellValue("{$dynamicColumn}{$currentRow}", $qty);
                        }
                    } else {
                        $sheet->setCellValue("{$dynamicColumn}{$currentRow}", buildDailyIssuanceRowFormula($currentRow, $columnMeta, $dateColumnByKey));
                    }
                    if (isset($columnMeta['type']) && $columnMeta['type'] !== 'date') {
                        $sheet->getStyle("{$dynamicColumn}{$currentRow}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB('f9fafb');
                        $sheet->getStyle("{$dynamicColumn}{$currentRow}")->getFont()->setBold(true);
                    }
                }

                $currentRow++;
                $lineNumber++;
            }
        }

        $othersSubtotalRow = $currentRow;
        $othersSubtotalRows[] = $othersSubtotalRow;
        $sheet->setCellValue("C{$othersSubtotalRow}", 'TOTAL ' . (string)$sourceGroup['source_channel']);
        setDailyIssuanceSummaryFormulas($sheet, $othersSubtotalRow, $sourceItemRows, $dynamicColumns);
        $sheet->getStyle("A{$othersSubtotalRow}:{$lastColumn}{$othersSubtotalRow}")->applyFromArray($subtotalStyle);
        $currentRow++;
    }

    $totalOthersRow = $currentRow;
    $sheet->setCellValue("C{$totalOthersRow}", 'TOTAL OTHERS');
    setDailyIssuanceSummaryFormulas($sheet, $totalOthersRow, $othersSubtotalRows, $dynamicColumns);
    $sheet->getStyle("A{$totalOthersRow}:{$lastColumn}{$totalOthersRow}")->applyFromArray($totalStyle);
    $currentRow++;
} else {
    $totalOthersRow = null;
}

$lastDataRow = $currentRow - 1;
if ($lastDataRow >= $headerTopRow) {
    $sheet->getStyle("A{$headerTopRow}:{$lastColumn}{$lastDataRow}")->applyFromArray($borderStyle);
}
if ($lastDataRow >= $dataStartRow) {
    $sheet->getStyle("A{$dataStartRow}:A{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("B{$dataStartRow}:E{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("C{$dataStartRow}:C{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_LEFT)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("F{$dataStartRow}:{$lastColumn}{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("D{$dataStartRow}:D{$lastDataRow}")
        ->getNumberFormat()
        ->setFormatCode($decimalFormat);
    if (!empty($reportColumns)) {
        $sheet->getStyle("F{$dataStartRow}:{$lastColumn}{$lastDataRow}")
            ->getNumberFormat()
            ->setFormatCode($decimalFormat);
    }
    $sectionRows = array_merge($sourceRows, $othersSourceRows);
    if ($othersSectionRow !== null) {
        $sectionRows[] = $othersSectionRow;
    }
    foreach ($sectionRows as $sourceRow) {
        $sheet->getStyle("A{$sourceRow}:{$lastColumn}{$sourceRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }
    foreach ($groupRows as $groupRow) {
        $sheet->getStyle("A{$groupRow}:{$lastColumn}{$groupRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }
    $summaryRows = array_merge($productSubtotalRows, $subtotalRows, [$totalRow], $othersSubtotalRows);
    if ($totalOthersRow !== null) {
        $summaryRows[] = $totalOthersRow;
    }
    foreach ($summaryRows as $summaryRow) {
        $sheet->getStyle("F{$summaryRow}:{$lastColumn}{$summaryRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("F{$summaryRow}:{$lastColumn}{$summaryRow}")
            ->getNumberFormat()
            ->setFormatCode($decimalFormat);
    }
}

if (empty($sourceGroups) && empty($othersSourceGroups)) {
    $sheet->setCellValue('A6', 'No product records are available.');
}

$sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(6);
$sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(16);
$sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(58);
$sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(14);
$sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(10);
for ($index = 6; $index <= $lastColumnIndex; $index++) {
    $column = Coordinate::stringFromColumnIndex($index);
    $sheet->getColumnDimension($column)->setAutoSize(false)->setWidth($index === $lastColumnIndex ? 16 : 18);
}

$sheet->freezePane('F9');
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

$summarySheet = $spreadsheet->createSheet();
$summarySheet->setTitle('Daily Issuance Summary');

$summaryLastColumnIndex = 3 + count($reportColumns);
$summaryLastColumn = Coordinate::stringFromColumnIndex($summaryLastColumnIndex);

$summarySheet->setCellValue('A1', 'DAILY ISSUANCE SUMMARY');
$summarySheet->setCellValue('A2', 'Date Range: ' . $rangeLabel);
$summarySheet->setCellValue('A3', 'Generated: ' . $generatedAt);
$summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$summarySheet->getStyle('A1:A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$summaryHeaderTopRow = 7;
$summaryHeaderBottomRow = 8;
$summaryDataStartRow = 9;

$summarySheet->mergeCells("A{$summaryHeaderTopRow}:A{$summaryHeaderBottomRow}");
$summarySheet->mergeCells("B{$summaryHeaderTopRow}:B{$summaryHeaderBottomRow}");
$summarySheet->mergeCells("C{$summaryHeaderTopRow}:C{$summaryHeaderBottomRow}");
$summarySheet->setCellValue("A{$summaryHeaderTopRow}", 'NO');
$summarySheet->setCellValue("B{$summaryHeaderTopRow}", 'SOURCE CHANNEL');
$summarySheet->setCellValue("C{$summaryHeaderTopRow}", 'PRODUCT TYPE');

$summaryDynamicColumnIndex = 4;
$summaryDynamicColumns = [];
foreach ($reportColumns as $columnMeta) {
    $summaryDynamicColumn = Coordinate::stringFromColumnIndex($summaryDynamicColumnIndex);
    $summaryDynamicColumns[] = [
        'letter' => $summaryDynamicColumn,
        'meta' => $columnMeta
    ];
    $summarySheet->setCellValue("{$summaryDynamicColumn}{$summaryHeaderTopRow}", isset($columnMeta['date_label']) ? (string)$columnMeta['date_label'] : '');
    $summarySheet->setCellValue("{$summaryDynamicColumn}{$summaryHeaderBottomRow}", isset($columnMeta['day_label']) ? (string)$columnMeta['day_label'] : '');
    if (isset($columnMeta['type']) && $columnMeta['type'] !== 'date') {
        $summarySheet->getStyle("{$summaryDynamicColumn}{$summaryHeaderTopRow}:{$summaryDynamicColumn}{$summaryHeaderBottomRow}")
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('e5e7eb');
    }
    $summaryDynamicColumnIndex++;
}
$summarySheet->getStyle("A{$summaryHeaderTopRow}:{$summaryLastColumn}{$summaryHeaderBottomRow}")->applyFromArray($headerStyle);
$summarySheet->getStyle("A{$summaryHeaderTopRow}:{$summaryLastColumn}{$summaryHeaderBottomRow}")->applyFromArray($borderStyle);

$summaryCurrentRow = $summaryDataStartRow;
$summarySourceRows = [];
$summarySubtotalRows = [];
$summaryProductRows = [];
$summaryOthersSourceRows = [];
$summaryOthersSubtotalRows = [];
$summaryOthersSectionRow = null;

foreach ($sourceGroups as $sourceGroup) {
    $summarySourceRow = $summaryCurrentRow;
    $summarySourceRows[] = $summarySourceRow;
    $summarySheet->mergeCells("A{$summarySourceRow}:{$summaryLastColumn}{$summarySourceRow}");
    $summarySheet->setCellValue("A{$summarySourceRow}", (string)$sourceGroup['source_channel']);
    $summarySheet->getStyle("A{$summarySourceRow}:{$summaryLastColumn}{$summarySourceRow}")->applyFromArray($sourceStyle);
    $summaryCurrentRow++;

    $summaryLineNumber = 1;
    foreach ($sourceGroup['product_groups'] as $group) {
        $summaryProductRows[] = $summaryCurrentRow;
        $summarySheet->setCellValue("A{$summaryCurrentRow}", $summaryLineNumber);
        $summarySheet->setCellValue("B{$summaryCurrentRow}", (string)$sourceGroup['source_channel']);
        $summarySheet->setCellValue("C{$summaryCurrentRow}", (string)$group['product_type']);
        setDailyIssuanceSummaryValues($summarySheet, $summaryCurrentRow, $group['subtotal_row'], $summaryDynamicColumns);
        styleDailyIssuanceAggregateColumns($summarySheet, $summaryCurrentRow, $summaryDynamicColumns);
        $summaryCurrentRow++;
        $summaryLineNumber++;
    }

    $summarySubtotalRow = $summaryCurrentRow;
    $summarySubtotalRows[] = $summarySubtotalRow;
    $summarySheet->setCellValue("C{$summarySubtotalRow}", 'TOTAL ' . (string)$sourceGroup['source_channel']);
    setDailyIssuanceSummaryValues($summarySheet, $summarySubtotalRow, $sourceGroup['subtotal_row'], $summaryDynamicColumns);
    $summarySheet->getStyle("A{$summarySubtotalRow}:{$summaryLastColumn}{$summarySubtotalRow}")->applyFromArray($subtotalStyle);
    $summaryCurrentRow++;
}

$summaryTotalRow = $summaryCurrentRow;
$summarySheet->setCellValue("C{$summaryTotalRow}", 'TOTAL ALL SOURCE CHANNEL');
setDailyIssuanceSummaryValues($summarySheet, $summaryTotalRow, $reportData['total_row'], $summaryDynamicColumns);
$summarySheet->getStyle("A{$summaryTotalRow}:{$summaryLastColumn}{$summaryTotalRow}")->applyFromArray($totalStyle);
$summaryCurrentRow++;

if (!empty($othersSourceGroups)) {
    $summaryOthersSectionRow = $summaryCurrentRow;
    $summarySheet->mergeCells("A{$summaryOthersSectionRow}:{$summaryLastColumn}{$summaryOthersSectionRow}");
    $summarySheet->setCellValue("A{$summaryOthersSectionRow}", 'OTHERS');
    $summarySheet->getStyle("A{$summaryOthersSectionRow}:{$summaryLastColumn}{$summaryOthersSectionRow}")->applyFromArray($sourceStyle);
    $summaryCurrentRow++;

    foreach ($othersSourceGroups as $sourceGroup) {
        $summaryOthersSourceRow = $summaryCurrentRow;
        $summaryOthersSourceRows[] = $summaryOthersSourceRow;
        $summarySheet->mergeCells("A{$summaryOthersSourceRow}:{$summaryLastColumn}{$summaryOthersSourceRow}");
        $summarySheet->setCellValue("A{$summaryOthersSourceRow}", (string)$sourceGroup['source_channel']);
        $summarySheet->getStyle("A{$summaryOthersSourceRow}:{$summaryLastColumn}{$summaryOthersSourceRow}")->applyFromArray($sourceStyle);
        $summaryCurrentRow++;

        $summaryLineNumber = 1;
        foreach ($sourceGroup['product_groups'] as $group) {
            $summaryProductRows[] = $summaryCurrentRow;
            $summarySheet->setCellValue("A{$summaryCurrentRow}", $summaryLineNumber);
            $summarySheet->setCellValue("B{$summaryCurrentRow}", (string)$sourceGroup['source_channel']);
            $summarySheet->setCellValue("C{$summaryCurrentRow}", (string)$group['product_type']);
            setDailyIssuanceSummaryValues($summarySheet, $summaryCurrentRow, $group['subtotal_row'], $summaryDynamicColumns);
            styleDailyIssuanceAggregateColumns($summarySheet, $summaryCurrentRow, $summaryDynamicColumns);
            $summaryCurrentRow++;
            $summaryLineNumber++;
        }

        $summaryOthersSubtotalRow = $summaryCurrentRow;
        $summaryOthersSubtotalRows[] = $summaryOthersSubtotalRow;
        $summarySheet->setCellValue("C{$summaryOthersSubtotalRow}", 'TOTAL ' . (string)$sourceGroup['source_channel']);
        setDailyIssuanceSummaryValues($summarySheet, $summaryOthersSubtotalRow, $sourceGroup['subtotal_row'], $summaryDynamicColumns);
        $summarySheet->getStyle("A{$summaryOthersSubtotalRow}:{$summaryLastColumn}{$summaryOthersSubtotalRow}")->applyFromArray($subtotalStyle);
        $summaryCurrentRow++;
    }

    $summaryTotalOthersRow = $summaryCurrentRow;
    $summarySheet->setCellValue("C{$summaryTotalOthersRow}", 'TOTAL OTHERS');
    setDailyIssuanceSummaryValues($summarySheet, $summaryTotalOthersRow, $reportData['others_total_row'], $summaryDynamicColumns);
    $summarySheet->getStyle("A{$summaryTotalOthersRow}:{$summaryLastColumn}{$summaryTotalOthersRow}")->applyFromArray($totalStyle);
    $summaryCurrentRow++;
} else {
    $summaryTotalOthersRow = null;
}

$summaryLastDataRow = $summaryCurrentRow - 1;
if ($summaryLastDataRow >= $summaryHeaderTopRow) {
    $summarySheet->getStyle("A{$summaryHeaderTopRow}:{$summaryLastColumn}{$summaryLastDataRow}")->applyFromArray($borderStyle);
}
if ($summaryLastDataRow >= $summaryDataStartRow) {
    $summarySheet->getStyle("A{$summaryDataStartRow}:A{$summaryLastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $summarySheet->getStyle("B{$summaryDataStartRow}:C{$summaryLastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    if (!empty($reportColumns)) {
        $summarySheet->getStyle("D{$summaryDataStartRow}:{$summaryLastColumn}{$summaryLastDataRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $summarySheet->getStyle("D{$summaryDataStartRow}:{$summaryLastColumn}{$summaryLastDataRow}")
            ->getNumberFormat()
            ->setFormatCode($decimalFormat);
    }
    $summarySectionRows = array_merge($summarySourceRows, $summaryOthersSourceRows);
    if ($summaryOthersSectionRow !== null) {
        $summarySectionRows[] = $summaryOthersSectionRow;
    }
    foreach ($summarySectionRows as $summarySourceRow) {
        $summarySheet->getStyle("A{$summarySourceRow}:{$summaryLastColumn}{$summarySourceRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_LEFT)
            ->setVertical(Alignment::VERTICAL_CENTER);
    }
    $summaryTotalRows = array_merge($summarySubtotalRows, [$summaryTotalRow], $summaryOthersSubtotalRows);
    if ($summaryTotalOthersRow !== null) {
        $summaryTotalRows[] = $summaryTotalOthersRow;
    }
    foreach ($summaryTotalRows as $summaryRow) {
        if (!empty($reportColumns)) {
            $summarySheet->getStyle("D{$summaryRow}:{$summaryLastColumn}{$summaryRow}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $summarySheet->getStyle("D{$summaryRow}:{$summaryLastColumn}{$summaryRow}")
                ->getNumberFormat()
                ->setFormatCode($decimalFormat);
        }
    }
}

if (empty($sourceGroups) && empty($othersSourceGroups)) {
    $summarySheet->setCellValue('A6', 'No product records are available.');
}

$summarySheet->getColumnDimension('A')->setAutoSize(false)->setWidth(6);
$summarySheet->getColumnDimension('B')->setAutoSize(false)->setWidth(28);
$summarySheet->getColumnDimension('C')->setAutoSize(false)->setWidth(24);
for ($index = 4; $index <= $summaryLastColumnIndex; $index++) {
    $column = Coordinate::stringFromColumnIndex($index);
    $summarySheet->getColumnDimension($column)->setAutoSize(false)->setWidth($index === $summaryLastColumnIndex ? 16 : 18);
}

$summarySheet->freezePane('D9');
$summarySheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$summarySheet->getPageSetup()->setFitToWidth(1);
$summarySheet->getPageSetup()->setFitToHeight(0);

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'daily_issuance_register_' . $startDate . '_to_' . $endDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->setUseDiskCaching(true, getSpreadsheetExportTempDirectory());
$writer->save('php://output');
exit;
