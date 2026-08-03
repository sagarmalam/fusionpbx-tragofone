<?php

final class tragofone_tenant_configuration_exception extends InvalidArgumentException {}

final class tragofone_config {
	public static function resolve(array $global, array $tenant): array {
		$resolved = $tenant;
		if (tragofone_normalizer::boolean($tenant['inherit_global_url'] ?? false)) { $resolved['base_url'] = $global['base_url'] ?? null; }
		if (tragofone_normalizer::boolean($tenant['inherit_global_credentials'] ?? false)) {
			$resolved['customer_username'] = $global['customer_username'] ?? null;
			$resolved['encrypted_customer_password'] = $global['encrypted_customer_password'] ?? null;
		}
		foreach (['base_url', 'customer_username', 'encrypted_customer_password'] as $required) {
			if (empty($resolved[$required])) { throw new tragofone_tenant_configuration_exception("Missing tenant configuration: {$required}"); }
		}
		$resolved['voicemail_code'] = $resolved['voicemail_code'] ?? $global['voicemail_code'] ?? '*97';
		$resolved['sip_port'] = (int) ($resolved['sip_port'] ?? $global['sip_port'] ?? 5061);
		$resolved['sip_protocol'] = strtolower((string) ($resolved['sip_protocol'] ?? $global['sip_protocol'] ?? 'tls'));
		// Self-care branding is intentionally global. Tenant records cannot override it.
		foreach ([
			'selfcare_enabled', 'selfcare_base_url', 'selfcare_brand_name', 'selfcare_brand_logo_base64',
			'selfcare_brand_logo_mime', 'selfcare_light_background', 'selfcare_light_foreground',
			'selfcare_light_button', 'selfcare_light_button_foreground', 'selfcare_dark_background',
			'selfcare_dark_foreground', 'selfcare_dark_button', 'selfcare_dark_button_foreground',
			'selfcare_brand_version', 'selfcare_external_forwarding', 'selfcare_external_prefixes',
			'selfcare_session_idle_seconds', 'selfcare_session_absolute_seconds',
		] as $field) { $resolved[$field] = $global[$field] ?? null; }
		return $resolved;
	}
}
