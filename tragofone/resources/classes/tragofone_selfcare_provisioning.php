<?php

final class tragofone_selfcare_provisioning {
	public static function account(tragofone_store $store, tragofone_crypto $crypto, array $tenant, array $extension): ?array {
		$domain_uuid = (string) $tenant['domain_uuid']; $extension_uuid = (string) $extension['extension_uuid'];
		if (!tragofone_normalizer::boolean($tenant['selfcare_enabled'] ?? false)) {
			$store->revoke_selfcare_subject($domain_uuid, $extension_uuid); return null;
		}
		$base_url = tragofone_url_validator::validate((string) ($tenant['selfcare_base_url'] ?? ''));
		$subject = $store->selfcare_subject($domain_uuid, $extension_uuid); $rotate = $subject === null || !tragofone_normalizer::boolean($subject['active'] ?? false);
		if (!$rotate) {
			$existing_salt = $crypto->decrypt((string) $subject['encrypted_salt']);
			if (!preg_match('/^[a-f0-9]{32}$/', $existing_salt)) { $store->revoke_selfcare_subject($domain_uuid, $extension_uuid); $rotate = true; }
		}
		if ($rotate) {
			// Hex keeps the complete 128-bit random value while avoiding URL-parser
			// differences around base64url '-' and '_' characters in older clients.
			$salt = bin2hex(random_bytes(16));
			$subject = [
				'subject_uuid' => $subject['subject_uuid'] ?? tragofone_scanner::uuid(), 'domain_uuid' => $domain_uuid,
				'extension_uuid' => $extension_uuid, 'encrypted_salt' => $crypto->encrypt($salt), 'active' => true,
				'brand_version' => max(1, (int) ($tenant['selfcare_brand_version'] ?? 1)),
				'insert_date' => $subject['insert_date'] ?? gmdate('c'), 'update_date' => gmdate('c'),
			];
		} else {
			$salt = $crypto->decrypt((string) $subject['encrypted_salt']);
			$subject['active'] = true; $subject['brand_version'] = max(1, (int) ($tenant['selfcare_brand_version'] ?? 1)); $subject['update_date'] = gmdate('c');
		}
		$theme_config = $tenant;
		$theme_config['selfcare_brand_logo_url'] = self::logo_url($base_url, $subject['brand_version'], !empty($tenant['selfcare_brand_logo_base64']));
		$theme = tragofone_selfcare_theme::from_config($theme_config);
		tragofone_selfcare_theme::validate_logo_url($theme['brand_logo'], $base_url);
		$store->save_selfcare_subject($subject);
		return ['myaccount_status' => 'TRUE', 'myaccount_url' => tragofone_selfcare_theme::account_url($base_url, $subject['subject_uuid'], $salt, $theme)];
	}

	private static function logo_url(string $base_url, int $version, bool $available): string {
		return $available ? rtrim($base_url, '/').'/logo.php?v='.$version : '';
	}
}
