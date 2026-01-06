<?php
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}

session_start();

$role = isset($_SESSION['sess_userrole']) ? strtoupper($_SESSION['sess_userrole']) : '';
$assignedDepartments = isset($_SESSION['sess_department']) ? trim((string)$_SESSION['sess_department']) : '';
$allowedRoles = ['REGISTRATION', 'COUNTER', 'ADMIN', 'CASHIER', 'DEPARTMENT'];
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

function fetchWaitingCount($mysqli, $reportDate)
{
    $sql = "SELECT COUNT(*) AS total
            FROM daily_queue dq
            LEFT JOIN queue_calls qc ON qc.queue_id = dq.id AND qc.status = 'ACTIVE'
            LEFT JOIN registration_queue rq ON rq.queue_id = dq.id AND rq.status = 'WAITING'
            WHERE dq.queue_date = ?
              AND qc.id IS NULL
              AND rq.id IS NULL";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute()) {
            $stmt->bind_result($total);
            if ($stmt->fetch()) {
                $stmt->close();
                return (int)$total;
            }
        }
        $stmt->close();
    }
    return 0;
}

function fetchWaitingByCategory($mysqli, $reportDate)
{
    $data = [];
    $sql = "SELECT dq.category, COUNT(*) AS total
            FROM daily_queue dq
            LEFT JOIN queue_calls qc ON qc.queue_id = dq.id AND qc.status = 'ACTIVE'
            LEFT JOIN registration_queue rq ON rq.queue_id = dq.id AND rq.status = 'WAITING'
            WHERE dq.queue_date = ?
              AND qc.id IS NULL
              AND rq.id IS NULL
            GROUP BY dq.category";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $category = isset($row['category']) ? (string)$row['category'] : '';
                $data[] = [
                    'category' => $category,
                    'label' => describe_queue_category($category),
                    'total' => isset($row['total']) ? (int)$row['total'] : 0
                ];
            }
            $result->free();
        }
        $stmt->close();
    }
    return $data;
}

function fetchActiveCalls($mysqli, $reportDate)
{
    $rows = [];
    $sql = "SELECT qc.id,
                   qc.registration_number,
                   qc.called_at,
                   dq.category,
                   dq.queue_number,
                   dq.unique_code
            FROM queue_calls qc
            INNER JOIN daily_queue dq ON dq.id = qc.queue_id
            WHERE qc.status = 'ACTIVE'
              AND dq.queue_date = ?
            ORDER BY qc.called_at DESC";
    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = [
                    'call_id' => (int)$row['id'],
                    'ticket' => format_ticket_number_value($row['category'], $row['queue_number']),
                    'reference' => format_queue_reference_label($row['category'], $row['queue_number'], $row['unique_code']),
                    'registration' => $row['registration_number'],
                    'called_at' => $row['called_at']
                ];
            }
            $result->free();
        }
        $stmt->close();
    }
    return $rows;
}

function tableHasColumn($mysqli, $tableName, $columnName)
{
    static $cache = [];
    $cacheKey = strtolower($tableName . '.' . $columnName);
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    if (!$mysqli) {
        $cache[$cacheKey] = false;
        return false;
    }

    $sql = 'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1';
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        $cache[$cacheKey] = false;
        return false;
    }
    $stmt->bind_param('ss', $tableName, $columnName);
    $stmt->execute();
    $stmt->store_result();
    $cache[$cacheKey] = $stmt->num_rows > 0;
    $stmt->close();
    return $cache[$cacheKey];
}

function resolvePatientLastNameColumn($mysqli)
{
    static $cached = null;
    if ($cached !== null) {
        return $cached ?: false;
    }

    $candidates = ['lastname', 'last_name'];
    foreach ($candidates as $column) {
        if (tableHasColumn($mysqli, 'patient_info', $column)) {
            $cached = $column;
            return $cached;
        }
    }

    $cached = '';
    return false;
}

