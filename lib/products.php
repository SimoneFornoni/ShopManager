<?php
    include_once 'conf/conf.php';
    $suc_msg = "";

    $errors = array();

    $db = open_pg_connection();
    if (!$db) {
        $errors[] = "Errore di connessione al database: ".pg_last_error();
    }

    $result = pg_query($db, 'SET SEARCH_PATH TO myshop');

    $sql = "SELECT p.descrizione, ps.* 
            FROM prodotto_stats ps inner join prodotto p on p.codice = ps.codice
            ORDER BY nome ASC";
    
    $result = pg_query($db, $sql);
    if (!$result) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $products = array();
    while ($row = pg_fetch_assoc($result)) {
        $products[$row['codice']] = array(
            'name' => $row['nome'],
            'description' => $row['descrizione'],
            'num_suppliers' => $row['numero_fornitori'],
            'avg_supplier_price' => $row['media_prezzo_fornitore'],
        );
    }

    if(isset($_POST) && isset($_GET['action']) && $_GET['action'] === 'insertProduct') {
        $params = [];
        $name = $_POST['Name'];
        $description = $_POST['Description'];

        if (empty($name)) {
            $errors[] = "Nome non può essere vuoto.";
        } else if (strlen($name) <= 2) {
            $errors[] = "Nome non valido";
        }
        
        if (empty($errors)) {
            $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
            $params =  Array(
                $name,
                $description
            );

            $sql = "INSERT INTO prodotto(nome, descrizione) VALUES ($1, $2)";
            
            $result = pg_prepare($db, "ins_product", $sql);
            if (!$result) {
                $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
            }
            $result = pg_execute($db, "ins_product", $params);
            if (!$result) {
                $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
            }
            
        } 
        if (empty($errors)) {
            $suc_msg = "Il prodotto è stato aggiunto con successo.";
        }
    }
    close_pg_connection($db);
    $pagelink = $_SERVER['PHP_SELF'].'?mod=products&action=insertProduct';
?>
<div class="container">
    <div class="d-flex justify-content-between mt-4">
        <h1>Gestisci i Prodotti</h1>
        <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addProduct">Nuovo Prodotto</button>
    </div>

    <div class="modal fade" id="addProduct" tabindex="-1" aria-labelledby="addProductLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="addProductLabel">Aggiungi un nuovo prodotto</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="container border border-2 p-4 rounded" action="<?php echo $pagelink; ?>" method='POST'>
                        <div class="d-flex gap-2 mb-3">
                            <input type="text" class="form-control w-50" name="Name" placeholder="Nome del Prodotto">
                        </div>
                        <div class="form-floating w-75">
                            <textarea class="form-control" placeholder="Leave a comment here" name="Description"></textarea>
                            <label for="floatingTextarea">Descrizione del Prodotto</label>
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
             <?php if (empty($products)) { ?>
                <div class="alert alert-info w-100" role="alert">Nessun prodotto presente.</div>
            <?php } else { ?>
                <?php 
                    foreach($products as $id=>$values){
                ?>
                <div class="card" style="width: 15rem;">
                    <div class="card-body">
                        <div class="d-flex">
                            <h5 class="card-title"><?php echo $values['name'] ?></h5>
                            <span class="badge bg-primary ms-auto py-2"><?php echo $values['num_suppliers'] ?></span>
                        </div>
                        <div class="my-2">
                            <p class="card-text my-1">Prezzo Medio: <?php echo $values['avg_supplier_price'] !== null ? number_format($values['avg_supplier_price'], 2) : '0.00'; ?> €</p>
                            <p class="card-text text-truncate my-0"><?php echo $values['description'] ?></p>
                        </div>
                        <a href="<?php echo 'manageProduct.php?id='.$id?>" class="btn btn-primary mt-1">Gestisci</a>
                    </div>
                </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>
