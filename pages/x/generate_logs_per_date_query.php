<?php





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


	left join onbook_detailed_disbursement_register s
                  on odd.id = s.id

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


?>