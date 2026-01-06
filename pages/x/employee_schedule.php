<!-- This code generates the base URL for the website by combining the protocol, domain name, and directory path -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>
<!-- This code generates the base URL for the website by combining the protocol, domain name, and directory path -->

<?php 
session_start();
$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];

$rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') .
           $_SERVER['HTTP_HOST'] .
           str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<!-- This code is useful for internal styles  -->
<?php ob_start(); ?>

        <!-- DATA TABLES CSS -->
        <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">



<?php $styles = ob_get_clean(); ?>
<!-- This code is useful for internal styles  -->

<!-- This code is useful for content -->
<?php ob_start(); ?>


<?php
    // Database connection
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=mpc_ems', 'root', '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }

    $dt = new DateTime;

    if (isset($_GET['year']) && isset($_GET['week'])) {
        $year = (int)$_GET['year'];
        $week = (int)$_GET['week'];
    } else {
        $year = (int)$dt->format('o');
        $week = (int)$dt->format('W');
    }

    // Ensure valid ISO date
    try {
        $dt->setISODate($year, $week);
    } catch (Exception $e) {
        $dt = new DateTime();
        $dt->setISODate((int)$dt->format('o'), (int)$dt->format('W'));
    }

    $month = $dt->format('F');

    $prevDt = clone $dt;
    $prevDt->modify('-7 days');
    $prevWeek = $prevDt->format('W');
    $prevYear = $prevDt->format('o');

    $nextDt = clone $dt;
    $nextDt->modify('+7 days');
    $nextWeek = $nextDt->format('W');
    $nextYear = $nextDt->format('o');

    // Get actual current week and year
    $currentDt = new DateTime;
    $currentWeek = $currentDt->format('W');
    $currentYear = $currentDt->format('o');

    // Fetch employee schedules using LEFT JOIN to include employees with no schedule
    $startOfWeek = $dt->format('Y-m-d');
    $endOfWeek = clone $dt;
    $endOfWeek->modify('+6 days');
    $endOfWeek = $endOfWeek->format('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT CONCAT(e.firstName,' ',e.lastName) as full_name, s.date_duty as emp_sched, s.shift_name as schedule, s.empno
    FROM manpoweremployee e
    LEFT JOIN v_scheduling s ON e.empno = s.empno AND s.date_duty BETWEEN ? AND ?
    ");
    $stmt->execute([$startOfWeek, $endOfWeek]);
    $schedules = $stmt->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_ASSOC);
?>



