<?php
// File: C:\xampp\htdocs\MPCEMS\pages\export_attendance_period.php

require __DIR__ . '/database-config.php';
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// 1) Date-range
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to   = isset($_GET['to'])   ? $_GET['to']   : '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    die("Invalid date format. Use YYYY-MM-DD.");
}
$start  = date('M j',   strtotime($from));
$end    = date('M j, Y',strtotime($to));
$period = "$start to $end";

// 2) Fetch rows (with schedule)
$sql = "
  SELECT
    m.empno,
    m.lastName,
    m.firstName,
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
  LEFT JOIN manpowerdutyschedule s
    ON s.empno     = l.empno
   AND s.date_duty = l.log_date
  WHERE l.log_date BETWEEN '$from' AND '$to'
    AND m.empstatus = 'ACTIVE'
  GROUP BY l.empno, l.log_date
  ORDER BY FIELD(m.position,
    'RESTAURANT MANAGER OIC','Assistant RESTAURANT MANAGER',
    'Cashier','KITCHEN LEAD','ASST.KITCHEN LEAD',
    'KITCHEN STAFF','SERVICE STAFF','STOCKMAN',
    'DELIVERY RIDER','UTILITY'
  ), m.empno, l.log_date
";
$res = $conn->query($sql) or die("Query Error: " . $conn->error);

// 3) Collect data + init totals
$rows   = [];
$emps   = [];
$totals = []; // empno => [worked,lunch,break,net,decimal,tardiness,overtime]
while ($r = $res->fetch_assoc()) {
    $rows[] = $r;
    $eno = $r['empno'];
    if (!isset($emps[$eno])) {
        $emps[$eno]    = [
            'lastName'  => $r['lastName'],
            'firstName' => $r['firstName'],
            'position'  => $r['position'],
        ];
        $totals[$eno] = [
          'worked'    => 0,
          'lunch'     => 0,
          'break'     => 0,
          'net'       => 0,
          'decimal'   => 0,
          'tardiness' => 0,
          'overtime'  => 0,
        ];
    }
}

// 4) Create workbook & sheets
$ss      = new Spreadsheet();
$summary = $ss->getActiveSheet(); $summary->setTitle('Summary');
$details = $ss->createSheet();       $details->setTitle('Details');

// 5) Header style
$headerStyle = [
    'font'      => ['bold'=>true],
    'alignment' => [
      'horizontal'=> Alignment::HORIZONTAL_CENTER,
      'vertical'  => Alignment::VERTICAL_CENTER,
    ],
    'borders'   => ['allBorders'=> ['borderStyle'=>Border::BORDER_THIN]],
];

// ─── SUMMARY sheet ────────────────────────────────────────────────────────────
$hdrSummary = [
  'NO','EMP. NO','LAST NAME','FIRST NAME','POSITION',
  'TOTAL WORKED','TOTAL LUNCH','TOTAL BREAK'
];
$sr = 5;
$summary->fromArray($hdrSummary, null, "A{$sr}");
foreach (range(2, count($hdrSummary)) as $c) {
    $summary->getColumnDimensionByColumn($c)->setAutoSize(true);
}
$lastSc = Coordinate::stringFromColumnIndex(count($hdrSummary));
$summary->getStyle("A{$sr}:{$lastSc}{$sr}")->applyFromArray($headerStyle);

// ─── DETAILS sheet ────────────────────────────────────────────────────────────
$hdrDetail = [
  'NO','EMP. NO','LAST NAME','FIRST NAME','POSITION',
  'SCHEDULE START','SCHEDULE END','LOG DATE',
  'TIME-IN','TIME-OUT','LUNCH START','LUNCH END','BREAK START','BREAK END',
  'HOURS WORKED','HOURS LUNCH','HOURS BREAK','NET HOURS',
  'NET DECIMAL','TARDINESS DECIMAL','OVERTIME DECIMAL'
];
$dr = 5;
foreach ($hdrDetail as $i => $h) {
    $details->setCellValueByColumnAndRow($i+1, $dr, $h);
    $details->getColumnDimensionByColumn($i+2)->setAutoSize(true);
}
$details->getStyle('F:U')
        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$lastDc = Coordinate::stringFromColumnIndex(count($hdrDetail));
