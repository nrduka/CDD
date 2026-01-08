<!-- This code generates the base URL for the website by combining the protocol, domain name, and directory path -->
<?php
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>
<!-- This code generates the base URL for the website by combining the protocol, domain name, and directory path -->

<!-- This code is useful for internal styles  -->
<?php ob_start(); ?>


<?php $styles = ob_get_clean(); ?>
<!-- This code is useful for internal styles  -->

<!-- This code is useful for content -->
<?php ob_start(); ?>

<?php ob_start(); ?>
<body>
<?php $errorbody = ob_get_clean(); ?>

        <div class="container">
            <div class="row justify-content-center align-items-center authentication authentication-basic h-100">
                <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12">


                    <div class="card custom-card">
                        <div class="card-body p-4">
                            <p class="h5 fw-semibold mb-2 text-center">Log In</p>
                            <p class="mb-4 text-muted op-7 fw-normal text-center">COLDDUST BENTEL ICE MANUFACTURING</p>


                        <form method="post" action="authenticate.php" name="admin_form">
                            <div class="row gy-3">
                                <div class="col-xl-12">
                                    <label for="signin-username" class="form-label text-default">User Name</label>
                                    <input type="text" class="form-control form-control-lg" id="signin-username" name="username" placeholder="username" required>
                                </div>
                                <div class="col-xl-12 mb-4">
                                    <label for="signin-password" class="form-label text-default">Password</label>

                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-lg" id="signin-password" name="password" placeholder="password" required>
                                        <button class="btn btn-light" type="button" onclick="createpassword('signin-password',this)" id="button-addon2"><i class="ri-eye-off-line align-middle"></i></button>
                                    </div>

                                </div>

                                <div class="col-xl-12 d-grid mt-2">
                                    <!-- <a href="index.php" class="btn btn-lg btn-primary">Sign In</a> -->
                                     <button class="btn btn-lg btn-primary" id = "btn-login" name = "btn-login">Log in</button>
                                </div>
                            </div>
                        </form>


                            </div>
                        </div>


                              <?php 

                                $errors = array(
                                    1=>"Invalid user name or password, Try again",
                                    2=>"Please login to access this area"
                                  );

                                $error_id = isset($_GET['err']) ? (int)$_GET['err'] : 0;

                                if ($error_id == 1) {
                                        echo '<p>'.'<center class="text-danger">'.$errors[$error_id].'</center>'.'</p>';
                                    }elseif ($error_id == 2) {
                                        echo '<p>'.'<center class="text-danger">'.$errors[$error_id].'</center>'.'</p>';
                                    }
                               ?>
                    </div>
                </div>
            </div>
        </div>

<?php $content = ob_get_clean(); ?>
<!-- This code is useful for content -->

<!-- This code is useful for internal scripts  -->
<?php ob_start(); ?>

        <!-- SHOW PASSWORD JS -->
        <script src="<?php echo $baseUrl; ?>/assets/js/show-password.js"></script>

<?php $scripts = ob_get_clean(); ?>
<!-- This code is useful for internal scripts  -->

<!-- This code use for render base file -->
<?php include 'layouts/custom-base.php'; ?>
<!-- This code use for render base file -->
