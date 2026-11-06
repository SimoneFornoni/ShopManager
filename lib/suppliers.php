<?php
    include_once 'conf/conf.php';
    $suc_msg = "";

    $errors = array();

    $db = open_pg_connection();
    if (!$db) {
        $errors[] = "Errore di connessione al database: ".pg_last_error();
    }

    $result = pg_query($db, 'SET SEARCH_PATH TO myshop');

    $sql = "SELECT * FROM fornitore_stats ORDER BY nome asc";
    
    $result = pg_query($db, $sql);
    if (!$result) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $suppliers = array();
    while ($row = pg_fetch_assoc($result)) {
        $suppliers[$row['codice']] = array(
            'name' => $row['nome'],
            'n_products' => $row['numero_prodotti'],
            'n_orders' => $row['numero_ordini'],
            'avg_price' => $row['media_prezzi']
        );
    }

    if(isset($_POST) && isset($_GET['action']) && $_GET['action'] === 'insertSupplier') {
        $errors =[];
        $params = [];

        $name = $_POST['Name'];
        $pIva = $_POST['PIva'];
        $street = $_POST['Street'];
        $streetNumber = $_POST['StreetNumber'];
        $city = $_POST['City'];
        $cap = $_POST['Cap'];

        if (empty($name)) {
            $errors[] = "Nome non può essere vuoto.";
        } else if (strlen($name) <= 2) {
            $errors[] = "Nome non valido";
        }
        if (empty($pIva)) {
            $errors[] = "Partita Iva non può essere vuoto.";
        } else if (!preg_match('/^[0-9]{11}$/', $pIva)) {
            $errors[] = "Partita IVA non valido";
        }
        if (empty($street)) {
            $errors[] = "Via non può essere vuoto.";
        }
        if (empty($streetNumber)) {
            $errors[] = "Numero civico non può essere vuoto.";
        } else if (!preg_match('/^[0-9]{1,3}[A-Z]?$/i', $streetNumber)) {
            $errors[] = "Numero civico non valido.";
        }
        if (empty($city)) {
            $errors[] = "Città non può essere vuoto.";
        }
        if (empty($cap)) {
            $errors[] = "Cap non può essere vuoto.";
        } else if (!preg_match('/^[0-9]{5}$/', $cap)) {
            $errors[] = "Cap non valido.";
        }
        
        if (empty($errors)) {
            $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
            $params =  Array(
                $name,
                $pIva,
                $street,
                $streetNumber,
                $cap,
                $city
            );

            $sql = "INSERT INTO fornitore(nome, piva, via, civico, cap, citta) VALUES ($1, $2, $3, $4, $5, $6)";
            
            $result = pg_prepare($db, "ins_supplier", $sql);
            if (!$result) {
                $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
            }
            $result = pg_execute($db, "ins_supplier", $params);
            if (!$result) {
                $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
            }
        }
        if (empty($errors)) {
            $suc_msg = "Il fornitore è stato aggiunto con successo.";
        }
    }
    close_pg_connection($db);
    $pagelink = $_SERVER['PHP_SELF'].'?mod=suppliers&action=insertSupplier';
?>
<div class="container">
    <div class="d-flex justify-content-between mt-4">
        <h1>Gestisci i Fornitori</h1>
        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addSupplier">Nuovo Fornitore</button>
    </div>

    <div class="modal fade" id="addSupplier" tabindex="-1" aria-labelledby="addSupplierLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="addSupplierLabel">Aggiungi un nuovo fornitore</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="container border border-2 p-4 rounded" action="<?php echo $pagelink; ?>" method='POST'>
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
                            <button type="submit" class="btn btn-primary mt-3">Aggiungi</button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="d-flex flex-wrap gap-5 mt-3">
             <?php if (empty($suppliers)) { ?>
                <div class="alert alert-info w-100" role="alert">Nessun fornitore presente.</div>
            <?php } else { ?>
                <?php 
                    foreach($suppliers as $id=>$values){
                ?>
                <div class="card" style="width: 15rem;">
                    <div class="card-body">
                        <div class="d-flex">
                            <h5 class="card-title"><?php echo $values['name'] ?></h5>
                            <span class="badge bg-primary ms-auto py-2"><?php echo $values['n_products'] ?></span>
                        </div>
                        <div class="my-2">
                            <p class="card-text my-0">Vendite: <?php echo $values['n_orders'] ?></p>
                            <p class="card-text my-0">Prezzo Medio: <?php echo $values['avg_price'] !== null ? number_format($values['avg_price'], 2) : '0.00'; ?> €</p>
                        </div>
                        
                        <a href="<?php echo 'manageSupplier.php?id='.$id?>" class="btn btn-primary">Gestisci</a>
                    </div>
                </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>
