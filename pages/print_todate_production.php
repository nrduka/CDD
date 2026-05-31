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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$today = date('Y-m-d');
$startInput = isset($_GET['start']) ? trim((string)$_GET['start']) : '';
$endInput = isset($_GET['end']) ? trim((string)$_GET['end']) : '';
$reportTypeInput = isset($_GET['report_type']) ? trim((string)$_GET['report_type']) : '';
$reportType = in_array($reportTypeInput, ['detailed', 'by_product_machine'], true) ? $reportTypeInput : 'detailed';

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

function resolveToDateProductionDateTimeLabelForExport($datetimeValue, $dateValue = '', $timeValue = '')
{
    $datetimeValue = trim((string)$datetimeValue);
    if ($datetimeValue !== '' && $datetimeValue !== '0000-00-00 00:00:00') {
        $datetimeObject = DateTime::createFromFormat('Y-m-d H:i:s', $datetimeValue);
        if ($datetimeObject instanceof DateTime) {
            return $datetimeObject->format('Y-m-d H:i:s');
        }

        return $datetimeValue;
    }

    $dateValue = trim((string)$dateValue);
    $timeValue = trim((string)$timeValue);
    if ($dateValue !== '' && $timeValue !== '' && $timeValue !== '00:00:00') {
        return $dateValue . ' ' . $timeValue;
    }

    return $dateValue;
}

function calculateToDateProductionHoursForExport($startedValue, $endedValue)
{
    $startedValue = trim((string)$startedValue);
    $endedValue = trim((string)$endedValue);
    if ($startedValue === '' || $endedValue === '') {
        return 0.0;
    }

    $startedAt = strtotime($startedValue);
    $endedAt = strtotime($endedValue);
    if ($startedAt === false || $endedAt === false || $endedAt < $startedAt) {
        return 0.0;
    }

    return ($endedAt - $startedAt) / 3600;
}

function formatToDateProductionMachineLabelForExport($machineDescription)
{
    $label = strtoupper(trim((string)$machineDescription));
    $label = preg_replace('/\s*\([^)]*\)\s*$/', '', $label);
    $label = preg_replace('/\s+MACHINE\b/', '', $label);
    $label = preg_replace('/\s+/', ' ', $label);

    return trim($label);
}

