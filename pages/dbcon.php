<?php
// Define database related variables.
$database = 'cdd';
$host = 'localhost';
$user = 'root';
$pass = '';

if (!isset($dbh) || !($dbh instanceof PDO)) {
    try {
        $dsn = "mysql:host={$host};dbname={$database};port=3306;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        $dbh = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        error_log('dbcon.php connection failed: ' . $e->getMessage());
        $dbh = null;
    }
}
?>
