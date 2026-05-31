<?php
// File: C:\xampp\htdocs\MPCEMS\pages\export_attendance.php

// 1) Include DB config
require __DIR__ . '/database-config.php';

// 2) Include PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';
require_once dirname(__DIR__) . '/report_export_runtime.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 3) Prepare two queries: ON-DUTY and OFF-DUTY
$subquery = "
    SELECT
      empno,
      MIN(CASE WHEN log_type = 'TIME IN'      THEN log_time END) AS time_in,
      MAX(CASE WHEN log_type = 'TIME OUT'     THEN log_time END) AS time_out,
      MIN(CASE WHEN log_type = 'LUNCH START'  THEN log_time END) AS lunch_start,
      MAX(CASE WHEN log_type = 'LUNCH END'    THEN log_time END) AS lunch_end,
      MIN(CASE WHEN log_type = 'BREAK START'  THEN log_time END) AS break_start,
      MAX(CASE WHEN log_type = 'BREAK END'    THEN log_time END) AS break_end
    FROM employee_logs
    WHERE log_date = CURDATE()
    GROUP BY empno
";

$sqlOn = "
  SELECT m.empno, m.lastName, m.firstName,
           l.time_in, l.time_out, l.lunch_start, l.lunch_end, l.break_start, l.break_end
    FROM manpoweremployee AS m
    JOIN ($subquery) AS l ON l.empno = m.empno
   WHERE m.empstatus = 'ACTIVE'
ORDER BY m.lastName
";

$sqlOff = "
  SELECT m.empno, m.lastName, m.firstName,
           NULL AS time_in, NULL AS time_out,
           NULL AS lunch_start, NULL AS lunch_end,
           NULL AS break_start, NULL AS break_end
    FROM manpoweremployee AS m
    LEFT JOIN ($subquery) AS l ON l.empno = m.empno
   WHERE m.empstatus = 'ACTIVE'
     AND l.empno IS NULL
ORDER BY m.lastName
";

// 4) Execute
$resultOn  = $conn->query($sqlOn)  or die("ON-DUTY Query Error: " . $conn->error);
$resultOff = $conn->query($sqlOff) or die("OFF-DUTY Query Error: " . $conn->error);

// 5) Build spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Main Title and Date
$sheet->mergeCells('A1:N1');
$sheet->setCellValue('A1', 'EMPLOYEE ATTENDANCE TODAY');
$sheet->getStyle('A1')->getFont()->setBold(true);

$sheet->mergeCells('A2:N2');
$sheet->setCellValue('A2', date('F d, Y'));
$sheet->getStyle('A2')->getFont()->setBold(true);


// 6) Header row at row 4
$headerRow = 4;
$headers = [
    'NO','EMP. NO','LAST NAME','FIRST NAME',
    'TIME-IN','TIME-OUT','LUNCH START','LUNCH END',
    'BREAK START','BREAK END',
    'HOURS WORKED','HOURS LUNCH','HOURS BREAK',
    'TOTAL HOURS'
];
foreach ($headers as $i => $hdr) {
    $sheet->setCellValueByColumnAndRow($i + 1, $headerRow, $hdr);
}
foreach (range(1, count($headers)) as $col) {
    $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
}

// Set width for Column A (approx. 34 pixels)
$sheet->getColumnDimension('A')->setAutoSize(false);
$sheet->getColumnDimension('A')->setWidth(5);


