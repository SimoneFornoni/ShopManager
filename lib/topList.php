<?php
    include_once 'conf/conf.php';
    $errors = array();

    $db = open_pg_connection();
    if (!$db) {
        $errors[] = "Errore di connessione al database: ".pg_last_error();
    }

    $result_shop = pg_query($db, 'SET SEARCH_PATH TO myshop');

    $sql = "SELECT * FROM negozio_stats ORDER BY guadagno_totale DESC, numero_prodotti desc, nome asc LIMIT 5";
    
    $result_shop = pg_query($db, $sql);
    if (!$result_shop) {
        $errors[]  = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }

    $shops = array();
    while ($row = pg_fetch_assoc($result_shop)) {
        $shops[$row['codice']] = array(
            'name' => $row['nome'],
            'n_products' => $row['numero_prodotti'],
            'n_sales' => $row['numero_fatture'],
            'income' => $row['guadagno_totale'],
            'avg_price' => $row['media_prezzi']
        );
    }

    $result_supplier = pg_query($db, 'SET SEARCH_PATH TO myshop');

    $sql = "SELECT * FROM fornitore_stats ORDER BY numero_ordini DESC, numero_prodotti desc, nome asc LIMIT 5";
    
    $result_supplier = pg_query($db, $sql);
    if (!$result_supplier) {
        $errors[]  = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }

    $suppliers = array();
    while ($row = pg_fetch_assoc($result_supplier)) {
        $suppliers[$row['codice']] = array(
            'name' => $row['nome'],
            'n_products' => $row['numero_prodotti'],
            'n_orders' => $row['numero_ordini'],
            'avg_price' => $row['media_prezzi']
        );
    }

    close_pg_connection($db);
?>
<div class="container mt-4">
    <div class="row">
        <h2 class="ps-0">Negozi Migliori</h2>
        <?php if (empty($shops)) { ?>
            <div class="alert alert-info w-100" role="alert">Nessun negozio presente.</div>
        <?php } else { ?>
            <div class="d-flex flex-wrap gap-5 mt-3">
            <?php 
            foreach($shops as $id=>$values){
            ?>
                <div class="card" style="width: 15rem;">
                    <div class="card-body">
                        <div class="d-flex">
                            <h5 class="card-title"><?php echo $values['name'] ?></h5>
                            <span class="badge bg-primary ms-auto py-2"><?php echo $values['n_products'] ?></span>
                        </div>
                        <div class="my-2">
                            <p class="card-text my-0">Vendite: <?php echo $values['n_sales'] ?></p>
                            <p class="card-text my-0">Guadagno Totale: <?php echo $values['income'] !== null ? number_format($values['income'], 2) : '0.00'; ?> €</p>
                            <p class="card-text my-0">Prezzo Medio: <?php echo $values['avg_price'] !== null ? number_format($values['avg_price'], 2) : '0.00'; ?> €</p>
                        </div>
                
                        <a href="<?php echo 'manageShop.php?id='.$id?>" class="btn btn-primary">Gestisci</a>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
    <div class="row mt-5">
        <h2 class="ps-0">Fornitori Migliori</h2>
        <?php if (empty($suppliers)) { ?>
            <div class="alert alert-info w-100" role="alert">Nessun fornitore presente.</div>
        <?php } else { ?>
            <div class="d-flex flex-wrap gap-5 mt-3">
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