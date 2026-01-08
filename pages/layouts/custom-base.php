<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-theme-mode="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">

    <head>

        <!-- META DATA -->
        <meta charset="UTF-8">
        <meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=no'>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="Description" content="PHP Bootstrap Responsive Admin Web Dashboard Template">
        <meta name="Author" content="Spruko Technologies Private Limited">
        <meta name="keywords" content="dashboard, template dashboard, Bootstrap dashboard, admin panel template, sales dashboard, Bootstrap admin panel, stocks dashboard, crm admin dashboard, ecommerce admin panel, admin template, admin panel dashboard, course dashboard, template ecommerce website, dashboard hrm, admin dashboard">

        <!-- TITLE -->
        <title> COLDDUST BENTEL ICE MANUFACTURING </title>

        <!-- FAVICON -->
        <link rel="icon" href="<?php echo $baseUrl; ?>/assets/images/brand-logos/favicon.ico" type="image/x-icon">

        <!-- BOOTSTRAP CSS -->
        <link  id="style" href="<?php echo $baseUrl; ?>/assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">

        <!-- ICONS CSS -->
        <link href="<?php echo $baseUrl; ?>/assets/css/icons.css" rel="stylesheet">

        <!-- STYLES CSS -->
        <link href="<?php echo $baseUrl; ?>/assets/css/styles.css" rel="stylesheet">

        <!-- MAIN JS -->
        <script src="<?php echo $baseUrl; ?>/assets/js/authentication-main.js"></script>

        <?php echo $styles; ?>

	</head>

    <?php echo $errorbody; ?>

        <?php echo $content; ?>
        
        <!-- SCRIPTS -->

        <!-- BOOTSTRAP JS -->
        <script src="<?php echo $baseUrl; ?>/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

        <?php echo $scripts; ?>

        <!-- END SCRIPTS -->

	</body>
</html>
