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
$selectedDepartmentId = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$generatedAt = date('Y-m-d H:i:s');

if (!isset($mysqli) || !$mysqli) {
    http_response_code(500);
    echo 'Database connection unavailable.';
    exit;
}

$departmentLabel = resolveDepartmentLabel($mysqli, $selectedDepartmentId);
$reportRows = fetchDepartmentServiceTimes($mysqli, $reportDate, $selectedDepartmentId);
$stats = calculateSummaryStats($reportRows);
$reportDateLabel = date('F d, Y', strtotime($reportDate));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Department Service Time');

$sheet->setCellValue('A1', 'Department Service Time Report');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->setCellValue('A2', 'Report Date: ' . $reportDateLabel);
$sheet->setCellValue('A3', 'Department Filter: ' . $departmentLabel);
$sheet->setCellValue('A4', 'Generated: ' . $generatedAt);

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

$currentRow = 6;
$sheet->fromArray([
    ['Metric', 'Value'],
    ['Completed Services', (int)$stats['completed']],
    ['In Progress', (int)$stats['in_progress']],
    ['Average Duration (mins)', $stats['average_minutes']]
], null, "A{$currentRow}");
$sheet->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$currentRow}:B" . ($currentRow + 3))->applyFromArray($borderStyle);

$currentRow += 5;
$sheet->setCellValue("A{$currentRow}", 'Service Time Detail');
$sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
$currentRow++;
$sheet->fromArray(['Department', 'Ticket', 'Reference', 'Registration', 'Called At', 'Completed At', 'Status', 'Service Time (mins)'], null, "A{$currentRow}");
$sheet->getStyle("A{$currentRow}:H{$currentRow}")->applyFromArray($headerStyle);

$dataStart = $currentRow + 1;
if (!empty($reportRows)) {
    foreach ($reportRows as $index => $row) {
        $rowNumber = $dataStart + $index;
        $sheet->setCellValue("A{$rowNumber}", $row['department']);
        $sheet->setCellValue("B{$rowNumber}", $row['ticket']);
        $sheet->setCellValue("C{$rowNumber}", $row['reference']);
        $sheet->setCellValue("D{$rowNumber}", $row['registration'] !== '' ? $row['registration'] : 'Unassigned');
        $sheet->setCellValue("E{$rowNumber}", $row['start_time'] ?: '');
        $sheet->setCellValue("F{$rowNumber}", $row['finish_time'] ?: '');
        $sheet->setCellValue("G{$rowNumber}", $row['status']);
        $sheet->setCellValue("H{$rowNumber}", $row['service_minutes'] !== null ? $row['service_minutes'] : '');
    }
    $sheet->getStyle("A{$dataStart}:H" . ($dataStart + count($reportRows) - 1))->applyFromArray($borderStyle);
} else {
    $sheet->setCellValue("A{$dataStart}", 'No department service records for this date.');
    $sheet->mergeCells("A{$dataStart}:H{$dataStart}");
    $sheet->getStyle("A{$dataStart}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("A{$dataStart}:H{$dataStart}")->applyFromArray($borderStyle);
}

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'department_service_time_report_' . $reportDate;
if ($selectedDepartmentId > 0) {
    $filename .= '_dept_' . $selectedDepartmentId;
}
$filename .= '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

function fetchDepartmentServiceTimes($mysqli, $reportDate, $departmentId = 0)
{
    $rows = [];
    $sql = "SELECT dq.id,
                   dq.department_id,
                   d.department,
                   dq.called_at,
                   dq.completed_at,
                   qc.registration_number,
                   daily.category,
                   daily.queue_number,
                   daily.unique_code
            FROM department_queue dq
            INNER JOIN queue_calls qc ON qc.id = dq.queue_call_id
            INNER JOIN daily_queue daily ON daily.id = qc.queue_id
            INNER JOIN department d ON d.id = dq.department_id
            WHERE daily.queue_date = ?
              AND dq.called_at IS NOT NULL";
    if ($departmentId > 0) {
        $sql .= ' AND dq.department_id = ?';
    }
    $sql .= "
            ORDER BY d.department ASC, dq.called_at ASC";

    if ($stmt = $mysqli->prepare($sql)) {
        if ($departmentId > 0) {
            $stmt->bind_param('si', $reportDate, $departmentId);
        } else {
            $stmt->bind_param('s', $reportDate);
        }
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $ticket = format_ticket_number_value($row['category'], $row['queue_number']);
                $reference = format_queue_reference_label($row['category'], $row['queue_number'], $row['unique_code']);
                $start = $row['called_at'];
                $finish = $row['completed_at'];
                $serviceMinutes = null;
                if ($start && $finish) {
                    $serviceMinutes = max(0, round((strtotime($finish) - strtotime($start)) / 60, 2));
                }
                $rows[] = [
                    'department' => $row['department'],
                    'ticket' => $ticket,
                    'reference' => $reference,
                    'registration' => $row['registration_number'],
                    'start_time' => $start,
                    'finish_time' => $finish,
                    'service_minutes' => $serviceMinutes,
                    'status' => $finish ? 'Completed' : 'In Progress'
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function resolveDepartmentLabel($mysqli, $departmentId)
{
    if ($departmentId <= 0) {
        return 'All departments';
    }
    $label = 'Selected Department';
    $sql = 'SELECT department FROM department WHERE id = ? LIMIT 1';
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('i', $departmentId);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            if ($row = $result->fetch_assoc()) {
                $label = $row['department'];
            }
            $result->free();
        }
        $stmt->close();
    }
    return $label ?: 'All departments';
}

function calculateSummaryStats($rows)
{
    $stats = ['completed' => 0, 'in_progress' => 0, 'average_minutes' => 0];
    $durationSum = 0;
    $durationCount = 0;
    foreach ($rows as $row) {
        if ($row['service_minutes'] === null) {
            $stats['in_progress']++;
        } else {
            $stats['completed']++;
            $durationSum += $row['service_minutes'];
            $durationCount++;
        }
    }
    if ($durationCount > 0) {
        $stats['average_minutes'] = round($durationSum / $durationCount, 2);
    }
    return $stats;
}

function format_ticket_number_value($category, $queueNumber)
{
    $category = strtoupper(trim((string)$category));
    $number = (int)$queueNumber;
    return sprintf('%s%02d', $category !== '' ? $category : 'Q', $number);
}

function format_queue_reference_label($category, $queueNumber, $uniqueCode)
{
    $ticket = format_ticket_number_value($category, $queueNumber);
    $code = trim((string)$uniqueCode);
    return $code !== '' ? $ticket . ' (' . $code . ')' : $ticket;
}
