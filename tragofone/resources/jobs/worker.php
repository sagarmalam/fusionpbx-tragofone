<?php
require_once __DIR__.'/bootstrap.php';

$factory = static function (array $tenant) use ($crypto): tragofone_client {
	$url = tragofone_url_validator::validate($tenant['base_url'], false, false);
	$client = new tragofone_client($url, new tragofone_curl_transport());
	$client->customer_login($tenant['customer_username'], $crypto->decrypt($tenant['encrypted_customer_password']));
	$identity = $client->customer_me(); $actual = $identity['data']['cust_id'] ?? $identity['cust_id'] ?? null;
	if (!empty($tenant['expected_customer_id']) && (string) $actual !== (string) $tenant['expected_customer_id']) {
		throw new tragofone_tenant_identity_exception('Authenticated Tragofone customer identity does not match tenant configuration.');
	}
	return $client;
};
$worker = new tragofone_worker($store, $factory); $worker_id = gethostname().':'.getmypid();
while ($worker->run_once($worker_id)) { /* drain available jobs */ }
