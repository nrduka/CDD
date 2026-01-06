<?php
// File: print_attendance_period_by_employee.php

require __DIR__ . '/database-config.php';
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Get parameters
$from  = isset($_GET['from']) ? $_GET['from'] : '';
$to    = isset($_GET['to'])   ? $_GET['to']   : '';
$empno = isset($_GET['empno']) ? $_GET['empno'] : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    die("Invalid date format. Use YYYY-MM-DD.");
}
if (!preg_match('/^[A-Za-z0-9_-]+$/', $empno)) {
    die("Invalid or missing empno.");
}

$start = date('M j', strtotime($from));
$end = date('M j, Y', strtotime($to));
$period = "$start to $end";

// Fetch employee logs
$sql = "
  SELECT
    m.empno,
    m.firstName,
    m.lastName,
    m.position,
    s.duty_start,
    s.duty_end,
    l.log_date,
    MIN(CASE WHEN log_type='TIME IN'     THEN log_time END) AS time_in,
    MAX(CASE WHEN log_type='TIME OUT'    THEN log_time END) AS time_out,
    MIN(CASE WHEN log_type='LUNCH START' THEN log_time END) AS lunch_start,
    MAX(CASE WHEN log_type='LUNCH END'   THEN log_time END) AS lunch_end,
    MIN(CASE WHEN log_type='BREAK START' THEN log_time END) AS break_start,
    MAX(CASE WHEN log_type='BREAK END'   THEN log_time END) AS break_end
  FROM employee_logs l
  JOIN manpoweremployee m ON m.empno = l.empno
  LEFT JOIN manpowerdutyschedule s ON s.empno = l.empno AND s.date_duty = l.log_date
  WHERE l.log_date BETWEEN '$from' AND '$to'
    AND m.empstatus = 'ACTIVE'
    AND m.empno = '$empno'
  GROUP BY l.empno, l.log_date
  ORDER BY l.log_date ASC
";
$res = $conn->query($sql) or die("Query Error: " . $conn->error);

// Collect data
$rows = [];
$totals = [
  'worked'    => 0,
  'lunch'     => 0,
  'break'     => 0,
  'net'       => 0,
  'decimal'   => 0,
  'tardiness' => 0,
  'overtime'  => 0,
];
$employee = [];

while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
    if (empty($employee)) {
        $employee = [
            'empno'     => $r['empno'],
            'firstName' => $r['firstName'],
            'lastName'  => $r['lastName'],
            'position'  => $r['position'],
        ];
    }
}

// Spreadsheet setup
$ss = new Spreadsheet();
$ss->removeSheetByIndex(0);
$details = $ss->createSheet(0);
$details->setTitle('Details');

// Headers
$details->setCellValue('A1', 'DETAILED EMPLOYEE ATTENDANCE REPORT');
$details->setCellValue('A2', 'EMPLOYEE: ' . $employee['firstName'] . ' ' . $employee['lastName']);
$details->setCellValue('A3', 'POSITION: ' . $employee['position']);
$details->setCellValue('A4', 'FOR THE PERIOD: ' . $period);
$details->getStyle('A1:A4')->getFont()->setSize(14)->setBold(true);

// Column headers
$hdr = [
  'NO','EMP. NO','LAST NAME','FIRST NAME','POSITION',
  'SCHEDULE START','SCHEDULE END','LOG DATE',
  'TIME-IN','TIME-OUT','LUNCH START','LUNCH END','BREAK START','BREAK END',
  'HOURS WORKED','HOURS LUNCH','HOURS BREAK','NET HOURS',
  'NET DECIMAL','TARDINESS DECIMAL','OVERTIME DECIMAL'
];
$dr = 6;
foreach ($hdr as $i => $h) {
    $details->setCellValueByColumnAndRow($i + 1, $dr, $h);
    $details->getColumnDimensionByColumn($i + 1)->setAutoSize(true);
}
$lastCol = Coordinate::stringFromColumnIndex(count($hdr));
$details->getStyle("A{$dr}:{$lastCol}{$dr}")->applyFromArray([
    'font' => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
]);

$row = $dr + 1;
$ctr = 1;
foreach ($rows as $r) {
    $diff = function($s, $e, $d) {
        if (!$s || !$e) return 0;
        $a = new DateTime("$d $s");
        $b = new DateTime("$d $e");
        if ($b < $a) $b->modify('+1 day');
        return ($b->getTimestamp() - $a->getTimestamp()) / 86400;
    };

    $worked = $diff($r['time_in'], $r['time_out'], $r['log_date']);
    $lunch  = $diff($r['lunch_start'], $r['lunch_end'], $r['log_date']);
    $break  = $diff($r['break_start'], $r['break_end'], $r['log_date']);
    $net    = $worked - $lunch - $break;
    $decimal = $net * 24;

    $tardy = 0;
    if ($r['duty_start'] && $r['time_in']) {
        $sched = new DateTime("{$r['log_date']} {$r['duty_start']}");
        $in = new DateTime("{$r['log_date']} {$r['time_in']}");
        if ($in > $sched) {
            $tardy = ($in->getTimestamp() - $sched->getTimestamp()) / 86400 * 24;
        }
    }

    $ot = 0;
    if ($r['duty_end'] && $r['time_out']) {
        $sched = new DateTime("{$r['log_date']} {$r['duty_end']}");
        $out = new DateTime("{$r['log_date']} {$r['time_out']}");
        if ($out > $sched) {
            $ot = ($out->getTimestamp() - $sched->getTimestamp()) / 86400 * 24;
        }
    }

    $details->fromArray([
        $ctr++, $r['empno'], $r['lastName'], $r['firstName'], $r['position'],
        $r['duty_start'], $r['duty_end'], $r['log_date'],
        $r['time_in'], $r['time_out'], $r['lunch_start'], $r['lunch_end'],
        $r['break_start'], $r['break_end'],
        $worked, $lunch, $break, $net,
        $decimal, $tardy, $ot
    ], null, "A{$row}");

    // Format duration columns
    foreach (['O','P','Q','R'] as $col) {
        $details->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('[h]:mm:ss;;"-";@');
    }
    foreach (['S','T','U'] as $col) {
        $details->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('0.00');
    }

    // Totals
    $totals['worked']    += $worked;
    $totals['lunch']     += $lunch;
    $totals['break']     += $break;
    $totals['net']       += $net;
    $totals['decimal']   += $decimal;
    $totals['tardiness'] += $tardy;
    $totals['overtime']  += $ot;

    $row++;
}

// Subtotals row
$details->mergeCells("A{$row}:N{$row}");
$details->setCellValue("A{$row}", "TOTAL");
$details->getStyle("A{$row}")->getFont()->setBold(true);

$columns = ['O','P','Q','R','S','T','U'];
$keys = ['worked','lunch','break','net','decimal','tardiness','overtime'];
foreach ($columns as $i => $col) {
    $details->setCellValue("{$col}{$row}", $totals[$keys[$i]]);
    $format = in_array($col, ['S','T','U']) ? '0.00' : '[h]:mm:ss;;"-";@';
    $details->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($format);
    $details->getStyle("{$col}{$row}")->getFont()->setBold(true);
}

// Borders
$details->getStyle("A{$dr}:U{$row}")
        ->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

// Output
$filename = "EMPLOYEE_ATTENDANCE_{$empno}_{$from}_TO_{$to}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