function fetchToDateProductionRowsForExport($mysqli, $startDate, $endDate)
{
    $rows = [];
    $sql = "SELECT ip.id,
                   ip.dpr_code,
                   ip.production_code,
                   ip.date_production,
                   ip.time_dropped,
                   ip.datetime_started,
                   ip.datetime_ended,
                   ip.machine,
                   ip.finished_ice_qty,
                   ip.loose_ice_qty,
                   ip.remarks,
                   im.machine_description,
                   im.product_type AS machine_product_type
            FROM ice_production ip
            LEFT JOIN ice_machine im ON im.id = ip.machine
            WHERE ip.date_production BETWEEN ? AND ?
            ORDER BY COALESCE(ip.datetime_started, ip.date_production), COALESCE(ip.time_dropped, '00:00:00'), im.machine_description, ip.id";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $machineId = isset($row['machine']) ? (string)$row['machine'] : '';
                $machineDescription = trim((string)(isset($row['machine_description']) ? $row['machine_description'] : ''));
                if ($machineDescription === '') {
                    $machineDescription = $machineId !== '' ? 'Machine ' . $machineId : 'Unknown Machine';
                }

                $productType = trim((string)(isset($row['machine_product_type']) ? $row['machine_product_type'] : ''));
                if ($productType === '') {
                    $productType = 'UNASSIGNED';
                }

                $dprCode = trim((string)(isset($row['dpr_code']) ? $row['dpr_code'] : ''));
                if ($dprCode === '') {
                    $dprCode = trim((string)(isset($row['production_code']) ? $row['production_code'] : ''));
                }

                $rows[] = [
                    'id' => isset($row['id']) ? (int)$row['id'] : 0,
                    'date_production' => isset($row['date_production']) ? (string)$row['date_production'] : '',
                    'datetime_started' => resolveToDateProductionDateTimeLabelForExport(isset($row['datetime_started']) ? $row['datetime_started'] : '', isset($row['date_production']) ? $row['date_production'] : '', isset($row['time_dropped']) ? $row['time_dropped'] : ''),
                    'datetime_ended' => resolveToDateProductionDateTimeLabelForExport(isset($row['datetime_ended']) ? $row['datetime_ended'] : ''),
                    'dpr_code' => $dprCode,
                    'machine_id' => $machineId,
                    'machine_description' => $machineDescription,
                    'product_type' => $productType,
                    'finished_ice_qty' => isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0,
                    'loose_ice_qty' => isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0,
                    'remarks' => isset($row['remarks']) ? (string)$row['remarks'] : ''
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function fetchToDateProductionMachineColumnsForExport($mysqli, $rows)
{
    $columns = [];
    $seen = [];
    $appendColumn = function ($machineId, $machineDescription, $productType) use (&$columns, &$seen) {
        $machineId = trim((string)$machineId);
        $machineDescription = trim((string)$machineDescription);
        $productType = strtoupper(trim((string)$productType));
        if ($productType === '') {
            $productType = 'UNASSIGNED';
        }
        if ($machineDescription === '') {
            $machineDescription = $machineId !== '' ? 'Machine ' . $machineId : 'Unknown Machine';
        }

        $key = $machineId . '|' . $machineDescription;
        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $columns[] = [
            'key' => $key,
            'machine_id' => $machineId,
            'product_type' => $productType,
            'machine_description' => $machineDescription,
            'machine_label' => formatToDateProductionMachineLabelForExport($machineDescription)
        ];
    };

    if ($mysqli) {
        $result = $mysqli->query("SELECT id, machine_description, product_type FROM ice_machine ORDER BY product_type, machine_description");
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $appendColumn(
                    isset($row['id']) ? $row['id'] : '',
                    isset($row['machine_description']) ? $row['machine_description'] : '',
                    isset($row['product_type']) ? $row['product_type'] : ''
                );
            }
            $result->free();
        }
    }

    foreach ($rows as $row) {
        $appendColumn(
            isset($row['machine_id']) ? $row['machine_id'] : '',
            isset($row['machine_description']) ? $row['machine_description'] : '',
            isset($row['product_type']) ? $row['product_type'] : ''
        );
    }

    usort($columns, function ($left, $right) {
        $typeCompare = strcasecmp((string)$left['product_type'], (string)$right['product_type']);
        if ($typeCompare !== 0) {
            return $typeCompare;
        }

        return strcasecmp((string)$left['machine_label'], (string)$right['machine_label']);
    });

    return $columns;
}

function buildToDateProductionMachineMatrixForExport($rows, $startDate, $endDate, $machineColumns)
{
    $machineColumnMap = [];
    foreach ($machineColumns as $column) {
        $key = isset($column['key']) ? (string)$column['key'] : '';
        if ($key !== '') {
            $machineColumnMap[$key] = true;
        }
    }

    $matrixRows = [];
    $startObject = DateTime::createFromFormat('Y-m-d', $startDate);
    $endObject = DateTime::createFromFormat('Y-m-d', $endDate);
    if ($startObject && $endObject) {
        $currentObject = clone $startObject;
        while ($currentObject <= $endObject) {
            $dateKey = $currentObject->format('Y-m-d');
            $matrixRows[$dateKey] = [
                'date' => $dateKey,
                'date_label' => $currentObject->format('d-M-y'),
                'machines' => []
            ];
            foreach ($machineColumns as $column) {
                $matrixRows[$dateKey]['machines'][$column['key']] = [
                    'hours' => 0.0,
                    'qty' => 0.0
                ];
            }
            $currentObject->modify('+1 day');
        }
    }

    foreach ($rows as $row) {
        $dateKey = isset($row['date_production']) ? (string)$row['date_production'] : '';
        if (!isset($matrixRows[$dateKey])) {
            continue;
        }

        $machineKey = trim((string)(isset($row['machine_id']) ? $row['machine_id'] : '')) . '|' . trim((string)(isset($row['machine_description']) ? $row['machine_description'] : ''));
        if ($machineKey === '|' || !isset($machineColumnMap[$machineKey])) {
            continue;
        }

        $finishedQty = isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0;
        $looseQty = isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0;
        $matrixRows[$dateKey]['machines'][$machineKey]['hours'] += calculateToDateProductionHoursForExport(
            isset($row['datetime_started']) ? $row['datetime_started'] : '',
            isset($row['datetime_ended']) ? $row['datetime_ended'] : ''
        );
        $matrixRows[$dateKey]['machines'][$machineKey]['qty'] += $finishedQty + $looseQty;
    }

    return array_values($matrixRows);
}

