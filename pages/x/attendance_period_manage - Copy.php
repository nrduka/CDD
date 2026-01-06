<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') .
               $_SERVER['HTTP_HOST'] .
               str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php
    session_start();
    // Include the database connection
    include 'database-config.php';

    $role = $_SESSION['sess_userrole'];
    $user_fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];

    // Get employee number from URL and fetch employee details
    $empno = '';
    $employee_fullname = 'Employee Not Found';
    if (isset($_GET['empno'])) {
        $empno = $_GET['empno'];
        $stmt = $conn->prepare("SELECT firstName, lastName FROM manpoweremployee WHERE empno = ?");
        $stmt->bind_param("s", $empno);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $employee_fullname = $row['firstName'] . ' ' . $row['lastName'];
        }
        $stmt->close();
    }
?>

<?php ob_start(); ?>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<?php $styles = ob_get_clean(); ?>

<?php ob_start(); ?>
    <div class="container-fluid">

        <div class="d-md-flex d-block align-items-center justify-content-between mb-4 mt-2 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Manage Attendance</h1>
            <div class="ms-md-1 ms-0">
                <nav>
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="<?php echo $baseUrl; ?>/index">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo $baseUrl; ?>/pages/attendance_period">Employee List</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Manage Attendance</li>
                    </ol>
                </nav>
            </div>
        </div>

        <form class="form-inline" method="post" action="attendance_period_manage.php?empno=<?php echo urlencode($empno); ?>" id="attendance-form">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card custom-card mb-3">
                        <div class="card-header">
                            <div class="card-title">
                                Viewing Attendance for: <span class="fw-bold text-primary"><?php echo htmlspecialchars($employee_fullname); ?> (<?php echo htmlspecialchars($empno); ?>)</span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row gy-4 mb-3 align-items-bottom">
                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <label for="startDate" class="text-dark fw-semibold">Start Date</label>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required
                                           value="<?php echo isset($_POST['startDate']) ? $_POST['startDate'] : ''; ?>">
                                    <div class="invalid-feedback">Please provide a start date.</div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <label for="endDate" class="text-dark fw-semibold">End Date</label>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required
                                           value="<?php echo isset($_POST['endDate']) ? $_POST['endDate'] : ''; ?>">
                                    <div class="invalid-feedback">Please provide an end date.</div>
                                </div>

                                <div class="col-xl-2 col-lg-6 col-md-6 col-sm-12 d-flex flex-column">
                                    <button class="btn btn-primary mt-auto" type="submit" name="Submit">Search</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">Attendance Log</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="datatable-basic" class="table table-bordered text-nowrap w-100 table-striped">
                                <thead>
                                    <tr>
                                        <th>Employee No</th>
                                        <th>Full Name</th>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Lunch Start</th>
                                        <th>Lunch End</th>
                                        <th>Break Start</th>
                                        <th>Break End</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if (isset($_POST["endDate"]) && isset($_POST["startDate"])) {
                                        $start_date = $_POST["startDate"];
                                        $end_date = $_POST["endDate"];

                                        // We already have $empno from the GET parameter
                                        // The query uses prepared statements to prevent SQL injection
                                        $query = "SELECT
                                                    a.empno,
                                                    e.firstName,
                                                    e.lastName,
                                                    a.logdate,
                                                    a.timein,
                                                    a.timeout,
                                                    a.lunchstart,
                                                    a.lunchend,
                                                    a.breakstart,
                                                    a.breakend
                                                FROM
                                                    tblattendance AS a
                                                JOIN
                                                    manpoweremployee AS e ON a.empno = e.empno
                                                WHERE
                                                    a.empno = ? AND a.logdate BETWEEN ? AND ?
                                                ORDER BY
                                                    a.logdate ASC";

                                        $stmt = $conn->prepare($query);
                                        $stmt->bind_param("sss", $empno, $start_date, $end_date);
                                        $stmt->execute();
                                        $data_result = $stmt->get_result();

                                        while ($row = $data_result->fetch_assoc()) :
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['empno']); ?></td>
                                        <td><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></td>
                                        <td><?php echo htmlspecialchars(date('M d, Y', strtotime($row['logdate']))); ?></td>
                                        <td><?php echo htmlspecialchars($row['timein'] ? date('h:i A', strtotime($row['timein'])) : '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['timeout'] ? date('h:i A', strtotime($row['timeout'])) : '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['lunchstart'] ? date('h:i A', strtotime($row['lunchstart'])) : '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['lunchend'] ? date('h:i A', strtotime($row['lunchend'])) : '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['breakstart'] ? date('h:i A', strtotime($row['breakstart'])) : '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['breakend'] ? date('h:i A', strtotime($row['breakend'])) : '-'); ?></td>
                                    </tr>
                                    <?php
                                        endwhile;
                                        $stmt->close();
                                    }
                                    $conn->close();
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php $content = ob_get_clean(); ?>

<?php ob_start(); ?>
    <script src="https://code.jquery.com/jquery-3.6.1.min.js" integrity="sha256-o88AwQnZB+VDvE9tvIXrMQaPlFFSUTR+nldQm1LuPXQ=" crossorigin="anonymous"></script>

    <script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.3.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

    <script src="<?php echo $baseUrl; ?>/assets/js/datatables.js"></script>

    <script>
    // Example starter JavaScript for disabling form submissions if there are invalid fields
    (function () {
    'use strict'

    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')

    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
            event.preventDefault()
            event.stopPropagation()
            }

            form.classList.add('was-validated')
        }, false)
        })
    })()
    </script>
<?php $scripts = ob_get_clean(); ?>

<?php
    include 'layouts/base.php';
?>