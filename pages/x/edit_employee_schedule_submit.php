<?php
include 'database-config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $empnos = $_POST['empno'];
    $date_dutys = $_POST['date_duty'];
    $shiftnames = $_POST['shiftid']; // This is actually shift *name*

    $success = true;
    $message = '';

    for ($i = 0; $i < count($empnos); $i++) {
        $empno = $conn->real_escape_string($empnos[$i]);
        $date_duty = $conn->real_escape_string($date_dutys[$i]);
        $new_shiftname = $conn->real_escape_string($shiftnames[$i]);

        // Get original active schedule
        $sql_check = "SELECT s.schedid, s.shiftid, t.shift_name 
                      FROM scheduling s 
                      LEFT JOIN shift_templates t ON s.shiftid = t.id
                      WHERE s.empno = '$empno' AND s.date_duty = '$date_duty' AND s.status = 0 
                      LIMIT 1";

        $result = $conn->query($sql_check);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $original_schedid = $row['schedid'];
            $original_shiftname = $row['shift_name'];

            if ($original_shiftname != $new_shiftname) {
                // Mark original as status=1
                $sql_update = "UPDATE scheduling SET status = 1 WHERE schedid = '$original_schedid'";
                if (!$conn->query($sql_update)) {
                    $success = false;
                    $message .= "Error updating old schedule: " . $conn->error . "<br>";
                    continue;
                }

                // Get the shiftid of the new shift name
                $sql_get_id = "SELECT id FROM shift_templates WHERE shift_name = '$new_shiftname' LIMIT 1";
                $shift_result = $conn->query($sql_get_id);
                if ($shift_result && $shift_result->num_rows > 0) {
                    $shift_row = $shift_result->fetch_assoc();
                    $new_shiftid = $shift_row['id'];
                    $new_schedid = $empno . $date_duty;

                    // Insert new record
                    $sql_insert = "INSERT INTO scheduling (empno, date_duty, shiftid, status, schedid) 
                                   VALUES ('$empno', '$date_duty', '$new_shiftid', 0, '$new_schedid')";
                    if (!$conn->query($sql_insert)) {
                        $success = false;
                        $message .= "Error inserting new schedule: " . $conn->error . "<br>";
                    }
                } else {
                    $success = false;
                    $message .= "Shift name not found: $new_shiftname<br>";
                }
            }
            // else: shift did not change, do nothing
        } else {
            // No existing schedule, insert new
            $sql_get_id = "SELECT id FROM shift_templates WHERE shift_name = '$new_shiftname' LIMIT 1";
            $shift_result = $conn->query($sql_get_id);
            if ($shift_result && $shift_result->num_rows > 0) {
                $shift_row = $shift_result->fetch_assoc();
                $new_shiftid = $shift_row['id'];
                $new_schedid = $empno . $date_duty;

                $sql_insert = "INSERT INTO scheduling (empno, date_duty, shiftid, status, schedid) 
                               VALUES ('$empno', '$date_duty', '$new_shiftid', 0, '$new_schedid')";
                if (!$conn->query($sql_insert)) {
                    $success = false;
                    $message .= "Error inserting new schedule (no previous): " . $conn->error . "<br>";
                }
            } else {
                $success = false;
                $message .= "Shift name not found: $new_shiftname<br>";
            }
        }
    }

    if ($success) {
        echo "<script>alert('Schedule updated successfully.'); window.location.href='view_schedule.php';</script>";
    } else {
        echo "<div style='color:red;font-weight:bold;'>Update failed:</div>";
        echo "<div>$message</div>";
    }
}
?>