function buildToDateProductionProductGroupsForExport($machineColumns)
{
    $groups = [];
    foreach ($machineColumns as $column) {
        $productType = isset($column['product_type']) ? (string)$column['product_type'] : 'UNASSIGNED';
        if (!isset($groups[$productType])) {
            $groups[$productType] = [
                'product_type' => $productType,
                'colspan' => 0,
                'machine_columns' => []
            ];
        }
        $groups[$productType]['machine_columns'][] = $column;
        $groups[$productType]['colspan'] += 2;
    }

    foreach ($groups as &$group) {
        $group['colspan'] += 1;
    }
    unset($group);

    return array_values($groups);
}

function formatToDateProductionReportHeadingForExport($startDate, $endDate)
{
    if ($startDate === $endDate) {
        return strtoupper(date('F d, Y', strtotime($startDate)));
    }

    return strtoupper(date('F d, Y', strtotime($startDate)) . ' TO ' . date('F d, Y', strtotime($endDate)));
}

$registerRows = fetchToDateProductionRowsForExport($mysqli, $startDate, $endDate);
$totalFinishedIceQty = 0.0;
$totalLooseIceQty = 0.0;
foreach ($registerRows as $row) {
    $totalFinishedIceQty += isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0;
    $totalLooseIceQty += isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0;
}
$entryCount = count($registerRows);
$dayCount = $startObject && $endObject ? $startObject->diff($endObject)->days + 1 : 1;
$averageDailyEntries = $dayCount > 0 ? ($entryCount / $dayCount) : 0;
$reportRangeLabel = 'from ' . date('F d', strtotime($startDate)) . ' to ' . date('d, Y', strtotime($endDate));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('To-Date Production');

$sheet->setCellValue('A1', $reportType === 'by_product_machine' ? 'DAILY PRODUCTION SUMMARY - BY MACHINE' : 'TO-DATE PRODUCTION REPORT');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', $reportType === 'by_product_machine' ? formatToDateProductionReportHeadingForExport($startDate, $endDate) : $reportRangeLabel);
if ($reportType === 'by_product_machine') {
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
}
if ($reportType !== 'by_product_machine') {
    $sheet->setCellValue('A3', 'Generated: ' . $generatedAt);
}

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

$decimalFormat = '#,##0.00';

