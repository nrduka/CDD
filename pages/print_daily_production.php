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

$reportTypeInput = isset($_GET['report_type']) ? trim((string)$_GET['report_type']) : '';
$reportType = in_array($reportTypeInput, ['detailed', 'by_product_machine'], true) ? $reportTypeInput : 'detailed';
$generatedAt = date('Y-m-d H:i:s');

if (!isset($mysqli) || !$mysqli) {
    http_response_code(500);
    echo 'Database connection unavailable.';
    exit;
}

function resolveProductionDateTimeLabelForExport($datetimeValue, $dateValue = '', $timeValue = '')
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

function fetchDailyProductionRowsForExport($mysqli, $reportDate)
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
            WHERE ip.date_production = ?
            ORDER BY COALESCE(ip.datetime_started, ip.date_production), COALESCE(ip.time_dropped, '00:00:00'), im.machine_description, ip.id";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
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
                    'datetime_started' => resolveProductionDateTimeLabelForExport(isset($row['datetime_started']) ? $row['datetime_started'] : '', isset($row['date_production']) ? $row['date_production'] : '', isset($row['time_dropped']) ? $row['time_dropped'] : ''),
                    'datetime_ended' => resolveProductionDateTimeLabelForExport(isset($row['datetime_ended']) ? $row['datetime_ended'] : ''),
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

function buildProductionMachineGroupsForExport($rows)
{
    $groups = [];
    foreach ($rows as $row) {
        $productType = isset($row['product_type']) ? trim((string)$row['product_type']) : '';
        if ($productType === '') {
            $productType = 'UNASSIGNED';
        }

        $machineId = isset($row['machine_id']) ? trim((string)$row['machine_id']) : '';
        $machineDescription = isset($row['machine_description']) ? trim((string)$row['machine_description']) : '';
        $machineKey = $machineId . '|' . $machineDescription;

        if (!isset($groups[$productType])) {
            $groups[$productType] = [
                'subtotal_entries' => 0,
                'subtotal_finished' => 0.0,
                'subtotal_loose' => 0.0,
                'machines' => []
            ];
        }

        if (!isset($groups[$productType]['machines'][$machineKey])) {
            $groups[$productType]['machines'][$machineKey] = [
                'machine_description' => $machineDescription !== '' ? $machineDescription : 'Unknown Machine',
                'entry_count' => 0,
                'finished_ice_qty' => 0.0,
                'loose_ice_qty' => 0.0
            ];
        }

        $finishedQty = isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0;
        $looseQty = isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0;

        $groups[$productType]['machines'][$machineKey]['entry_count'] += 1;
        $groups[$productType]['machines'][$machineKey]['finished_ice_qty'] += $finishedQty;
        $groups[$productType]['machines'][$machineKey]['loose_ice_qty'] += $looseQty;

        $groups[$productType]['subtotal_entries'] += 1;
        $groups[$productType]['subtotal_finished'] += $finishedQty;
        $groups[$productType]['subtotal_loose'] += $looseQty;
    }

    if (!empty($groups)) {
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($groups as &$groupData) {
            uasort($groupData['machines'], function ($left, $right) {
                return strcasecmp((string)$left['machine_description'], (string)$right['machine_description']);
            });
        }
        unset($groupData);
    }

    return $groups;
}

$registerRows = fetchDailyProductionRowsForExport($mysqli, $reportDate);
$totalFinishedIceQty = 0.0;
$totalLooseIceQty = 0.0;
foreach ($registerRows as $row) {
    $totalFinishedIceQty += isset($row['finished_ice_qty']) ? (float)$row['finished_ice_qty'] : 0.0;
    $totalLooseIceQty += isset($row['loose_ice_qty']) ? (float)$row['loose_ice_qty'] : 0.0;
}
$entryCount = count($registerRows);
$averageDailyEntries = $entryCount;
$reportRangeLabel = 'from ' . date('F d', strtotime($reportDate)) . ' to ' . date('d, Y', strtotime($reportDate));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Production');

