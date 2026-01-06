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
   <!--  <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
        <h1 class="page-title fw-semibold fs-18 mb-0">Device Regsitration List</h1>
     </div> -->


    <?php
    include 'database-config.php';

    $sql = "SELECT * FROM `shift_templates` ORDER BY id ASC ";

    $result = $conn->query($sql);
    ?>




    <!-- Start::row-1 -->
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">
                         Employee Shift List
                    </div>
                    <div class="prism-toggle">
                        <button type="button" data-bs-toggle="modal" data-bs-effect="effect-scale" data-bs-target="#add-device-modal" class="btn btn-success btn-wave">Add New</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="datatable-basic" class="table table-bordered text-nowrap w-100">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Shift Name</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                        while ($row = $result->fetch_assoc()) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row["id"]); ?></td>
                                            <td><?php echo htmlspecialchars($row["shift_name"]); ?></td>
                                            <td><?php echo date("h:i a", strtotime($row["start_time"])); ?></td>
                                            <td><?php echo date("h:i a", strtotime($row["end_time"])); ?></td>
                                            <td>
                                                <div class="hstack gap-2 fs-15">
                                                    <a href="update_shift.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-md btn-info"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Details"
                                                        ><i class="ri-edit-line"></i></a>

                                                    <a href="delete_shift.php?id=<?php echo $row['id']; ?>&case_no=<?php echo $row['case_no']; ?>&first_name=<?php echo $row['first_name']; ?>&middle_name=<?php echo $row['middle_name']; ?>&last_name=<?php echo $row['last_name']; ?>" class="btn btn-icon btn-md btn-danger"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Shift"
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








<div class="modal fade" id="add-device-modal">
    <div class="modal-dialog modal-dialog-centered text-center" role="document">
        <div class="modal-content modal-content-demo">
            <div class="modal-header">
                <h6 class="modal-title">Add Shift</h6>
                <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

                <form action="save_shift.php" method="post">
                    <div class="modal-body text-start">
                        <div class="col-xl-12 mb-4">
                            <label for="shift_name" class="form-label">Shift Name</label>
                            <input type="text" class="form-control" id="shift_name" name="shift_name" placeholder="Shift Name" required>
                        </div>

                        <div class="col-xl-12 mb-4">
                            <label for="start_time" class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>

                        <div class="col-xl-12 mb-4">
                            <label for="end_time" class="form-label">End Time</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>

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