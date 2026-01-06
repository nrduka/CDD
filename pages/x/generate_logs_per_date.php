<?php



require_once ($_SERVER["DOCUMENT_ROOT"] . "../mpcems/Classes/PHPExcel.php");

$con = mysqli_connect("localhost","root","","mpc_ems");

if(!$con){
    echo mysqli_error($con);
    exit;
}


$excel = new PHPExcel();


$start_date = date("Y-m-d", strtotime($_POST["startDate"]));
$end_date = date("Y-m-d", strtotime($_POST["endDate"]));





//PAGE SETTINGS
$excel->setActiveSheetIndex(0);

$highestColumn = $excel->setActiveSheetIndex(0)->getHighestColumn();
$highestRow = $excel->setActiveSheetIndex(0)->getHighestRow(); 


$excel->getActiveSheet()->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
$excel->getActiveSheet()->getPageSetup()->setPaperSize(PHPExcel_Worksheet_PageSetup::PAPERSIZE_FOLIO);
$excel->getActiveSheet()->getPageSetup()->setFitToPage(true);
$excel->getActiveSheet()->getPageSetup()->setFitToWidth(1);
$excel->getActiveSheet()->getPageSetup()->setFitToHeight(0);
$excel->getActiveSheet()
    ->getPageMargins()->setTop(0.2);
$excel->getActiveSheet()
    ->getPageMargins()->setRight(0.25);
$excel->getActiveSheet()
    ->getPageMargins()->setLeft(0.25);
$excel->getActiveSheet()
    ->getPageMargins()->setBottom(0);
$excel->getActiveSheet()->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1,7);



//PAGE SETTINGS
for($col_AC = 'B'; $col_AC !== 'M'; $col_AC++) {
    $excel->getActiveSheet()->getColumnDimension($col_AC)->setAutoSize(true);

  // $excel ->getActiveSheet()->getColumnDimension($col_AC)->setWidth(12);
}


$excel->setActiveSheetIndex(0);


$excel->getActiveSheet()->setCellValue('A1', 'SIERRA PINES BAGUIO INC.');
$excel->getActiveSheet()->setCellValue('A2', 'LOGS PER DATE');

  
$excel->getActiveSheet()->setTitle('1');



$excel->setActiveSheetIndex(0);


// ✅ Create Excel sheet
$excel = new PHPExcel();
$sheet = $excel->getActiveSheet();


 $log_dates_query=mysqli_query($con,"SELECT DISTINCT(log_date) FROM employee_logs 
                     WHERE log_date BETWEEN '$start_date' AND '$end_date' 
                     ORDER BY log_date ASC");



    $startCol = 3; // Column C = index 3 (1-based for readability)
    $row = 6;
    $colIndex = $startCol;

    while ($log_dates_data = mysqli_fetch_object($log_dates_query)) {
        $columnLetter = PHPExcel_Cell::stringFromColumnIndex($colIndex - 1);
        $excel->getActiveSheet()->setCellValue($columnLetter . $row, $log_dates_data->log_date);
        $colIndex+=8;
    }



 $log_day_name_query=mysqli_query($con,"SELECT DISTINCT(log_date), DAYNAME(log_date) AS day_name FROM employee_logs 
                     WHERE log_date BETWEEN '$start_date' AND '$end_date' 
                     ORDER BY log_date ASC");



    $startCol = 3; // Column C = index 3 (1-based for readability)
    $row = 5;
    $colIndex = $startCol;

    while ($log_day_name_data = mysqli_fetch_object($log_day_name_query)) {
        $columnLetter = PHPExcel_Cell::stringFromColumnIndex($colIndex - 1);
        $excel->getActiveSheet()->setCellValue($columnLetter . $row, $log_day_name_data->day_name);
        $colIndex+=8;
    }







// ✅ Get only log_dates that actually exist between the range
$date_query = mysqli_query($con, "
    SELECT DISTINCT(log_date) AS log_date 
    FROM employee_logs 
    WHERE log_date BETWEEN '$start_date' AND '$end_date'
    ORDER BY log_date ASC
");

// ✅ Prepare dynamic pivot columns
$log_types = ['TIME IN', 'LUNCH IN', 'LUNCH OUT', 'TIME OUT'];
$log_columns = [];
$log_columns_alias = [];

while ($row = mysqli_fetch_array($date_query)) {
    $log_date = $row['log_date'];
    foreach ($log_types as $type) {
        // $alias = "d" . str_replace("-", "_", $log_date) . "_" . str_replace(" ", "_", strtolower($type));
        $alias = str_replace(" ", " ",($type));
        $log_columns[] = "MAX(CASE WHEN log_date = '$log_date' AND log_type = '$type' THEN log_time END) AS `$alias`";
        $log_columns_alias[] = $alias;
    }
}

$log_columns_sql = implode(",", $log_columns);

// ✅ Run pivot query
$query_employee_logs = mysqli_query($con, "
    SELECT empno, $log_columns_sql
    FROM employee_logs
    WHERE log_date BETWEEN '$start_date' AND '$end_date'
    GROUP BY empno
");

// // ✅ Create Excel sheet
// $excel = new PHPExcel();
// $sheet = $excel->getActiveSheet();

// ✅ Header row
$row_index = 7;
$col_index = 0;
$sheet->setCellValueByColumnAndRow($col_index++, $row_index, "EMPLOYEE NO");

foreach ($log_columns_alias as $alias) {
    $sheet->setCellValueByColumnAndRow($col_index++, $row_index, $alias);
}

// ✅ Data rows
$row_index = 8;
while ($row_data = mysqli_fetch_object($query_employee_logs)) {
    $col_index = 0;
    $sheet->setCellValueByColumnAndRow($col_index++, $row_index, $row_data->empno);
    foreach ($log_columns_alias as $alias) {
        $sheet->setCellValueByColumnAndRow($col_index++, $row_index, $row_data->$alias);
    }
    $row_index++;
}




$filename = "log_per_date".".xlsx";
header('Content-Disposition: attachment; filename="' . $filename . '"');

$file=PHPExcel_IOFactory::createWriter($excel,'Excel2007');
$file->save('php://output');

?>