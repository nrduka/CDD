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

<?php $styles = ob_get_clean(); ?>
<!-- This code is useful for internal styles  -->

<!-- This code is useful for content -->
<?php ob_start(); ?>



        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-md-flex d-block align-items-center justify-content-between mb-4 page-header-breadcrumb">
                <h1 class="page-title fw-semibold fs-18 mb-0">Employee Payroll</h1>
            </div>
            <!-- Page Header Close -->


            <form class="form-inline" method="post" action="process_payroll.php" id="payperiod">

            <!-- Start:: row-1 -->
            <div class="row">
                <div class="col-xl-12">
                    <div class="card custom-card mb-3">
                        <!-- <div class="card-header justify-content-between">
                            <div class="card-title">
                                Employment Details
                            </div> 
                        </div> -->
                        
                        <div class="card-body">
                            <div class="row gy-4 mb-3 align-bottom">

                                <div class="col-xl-3 col-lg-6 col-md-6 col-sm-12">
                                    <p for="company" class="text-dark fw-semibold">Company</p>
                                    <input type="text" class="form-control" id="company" name="company" value ="Sierra Pines Baguio Inc." disabled>
                                </div>

                                <div class="col-xl-2 col-lg-6 col-md-6 col-sm-12">
                                    <p for="startDate" class="text-dark fw-semibold">Start Date</p>
                                    <input type="date" class="form-control" id="startDate" name="startDate" required>
                                    <div class="invalid-feedback">
                                        Please provide date.
                                    </div>
                                </div>

                                <div class="col-xl-2 col-lg-6 col-md-6 col-sm-12">
                                    <p for="endDate" class="text-dark fw-semibold">End Date</p>
                                    <input type="date" class="form-control" id="endDate" name="endDate" required>
                                    <div class="invalid-feedback">
                                        Please provide date.
                                    </div>
                                </div>

                                <div class="col-xl-2 col-lg-6 col-md-6 col-sm-12">
                                    <p for="payPeriod" class="text-dark fw-semibold">Pay Period</p>
                                    <input type="date" class="form-control" id="payPeriod" name="payPeriod" required>
                                    <div class="invalid-feedback">
                                        Please provide date.
                                    </div>
                                </div>

                                <div class="col-xl-2 col-lg-6 col-md-6 col-sm-12">
                                    <p for="cutoff" class="text-dark fw-semibold">Cutoff</p>
                                            
                                    <select class="form-control custom-select-arrow " id="cutoff" name="cutoff" required>
                                        <option value="">Select Cutoff</option>
                                        <option value="1">First Cutoff</option>
                                        <option value="2">Second Cutoff</option>
                                        <option value="3">Special Payment</option>       
                                    </select>

                                    <div class="invalid-feedback">
                                        Please select Tax Status.
                                    </div>
                                </div>

                                <div class="col-xl-1 col-lg-6 col-md-6 col-sm-12 d-flex flex-column">
                                    <button class="btn btn-primary mt-auto" type="submit" name="Submit">Search</button>
                                </div>
                            </div>
                        </div>    
                    </div>
                </div>
            </div>
            <!-- End:: row-1 -->

            </form>

            <!-- Start::row-2 -->
            <div class="row">
                <div class="col-xl-12">
                    <div class="card custom-card">
                        <div class="card-header justify-content-between">
                            <div class="card-title">
                                Payroll Details
                            </div>
                            <div class="prism-toggle">
                                <a onclick="window.location.reload(); return false;" target="popup" class="btn btn-info" role="button">Refresh</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="datatable-basic" class="table table-bordered text-nowrap w-100">
                                    <thead>
                                        <tr>
                                            <th>Action</th>
                                            <th>Employee No</th>
                                            <th>Full Name</th>
                                            <th>Pay Period</th>
                                            <th>Cutoff</th>
                                            <th>Total Worked Days</th>
                                            <th>Total Absences</th>
                                            <th>Allowance</th>                        
                                            <th>Gross Pay</th>
                                            <th>Basic Pay</th>   
                                            <th>Deductions</th>
                                            <th>Taxable Income</th> 
                                            <th>Tax</th> 
                                            <th>Net Pay</th>
                                            <th>Reg Hol</th>
                                            <th>Reg Hol Restday</th>
                                            <th>Restday</th>
                                            <th>Spc Hol</th>
                                            <th>Spc Hol Restday</th>
                                            <th>Reg OT DS</th>
                                            <th>Reg OT NS</th>
                                            <th>Reg OT</th>
                                            <th>Hol OT</th>
                                            <th>Hol RD OT</th>
                                            <th>RD OT</th>
                                            <th>Spc OT</th>
                                            <th>Spc RD OT</th>
                                            <th>ND Reg</th>
                                            <th>ND Reg OT</th>
                                            <th>ND Reg Hol</th>
                                            <th>ND Reg Hol OT</th>
                                            <th>ND Reg Hol RD</th>
                                            <th>ND Reg Hol RD OT</th>
                                            <th>ND RD</th>
                                            <th>ND RD OT</th>
                                            <th>ND Spc</th>
                                            <th>ND Spc OT</th>
                                            <th>ND Spc RD</th>
                                            <th>ND Spc RD OT</th>
                                            <th>ND Dbl Hol</th>
                                            <th>ND Dbl Hol OT</th>
                                            <th>ND Dbl Hol RD</th>
                                            <th>ND Dbl Hol RD OT</th>
                                            <th>SL</th>
                                            <th>VL</th>
                                            <th>EL</th>
                                            <th>Incentive</th>
                                            <th>13th Month</th>
                                            <th>Bonus</th>
                                            <th>SIL</th>
                                            <th>Debit</th>
                                            <th>Company Bond</th>
                                            <th>Credit</th>
                                            <th>Other Deduction</th>
                                            <th>Medical</th>
                                            <th>Loan</th>
                                            <th>SSS Loan</th>
                                            <th>HDMF Loan</th>
                                            <th>Cash Advance</th>
                                            <th>Tardiness</th>
                                            <th>Undertime</th>
                                            <th>Philhealth Employer Share</th>
                                            <th>Philhealth Employee Share</th>
                                            <th>SSS Employer Share</th>
                                            <th>SSS Employee Share</th>
                                            <th>Pagibig Employer Share</th>
                                            <th>Pagibig Employee Share</th>
                                        </tr>
                                    </thead>
                                    <tfoot>
                                        <tr>
                                            <th>Action</th>
                                            <th>Employee No</th>
                                            <th>Full Name</th>
                                            <th>Pay Period</th>
                                            <th>Cutoff</th>
                                            <th>Total Worked Days</th>
                                            <th>Total Absences</th>
                                            <th>Allowance</th>                        
                                            <th>Gross Pay</th>
                                            <th>Basic Pay</th>   
                                            <th>Deductions</th>
                                            <th>Taxable Income</th> 
                                            <th>Tax</th> 
                                            <th>Net Pay</th>
                                            <th>Reg Hol</th>
                                            <th>Reg Hol Restday</th>
                                            <th>Restday</th>
                                            <th>Spc Hol</th>
                                            <th>Spc Hol Restday</th>
                                            <th>Reg OT DS</th>
                                            <th>Reg OT NS</th>
                                            <th>Reg OT</th>
                                            <th>Hol OT</th>
                                            <th>Hol RD OT</th>
                                            <th>RD OT</th>
                                            <th>Spc OT</th>
                                            <th>Spc RD OT</th>
                                            <th>ND Reg</th>
                                            <th>ND Reg OT</th>
                                            <th>ND Reg Hol</th>
                                            <th>ND Reg Hol OT</th>
                                            <th>ND Reg Hol RD</th>
                                            <th>ND Reg Hol RD OT</th>
                                            <th>ND RD</th>
                                            <th>ND RD OT</th>
                                            <th>ND Spc</th>
                                            <th>ND Spc OT</th>
                                            <th>ND Spc RD</th>
                                            <th>ND Spc RD OT</th>
                                            <th>ND Dbl Hol</th>
                                            <th>ND Dbl Hol OT</th>
                                            <th>ND Dbl Hol RD</th>
                                            <th>ND Dbl Hol RD OT</th>
                                            <th>SL</th>
                                            <th>VL</th>
                                            <th>EL</th>
                                            <th>Incentive</th>
                                            <th>13th Month</th>
                                            <th>Bonus</th>
                                            <th>SIL</th>
                                            <th>Debit</th>
                                            <th>Company Bond</th>
                                            <th>Credit</th>
                                            <th>Other Deduction</th>
                                            <th>Medical</th>
                                            <th>Loan</th>
                                            <th>SSS Loan</th>
                                            <th>HDMF Loan</th>
                                            <th>Cash Advance</th>
                                            <th>Tardiness</th>
                                            <th>Undertime</th>
                                            <th>Philhealth Employer Share</th>
                                            <th>Philhealth Employee Share</th>
                                            <th>SSS Employer Share</th>
                                            <th>SSS Employee Share</th>
                                            <th>Pagibig Employer Share</th>
                                            <th>Pagibig Employee Share</th>
                                        </tr>
                                    </tfoot>
                                    <tbody>
                                        <?php 
                                          if (isset($_POST["company"])):

                                            $client = $_POST["company"];
                                            $start_date = $_POST["startDate"];
                                            $end_date = $_POST["endDate"];
                                            $key = $_POST["payPeriod"];
                                            $cutoff = $_POST["cutoff"];

                                            $parts= explode('-', $_POST["startDate"]);
                                            $month=$parts[1];
                                            
                                            if (!empty($_POST["company"]))

                                            if ($cutoff ==2){
                                                $query ="SELECT 
                                                    payrolladjustment.id AS id, 
                                                    payrolladjustment.empno AS empno, 
                                                    payrolladjustment.payperiod AS payperiod,
                                                    payrolladjustment.cutoff AS cutoff, 
                                                    payrolladjustment.month AS month,   

                                                    payrolladjustment.REGHOL AS REGHOL,   
                                                    payrolladjustment.REGHOLRESTDAY AS REGHOLRESTDAY,
                                                    payrolladjustment.RESTDAY AS RESTDAY,    
                                                    payrolladjustment.SPCHOL AS SPCHOL,   
                                                    payrolladjustment.SPCHOLRESTDAY AS SPCHOLRESTDAY, 

                                                    payrolladjustment.regot AS regot, 
                                                    payrolladjustment.regotds AS regotds, 
                                                    payrolladjustment.regotns AS regotns, 
                                                    payrolladjustment.holot AS holot, 
                                                    payrolladjustment.holrdot AS holrdot, 
                                                    payrolladjustment.rdot AS rdot,   
                                                    payrolladjustment.spcot AS spcot, 
                                                    payrolladjustment.spcrdot AS spcrdot, 

                                                    payrolladjustment.ndreg AS ndreg, 
                                                    payrolladjustment.ndregot AS ndregot, 
                                                    payrolladjustment.ndreghol AS ndreghol,   
                                                    payrolladjustment.ndregholot AS ndregholot,   
                                                    payrolladjustment.ndregholrd AS ndregholrd,   
                                                    payrolladjustment.ndregholrdot AS ndregholrdot,   
                                                    payrolladjustment.ndrd AS ndrd,   
                                                    payrolladjustment.ndrdot AS ndrdot,  
                                                    payrolladjustment.ndspc AS ndspc, 
                                                    payrolladjustment.ndspcot AS ndspcot, 
                                                    payrolladjustment.ndspcrd AS ndspcrd, 
                                                    payrolladjustment.ndspcrdot AS ndspcrdot, 
                                                    payrolladjustment.nddblhol AS nddblhol,   
                                                    payrolladjustment.nddblholot AS nddblholot,   
                                                    payrolladjustment.nddblholrd AS nddblholrd,   
                                                    payrolladjustment.nddblholrdot AS nddblholrdot,   
                                                    
                                                    payrolladjustment.SL AS SL,   
                                                    payrolladjustment.VL AS VL,   
                                                    payrolladjustment.EL AS EL,  
                                                    payrolladjustment.ADDITIONALALLOWANCE AS ADDITIONALALLOWANCE, 
                                                    payrolladjustment.INCENTIVE AS INCENTIVE, 
                                                    payrolladjustment.XIIIMONTH AS XIIIMONTH, 
                                                    payrolladjustment.BONUS AS BONUS,
                                                    payrolladjustment.SIL AS SIL, 
                                                    payrolladjustment.OTHERDEDUCTION AS OTHERDEDUCTION,   
                                                    payrolladjustment.MEDICAL AS MEDICAL, 
                                                    payrolladjustment.LOAN AS LOAN,
                                                    payrolladjustment.SSSLOAN AS SSSLOAN,
                                                    payrolladjustment.HDMFLOAN AS HDMFLOAN,   
                                                    payrolladjustment.CASHADVANCE AS CASHADVANCE,
                                                    payrolladjustment.TARDINESS AS TARDINESS, 
                                                    payrolladjustment.UNDERTIME AS UNDERTIME,
                                                    payrolladjustment.ABSENCES AS ABSENCES,
                                                    payrolladjustment.paystatus AS paystatus,
                                                    payrolladjustment.DEBIT AS DEBIT,
                                                    payrolladjustment.CREDIT AS CREDIT,
                                                    payrolladjustment.COMPANYBOND AS COMPANYBOND,

                                                    /*manpower employee table*/
                                                    fullempdetailsandbenefits.perid as personid,
                                                    fullempdetailsandbenefits.perempno as empno,
                                                    fullempdetailsandbenefits.perfirstName as firstname,
                                                    fullempdetailsandbenefits.permiddleName as middlename,
                                                    fullempdetailsandbenefits.perlastName as lastname,
                                                    /*fullempdetailsandbenefits.pergender as gender,
                                                    fullempdetailsandbenefits.peraddress as address,
                                                    fullempdetailsandbenefits.perdob as dob,
                                                    fullempdetailsandbenefits.perage as age,
                                                    fullempdetailsandbenefits.percivil as civil,
                                                    fullempdetailsandbenefits.percontact as concact,
                                                    fullempdetailsandbenefits.perhired as hired,
                                                    fullempdetailsandbenefits.pereducation as education,
                                                    fullempdetailsandbenefits.percompany as company,
                                                    fullempdetailsandbenefits.perclient as client,
                                                    fullempdetailsandbenefits.perposition as position,
                                                    fullempdetailsandbenefits.perdepartment as department,
                                                    fullempdetailsandbenefits.perdateassigned as dateassigned,
                                                    fullempdetailsandbenefits.perempclass as empclass,
                                                    fullempdetailsandbenefits.pertinid as tinid,
                                                    fullempdetailsandbenefits.persssid as sssid,
                                                    fullempdetailsandbenefits.perphilhealthid as philhealthid,
                                                    fullempdetailsandbenefits.perpagibigid as pagibigid,
                                                    fullempdetailsandbenefits.allowance AS allowance,*/
                                                    fullempdetailsandbenefits.perdailysalary as dailysalary,
                                                    fullempdetailsandbenefits.permonthlysalary as monthlysalary,
                                                    fullempdetailsandbenefits.perempstatus as empstatus,
                                                    fullempdetailsandbenefits.baguiodaily as baguiodaily,
                                                    fullempdetailsandbenefits.tax_exemption as tax_exemption,
                                                    fullempdetailsandbenefits.monthly_rate as monthly_rate,

                                                    fullempdetailsandbenefits.forph as forph,
                                                    fullempdetailsandbenefits.perphilhealthbracket as philhealthbracket,
                                                    fullempdetailsandbenefits.philhealthemployershare AS philhealthemployershare, 
                                                    fullempdetailsandbenefits.philhealthemployeeshare AS philhealthemployeeshare,

                                                    fullempdetailsandbenefits.sssbracket AS sssbracket,
                                                    fullempdetailsandbenefits.sssemployershare AS sssemployershare,
                                                    fullempdetailsandbenefits.sssemployeeshare AS sssemployeeshare,

                                                    fullempdetailsandbenefits.hdmfbracket AS hdmfbracket,
                                                    fullempdetailsandbenefits.hdmfemployershare AS hdmfemployershare,
                                                    fullempdetailsandbenefits.hdmfemployeeshare AS hdmfemployeeshare,

                                                    processed_payroll.totalnightdifferential as prevtotalnightdifferential,
                                                    processed_payroll.totalregularOT as prevtotalregularOT,
                                                    processed_payroll.totalholidayOT as prevtotalholidayOT,
                                                    processed_payroll.totalholidayrestdaypay as prevtotalholidayrestdaypay,
                                                    processed_payroll.totalBasicpay as prevtotalBasicpay

                                                    FROM payrolladjustment 

                                                    inner join fullempdetailsandbenefits ON fullempdetailsandbenefits.perempno = payrolladjustment.empno
                                                    inner join processed_payroll ON processed_payroll.empno = payrolladjustment.empno

                                                    WHERE payrolladjustment.payperiod = '$key' and fullempdetailsandbenefits.perclient='$client'  and payrolladjustment.paystatus != 'Processed' and processed_payroll.month='$month' and processed_payroll.year=''";
                                                }
                                            else if ($cutoff == 1) {
                                                $query ="SELECT 
                                                    payrolladjustment.id AS id, 
                                                    payrolladjustment.empno AS empno, 
                                                    payrolladjustment.payperiod AS payperiod,
                                                    payrolladjustment.cutoff AS cutoff, 
                                                    payrolladjustment.month AS month,   

                                                    payrolladjustment.REGHOL AS REGHOL,   
                                                    payrolladjustment.REGHOLRESTDAY AS REGHOLRESTDAY,
                                                    payrolladjustment.RESTDAY AS RESTDAY,    
                                                    payrolladjustment.SPCHOL AS SPCHOL,   
                                                    payrolladjustment.SPCHOLRESTDAY AS SPCHOLRESTDAY, 

                                                    payrolladjustment.regot AS regot, 
                                                    payrolladjustment.regotds AS regotds, 
                                                    payrolladjustment.regotns AS regotns, 
                                                    payrolladjustment.holot AS holot, 
                                                    payrolladjustment.holrdot AS holrdot, 
                                                    payrolladjustment.rdot AS rdot,   
                                                    payrolladjustment.spcot AS spcot, 
                                                    payrolladjustment.spcrdot AS spcrdot, 

                                                    payrolladjustment.ndreg AS ndreg, 
                                                    payrolladjustment.ndregot AS ndregot, 
                                                    payrolladjustment.ndreghol AS ndreghol,   
                                                    payrolladjustment.ndregholot AS ndregholot,   
                                                    payrolladjustment.ndregholrd AS ndregholrd,   
                                                    payrolladjustment.ndregholrdot AS ndregholrdot,   
                                                    payrolladjustment.ndrd AS ndrd,   
                                                    payrolladjustment.ndrdot AS ndrdot,  
                                                    payrolladjustment.ndspc AS ndspc, 
                                                    payrolladjustment.ndspcot AS ndspcot, 
                                                    payrolladjustment.ndspcrd AS ndspcrd, 
                                                    payrolladjustment.ndspcrdot AS ndspcrdot, 
                                                    payrolladjustment.nddblhol AS nddblhol,   
                                                    payrolladjustment.nddblholot AS nddblholot,   
                                                    payrolladjustment.nddblholrd AS nddblholrd,   
                                                    payrolladjustment.nddblholrdot AS nddblholrdot,   
                                                    
                                                    payrolladjustment.SL AS SL,   
                                                    payrolladjustment.VL AS VL,   
                                                    payrolladjustment.EL AS EL,  
                                                    payrolladjustment.ADDITIONALALLOWANCE AS ADDITIONALALLOWANCE, 
                                                    payrolladjustment.INCENTIVE AS INCENTIVE, 
                                                    payrolladjustment.XIIIMONTH AS XIIIMONTH, 
                                                    payrolladjustment.BONUS AS BONUS,
                                                    payrolladjustment.SIL AS SIL, 
                                                    payrolladjustment.OTHERDEDUCTION AS OTHERDEDUCTION,   
                                                    payrolladjustment.MEDICAL AS MEDICAL, 
                                                    payrolladjustment.LOAN AS LOAN,
                                                    payrolladjustment.SSSLOAN AS SSSLOAN,
                                                    payrolladjustment.HDMFLOAN AS HDMFLOAN,   
                                                    payrolladjustment.CASHADVANCE AS CASHADVANCE,
                                                    payrolladjustment.TARDINESS AS TARDINESS, 
                                                    payrolladjustment.UNDERTIME AS UNDERTIME,
                                                    payrolladjustment.ABSENCES AS ABSENCES,
                                                    payrolladjustment.paystatus AS paystatus,
                                                    payrolladjustment.DEBIT AS DEBIT,
                                                    payrolladjustment.CREDIT AS CREDIT,
                                                    payrolladjustment.COMPANYBOND AS COMPANYBOND,

                                                    /*manpower employee table*/
                                                    fullempdetailsandbenefits.perid as personid,
                                                    fullempdetailsandbenefits.perempno as empno,
                                                    fullempdetailsandbenefits.perfirstName as firstname,
                                                    fullempdetailsandbenefits.permiddleName as middlename,
                                                    fullempdetailsandbenefits.perlastName as lastname,
                                                    /*fullempdetailsandbenefits.pergender as gender,
                                                    fullempdetailsandbenefits.peraddress as address,
                                                    fullempdetailsandbenefits.perdob as dob,
                                                    fullempdetailsandbenefits.perage as age,
                                                    fullempdetailsandbenefits.percivil as civil,
                                                    fullempdetailsandbenefits.percontact as concact,
                                                    fullempdetailsandbenefits.perhired as hired,
                                                    fullempdetailsandbenefits.pereducation as education,
                                                    fullempdetailsandbenefits.percompany as company,
                                                    fullempdetailsandbenefits.perclient as client,
                                                    fullempdetailsandbenefits.perposition as position,
                                                    fullempdetailsandbenefits.perdepartment as department,
                                                    fullempdetailsandbenefits.perdateassigned as dateassigned,
                                                    fullempdetailsandbenefits.perempclass as empclass,
                                                    fullempdetailsandbenefits.pertinid as tinid,
                                                    fullempdetailsandbenefits.persssid as sssid,
                                                    fullempdetailsandbenefits.perphilhealthid as philhealthid,
                                                    fullempdetailsandbenefits.perpagibigid as pagibigid,
                                                    fullempdetailsandbenefits.allowance AS allowance,*/
                                                    fullempdetailsandbenefits.perdailysalary as dailysalary,
                                                    fullempdetailsandbenefits.permonthlysalary as monthlysalary,
                                                    fullempdetailsandbenefits.perempstatus as empstatus,
                                                    fullempdetailsandbenefits.baguiodaily as baguiodaily,
                                                    fullempdetailsandbenefits.tax_exemption as tax_exemption,

                                                    fullempdetailsandbenefits.forph as forph,
                                                    fullempdetailsandbenefits.perphilhealthbracket as philhealthbracket,
                                                    fullempdetailsandbenefits.philhealthemployershare AS philhealthemployershare, 
                                                    fullempdetailsandbenefits.philhealthemployeeshare AS philhealthemployeeshare,

                                                    fullempdetailsandbenefits.sssbracket AS sssbracket,
                                                    fullempdetailsandbenefits.sssemployershare AS sssemployershare,
                                                    fullempdetailsandbenefits.sssemployeeshare AS sssemployeeshare,

                                                    fullempdetailsandbenefits.hdmfbracket AS hdmfbracket,
                                                    fullempdetailsandbenefits.hdmfemployershare AS hdmfemployershare,
                                                    fullempdetailsandbenefits.hdmfemployeeshare AS hdmfemployeeshare,
                                                    fullempdetailsandbenefits.tax_exemption AS tax_exemption,
                                                     fullempdetailsandbenefits.monthly_rate AS monthly_rate

                                                    FROM payrolladjustment 

                                                    inner join fullempdetailsandbenefits ON fullempdetailsandbenefits.perempno = payrolladjustment.empno
                                                  
                                                    WHERE payrolladjustment.payperiod = '$key' and fullempdetailsandbenefits.perclient='$client'  and payrolladjustment.paystatus != 'Processed' and payrolladjustment.month='$month' and payrolladjustment.year='' ";

                                            }else if ($cut == 3) {
                                                $query ="SELECT 
                                                    payrolladjustment.id AS id, 
                                                    payrolladjustment.empno AS empno, 
                                                    payrolladjustment.payperiod AS payperiod,
                                                    payrolladjustment.cutoff AS cutoff, 
                                                    payrolladjustment.month AS month,   

                                                    payrolladjustment.REGHOL AS REGHOL,   
                                                    payrolladjustment.REGHOLRESTDAY AS REGHOLRESTDAY,
                                                    payrolladjustment.RESTDAY AS RESTDAY,    
                                                    payrolladjustment.SPCHOL AS SPCHOL,   
                                                    payrolladjustment.SPCHOLRESTDAY AS SPCHOLRESTDAY, 

                                                    payrolladjustment.regot AS regot, 
                                                    payrolladjustment.regotds AS regotds, 
                                                    payrolladjustment.regotns AS regotns, 
                                                    payrolladjustment.holot AS holot, 
                                                    payrolladjustment.holrdot AS holrdot, 
                                                    payrolladjustment.rdot AS rdot,   
                                                    payrolladjustment.spcot AS spcot, 
                                                    payrolladjustment.spcrdot AS spcrdot, 

                                                    payrolladjustment.ndreg AS ndreg, 
                                                    payrolladjustment.ndregot AS ndregot, 
                                                    payrolladjustment.ndreghol AS ndreghol,   
                                                    payrolladjustment.ndregholot AS ndregholot,   
                                                    payrolladjustment.ndregholrd AS ndregholrd,   
                                                    payrolladjustment.ndregholrdot AS ndregholrdot,   
                                                    payrolladjustment.ndrd AS ndrd,   
                                                    payrolladjustment.ndrdot AS ndrdot,  
                                                    payrolladjustment.ndspc AS ndspc, 
                                                    payrolladjustment.ndspcot AS ndspcot, 
                                                    payrolladjustment.ndspcrd AS ndspcrd, 
                                                    payrolladjustment.ndspcrdot AS ndspcrdot, 
                                                    payrolladjustment.nddblhol AS nddblhol,   
                                                    payrolladjustment.nddblholot AS nddblholot,   
                                                    payrolladjustment.nddblholrd AS nddblholrd,   
                                                    payrolladjustment.nddblholrdot AS nddblholrdot,   
                                                    
                                                    payrolladjustment.SL AS SL,   
                                                    payrolladjustment.VL AS VL,   
                                                    payrolladjustment.EL AS EL,  
                                                    payrolladjustment.ADDITIONALALLOWANCE AS ADDITIONALALLOWANCE, 
                                                    payrolladjustment.INCENTIVE AS INCENTIVE, 
                                                    payrolladjustment.XIIIMONTH AS XIIIMONTH, 
                                                    payrolladjustment.BONUS AS BONUS,
                                                    payrolladjustment.SIL AS SIL, 
                                                    payrolladjustment.OTHERDEDUCTION AS OTHERDEDUCTION,   
                                                    payrolladjustment.MEDICAL AS MEDICAL, 
                                                    payrolladjustment.LOAN AS LOAN,
                                                    payrolladjustment.SSSLOAN AS SSSLOAN,
                                                    payrolladjustment.HDMFLOAN AS HDMFLOAN,   
                                                    payrolladjustment.CASHADVANCE AS CASHADVANCE,
                                                    payrolladjustment.TARDINESS AS TARDINESS, 
                                                    payrolladjustment.UNDERTIME AS UNDERTIME,
                                                    payrolladjustment.ABSENCES AS ABSENCES,
                                                    payrolladjustment.paystatus AS paystatus,
                                                    payrolladjustment.DEBIT AS DEBIT,
                                                    payrolladjustment.CREDIT AS CREDIT,
                                                    payrolladjustment.COMPANYBOND AS COMPANYBOND,

                                                        /*manpower employee table*/
                                                    fullempdetailsandbenefits.perid as personid,
                                                    fullempdetailsandbenefits.perempno as empno,
                                                    fullempdetailsandbenefits.perfirstName as firstname,
                                                    fullempdetailsandbenefits.permiddleName as middlename,
                                                    fullempdetailsandbenefits.perlastName as lastname,
                                                    /*fullempdetailsandbenefits.pergender as gender,
                                                    fullempdetailsandbenefits.peraddress as address,
                                                    fullempdetailsandbenefits.perdob as dob,
                                                    fullempdetailsandbenefits.perage as age,
                                                    fullempdetailsandbenefits.percivil as civil,
                                                    fullempdetailsandbenefits.percontact as concact,
                                                    fullempdetailsandbenefits.perhired as hired,
                                                    fullempdetailsandbenefits.pereducation as education,
                                                    fullempdetailsandbenefits.percompany as company,
                                                    fullempdetailsandbenefits.perclient as client,
                                                    fullempdetailsandbenefits.perposition as position,
                                                    fullempdetailsandbenefits.perdepartment as department,
                                                    fullempdetailsandbenefits.perdateassigned as dateassigned,
                                                    fullempdetailsandbenefits.perempclass as empclass,
                                                    fullempdetailsandbenefits.pertinid as tinid,
                                                    fullempdetailsandbenefits.persssid as sssid,
                                                    fullempdetailsandbenefits.perphilhealthid as philhealthid,
                                                    fullempdetailsandbenefits.perpagibigid as pagibigid,
                                                    fullempdetailsandbenefits.allowance AS allowance,*/
                                                    fullempdetailsandbenefits.perdailysalary as dailysalary,
                                                    fullempdetailsandbenefits.permonthlysalary as monthlysalary,
                                                    fullempdetailsandbenefits.perempstatus as empstatus,
                                                    fullempdetailsandbenefits.baguiodaily as baguiodaily,
                                                    fullempdetailsandbenefits.tax_exemption as tax_exemption,

                                                    fullempdetailsandbenefits.forph as forph,
                                                    fullempdetailsandbenefits.perphilhealthbracket as philhealthbracket,
                                                    fullempdetailsandbenefits.philhealthemployershare AS philhealthemployershare, 
                                                    fullempdetailsandbenefits.philhealthemployeeshare AS philhealthemployeeshare,

                                                    fullempdetailsandbenefits.sssbracket AS sssbracket,
                                                    fullempdetailsandbenefits.sssemployershare AS sssemployershare,
                                                    fullempdetailsandbenefits.sssemployeeshare AS sssemployeeshare,


                                                    fullempdetailsandbenefits.hdmfbracket AS hdmfbracket,
                                                    fullempdetailsandbenefits.hdmfemployershare AS hdmfemployershare,
                                                    fullempdetailsandbenefits.hdmfemployeeshare AS hdmfemployeeshare,
                                                    fullempdetailsandbenefits.tax_exemption AS tax_exemption,
                                                     fullempdetailsandbenefits.monthly_rate AS monthly_rate

                                                    FROM payrolladjustment 

                                                    inner join fullempdetailsandbenefits ON fullempdetailsandbenefits.perempno = payrolladjustment.empno

                                                    WHERE payrolladjustment.payperiod = '$key' and fullempdetailsandbenefits.perclient='$client'  and payrolladjustment.paystatus != 'Processed' and payrolladjustment.month='$month' and payrolladjustment.year='' ";

                                                }
                                            else
                                                $query = "SELECT * FROM payrolladjustment.payperiod inner join fullempdetailsandbenefits ON fullempdetailsandbenefits.empno = payrolladjustment.empno";


    include 'dbconnection.php'; 
    $result = mysql_query($query,$link) or die (mysql_error());
    while ($row = mysql_fetch_array($result,MYSQL_ASSOC)) :

    $startdate = $_POST['startdate'];
    $enddate = $_POST['enddate'];

    $start = new DateTime($startdate);
    $end = new DateTime($enddate);
    // otherwise the  end date is excluded (bug?)
    $end->modify('+1 day');
    $interval = $end->diff($start);

    // total days
    $days = $interval->days;
    // create an iterateable period of date (P1D equates to 1 day)
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);
    // best stored as array, so you can add more than one
    $holidays = array('2012-09-07');

        foreach($period as $dt) {
            $curr = $dt->format('D');
            // substract if Saturday or Sunday
            if ($curr == 'Sun') {
                $days--;
            }
            // (optional) for the updated question
            elseif (in_array($dt->format('Y-m-d'), $holidays)) {
                $days--;
            }
        }

    $cutoff = $row['cutoff'];
  
    $rateperhr = $row['dailysalary']/8;
    $baguiorateperhr = $row['baguiodaily']/8;

        if($cutoff  ==3){
            $totaldays = 0;
        }else{
            $totaldays = $days - $row['ABSENCES'];
        }

    $totalabsence = $row['ABSENCES'] * $row['dailysalary'];

    // $monthlybasicsalary = $row['dailysalary'] * 26;
    $monthlybasicsalary = ($row['dailysalary'] * 313)/12;

        if ($row['monthly_rate'] !=0){
            $basicpay = ($row['monthly_rate']/2) - $totalabsence;
        }else{
            $basicpay = ($days * $row['dailysalary']) - $totalabsence;
        }

        if($cutoff  ==3){
            $phemployeeshare = 0;
            $phemployershare = 0;
        }else{
            
            if($row['philhealthbracket']!='PHNA'){
                
                if($monthlybasicsalary<=10000){
                    $phemployeeshare = 200;
                    $phemployershare = 200;
                }else if (($monthlybasicsalary>= 10000.01) && ($monthlybasicsalary<=79999.99)){
                    $phemployeeshare = ($monthlybasicsalary * .05)/2;
                    $phemployershare = ($monthlybasicsalary * .05)/2;
                }else{
                    $phemployeeshare = 1600;
                    $phemployershare = 1600;
                }
            }
            else{
                $phemployeeshare = 0;
                $phemployershare = 0;
            }
        }

    $totalallowance = $row['ADDITIONALALLOWANCE'];

        if ($cutoff == 2){
            $FirstGross= $row['prevtotalBasicpay'] + $row['prevtotalregularOT'] + $row['prevtotalholidayOT'] + $row['prevtotalnightdifferential'] + $row['prevtotalholidayrestdaypay'];
        }else {
        $FirstGross=0;
        }


    //dsregot
    $regotdspay= ($rateperhr*1.25) * $row['regotds'];
    $regotnspay= ($rateperhr*1.25) * $row['regotns'];
    $regotpay = $regotdspay + $regotnspay;
    //dsregot

    //dsholot
    $holotpay = ($rateperhr *2.6) * $row['holot'];
    //dsholot

    //dsholrdot
    $holrdotpay = ($rateperhr*3.38) * $row['holrdot'];
    //dsholrdot

    //dsrdot
    $rdotpay = ($rateperhr *1.69) * $row['rdot'];
    //dsrdot

    //dsspcot
    $spcotpay = ($rateperhr *1.69) * $row['spcot'];
    //dsspcot

    //dsspcrdot 
    $spcrdotpay = ($rateperhr *1.95) * $row['spcrdot'];
    //dsspcrdot

    //ndreg
    $ndregpay = ($rateperhr* .10) * $row['ndreg'];
    $ndregotpay = ($rateperhr* .125) * $row['ndregot'];
    //ndreg

    //ndregrd
    $ndrdpay = ($rateperhr * .13) * $row['ndrd'];
    $ndrdotpay = ($rateperhr * .169) * $row['ndrdot'];
    //ndregrd

    //ndspc
    $ndspcpay = ($rateperhr * .13) * $row['ndspc'];
    $ndspcotpay = ($rateperhr * .169) * $row['ndspcot'];          
    //ndspc

    //ndspcrd
    $ndspcrdpay = ($rateperhr* .15) * $row['ndspcrd'];
    $ndspcrdotpay = ($rateperhr* .195) * $row['ndspcrdot'];
    //ndspcrd

    //ndreghol
    $ndregholpay = ($rateperhr* .20) * $row['ndreghol'];
    $ndregholotpay = ($rateperhr* .26) * $row['ndregholot'];
    //ndreghol

    //ndregholrd
    $ndregholrdpay = ($rateperhr* .26) * $row['ndregholrd'];
    $ndregholrdotpay = ($rateperhr* .338) * $row['ndregholrdot'];
    //ndregholrd 

    //nddblhol
    $nddblholpay = ($rateperhr* .30) * $row['nddblhol'];
    $nddblholotpay = ($rateperhr* .39) * $row['nddblholot'];
    //nddblhol

    //nddblholrd
    $nddblholrdpay = ($rateperhr* .39) * $row['nddblholrd'];
    $nddblholrdotpay = ($rateperhr* .507) * $row['nddblholrdot'];
    //nddblholrd

    $REGHOLpay = ($rateperhr * 1) * $row['REGHOL'];
    $REGHOLRESTDAYpay = ($rateperhr * 1.6) * $row['REGHOLRESTDAY'];
    $RESTDAYpay = ($rateperhr * 1.30) * $row['RESTDAY'];
    $SPCHOLpay = ($rateperhr * .30) * $row['SPCHOL'];
    $SPCHOLRESTDAYpay = ($rateperhr * .5) * $row['SPCHOLRESTDAY'];

    $TARDINESSpay = $row['TARDINESS']*$rateperhr;
    $UNDERTIMEpay = $row['UNDERTIME'] *$rateperhr;

    $ADDITIONALALLOWANCEpay = $row['ADDITIONALALLOWANCE'];

    $INCENTIVEpay = $row['INCENTIVE'];
    $XIIIMONTHpay = $row['XIIIMONTH'];
    $BONUSpay = $row['BONUS'];
    $SILpay = $row['SIL'];
    $DEBITpay = $row['DEBIT'];
    $CREDITpay = $row['CREDIT'];

    $OTHERDEDUCTIONpay = $row['OTHERDEDUCTION'];
    $MEDICALpay = $row['MEDICAL'];
    $LOANpay = $row['LOAN'];
    $SSSLOANpay = $row['SSSLOAN'];
    $HDMFLOANpay = $row['HDMFLOAN'];
    $CASHADVANCEpay = $row['CASHADVANCE'];

    $SLpay =  $row['SL'] * $rateperhr;
    $VLpay =  $row['VL'] * $rateperhr;
    $ELpay =  $row['EL'] * $rateperhr;

        if($cutoff==3){
            $totalBasicpay = 0; 
        }else{  
            $totalBasicpay = $basicpay; 
        }


    $totalregularOT = $regotpay;

    $totalholidayOT = $holotpay + $holrdotpay + $rdotpay + $spcotpay + $spcrdotpay;

    $totalnightdifferential =   $ndregpay + 
                                $ndregotpay + 

                                $ndrdpay +
                                $ndrdotpay + 

                                $ndspcpay + 
                                $ndspcotpay +

                                $ndspcrdpay + 
                                $ndspcrdotpay + 

                                $ndregholpay + 
                                $ndregholotpay +

                                $ndregholrdpay +  
                                $ndregholrdotpay + 

                                $nddblholpay + 
                                $nddblholotpay + 

                                $nddblholrdpay + 
                                $nddblholrdotpay;
     
    $totalholidayrestdaypay =   $REGHOLpay +
                                $REGHOLRESTDAYpay +
                                $RESTDAYpay +
                                $SPCHOLpay +
                                $SPCHOLRESTDAYpay;

    $totaladditionaladjustment =    $XIIIMONTHpay +
                                    $INCENTIVEpay +
                                    $BONUSpay + 
                                    $SILpay +$DEBITpay  + $row['ADDITIONALALLOWANCE']+ $row['COMPANYBOND'];


    $gross = $totalBasicpay + $totalregularOT + $totalholidayOT  + $totalnightdifferential + $totalholidayrestdaypay; 

    //Government Dues
    $combinedGROSS =  $FirstGross + ($totalBasicpay + $totalregularOT + $totalholidayOT  + $totalnightdifferential + $totalholidayrestdaypay);

    $sssemployeeshare = $row['sssemployeeshare'];
    $sssemployershare = $row['sssemployershare'];

        if ($row['sssbracket'] == "S0"){

            if (($combinedGROSS >= 1000) && ($combinedGROSS <= 2249.99)){

                $sssemployeeshare = 80;
                $sssemployershare = 160 + 10;
            }
            if (($combinedGROSS >= 2250) && ($combinedGROSS <= 2749.99)){

                    $sssemployeeshare = 100;
                    $sssemployershare = 200 + 10;
                }
            if (($combinedGROSS >= 2750) && ($combinedGROSS <= 3249.99)){

                    $sssemployeeshare = 120;
                    $sssemployershare = 240 + 10;
                }
            if (($combinedGROSS >= 3250) && ($combinedGROSS <= 3749.99)){

                    $sssemployeeshare = 140;
                    $sssemployershare = 280 + 10;
                }
            if (($combinedGROSS >= 3750) && ($combinedGROSS <= 4249.99)){

                    $sssemployeeshare =160;
                    $sssemployershare = 320 + 10;

                }
            if (($combinedGROSS >= 4250) && ($combinedGROSS <= 4749.99)){

                   $sssemployeeshare =180;
                    $sssemployershare = 360 + 10;
                }
            if (($combinedGROSS >= 4750) && ($combinedGROSS <= 5249.99)){

                    $sssemployeeshare =200;
                    $sssemployershare = 400 + 10;
                }
            if (($combinedGROSS >= 5250) && ($combinedGROSS <= 5749.99)){

                   $sssemployeeshare =220;
                    $sssemployershare = 440 + 10;
                }
            if (($combinedGROSS >= 5750) && ($combinedGROSS <= 6249.99)){

                    $sssemployeeshare =240;
                    $sssemployershare = 480 + 10;
                }
            if (($combinedGROSS >= 6250) && ($combinedGROSS <= 6749.99)){

                    $sssemployeeshare =260;
                    $sssemployershare = 520 + 10;
                }
            if (($combinedGROSS >= 6750) && ($combinedGROSS <= 7249.99)){

                    $sssemployeeshare =280;
                    $sssemployershare = 560 + 10;
                }
            if (($combinedGROSS >= 7250) && ($combinedGROSS <= 7749.99)){

                    $sssemployeeshare =300;
                    $sssemployershare = 600 + 10;
                }
            if (($combinedGROSS >= 7750) && ($combinedGROSS <= 8249.99)){

                    $sssemployeeshare =320;
                    $sssemployershare = 640 + 10;
                }
            if (($combinedGROSS >= 8250) && ($combinedGROSS <= 8749.99)){

                    $sssemployeeshare =340;
                    $sssemployershare = 680 + 10;
                }
            if (($combinedGROSS >= 8750) && ($combinedGROSS <= 9249.99)){

                    $sssemployeeshare =360;
                    $sssemployershare = 720 + 10;
                }
            if (($combinedGROSS >= 9250) && ($combinedGROSS <= 9749.99)){

                    $sssemployeeshare =380;
                    $sssemployershare = 760 + 10;
                }
            if (($combinedGROSS >= 9750) && ($combinedGROSS <= 10249.99)){

                    $sssemployeeshare =400;
                    $sssemployershare = 800 + 10;
                }
            if (($combinedGROSS >= 10250) && ($combinedGROSS <= 10749.99)){

                    $sssemployeeshare =420;
                    $sssemployershare = 840 + 10;
                }
            if (($combinedGROSS >= 10750) && ($combinedGROSS <= 11249.99)){

                    $sssemployeeshare =440;
                    $sssemployershare = 880 + 10;
                }
            if (($combinedGROSS >= 11250) && ($combinedGROSS <= 11749.99)){

                    $sssemployeeshare =460;
                    $sssemployershare = 920 + 10;
                }
            if (($combinedGROSS >= 11750) && ($combinedGROSS <= 12249.99)){

                    $sssemployeeshare =480;
                    $sssemployershare = 960 + 10;

                }
            if (($combinedGROSS >= 12250) && ($combinedGROSS <= 12749.99)){

                    $sssemployeeshare =500;
                    $sssemployershare = 1000 + 10;
                }
            if (($combinedGROSS >= 12750) && ($combinedGROSS <= 13249.99)){

                    $sssemployeeshare =520;
                    $sssemployershare = 1040 + 10;
                }
            if (($combinedGROSS >= 13250) && ($combinedGROSS <= 13749.99)){

                    $sssemployeeshare =540;
                    $sssemployershare = 1080 + 10;
                }
            if (($combinedGROSS >= 13750) && ($combinedGROSS <= 14249.99)){

                    $sssemployeeshare =560;
                    $sssemployershare = 1120 + 10;
                }
            if (($combinedGROSS >= 14250) && ($combinedGROSS <= 14749.99)){

                    $sssemployeeshare =580;
                    $sssemployershare = 1160 + 10;
                }
            if (($combinedGROSS >= 14750) && ($combinedGROSS <= 15249.99)){

                    $sssemployeeshare =600;
                    $sssemployershare = 1200 + 30;
                }
            if (($combinedGROSS >= 15250) && ($combinedGROSS <= 15749.99)){

                    $sssemployeeshare =620;
                    $sssemployershare = 1240 + 30;
                }
            if (($combinedGROSS >= 15750) && ($combinedGROSS <= 16249.99)){

                    $sssemployeeshare =640;
                    $sssemployershare = 1280 + 30;
                }
            if (($combinedGROSS >= 16250) && ($combinedGROSS <= 16749.99)){

                    $sssemployeeshare =660;
                    $sssemployershare = 1320 + 30;

            }
            if (($combinedGROSS >= 16750) && ($combinedGROSS <= 17249.99)){

                $sssemployeeshare =680;
                $sssemployershare = 1360 + 30;
            }
            if (($combinedGROSS >= 17250) && ($combinedGROSS <= 17749.99)){

                $sssemployeeshare =700;
                $sssemployershare = 1400 + 30;
            }
            if (($combinedGROSS >= 17750) && ($combinedGROSS <= 18249.99)){

                $sssemployeeshare =720;
                $sssemployershare = 1440 + 30;
            }
            if (($combinedGROSS >= 18250) && ($combinedGROSS <= 18749.99)){

                $sssemployeeshare =740;
                $sssemployershare = 1480 + 30;
            }
            if (($combinedGROSS >= 18750) && ($combinedGROSS <= 19249.99)){

                $sssemployeeshare =760;
                $sssemployershare = 1520 + 30;
            }
            if (($combinedGROSS >= 19250) && ($combinedGROSS <= 19749.99)){

                $sssemployeeshare =780;
                $sssemployershare = 1560 + 30;
            }

            if ($combinedGROSS >= 19750){

                    $sssemployeeshare =800;
                    $sssemployershare = 1600 + 30;
            }
        }

        if($cutoff==2){

            $totalgovdue =  $sssemployeeshare;

        }else if ($cutoff==3){

            $totalgovdue = 0;
       
        }else{

            $totalgovdue = $row['hdmfemployeeshare'] + $phemployeeshare;
        } 

    $taxableincome = $gross - $TARDINESSpay - $UNDERTIMEpay - $SLpay -$VLpay - $ELpay - $totalgovdue;

    $totaldeductions =  $totalgovdue +
                        $OTHERDEDUCTIONpay +
                        $MEDICALpay +
                        $LOANpay +
                        $SSSLOANpay +
                        $HDMFLOANpay +
                        $CASHADVANCEpay +
                        $TARDINESSpay +
                        $UNDERTIMEpay +
                        $SLpay +
                        $VLpay +
                        $ELpay + $CREDITpay;


        /*New Tax effective january 1, 2018*/
        if(($taxableincome <= 10416.99)){

            $tax = 0;
            $netpay = $taxableincome - $CASHADVANCEpay - $HDMFLOANpay  - $SSSLOANpay - $LOANpay - $MEDICALpay - $OTHERDEDUCTIONpay-$tax + $totaladditionaladjustment;
        }
        if(($taxableincome >= 10417) && ($taxableincome <= 16666.99)){

            $tax = ($taxableincome-10417) * (.20) + 0;
            $netpay = $taxableincome - $CASHADVANCEpay - $HDMFLOANpay  - $SSSLOANpay - $LOANpay - $MEDICALpay - $OTHERDEDUCTIONpay-$tax + $totaladditionaladjustment;
        }
        if(($taxableincome >= 16667) && ($taxableincome <= 33332.99)){

            $tax = ($taxableincome-16667) * (.25) + 1250;
            $netpay = $taxableincome - $CASHADVANCEpay - $HDMFLOANpay  - $SSSLOANpay - $LOANpay - $MEDICALpay - $OTHERDEDUCTIONpay-$tax + $totaladditionaladjustment;
        }
        if(($taxableincome >= 33333) && ($taxableincome <= 83332.99)){

            $tax = ($taxableincome-33333) * (.30) + 5416.67;
            $netpay = $taxableincome - $CASHADVANCEpay - $HDMFLOANpay  - $SSSLOANpay - $LOANpay - $MEDICALpay - $OTHERDEDUCTIONpay-$tax + $totaladditionaladjustment;
        }
        if(($taxableincome >= 83333) && ($taxableincome <= 333332.99)){

            $tax = ($taxableincome-83333) * (.32) + 20416.67;
            $netpay = $taxableincome - $CASHADVANCEpay - $HDMFLOANpay  - $SSSLOANpay - $LOANpay - $MEDICALpay - $OTHERDEDUCTIONpay-$tax + $totaladditionaladjustment;
        }
        if(($taxableincome >= 333333)){

            $tax = ($taxableincome-333333) * (.35) + 100416.67;
            $netpay = $taxableincome - $CASHADVANCEpay - $HDMFLOANpay - $SSSLOANpay - $LOANpay - $MEDICALpay - $OTHERDEDUCTIONpay-$tax + $totaladditionaladjustment;
        }
        else{
            $tax =0;
            $netpay = $gross - $totaldeductions - $tax  + $totaladditionaladjustment;
        }

        ?>

        <tr>
            <td>
                <a href='processedpayrollsubmit.php?cutoff=<?php echo $row['cutoff'];?>&empno=<?php echo $row['empno'];?>&startdate=<?php echo $startdate;?>
                &enddate=<?php echo $enddate;?>
                &totaldays=<?php echo $totaldays;?>
                &payperiod=<?php echo $row['payperiod'];?>
                &month=<?php echo $row['month'];?>
                &totalnightdifferential=<?php echo $totalnightdifferential;?>
                &totalregularOT=<?php echo $totalregularOT;?>
                &totalholidayOT=<?php echo $totalholidayOT;?>
                &totalBasicpay=<?php echo $totalBasicpay;?>
                &totalholidayrestdaypay=<?php echo $totalholidayrestdaypay;?>
                &totaladditionaladjustment=<?php echo $totaladditionaladjustment;?>
                &netpay=<?php echo $netpay;?>
                &gross=<?php echo $gross;?>
                &totaldeductions=<?php echo $totaldeductions;?>
                &REGHOL=<?php echo $REGHOLpay;?>
                &REGHOLRESTDAY=<?php echo $REGHOLRESTDAYpay;?>
                &RESTDAY=<?php echo $RESTDAYpay;?>
                &SPCHOL=<?php echo $SPCHOLpay;?>
                &SPCHOLRESTDAY=<?php echo $SPCHOLRESTDAYpay;?>
                &regot=<?php echo $regotpay;?>
                &regotds=<?php echo $regotdspay;?>
                &regotns=<?php echo $regotnspay;?>
                &holot=<?php echo $holotpay;?>
                &holrdot=<?php echo $holrdotpay;?>
                &rdot=<?php echo $rdotpay;?>
                &spcot=<?php echo $spcotpay;?>
                &spcrdot=<?php echo $spcrdotpay;?>
                &ndreg=<?php echo $ndregpay;?>
                &ndregot=<?php echo $ndregotpay;?>
                &ndreghol=<?php echo $ndregholpay;?>
                &ndregholot=<?php echo $ndregholotpay;?>
                &ndregholrd=<?php echo $ndregholrdpay;?>
                &ndregholrdot=<?php echo $ndregholrdotpay;?>
                &ndrd=<?php echo $ndrdpay;?>
                &ndrdot=<?php echo $ndrdotpay;?>
                &ndspc=<?php echo $ndspcpay;?>
                &ndspcot=<?php echo $ndspcotpay;?>
                &ndspcrd=<?php echo $ndspcrdpay;?>
                &ndspcrdot=<?php echo $ndspcrdotpay;?>
                &nddblhol=<?php echo $nddblholpay;?>
                &nddblholot=<?php echo $nddblholotpay;?>
                &nddblholrd=<?php echo $nddblholrdpay;?>
                &nddblholrdot=<?php echo $nddblholrdotpay;?>
                &SL=<?php echo $SLpay;?>
                &VL=<?php echo $VLpay;?>
                &EL=<?php echo $ELpay;?>
                &ADDITIONALALLOWANCE=<?php echo $totalallowance;?>
                &INCENTIVE=<?php echo $INCENTIVEpay;?>
                &XIIIMONTH=<?php echo $XIIIMONTHpay;?>
                &BONUS=<?php echo $BONUSpay;?>
                &SIL=<?php echo $SILpay;?>
                &DEBIT=<?php echo $DEBITpay;?>
                &CREDIT=<?php echo $CREDITpay;?>
                &OTHERDEDUCTION=<?php echo $OTHERDEDUCTIONpay;?>
                &MEDICAL=<?php echo $MEDICALpay;?>
                &LOAN=<?php echo $LOANpay;?>
                &SSSLOAN=<?php echo $SSSLOANpay;?>
                &HDMFLOAN=<?php echo $HDMFLOANpay;?>
                &CASHADVANCE=<?php echo $CASHADVANCEpay;?>
                &TARDINESS=<?php echo $TARDINESSpay;?>
                &UNDERTIME=<?php echo $UNDERTIMEpay;?>
                &ABSENCES=<?php echo $totalabsence;?>
                &philhealthemployershare=<?php echo $phemployershare;?>
                &philhealthemployeeshare=<?php echo $phemployeeshare;?>
                &sssemployershare=<?php echo $sssemployershare;?>
                &sssemployeeshare=<?php echo $sssemployeeshare;?>
                &hdmfemployershare=<?php echo $row['hdmfemployershare'];?>
                &hdmfemployeeshare=<?php echo $row['hdmfemployeeshare'];?>
                &payrolladjustmentid=<?php echo $row['id'];?>
                &withholdingtax=<?php echo $tax;?>
                &COMPANYBOND=<?php echo $row['COMPANYBOND'];?>
                &taxableincome=<?php echo $taxableincome;?>'
                onclick="window.open('processedpayrollsubmit.php','popup','width=600,height=600'); return false;" target="popup" class="btn btn-info" >Process
                </a>
            </td>
        
            <td><?php echo $row['empno'];?></td>
            <td><?php echo $row['firstname'] ." ". $row['lastname'] ;?></td>
            <td><?php echo $row['payperiod'];?></td>
            <td><?php echo $row['cutoff'];?></td>
            <td><?php echo $totaldays;?></td>
            <td><?php echo $row['ABSENCES'];?></td>
            <td><?php echo number_format($row['ADDITIONALALLOWANCE'],2);?></td>      
            <td><?php echo number_format("$FirstGross",2);?></td>        
            <td><?php echo number_format("$totalBasicpay",2);?></td>        
            <td><?php echo number_format("$gross",2);?></td>
            <td><?php echo number_format("$totaldeductions",2);?></td>
            <td><?php echo number_format("$taxableincome",2);?></td>
            <td><?php echo number_format("$tax",2);?></td>
            <td><?php echo number_format("$netpay",2);?></td>
            <td><?php echo number_format("$REGHOLpay",2);?></td>
            <td><?php echo number_format("$REGHOLRESTDAYpay",2);?></td>
            <td><?php echo number_format("$RESTDAYpay",2);?></td>
            <td><?php echo number_format("$SPCHOLpay",2);?></td>
            <td><?php echo number_format("$SPCHOLRESTDAYpay",2);?></td>
            <td><?php echo number_format("$regotdspay",2);?></td>
            <td><?php echo number_format("$regotnspay",2);?></td>
            <td><?php echo number_format("$regotpay",2);?></td>
            <td><?php echo number_format("$holotpay",2);?></td>
            <td><?php echo number_format("$holrdotpay",2);?></td>
            <td><?php echo number_format("$rdotpay",2);?></td>
            <td><?php echo number_format("$spcotpay",2);?></td>
            <td><?php echo number_format("$spcrdotpay",2);?></td>
            <td><?php echo number_format("$ndregpay",2);?></td>
            <td><?php echo number_format("$ndregotpay",2);?></td>
            <td><?php echo number_format("$ndregholpay",2);?></td>
            <td><?php echo number_format("$ndregholotpay",2);?></td>
            <td><?php echo number_format("$ndregholrdpay",2);?></td>
            <td><?php echo number_format("$ndregholrdotpay",2);?></td>
            <td><?php echo number_format("$ndrdpay",2);?></td>
            <td><?php echo number_format("$ndrdotpay",2);?></td>
            <td><?php echo number_format("$ndspcpay",2);?></td>
            <td><?php echo number_format("$ndspcotpay",2);?></td>
            <td><?php echo number_format("$ndspcrdpay",2);?></td>
            <td><?php echo number_format("$ndspcrdotpay",2);?></td>
            <td><?php echo number_format("$nddblholpay",2);?></td>
            <td><?php echo number_format("$nddblholotpay",2);?></td>
            <td><?php echo number_format("$nddblholrdpay",2);?></td>
            <td><?php echo number_format("$nddblholrdotpay",2);?></td>
            <td><?php echo number_format("$SLpay",2);?></td>
            <td><?php echo number_format("$VLpay",2);?></td>
            <td><?php echo number_format("$ELpay",2);?></td>
           
            <td><?php echo number_format("$INCENTIVEpay",2);?></td>
            <td><?php echo number_format("$XIIIMONTHpay",2);?></td>
            <td><?php echo number_format("$BONUSpay",2);?></td>

            <td><?php echo number_format($row['SIL'],2);?></td>
            <td><?php echo number_format($row['DEBIT'],2);?></td>
            <td><?php echo number_format($row['COMPANYBOND'],2);?></td>
            <td><?php echo number_format($row['CREDIT'],2);?></td>
            
            <td><?php echo number_format("$OTHERDEDUCTIONpay",2);?></td>
            <td><?php echo number_format("$MEDICALpay",2);?></td>
            <td><?php echo number_format("$LOANpay",2);?></td>
            <td><?php echo number_format("$SSSLOANpay",2);?></td>
            <td><?php echo number_format("$HDMFLOANpay",2);?></td>
            <td><?php echo number_format("$CASHADVANCEpay",2);?></td>
            <td><?php echo number_format("$TARDINESSpay",2);?></td>
            <td><?php echo number_format("$UNDERTIMEpay",2);?></td>
            <td><?php echo number_format($phemployershare,2);?></td>
            <td><?php echo number_format($phemployeeshare,2);?></td>
            <td><?php echo number_format($sssemployershare,2);?></td>
            <td><?php echo number_format($sssemployeeshare,2);?></td>
            <td><?php echo number_format($row['hdmfemployershare'],2);?></td>
            <td><?php echo number_format($row['hdmfemployeeshare'],2);?></td>
        </tr>
    <?php endwhile;?>
    <?php endif;?>

  
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--End::row-2 -->
        </div>



<script>
    $(document).ready(function() {
        $('#datatable-basic').DataTable({
            scrollX: true, // Enables horizontal scrolling
            responsive: true,
            paging: true,
            searching: true,
            info: true,
            autoWidth: false
        });
    });
</script>



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

<?php $scripts = ob_get_clean(); ?>
<!-- This code is useful for internal scripts  -->

<!-- This code use for render base file -->
<?php include 'layouts/base.php'; ?>
<?php include 'process_payroll_settings.php'; ?>
<!-- This code use for render base file -->
