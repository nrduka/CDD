<?php
// Isama ang database connection file
require_once 'database-config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kunin ang empno, date, at schedule mula sa request body
    $data = json_decode(file_get_contents('php://input'), true);
    $empno = $data['empno'];
    $date = $data['date'];
    $schedule = $data['schedule'];
    $schedid = $empno . '-' . $date; // Create schedid by concatenating empno and date

    // Siguraduhin na hindi empty ang schedule
    if (empty($schedule)) {
        echo json_encode(['success' => false, 'message' => 'Schedule cannot be empty']);
        exit;
    }

    // Query para i-update ang schedule at schedid
    $query = "UPDATE scheduling SET schedule = ?, schedid = ? WHERE empno = ? AND emp_sched = ?";
    
    // Gamitin ang prepared statement
    if ($stmt = $conn->prepare($query)) {
        // I-bind ang mga parameter sa prepared statement
        $stmt->bind_param('ssss', $schedule, $schedid, $empno, $date);
        
        // I-execute ang query
        if ($stmt->execute()) {
            // Kung successful, mag-send ng JSON response
            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
        } else {
            // Kung may error sa query execution
            echo json_encode(['success' => false, 'message' => 'Error updating schedule']);
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
