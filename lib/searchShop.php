<?php
    include_once 'conf/conf.php';

    $suc_msg = "";
    $errors = array();
    $shops = array();

    $db = open_pg_connection();
    if (!$db) {
        $errors[] = "Errore di connessione al database: ".pg_last_error();
    }

    if (isset($_POST) && isset($_GET['action']) && $_GET['action'] === 'search') {
        $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
        $search = trim($_POST['Search']);

        if (empty($errors)) {

            $params = array('%'.$search.'%');
            $sql = "SELECT n.codice, n.citta , ns.*
                    FROM negozio_stats ns inner join negozio n on n.codice = ns.codice and n.data_eliminazione is null
                    WHERE n.citta ILIKE $1
                    ORDER BY nome asc";

            $result = pg_prepare($db, "search_shop", $sql);
            if (!$result) {
                $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
            }
            $result = pg_execute($db, "search_shop", $params);
            if (!$result) {
                $errors[]  = "Errore nell'esecuzione della query: ".pg_last_error($db);
            }

            while ($row = pg_fetch_assoc($result)) {
                $shops[$row['codice']] = array(
                    'name' => $row['nome'],
                    'city' => $row['citta'],
                    'n_products' => $row['numero_prodotti'],
                    'n_sales' => $row['numero_fatture'],
                    'avg_price' => $row['media_prezzi']
                );
            }
        }
    } else {
        $result = pg_query($db, 'SET SEARCH_PATH TO myshop');

        $sql = "SELECT n.codice, n.citta , ns.* 
                FROM negozio_stats ns inner join negozio n on n.codice = ns.codice and n.data_eliminazione is null
                ORDER BY nome asc";

        $result = pg_query($db, $sql);
        if (!$result) {
            $errors[]  = "Errore nell'esecuzione della query: ".pg_last_error($db);
        }

        while ($row = pg_fetch_assoc($result)) {
            $shops[$row['codice']] = array(
                'name' => $row['nome'],
                'city' => $row['citta'],
                'n_products' => $row['numero_prodotti'],
                'n_sales' => $row['numero_fatture'],
                'avg_price' => $row['media_prezzi']
            );
        }
    }

    close_pg_connection($db);
?>
<div class="container mt-4">
    <div class="row">
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
                            <p class="card-text my-0">Città: <?php echo $values['city'] ?></p>
                            <p class="card-text mt-1 mb-0">Vendite: <?php echo $values['n_sales'] ?></p>
                            <p class="card-text my-0">Prezzo Medio: <?php echo $values['avg_price'] !== null ? number_format($values['avg_price'], 2) : '0.00'; ?> €</p>
                        </div>
                
                        <a href="<?php echo 'buy.php?id='.$id?>" class="btn btn-primary">Seleziona</a>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>