// Make headers bold and apply border
$headerStyle = [
    'font' => ['bold' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$sheet->getStyle('A'.$headerRow.':N'.$headerRow)->applyFromArray($headerStyle);

// Center entire columns (for data rows below headers) to include 'N'
$sheet->getStyle('B')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Entire 'EMP. NO' column
$sheet->getStyle('E:N')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Entire columns E through N

// Explicitly center the relevant header cells (A4, B4, E4:N4)
$sheet->getStyle('A'.$headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // NO header (A4)
$sheet->getStyle('B'.$headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // EMP. NO header (B4)
$sheet->getStyle('E'.$headerRow.':N'.$headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);


// 7) Start writing data one row below the header
$rowNum = $headerRow + 1;

// Section A: ON-DUTY
// Make section title bold
$onDutyTitleCell = "A{$rowNum}";
$sheet->setCellValue($onDutyTitleCell, 'A. ON-DUTY EMPLOYEES');
$sheet->getStyle($onDutyTitleCell)->getFont()->setBold(true);

$rowNum++;
$onDutyDataStartRow = $rowNum;
$ctrA = 1;
while ($r = $resultOn->fetch_assoc()) {
    // Set and center cell in Column A
    $cellA = 'A'.$rowNum;
    $sheet->setCellValue($cellA, $ctrA++);
    $sheet->getStyle($cellA)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // basic columns
    $sheet->setCellValueByColumnAndRow(2,  $rowNum, $r['empno']);
    $sheet->setCellValueByColumnAndRow(3,  $rowNum, $r['lastName']);
    $sheet->setCellValueByColumnAndRow(4,  $rowNum, $r['firstName']);
    $sheet->setCellValueByColumnAndRow(5,  $rowNum, $r['time_in']);
    $sheet->setCellValueByColumnAndRow(6,  $rowNum, $r['time_out']);
    $sheet->setCellValueByColumnAndRow(7,  $rowNum, $r['lunch_start']);
    $sheet->setCellValueByColumnAndRow(8,  $rowNum, $r['lunch_end']);
    $sheet->setCellValueByColumnAndRow(9,  $rowNum, $r['break_start']);
    $sheet->setCellValueByColumnAndRow(10, $rowNum, $r['break_end']);

    // Hours Worked = Time-Out – Time-In or zero
    $sheet->setCellValueByColumnAndRow(
        11, $rowNum,
        '=IF(AND(E'.$rowNum.'<>"",F'.$rowNum.'<>""),F'.$rowNum.'-E'.$rowNum.',0)'
    );
    $sheet->getStyleByColumnAndRow(11, $rowNum)
           ->getNumberFormat()
           ->setFormatCode('[h]:mm;;"-";@');

    // Lunch Hours = Lunch-End – Lunch-Start or zero
    $sheet->setCellValueByColumnAndRow(
        12, $rowNum,
        '=IF(AND(G'.$rowNum.'<>"",H'.$rowNum.'<>""),H'.$rowNum.'-G'.$rowNum.',0)'
    );
    $sheet->getStyleByColumnAndRow(12, $rowNum)
           ->getNumberFormat()
           ->setFormatCode('[h]:mm;;"-";@');

    // Break Hours = Break-End – Break-Start or zero
    $sheet->setCellValueByColumnAndRow(
        13, $rowNum,
        '=IF(AND(I'.$rowNum.'<>"",J'.$rowNum.'<>""),J'.$rowNum.'-I'.$rowNum.',0)'
    );
    $sheet->getStyleByColumnAndRow(13, $rowNum)
           ->getNumberFormat()
           ->setFormatCode('[h]:mm;;"-";@');

    // Total Hours = K - L - M
    $sheet->setCellValueByColumnAndRow(
        14, $rowNum,
        '=K'.$rowNum.'-L'.$rowNum.'-M'.$rowNum // Formula: Hours Worked - Lunch Hours - Break Hours
    );
    $sheet->getStyleByColumnAndRow(14, $rowNum)
           ->getNumberFormat()
           ->setFormatCode('[h]:mm;;"-";@'); // Apply time format


    $rowNum++;
}

// Apply border to ON-DUTY data rows
if ($resultOn->num_rows > 0) {
    $onDutyDataEndRow = $rowNum - 1;
    $sheet->getStyle('A'.$onDutyDataStartRow.':N'.$onDutyDataEndRow)
           ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}


// 8) Blank row then Section B: OFF-DUTY/ABSENT
$rowNum++;

// Make section title bold
$offDutyTitleCell = "A{$rowNum}";
$sheet->setCellValue($offDutyTitleCell, 'B. OFF-DUTY / ABSENT');
$sheet->getStyle($offDutyTitleCell)->getFont()->setBold(true);

$rowNum++;
$offDutyDataStartRow = $rowNum;
$ctrB = 1;
while ($r = $resultOff->fetch_assoc()) {
    // Set and center cell in Column A
    $cellA = 'A'.$rowNum;
    $sheet->setCellValue($cellA, $ctrB++);
    $sheet->getStyle($cellA)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    $sheet->setCellValueByColumnAndRow(2, $rowNum, $r['empno']);
    $sheet->setCellValueByColumnAndRow(3, $rowNum, $r['lastName']);
    $sheet->setCellValueByColumnAndRow(4, $rowNum, $r['firstName']);
    
    // NEW: Remove contents for columns E to N for OFF-DUTY employees
    // Columns E-J are already NULL from query, so they will appear blank.
    // Explicitly setting K-N to empty string to ensure no formulas or zeros appear.
    $sheet->setCellValueByColumnAndRow(11, $rowNum, ''); // K: HOURS WORKED
    $sheet->setCellValueByColumnAndRow(12, $rowNum, ''); // L: HOURS LUNCH
    $sheet->setCellValueByColumnAndRow(13, $rowNum, ''); // M: HOURS BREAK
    $sheet->setCellValueByColumnAndRow(14, $rowNum, ''); // N: TOTAL HOURS

    $rowNum++;
}

// Apply border to OFF-DUTY data rows
if ($resultOff->num_rows > 0) {
    $offDutyDataEndRow = $rowNum - 1;
    // MODIFIED: Apply borders only to A:D for the OFF-DUTY table
    $sheet->getStyle('A'.$offDutyDataStartRow.':D'.$offDutyDataEndRow)
           ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
}


// 9) Send to browser
$filename = 'mpc_attendance_' . date('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->setUseDiskCaching(true, getSpreadsheetExportTempDirectory());
$writer->save('php://output');
exit;
