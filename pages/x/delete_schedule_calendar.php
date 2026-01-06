<?php
// Include your database connection file
include 'database-config.php'; 

// Enable error reporting for debugging
ini_set('display_errors', 1); // Show errors for debugging
error_reporting(E_ALL); // Report all errors

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read the raw POST data (as JSON)
    $data = json_decode(file_get_contents("php://input"));

    // Check if the data is valid JSON
    if (!$data) {
        // If the JSON decoding fails, log and return a JSON error response
        error_log("Failed to decode JSON data.");
        echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
        exit;
    }

    // Extract empno and date from the JSON object
    $empno = $data->empno ?? '';  // Empno from the request
    $date = $data->date ?? '';    // Date from the request

    // Log the received data (for debugging)
    error_log("Received data - Empno: $empno, Date: $date");

    // Check if empno and date are empty
    if (empty($empno) || empty($date)) {
        error_log("Validation failed: Empno or Date is empty.");
        echo json_encode(['success' => false, 'message' => 'Employee number and date are required.']);
        exit;
    }

    // Prepare the DELETE query
    $delete_sql = "DELETE FROM scheduling WHERE empno = ? AND emp_sched = ?";
    $stmt = $conn->prepare($delete_sql);

    // Check if the statement is prepared correctly
    if (!$stmt) {
        error_log("Prepared statement failed: " . $conn->error);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare SQL query.']);
        exit;
    }

    // Bind the parameters to the SQL query
    $stmt->bind_param("ss", $empno, $date);

    // Execute the query and check the result
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully.']);
    } else {
        error_log("Execution failed: " . $stmt->error);
        echo json_encode(['success' => false, 'message' => 'Failed to delete schedule.']);
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
} else {
    // If the request is not POST, return an error
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
