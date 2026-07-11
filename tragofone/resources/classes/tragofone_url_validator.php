<?php

final class tragofone_url_validator {
	public static function validate(string $url, bool $allow_private = false, bool $allow_http = false): string {
		$url = rtrim(trim($url), '/');
		$parts = parse_url($url);
		if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
			throw new InvalidArgumentException('A valid Tragofone base URL is required.');
		}
		if (!$allow_http && strtolower($parts['scheme']) !== 'https') {
			throw new InvalidArgumentException('HTTPS is required.');
		}
		if (isset($parts['user']) || isset($parts['pass'])) {
			throw new InvalidArgumentException('Embedded URL credentials are not allowed.');
		}
		$addresses = gethostbynamel($parts['host']) ?: [];
		foreach ($addresses as $address) {
			if (!$allow_private && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
				throw new InvalidArgumentException('Private and reserved Tragofone destinations require explicit approval.');
			}
		}
		return $url;
	}
}
