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

    // Fetch Departments
    $sqlDepartments = "SELECT department FROM department";
    $resultDepartments = $conn->query($sqlDepartments);

    // Fetch Companies
    $sqlCompanies = "SELECT company_description FROM company";
    $resultCompanies = $conn->query($sqlCompanies);

    // Fetch Clients
    $sqlClients = "SELECT client_name FROM client";
    $resultClients = $conn->query($sqlClients);

    // Fetch Employment Status
    $sqlEmploymentStatus = "SELECT status_name FROM employment_status";
    $resultEmploymentStatus = $conn->query($sqlEmploymentStatus);

    // Fetch Job Titles
    $sqlJobTitles = "SELECT title FROM jobtitle";
    $resultJobTitles = $conn->query($sqlJobTitles);

    // Fetch Pag-IBIG table data
    $sqlPagibigTable = "SELECT * FROM pagibigtable";
    $resultPagibigTable = $conn->query($sqlPagibigTable);

    // Fetch PhilHealth table data
    $sqlPhilhealthTable = "SELECT * FROM philhealthtable";
    $resultPhilhealthTable = $conn->query($sqlPhilhealthTable);

    // Fetch SSS table data
    $sqlSssTable = "SELECT * FROM ssstable";
    $resultSssTable = $conn->query($sqlSssTable);

?>

<!-- This code is useful for internal styles  -->
<?php ob_start(); ?>
<?php $styles = ob_get_clean(); ?>
<!-- This code is useful for internal styles  -->

