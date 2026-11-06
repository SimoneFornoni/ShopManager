<?php
//TODO: modificare utente con quello del prof
define ("myhost", env('MYHOST'));
define ("myuser", env('MYUSER'));
define ("mypsw", env('MYPSW'));
define ("mydb", env('MYDB'));

function open_pg_connection() {
    $connection = "host=".myhost." dbname=".mydb." user=".myuser." password=".mypsw;
    return pg_connect($connection);
}

function close_pg_connection($db) {
    return pg_close($db);
}
?>