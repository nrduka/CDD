<?php

require('mysql_table.php');
include("dbconnection.php");

class PDF extends PDF_MySQL_Table
{
    function Header()
    {
        $this->SetFillColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 9);
        parent::Header();
    }

    function Footer()
    {
        $this->SetY(-10);
        $this->SetFont('Courier','I',10);
    }

    function WordWrap(&$text, $maxwidth)
    {
        $text = trim($text);
        if ($text==='') return 0;

        $space = $this->GetStringWidth(' ');
        $lines = explode("\n", $text);
        $text = '';
        $count = 0;

        foreach ($lines as $line)
        {
            $words = preg_split('/ +/', $line);
            $width = 0;

            foreach ($words as $word)
            {
                $wordwidth = $this->GetStringWidth($word);
                if ($wordwidth > $maxwidth)
                {
                    for($i=0; $i<strlen($word); $i++)
                    {
                        $charwidth = $this->GetStringWidth(substr($word, $i, 1));
                        if($width + $charwidth <= $maxwidth)
                        {
                            $width += $charwidth;
                            $text .= substr($word, $i, 1);
                        }
                        else
                        {
                            $width = $charwidth;
                            $text = rtrim($text)."\n".substr($word, $i, 1);
                            $count++;
                        }
                    }
                }
                elseif($width + $wordwidth <= $maxwidth)
                {
                    $width += $wordwidth + $space;
                    $text .= $word.' ';
                }
                else
                {
                    $width = $wordwidth + $space;
                    $text = rtrim($text)."\n".$word.' ';
                    $count++;
                }
            }
            $text = rtrim($text)."\n";
            $count++;
        }
        $text = rtrim($text);
        return $count;
    }
}




function drawTableHeader($pdf, $headers, $widths, $startX, $startY, $rowHeight) {
    $pdf->SetFont('Courier', 'B', 10);
    $x = $startX;
    foreach ($headers as $i => $header) {
        $pdf->SetXY($x, $startY);
        $pdf->drawTextBox($header, $widths[$i], $rowHeight, 'C', 'M');
        $x += $widths[$i];
    }
    $pdf->SetFont('Courier', '', 10);
}



$pdf = new PDF('P','mm',array(220,140));
$pdf->AliasNbPages();
$pdf->SetMargins(3, 3, 3);
$pdf->SetAutoPageBreak(false);
$link = mysqli_connect("localhost", "root", "", "mpc_ems");




$startdate = $_GET["startdate"];
$enddate = $_GET["enddate"];

$query = "
    SELECT scheduling.empno, firstName, middleName, lastName, department, emp_sched, schedule, start_time, end_time
    FROM scheduling
    LEFT JOIN manpoweremployee ON manpoweremployee.empno = scheduling.empno
    LEFT JOIN shift_templates ON shift_templates.shift_name = scheduling.schedule
    WHERE emp_sched BETWEEN '$startdate' AND '$enddate'
    AND manpoweremployee.empstatus = 'active' and scheduling.status=0
    ORDER BY scheduling.empno ASC, emp_sched ASC
";

$result = mysqli_query($link, $query);

$current_empno = '';
$schedules_by_emp = [];

while ($row = mysqli_fetch_assoc($result)) {
    $empno = $row['empno'];

    if (!isset($schedules_by_emp[$empno])) {
        $schedules_by_emp[$empno] = [
            'employeeName' => $row['firstName'] . ' ' . $row['lastName'],
            // 'department' => $row['department'],
            'schedules' => []
        ];
    }

    $schedules_by_emp[$empno]['schedules'][] = [
        'Date' => $row['emp_sched'],
        'Time' => date("h:i a", strtotime($row["start_time"])) . ' - ' . date("h:i a", strtotime($row["end_time"])),
        'Schedule' => $row['schedule']
    ];
}

$formatted_start_date = date("F j", strtotime($startdate));
$formatted_end_date = date("F j, Y", strtotime($enddate));

foreach ($schedules_by_emp as $empno => $emp_data) {
    $pdf->AddPage('L');

    $pdf->SetXY(1, 2);
    $logoPath = __DIR__ . '/../assets/images/company-logos/header_logo.jpg';
    $pdf->Image($logoPath, 54.5, 6, 30);

    $pdf->SetFont('Courier','B',10);
    $pdf->SetXY(5,28); $pdf->Write(2, 'Employee No :');
    $pdf->SetXY(38,28); $pdf->Write(2, $empno);
    $pdf->SetXY(5,34); $pdf->Write(2, 'Name :');
    $pdf->SetXY(38,34); $pdf->Write(2, $emp_data['employeeName']);
    $pdf->SetXY(5,40); $pdf->Write(2, 'Department :');
    // $pdf->SetXY(38,40); $pdf->Write(2, $emp_data['department']);
    $pdf->SetXY(5,46); $pdf->Write(2, 'Date :');
    $pdf->SetXY(38,46); $pdf->Write(2, $formatted_start_date . ' - ' . $formatted_end_date);
    $pdf->SetXY(5,52); $pdf->Write(2, 'Total Days :');
    $pdf->SetXY(38,52); $pdf->Write(2, count($emp_data['schedules']));

    $pdf->SetFont('Courier', 'B', 10);
    $startX = 5;
    $startY = 60;
    $rowHeight = 10;
    $bottomMargin = 10;
    $pageHeightLimit = $pdf->h - $bottomMargin;
    $widths = [15, 30, 50, 35];
    $headers = ['#', 'Date', 'Time', 'Schedule'];

    drawTableHeader($pdf, $headers, $widths, $startX, $startY, $rowHeight);
    $startY += $rowHeight;
    $pdf->SetFont('Courier', '', 10);

    $row_no = 1;
    foreach ($emp_data['schedules'] as $row_data) {
        if ($startY + $rowHeight > $pageHeightLimit) {
            $pdf->AddPage('L');
            $startY = 10;
            drawTableHeader($pdf, $headers, $widths, $startX, $startY, $rowHeight);
            $startY += $rowHeight;
        }

        $x = $startX;
        $pdf->SetXY($x, $startY);
        $pdf->drawTextBox($row_no++, $widths[0], $rowHeight, 'C', 'M');
        $x += $widths[0];

        foreach (['Date', 'Time', 'Schedule'] as $i => $key) {
            $pdf->SetXY($x, $startY);
            $pdf->drawTextBox($row_data[$key], $widths[$i+1], $rowHeight, 'C', 'M');
            $x += $widths[$i+1];
        }

        $startY += $rowHeight;
    }

    // Certified By footer
    $pdf->SetFont('Courier','',10);
    $pdf->Cell(0);
    $pdf->Write(5,'Certified By:');
    $pdf->Ln();

    $pdf->Ln(7);
    $pdf->SetFont('Courier','',10);
    $pdf->Cell(0);
    $pdf->Write(0,'_______________________________');
    $pdf->Ln(5);
    
    $pdf->Ln();
    $pdf->SetFont('Courier','',10);
    $pdf->Cell(18);
    $pdf->Write(5,'HR Department');
}

$pdf->Output();

?>
