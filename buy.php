<?php
    include_once 'conf/conf.php';

    session_start();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
        header("Location: auth/login.php");
        exit();
    }

    if (!isset($_GET['id']) || empty($_GET['id'])) {
        header("Location: homeCustomer.php");
        exit();
    }

    $suc_msg = "";
    $errors = array();

    $db = open_pg_connection();
    if (!$db) {
        $errors[] = "Errore di connessione al database: ".pg_last_error();
    }

    $result_shop = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT nome FROM negozio WHERE codice = '".$_GET['id']."' and data_eliminazione is null";
    $result_shop = pg_query($db, $sql);
    if (!$result_shop) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $shop = pg_fetch_assoc($result_shop);

    $result_products = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT * FROM lista_prodotti_in_vendita('".$_GET['id']."') order by nome asc";
    $result_products = pg_query($db, $sql);
    if (!$result_products) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $products = array();
    while ($row = pg_fetch_assoc($result_products)) {
        $products[$row['codice']] = array(
            'name' => $row['nome'],
            'price' => $row['prezzo'],
            'quantity' => $row['disponibilita'],
        );
    }

    $result_fidaty = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT codice, punti FROM tessera WHERE cliente = '".$_SESSION['id']."' and data_disattivazione is null";
    $result_fidaty = pg_query($db, $sql);
    if (!$result_fidaty) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $fidaty = pg_fetch_assoc($result_fidaty);


    if (isset($_POST) && isset($_GET['action']) && $_GET['action'] === 'purchase') {
        $result = pg_query($db, 'SET SEARCH_PATH TO myshop');

        $quantities = $_POST['Quantities'];
        $discount = $_POST['Discount'] ?? null;

        if (empty($quantities)) {
            $errors[] = "Devi selezionare almeno un prodotto da acquistare.";
        }

        if (empty($errors)) {
            $purchased_products = array();
            foreach ($quantities as $product => $qty) {
                if ($qty > 0 && isset($products[$product])) {
                    $purchased_products[] = [
                        'prodotto' => $product,
                        'quantita' => $qty,
                        'prezzo' => $products[$product]['price']
                    ];
                }
            }
            
            $json_purchase = json_encode($purchased_products);
            
            $sql = "SELECT myshop.nuovo_acquisto($1, $2, $3, $4)";
            $params = [
                $discount,
                $_GET['id'],
                $_SESSION['id'],
                $json_purchase 
            ];

            $result = pg_prepare($db, "ins_purchase", $sql);
            if (!$result) {
                $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
            }
            $result = pg_execute($db, "ins_purchase", $params);
            if (!$result) {
                $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
            }
        }
        if (empty($errors)) {
            $suc_msg = "Acquisto effettuato con successo!";
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

        <div class="container mt-4">
            <h2 class="mb-4">Acquista Prodotti da <?php echo $shop['nome']; ?> </h2>
            <?php if (empty($products)) { ?>
                <div class="alert alert-info" role="alert">Questo negozio non vende nessun prodotto</div>
            <?php } else { ?>
                <form method="post" action="<?php echo $_SERVER['PHP_SELF']?> ?id=<?php echo $_GET['id']; ?>&action=purchase">
                    <div class="row">
                        <p>Specifica la quantità dei prodotti che desideri acquistare.</p>
                        <div class="col-7 border-end border-secondary pe-5" style="--bs-border-opacity: .5;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Prezzo</th>
                                        <th>Disponibilità</th>
                                        <th>Seleziona</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $code => $product) { ?>
                                        <tr>
                                            <td><?php echo $product['name']; ?> </td>
                                            <td><?php echo $product['price']; ?> €</td>
                                            <td><?php echo $product['quantity']; ?></td>
                                            <td class="d-flex gap-2 justify-content-center">
                                                <input class="form-control" type="number" name="Quantities[<?php echo $code; ?>]" min="0" max="<?php echo (int)$product['quantity']; ?>" value="0" style="width: 60px;">
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                            <div class="d-flex flex-row-reverse">
                                <button type="button" class="btn btn-secondary ms-2" onclick="deselectAll()">Deseleziona tutti</button>
                            </div>
                        </div>
                        <div class="col-5 ps-5">
                            <h4>Vuoi usare uno sconto?</h4>
                            <?php 
                                if (empty($fidaty)) {
                            ?>
                            <div class="alert alert-info" role="alert">Non hai una tessera associata</div>
                            <?php
                                } else {
                            ?>
                            <p>Hai <strong><?php echo $fidaty['punti']; ?></strong> punti sulla tua tessera fedeltà.</p>
                            <ul class="list-group list-group-horizontal">
                                <?php
                                $discounts = [
                                    ['percent' => 5, 'points' => 100],
                                    ['percent' => 15, 'points' => 200],
                                    ['percent' => 30,  'points' => 300],
                                ];
                                foreach ($discounts as $idx => $discount) {
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>Sconto del <strong><?php echo $discount['percent']; ?>%</strong> con <strong><?php echo $discount['points']; ?></strong> punti</span>
                                        <input type="radio" name="Discount" value="<?php echo $discount['percent']; ?>" <?php echo ($fidaty['punti'] >= $discount['points']) ? '' : 'disabled'; ?>>
                                    </li>
                                <?php
                                } 
                                ?>
                            </ul>
                            <?php
                                }
                            ?>
                        </div>
                    </div>
                    <div class="d-flex flex-row-reverse mt-2">
                        <button type="submit" class="btn btn-primary">Acquista Selezionati</button>
                    </div>
                </form>
            <?php } ?>
        </div>
        <script>
            function deselectAll() {
                document.querySelectorAll('input[type="number"][name^="Quantities["]').forEach(function(input) {
                    input.value = 0;
                });
            }
        </script>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
    </body>
</html>