if ($reportType !== 'by_product_machine') {
    $sheet->setCellValue('I1', 'TOTAL FINISHED ICE QTY (KG)');
    $sheet->setCellValue('K1', $totalFinishedIceQty);
    $sheet->setCellValue('I2', 'TOTAL LOOSE ICE QTY (KG)');
    $sheet->setCellValue('K2', $totalLooseIceQty);
    $sheet->setCellValue('I3', 'TOTAL PRODUCTION');
    $sheet->setCellValue('K3', $entryCount);
    $sheet->setCellValue('I4', 'AVERAGE DAILY PRODUCTION');
    $sheet->setCellValue('K4', $averageDailyEntries);
    $sheet->getStyle('I1:K1')->applyFromArray($summaryBorderStyle);
    $sheet->getStyle('I2:K2')->applyFromArray($summaryBorderStyle);
    $sheet->getStyle('I3:K3')->applyFromArray($summaryBorderStyle);
    $sheet->getStyle('I4:K4')->applyFromArray($summaryBorderStyle);
    $sheet->getStyle('I1:I4')->getFont()->setBold(true);
    $sheet->getStyle('K1')->getNumberFormat()->setFormatCode($decimalFormat);
    $sheet->getStyle('K2')->getNumberFormat()->setFormatCode($decimalFormat);
    $sheet->getStyle('K3')->getNumberFormat()->setFormatCode('0');
    $sheet->getStyle('K4')->getNumberFormat()->setFormatCode($decimalFormat);
    $sheet->getStyle('K1:K4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

$headerRow = 9;
$dataRow = 10;

if ($reportType === 'by_product_machine') {
    $machineColumns = fetchToDateProductionMachineColumnsForExport($mysqli, $registerRows);
    $machineProductGroups = buildToDateProductionProductGroupsForExport($machineColumns);
    $machineMatrixRows = buildToDateProductionMachineMatrixForExport($registerRows, $startDate, $endDate, $machineColumns);
    $measureColumnCount = 0;
    foreach ($machineProductGroups as $productGroup) {
        $measureColumnCount += (int)$productGroup['colspan'];
    }
    $measureColumnCount = max(1, $measureColumnCount);
    $lastColumnIndex = 1 + $measureColumnCount;
    $lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex);

    if ($lastColumn !== 'B') {
        $sheet->mergeCells("B5:{$lastColumn}5");
        $sheet->mergeCells("B6:{$lastColumn}6");
    }
    $sheet->mergeCells('A7:A9');
    $sheet->setCellValue('B5', 'IN (IN KG)');
    $sheet->setCellValue('B6', 'PRODUCTION');
    $sheet->setCellValue('A7', 'DATE');

    $currentColumnIndex = 2;
    foreach ($machineProductGroups as $productGroup) {
        $startColumn = Coordinate::stringFromColumnIndex($currentColumnIndex);
        $endColumn = Coordinate::stringFromColumnIndex($currentColumnIndex + (int)$productGroup['colspan'] - 1);
        $sheet->mergeCells("{$startColumn}7:{$endColumn}7");
        $sheet->setCellValue("{$startColumn}7", (string)$productGroup['product_type']);
        $currentColumnIndex += (int)$productGroup['colspan'];
    }

    $currentColumnIndex = 2;
    foreach ($machineProductGroups as $productGroup) {
        foreach ($productGroup['machine_columns'] as $machineColumn) {
            $startColumn = Coordinate::stringFromColumnIndex($currentColumnIndex);
            $endColumn = Coordinate::stringFromColumnIndex($currentColumnIndex + 1);
            $sheet->mergeCells("{$startColumn}8:{$endColumn}8");
            $sheet->setCellValue("{$startColumn}8", (string)$machineColumn['machine_label']);
            $sheet->setCellValue("{$startColumn}9", 'HRS');
            $sheet->setCellValue("{$endColumn}9", 'QTY');
            $currentColumnIndex += 2;
        }
        $subtotalColumn = Coordinate::stringFromColumnIndex($currentColumnIndex);
        $sheet->mergeCells("{$subtotalColumn}8:{$subtotalColumn}9");
        $sheet->setCellValue("{$subtotalColumn}8", 'SUB-TOTAL');
        $currentColumnIndex += 1;
    }

    $currentRow = 10;
    foreach ($machineMatrixRows as $matrixRow) {
        $sheet->setCellValueExplicit("A{$currentRow}", (string)$matrixRow['date_label'], DataType::TYPE_STRING);
        $currentColumnIndex = 2;
        foreach ($machineProductGroups as $productGroup) {
            $productSubtotalQty = 0.0;
            foreach ($productGroup['machine_columns'] as $machineColumn) {
                $hoursColumn = Coordinate::stringFromColumnIndex($currentColumnIndex);
                $qtyColumn = Coordinate::stringFromColumnIndex($currentColumnIndex + 1);
                $cell = isset($matrixRow['machines'][$machineColumn['key']]) ? $matrixRow['machines'][$machineColumn['key']] : ['hours' => 0.0, 'qty' => 0.0];
                if (abs((float)$cell['hours']) >= 0.005) {
                    $sheet->setCellValue("{$hoursColumn}{$currentRow}", (float)$cell['hours']);
                }
                if (abs((float)$cell['qty']) >= 0.005) {
                    $sheet->setCellValue("{$qtyColumn}{$currentRow}", (float)$cell['qty']);
                }
                $productSubtotalQty += isset($cell['qty']) ? (float)$cell['qty'] : 0.0;
                $currentColumnIndex += 2;
            }
            $subtotalColumn = Coordinate::stringFromColumnIndex($currentColumnIndex);
            if (abs($productSubtotalQty) >= 0.005) {
                $sheet->setCellValue("{$subtotalColumn}{$currentRow}", $productSubtotalQty);
            }
            $currentColumnIndex += 1;
        }
        $currentRow++;
    }

    $lastDataRow = max(10, $currentRow - 1);
    $sheet->getStyle("A5:{$lastColumn}{$lastDataRow}")->applyFromArray($borderStyle);
    $sheet->getStyle('A5:A6')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_NONE);
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

    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(14);
    for ($columnIndex = 2; $columnIndex <= $lastColumnIndex; $columnIndex++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(false)->setWidth(13);
    }
    $sheet->getRowDimension(8)->setRowHeight(30);
    $sheet->freezePane('B10');
} else {
    $headers = [
        'NO.',
        'DATE/TIME STARTED',
        'DATE/TIME END',
        'DPR CODE',
        'MACHINE',
        'PRODUCT TYPE',
        'FINISHED ICE QTY (KG)',
        'LOOSE ICE QTY (KG)',
        'REMARKS'
    ];

    $sheet->fromArray($headers, null, 'A' . $headerRow);
    $sheet->getStyle("A{$headerRow}:I{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$headerRow}:I{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $currentRow = $dataRow;
    $lineNumber = 1;
    foreach ($registerRows as $row) {
        $sheet->setCellValue("A{$currentRow}", $lineNumber);
        $sheet->setCellValueExplicit("B{$currentRow}", (string)$row['datetime_started'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("C{$currentRow}", (string)$row['datetime_ended'], DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("D{$currentRow}", (string)$row['dpr_code'], DataType::TYPE_STRING);
        $sheet->setCellValue("E{$currentRow}", (string)$row['machine_description']);
        $sheet->setCellValue("F{$currentRow}", (string)$row['product_type']);
        $sheet->setCellValue("G{$currentRow}", (float)$row['finished_ice_qty']);
        $sheet->setCellValue("H{$currentRow}", (float)$row['loose_ice_qty']);
        $sheet->setCellValue("I{$currentRow}", (string)$row['remarks']);
        $currentRow++;
        $lineNumber++;
    }

    $lastDetailRow = $currentRow - 1;
    $totalRow = $currentRow;
    $sheet->setCellValue("F{$totalRow}", 'TOTAL');
    $sheet->getStyle("A{$totalRow}:I{$totalRow}")->getFont()->setBold(true);
    if ($lastDetailRow >= $dataRow) {
        $sheet->setCellValue("G{$totalRow}", "=SUM(G{$dataRow}:G{$lastDetailRow})");
        $sheet->setCellValue("H{$totalRow}", "=SUM(H{$dataRow}:H{$lastDetailRow})");
    } else {
        $sheet->setCellValue("G{$totalRow}", 0);
        $sheet->setCellValue("H{$totalRow}", 0);
    }

    $lastDataRow = $totalRow;
    if ($lastDataRow >= $headerRow) {
        $sheet->getStyle("A{$headerRow}:I{$lastDataRow}")->applyFromArray($borderStyle);
    }
    if ($lastDataRow >= $dataRow) {
        $sheet->getStyle("G{$dataRow}:H{$lastDataRow}")->getNumberFormat()->setFormatCode($decimalFormat);
    }

    $sheet->getStyle("A{$headerRow}:F{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("G{$headerRow}:H{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
    $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(14);
    $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(14);
    $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(22);
    $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(38);
    $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(18);
    $sheet->getColumnDimension('G')->setAutoSize(false)->setWidth(22);
    $sheet->getColumnDimension('H')->setAutoSize(false)->setWidth(18);
    $sheet->getColumnDimension('I')->setAutoSize(false)->setWidth(30);
}

$spreadsheet->setActiveSheetIndex(0);

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'todate_production_' . $startDate . '_to_' . $endDate . ($reportType === 'by_product_machine' ? '_by_product_machine' : '_detailed') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->setUseDiskCaching(true, getSpreadsheetExportTempDirectory());
$writer->save('php://output');
exit;
