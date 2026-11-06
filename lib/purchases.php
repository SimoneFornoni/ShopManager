<?php
    include_once 'conf/conf.php';

    $suc_msg = "";
    $errors = array();

    $db = open_pg_connection();
    if (!$db) {
        $errors[] = "Errore di connessione al database: ".pg_last_error();
    }
    $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
    $sql = "SELECT * FROM storico_acquisti('".$_SESSION['id']."')";
    $result = pg_query($db, $sql);
    if (!$result) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $purchases = array();
    while ($row = pg_fetch_assoc($result)) {
        $purchases[] = array(
            'spesa' => $row['spesa'],
            'nome_negozio' => $row['nome_negozio'],
            'data_emissione' => $row['data_emissione'],
            'prodotti' => json_decode($row['prodotti'], true)
        );
    }
?>
<div class="container mt-4">
    <h2>Storico Acquisti</h2>
    <?php if (empty($purchases)) { ?>
        <div class="alert alert-info" role="alert"> Non hai effettuato acquisti. </div>
    <?php } else { ?>
        <div class="table-responsive mt-4" style="max-height: 400px; overflow-y: auto;">
            <table class="table table-striped">
                <thead class="sticky-top">
                    <tr>
                        <th scope="col">Spesa</th>
                        <th scope="col">Negozio</th>
                        <th scope="col">Data Emissione</th>
                        <th scope="col">Prodotti</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($purchases as $purchase) { ?>
                        <tr>
                            <td><?php echo $purchase['spesa']; ?></td>
                            <td><?php echo $purchase['nome_negozio']; ?></td>
                            <td><?php echo $purchase['data_emissione']; ?></td>
                            <td>
                                <ul>
                                    <?php foreach ($purchase['prodotti'] as $product) { ?>
                                        <li><?php echo $product['nome'] . " - " . $product['quantita'] . " x " . $product['prezzo'] . " €"; ?></li>
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