<?php

final class tragofone_config {
	public static function resolve(array $global, array $tenant): array {
		$resolved = $tenant;
		if (!empty($tenant['inherit_global_url'])) { $resolved['base_url'] = $global['base_url'] ?? null; }
		if (!empty($tenant['inherit_global_credentials'])) {
			$resolved['customer_username'] = $global['customer_username'] ?? null;
			$resolved['encrypted_customer_password'] = $global['encrypted_customer_password'] ?? null;
		}
		foreach (['base_url', 'customer_username', 'encrypted_customer_password'] as $required) {
			if (empty($resolved[$required])) { throw new InvalidArgumentException("Missing tenant configuration: {$required}"); }
		}
		$resolved['voicemail_code'] = $resolved['voicemail_code'] ?? $global['voicemail_code'] ?? '*97';
		$resolved['sip_port'] = (int) ($resolved['sip_port'] ?? $global['sip_port'] ?? 5061);
		$resolved['sip_protocol'] = strtolower((string) ($resolved['sip_protocol'] ?? $global['sip_protocol'] ?? 'tls'));
		return $resolved;
	}
}