function fetchQueueReportRows($mysqli, $reportDate)
{
    $rows = [];
    if (!$mysqli) {
        return $rows;
    }

    $retrievalColumn = '';
    if (tableHasColumn($mysqli, 'daily_queue', 'retrieval_code')) {
        $retrievalColumn = 'retrieval_code';
    }

    $queueRetrievalSelect = $retrievalColumn !== '' ? "dq.`{$retrievalColumn}` AS queue_retrieval_code" : "'' AS queue_retrieval_code";

    $joinPatient = $retrievalColumn !== '' && tableHasColumn($mysqli, 'patient_info', 'retrieval_code');
    $lastNameColumn = $joinPatient ? resolvePatientLastNameColumn($mysqli) : '';
    $lastNameSelect = $lastNameColumn ? "pi.`{$lastNameColumn}` AS patient_lastname" : "'' AS patient_lastname";

    $sql = "SELECT dq.category,
                   dq.queue_number,
                   dq.unique_code,
                   {$queueRetrievalSelect}";
    if ($joinPatient) {
        $sql .= ",
                   pi.firstname AS patient_firstname,
                   {$lastNameSelect},
                   pi.retrieval_code AS patient_retrieval_code";
    }
    $sql .= "
            FROM daily_queue dq";
    if ($joinPatient) {
        $sql .= " LEFT JOIN patient_info pi ON pi.retrieval_code = dq.`{$retrievalColumn}`";
    }
    $sql .= "
            WHERE dq.queue_date = ?
            ORDER BY dq.queue_number ASC, dq.id ASC";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('s', $reportDate);
        if ($stmt->execute() && ($result = $stmt->get_result())) {
            while ($row = $result->fetch_assoc()) {
                $firstName = $joinPatient && isset($row['patient_firstname']) ? (string)$row['patient_firstname'] : '';
                $lastName = $joinPatient && isset($row['patient_lastname']) ? (string)$row['patient_lastname'] : '';
                $queueRetrieval = isset($row['queue_retrieval_code']) ? (string)$row['queue_retrieval_code'] : '';
                $patientRetrieval = $joinPatient && isset($row['patient_retrieval_code']) ? (string)$row['patient_retrieval_code'] : '';
                $activeRetrieval = $patientRetrieval !== '' ? $patientRetrieval : $queueRetrieval;

                $rows[] = [
                    'ticket' => format_ticket_number_value($row['category'], $row['queue_number']),
                    'category' => isset($row['category']) ? (string)$row['category'] : '',
                    'category_label' => describe_queue_category($row['category']),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'retrieval_code' => $activeRetrieval
                ];
            }
            $result->free();
        }
        $stmt->close();
    }

    return $rows;
}

