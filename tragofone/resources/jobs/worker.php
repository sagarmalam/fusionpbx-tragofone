<?php
require_once __DIR__.'/bootstrap.php';

$factory = static function (array $tenant) use ($crypto): tragofone_client {
	return tragofone_customer_client_factory::create($tenant, $crypto);
};
$worker = new tragofone_worker($store, $factory, $crypto); $worker_id = gethostname().':'.getmypid();
while ($worker->run_once($worker_id)) { /* drain available jobs */ }
