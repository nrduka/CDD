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

    $sql = "SELECT * FROM department ORDER BY department_code ASC";

    $result = $conn->query($sql);
    ?>


    <!-- Start::row-1 -->
    <div class="row">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">
                        Manage Department
                    </div>
                    <div class="prism-toggle">
                        <button type="button" data-bs-toggle="modal" data-bs-effect="effect-scale" data-bs-target="#add-department-modal" class="btn btn-success btn-wave">Add Department</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-striped">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Department Code</th>
                                    <th scope="col">Department Name</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                <?php $count = 1; ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td scope="row"><?php echo $count++; ?></td>
                                        <td><?php echo htmlspecialchars($row['department_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td><?php echo htmlspecialchars($row['dept_description']); ?></td>
                                        <td>
                                            <div class="hstack gap-2 fs-15">
                                                <a href="update_device.php?id=<?php echo $row['id']; ?>" class="btn btn-icon btn-md btn-info"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Edit Details"
                                                        ><i class="ri-edit-line"></i></a>

                                                <a href="delete_device.php?id=<?php echo $row['id']; ?>&case_no=<?php echo $row['case_no']; ?>&first_name=<?php echo $row['first_name']; ?>&middle_name=<?php echo $row['middle_name']; ?>&last_name=<?php echo $row['last_name']; ?>" class="btn btn-icon btn-md btn-danger"
                                                        data-bs-toggle="tooltip" data-bs-placement="top" title="Delete Device"
                                                        ><i class="ri-delete-bin-line"></i></a>
                                            </div> 
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">No departments found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--End::row-1 -->



























</div>








<div class="modal fade"  id="add-department-modal">
    <div class="modal-dialog modal-dialog-centered text-center" role="document">
        <div class="modal-content modal-content-demo">
            <div class="modal-header">
                <h6 class="modal-title">Add New Department</h6><button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-start">

                <div class="col-xl-12 mb-4">
                    <label for="first_name" class="form-label">Department Code</label>
                    <input type="text" class="form-control" id="department_code" name="department_code" placeholder="Department Code" required>
                    <div class="invalid-feedback">
                        Please provide Department Code.
                    </div>
                </div>

                <div class="col-xl-12 mb-4">
                    <label for="first_name" class="form-label">Department Name</label>
                    <input type="text" class="form-control" id="department_name" name="department_name" placeholder="Department Name" required>
                    <div class="invalid-feedback">
                        Please provide Department Name.
                    </div>
                </div>

                <div class="col-xl-12 mb-4">
                    <label for="first_name" class="form-label">Description</label>
                    <input type="text" class="form-control" id="description" name="description" placeholder="Description" required>
                    <div class="invalid-feedback">
                        Please provide Description.
                    </div>
                </div>

            </div>
                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal" >Close</button><button class="btn btn-primary" >Save Department</button>
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