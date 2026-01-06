<!-- This code generates the base URL for the website by combining the protocol, domain name, and directory path -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>


<?php 
session_start();
$role = $_SESSION['sess_userrole'];
$fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];
?>




<!-- This code is useful for internal styles  -->
<?php ob_start(); ?>

<link href="<?php echo $baseUrl; ?>/assets/css/pos_terminal.css" rel="stylesheet">

<?php $styles = ob_get_clean(); ?>
<!-- This code is useful for internal styles  -->

<!-- This code is useful for content -->
<?php ob_start(); ?>

                <div class="container-fluid">

                    <!-- Start::page-header -->
                    <div class="d-md-flex d-block align-items-center justify-content-between my-4 page-header-breadcrumb">
                        <div>
                            <p class="fw-semibold fs-18 mb-0">Ice Plant POS</p>
                            <span class="fs-semibold text-muted">Create and submit customer orders from one screen.</span>
                        </div>
                    </div>
                    <!-- End::page-header -->

                    <!-- Start::row -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card pos-terminal-card">
                                <div class="card-header pos-terminal-header">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bx bx-store-alt fs-20"></i>
                                            <span class="fw-semibold">Ice Plant POS</span>
                                        </div>
                                        <button type="button" class="btn btn-link pos-terminal-close" aria-label="Close">
                                            <i class="ri-close-line"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body pos-terminal-body">
                                    <div class="row align-items-center g-3">
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <label class="form-label fw-semibold mb-0">Customer:</label>
                                        </div>
                                        <div class="col-12 col-md-7 col-lg-5">
                                            <select class="form-select">
                                                <option selected disabled>Select Customer</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="pos-terminal-divider"></div>

                                    <div class="row align-items-center g-3">
                                        <div class="col-12 col-md-5 col-lg-4">
                                            <label class="form-label fw-semibold">Product</label>
                                            <select class="form-select">
                                                <option selected disabled>Select Product</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3 col-lg-2">
                                            <label class="form-label fw-semibold">Quantity</label>
                                            <input type="number" class="form-control" value="1" min="1">
                                        </div>
                                        <div class="col-12 col-md-2 col-lg-2">
                                            <label class="form-label fw-semibold">Price</label>
                                            <div class="pos-terminal-price">PHP 0.00</div>
                                        </div>
                                        <div class="col-12 col-md-2 col-lg-2 d-flex align-items-end">
                                            <button type="button" class="btn btn-primary w-100">Add Item</button>
                                        </div>
                                    </div>

                                    <div class="pos-terminal-divider"></div>

                                    <div class="pos-terminal-items text-muted">
                                        Items will appear here once added.
                                    </div>

                                    <div class="pos-terminal-divider"></div>

                                    <div class="pos-terminal-summary">
                                        <div class="pos-terminal-summary-row">
                                            <span class="fw-semibold">Subtotal:</span>
                                            <span class="fw-semibold">PHP 0.00</span>
                                        </div>
                                        <div class="pos-terminal-summary-row">
                                            <span class="fw-semibold">Discount:</span>
                                            <div class="input-group pos-terminal-discount">
                                                <input type="number" class="form-control" value="0" min="0" max="100">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                        <div class="pos-terminal-summary-row">
                                            <span class="fw-semibold">Additional Charges:</span>
                                            <span class="fw-semibold">PHP 0.00</span>
                                        </div>
                                        <div class="pos-terminal-summary-row pos-terminal-grand">
                                            <span>Grand Total:</span>
                                            <span>PHP 0.00</span>
                                        </div>
                                    </div>

                                    <div class="text-center mt-4">
                                        <button type="button" class="btn btn-primary btn-lg px-5">Submit Order</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- End::row -->

                </div>

<?php $content = ob_get_clean(); ?>
<!-- This code is useful for content -->

<!-- This code is useful for internal scripts  -->
<?php ob_start(); ?>


<?php $scripts = ob_get_clean(); ?>
<!-- This code is useful for internal scripts  -->

<!-- This code use for render base file -->
<?php include 'layouts/base.php'; ?>
<!-- This code use for render base file -->
