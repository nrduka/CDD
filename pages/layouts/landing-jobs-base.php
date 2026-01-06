<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="horizontal" data-nav-style="menu-click" data-menu-position="fixed" data-theme-mode="light">

    <head>

        <!-- Meta Data -->
		<meta charset="UTF-8">
        <meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=no'>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="Description" content="PHP Bootstrap Responsive Admin Web Dashboard Template">
        <meta name="Author" content="Spruko Technologies Private Limited">
        <meta name="keywords" content="php dashboard, php template, admin dashboard bootstrap, bootstrap admin theme, admin, php admin panel, bootstrap admin template, admin dashboard template, admin template bootstrap, php admin dashboard, dashboard template, dashboard template bootstrap, bootstrap admin, admin panel template, dashboard">

        <!-- TITLE -->
		<title> YNEX - PHP Bootstrap 5 Premium Admin & Dashboard Template </title>

        <!-- FAVICON -->
        <link rel="icon" href="<?php echo $baseUrl; ?>/assets/images/brand-logos/favicon.ico" type="image/x-icon">

        <!-- BOOTSTRAP CSS -->
        <link  id="style" href="<?php echo $baseUrl; ?>/assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

        <!-- ICONS CSS -->
        <link href="<?php echo $baseUrl; ?>/assets/css/icons.css" rel="stylesheet">

        <!-- STYLES CSS -->
        <link href="<?php echo $baseUrl; ?>/assets/css/styles.css" rel="stylesheet">

        <?php include 'layouts/components/landing/styles.php'; ?>

        <?php echo $styles; ?>

	</head>

    <body class="landing-body jobs-landing">

        <!-- SWITCHER -->
        <?php include 'layouts/components/landing/switcher.php'; ?>

        <!-- END SWITCHER -->

        <!-- PAGE -->
		<div class="landing-page-wrapper">

            <!-- HEADER -->
            <?php include 'layouts/components/landing/jobs-header.php'; ?>

            <!-- END HEADER -->

            <!-- SIDEBAR -->
            <?php include 'layouts/components/landing/jobs-sidebar.php'; ?>

            <!-- END SIDEBAR -->

            <!-- MAIN-CONTENT -->

            <div class="main-content landing-main">

                <?php echo $content; ?>

                    <!-- FOOTER -->
                    <?php include 'layouts/components/landing/footer.php'; ?>
                    
                    <!-- FOOTER -->

            </div> 
            <!-- END MAIN-CONTENT -->

		</div>
        <!-- END PAGE-->

        <!-- SCRIPTS -->

        <?php include 'layouts/components/landing/scripts.php'; ?>

        <?php echo $scripts; ?>
        
        <!-- STICKY JS -->
		<script src="<?php echo $baseUrl; ?>/assets/js/sticky.js"></script>

        <!-- END SCRIPTS -->

	</body>
</html>
