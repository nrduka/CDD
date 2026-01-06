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

$pdf = new PDF('P','mm',array(220,140));
$pdf->AliasNbPages();
$pdf->SetMargins(3, 3, 3);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage('L');

$empno = $_GET["empno"];
$startdate = $_GET["startdate"];
$enddate = $_GET["enddate"];
$no_of_sched = $_GET["no_of_sched"];

$link = mysqli_connect("localhost", "root", "", "mpc_ems");

$query = "SELECT 
    @row_number:=@row_number + 1 AS row_no, 
    v_scheduling.empno, 
    employees.firstName, 
    employees.middleName, 
    employees.lastName, 
    employees.department, 
    v_scheduling.date_duty, 
    v_scheduling.shift_name, 
    v_scheduling.start_time, 
    v_scheduling.end_time
FROM v_scheduling
JOIN (SELECT @row_number:=0) AS t
LEFT JOIN (
    SELECT empno, firstName, middleName, lastName, department, empstatus 
    FROM manpoweremployee
) AS employees 
    ON employees.empno = v_scheduling.empno
WHERE v_scheduling.date_duty BETWEEN '2025-10-01' AND '2025-10-02' 
  AND v_scheduling.empno='MPC-04-00340' 
  AND v_scheduling.status=0
  AND employees.empstatus='active'
ORDER BY v_scheduling.date_duty ASC";

$result_header = mysqli_query($link, $query);
$result = mysqli_query($link, $query);

$pdf->SetXY(1, 2);
$logoPath = __DIR__ . '/../assets/images/company-logos/header_logo.jpg';
$pdf->Image($logoPath, 54.5, 6, 30);

$employeeName = '';
while ($row_header = mysqli_fetch_array($result_header)) {
    $empno = $row_header['empno'];
    $employeeName = $row_header['firstName'] . ' ' . $row_header['lastName'];
    // $department = $row_header['department'];
    break;
}

$pdf->SetFont('Courier','B',10);
$pdf->SetXY(5,28); $pdf->Write(2, 'Employee No :');
$pdf->SetXY(38,28); $pdf->Write(2, $empno);
$pdf->SetXY(5,34); $pdf->Write(2, 'Name :');
$pdf->SetXY(38,34); $pdf->Write(2, $employeeName);
$pdf->SetXY(5,40); $pdf->Write(2, 'Department :');
// $pdf->SetXY(38,40); $pdf->Write(2, $department);
$formatted_start_date = date("F j", strtotime($startdate));
$formatted_end_date = date("F j, Y", strtotime($enddate));
$pdf->SetXY(5,46); $pdf->Write(2, 'Date :');
$pdf->SetXY(38,46); $pdf->Write(2, $formatted_start_date . ' - ' . $formatted_end_date);
$pdf->SetXY(5,52); $pdf->Write(2, 'Total Days :');
$pdf->SetXY(38,52); $pdf->Write(2, $no_of_sched);

// Table setup
$schedules = [];
while ($row = mysqli_fetch_array($result)) {
    $row_no = $row['row_no'];
    $date_duty = $row['date_duty'];
    $shift_name = $row['shift_name'];
    $time_sched = date("h:i a", strtotime($row["start_time"])) . ' - ' . date("h:i a", strtotime($row["end_time"]));

    $schedules[] = [
        '#' => $row_no,
        'Date' => $date_duty,
        'Time' => $time_sched,
        'Schedule' => $shift_name
    ];
}

$pdf->SetFont('Courier', 'B', 10);
$startX = 5;
$startY = 60;
$rowHeight = 10;
$bottomMargin = 10;
$pageHeightLimit = $pdf->h - $bottomMargin;
$widths = [15, 30, 50, 35];
$headers = ['#', 'Date', 'Time', 'Schedule'];

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



drawTableHeader($pdf, $headers, $widths, $startX, $startY, $rowHeight);
$startY += $rowHeight;
$pdf->SetFont('Courier', '', 10);

// Draw table rows
foreach ($schedules as $row_data) {
    if ($startY + $rowHeight > $pageHeightLimit) {
        $pdf->AddPage('L');
        $startY = 10; // top margin on new page
        drawTableHeader($pdf, $headers, $widths, $startX, $startY, $rowHeight);
        $startY += $rowHeight;
    }

    $x = $startX;
    foreach ($headers as $i => $key) {
        $pdf->SetXY($x, $startY);
        $pdf->drawTextBox($row_data[$key], $widths[$i], $rowHeight, 'C', 'M');
        $x += $widths[$i];
    }
    $startY += $rowHeight;
}

    $pdf->SetFont('Courier','',10);
    $pdf->Cell(0);
    $pdf->Write(10,'Certified By:');
    $pdf->Ln();


    $pdf->Ln(7);
    $pdf->SetFont('Courier','',10);
    $pdf->Cell(0);
    $pdf->Write(0,'_______________________________');//ops
    $pdf->Ln(5);
    

    $pdf->Ln();
    $pdf->SetFont('Courier','',10);
    $pdf->Cell(18);
    $pdf->Write(5,'HR Department'); 


$pdf->Output();
?>
