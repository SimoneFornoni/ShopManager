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

    $result_supplier = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT f.* , fs.* 
            FROM fornitore_stats fs inner join fornitore f on f.codice = fs.codice 
            WHERE f.codice = '".$_GET['id']."' and f.data_eliminazione is null";
            
    $result_supplier = pg_query($db, $sql);
    if (!$result_supplier) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $supplier = pg_fetch_assoc($result_supplier);
    if (empty($supplier)) {
        $errors[] = "Fornitore non trovato.";
    }

    $result_products = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT p.codice, p.nome, o.prezzo, o.disponibilita
	        FROM prodotto p left join offre o on p.codice = o.prodotto and o.fornitore = '".$_GET['id']."'
	        WHERE p.data_eliminazione is null
            order by disponibilita asc, p.nome asc";
    $result_products = pg_query($db, $sql);

    if (!$result_products) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }

    $products = array();
    while ($row = pg_fetch_assoc($result_products)) {
        $products[$row['codice']] = array(
            'name' => $row['nome'],
            'price' => $row['prezzo'],
            'availability' => $row['disponibilita']
        );
    }

    $result_orders = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT * from storico_ordini('".$_GET['id']."')";
            
    $result_orders = pg_query($db, $sql);
    if (!$result_orders) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $orders = array();
    while ($row = pg_fetch_assoc($result_orders)) {
        $orders[] = array(
            'nome_negozio' => $row['nome_negozio'],
            'data_consegna' => $row['data_consegna'],
            'nome_prodotto' => $row['nome_prodotto'],
            'quantita' => $row['quantita']
        );
    }

    if(empty($errors)) {

        if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'updateSupplier') {

            $input = array(
                'nome' => $_POST['Name'] ?? null,
                'piva' => $_POST['PIva'] ?? null,
                'via' => $_POST['Street'] ?? null,
                'civico' => $_POST['StreetNumber'] ?? null,
                'citta' => $_POST['City'] ?? null,
                'cap' => $_POST['Cap'] ?? null              
            );

            if ($input['nome'] !== '' && strlen($input['nome']) <= 2) {
                $errors[] = "Nome non valido";
            }
            if ($input['piva'] !== '' && !preg_match('/^[0-9]{11}$/', $input['piva'])) {
                $errors[] = "Responsabile non valido";
            }
            if ($input['civico'] !== '' && !preg_match('/^[0-9]{1,3}[A-Z]?$/i', $input['civico'])) {
                $errors[] = "Numero civico non valido.";
            }
            if ($input['cap'] !== '' && !preg_match('/^[0-9]{5}$/', $input['cap'])) {
                $errors[] = "Cap non valido.";
            }

            if (empty($errors)) {
                $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
                $params = array(
                    $_GET['id']
                );

                /* $sql = "UPDATE fornitore SET nome = $2, piva = $3, via = $4, civico = $5, citta = $6, cap = $7 WHERE codice = $1"; */
                $sql = "UPDATE fornitore SET";
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

                $result = pg_prepare($db, "upd_supplier", $sql);
                if (!$result) {
                    $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
                }
                $result = pg_execute($db, "upd_supplier", $params);
                if (!$result) {
                    $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
                } else {
                    header("Location: homeManager.php");
                    exit();
                }
            }
            if (empty($errors)) {
                $suc_msg = "La modifica del fornitore è stata effettuata con successo.";
            }
        }

        if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'deleteSupplier') {
            $result = pg_query($db, 'SET SEARCH_PATH TO myshop');

            $sql = "select * from soft_delete('".$_GET['id']."')";
            $result = pg_query($db, $sql);
            if (!$result) {
                $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
            } else {
                $result = pg_fetch_assoc($result);
                if ($result['soft_delete'] === 'f') {
                    $errors[] = "Errore nell'eliminazione del fornitore: ".pg_last_error($db);
                }  
            }
            if (empty($errors)) {
                $suc_msg = "Il fornitore è stato eliminato con successo.";
                close_pg_connection($db);
                header("Location: homeManager.php");
                exit();
            }
        }

        if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'supply') {
            $product_id = $_POST['idp'];
            $quantity = $_POST['quantity'];

            if (empty($quantity)) {
                $errors[] = "Quantità é obbligatorio.";
            } else if (!is_numeric($quantity) || $quantity <= 0) {
                $errors[] = "Quantità deve essere un numero positivo.";
            }

            if (empty($errors)) {
                $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
                $params = array(
                    $_GET['id'],
                    $product_id,
                    $quantity,
                );

                $sql = "INSERT INTO offre (fornitore, prodotto, disponibilita) VALUES ($1, $2, $3)";
                $result = pg_prepare($db, "supply_product", $sql);
                if (!$result) {
                    $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
                }
                $result = pg_execute($db, "supply_product", $params);
                if (!$result) {
                    $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
                }
            } 
            if (empty($errors)) {
                $suc_msg = "Il rifornimento è stato effettuato con successo.";
                header("Location: ".$_SERVER['PHP_SELF']."?id=".$_GET['id']);
                exit();
            }
        } 
        

        if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'updatePrice') {
            $product_id = $_POST['idp'];
            $price = $_POST['price'];

            if (empty($price)) {
                $errors[] = "Prezzo é obbligatorio.";
            } else if (!is_numeric($price) || $price < 0) {
                $errors[] = "Prezzo deve essere un numero positivo.";
            }

            if (empty($errors)) {
                $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
                $params = array(
                    $_GET['id'],
                    $product_id,
                    $price,
                );

                $sql = "UPDATE offre SET prezzo = $3 WHERE fornitore = $1 AND prodotto = $2";
                $result = pg_prepare($db, "upd_price", $sql);
                if (!$result) {
                    $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
                }
                $result = pg_execute($db, "upd_price", $params);
                if (!$result) {
                    $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
                }
            }
            if (empty($errors)) {
                $suc_msg = "Il prezzo è stato modificato con successo.";
                header("Location: ".$_SERVER['PHP_SELF']."?id=".$_GET['id']);
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
                <h1>Gestisci <?php echo $supplier['nome']?></h1>
                <div class="btn-group gap-2" role="group">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateSupplier">Update</button>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteSupplier">Delete</button>
                </div>
            </div>

            <!-- Modale delete fornitore -->
            <div class="modal fade" id="deleteSupplier" tabindex="-1" aria-labelledby="deleteSupplierLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="deleteSupplierLabel">Vuoi davvero eliminare il Fornitore</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <form method="post" action="<?php echo $_SERVER["PHP_SELF"]."?id=".$_GET['id']."&mod=deleteSupplier"; ?>">
                            <button type="submit" class="btn btn-outline-danger">Delete</button>
                        </form>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Modale update fornitore -->
            <div class="modal fade" id="updateSupplier" tabindex="-1" aria-labelledby="updateSupplierLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="updateSupplierLabel">Modifica il fornitore</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form class="container p-4 " action="<?php echo $_SERVER["PHP_SELF"]."?id=".$_GET['id']."&mod=updateSupplier"; ?>" method='POST'>
                                 <div class="d-flex gap-2 mb-3">
                                    <input type="text" class="form-control w-50" name="Name" placeholder="Nome del Fornitore">
                                    <input type="text" class="form-control w-25" name="PIva" placeholder="Partita IVA">
                                </div>
                                <div class="d-flex gap-2 mb-3">
                                    <input type="text" class="form-control w-50" name="Street" placeholder="Via">
                                    <input type="text" class="form-control w-25" name="StreetNumber" placeholder="Numero Civico">
                                </div>
                                <div class="d-flex gap-2 mb-3">
                                    <input type="text" class="form-control w-50" name="City" placeholder="Città">
                                    <input type="text" class="form-control w-25" name="Cap" placeholder="Cap">
                                </div>
                                <div class="d-flex flex-row-reverse">
                                    <button type="submit" class="btn btn-outline-success mt-3">Modifica</button>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>


            
            <div class="row mt-3">
                <div class="col-md-6">
                    <h2>Dettagli Fornitore</h2>
                    <table class="table table-striped">
                        <tr>
                            <th>Nome</th>
                            <td><?php echo $supplier['nome']; ?></td>
                        </tr>
                        <tr>
                            <th>Partita IVA</th>
                            <td><?php echo $supplier['piva']; ?></td>
                        </tr>
                        <tr>
                            <th>Indirizzo</th>
                            <td><?php echo $supplier['via'].' '.$supplier['civico'].', '.$supplier['citta'].' ( '.$supplier['cap'].' )';?></td>
                        </tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <h2>Statistiche</h2>
                    <table class="table table-striped">
                        <tr>
                            <th>Prodotti in vendita</th>
                            <td><?php echo $supplier['numero_prodotti']; ?></td>
                        </tr>
                        <tr>
                            <th>Numero Ordini</th>
                            <td><?php echo $supplier['numero_ordini']; ?></td>
                        </tr>
                        <tr>
                            <th>Media Prezzi</th>
                            <td><?php echo $supplier['media_prezzi'] !== null ? number_format($supplier['media_prezzi'], 2) : '0.00'; ?> €</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="row mt-4">
                <h2>Prodotti in Vendita</h2>
                <p>Il prezzo é inizialmente fissato a 0, successivamente é possibile modificarlo a piacimento</p>
                <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                    <?php if (empty($products)) { ?>
                        <div class="alert alert-info w-100" role="alert">Nessun prodotto presente.</div>
                    <?php } else { ?>
                        <table class="table table-striped ">
                            <thead class="sticky-top">
                                <tr class="text-center">
                                    <th>Nome Prodotto</th>
                                    <th>Prezzo</th>
                                    <th>Disponibilità</th>
                                    <th>Aggiungi Disponibilità</th>
                                    <th>Modifica Prezzo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product_id => $product) { ?>
                                    <tr class="text-center">
                                        <td><?php echo $product['name']; ?></td>
                                        <td><?php echo $product['price'] !== null ? $product['price']."€" : "-"; ?> </td>
                                        <td>
                                            <?php if ($product['availability'] === null) { ?>
                                                <span class="badge bg-danger">Non disponibile</span>
                                            <?php } else { 
                                                    echo $product['availability'];
                                                } 
                                            ?>
                                        </td>
                                        <td>
                                            <form method="post" class="d-flex justify-content-center gap-2" action="<?php echo $_SERVER["PHP_SELF"]."?id=".$_GET['id']."&mod=supply"; ?>">
                                                <input type="hidden" name="idp" value="<?php echo $product_id; ?>">
                                                <input type="number" name="quantity" class="form-control" style="width: 122px" min="0" step="1" required placeholder="Quantità">
                                                <button type="submit" class="btn btn-outline-success">Aggiungi</button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="post" class="d-flex justify-content-center gap-2" action="<?php echo $_SERVER["PHP_SELF"]."?id=".$_GET['id']."&mod=updatePrice"; ?>">
                                                <input type="hidden" name="idp" value="<?php echo $product_id; ?>">
                                                <?php 
                                                    if ($product['availability'] === null) { 
                                                        $price_input_class = "form-control disabled";
                                                    } else { 
                                                        $price_input_class = "form-control";
                                                    } 
                                                ?>
                                                <input type="number" name="price" class=<?php echo $price_input_class?> style="width: 122px" placeholder="Prezzo" min="0" step="0.01" required>
                                                <button type="submit" class="btn btn-outline-success">Modifica</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>

            <div class="row mt-5">
                <h2>Storico Ordini</h2>
                <?php if (empty($orders)) { ?>
                    <div class="alert alert-info" role="alert"> Non hai effettuato ordini. </div>
                <?php } else { ?>
                    <div class="table-responsive w-75 mt-3 mb-5" style="max-height: 260px; overflow-y: auto;">
                        <table class="table table-striped">
                            <thead class="sticky-top">
                                <tr>
                                    <th>Negozio</th>
                                    <th>Data Consegna</th>
                                    <th>Prodotto</th>
                                    <th>Quantità</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order) { ?>
                                    <tr>
                                        <td><?php echo $order['nome_negozio']; ?></td>
                                        <td><?php echo $order['data_consegna']; ?></td>
                                        <td><?php echo $order['nome_prodotto']; ?></td>
                                        <td><?php echo $order['quantita']; ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>                    
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
	</body>
</html>