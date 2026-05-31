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

if (
    !isset($mysqli)
    || !($mysqli instanceof mysqli)
    || (function_exists('mysqli_ping') && !@mysqli_ping($mysqli))
) {
    $fallbackHost = isset($databaseHost) ? (string)$databaseHost : 'localhost';
    $fallbackName = isset($databaseName) ? (string)$databaseName : 'cdd';
    $fallbackUser = isset($databaseUsername) ? (string)$databaseUsername : 'root';
    $fallbackPass = isset($databasePassword) ? (string)$databasePassword : '';
    $fallbackMysqli = @mysqli_connect($fallbackHost, $fallbackUser, $fallbackPass, $fallbackName);
    if ($fallbackMysqli instanceof mysqli) {
        $mysqli = $fallbackMysqli;
    }
}

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    http_response_code(500);
    $connectError = trim((string)mysqli_connect_error());
    echo $connectError !== '' ? 'Database connection unavailable. ' . $connectError : 'Database connection unavailable.';
    exit;
}

function customerRegisterReportColumnLetter($columnIndex)
{
    return Coordinate::stringFromColumnIndex((int)$columnIndex);
}

function customerRegisterReportBuildName($row)
{
    $storeName = trim((string)(isset($row['storeName']) ? $row['storeName'] : ''));
    if ($storeName !== '') {
        return $storeName;
    }

    $nameParts = [];
    foreach (['firstName', 'middleName', 'lastName'] as $field) {
        $value = trim((string)(isset($row[$field]) ? $row[$field] : ''));
        if ($value !== '') {
            $nameParts[] = $value;
        }
    }

    return trim(implode(' ', $nameParts));
}

function customerRegisterReportFetchSources($mysqli)
{
    $sources = [];
    $sql = "SELECT customer_source, customer_channel
            FROM source_channel
            ORDER BY customer_source, customer_channel";
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $sourceId = trim((string)(isset($row['customer_source']) ? $row['customer_source'] : ''));
            if ($sourceId === '') {
                continue;
            }
            $label = trim((string)(isset($row['customer_channel']) ? $row['customer_channel'] : ''));
            $sources[$sourceId] = [
                'source_id' => $sourceId,
                'label' => $label !== '' ? $label : 'SOURCE ' . $sourceId
            ];
        }
        $result->free();
    }

    return array_values($sources);
}

function customerRegisterReportFetchCustomers($mysqli)
{
    $rows = [];
    $sql = "SELECT c.customer_code,
                   c.firstName,
                   c.middleName,
                   c.lastName,
                   c.address,
                   c.storeName,
                   c.area,
                   c.customer_source
            FROM customer_register c
            ORDER BY c.customer_source, c.storeName, c.firstName, c.lastName, c.customer_code";
    $result = $mysqli->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'customer_name' => customerRegisterReportBuildName($row),
                'customer_code' => trim((string)(isset($row['customer_code']) ? $row['customer_code'] : '')),
                'address' => trim((string)(isset($row['address']) ? $row['address'] : '')),
                'area' => trim((string)(isset($row['area']) ? $row['area'] : '')),
                'source_id' => trim((string)(isset($row['customer_source']) ? $row['customer_source'] : ''))
            ];
        }
        $result->free();
    }

    return $rows;
}

$sourceColumns = customerRegisterReportFetchSources($mysqli);
$customerRows = customerRegisterReportFetchCustomers($mysqli);
$sourceLookup = [];
foreach ($sourceColumns as $sourceColumn) {
    $sourceLookup[(string)$sourceColumn['source_id']] = true;
}
foreach ($customerRows as $row) {
    $sourceId = trim((string)$row['source_id']);
    if ($sourceId !== '' && !isset($sourceLookup[$sourceId])) {
        $sourceLookup[$sourceId] = true;
        $sourceColumns[] = [
            'source_id' => $sourceId,
            'label' => 'SOURCE ' . $sourceId
        ];
    }
}

$sourceTotals = [];
foreach ($sourceColumns as $sourceColumn) {
    $sourceTotals[(string)$sourceColumn['source_id']] = 0;
}
foreach ($customerRows as $row) {
    $sourceId = (string)$row['source_id'];
    if ($sourceId !== '' && isset($sourceTotals[$sourceId])) {
        $sourceTotals[$sourceId]++;
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Customer Register');

$title = 'COLDDUST BENTEL - CUSTOMER REGISTER';
$sheet->setCellValue('A1', $title);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '000000']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'E5E7EB']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
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

$sectionRow = 5;
$sourceIdRow = 6;
$headerRow = 7;
$dataStartRow = 8;
$baseHeaders = ['NO', 'CUSTOMER NAME', 'CUSTOMER CODE', 'ADDRESS', 'AREA'];

