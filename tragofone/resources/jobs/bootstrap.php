<?php

$document_root = dirname(__DIR__, 4);
require_once $document_root.'/resources/require.php';
require_once dirname(__DIR__).'/classes/bootstrap.php';

$database = new database();
$store = new tragofone_fusionpbx_store($database);
$crypto = tragofone_crypto::from_environment();
