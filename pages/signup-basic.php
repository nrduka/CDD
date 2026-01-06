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

        <div class="container-lg">
            <div class="row justify-content-center align-items-center authentication authentication-basic h-100">
                <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12">
<!--                     <div class="my-5 d-flex justify-content-center">
                        <a href="index.php">
                            <img src="<?php echo $baseUrl; ?>/assets/images/brand-logos/desktop-logo.png" alt="logo" class="desktop-logo">
                            <img src="<?php echo $baseUrl; ?>/assets/images/brand-logos/desktop-dark.png" alt="logo" class="desktop-dark">
                        </a>
                    </div> -->



                    <form id="form" method="post">
                        <div class="card custom-card">
                            <div class="card-body p-4">
                                <p class="h5 fw-semibold mb-2 text-center">Sign Up</p>
                                <p class="mb-4 text-muted op-7 fw-normal text-center">Welcome & Join us by creating a free account !</p>
                                <div class="row gy-3">
                                    <div class="col-xl-12">
                                        <label for="signup-firstname" class="form-label text-default">First Name</label>
                                        <input type="text" class="form-control form-control-lg" id="signup-firstname" placeholder="First Name" required>
                                        <div class="error"></div>
                                    </div>
                                    <div class="col-xl-12">
                                        <label for="signup-lastname" class="form-label text-default">Last Name</label>
                                        <input type="text" class="form-control form-control-lg" id="signup-lastname" placeholder="Last Name" required>
                                        <div class="error"></div>
                                    </div>

                                     <hr class="mb-2 mt-4" style="border-color: #F1F1F1;">

                                    <div class="col-xl-12">
                                        <label for="signup-username" class="form-label text-default">Username</label>
                                        <input type="text" class="form-control form-control-lg" id="signup-username" placeholder="Username" required>
                                        <div class="error"></div>
                                    </div>


                                    <div class="col-xl-12">
                                        <label for="signup-password" class="form-label text-default">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control form-control-lg" id="signup-password" placeholder="Password" required>
                                            <button class="btn btn-light" onclick="createpassword('signup-password',this)" type="button" id="button-addon2"><i class="ri-eye-off-line align-middle"></i></button>
                                            <div class="error"></div>
                                        </div>
                                    </div>
                                    <div class="col-xl-12 mb-2">
                                        <label for="signup-confirmpassword" class="form-label text-default">Confirm Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control form-control-lg" id="signup-confirmpassword" placeholder="Confirm Password" required>
                                            <button class="btn btn-light" onclick="createpassword('signup-confirmpassword',this)" type="button" id="button-addon21"><i class="ri-eye-off-line align-middle"></i></button>
                                            <div class="error"></div>
                                        </div>
                                       <!--  <div class="form-check mt-3">
                                            <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                                            <label class="form-check-label text-muted fw-normal" for="defaultCheck1">
                                                By creating a account you agree to our <a href="terms-conditions.php" class="text-success"><u>Terms & Conditions</u></a> and <a class="text-success"><u>Privacy Policy</u></a>
                                            </label>
                                        </div> -->
                                    </div>
                                    <div class="col-xl-12 d-grid mt-2">
                                        <!-- <a href="signin-authenticate.php" class="btn btn-lg btn-primary">Create Account</a> -->
                                        <button type="submit" class="btn btn-lg btn-primary">Create Account</button>
                                    </div>
                                </div>
                                <div class="text-center">
                                    <p class="fs-12 text-muted mt-3">Already have an account? <a href="signin-basic.php" class="text-primary">Sign In</a></p>
                                </div>


    <!--                             <div class="text-center my-3 authentication-barrier">
                                    <span>OR</span>
                                </div>
                                <div class="btn-list text-center">
                                    <button class="btn btn-icon btn-light">
                                        <i class="ri-facebook-line fw-bold text-dark op-7"></i>
                                    </button>
                                    <button class="btn btn-icon btn-light">
                                        <i class="ri-google-line fw-bold text-dark op-7"></i>
                                    </button>
                                    <button class="btn btn-icon btn-light">
                                        <i class="ri-twitter-line fw-bold text-dark op-7"></i>
                                    </button>
                                </div> -->


                            </div>
                        </div>
                    </form>


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



<style type="text/css">
    

.form-control.success input{

    border-color: #198754;
}

.form-control.error input{

    border-color: #dc3545;
}


.form-control .error{

    color: #dc3545;
    font-size: 10px;
    height: 13px;
}



</style>




<script type="text/javascript">
    



const form = document.getElementById('form');
const firstname = document.getElementById('signup-firstname');
const lastname = document.getElementById('signup-lastname');
const username = document.getElementById('signup-username');
const password = document.getElementById('signup-password');
const confirmpassword = document.getElementById('signup-confirmpassword');

form.addEventListener('submit', e => {
    e.preventDefault();

    validateInputs();
});

const setError = (element, message) => {
    const inputControl = element.parentElement;
    const errorDisplay = inputControl.querySelector('.error');

    errorDisplay.innerText = message;
    inputControl.classList.add('error');
    inputControl.classList.remove('success')
}

const setSuccess = element => {
    const inputControl = element.parentElement;
    const errorDisplay = inputControl.querySelector('.error');

    errorDisplay.innerText = '';
    inputControl.classList.add('success');
    inputControl.classList.remove('error');
};

const isValidEmail = email => {
    const re = /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}

const validateInputs = () => {
    const firtstnameValue = firtstname.value.trim();
    const lastnameValue = lastname.value.trim();
    const usernameValue = username.value.trim();
    const passwordValue = password.value.trim();
    const confirmpasswordValue = confirmpassword.value.trim();

    if(usernameValue === '') {
        setError(username, 'Username is required');
    } else {
        setSuccess(username);
    }

    if(emailValue === '') {
        setError(email, 'Email is required');
    } else if (!isValidEmail(emailValue)) {
        setError(email, 'Provide a valid email address');
    } else {
        setSuccess(email);
    }

    if(passwordValue === '') {
        setError(password, 'Password is required');
    } else if (passwordValue.length < 8 ) {
        setError(password, 'Password must be at least 8 character.')
    } else {
        setSuccess(password);
    }

    if(password2Value === '') {
        setError(password2, 'Please confirm your password');
    } else if (password2Value !== passwordValue) {
        setError(password2, "Passwords doesn't match");
    } else {
        setSuccess(password2);
    }

};






</script>