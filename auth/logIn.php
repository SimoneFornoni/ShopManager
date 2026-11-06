<?php
    require '../conf/conf.php';

    $err_msg = "";
    if(isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'login') {
        $errors =[];
        $role = $_POST['Role'];
        $params[] =  $_POST['Email'];
        $params[] =  $_POST['Password'];
        
        $db = open_pg_connection();
        if (!$db) {
            $err_msg = "Errore di connessione al database: ".pg_last_error();
        }
        $result = pg_query($db, 'SET SEARCH_PATH TO myshop');

        if ($role === 'customer') {
            $sql = "SELECT * FROM cliente WHERE email = $1 AND password = $2 AND data_eliminazione IS NULL";
        } else {
            $sql = "SELECT * FROM manager WHERE email = $1 AND password = $2";
        }
        
        $result = pg_prepare($db, "check_user", $sql);
        if (!$result) {
            $err_msg = "Errore nella preparazione della query: ".pg_last_error($db);
        }
        $result = pg_execute($db, "check_user", $params);
        if (!$result) {
            $err_msg = "Errore nell'esecuzione della query: ".pg_last_error($db);
        } else {
            if (pg_num_rows($result) > 0) {
                $row = pg_fetch_assoc($result);
                session_start();
                $_SESSION['id'] = $row['codice'];
                $_SESSION['username'] = $row['username'];
                if ($role === 'customer') {
                    $_SESSION['role'] = 'customer';
                    header("Location: ../homeCustomer.php");
                } else {
                    $_SESSION['role'] = 'manager';
                    header("Location: ../homeManager.php");
                }
            } else {
                $err_msg = "Email o password non corretti.";
            }
        }
        close_pg_connection($db);
    }
    $pagelink = $_SERVER['PHP_SELF'].'?mod=login';
?>
<!DOCTYPE html>
<html>
	<head>
		<?php include_once('../lib/header.php'); ?>
	</head>
	<body>
        <?php ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL); ?>
        <div class="container border border-2 p-4 rounded col-6 mt-5">
            <div class="row mt-3 mb-2">
                <div class="col-8">
                    <h1 class="">Accedi a MyShop</h1>
                </div>
            </div>
            <form id="logIn" class="container" action="<?php echo $pagelink; ?>" method='POST'>
                <div class="mb-5">
                    <label for="Role" class="form-label">Inserisci il tipo di utente</label>
                    <select class="form-select w-75" name="Role">
                        <option value="customer">Cliente</option>
                        <option value="manager">Manager</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="Email" class="form-label">Inserisci le credenziali di accesso</label>
                    <input type="email" class="form-control w-75" name="Email" placeholder="Email">
                </div>
                <div class="mb-3">
                    <input type="password" class="form-control w-75" name="Password" placeholder="Password">
                </div>
                <div class="d-flex flex-row-reverse">
                    <button type="submit" class="btn btn-primary">Log In</button> 
                </div>
            </form>
        </div>
        <?php
        if (!empty($err_msg)) {
        ?>
        <div class="alert alert-danger mt-3" role="alert">
            <?php echo $err_msg; ?>
        </div>
        <?php
        } else if (!empty($suc_msg)) {
        ?>
        <div class="alert alert-success mt-3" role="alert">
            <?php echo $suc_msg; ?>
        </div>
        <?php
        }
        ?>
    </body>
</html>

