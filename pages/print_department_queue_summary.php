<?php
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}

session_start();

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartments = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$allowedRoles = ['ADMIN', 'REGISTRATION', 'COUNTER', 'DEPARTMENT', 'CASHIER'];
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

$reportDateInput = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
$reportDate = $reportDateInput !== '' ? $reportDateInput : date('Y-m-d');
$reportDateObject = DateTime::createFromFormat('Y-m-d', $reportDate);
if (!$reportDateObject || $reportDateObject->format('Y-m-d') !== $reportDate) {
    $reportDate = date('Y-m-d');
}
$generatedAt = date('Y-m-d H:i:s');

if (!isset($mysqli) || !$mysqli) {
    http_response_code(500);
    echo 'Database connection unavailable.';
    exit;
}

$departments = fetchDepartmentQueueSummary($mysqli, $reportDate);
$totals = calculateDepartmentTotals($departments);
$reportDateLabel = date('F d, Y', strtotime($reportDate));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Department Summary');

$sheet->setCellValue('A1', 'Department Queue Summary');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', 'Report Date: ' . $reportDateLabel);
$sheet->setCellValue('A3', 'Generated: ' . $generatedAt);

$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => '1f2937']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'e5e7eb']
    ],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
];

$borderStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'cbd5e1']
        ]
    ]
];

$currentRow = 5;
$sheet->fromArray([
    ['Metric', 'Value'],
    ['Queued (Waiting)', (int)$totals['waiting']],
    ['Calling', (int)$totals['called']],
    ['Completed', (int)$totals['done']]
], null, "A{$currentRow}");
$sheet->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$currentRow}:B" . ($currentRow + 3))->applyFromArray($borderStyle);

$currentRow += 5;
$sheet->setCellValue("A{$currentRow}", 'Per Department Detail');
$sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
$sheet->setCellValue("B{$currentRow}", $reportDateLabel);
$sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

$currentRow += 1;
$sheet->fromArray(['Department', 'Waiting', 'Calling', 'Completed'], null, "A{$currentRow}");
$sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray($headerStyle);

$dataStart = $currentRow + 1;
if (!empty($departments)) {
    foreach ($departments as $index => $dept) {
        $rowNumber = $dataStart + $index;
        $sheet->setCellValue("A{$rowNumber}", $dept['department_name']);
        $sheet->setCellValue("B{$rowNumber}", (int)$dept['waiting_count']);
        $sheet->setCellValue("C{$rowNumber}", (int)$dept['called_count']);
        $sheet->setCellValue("D{$rowNumber}", (int)$dept['done_count']);
    }
    $sheet->getStyle("A{$dataStart}:D" . ($dataStart + count($departments) - 1))->applyFromArray($borderStyle);
} else {
    $sheet->setCellValue("A{$dataStart}", 'No department records for this date.');
    $sheet->mergeCells("A{$dataStart}:D{$dataStart}");
    $sheet->getStyle("A{$dataStart}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("A{$dataStart}:D{$dataStart}")->applyFromArray($borderStyle);
}

foreach (['A', 'B', 'C', 'D'] as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'department_queue_summary_' . $reportDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

function fetchDepartmentQueueSummary($mysqli, $reportDate)
{
    $results = [];
    $sql = "SELECT d.id,
                   d.department,
                   SUM(CASE WHEN daily.queue_date IS NOT NULL AND dq.status = 'WAITING' THEN 1 ELSE 0 END) AS waiting_count,
                   SUM(CASE WHEN daily.queue_date IS NOT NULL AND dq.status = 'CALLED' THEN 1 ELSE 0 END) AS called_count,
                   SUM(CASE WHEN daily.queue_date IS NOT NULL AND dq.status = 'DONE' THEN 1 ELSE 0 END) AS done_count
            FROM department d
            LEFT JOIN department_queue dq ON dq.department_id = d.id
            LEFT JOIN queue_calls qc ON qc.id = dq.queue_call_id
            LEFT JOIN daily_queue daily ON daily.id = qc.queue_id AND daily.queue_date = ?
            GROUP BY d.id, d.department
            ORDER BY d.department ASC";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $results[] = [
                    'department_id' => isset($row['id']) ? (int)$row['id'] : 0,
                    'department_name' => $row['department'],
                    'waiting_count' => isset($row['waiting_count']) ? (int)$row['waiting_count'] : 0,
                    'called_count' => isset($row['called_count']) ? (int)$row['called_count'] : 0,
                    'done_count' => isset($row['done_count']) ? (int)$row['done_count'] : 0
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $results;
}

function calculateDepartmentTotals($departments)
{
    $totals = ['waiting' => 0, 'called' => 0, 'done' => 0];
    foreach ($departments as $dept) {
        $totals['waiting'] += isset($dept['waiting_count']) ? (int)$dept['waiting_count'] : 0;
        $totals['called'] += isset($dept['called_count']) ? (int)$dept['called_count'] : 0;
        $totals['done'] += isset($dept['done_count']) ? (int)$dept['done_count'] : 0;
    }
    return $totals;
}
