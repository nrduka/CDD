<?php 
	require_once 'dbcon.php';

	session_start();

	$username = isset($_POST['username']) ? trim((string)$_POST['username']) : "";
	$password = isset($_POST['password']) ? (string)$_POST['password'] : "";

	if (!($dbh instanceof PDO)) {
		header('Location: signin-basic.php?err=3');
		exit;
	}

	try {
		$q = 'SELECT * FROM user WHERE username = :username AND password = :password LIMIT 1';
		$query = $dbh->prepare($q);
		$query->execute(array(':username' => $username, ':password' => $password));
		$row = $query->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$dbh = null;
			header('Location: signin-basic.php?err=1');
			exit;
		}

		session_regenerate_id();
		$_SESSION['sess_user_id'] = $row['id'];
		$_SESSION['sess_firstname'] = $row['firstname'];
		$_SESSION['sess_lastname'] = $row['lastname'];
        $_SESSION['sess_userrole'] = $row['role'];
        $_SESSION['sess_department'] = isset($row['department']) ? $row['department'] : null;
		session_write_close();
		$dbh = null;

		if (($_SESSION['sess_userrole'] == "Admin") || ($_SESSION['sess_userrole'] == "Nonadmin")) {
			header('Location: /cdd/pages/index.php');
			exit;
		}

		header('Location: index.php');
		exit;
	} catch (PDOException $e) {
		error_log('authenticate.php query failed: ' . $e->getMessage());
		$dbh = null;
		header('Location: signin-basic.php?err=3');
		exit;
	}


?>
