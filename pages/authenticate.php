<?php 
	require 'dbcon.php';

	session_start();

	$username = "";
	$password = "";
	
	if(isset($_POST['username'])){
		$username = $_POST['username'];
	}
	if (isset($_POST['password'])) {
		$password = $_POST['password'];

	}
	
	echo $username ." : ".$password;

	$q = 'SELECT * FROM user WHERE username=:username AND password=:password';

	$query = $dbh->prepare($q);

	$query->execute(array(':username' => $username, ':password' => $password));


	if($query->rowCount() == 0){
		header('Location: signin-basic.php?err=1');
	}else{

		$row = $query->fetch(PDO::FETCH_ASSOC);

		session_regenerate_id();
		$_SESSION['sess_user_id'] = $row['id'];
		$_SESSION['sess_firstname'] = $row['firstname'];
		$_SESSION['sess_lastname'] = $row['lastname'];
        $_SESSION['sess_userrole'] = $row['role'];
        $_SESSION['sess_department'] = isset($row['department']) ? $row['department'] : null;

        echo $_SESSION['sess_userrole'];
		session_write_close();

		if( ($_SESSION['sess_userrole'] == "Admin") || ( $_SESSION['sess_userrole'] == "Nonadmin")){
			header('Location: /smhc/pages/index.php');
		}else{
			header('Location: index.php');
		}
		
		
	}


?>
