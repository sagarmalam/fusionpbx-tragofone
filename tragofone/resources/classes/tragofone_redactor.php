<?php

final class tragofone_redactor {
	private const SENSITIVE_KEYS = ['password', 'token', 'authorization', 'sip_auth_password', 'encrypted_customer_password', 'encrypted_contact_password'];

	public static function data(array $input): array {
		$output = [];
		foreach ($input as $key => $value) {
			$is_sensitive = in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)
				|| str_contains(strtolower((string) $key), 'secret');
			$output[$key] = $is_sensitive ? '[REDACTED]' : (is_array($value) ? self::data($value) : $value);
		}
		return $output;
	}

	public static function message(string $message): string {
		return preg_replace('/(bearer\s+|password["\'=:\s]+|token["\'=:\s]+)[^\s,}"\']+/i', '$1[REDACTED]', $message) ?? '[REDACTED]';
	}
}