<!-- This code is useful for content -->
<?php ob_start(); ?>



    <form id="userForm" action="add_employee_submit.php" method="POST" novalidate>

    <div class="container">   
        <div class="tab-pane show active" id="first-step" role="tabpanel">
            <div class="p-1 pt-2">
 
                <!-- Start:: row-1 -->
                <div class="row">
                    <div class="col-xl-12">
                        <div class="card custom-card mb-3">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    Employment Details
                                </div> 
                            </div>
                            
                            <div class="card-body">
                                <div class="row gy-4  mb-3">

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="empno" class="text-dark fw-medium">Employee ID</p>
                                        <input type="text" class="form-control" id="empno" name="empno" placeholder="Employee ID" required>
                                        <div class="invalid-feedback">
                                            Please provide Employee ID.
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="firstName" class="text-dark fw-medium">First Name</p>
                                        <input type="text" class="form-control" id="firstName" name="firstName" placeholder="First Name"  required>
                                        <div class="invalid-feedback">
                                            Please provide the First Name.
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="middleName" class="text-dark fw-medium">Middle Name</p>
                                        <input type="text" class="form-control" id="middleName" name="middleName" placeholder="Middle Name" required>
                                        <div class="invalid-feedback">
                                            Please provide the Middle Name.
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="lastName" class="text-dark fw-medium">Last Name</p>
                                        <input type="text" class="form-control" id="lastName" name="lastName" placeholder="Last Name"  required>
                                        <div class="invalid-feedback">
                                            Please provide the Last Name.
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="dateHired" class="text-dark fw-medium">Date Hired</p>
                                        <input type="date" class="form-control" id="dateHired" name="dateHired" required>
                                        <div class="invalid-feedback">
                                            Please provide the date.
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="company" class="text-dark fw-medium">Company</p>
                                            
                                        <select class="form-control custom-select-arrow " id="company" name="company" required>
                                            <option value="">Select Company</option>
                                                <?php
                                                if ($resultCompanies->num_rows > 0) {
                                                while ($row = $resultCompanies->fetch_assoc()) {
                                                echo '<option value="' . htmlspecialchars($row['company_description']) . '">' . htmlspecialchars($row['company_description']) . '</option>';
                                                }
                                                }else{
                                                echo '<option value="">No Companies Available</option>';
                                                }
                                                ?>
                                        </select>

                                        <div class="invalid-feedback">
                                            Please Select a Company.
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="client" class="text-dark fw-medium">Client</p>

                                        <select class="form-control custom-select-arrow" id="client" name="client" required>
                                            <option value="">Select Client</option>
                                                <?php
                                                if ($resultClients->num_rows > 0) {
                                                while ($row = $resultClients->fetch_assoc()) {
                                                echo '<option value="' . htmlspecialchars($row['client_name']) . '">' . htmlspecialchars($row['client_name']) . '</option>';
                                                }
                                                }else{
                                                echo '<option value="">No Clients Available</option>';
                                                }
                                                ?>
                                        </select>

                                        <div class="invalid-feedback">
                                            Please Select a Client.
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="empstatus" class="text-dark fw-medium">Employment Status</p>
                                        
                                        <select class="form-control custom-select-arrow" id="empstatus" name="empstatus" required>
                                            <option value="">Select Status</option>                                                    
                                            <option value="Active">Active</option>
                                            <option value="Resigned">Resigned</option>
                                            <option value="Pooled">Pooled</option>
                                            <option value="Floating">Floating</option>
                                            <option value="Suspended">Suspended</option>
                                            <option value="Retired">Retired</option>                                                    
                                        </select>

                                        <div class="invalid-feedback">
                                            Please Select Status.
                                        </div>
                                    </div>




                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="department" class="text-dark fw-medium">Department</p>
                                
                                        <select class="form-control custom-select-arrow" id="department" name="department" required>
                                            <option value="">Select Department</option>
                                                <?php
                                                if ($resultDepartments->num_rows > 0) {
                                                while ($row = $resultDepartments->fetch_assoc()) {
                                                echo '<option value="' . htmlspecialchars($row['department']) . '">' . htmlspecialchars($row['department']) . '</option>';
                                                }
                                                } else {
                                                echo '<option value="">No Departments Available</option>';
                                                }
                                                ?>
                                        </select>

                                        <div class="invalid-feedback">
                                            Please Select a Department.
                                        </div>
                                    </div>

                                    <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                        <p for="position" class="text-dark fw-medium">Job Title</p>

                                        <select class="form-control custom-select-arrow" id="position" name="position" required>
                                            <option value="">Select Job Title</option>
                                            <?php
                                            if ($resultJobTitles->num_rows > 0) {
                                                while ($row = $resultJobTitles->fetch_assoc()) {
                                                    echo '<option value="' . htmlspecialchars($row['title']) . '">' . htmlspecialchars($row['title']) . '</option>';
                                                }
                                            } else {
                                                echo '<option value="">No Job Titles Available</option>';
                                            }
                                            ?>
                                        </select>

                                        <div class="invalid-feedback">
                                            Please Select a Title.
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
                    <div class="card-header border-bottom-0 justify-content-between" data-bs-toggle="collapse" data-bs-target="#payrollCollapse" aria-expanded="false" aria-controls="payrollCollapse" style="cursor: pointer;">
                        <div class="card-title">
                            Payroll Details
                        </div>
                        <a href="javascript:void(0);">
                            <i class="ri-arrow-down-s-line fs-18 collapse-open"></i>
                            <i class="ri-arrow-up-s-line collapse-close fs-18"></i>
                        </a>
                    </div>
                    
                    <div class="collapse border-top" id="payrollCollapse">
                        <div class="card-body">
                            <div class="row gy-4 mb-3">

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="atm" class="text-dark fw-medium">Account Number</p>
                                    <input type="text" class="form-control" id="atm" name="atm" placeholder="Bank Number or CASH" required>
                                    <div class="invalid-feedback">
                                        Please provide Account Number.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="daily_salary" class="text-dark fw-medium">Daily Rate</p>
                                    <input type="text" class="form-control" id="daily_salary" name="daily_salary" placeholder="0.00" required>
                                    <div class="invalid-feedback">
                                        Please provide the Daily Rate.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="secondaryrate" class="text-dark fw-medium">Secondary/ Provincial Rate</p>
                                    <input type="text" class="form-control" id="secondaryrate" name="secondaryrate" placeholder="0.00" required>
                                    <div class="invalid-feedback">
                                        Please provide the Secondary Rate.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="monthly_salary" class="text-dark fw-medium">Monthly Rate</p>
                                    <input type="text" class="form-control" id="monthly_salary" name="monthly_salary" placeholder="0.00"  required>
                                    <div class="invalid-feedback">
                                        Please provide the Monthly Rate.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="allowance" class="text-dark fw-medium">Monthly Allowance</p>
                                    <input type="text" class="form-control" id="allowance" name="allowance" placeholder="0.00" required>
                                    <div class="invalid-feedback">
                                        Please provide Allowance.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="tinID" class="text-dark fw-medium">Tin ID</p>
                                    <input type="text" class="form-control" id="tinID" name="tinID" placeholder="Tin ID" required>
                                    <div class="invalid-feedback">
                                        Please provide the Tin ID.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="philhealthID" class="text-dark fw-medium">Philhealth ID</p>
                                    <input type="text" class="form-control" id="philhealthID" name="philhealthID" placeholder="Philhealth ID" required>
                                    <div class="invalid-feedback">
                                        Please provide the Philhealth ID.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="pagibigID" class="text-dark fw-medium">PAGIBIG ID</p>
                                    <input type="text" class="form-control" id="pagibigID" name="pagibigID" placeholder="PAGIBIG ID"  required>
                                    <div class="invalid-feedback">
                                        Please provide the PAGIBIG ID.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="sssID" class="text-dark fw-medium">SSS ID</p>
                                    <input type="text" class="form-control" id="sssID" name="sssID" placeholder="SSS ID"  required>
                                    <div class="invalid-feedback">
                                        Please provide SSS ID.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="tax_exemption" class="text-dark fw-medium">Tax Status</p>
                                            
                                    <select class="form-control custom-select-arrow " id="tax_exemption" name="tax_exemption" required>
                                        <option value="">Select Status</option>
                                        <option value="NA">Not Applicable</option>
                                        <option value="SME">Single / Married</option>
                                        <option value="SME1">Single / Married With ONE DEPENDENT</option>
                                        <option value="SME2">Single / Married With TWO DEPENDENT</option>
                                        <option value="SME3">Single / Married With THREE DEPENDENT</option>
                                        <option value="SME4">Single / Married With FOUR DEPENDENT</option>          
                                    </select>

                                    <div class="invalid-feedback">
                                        Please select Tax Status.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="philhealthbracket" class="text-dark fw-medium">Philhealth Contribution</p>
                                            
                                    <select class="form-control custom-select-arrow" id="philhealthbracket" name="philhealthbracket" required>
                                        <option value="">Select Bracket</option>
                                            <?php
                                                if ($resultPhilhealthTable->num_rows > 0) {
                                                    while ($row = $resultPhilhealthTable->fetch_assoc()) {
                                                    echo '<option value="' . htmlspecialchars($row['philhealthbracket']) . '">' . htmlspecialchars($row['philhealthsalaryrange']) . '</option>';
                                                            }
                                                } else {
                                                    echo '<option value="">No Contributions Available</option>';
                                                }
                                            ?>
                                    </select>

                                    <div class="invalid-feedback">
                                        Please select Contribution.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="sssbracket" class="text-dark fw-medium">SSS Contribution</p>
                                            
                                    <select class="form-control custom-select-arrow" id="sssbracket" name="sssbracket" required>
                                        <option value="">Select Bracket</option>
                                            <?php
                                                if ($resultSssTable->num_rows > 0) {
                                                    while ($row = $resultSssTable->fetch_assoc()) {
                                                        echo '<option value="' . htmlspecialchars($row['sssbracket']) . '">' . htmlspecialchars($row['ssssalaryrange']) . '</option>';
                                                            }
                                                } else {
                                                        echo '<option value="">No Contributions Available</option>';
                                                }
                                            ?>
                                    </select>

                                    <div class="invalid-feedback">
                                        Please select Contribution.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="hdmfbracket" class="text-dark fw-medium">PAGIBIG Contribution</p>
                                            
                                    <select class="form-control custom-select-arrow" id="hdmfbracket" name="hdmfbracket" required>
                                        <option value="">Select Bracket</option>
                                            <?php
                                                if ($resultPagibigTable->num_rows > 0) {
                                                    while ($row = $resultPagibigTable->fetch_assoc()) {
                                                        echo '<option value="' . htmlspecialchars($row['hdmfbracket']) . '">' . htmlspecialchars($row['hdmfemployershare']) . '</option>';
                                                            }
                                                } else {
                                                        echo '<option value="">No Contributions Available</option>';
                                                }
                                            ?>
                                    </select>

                                    <div class="invalid-feedback">
                                        Please select Contribution.
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-2 -->

                <!-- Start:: row-3 -->
                <div class="card custom-card mb-3">
                    <div class="card-header border-bottom-0 justify-content-between" data-bs-toggle="collapse" data-bs-target="#personalCollapse" aria-expanded="false" aria-controls="personalCollapse" style="cursor: pointer;">
                        <div class="card-title">
                            Personal Details
                        </div>
                        <a href="javascript:void(0);">
                            <i class="ri-arrow-down-s-line fs-18 collapse-open"></i>
                            <i class="ri-arrow-up-s-line collapse-close fs-18"></i>
                        </a>
                    </div>
                    <div class="collapse border-top" id="personalCollapse">
                        <div class="card-body">
                            <div class="row gy-4 mb-3">

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="address" class="text-dark fw-medium">Address</p>
                                    <input type="text" class="form-control" id="address" name="address" placeholder="Address" required>
                                    <div class="invalid-feedback">
                                        Please Provide Address.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="dob" class="text-dark fw-medium">Date of Birth</p>
                                    <input type="date" class="form-control" id="dob" name="dob" placeholder="Date of Birth" required>
                                    <div class="invalid-feedback">
                                        Please provide the date.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="age" class="text-dark fw-medium">Age</p>
                                    <input type="text" class="form-control" id="age" name="age" placeholder="Age" required>
                                    <div class="invalid-feedback">
                                        Please provide age.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="education" class="text-dark fw-medium">Education</p>
                                    <input type="text" class="form-control" id="education" name="education" placeholder="Education" required>
                                    <div class="invalid-feedback">
                                        Please provide Education.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="contact" class="text-dark fw-medium">Contact Number</p>
                                    <input type="text" class="form-control" id="contact" name="contact" placeholder="Contact Number" required>
                                    <div class="invalid-feedback">
                                        Please provide Contact Number.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="civil" class="text-dark fw-medium">Civil Status</p>
                                            
                                    <select class="form-control custom-select-arrow " id="civil" name="civil" required>
                                        <option value="">Select Status</option>                                                    
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Separated">Separated</option>
                                        <option value="Widowed">Widowed</option>           
                                    </select>

                                    <div class="invalid-feedback">
                                        Please select Civil Status.
                                    </div>
                                </div>

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="gender" class="text-dark fw-medium">Gender</p>
                                            
                                    <select class="form-control custom-select-arrow " id="gender" name="gender" required>
                                        <option value="">Select Status</option>                                                    
                                        <option value="Single">Single</option>
                                        <option value="Married">Married</option>
                                        <option value="Separated">Separated</option>
                                        <option value="Widowed">Widowed</option>           
                                    </select>

                                    <div class="invalid-feedback">
                                        Please select Gender.
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- End:: row-3 -->

                <button class="btn btn-md btn-primary mb-3" type="submit" name="Submit">Save Employee</button>
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
