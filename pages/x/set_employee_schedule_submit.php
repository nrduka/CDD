<?php
// Connect to the database
$conn = new mysqli('localhost', 'root', '', 'mpc_ems');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if required POST data exists
if (isset($_POST['empno'], $_POST['date'], $_POST['schedule'])) {
    $selectedEmpnos = $_POST['empno'];     // array of employee numbers
    $dates = $_POST['date'];               // array of dates
    $schedules = $_POST['schedule'];       // array of schedules (one per date)

    $messages = [];

    // Combine employee-date-schedule into a queue
    $combinedData = [];
    foreach ($selectedEmpnos as $empIndex => $empno) {
        foreach ($dates as $dateIndex => $date) {
            $combinedData[] = [
                'empno' => $conn->real_escape_string($empno),
                'date' => $conn->real_escape_string($date),
                'schedule' => $conn->real_escape_string($schedules[$dateIndex])
            ];
        }
    }

    foreach ($combinedData as $data) {
        $empno = $data['empno'];
        $date = $data['date'];
        $schedule = $data['schedule'];

        // Get employee name
        $emp_query = "SELECT CONCAT(firstName, ' ', lastName) AS name FROM manpoweremployee WHERE empno = '$empno'";
        $emp_result = $conn->query($emp_query);
        $emp_name = ($emp_result && $emp_result->num_rows > 0) ? $emp_result->fetch_assoc()['name'] : $empno;

        // Check for duplicate schedule
        $sql_check = "SELECT COUNT(*) as count FROM scheduling WHERE empno = '$empno' AND date_duty = '$date'";
        $result = $conn->query($sql_check);

        if (!$result) {
            $messages[] = "Error checking schedule for $emp_name: " . $conn->error;
            continue;
        }

        $row = $result->fetch_assoc();
        if ($row['count'] > 0) {
            $messages[] = "$emp_name already has a schedule on $date.";
        } else {
            // Insert schedule
            $schedid = $empno . '-' . $date;
            $sql_insert = "INSERT INTO scheduling (empno, schedid, date_duty, shiftid)
               VALUES ('$empno', '$schedid', '$date', '$schedule')";
            if ($conn->query($sql_insert) === TRUE) {
                $messages[] = "$emp_name's schedule on $date saved.";
            } else {
                $messages[] = "Error saving schedule for $emp_name on $date: " . $conn->error;
            }
        }
    }

    // Return alert with all results
    $alertMessage = json_encode(implode("\n", $messages));
    echo "<script>alert($alertMessage); window.location.href='employee_schedule.php';</script>";

} else {
    // Missing data
    echo "<script>alert('Incomplete data. Please ensure employee numbers, dates, and schedules are selected.'); window.location.href='employee_schedule.php';</script>";
}

$conn->close();
?>
