<?php
// Database connection details
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cdd";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure UTF-8 encoding for consistent JSON/API responses
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}
?>