function describe_queue_category($category)
{
    static $map = [
        'P' => 'Priority',
        'R' => 'Regular'
    ];
    $key = strtoupper(trim((string)$category));
    return isset($map[$key]) ? $map[$key] : ($key !== '' ? $key : 'Unassigned');
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

$summary = [
    'waiting_total' => fetchWaitingCount($mysqli, $reportDate),
    'waiting_by_category' => fetchWaitingByCategory($mysqli, $reportDate),
    'active_list' => fetchActiveCalls($mysqli, $reportDate),
    'queue_list' => fetchQueueReportRows($mysqli, $reportDate),
    'report_date' => $reportDate,
    'generated_at' => $generatedAt
];
$summary['active_total'] = count($summary['active_list']);

$reportDateLabel = date('F d, Y', strtotime($reportDate));

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Queue Summary');

$sheet->setCellValue('A1', 'Queue Summary');
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

$currentRow = 5;
$metrics = [
    ['Waiting Tickets', $summary['waiting_total']],
    ['Now Serving', $summary['active_total']],
    ['Queue Records', count($summary['queue_list'])]
];
$sheet->fromArray(array_merge([['Metric', 'Value']], $metrics), null, "A{$currentRow}");
$metricsEndRow = $currentRow + count($metrics);
$sheet->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray($headerStyle);
$sheet->getStyle("A{$currentRow}:B{$metricsEndRow}")->applyFromArray($borderStyle);

$currentRow = $metricsEndRow + 2;
$sheet->setCellValue("A{$currentRow}", 'Waiting Breakdown');
$sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
$currentRow++;
$sheet->fromArray([['Category', 'Total']], null, "A{$currentRow}");
$sheet->getStyle("A{$currentRow}:B{$currentRow}")->applyFromArray($headerStyle);

if (!empty($summary['waiting_by_category'])) {
    $breakdownStart = $currentRow + 1;
    foreach ($summary['waiting_by_category'] as $index => $item) {
        $rowNumber = $breakdownStart + $index;
        $sheet->setCellValue("A{$rowNumber}", $item['label']);
        $sheet->setCellValue("B{$rowNumber}", $item['total']);
    }
    $sheet->getStyle("A{$breakdownStart}:B" . ($breakdownStart + count($summary['waiting_by_category']) - 1))->applyFromArray($borderStyle);
    $currentRow = $breakdownStart + count($summary['waiting_by_category']) - 1;
} else {
    $sheet->setCellValue("A" . ($currentRow + 1), 'No waiting tickets for this date.');
    $sheet->mergeCells("A" . ($currentRow + 1) . ":B" . ($currentRow + 1));
    $sheet->getStyle("A" . ($currentRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $currentRow++;
}

$currentRow += 2;
$sheet->setCellValue("A{$currentRow}", 'Queue Details');
$sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
$currentRow++;
$queueHeaders = ['Queue No.', 'First Name', 'Last Name', 'Retrieval Code', 'Customer Type'];
$sheet->fromArray($queueHeaders, null, "A{$currentRow}");
$sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray($headerStyle);

$queueDataStart = $currentRow + 1;
if (!empty($summary['queue_list'])) {
    foreach ($summary['queue_list'] as $index => $queueRow) {
        $rowNumber = $queueDataStart + $index;
        $sheet->setCellValue("A{$rowNumber}", $queueRow['ticket']);
        $sheet->setCellValue("B{$rowNumber}", $queueRow['first_name'] !== '' ? $queueRow['first_name'] : 'N/A');
        $sheet->setCellValue("C{$rowNumber}", $queueRow['last_name'] !== '' ? $queueRow['last_name'] : 'N/A');
        $sheet->setCellValue("D{$rowNumber}", $queueRow['retrieval_code'] !== '' ? $queueRow['retrieval_code'] : 'N/A');
        $sheet->setCellValue("E{$rowNumber}", $queueRow['category_label']);
    }
    $sheet->getStyle("A{$queueDataStart}:E" . ($queueDataStart + count($summary['queue_list']) - 1))->applyFromArray($borderStyle);
    $currentRow = $queueDataStart + count($summary['queue_list']) - 1;
} else {
    $sheet->setCellValue("A{$queueDataStart}", 'No queue records for this date.');
    $sheet->mergeCells("A{$queueDataStart}:E{$queueDataStart}");
    $sheet->getStyle("A{$queueDataStart}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $currentRow = $queueDataStart;
}

$currentRow += 2;
$sheet->setCellValue("A{$currentRow}", 'Active Calls');
$sheet->getStyle("A{$currentRow}")->getFont()->setBold(true);
$currentRow++;
$activeHeaders = ['Ticket', 'Registration', 'Reference', 'Last Called'];
$sheet->fromArray($activeHeaders, null, "A{$currentRow}");
$sheet->getStyle("A{$currentRow}:D{$currentRow}")->applyFromArray($headerStyle);

$activeDataStart = $currentRow + 1;
if (!empty($summary['active_list'])) {
    foreach ($summary['active_list'] as $index => $active) {
        $rowNumber = $activeDataStart + $index;
        $sheet->setCellValue("A{$rowNumber}", $active['ticket']);
        $sheet->setCellValue("B{$rowNumber}", $active['registration'] ?: 'Unassigned');
        $sheet->setCellValue("C{$rowNumber}", $active['reference']);
        $sheet->setCellValue("D{$rowNumber}", $active['called_at']);
    }
    $sheet->getStyle("A{$activeDataStart}:D" . ($activeDataStart + count($summary['active_list']) - 1))->applyFromArray($borderStyle);
} else {
    $sheet->setCellValue("A{$activeDataStart}", 'No active calls for this date.');
    $sheet->mergeCells("A{$activeDataStart}:D{$activeDataStart}");
    $sheet->getStyle("A{$activeDataStart}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

foreach (['A', 'B', 'C', 'D', 'E'] as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

if (ob_get_length()) {
    ob_end_clean();
}

$filename = 'queue_summary_' . $reportDate . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
