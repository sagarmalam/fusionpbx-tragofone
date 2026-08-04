<?php

final class tragofone_tenant_identity_exception extends RuntimeException {}

final class tragofone_customer_client_factory {
	public static function create(array $tenant, tragofone_crypto $crypto, ?tragofone_http_transport $transport = null): tragofone_client {
		$url = tragofone_url_validator::validate((string) ($tenant['base_url'] ?? ''), false, false);
		$username = trim((string) ($tenant['customer_username'] ?? ''));
		$encrypted_password = (string) ($tenant['encrypted_customer_password'] ?? '');
		if ($username === '' || $encrypted_password === '') {
			throw new tragofone_tenant_configuration_exception('Tragofone company-admin credentials are incomplete.');
		}
		$client = new tragofone_client($url, $transport ?? new tragofone_curl_transport());
		$client->customer_login($username, $crypto->decrypt($encrypted_password));
		$identity = $client->customer_me();
		$actual = $identity['data']['cust_id'] ?? $identity['cust_id'] ?? null;
		if (!empty($tenant['expected_customer_id']) && (string) $actual !== (string) $tenant['expected_customer_id']) {
			throw new tragofone_tenant_identity_exception('Authenticated Tragofone customer identity does not match tenant configuration.');
		}
		return $client;
	}
}