foreach ($baseHeaders as $index => $header) {
    $columnLetter = customerRegisterReportColumnLetter($index + 1);
    $sheet->mergeCells("{$columnLetter}{$sourceIdRow}:{$columnLetter}{$headerRow}");
    $sheet->setCellValue("{$columnLetter}{$sourceIdRow}", $header);
}

$sourceStartColumnIndex = count($baseHeaders) + 1;
$sourceLastColumnIndex = $sourceStartColumnIndex + count($sourceColumns) - 1;
if (!empty($sourceColumns)) {
    $sourceStartColumnLetter = customerRegisterReportColumnLetter($sourceStartColumnIndex);
    $sourceLastColumnLetter = customerRegisterReportColumnLetter($sourceLastColumnIndex);
    if ($sourceStartColumnLetter !== $sourceLastColumnLetter) {
        $sheet->mergeCells("{$sourceStartColumnLetter}{$sectionRow}:{$sourceLastColumnLetter}{$sectionRow}");
    }
    $sheet->setCellValue("{$sourceStartColumnLetter}{$sectionRow}", 'SOURCE CHANNEL');

    foreach ($sourceColumns as $index => $sourceColumn) {
        $columnLetter = customerRegisterReportColumnLetter($sourceStartColumnIndex + $index);
        $sheet->setCellValueExplicit($columnLetter . $sourceIdRow, (string)$sourceColumn['source_id'], DataType::TYPE_STRING);
        $sheet->setCellValue($columnLetter . $headerRow, $sourceColumn['label']);
    }
}

$lastColumnIndex = !empty($sourceColumns) ? $sourceLastColumnIndex : count($baseHeaders);
$lastColumnLetter = customerRegisterReportColumnLetter($lastColumnIndex);
$headerRange = "A{$sectionRow}:{$lastColumnLetter}{$sourceIdRow}";
$sheet->getStyle($headerRange)->applyFromArray($headerStyle);
$sheet->getStyle($headerRange)->applyFromArray($borderStyle);
$sheet->getStyle("A{$headerRow}:{$lastColumnLetter}{$headerRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$headerRow}:{$lastColumnLetter}{$headerRow}")->applyFromArray($borderStyle);

$currentRow = $dataStartRow;
foreach ($customerRows as $index => $row) {
    $sheet->setCellValue("A{$currentRow}", $index + 1);
    $sheet->setCellValue("B{$currentRow}", $row['customer_name']);
    $sheet->setCellValueExplicit("C{$currentRow}", $row['customer_code'], DataType::TYPE_STRING);
    $sheet->setCellValue("D{$currentRow}", $row['address']);
    $sheet->setCellValue("E{$currentRow}", $row['area']);

    foreach ($sourceColumns as $sourceIndex => $sourceColumn) {
        $columnLetter = customerRegisterReportColumnLetter($sourceStartColumnIndex + $sourceIndex);
        if ((string)$row['source_id'] === (string)$sourceColumn['source_id']) {
            $sheet->setCellValue($columnLetter . $currentRow, 1);
        }
    }

    $currentRow++;
}

$totalRow = $currentRow;
$sheet->mergeCells("A{$totalRow}:B{$totalRow}");
$sheet->setCellValue("A{$totalRow}", 'TOTAL COUNT');
foreach ($sourceColumns as $sourceIndex => $sourceColumn) {
    $sourceId = (string)$sourceColumn['source_id'];
    $columnLetter = customerRegisterReportColumnLetter($sourceStartColumnIndex + $sourceIndex);
    $sheet->setCellValue($columnLetter . $totalRow, isset($sourceTotals[$sourceId]) ? (int)$sourceTotals[$sourceId] : 0);
}
$sheet->getStyle("A{$totalRow}:{$lastColumnLetter}{$totalRow}")->getFont()->setBold(true);

if ($totalRow >= $sectionRow) {
    $sheet->getStyle("A{$sectionRow}:{$lastColumnLetter}{$totalRow}")->applyFromArray($borderStyle);
}
$sheet->getStyle("A{$dataStartRow}:A{$totalRow}")
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);
if (!empty($sourceColumns)) {
    $sheet->getStyle(customerRegisterReportColumnLetter($sourceStartColumnIndex) . "{$dataStartRow}:{$lastColumnLetter}{$totalRow}")
        ->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
}

$sheet->getColumnDimension('A')->setAutoSize(false)->setWidth(8);
$sheet->getColumnDimension('B')->setAutoSize(false)->setWidth(28);
$sheet->getColumnDimension('C')->setAutoSize(false)->setWidth(20);
$sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(28);
$sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(16);
for ($columnIndex = $sourceStartColumnIndex; $columnIndex <= $lastColumnIndex; $columnIndex++) {
    $sheet->getColumnDimension(customerRegisterReportColumnLetter($columnIndex))->setAutoSize(false)->setWidth(16);
}

$sheet->freezePane('A8');

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'customer_register_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->setUseDiskCaching(true, getSpreadsheetExportTempDirectory());
$writer->save('php://output');
exit;
