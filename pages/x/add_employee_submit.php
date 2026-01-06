<html>
<head>
    <title>Add Data</title>
</head>
 
<body>
<?php
//including the database connection file
include_once("db2.php");


echo $empno = $_POST['empno'];
$firstName = $_POST['firstName'];
$middleName = $_POST['middleName'];
$lastName = $_POST['lastName'];
$gender = $_POST['gender'];
$address = $_POST['address'];
$dob = $_POST['dob'];
$age = $_POST['age'];
$civil = $_POST['civil'];
$contact = $_POST['contact'];
$hired = $_POST['hired'];
$education = $_POST['education'];
$company = $_POST['company'];
$position = $_POST['position'];
$department = $_POST['department'];
$client = $_POST['client'];
$secondaryrate = $_POST['secondaryrate'];
$tinID = $_POST['tinID'];
$sssID = $_POST['sssID'];
$philhealthID = $_POST['philhealthID'];
$pagibigID = $_POST['pagibigID'];
$daily_salary = $_POST['daily_salary'];
$monthly_salary = $_POST['monthly_salary'];
$empstatus = $_POST['empstatus'];
$philhealthbracket = $_POST['philhealthbracket'];
$sssbracket = $_POST['sssbracket'];
$hdmfbracket = $_POST['hdmfbracket'];
$atm = $_POST['atm'];
$allowance = $_POST['allowance'];
$tax_exemption = $_POST['tax_exemption'];
$today = date("Y-m-d");
$user = $_POST['loggeduser'];

// $forlog = $empno." - ".$firstName." - ".$lastName." Manpower Module";

$result = mysqli_query($mysqli, "SELECT * FROM employees WHERE empno = '$empno'");

if((mysqli_num_rows($result) == 0) && isset($_POST['Submit'])) {
    $insert = mysqli_query($mysqli, "INSERT INTO employees 
        (empno, firstName, middleName, lastName, gender, address, dob, age, civil, contact, hired, education, company, position, department, tinID, sssID, philhealthID, pagibigID, daily_salary, monthly_salary, empstatus, philhealthbracket, sssbracket, hdmfbracket, atm, allowance, tax_exemption, client, secondaryrate, dateadded, covax_priority, project_code) 
    VALUES 
    ('$empno','$firstName','$middleName','$lastName','$gender','$address','$dob','$age','$civil','$contact','$hired','$education','$company','$position','$department','$tinID','$sssID','$philhealthID','$pagibigID','$daily_salary','$monthly_salary','$empstatus','$philhealthbracket','$sssbracket','$hdmfbracket','$atm','$allowance','$tax_exemption','$client','$secondaryrate','$today','$covax_priority','$project_code')");
    

    if (mysqli_query($mysqli, $insert)) {
        // Display success message and redirect
        echo "<script>
                alert('Data added successfully');
                window.location.href = 'add_employee.php';
              </script>";
    } else {
        // Display error message if query fails
        echo "Error: " . mysqli_error($mysqli);
    }



    
    //display success message
    echo "<script>alert('Data added successfully'); window.location.href = 'add_employee.php'; //Will take you to Google.</script>";
    //header('Location: manpowerattpepsi.php');
    //echo "<br/><a href='index.php'>View Result</a>";
    } else if ( (mysqli_num_rows($result) != 0) && isset($_POST['Submit'])) { 
    
             print "<br>Error! <p></p>";
            echo "" . $empno . " already exists in the Database";
            print "<p></p>Please check Employee Details and try again";
    
            
    }









?>
</body>
</html>