$sheet->setCellValue('A1', $reportType === 'by_product_machine' ? 'DAILY PRODUCTION REPORT (BY PRODUCT TYPE / MACHINE)' : 'DAILY PRODUCTION REPORT');
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

$decimalFormat = '#,##0.00';

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

$headerRow = 9;
$dataRow = 10;

if ($reportType === 'by_product_machine') {
    $headers = [
        'NO.',
        'PRODUCT TYPE',
        'MACHINE',
        'PRODUCTION',
        'FINISHED ICE QTY (KG)',
        'LOOSE ICE QTY (KG)'
    ];

    $groups = buildProductionMachineGroupsForExport($registerRows);

    $sheet->fromArray($headers, null, 'A' . $headerRow);
    $sheet->getStyle("A{$headerRow}:F{$headerRow}")->applyFromArray($headerStyle);
    $sheet->getStyle("A{$headerRow}:F{$headerRow}")
        ->getAlignment()
        ->setWrapText(true)
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $currentRow = $dataRow;
    $lineNumber = 1;
    $grandTotalEntries = 0;
    $grandTotalFinished = 0.0;
    $grandTotalLoose = 0.0;

    foreach ($groups as $productType => $groupData) {
        foreach ($groupData['machines'] as $machineData) {
            $sheet->setCellValue("A{$currentRow}", $lineNumber);
            $sheet->setCellValue("B{$currentRow}", (string)$productType);
            $sheet->setCellValue("C{$currentRow}", (string)$machineData['machine_description']);
            $sheet->setCellValue("D{$currentRow}", (int)$machineData['entry_count']);
            $sheet->setCellValue("E{$currentRow}", (float)$machineData['finished_ice_qty']);
            $sheet->setCellValue("F{$currentRow}", (float)$machineData['loose_ice_qty']);
            $currentRow++;
            $lineNumber++;
        }

        $sheet->setCellValue("B{$currentRow}", 'SUBTOTAL - ' . $productType);
        $sheet->setCellValue("D{$currentRow}", (int)$groupData['subtotal_entries']);
        $sheet->setCellValue("E{$currentRow}", (float)$groupData['subtotal_finished']);
        $sheet->setCellValue("F{$currentRow}", (float)$groupData['subtotal_loose']);
        $sheet->getStyle("A{$currentRow}:F{$currentRow}")->getFont()->setBold(true);
        $currentRow++;

        $grandTotalEntries += (int)$groupData['subtotal_entries'];
        $grandTotalFinished += (float)$groupData['subtotal_finished'];
        $grandTotalLoose += (float)$groupData['subtotal_loose'];
    }

    $totalRow = $currentRow;
    $sheet->setCellValue("C{$totalRow}", 'TOTAL');
    $sheet->setCellValue("D{$totalRow}", $grandTotalEntries);
    $sheet->setCellValue("E{$totalRow}", $grandTotalFinished);
    $sheet->setCellValue("F{$totalRow}", $grandTotalLoose);
    $sheet->getStyle("A{$totalRow}:F{$totalRow}")->getFont()->setBold(true);

    $lastDataRow = $totalRow;
    if ($lastDataRow >= $headerRow) {
        $sheet->getStyle("A{$headerRow}:F{$lastDataRow}")->applyFromArray($borderStyle);
    }
    if ($lastDataRow >= $dataRow) {
        $sheet->getStyle("D{$dataRow}:D{$lastDataRow}")->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle("E{$dataRow}:F{$lastDataRow}")->getNumberFormat()->setFormatCode($decimalFormat);
    }

    $sheet->getStyle("A{$headerRow}:C{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("D{$headerRow}:F{$lastDataRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(5);
    $sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(20);
    $sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(42);
    $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(12);
    $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(20);
    $sheet->getColumnDimension('F')->setAutoSize(false)->setWidth(20);
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

$filename = 'daily_production_' . $reportDate . ($reportType === 'by_product_machine' ? '_by_product_machine' : '_detailed') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
