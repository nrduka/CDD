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

$reportRows = fetchPatientServiceTimes($mysqli, $reportDate);
$stats = calculateSummaryStats($reportRows);
$reportDateLabel = date('F d, Y', strtotime($reportDate));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Patient Service Time');

$sheet->setCellValue('A1', 'Patient Service Time Report');
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
    ['Completed Patients', (int)$stats['completed']],
    ['In Progress', (int)$stats['in_progress']],
    ['Average Duration (mins)', $stats['average_minutes']]
], null, "A{$currentRow}");
$sheet->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$currentRow}:B" . ($currentRow + 3))->applyFromArray($borderStyle);

$currentRow += 5;
$sheet->setCellValue("A{$currentRow}", 'Service Time Detail');
$sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
$currentRow++;
$sheet->fromArray(['Ticket', 'Reference', 'Registration', 'First Call', 'Completed At', 'Status', 'Service Time (mins)'], null, "A{$currentRow}");
$sheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray($headerStyle);

$dataStart = $currentRow + 1;
if (!empty($reportRows)) {
    foreach ($reportRows as $index => $row) {
        $rowNumber = $dataStart + $index;
        $sheet->setCellValue("A{$rowNumber}", $row['ticket']);
        $sheet->setCellValue("B{$rowNumber}", $row['reference']);
        $sheet->setCellValue("C{$rowNumber}", $row['registration'] !== '' ? $row['registration'] : 'Unassigned');
        $sheet->setCellValue("D{$rowNumber}", $row['start_time'] ?: '');
        $sheet->setCellValue("E{$rowNumber}", $row['finish_time'] ?: '');
        $sheet->setCellValue("F{$rowNumber}", $row['status']);
        $sheet->setCellValue("G{$rowNumber}", $row['service_minutes'] !== null ? $row['service_minutes'] : '');
    }
    $sheet->getStyle("A{$dataStart}:G" . ($dataStart + count($reportRows) - 1))->applyFromArray($borderStyle);
} else {
    $sheet->setCellValue("A{$dataStart}", 'No service time records for this date.');
    $sheet->mergeCells("A{$dataStart}:G{$dataStart}");
    $sheet->getStyle("A{$dataStart}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getStyle("A{$dataStart}:G{$dataStart}")->applyFromArray($borderStyle);
}

foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'patient_service_time_report_' . $reportDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

function fetchPatientServiceTimes($mysqli, $reportDate)
{
    $data = [];
    $sql = "SELECT qc.id AS call_id,
                   qc.registration_number,
                   qc.called_at,
                   qc.completed_at AS registration_completed,
                   daily.category,
                   daily.queue_number,
                   daily.unique_code,
                   daily.retrieval_code,
                   (SELECT MAX(completed_at) FROM department_queue WHERE queue_call_id = qc.id) AS department_completed,
                   (SELECT MAX(completed_at) FROM registration_queue WHERE queue_call_id = qc.id) AS counter_completed
            FROM queue_calls qc
            INNER JOIN daily_queue daily ON daily.id = qc.queue_id
            WHERE daily.queue_date = ?
            ORDER BY qc.called_at ASC";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $ticket = format_ticket_number_value($row['category'], $row['queue_number']);
                $reference = format_queue_reference_label($row['category'], $row['queue_number'], $row['unique_code']);
                $start = !empty($row['called_at']) ? $row['called_at'] : null;
                $completionCandidates = array_filter([
                    $row['department_completed'],
                    $row['registration_completed'],
                    $row['counter_completed']
                ]);
                $finish = !empty($completionCandidates) ? max($completionCandidates) : null;
                $serviceMinutes = null;
                if ($start !== null && $finish !== null) {
                    $serviceMinutes = max(0, round((strtotime($finish) - strtotime($start)) / 60, 2));
                }

                $data[] = [
                    'ticket' => $ticket,
                    'reference' => $reference,
                    'registration' => $row['registration_number'],
                    'start_time' => $start,
                    'finish_time' => $finish,
                    'service_minutes' => $serviceMinutes,
                    'status' => $finish === null ? 'In Progress' : 'Completed'
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $data;
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
