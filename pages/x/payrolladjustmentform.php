<?php
    // Generate the base URL for the website by combining the protocol, domain name, and directory path
    $rootFolder = basename($_SERVER['DOCUMENT_ROOT']);
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
?>

<?php
    include 'database-config.php';
    session_start();
    $role = $_SESSION['sess_userrole'];
    $fullname = $_SESSION['sess_firstname'] . " " . $_SESSION['sess_lastname'];
?>

<?php
    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "SPBI";

    $conn = new mysqli($servername, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $empno = $_GET['empno'];

    // Fetch Departments
    $sql = "SELECT empno, firstName, lastName, daily_salary, allowance, company FROM employees WHERE empno='$empno'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {

        $row = $result->fetch_assoc();

        $first_name = $row['firstName'];
        $last_name = $row['lastName'];
        $daily_salary = $row['daily_salary'];
        $allowance = $row['allowance'];
        $company = $row['company'];
   
    }else {
        
        echo "No employee found with empno: $empno";
    }
    
?>

<!-- This code is useful for internal styles  -->
<?php ob_start(); ?>
<?php $styles = ob_get_clean(); ?>
<!-- This code is useful for internal styles  -->

<!-- This code is useful for content -->
<?php ob_start(); ?>



    <form id="userForm" action="add_employee_submit.php" method="POST" novalidate>

    <div class="container">  

        <div class="d-md-flex d-block align-items-center justify-content-between mb-4 mt-5 ps-2 page-header-breadcrumb">
                <h1 class="page-title fw-semibold fs-18 mb-0"><?php echo $first_name." ".$last_name ?></h1>
            </div>
            <!-- Page Header Close -->




        <div class="tab-pane show active" id="first-step" role="tabpanel">
            <div class="p-1 pt-2">
 
                <!-- Start:: row-1 -->
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card custom-card mb-3">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    Employee Payroll Adjustments
                                </div> 
                            </div>
                            
                            <div class="card-body">
                                <div class="row gy-4  mb-3">

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p class="text-dark fw-medium">Employee ID</p>
                                        <input type="text" class="form-control" id="empno" name="empno" value="<?php echo $empno ?>"  disabled>
                                    </div>

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p class="text-dark fw-medium">Daily Salary</p>
                                        <input type="text" class="form-control" id="daily_salary" name="daily_salary" value="<?php echo $daily_salary ?>"  disabled>
                                    </div>

                                    <div class="col-xl-2 col-lg-6 col-md-6 col-sm-12">
                                        <p class="text-dark fw-medium">Allowance</p>
                                        <input type="text" class="form-control" id="allowance" name="allowance" value="<?php echo $allowance ?>"  disabled>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                        <p class="text-dark fw-medium">Company</p>
                                        <input type="text" class="form-control" id="company" name="company" value="<?php echo $company ?>"  disabled>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                        <p for="pay_period" class="text-dark fw-medium">Pay Period</p>
                                        <input type="date" class="form-control" id="pay_period" name="pay_period" required>
                                        <div class="invalid-feedback">
                                            Please provide the date.
                                        </div>
                                    </div>

                                    

                                    <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                        <p for="cutoff" class="text-dark fw-medium">Cutoff</p>
                                        
                                        <select class="form-control custom-select-arrow" id="cutoff" name="cutoff" required>
                                            <option value="">Select Cutoff</option>
                                            <option value="1">First Cutoff</option>
                                            <option value="2">Second Cutoff</option>
                                            <option value="3">Special Payment</option>                                                    
                                        </select>

                                        <div class="invalid-feedback">
                                            Please Select Cutoff.
                                        </div>
                                    </div>

                                    <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                        <p for="month" class="text-dark fw-medium">Payroll Month</p>
                                        
                                        <select class="form-control custom-select-arrow" id="month" name="month" required>
                                            <option value="">Please Select Month</option>
                                            <option value="1">January</option>
                                            <option value="2">February</option>
                                            <option value="3">March</option>
                                            <option value="4">April</option>
                                            <option value="5">May</option>
                                            <option value="6">June</option>
                                            <option value="7">July</option>
                                            <option value="8">August</option>
                                            <option value="9">September</option>
                                            <option value="10">October</option>
                                            <option value="11">November</option>
                                            <option value="12">December</option>                                                    
                                        </select>

                                        <div class="invalid-feedback">
                                            Please Select Month.
                                        </div>
                                    </div>

                                </div>
                            </div>    
                        </div>
                    </div>
                </div>
                <!-- End:: row-1 -->



                <!-- Start:: row-2 -->
                <div class="card custom-card mb-3">
                    <div class="card-header border-bottom-0 justify-content-between" data-bs-toggle="collapse" data-bs-target="#firstCollapse" aria-expanded="false" aria-controls="firstCollapse" style="cursor: pointer;">
                        <div class="card-title">
                            Holiday / Restday Credits
                        </div>
                        <a href="javascript:void(0);">
                            <i class="ri-arrow-down-s-line fs-18 collapse-open"></i>
                            <i class="ri-arrow-up-s-line collapse-close fs-18"></i>
                        </a>
                    </div>
                    
                    <div class="collapse border-top" id="firstCollapse">
                        <div class="card-body">
                            <div class="row gy-4 mb-3">

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="REGHOL" class="text-dark fw-medium">Regular Holiday</p>
                                    <input type="text" class="form-control" id="REGHOL" name="REGHOL" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="REGHOLRESTDAY" class="text-dark fw-medium">Regular Holiday on a Rest Day</p>
                                    <input type="text" class="form-control" id="REGHOLRESTDAY" name="REGHOLRESTDAY" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="RESTDAY" class="text-dark fw-medium">Rest Day</p>
                                    <input type="text" class="form-control" id="RESTDAY" name="RESTDAY" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="SPCHOL" class="text-dark fw-medium">Special Holiday</p>
                                    <input type="text" class="form-control" id="SPCHOL" name="SPCHOL" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="SPCHOLRESTDAY" class="text-dark fw-medium">Special Holiday on a Rest Day</p>
                                    <input type="text" class="form-control" id="SPCHOLRESTDAY" name="SPCHOLRESTDAY" placeholder="NO. OF HRS" size="30">
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-2 -->

                <!-- Start:: row-3 -->
                <div class="card custom-card mb-3">
                    <div class="card-header border-bottom-0 justify-content-between" data-bs-toggle="collapse" data-bs-target="#secondCollapse" aria-expanded="false" aria-controls="secondCollapse" style="cursor: pointer;">
                        <div class="card-title">
                            Regular Overtime 
                        </div>
                        <a href="javascript:void(0);">
                            <i class="ri-arrow-down-s-line fs-18 collapse-open"></i>
                            <i class="ri-arrow-up-s-line collapse-close fs-18"></i>
                        </a>
                    </div>
                    <div class="collapse border-top" id="secondCollapse">
                        <div class="card-body">
                            <div class="row gy-4 mb-3">

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="regotds" class="text-dark fw-medium">Regular OT Day-Shift</p>
                                    <input type="text" class="form-control" id="regotds" name="regotds" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="regotns" class="text-dark fw-medium">Regular OT Night-Shift</p>
                                    <input type="text" class="form-control" id="regotns" name="regotns" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="holot" class="text-dark fw-medium">Holiday OT</p>
                                    <input type="text" class="form-control" id="holot" name="holot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="holrdot" class="text-dark fw-medium">Holiday OT on a Rest Day</p>
                                    <input type="text" class="form-control" id="holrdot" name="holrdot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="rdot" class="text-dark fw-medium">Rest Day OT</p>
                                    <input type="text" class="form-control" id="rdot" name="rdot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="spcot" class="text-dark fw-medium">Special Holiday OT</p>
                                    <input type="text" class="form-control" id="spcot" name="spcot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="spcrdot" class="text-dark fw-medium">Special Holiday OT on a Rest Day</p>
                                    <input type="text" class="form-control" id="spcrdot" name="spcrdot" placeholder="NO. OF HRS" size="30">
                                </div>
                               
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-3 -->


                <!-- Start:: row-4 -->
                <div class="card custom-card mb-3">
                    <div class="card-header border-bottom-0 justify-content-between" data-bs-toggle="collapse" data-bs-target="#thirdCollapse" aria-expanded="false" aria-controls="thirdCollapse" style="cursor: pointer;">
                        <div class="card-title">
                            Night Differential / Overtime
                        </div>
                        <a href="javascript:void(0);">
                            <i class="ri-arrow-down-s-line fs-18 collapse-open"></i>
                            <i class="ri-arrow-up-s-line collapse-close fs-18"></i>
                        </a>
                    </div>
                    <div class="collapse border-top" id="thirdCollapse">
                        <div class="card-body">
                            <div class="row gy-4 mb-3">

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndreg" class="text-dark fw-medium">N.D on Regular Day</p>
                                    <input type="text" class="form-control" id="ndreg" name="ndreg" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndregot" class="text-dark fw-medium">N.D OT on a Regular Day</p>
                                    <input type="text" class="form-control" id="ndregot" name="ndregot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndreghol" class="text-dark fw-medium">N.D on a Regular Holiday</p>
                                    <input type="text" class="form-control" id="ndreghol" name="ndreghol" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndregholot" class="text-dark fw-medium">N.D  OT  on a Regular Holiday</p>
                                    <input type="text" class="form-control" id="ndregholot" name="ndregholot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndregholrd" class="text-dark fw-medium">N.D - Reg. Holiday on a Rest Day</p>
                                    <input type="text" class="form-control" id="ndregholrd" name="ndregholrd" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndregholrdot" class="text-dark fw-medium">N.D OT - Reg. Holiday on a Rest Day</p>
                                    <input type="text" class="form-control" id="ndregholrdot" name="ndregholrdot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndrd" class="text-dark fw-medium">N.D on a Rest Day</p>
                                    <input type="text" class="form-control" id="ndrd" name="ndrd" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndrdot" class="text-dark fw-medium">N.D OT on a Rest Day</p>
                                    <input type="text" class="form-control" id="ndrdot" name="ndrdot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndspc" class="text-dark fw-medium">N.D on a Special Holiday</p>
                                    <input type="text" class="form-control" id="ndspc" name="ndspc" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndspcot" class="text-dark fw-medium">N.D OT on a Special Holiday</p>
                                    <input type="text" class="form-control" id="ndspcot" name="ndspcot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndspcrd" class="text-dark fw-medium">N.D on a Special Holiday/Rest Day</p>
                                    <input type="text" class="form-control" id="ndspcrd" name="ndspcrd" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ndspcrdot" class="text-dark fw-medium">N.D OT on a Special Holiday/Rest Day</p>
                                    <input type="text" class="form-control" id="ndspcrdot" name="ndspcrdot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="nddblhol" class="text-dark fw-medium">N.D on a Double Holiday</p>
                                    <input type="text" class="form-control" id="nddblhol" name="nddblhol" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="nddblholot" class="text-dark fw-medium">N.D OT on a Double Holiday</p>
                                    <input type="text" class="form-control" id="nddblholot" name="nddblholot" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="nddblholrd" class="text-dark fw-medium">N.D on a Double Holiday/Rest Day</p>
                                    <input type="text" class="form-control" id="nddblholrd" name="nddblholrd" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="nddblholrdot" class="text-dark fw-medium">N.D OT on a Double Holiday/Rest Day</p>
                                    <input type="text" class="form-control" id="nddblholrdot" name="nddblholrdot" placeholder="NO. OF HRS" size="30">
                                </div>
                               
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-4 -->


                <!-- Start:: row-5 -->
                <div class="card custom-card mb-3">
                    <div class="card-header border-bottom-0 justify-content-between" data-bs-toggle="collapse" data-bs-target="#fourthCollapse" aria-expanded="false" aria-controls="fourthCollapse" style="cursor: pointer;">
                        <div class="card-title">
                            Leaves
                        </div>
                        <a href="javascript:void(0);">
                            <i class="ri-arrow-down-s-line fs-18 collapse-open"></i>
                            <i class="ri-arrow-up-s-line collapse-close fs-18"></i>
                        </a>
                    </div>
                    <div class="collapse border-top" id="fourthCollapse">
                        <div class="card-body">
                            <div class="row gy-4 mb-3">

                                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                    <p for="SL" class="text-dark fw-medium">Sick Leave</p>
                                    <input type="text" class="form-control" id="SL" name="SL" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                    <p for="VL" class="text-dark fw-medium">Vacation Leave</p>
                                    <input type="text" class="form-control" id="VL" name="VL" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-12">
                                    <p for="EL" class="text-dark fw-medium">Emergency Leave</p>
                                    <input type="text" class="form-control" id="EL" name="EL" placeholder="NO. OF HRS" size="30">
                                </div>                                

                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-5 -->


                <!-- Start:: row-6 -->
                <div class="card custom-card mb-3">
                    <div class="card-header border-bottom-0 justify-content-between" data-bs-toggle="collapse" data-bs-target="#fifthCollapse" aria-expanded="false" aria-controls="fifthCollapse" style="cursor: pointer;">
                        <div class="card-title">
                            Additional
                        </div>
                        <a href="javascript:void(0);">
                            <i class="ri-arrow-down-s-line fs-18 collapse-open"></i>
                            <i class="ri-arrow-up-s-line collapse-close fs-18"></i>
                        </a>
                    </div>
                    <div class="collapse border-top" id="fifthCollapse">
                        <div class="card-body">
                            <div class="row gy-4 mb-3">

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ALLOWANCE" class="text-dark fw-medium">Allowance</p>
                                    <input type="text" class="form-control" id="ALLOWANCE" name="ALLOWANCE" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="DEBIT" class="text-dark fw-medium">DEBIT</p>
                                    <input type="text" class="form-control" id="DEBIT" name="DEBIT" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="INCENTIVE" class="text-dark fw-medium">Incentive</p>
                                    <input type="text" class="form-control" id="INCENTIVE" name="INCENTIVE" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="XIIIMONTH" class="text-dark fw-medium">13th Month</p>
                                    <input type="text" class="form-control" id="XIIIMONTH" name="XIIIMONTH" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="BONUS" class="text-dark fw-medium">Bonus</p>
                                    <input type="text" class="form-control" id="BONUS" name="BONUS" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="SIL" class="text-dark fw-medium">Service Incentive Leave</p>
                                    <input type="text" class="form-control" id="SIL" name="SIL" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="COMPANYBOND" class="text-dark fw-medium">COMPANY BOND</p>
                                    <input type="text" class="form-control" id="COMPANYBOND" name="COMPANYBOND" placeholder="PHP 0.00" size="30">
                                </div>
  
                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-6 -->


                <!-- Start:: row-7 -->
                <div class="card custom-card mb-3">
                    <div class="card-header border-bottom-0 justify-content-between" data-bs-toggle="collapse" data-bs-target="#sixthCollapse" aria-expanded="false" aria-controls="sixthCollapse" style="cursor: pointer;">
                        <div class="card-title">
                            Deductions
                        </div>
                        <a href="javascript:void(0);">
                            <i class="ri-arrow-down-s-line fs-18 collapse-open"></i>
                            <i class="ri-arrow-up-s-line collapse-close fs-18"></i>
                        </a>
                    </div>
                    <div class="collapse border-top" id="sixthCollapse">
                        <div class="card-body">
                            <div class="row gy-4 mb-3">

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="OTHERDEDUCTION" class="text-dark fw-medium">OTHER DEDUCTION</p>
                                    <input type="text" class="form-control" id="OTHERDEDUCTION" name="OTHERDEDUCTION" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="CREDIT" class="text-dark fw-medium">CREDIT</p>
                                    <input type="text" class="form-control" id="CREDIT" name="CREDIT" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="LOAN" class="text-dark fw-medium">OTHER LOAN</p>
                                    <input type="text" class="form-control" id="LOAN" name="LOAN" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="SSSLOAN" class="text-dark fw-medium">SSS LOAN</p>
                                    <input type="text" class="form-control" id="SSSLOAN" name="SSSLOAN" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="HDMFLOAN" class="text-dark fw-medium">PAGIBIG LOAN</p>
                                    <input type="text" class="form-control" id="HDMFLOAN" name="HDMFLOAN" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="CASHADVANCE" class="text-dark fw-medium">Cash Advance</p>
                                    <input type="text" class="form-control" id="CASHADVANCE" name="CASHADVANCE" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="MEDICAL" class="text-dark fw-medium">Medical</p>
                                    <input type="text" class="form-control" id="MEDICAL" name="MEDICAL" placeholder="PHP 0.00" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="TARDINESS" class="text-dark fw-medium">TARDINESS</p>
                                    <input type="text" class="form-control" id="TARDINESS" name="TARDINESS" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="UNDERTIME" class="text-dark fw-medium">Undertime</p>
                                    <input type="text" class="form-control" id="UNDERTIME" name="UNDERTIME" placeholder="NO. OF HRS" size="30">
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="ABSENCES" class="text-dark fw-medium">Absences</p>
                                    <input type="text" class="form-control" id="ABSENCES" name="ABSENCES" placeholder="NO. OF DAYS" size="30">
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-7 -->


                <div class="col-12 d-flex justify-content-end mb-4">
                    <button class="btn btn-lg btn-secondary-light  btn-wave me-2 px-4" type="submit" name="Submit">Cancel</button>
                    <button class="btn btn-lg btn-primary btn-wave px-4" type="submit" name="Submit">Submit</button>
                </div>



                
            </div>
        </div>
    </div>
    </form>


    <!-- FORM VALIDATIONS JS -->
    <script src="<?php echo $baseUrl; ?>/assets/js/validation.js"></script>

    <!-- INTERNAL CHOICES JS -->
        <script src="<?php echo $baseUrl; ?>/assets/js/choices.js"></script>

    <?php $content = ob_get_clean(); ?>
    <!-- Content -->

    <!-- Internal Scripts -->
    <?php ob_start(); ?>


    <?php $scripts = ob_get_clean(); ?>
    <!-- Internal Scripts -->

    <!-- This code use for render base file -->
    <?php include 'layouts/base.php'; ?>
    <?php include 'add_employee_settings.php'; ?>
    <!-- This code use for render base file -->

<?php
// Close the database connection after rendering the content
$conn->close();
?>
