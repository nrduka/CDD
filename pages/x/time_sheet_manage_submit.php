<?php
session_start();
include 'dbconnection.php'; // Assumes $link = mysql_connect() etc.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected']) && is_array($_POST['selected'])) {
    
    foreach ($_POST['selected'] as $rowId) {

        // Sanitize input manually since PHP 5.0 has no filter_input
        $id = mysql_real_escape_string($rowId);
        $empno = mysql_real_escape_string($_POST['empno'][$id]);
        $log_date = mysql_real_escape_string($_POST['log_date'][$id]);
        $shift_name = mysql_real_escape_string($_POST['shift_name'][$id]);
        $duty_start = mysql_real_escape_string($_POST['duty_start'][$id]);
        $duty_end = mysql_real_escape_string($_POST['duty_end'][$id]);
        $time_in = mysql_real_escape_string($_POST['time_in'][$id]);
        $time_out = mysql_real_escape_string($_POST['time_out'][$id]);
        $lunch_start = mysql_real_escape_string($_POST['lunch_start'][$id]);
        $lunch_end = mysql_real_escape_string($_POST['lunch_end'][$id]);
        $break_start = mysql_real_escape_string($_POST['break_start'][$id]);
        $break_end = mysql_real_escape_string($_POST['break_end'][$id]);
        $total_hours = mysql_real_escape_string($_POST['total_hours'][$id]);
        $lunch_hours = mysql_real_escape_string($_POST['lunch_hours'][$id]);
        $break_hours = mysql_real_escape_string($_POST['break_hours'][$id]);
        $net_hours = mysql_real_escape_string($_POST['net_hours'][$id]);
        $net_decimal = mysql_real_escape_string($_POST['net_decimal'][$id]);
        $regular_decimal = mysql_real_escape_string($_POST['regular_decimal'][$id]);
        $regular_hours = mysql_real_escape_string($_POST['regular_hours'][$id]);
        $tardiness_decimal = mysql_real_escape_string($_POST['tardiness_decimal'][$id]);
        $overtime_decimal = mysql_real_escape_string($_POST['overtime_decimal'][$id]);
        $shift_type = mysql_real_escape_string($_POST['shift_type'][$id]);

        // Insert into approved_timesheet
        $insertQuery = "
            INSERT INTO approved_timesheet (
                empno, log_date, shift_name, duty_start, duty_end,
                time_in, time_out, lunch_start, lunch_end,
                break_start, break_end, total_hours, lunch_hours, break_hours,
                net_hours, net_decimal, regular_decimal, regular_hours,
                tardiness_decimal, overtime_decimal,shift_type
            ) VALUES (
                '$empno', '$log_date', '$shift_name', '$duty_start', '$duty_end',
                '$time_in', '$time_out', '$lunch_start', '$lunch_end',
                '$break_start', '$break_end', '$total_hours', '$lunch_hours', '$break_hours',
                '$net_hours', '$net_decimal', '$regular_decimal', '$regular_hours',
                '$tardiness_decimal', '$overtime_decimal','$shift_type'
            )
        ";

        mysql_query($insertQuery, $link) or die("Insert Error: " . mysql_error());
    }

    $empno = isset($_POST['empno']) ? urlencode(reset($_POST['empno'])) : '';
    header("Location: time_sheet_manage.php?empno=$empno&status=approved");
    exit();
} else {
    header("Location: time_sheet.php?status=empty");
    exit();
}
?>
