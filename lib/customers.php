<?php
    include_once 'conf/conf.php';
    $suc_msg = "";

    $errors = array();

    $db = open_pg_connection();
    if (!$db) {
        $errors[] = "Errore di connessione al database: ".pg_last_error();
    }

    $result_customers = pg_query($db, 'SET SEARCH_PATH TO myshop');

    $sql = "SELECT * from clienti_tessera";
    
    $result_customers = pg_query($db, $sql);
    if (!$result_customers) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $customers = array();
    while ($row = pg_fetch_assoc($result_customers)) {
        $customers[$row['codice']] = array(
            'username' => $row['username'],
            'email' => $row['email'],
            'cf' => $row['cf'],
            'points' => $row['punti'],
            'shop' => $row['negozio'],
            'release_date' => $row['data_rilascio']
        );
    }

    $result_shops = pg_query($db, 'SET SEARCH_PATH TO myshop');

    $sql = "SELECT codice, nome FROM negozio where data_eliminazione is null order by nome ASC";
    
    $result_shops = pg_query($db, $sql);
    if (!$result_shops) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $shops = array();
    while ($row = pg_fetch_assoc($result_shops)) {
        $shops[$row['codice']] = array(
            'name' => $row['nome'] 
        );
    }

    $result_fidaty = pg_query($db, 'SET SEARCH_PATH TO myshop');

    $sql = "SELECT username, punti FROM mv_clienti_300_punti";
    
    $result_fidaty = pg_query($db, $sql);
    if (!$result_fidaty) {
        $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
    }
    $fidaties = array();
    while ($row = pg_fetch_assoc($result_fidaty)) {
        $fidaties[] = array(
            'username' => $row['username'],
            'points' => $row['punti']
        );
    }

    if (empty($errors)) {

        if (isset($_POST) && isset($_GET['action']) && $_GET['action'] === 'insertCustomer') {
            $params = [];
            $username = $_POST['UsernameCustomer'];
            $email = strtolower($_POST['EmailCustomer']);
            $password = $_POST['PasswordCustomer'];
            $cf = strtoupper($_POST['CfCustomer']);

            if (empty($username)) {
                $errors[] = "Username non può essere vuoto.";
            } else if (strlen($username) <= 2) {
                $errors[] = "Username non valido";
            }
            if (empty($email)) {
                $errors[] = "Email non può essere vuoto.";
            } else if (!preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/', $email)) {
                $errors[] = "Email non valida.";
            }
            if (empty($password)) {
                $errors[] = "Password non può essere vuoto.";
            }
            if (empty($cf)) {
                $errors[] = "Codice fiscale non può essere vuoto.";
            } else if (!preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $cf)) {
                $errors[] = "Codice fiscale non valido.";
            }

            if (empty($errors)) {
                $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
                $params =  Array(
                    $username,
                    $email,
                    $password,
                    $cf
                );

                $sql = "INSERT INTO cliente(username, email, password, cf) VALUES ($1, $2, $3, $4)";
                
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
                $suc_msg = "Il cliente è stato aggiunto con successo.";
            }
        }

        if(isset($_POST) && isset($_GET['action']) && $_GET['action'] === 'updateCustomer') {
            $input = array(
                'id' => $_POST['Customer'],
                'username' => $_POST['UsernameCustomer'],
                'email' => strtolower($_POST['EmailCustomer']),
                'cf' => strtoupper($_POST['CfCustomer'])          
            );

            if (empty($input['id'])) {
                $errors[] = "Seleziona un cliente da modificare.";
            }
            if (strlen($input['username']) <= 2) {
                $errors[] = "Username non valido";
            }
            if ($input['email'] !== '' && !preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/', $input['email'])) {
                $errors[] = "Email non valida.";
            }
            if ($input['cf'] !== '' && !preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $input['cf'])) {
                $errors[] = "Codice fiscale non valido.";
            }

            if (empty($errors)) {
                $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
                $params = array(
                    $input['id'],
                );

                /* $sql = "UPDATE cliente SET username = $2, email = $3, cf = $4 WHERE codice = $1"; */
                $sql = "UPDATE cliente SET";
                $count = 2;
                foreach (array_slice($input, 1, null, true) as $key => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $params[] = $value;
                    $sql .= " $key = \$" . $count . ",";
                    $count++;
                }
                $sql = rtrim($sql, ',')." WHERE codice = \$1";

                $result = pg_prepare($db, "upd_customer", $sql);
                if (!$result) {
                    $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
                }
                $result = pg_execute($db, "upd_customer", $params);
                if (!$result) {
                    $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
                } else {
                    header("Location: homeManager.php");
                    exit();
                }
            }
            if (empty($errors)) {
                $suc_msg = "La modifica del cliente è stata effettuata con successo.";
            }
        }

        if (isset($_POST) && isset($_GET['action']) && $_GET['action'] === 'deleteCustomer') {
            $id = $_POST['Customer'];

            if (empty($id)) {
                $errors[] = "Seleziona un cliente da eliminare.";
            }

            if (empty($errors)) {
                $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
                $sql = "select * from soft_delete('".$id."')";
                $result = pg_query($db, $sql);
                if (!$result) {
                    $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
                } else {
                    $result = pg_fetch_assoc($result);
                    if ($result['soft_delete'] === 'f') {
                        $errors[] = "Errore nell'eliminazione del cliente: ".pg_last_error($db);
                    }  
                }
            }
            if (empty($errors)) {
                $suc_msg = "Il cliente è stato eliminato con successo.";
                close_pg_connection($db);
                header("Location: homeManager.php");
                exit();
            }
        }

        if (isset($_POST) && isset($_GET['action']) && $_GET['action'] === 'insertFidaty') {
            $params = [];
            $customerId = $_POST['CustomerFidaty'];
            $shop = $_POST['Shop'];

            if (empty($customerId)) {
                $errors[] = "Seleziona un cliente.";
            }
            if (empty($shop)) {
                $errors[] = "Inserisci il nome del negozio.";
            } else if (strlen($shop) <= 2) {
                $errors[] = "Nome negozio non valido";  
            }

            if (empty($errors)) {
                $result = pg_query($db, 'SET SEARCH_PATH TO myshop');
                $params =  Array(
                    $customerId,
                    $shop
                );

                $sql = "INSERT INTO tessera(cliente, negozio) VALUES ($1, $2)";
                
                $result = pg_prepare($db, "ins_fidaty", $sql);
                if (!$result) {
                    $errors[] = "Errore nella preparazione della query: ".pg_last_error($db);
                }
                $result = pg_execute($db, "ins_fidaty", $params);
                if (!$result) {
                    $errors[] = "Errore nell'esecuzione della query: ".pg_last_error($db);
                }
            } 
            if (empty($errors)) {
                $suc_msg = "La tessera è stato aggiunto con successo.";
                header("Location: homeManager.php");
                exit();
            }
        }
    }
    close_pg_connection($db);
    $pagelink = $_SERVER['PHP_SELF'].'?mod=customers';
