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
?>

tt
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
            include 'database-config.php';

            $sql = "SELECT (@row_number:=@row_number + 1) AS No,manpoweremployee.* FROM  `manpoweremployee` JOIN (SELECT @row_number:=0 ) AS t WHERE empstatus='active'
                    ORDER BY `manpoweremployee`.`lastName`  ASC";

            $result = $conn->query($sql);
            ?>



                <div class="container-fluid">

                    <!-- Page Header -->
                   <!--  <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
                        <h1 class="page-title fw-semibold fs-18 mb-0">Employee Masterlist</h1>
                    </div> -->
                    <!-- Page Header Close -->

                    <!-- Start::row-1 -->
                    <div class="row">
                        <div class="col-xl-12">
                            <div class="card custom-card">
                                <div class="card-header">
                                    <div class="card-title">
                                        Employee Masterlist
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table id="datatable-basic" class="table table-bordered text-nowrap w-100">
                                            <thead>
                                                <tr>
                                                    <th>Manage</th>
                                                    <th>Employee ID</th>
                                                    <th>First Name</th>
                                                    <th>Middle Name</th>
                                                    <th>Last Name</th>
                                                    <th>Address</th>
                                                    <th>Date Of Birth</th>                                    
                                                    <th>Age</th>
                                                    <th>Education</th>                                    
                                                    <th>Contact No</th>
                                                    <th>Civil Status</th>
                                                    <th>Gender</th>                                    
                                                    <th>Hired Date</th>
                                                    <th>Company</th>                                    
                                                    <th>Client</th>
                                                    <th>Employment Status</th>                                    
                                                    <th>Department</th>
                                                    <th>Job Title</th>
                                                    <th>Account No</th>
                                                    <th>Daily Rate</th>
                                                    <th>Secondary/Provincial Rate</th>                                    
                                                    <th>Monthly Rate</th>
                                                    <th>Monthly Allowance</th>                                    
                                                    <th>Tin ID</th>
                                                    <th>Philhealth ID</th>
                                                    <th>PAGIBIG ID</th>                                    
                                                    <th>SSS ID</th>
                                                    <th>Tax Status</th>                                    
                                                    <th>Philhealth Contribution</th>
                                                    <th>SSS Contribution</th>                                    
                                                    <th>PAGIBIG Contribution</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                        while ($row = $result->fetch_assoc()) : ?>
                                                <tr>
                                                   <td>
                                                        <!-- Edit Button -->
                                                        <a href="time_sheet_manage.php?empno=<?php echo urlencode($row['empno']); ?>" class="btn btn-warning btn">Manage</a>                                    
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['empno']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['firstName']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['middleName']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['lastName']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['address']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['dob']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['age']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['education']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['contact']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['civil']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['hired']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['company']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['client']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['empstatus']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['position']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['atm']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['daily_salary']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['secondaryrate']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['monthly_salary']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['allowance']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['tinID']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['philhealthID']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['pagibigID']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['sssID']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['tax_exemption']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['philhealthbracket']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['sssbracket']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['hdmfbracket']); ?></td>    
                                                </tr>
                                    <?php endwhile; ?>
                                               
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--End::row-1 -->        
                </div>






<script>
    $(document).ready(function() {
        $('#datatable-basic').DataTable({
            scrollX: true, // Enables horizontal scrolling
            responsive: true,
            paging: true,
            searching: true,
            info: true,
            autoWidth: false
        });
    });
</script>




<style>
/* Custom Scrollbar Styling */
::-webkit-scrollbar {
    height: 10px; /* Make scrollbar bigger */
    background-color: #f0f0f0; /* Background of the scrollbar */
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background-color: #888; /* Default thumb color */
    border-radius: 10px;
    border: 1px solid #f0f0f0; /* Border around the thumb */
}

/* Change thumb color on hover */
::-webkit-scrollbar-thumb:hover {
    background-color: #555; /* Change color only on thumb hover */
}

/* Change thumb color when hovering over the table */
.dataTables_wrapper:hover ::-webkit-scrollbar-thumb {
    background-color: #888; /* Change thumb color to red when hovering over the table */
}

/* Ensure hover effect applies to the thumb when hovering over it */
.dataTables_wrapper .dataTables_scrollBody:hover::-webkit-scrollbar-thumb:hover {
    background-color: #555; /* Change thumb color to black when hovering over the thumb itself */
}

/* Add padding to make scroll more obvious */
.dataTables_wrapper .dataTables_scrollBody {
    padding-bottom: 1vw; /* Increases space, making scroll more noticeable */
    overflow-x: scroll; /* Ensures horizontal scrolling */
    margin-bottom: 1vw;
}
</style>





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

<?php $scripts = ob_get_clean(); ?>
<!-- This code is useful for internal scripts  -->

<!-- This code use for render base file -->
<?php include 'layouts/base.php'; ?>
<!-- This code use for render base file -->



