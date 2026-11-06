<?php 
    include_once 'conf/conf.php';

    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
        header("Location: auth/login.php");
        exit();
    }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        header("Location: homeManager.php");
        exit();
    }

    $suc_msg = "";
    $errors = array();

    $db = open_pg_connection();
    if (!$db) {
        $errors[] = "Errore di connessione al database: ".pg_last_error();
    }

    $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT p.descrizione, ps.* 
            FROM prodotto_stats ps inner join prodotto p on p.codice = ps.codice 
            WHERE p.codice = '".$_GET['id']."' and p.data_eliminazione is null";

    $result = pg_query($db, $sql);
    if (!$result) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $product = pg_fetch_assoc($result);
    if (empty($product)) {
        $errors[] = "Prodotto non trovato.";
    }

    if(empty($errors)) {

        if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'updateProduct') {

            $input = array(
                'nome' => $_POST['Name'] ?? null,
                'descrizione' => $_POST['Description'] ?? null,           
            );

            if ($input['nome'] !== '' && strlen($input['nome']) <= 2) {
                $errors[] = "Nome non valido";
            }
            if ($input['descrizione'] !== '' && strlen($input['descrizione']) <= 2) {
                $errors[] = "Descrizione non valido";
            }
            if (empty($errors)) {
                $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
                $params = array(
                    $_GET['id']
                );

                /* $sql = "UPDATE prodotto SET nome = $2, descrizione = $3 WHERE codice = $1"; */
                $sql = "UPDATE prodotto SET";
                $count = 2;
                foreach ($input as $key => $value) {
                    if ( $value === null || $value === '') {
                        continue;
                    }
                    $params[] = $value; 
                    $sql .= " $key = \$".$count.",";
                    $count++;
                }
                $sql = rtrim($sql, ',')." WHERE codice = \$1";

                $result = pg_prepare($db, "upd_product", $sql);
                if (!$result) {
                    $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
                }
                $result = pg_execute($db, "upd_product", $params);
                if (!$result) {
                    $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
                } else {
                    header("Location: homeManager.php");
                    exit();
                }
            }
            if (empty($errors)) {
                $suc_msg = "La modifica del prodotto è stata effettuata con successo.";
            }
        }

        if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'deleteProduct') {
            $result = pg_query($db, 'SET SEARCH_PATH TO myshop');

            $sql = "select * from soft_delete('".$_GET['id']."')";
            $result = pg_query($db, $sql);
            if (!$result) {
                $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
            } else {
                $result = pg_fetch_assoc($result);
                if ($result['soft_delete'] === 'f') {
                    $errors[] = "Errore nell'eliminazione del prodotto: ".pg_last_error($db);
                }  
            }
            if (empty($errors)) {
                $suc_msg = "Il prodotto è stato eliminato con successo.";
                close_pg_connection($db);
                header("Location: homeManager.php");
                exit();
            }
        }
    }
    close_pg_connection($db);
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
            if (!empty($errors)) {
        ?>
            <div class="alert alert-danger mt-3" role="alert">
                <?php echo implode("<br>", $errors); ?>
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

        <div class="container">
            <div class="d-flex justify-content-between mt-4">
                <h1>Gestisci <?php echo $product['nome']?></h1>
                <div class="btn-group gap-2" role="group">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateProduct">Update</button>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteProduct">Delete</button>
                </div>
            </div>

            <!-- Modale delete negozio -->
            <div class="modal fade" id="deleteProduct" tabindex="-1" aria-labelledby="deleteProductLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="deleteProductLabel">Vuoi davvero eliminare il prodotto</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <form method="post" action="<?php echo $_SERVER["PHP_SELF"]."?id=".$_GET['id']."&mod=deleteProduct"; ?>">
                            <button type="submit" class="btn btn-outline-danger">Delete</button>
                        </form>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Modale update negozio -->
            <div class="modal fade" id="updateProduct" tabindex="-1" aria-labelledby="updateProductLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="updateProductLabel">Modifica il prodotto</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form class="container p-4 " action="<?php echo $_SERVER["PHP_SELF"]."?id=".$_GET['id']."&mod=updateProduct"; ?>" method='POST'>
                                <div class="d-flex gap-2 mb-3">
                                    <input type="text" class="form-control w-50" name="Name" placeholder="Nome del Prodotto">
                                </div>
                                <div class="form-floating w-75">
                                    <textarea class="form-control" placeholder="Leave a comment here" name="Description"></textarea>
                                    <label for="floatingTextarea">Descrizione del Prodotto</label>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-outline-success">Modifica</button>
                                </div>
                            </form>
                        </div>
                        
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-9">
                    <h2>Dettagli Prodotto</h2>
                    <table class="table table-striped">
                        <tr>
                            <th>Nome</th>
                            <td><?php echo $product['nome']; ?></td>
                        </tr>
                        <tr>
                            <th>Descrizione</th>
                            <td><?php echo $product['descrizione']; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-6">
                    <h2>Statistiche</h2>
                    <table class="table table-striped">
                        <tr>
                            <th>Fornitori che offrono il prodotto</th>
                            <td><?php echo $product['numero_fornitori']; ?></td>
                        </tr>
                        <tr>
                            <th>Media del prezzo di offerta dei fornitori</th>
                            <td><?php echo $product['media_prezzo_fornitore'] !== null ? number_format($product['media_prezzo_fornitore'], 2) : '0.00'; ?> €</td>
                        </tr>
                        <tr>
                            <th>Negozi che vendono il prodotto</th>
                            <td><?php echo $product['numero_negozi']; ?></td>
                        </tr>
                        <tr>
                            <th>Media del prezzo di vendita dei negozi</th>
                            <td><?php echo $product['media_prezzo_negozio'] !== null ? number_format($product['media_prezzo_negozio'], 2) : '0.00'; ?> €</td>
                        </tr>
                    </table>
                </div>
            </div>
            
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
	</body>
</html>