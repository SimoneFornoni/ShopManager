<?php 
  include_once 'conf/conf.php';

  $suc_msg_nav = "";
  $errors_nav = array();

  $db = open_pg_connection();
  if (!$db) {
      $errors_nav[] = "Errore di connessione al database: ".pg_last_error();
  }

  $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
  $sql = "SELECT punti FROM tessera where cliente = '".$_SESSION['id']."' and data_disattivazione is null";
  $result = pg_query($db, $sql);
  if (!$result) {
      $errors_nav[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
  }
  $points = @pg_fetch_result($result, 0, 0);

  if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'logout') {
    session_unset();
    session_destroy();
    header("Location: auth/logIn.php");
    exit();
  }

  if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'changePassword') {
      $currentPassword = $_POST['currentPassword'];
      $newPassword = $_POST['newPassword'];

      if (empty($currentPassword)) {
          $errors_nav[] = "La password attuale non può essere vuota.";
      }

      if (empty($newPassword)) {
          $errors_nav[] = "La nuova password non può essere vuota.";
      }

      if (empty($errors_nav)) {
           $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
           
           $params = array(
              $_SESSION['id'],
              $currentPassword,
              $newPassword
          );

          $sql = "SELECT cambia_password($1, $2, $3)";
          
          $result = pg_prepare($db, "change_password", $sql);
          if (!$result) {
              $errors_nav[] = "Errore nella preparazione della query: ".pg_last_error($db);
          }
          $result = pg_execute($db, "change_password", $params);
          if (!$result) {
              $errors_nav[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
          } else {
              $row = pg_fetch_result($result, 0, 0); // recupera il valore booleano
              if ($row === 't') {
                  $suc_msg_nav = "Password cambiata con successo.";
              } else {
                  $errors_nav[] = "La password attuale non è corretta.";
              }
          }
      }
      close_pg_connection($db);
  }

  if (!isset($_SESSION['role']) || $_SESSION['role'] === 'manager') {
      $main_link = "homeManager.php";
      $nav_items = array (
          'Fornitori' => 'homeManager.php?mod=suppliers',
          'Negozi' => 'homeManager.php?mod=shops',
          'Prodotti' => 'homeManager.php?mod=products',
          'Clienti' => 'homeManager.php?mod=customers'
      );
  } else {
      $main_link = "homeCustomer.php";
      $nav_items = array (
          'Acquisti' => 'homeCustomer.php?mod=purchases',
      );
  }
?>
<nav class="navbar navbar-expand-lg bg-body-tertiary sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?php echo $main_link; ?>">MyShop</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php 
              if (isset($nav_items)) {
                  foreach($nav_items as $key => $value){
          ?>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo $value; ?>"><?php echo $key; ?></a>
          </li>
          <?php
                  }
              }
          ?>
      </ul>
      <div class="d-flex gap-2">
        <?php 
          if (isset($_SESSION['username'])) {
            if ($_SESSION['role'] === 'customer') {
              if ($points === false) {
                $points = "Nessuna tessera attiva";
              }
        ?>
              <span class="navbar-text me-5">Punti: <?php echo $points; ?></span>
          <?php
            }
          ?>
          <span class="navbar-text me-3"> <?php echo $_SESSION['username']; ?> </span>
          <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#changePassword">Cambia Password</button>
          <form method="post" action="<?php echo "homeManager.php?mod=logout"; ?>">
            <button type="submit" name="logout" class="btn btn-outline-primary">Logout</button>
          </form>
        <?php
            }
        ?>
      </div>
    </div>
  </div>
</nav>
<?php
    if (!empty($errors_nav)) {
?>
    <div class="alert alert-danger mt-3" role="alert">
        <?php echo implode('<br>', $errors_nav); ?>
    </div>
<?php
    } else if (!empty($suc_msg_nav)) {
?>
    <div class="alert alert-success mt-3" role="alert">
        <?php echo $suc_msg_nav; ?>
    </div>
<?php
    }
?>
<div class="modal fade" id="changePassword" tabindex="-1" aria-labelledby="changePasswordLabel" aria-hidden="true">
  <div class="modal-dialog">
      <div class="modal-content">
          <div class="modal-header">
              <h1 class="modal-title fs-5" id="changePasswordLabel">Cambia la password del tuo account</h1>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              <form method="post" action="<?php echo $main_link."?mod=changePassword"; ?>">
                  <div class="mb-3">
                    <label for="currentPassword" class="form-label">Password attuale</label>
                    <input type="password" class="form-control" id="currentPassword" name="currentPassword" required>
                  </div>
                  <div class="mb-3">
                    <label for="newPassword" class="form-label">Nuova password</label>
                    <input type="password" class="form-control" id="newPassword" name="newPassword" required>
                  </div>
                  <div class="d-flex flex-row-reverse">
                      <button type="submit" class="btn btn-primary">Cambia Password</button>
                  </div>
              </form>
          </div>
      </div>
  </div>
</div>