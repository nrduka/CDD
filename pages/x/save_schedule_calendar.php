<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'SPBI');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empno = $_POST['empno'];
    $schedule = $_POST['schedule'];
    $date = $_POST['date'];

    if (empty($empno) || empty($schedule) || empty($date)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    // Check if a schedule already exists for the employee and date
    $check_sql = "SELECT * FROM schedules WHERE empno = ? AND date = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $empno, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Update the existing schedule
        $update_sql = "UPDATE scheduling SET schedule = ? WHERE empno = ? AND emp_sched = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sss", $schedule, $empno, $date);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update schedule.']);
        }
    } else {
        // Insert a new schedule
        $insert_sql = "INSERT INTO scheduling (empno, schedule, emp_sched) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("sss", $empno, $schedule, $date);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Schedule saved successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save schedule.']);
        }
    }
    $stmt->close();
    $conn->close();
}
?>        