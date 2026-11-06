<!DOCTYPE html>
<html>
	<head>
		<?php include_once('lib/header.php'); ?>
	</head>
	<body>
        <?php ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); ?>
		<?php 
		session_start();
		if(!isset($_SESSION['id'])) {
			header('Location: auth\logIn.php'); 
		} else {
			if(isset($_SESSION['role']) && $_SESSION['role'] == 'manager') {
				header('Location: homeManager.php'); 
			} else {
				header('Location: homeManager.php'); 
			}
		}
		?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
	</body>
</html>