<div class="container">

    <button type="button" class="btn btn-success btn-wave" onclick="window.location.href='create_employee_schedule.php'">Create Employee Schedule</button>


    <br></br>

    <!-- Start::row-1 -->
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">
                        <?php echo "$month $year"; ?>
                    </div>
                    <div class="prism-toggle">
                        <!-- <button type="button" data-bs-toggle="modal" data-bs-effect="effect-scale" data-bs-target="#add-device-modal" class="btn btn-success btn-wave">Add New Device</button> -->
                        <a class="btn btn-primary btn-xs week-btn bi bi-caret-left-fill" data-week="<?php echo $prevWeek; ?>" data-year="<?php echo $prevYear; ?>"></a>
                        <a class="btn btn-primary btn-xs current-week-btn">Current Week</a>
                        <a class="btn btn-primary btn-xs week-btn bi bi-caret-right-fill" data-week="<?php echo $nextWeek; ?>" data-year="<?php echo $nextYear; ?>"></a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="scheduleTable" class="table table-bordered text-nowrap w-100">
                            <thead>
                                <tr class="success">
                                  <td>Employee</td>
                                  <?php
                                  $tempDate = clone $dt;
                                  $days = [];
                                  do {
                                    $formattedDate = $tempDate->format('Y-m-d');
                                    $days[] = $formattedDate;
                                    echo $tempDate->format('d M Y') == date('d M Y') ?
                                      "<td style='background:yellow'>{$tempDate->format('l')}<br>{$tempDate->format('d M Y')}</td>" :
                                      "<td>{$tempDate->format('l')}<br>{$tempDate->format('d M Y')}</td>";
                                    $tempDate->modify('+1 day');
                                  } while ($week == $tempDate->format('W'));
                                  ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schedules as $employee => $scheduleData) { ?>
          <tr style="height: 50px;">
            <td><?php echo htmlspecialchars($employee); ?></td>
            <?php foreach ($days as $day) { 

            $empno = isset($scheduleData[0]['empno']) ? $scheduleData[0]['empno'] : '';
            $full_name = isset($scheduleData[0]['full_name']) ? $scheduleData[0]['full_name'] : $employee;

              $shift = "-";
              foreach ($scheduleData as $schedule) {
                if ($schedule['emp_sched'] === $day) {
                  $shift = htmlspecialchars($schedule['schedule']);
                  break;
                }
              }
              if ($shift === "-") {
  echo "<td><span style='color: gray;'>{$shift}</span></td>";
} else {
  echo "<td>
    <button class='btn btn-info btn-sm' 
      data-bs-toggle='modal' 
      data-bs-effect='effect-scale' 
      data-bs-target='#scheduleModal'
      data-empno='{$empno}'
      data-name='{$full_name}'
      data-date='{$day}'
      data-schedule='{$shift}'
    >
      {$shift}
    </button>
  </td>";
}
            } ?>
          </tr>
        <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--End::row-1 -->
</div>


</div>


<?php

include 'database-config.php';

// Fetch shift options from the database
    $shiftOptions = '';
    $shift_sql = "SELECT shift_name, start_time, end_time FROM shift_templates ORDER BY id ASC";
    $shift_result = $conn->query($shift_sql);
    while ($shift_row = $shift_result->fetch_assoc()) {
        $shiftOptions .= '<option value="' . htmlspecialchars($shift_row['shift_name']) . '">' . htmlspecialchars($shift_row['shift_name']). '</option>';
    }

?>


<div class="modal fade" id="scheduleModal" tabindex="-1" aria-labelledby="scheduleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="scheduleModalLabel">Edit Schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="scheduleForm">
          <div class="mb-3">
            <label for="modalEmpno">Employee Number:</label>
            <input type="text" class="form-control" id="modalEmpno" name="empno" readonly>
          </div>
          <div class="mb-3">
            <label for="modalName">Employee Name:</label>
            <input type="text" class="form-control" id="modalName" name="name" readonly>
          </div>
          <div class="mb-3">
            <label for="modalDate">Date:</label>
            <input type="text" class="form-control" id="modalDate" name="date" readonly>
          </div>
          <div class="mb-3">
              <label for="schedule">Schedule:</label>
              <select class="form-control custom-select-arrow" id="schedule" name="schedule" required>
                <option value="">Select Shift</option>
                <?= $shiftOptions ?>
              </select>
            </div>
        </form>
      </div>
      <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      <!-- Example of dynamic HTML generation for each schedule -->
          <button type="button" class="btn btn-danger" onclick="deleteSchedule()">Delete</button>
          <button type="button" class="btn btn-primary" onclick="saveSchedule()">Save changes</button>
    </div>
    </div>
  </div>
</div>


<?php $content = ob_get_clean(); ?>
<!-- This code is useful for content -->
<!-- This code is useful for internal scripts  -->
<?php ob_start(); ?>

        <!-- JQUERY JS -->
        <script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>

        <!-- DATATABLES CDN JS -->
        <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
        <script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

        <!-- INTERNAL DATATABLES JS -->
        <script src="<?php echo $baseUrl; ?>/assets/js/datatables.js"></script>

        <!-- PRISM JS -->
        <script src="<?php echo $baseUrl; ?>/assets/libs/prismjs/prism.js"></script>
        <script src="<?php echo $baseUrl; ?>/assets/js/prism-custom.js"></script>

<script>
function attachModalListeners() {
  document.querySelectorAll('[data-bs-target="#scheduleModal"]').forEach(button => {
    button.addEventListener('click', function() {
      const empno = this.getAttribute('data-empno');
      const name = this.getAttribute('data-name');
      const date = this.getAttribute('data-date');
      const schedule = this.getAttribute('data-schedule');

      document.getElementById('modalEmpno').value = empno;
      document.getElementById('modalName').value = name;
      document.getElementById('modalDate').value = date;
      document.getElementById('schedule').value = schedule;
    });
  });
}

// Function to delete schedule
function deleteSchedule() {
    let empno = document.getElementById('modalEmpno').value;
    let date = document.getElementById('modalDate').value;
    
    if (confirm("Are you sure you want to delete this schedule? This action cannot be undone.")) {
        fetch('delete_schedule.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ empno: empno, date: date })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Schedule has been deleted!");
                
                let tableCells = document.querySelectorAll("td button[data-empno='" + empno + "'][data-date='" + date + "']");
                tableCells.forEach(button => {
                    let cell = button.parentElement;
                    cell.innerHTML = "<span style='color: gray;'>-</span>";
                });

                // Close modal
                let modal = bootstrap.Modal.getInstance(document.getElementById('scheduleModal'));
                modal.hide();
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(error => {
            console.error("Error:", error);
            alert("There was an error processing your request.");
        });
    }
}


// Function to save schedule changes
function saveSchedule() {
    let empno = document.getElementById('modalEmpno').value;
    let date = document.getElementById('modalDate').value;
    let schedule = document.getElementById('schedule').value;

    if (!schedule) {
        alert("Please select a schedule.");
        return;
    }

    fetch('save_schedule.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ empno: empno, date: date, schedule: schedule })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert("Schedule has been updated!");

            // Find the table cell that matches the employee number & date
            let tableCells = document.querySelectorAll("td button[data-empno='" + empno + "'][data-date='" + date + "']");
            tableCells.forEach(button => {
                button.textContent = schedule; // Update the button text with the new schedule
                button.setAttribute("data-schedule", schedule); // Update the schedule attribute
            });

            // Close the modal properly
            let modal = document.getElementById('scheduleModal');
            let modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        } else {
            alert("Error: " + data.message);
        }
    })
    .catch(error => {
        console.error("Error:", error);
        alert("There was an error processing your request.");
    });
}
</script>

