<!-- Generate the base URL for the website -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . 
               $_SERVER['HTTP_HOST'] . 
               str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<!-- Start session and assign user details -->
<?php 
    session_start();
    $role = $_SESSION['sess_userrole'];
    $fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];
?>

<!-- Internal Styles -->
<?php ob_start(); ?>
    <!-- DATA TABLES CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.3.0/css/responsive.bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<?php $styles = ob_get_clean(); ?>

<!-- Page Content -->
<?php ob_start(); ?>
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="d-md-flex d-block align-items-center justify-content-between mb-4 mt-2 page-header-breadcrumb">
            <h1 class="page-title fw-semibold fs-18 mb-0">Logs Per Date</h1>
        </div>

        <!-- Search Form -->
        <form class="form-inline" method="post" action="generate_logs_per_date.php">
            <div class="row">
                <div class="col-xl-12">
                    <div class="card custom-card mb-3">
                        <div class="card-body">
                            <div class="row gy-4 mb-3 align-bottom">
                                <div class="col-xl-2 col-lg-6 col-md-6 col-sm-12">
                                    <p for="startDate" class="text-dark fw-semibold">Start Date</p>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required 
                                           value="<?php echo isset($_POST['startDate']) ? $_POST['startDate'] : ''; ?>">
                                    <div class="invalid-feedback">Please provide a date.</div>
                                </div>

                                <div class="col-xl-2 col-lg-6 col-md-6 col-sm-12">
                                    <p for="endDate" class="text-dark fw-semibold">End Date</p>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required 
                                           value="<?php echo isset($_POST['endDate']) ? $_POST['endDate'] : ''; ?>">
                                    <div class="invalid-feedback">Please provide a date.</div>
                                </div>

                                <div class="col-xl-1 col-lg-6 col-md-6 col-sm-12 d-flex flex-column">
                                    <button class="btn btn-primary mt-auto" type="submit" name="Submit">Search</button>
                                </div>
                            </div>
                        </div>    
                    </div>
                </div>
            </div>
        </form>






<?php $content = ob_get_clean(); ?>

<!-- Internal Scripts -->
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

<!-- Include base layout and logic -->
<?php 
    include 'layouts/base.php'; 
    include 'process_payroll_settings.php'; 
?>
