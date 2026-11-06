<?php 
    session_start(); 
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
        header("Location: auth/login.php");
        exit();
    }
?>
<!DOCTYPE html>
<html>
	<head>
		<?php include_once('lib/header.php'); ?>
	</head>
	<body>
        <?php ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); ?>
        <?php include_once('lib/navbar.php'); ?>
        <?php
            if (isset($_GET) && isset($_GET['mod'])) {
                switch ($_GET['mod']) {
                    case 'suppliers':
                        include_once('lib/suppliers.php');
                        break;
                    case 'shops':
                        include_once('lib/shops.php');
                        break;
                    case 'products':
                        include_once('lib/products.php');
                        break;
                    case 'customers':
                        include_once('lib/customers.php');
                        break;
                }
            } else {
                include_once('lib/topList.php');
            }
        ?> 

        <?php
            if (!empty($errors)) {
        ?>
            <div class="alert alert-danger mt-3" role="alert">
                <?php echo implode("<br>", $errors); ?>
            </div>
        <?php
            }
            if (!empty($suc_msg)) {
        ?>
            <div class="alert alert-success mt-3" role="alert">
                <?php echo $suc_msg; ?>
            </div>
        <?php
            }   
        ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
	</body>
</html>