<script>
$(document).ready(function () {
  // Initialize DataTables
  let table = $('#scheduleTable').DataTable({
    responsive: true,
    paging: true,
    searching: true,
    lengthChange: true,
    stateSave: true
  });

  // Function to refresh DataTables without losing pagination state
  function refreshTable() {
    table.destroy();
    table = $('#scheduleTable').DataTable({
      responsive: true,
      paging: true,
      searching: true,
      lengthChange: true,
      stateSave: true
    });
  }

  // Handle week button click with AJAX
  $(document).on('click', '.week-btn', function(e) {
    e.preventDefault();
    const week = $(this).data('week');
    const year = $(this).data('year');

    $.ajax({
      url: 'employee_schedule.php',
      type: 'GET',
      data: { week: week, year: year },
      success: function(response) {
        $('.container').html($(response).find('.container').html());
        refreshTable();
        attachModalListeners(); // Reattach modal listeners after AJAX update
      },
      error: function() {
        alert('Failed to fetch data. Please try again.');
      }
    });
  });

  // Handle current week button click with AJAX
  $(document).on('click', '.current-week-btn', function(e) {
    e.preventDefault();

    $.ajax({
      url: 'employee_schedule.php',
      type: 'GET',
      data: { week: '<?php echo $currentWeek; ?>', year: '<?php echo $currentYear; ?>' },
      success: function(response) {
        $('.container').html($(response).find('.container').html());
        refreshTable();
        attachModalListeners(); // Reattach modal listeners after AJAX update
      },
      error: function() {
        alert('Failed to fetch data. Please try again.');
      }
    });
  });

  // Initial modal event listener attachment
  attachModalListeners();
});
</script>


<?php $scripts = ob_get_clean(); ?>
<!-- This code is useful for internal scripts  -->

<!-- This code use for render base file -->
<?php include 'layouts/base.php'; ?>
<!-- This code use for render base file -->