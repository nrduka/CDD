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


<!-- This code is useful for internal styles  -->
<?php ob_start(); ?>


        <!-- DATA TABLES CSS -->
        <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">


        <!-- PRISM CSS -->
        <link rel="stylesheet" href="<?php echo $baseUrl; ?>/assets/libs/prismjs/themes/prism-coy.min.css">

<?php $styles = ob_get_clean(); ?>
<!-- This code is useful for internal styles  -->

<!-- This code is useful for content -->
<?php ob_start(); ?>

<div class="container-fluid">

    <!-- Page Header -->
    <!-- <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Employee Regsitration List</h1>
     </div> -->


    <?php
    include 'database-config.php';

    $sql = "SELECT (@row_number:=@row_number + 1) AS No,manpoweremployee.* FROM  `manpoweremployee` JOIN (SELECT @row_number:=0 ) AS t WHERE empstatus='active'
            ORDER BY `manpoweremployee`.`lastName`  ASC";

    $result = $conn->query($sql);
    ?>




    <!-- Start::row-1 -->
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">
                        Employee Regsitration List
                    </div>
                    <!-- <div class="prism-toggle">
                        <button type="button" data-bs-toggle="modal" data-bs-effect="effect-scale" data-bs-target="#add-device-modal" class="btn btn-success btn-wave">Add New Device</button>
                    </div> -->
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="datatable-basic" class="table table-bordered text-nowrap w-100">
                            <thead>
                                <tr class="w-100">
                                    <th>No</th>
                                    <th>Employee No.</th>
                                    <th>Full Name</th>
                                    <th>Position</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                        while ($row = $result->fetch_assoc()) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row["No"]); ?></td>
                                            <td><?php echo htmlspecialchars($row["empno"]); ?></td>
                                            <td><?php echo htmlspecialchars($row["lastName"].", ".$row["firstName"]." ".$row["middleName"]); ?></td>
                                            <td><?php echo htmlspecialchars($row["position"]); ?></td>

                                            <td>
                                                <div class="hstack gap-2 fs-15">
                                                    <a href="myappmpc://open?fullname=<?php echo urlencode($row["lastName"].", ".$row["firstName"]." ".$row["middleName"]); ?>&empno=<?php echo urlencode($row['empno']); ?>" class="btn btn-icon btn-md btn-info" data-bs-toggle="tooltip" data-bs-placement="top" title="Register Fingerprint"
                                                        ><i class="ri-fingerprint-line"></i></a>

                                                    <a href="delete_device.php?id=<?php echo $row['id']; ?>&case_no=<?php echo $row['case_no']; ?>&first_name=<?php echo $row['first_name']; ?>&middle_name=<?php echo $row['middle_name']; ?>&last_name=<?php echo $row['last_name']; ?>" class="btn btn-icon btn-md btn-danger"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Fingerprint"
                                                        ><i class="ri-delete-bin-line"></i></a>
                                                </div> 
                                            </td>
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








<div class="modal fade"  id="add-device-modal">
    <div class="modal-dialog modal-dialog-centered text-center" role="document">
        <div class="modal-content modal-content-demo">
            <div class="modal-header">
                <h6 class="modal-title">Add Device</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-start">

                <div class="col-xl-12 mb-4">
                    <label for="first_name" class="form-label">Device Name</label>
                    <input type="text" class="form-control" id="device_name" name="device_name" placeholder="Device Name" required>
                    <div class="invalid-feedback">
                        Please provide Device Name.
                    </div>
                </div>

                <div class="col-xl-12 mb-4">
                    <label for="first_name" class="form-label">Serial Number</label>
                    <input type="text" class="form-control" id="serial_no" name="serial_no" placeholder="Serial Number" required>
                    <div class="invalid-feedback">
                        Please provide Serial Number.
                    </div>
                </div>

                <div class="col-xl-12 mb-4">
                    <label for="first_name" class="form-label">Registration Number</label>
                    <input type="text" class="form-control" id="registration_no" name="registration_no" placeholder="Registration Number" required>
                    <div class="invalid-feedback">
                        Please provide Registration Number.
                    </div>
                </div>

                <div class="col-xl-12 mb-4">
                    <label for="first_name" class="form-label">Verification Number</label>
                    <input type="text" class="form-control" id="verification_no" name="verification_no" placeholder="Verification Number" required>
                    <div class="invalid-feedback">
                        Please provide Verification Number.
                    </div>
                </div>

                <div class="col-xl-12 mb-4">
                    <label for="first_name" class="form-label">Activation Number</label>
                    <input type="text" class="form-control" id="activation_no" name="activation_no" placeholder="Activation Number" required>
                    <div class="invalid-feedback">
                        Please provide Activation Number.
                    </div>
                </div>

                <div class="col-xl-12 mb-4">
                    <label for="first_name" class="form-label">Verification Key</label>
                    <input type="text" class="form-control" id="verification_key" name="verification_key" placeholder="Verification Key" required>
                    <div class="invalid-feedback">
                        Please provide Verification Key.
                    </div>
                </div>


            </div>
            <div class="modal-footer">
                 <button class="btn btn-light" data-bs-dismiss="modal" >Close</button><button class="btn btn-primary" >Save Device</button>
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

<?php $scripts = ob_get_clean(); ?>
<!-- This code is useful for internal scripts  -->

<!-- This code use for render base file -->
<?php include 'layouts/base.php'; ?>
<!-- This code use for render base file -->