$details->getStyle("A{$dr}:{$lastDc}{$dr}")
        ->applyFromArray($headerStyle);

// 6) Write details + compute durations
$row        = $dr + 1;
$ctr        = 1;
$curEmp     = null;
$blockStart = $row;
$prevLast   = $prevFirst = '';

foreach ($rows as $r) {
    // when employee changes, write subtotal
    if ($curEmp !== null && $r['empno'] !== $curEmp) {
        writeSubtotal($details, $curEmp, $prevLast, $prevFirst, $blockStart, $row-1, $row);
        $row++;
        $ctr = 1;
        $blockStart = $row;
    }
    $curEmp    = $r['empno'];
    $prevLast  = $r['lastName'];
    $prevFirst = $r['firstName'];

    // raw values up to BREAK END
    $vals = [
      $ctr++, $r['empno'], $r['lastName'], $r['firstName'],
      $r['position'], $r['duty_start'], $r['duty_end'], $r['log_date'],
      $r['time_in'], $r['time_out'], $r['lunch_start'], $r['lunch_end'],
      $r['break_start'], $r['break_end'],
    ];
    foreach ($vals as $i => $v) {
        $details->setCellValueByColumnAndRow($i+1, $row, $v);
    }

    // compute durations (fraction of day)
    $diff = function($s, $e, $d) {
        if (!$s || !$e) return 0;
        $a = new \DateTime("$d $s");
        $b = new \DateTime("$d $e");
        if ($b < $a) $b->modify('+1 day');
        return ($b->getTimestamp() - $a->getTimestamp()) / 86400;
    };
    $worked = $diff($r['time_in'],   $r['time_out'],  $r['log_date']);
    $lunch  = $diff($r['lunch_start'],$r['lunch_end'],  $r['log_date']);
    $break  = $diff($r['break_start'],$r['break_end'],  $r['log_date']);
    $net    = $worked - $lunch - $break;
    $decimal= $net * 24;  // Net hours in decimal

    // compute tardiness (days fraction → decimal hours)
    $tardyDays = 0;
    if ($r['duty_start'] && $r['time_in']) {
        $s = new \DateTime("{$r['log_date']} {$r['duty_start']}");
        $a = new \DateTime("{$r['log_date']} {$r['time_in']}");
        if ($a > $s) {
            $tardyDays = ($a->getTimestamp() - $s->getTimestamp()) / 86400;
        }
    }
    $tardyDecimal = $tardyDays * 24;

    // compute overtime (days fraction → decimal hours)
    $otDays = 0;
    if ($r['duty_end'] && $r['time_out']) {
        $sE = new \DateTime("{$r['log_date']} {$r['duty_end']}");
        $aE = new \DateTime("{$r['log_date']} {$r['time_out']}");
        if ($aE > $sE) {
            $otDays = ($aE->getTimestamp() - $sE->getTimestamp()) / 86400;
        }
    }
    $otDecimal = $otDays * 24;

    // accumulate totals
    $totals[$curEmp]['worked']    += $worked;
    $totals[$curEmp]['lunch']     += $lunch;
    $totals[$curEmp]['break']     += $break;
    $totals[$curEmp]['net']       += $net;
    $totals[$curEmp]['decimal']   += $decimal;
    $totals[$curEmp]['tardiness'] += $tardyDecimal;
    $totals[$curEmp]['overtime']  += $otDecimal;

    // write O–R time columns, S–U decimal columns
    $cells = [
      'O' => $worked,
      'P' => $lunch,
      'Q' => $break,
      'R' => $net,
      'S' => $decimal,
      'T' => $tardyDecimal,
      'U' => $otDecimal,
    ];
    foreach ($cells as $col => $val) {
        $details->setCellValue("{$col}{$row}", $val);
        if (in_array($col, ['S','T','U'])) {
            // decimal
            $details->getStyle("{$col}{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('0.00');
        } else {
            // show seconds
            $details->getStyle("{$col}{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('[h]:mm:ss;;"-";@');
        }
    }

    $row++;
}
// final subtotal for last emp
if ($curEmp !== null) {
    writeSubtotal($details, $curEmp, $prevLast, $prevFirst, $blockStart, $row-1, $row);
    $row++;
}

// borders around details
$details->getStyle("A{$dr}:{$lastDc}{$row}")
        ->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

// ─── Fill SUMMARY ──────────────────────────────────────────────────────────────
$r = $sr + 1;
$n = 1;
foreach ($emps as $eno => $nm) {
    $summary->setCellValue("A{$r}", $n++);
    $summary->setCellValue("B{$r}", $eno);
    $summary->setCellValue("C{$r}", $nm['lastName']);
    $summary->setCellValue("D{$r}", $nm['firstName']);
    $summary->setCellValue("E{$r}", $nm['position']);
    $summary->setCellValue("F{$r}", $totals[$eno]['worked']);
    $summary->setCellValue("G{$r}", $totals[$eno]['lunch']);
    $summary->setCellValue("H{$r}", $totals[$eno]['break']);
    foreach (['F','G','H'] as $col) {
        $summary->getStyle("{$col}{$r}")
                ->getNumberFormat()
                ->setFormatCode('[h]:mm:ss;;"-";@');
    }
    $r++;
}
$summary->getStyle("A{$sr}:{$lastSc}{$r}")
        ->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

// ─── Headers & Output ─────────────────────────────────────────────────────────
$summary->setCellValue('A1', 'EMPLOYEE ATTENDANCE REPORT SUMMARY');
$summary->setCellValue('A2', 'PERIOD: ' . $period);
$summary->getStyle('A1')->getFont()->setSize(14)->setBold(true);
$summary->getStyle('A2')->getFont()->setSize(14)->setBold(true);

$details->setCellValue('A1', 'DETAILED EMPLOYEE ATTENDANCE REPORT');
$details->setCellValue('A2', 'FOR THE PERIOD: ' . $period);
$details->getStyle('A1')->getFont()->setSize(14)->setBold(true);
$details->getStyle('A2')->getFont()->setSize(14)->setBold(true);

$fn = "EMPLOYEE_ATTENDANCE_{$from}_TO_{$to}.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$fn\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($ss);
$writer->save('php://output');
exit;

/**
 * Helper to write a subtotal row (merge A–H, sum cols O–U, format)
 */
function writeSubtotal($sheet, $eno, $last, $first, $s, $e, $dR) {
    $sheet->mergeCells("A{$dR}:H{$dR}");
    $sheet->setCellValue("A{$dR}", "Subtotal for {$eno} – {$last}, {$first}");
    $sheet->getStyle("A{$dR}")->getFont()->setBold(true);

    foreach (range(15,21) as $idx) {
        $col = Coordinate::stringFromColumnIndex($idx);
        $sheet->setCellValue("{$col}{$dR}", "=SUM({$col}{$s}:{$col}{$e})");
        if ($idx >= 19) {
            // decimal columns: S, T, U
            $sheet->getStyle("{$col}{$dR}")
                  ->getNumberFormat()
                  ->setFormatCode('0.00');
        } else {
            // time columns
            $sheet->getStyle("{$col}{$dR}")
                  ->getNumberFormat()
                  ->setFormatCode('[h]:mm:ss;;"-";@');
        }
        $sheet->getStyle("{$col}{$dR}")->getFont()->setBold(true);
    }
}
?>