<?php
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light" data-menu-styles="dark" data-toggled="close">

    <head>

        <!-- Meta Data -->
        <meta charset="UTF-8">
        <meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=no'>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="Description" content="PHP Bootstrap Responsive Admin Web Dashboard Template">
        <meta name="Author" content="Spruko Technologies Private Limited">
        <meta name="keywords" content="dashboard, template dashboard, Bootstrap dashboard, admin panel template, sales dashboard, Bootstrap admin panel, stocks dashboard, crm admin dashboard, ecommerce admin panel, admin template, admin panel dashboard, course dashboard, template ecommerce website, dashboard hrm, admin dashboard">

        <!-- TITLE -->
        <title>SMHC</title>

        <!-- FAVICON -->
        <link rel="icon" href="<?php echo $baseUrl; ?>/assets/images/company-logos/header_icon.webp" type="image/x-icon">

        <!-- BOOTSTRAP CSS -->
        <link  id="style" href="<?php echo $baseUrl; ?>/assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

        <!-- ICONS CSS -->
        <link href="<?php echo $baseUrl; ?>/assets/css/icons.css" rel="stylesheet">

        <!-- STYLES CSS -->
        <link href="<?php echo $baseUrl; ?>/assets/css/styles.css" rel="stylesheet">

        <!-- MAIN JS -->
        <script src="<?php echo $baseUrl; ?>/assets/js/main.js"></script>

        <?php include 'layouts/components/styles.php'; ?>

        <?php echo $styles; ?>

    </head>

    <body>

        <!-- SWITCHER -->
        <?php include 'layouts/components/switcher.php'; ?>

        <!-- END SWITCHER -->

        <!-- LOADER -->
        <div id="loader">
            <img src="<?php echo $baseUrl; ?>/assets/images/media/loader.svg" alt="">
        </div>
        <!-- END LOADER -->

        <!-- PAGE -->
        <div class="page">

            <!-- HEADER -->
            <?php include 'layouts/components/header.php'; ?>

            <!-- END HEADER -->

            <!-- SIDEBAR -->
            <?php include 'layouts/components/sidebar.php'; ?>

            <!-- END SIDEBAR -->

            <!-- MAIN-CONTENT -->

            <div class="main-content app-content">

                <?php echo $content; ?>

            </div> 
            <!-- END MAIN-CONTENT -->

            <!-- SEARCH-MODAL -->
            <?php include 'layouts/components/search-modal.php'; ?>

            <!-- END SEARCH-MODAL -->

            <!-- FOOTER -->
            <?php include 'layouts/components/footer.php'; ?>

            <!-- END FOOTER -->

        </div>
        <!-- END PAGE-->

        <!-- SCRIPTS -->

        <?php include 'layouts/components/scripts.php'; ?>

        <?php echo $scripts; ?>

        <!-- STICKY JS -->
        <script src="<?php echo $baseUrl; ?>/assets/js/sticky.js"></script>

        <!-- CUSTOM JS -->
        <script src="<?php echo $baseUrl; ?>/assets/js/custom.js"></script>

        <!-- CUSTOM-SWITCHER JS -->
        <script src="<?php echo $baseUrl; ?>/assets/js/custom-switcher.js"></script>

        <!-- END SCRIPTS -->

    </body>
</html>
