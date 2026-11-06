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

    $result_shop = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT n.* , ns.* 
            FROM negozio_stats ns inner join negozio n on n.codice = ns.codice 
            WHERE n.codice = '".$_GET['id']."' and n.data_eliminazione is null";
            
    $result_shop = pg_query($db, $sql);
    if (!$result_shop) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $shop = pg_fetch_assoc($result_shop);
    if (empty($shop)) {
        $errors[] = "Negozio non trovato.";
    }

    $result_products = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT p.codice, p.nome, v.prezzo, v.disponibilita 
	        FROM prodotto p left join vende v on p.codice = v.prodotto and v.negozio = '".$_GET['id']."'
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

    $result_fidaty = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT * FROM lista_tesserati('".$_GET['id']."')";

    $result_fidaty = pg_query($db, $sql);

    if (!$result_fidaty) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }

    $fidaties = array();
    while ($row = pg_fetch_assoc($result_fidaty)) {
        $fidaties[] = array(
            'username' => $row['username'],
            'data_rilascio' => $row['data_rilascio'],
            'attiva' => ($row['data_disattivazione'] === null)
        );
    }
        
    $result_invoices = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT * FROM storico_fatture('".$_GET['id']."')";
    $result_invoices = pg_query($db, $sql);
    if (!$result_invoices) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $invoices = array();
    while ($row = pg_fetch_assoc($result_invoices)) {
        $invoices[] = array(
            'spesa' => $row['spesa'],
            'username_cliente' => $row['username_cliente'],
            'data_emissione' => $row['data_emissione'],
            'prodotti' => json_decode($row['prodotti'], true)
        );
    }

    if(empty($errors)) {

        if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'updateShop') {

            $input = array(
                'nome' => $_POST['Name'] ?? null,
                'responsabile' => $_POST['Head'] ?? null,
                'orario_apertura' => $_POST['OpeningTime'] ?? null,
                'via' => $_POST['Street'] ?? null,
                'civico' => $_POST['StreetNumber'] ?? null,
                'citta' => $_POST['City'] ?? null,
                'cap' => $_POST['Cap'] ?? null              
            );

            if ($input['nome'] !== '' && strlen($input['nome']) <= 2) {
                $errors[] = "Nome non valido";
            }
            if ($input['responsabile'] !== '' && strlen($input['responsabile']) <= 2) {
                $errors[] = "Responsabile non valido";
            }
            if ($input['civico'] !== '' && !preg_match('/^[0-9]{1,3}[A-Z]?$/i', $input['civico'])) {
                $errors[] = "Numero civico non valido.";
            }
            if ($input['cap'] !== '' && !preg_match('/^[0-9]{5}$/', $input['cap'])) {
                $errors[] = "Cap non valido.";
            }
            echo "<script>console.log(" . json_encode($errors) . ");</script>";
            if (empty($errors)) {
                $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
                $params = array(
                    $_GET['id']
                );

                /* $sql = "UPDATE negozio SET nome = $2, responsabile = $3, orario_apertura = $4, via = $5, civico = $6, citta = $7, cap = $8 WHERE codice = $1"; */
                $sql = "UPDATE negozio SET";
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

                $result = pg_prepare($db, "upd_shop", $sql);
                if (!$result) {
                    $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
                }
                $result = pg_execute($db, "upd_shop", $params);
                if (!$result) {
                    $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
                }
                echo "<script>console.log(" . json_encode($errors) . ");</script>";
            }
            if (empty($errors)) {
                $suc_msg = "La modifica del negozio è stata effettuata con successo.";
            }
        }

        if (isset($_POST) && isset($_GET['mod']) && $_GET['mod'] === 'deleteShop') {
            $result = pg_query($db, 'SET SEARCH_PATH TO myshop');

            $sql = "select * from soft_delete('".$_GET['id']."')";
            $result = pg_query($db, $sql);
            if (!$result) {
                $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
            } else {
                $result = pg_fetch_assoc($result);
                if ($result['soft_delete'] === 'f') {
                    $errors[] = "Errore nell'eliminazione del negozio: ".pg_last_error($db);
                }  
            }
            if (empty($errors)) {
                $suc_msg = "Il negozio è stato eliminato con successo.";
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

                $sql = "SELECT * FROM ordina_prodotto($1, $2, $3)";
                $result = pg_prepare($db, "supply_product", $sql);
                if (!$result) {
                    $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
                }
                $result = pg_execute($db, "supply_product", $params);
                if (!$result) {
                    $errors[] = "Errore nell'esecuzione della query: " . pg_last_error($db);
                }
            } 
            if (empty($errors)) {
                $suc_msg = "L'ordine è stato aggiunto con successo.";
                close_pg_connection($db);
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

                $sql = "UPDATE vende SET prezzo = $3 WHERE negozio = $1 AND prodotto = $2";
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
                close_pg_connection($db);
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
                <?php echo implode('<br>', $errors); ?>
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
                <h1>Gestisci <?php echo $shop['nome']?></h1>
                <div class="btn-group gap-2" role="group">
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateShop">Update</button>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteShop">Delete</button>
                </div>
            </div>

            <!-- Modale delete negozio -->
            <div class="modal fade" id="deleteShop" tabindex="-1" aria-labelledby="deleteShopLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="deleteShopLabel">Vuoi davvero eliminare il Negozio</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <form method="post" action="<?php echo $_SERVER["PHP_SELF"]."?id=".$_GET['id']."&mod=deleteShop"; ?>">
                            <button type="submit" class="btn btn-outline-danger">Delete</button>
                        </form>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Modale update negozio -->
            <div class="modal fade" id="updateShop" tabindex="-1" aria-labelledby="updateShopLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="updateShopLabel">Modifica il negozio</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form class="container p-4 " action="<?php echo $_SERVER["PHP_SELF"]."?id=".$_GET['id']."&mod=updateShop"; ?>" method='POST'>
                                <div class="d-flex gap-2 mb-3">
                                    <input type="text" class="form-control w-50" name="Name" placeholder="Nome del Negozio">
                                    <input type="text" class="form-control w-50" name="Head" placeholder="Responsabile del Negozio">
                                </div>
                                <div class="d-flex mb-3">
                                    <label for="OpeningTime" class="form-label col-2 mt-1">Orario di Apertura:</label>
                                    <input type="time" class="form-control w-25" id="OpeningTime" name="OpeningTime" placeholder="Orario di Apertura">
                                </div>
                                <div class="d-flex gap-2 mb-3">
                                    <input type="text" class="form-control w-50" name="Street" placeholder="Via">
                                    <input type="text" class="form-control w-25" name="StreetNumber" placeholder="Numero Civico">
                                </div>
                                <div class="d-flex gap-2 mb-3">
                                    <input type="text" class="form-control w-50" name="City" placeholder="Città">
                                    <input type="text" class="form-control w-25" name="Cap" placeholder="Cap">
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
                <div class="col-md-6">
                    <h2>Dettagli Negozio</h2>
                    <table class="table table-striped">
                        <tr>
                            <th>Nome</th>
                            <td><?php echo $shop['nome']; ?></td>
                        </tr>
                        <tr>
                            <th>Responsabile</th>
                            <td><?php echo $shop['responsabile']; ?></td>
                        </tr>
                        <tr>
                            <th>Orario di apertura</th>
                            <td><?php echo $shop['orario_apertura']; ?></td>
                        </tr>
                        <tr>
                            <th>Indirizzo</th>
                            <td><?php echo $shop['via'].' '.$shop['civico'].', '.$shop['citta'].' ( '.$shop['cap'].' )';?></td>
                        </tr>
                    </table>
                </div>

                <div class="col-md-6">
                    <h2>Statistiche</h2>
                    <table class="table table-striped">
                        <tr>
                            <th>Prodotti in vendita</th>
                            <td><?php echo $shop['numero_prodotti']; ?></td>
                        </tr>
                        <tr>
                            <th>Numero vendite</th>
                            <td><?php echo $shop['numero_fatture']; ?></td>
                        </tr>
                        <tr>
                            <th>Fatturato</th>
                            <td><?php echo $shop['guadagno_totale'] !== null ? number_format($shop['guadagno_totale'], 2) : '0.00'; ?> €</td>
                        </tr>
                        <tr>
                            <th>Media Prezzi</th>
                            <td><?php echo $shop['media_prezzi'] !== null ? number_format($shop['media_prezzi'], 2) : '0.00'; ?> €</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="row mt-4">
                <h2>Prodotti in Vendita</h2>
                <p>Il prezzo é inizialmente fissato a quello indicato dal fornitore, successivamente é possibile modificarlo a piacimento</p>
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
                                                <input type="number" name="quantity" class="form-control" style="width: 122px" min="0" step="1" placeholder="Quantità" required>
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
            <div class="row mt-4 mb-5">
                <div class="col-8">
                    <h2>Storico Fatture</h2>
                    <?php if (empty($invoices)) { ?>
                        <div class="alert alert-info" role="alert"> Questo negozio non ha emesso nessuna fattura. </div>
                    <?php } else {?>
                        <div class="table-responsive mt-4" style="max-height: 260px; overflow-y: auto;">
                            <table class="table table-striped">
                                <thead class="sticky-top">
                                    <tr>
                                        <th scope="col">Spesa</th>
                                        <th scope="col">Cliente</th>
                                        <th scope="col">Data Emissione</th>
                                        <th scope="col">Prodotti</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($invoices as $invoice) { ?>
                                        <tr>
                                            <td><?php echo $invoice['spesa']; ?></td>
                                            <td><?php echo $invoice['username_cliente']; ?></td>
                                            <td><?php echo $invoice['data_emissione']; ?></td>
                                            <td>
                                                <ul>
                                                    <?php foreach ($invoice['prodotti'] as $product) { ?>
                                                        <li><?php echo $product['nome'] . " - " . $product['quantita'] . " x " . $product['prezzo']; ?></li>
                                                    <?php } ?>
                                                </ul>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
                <div class="col-4">
                    <h2>Tessere Rilasciate</h2>
                    <?php if (empty($fidaties)) { ?>
                        <div class="alert alert-info" role="alert"> Nessun cliente ha attivato la tessera in questo negozio. </div>
                    <?php } else {
                        ?>
                        <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
                            <table class="table table-striped">
                                <thead class="sticky-top">
                                    <th>Cliente</th>
                                    <th>Data Rilascio</th>
                                    <th>Attiva</th>
                                </thead>
                                <tbody>
                                    <?php foreach ($fidaties as $fidaty) { ?>
                                        <tr>
                                            <td><?php echo $fidaty['username']; ?></td>
                                            <td><?php echo $fidaty['data_rilascio']; ?></td>
                                            <?php if ($fidaty['attiva']) { ?>
                                                <td><span class="badge bg-success">Attiva</span></td>
                                            <?php } else { ?>
                                                <td><span class="badge bg-danger">Disattivata</span></td>
                                            <?php } ?>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>

            

            <div class="row mt-5 mb-5">
                
            </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
	</body>
</html>