<?php
// Isama ang database connection file
require_once 'database-config.php';


// Ipagpatuloy ang iyong delete query logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kunin ang empno at date mula sa request body
    $data = json_decode(file_get_contents('php://input'), true);
    $empno = $data['empno'];
    $date = $data['date'];

    // Query para i-delete ang schedule
    $query = "DELETE FROM scheduling WHERE empno = ? AND emp_sched = ?";
    
    // Gamitin ang prepared statement
    if ($stmt = $conn->prepare($query)) {
        // I-bind ang mga parameter sa prepared statement
        $stmt->bind_param('ss', $empno, $date);
        
        // I-execute ang query
        if ($stmt->execute()) {
            // Kung successful, mag-send ng JSON response
            echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
        } else {
            // Kung may error sa query execution
            echo json_encode(['success' => false, 'message' => 'Error deleting schedule']);
        }
        
        // Isara ang statement
        $stmt->close();
    } else {
        // Kung may error sa pag-prepare ng query
        echo json_encode(['success' => false, 'message' => 'Error preparing the query']);
    }

    // Isara ang connection
    $conn->close();
}
?>
