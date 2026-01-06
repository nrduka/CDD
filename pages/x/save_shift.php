<?php
include 'database-config.php';

// Check if the form data is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $shift_name = trim($_POST['shift_name']);
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // Check for duplicate shift name
    $checkSql = "SELECT COUNT(*) FROM shift_templates WHERE shift_name = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $shift_name);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($count > 0) {
        echo "<script>alert('Duplicate shift name detected. Please use a different name.'); window.location.href='create_shift.php';</script>";
    } else {
        // Insert data into the shift_templates table
        $sql = "INSERT INTO shift_templates (shift_name, start_time, end_time) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $shift_name, $start_time, $end_time);

        if ($stmt->execute()) {
            echo "<script>alert('Shift added successfully!'); window.location.href='create_shift.php';</script>";
        } else {
            echo "<script>alert('Error adding shift: " . $stmt->error . "'); window.location.href='create_shift.php';</script>";
        }

        $stmt->close();
    }
    $conn->close();
}
?>
