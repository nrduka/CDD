<?php
session_id('codexcust2');
session_start();
$_SESSION['sess_userrole']='ADMIN';
session_write_close();
$_COOKIE[session_name()]='codexcust2';
$_GET['start']='2026-03-01';
$_GET['end']='2026-03-16';
include __DIR__ . '/pages/print_todate_daily_sales_amount_summary_customer.php';