?>
<div class="container">

    <div class="d-flex justify-content-between mt-4">
        <h1>Gestisci i Clienti</h1>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#insertCustomer">Nuovo Cliente</button>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateCustomer">Modifica Cliente</button>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteCustomer">Delete Cliente</button>
        </div>
    </div>

    <!-- Insert Client Modal -->
    <div class="modal fade" id="insertCustomer" tabindex="-1" aria-labelledby="insertCustomerLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="insertCustomerLabel">Aggiungi un nuovo cliente</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="container border border-2 p-4 rounded" action="<?php echo $pagelink."&action=insertCustomer"; ?>" method='POST'>
                        <div class="d-flex gap-2 mb-3">
                            <input type="text" class="form-control" name="UsernameCustomer" placeholder="Username">
                            <input type="text" class="form-control" name="CfCustomer" placeholder="Codice Fiscale">
                        </div>
                        <div class="mb-3">
                            <input type="email" class="form-control" name="EmailCustomer" placeholder="Email">
                        </div>
                        <div class="mb-3">
                            <input type="password" class="form-control" name="PasswordCustomer" placeholder="Password">
                        </div>
                        <div class="d-flex flex-row-reverse">
                            <button type="submit" class="btn btn-primary mt-3">Aggiungi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Client Modal -->
    <div class="modal fade" id="updateCustomer" tabindex="-1" aria-labelledby="updateCustomerLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="updateCustomerLabel">Modifica cliente</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form class="container border border-2 p-4 rounded" action="<?php echo $pagelink; ?>&action=updateCustomer" method='POST'>
                        <select class="form-control mb-4" name="Customer" required>
                            <option value="" selected disabled>Seleziona il cliente che vuoi modificare</option>
                            <?php foreach ($customers as $id => $customer) { ?>
                                <option value="<?php echo $id; ?>"><?php echo $customer['username']; ?></option>
                            <?php } ?>
                        </select>
                        <div class="d-flex gap-2 mb-3">
                            <input type="text" class="form-control" name="UsernameCustomer" placeholder="Username">
                            <input type="text" class="form-control" name="CfCustomer" placeholder="Codice Fiscale">
                        </div>
                        <div class="mb-3">
                            <input type="email" class="form-control" name="EmailCustomer" placeholder="Email">
                        </div>
                        <div class="d-flex flex-row-reverse">
                            <button type="submit" class="btn btn-primary mt-3">Modifica</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Client Modal -->
    <div class="modal fade" id="deleteCustomer" tabindex="-1" aria-labelledby="deleteCustomerLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="deleteCustomerLabel">Vuoi davvero eliminare un cliente</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="<?php echo $pagelink; ?>&action=deleteCustomer">
                        <select class="form-control mb-4 w-100" name="Customer" required>
                            <option value="" selected disabled>Seleziona il cliente che vuoi eliminare</option>
                            <?php foreach ($customers as $id => $customer) { ?>
                                <option value="<?php echo $id; ?>"><?php echo $customer['username']; ?></option>
                            <?php } ?>
                        </select>
                        <div class="d-flex flex-row-reverse">
                            <button type="submit" class="btn btn-outline-danger">Delete</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="d-flex flex-wrap gap-5 mt-3">
             <?php if (empty($customers)) { ?>
                <div class="alert alert-info w-100" role="alert">Nessun cliente presente.</div>
            <?php } else { ?>
                <table class="table table-striped ">
                    <thead class="sticky-top">
                        <tr class="text-center">
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Codice Fiscale</th>
                            <th>Punti Tessera</th>
                            <th>Emittente Tessera</th>
                            <th>Data Rilascio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $id => $customer) { ?>
                            <tr class="text-center">
                                <td><?php echo $customer['username']; ?></td>
                                <td><?php echo $customer['email']; ?> </td>
                                <td><?php echo $customer['cf']; ?> </td>
                                <td><?php echo $customer['points'] !== null ? $customer['points'] : '-'; ?> </td>
                                <td>
                                    <?php
                                        if ($customer['shop'] !== null && isset($shops[$customer['shop']])) {
                                            echo $shops[$customer['shop']]['name'];
                                        } else {
                                            echo '-';
                                        }
                                    ?>
                                </td>
                                <td><?php echo $customer['release_date'] !== null ? $customer['release_date'] : '-'; ?> </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    </div>

    <hr class="my-4">

    <div class="d-flex flex-row-reverse mb-3">
        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#insertFidaty">Aggiungi Tessera Fidaty</button>
    </div>

    <div class="row">
        <h4>Utenti con più di 300 punti sulla Tessera</h4>
        <div class="mt-3 "></div>
            <?php if (empty($fidaties)) { ?>
                <div class="alert alert-info w-100" role="alert">Nessuna cliente ha almeno 300 punti sulla tessera</div>
            <?php } else { ?>
                <table class="table table-striped w-25">
                    <thead class="sticky-top">
                        <tr>
                            <th>Username</th>
                            <th>Punti</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fidaties as $fidaty) { ?>
                            <tr>
                                <td><?php echo $fidaty['username']; ?></td>
                                <td><?php echo $fidaty['points']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    </div>
                
    <!-- Insert Fidaty Modal -->
    <div class="modal fade" id="insertFidaty" tabindex="-1" aria-labelledby="insertFidatyLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="insertFidatyLabel">Aggiungi Tessera Fidaty</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="<?php echo $pagelink."&action=insertFidaty"; ?>">
                        <div class="mb-3">
                            <label for="CustomerFidaty" class="form-label">Cliente</label>
                            <select class="form-control" name="CustomerFidaty" id="CustomerFidaty" required>
                                <option value="" selected disabled>Seleziona cliente</option>
                                <?php foreach ($customers as $id => $customer) { ?>
                                    <?php if ($customer['points'] === null && $customer['shop'] === null && $customer['release_date'] === null) { ?>
                                        <option value="<?php echo $id; ?>"><?php echo $customer['username']; ?></option>
                                    <?php } ?>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="Shop" class="form-label">Negozio</label>
                            <select class="form-control" name="Shop" id="Shop" required>
                                <option value="" selected disabled>Seleziona negozio</option>
                                <?php foreach ($shops as $id => $shop) { ?>
                                    <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($shop['name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="d-flex flex-row-reverse">
                            <button type="submit" class="btn btn-primary">Aggiungi Tessera</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
