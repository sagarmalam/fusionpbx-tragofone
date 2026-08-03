<?php

final class tragofone_selfcare_theme {
	public const DEFAULTS = [
		'selfcare_brand_name' => 'Tragofone',
		'selfcare_light_background' => 'F7F8FA', 'selfcare_light_foreground' => '172033',
		'selfcare_light_button' => '1769E0', 'selfcare_light_button_foreground' => 'FFFFFF',
		'selfcare_dark_background' => '10141D', 'selfcare_dark_foreground' => 'F4F7FB',
		'selfcare_dark_button' => '6EA8FE', 'selfcare_dark_button_foreground' => '08101F',
	];

	private const URL_FIELDS = [
		'brand_name', 'brand_logo', 'l_bg', 'l_fg', 'l_btn', 'l_btn_fg',
		'd_bg', 'd_fg', 'd_btn', 'd_btn_fg', 'brand_v',
	];

	public static function from_config(array $config): array {
		$config = array_replace(self::DEFAULTS, $config);
		$name = trim((string) $config['selfcare_brand_name']);
		if ($name === '' || mb_strlen($name) > 40 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
			throw new InvalidArgumentException('Portal name must contain 1 to 40 printable characters.');
		}
		$theme = [
			'brand_name' => $name,
			'brand_logo' => trim((string) ($config['selfcare_brand_logo_url'] ?? '')),
			'l_bg' => self::color($config['selfcare_light_background']),
			'l_fg' => self::color($config['selfcare_light_foreground']),
			'l_btn' => self::color($config['selfcare_light_button']),
			'l_btn_fg' => self::color($config['selfcare_light_button_foreground']),
			'd_bg' => self::color($config['selfcare_dark_background']),
			'd_fg' => self::color($config['selfcare_dark_foreground']),
			'd_btn' => self::color($config['selfcare_dark_button']),
			'd_btn_fg' => self::color($config['selfcare_dark_button_foreground']),
			'brand_v' => max(1, (int) ($config['selfcare_brand_version'] ?? 1)),
		];
		if (self::contrast($theme['l_bg'], $theme['l_fg']) < 4.5 || self::contrast($theme['l_btn'], $theme['l_btn_fg']) < 4.5
			|| self::contrast($theme['d_bg'], $theme['d_fg']) < 4.5 || self::contrast($theme['d_btn'], $theme['d_btn_fg']) < 4.5) {
			throw new InvalidArgumentException('Theme text and button color pairs must meet WCAG AA contrast (4.5:1).');
		}
		return $theme;
	}

	public static function color(mixed $value): string {
		$value = strtoupper(ltrim(trim((string) $value), '#'));
		if (!preg_match('/^[0-9A-F]{6}$/', $value)) { throw new InvalidArgumentException('Theme colors must use six hexadecimal digits.'); }
		return $value;
	}

	public static function contrast(string $first, string $second): float {
		$luminance = static function (string $hex): float {
			$values = [];
			foreach ([0, 2, 4] as $offset) {
				$value = hexdec(substr($hex, $offset, 2)) / 255;
				$values[] = $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
			}
			return 0.2126 * $values[0] + 0.7152 * $values[1] + 0.0722 * $values[2];
		};
		$a = $luminance(self::color($first)); $b = $luminance(self::color($second));
		return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
	}

	public static function account_url(string $base_url, string $subject_uuid, string $salt, array $theme): string {
		$base_url = rtrim($base_url, '/').'/launch.php';
		$payload = ['scid' => $subject_uuid, ...array_intersect_key($theme, array_flip(self::URL_FIELDS))];
		$payload['brand_sig'] = self::sign($subject_uuid, $salt, $payload);
		$payload['tragofone_salt'] = $salt;
		return $base_url.'?'.http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
	}

	public static function sign(string $subject_uuid, string $salt, array $payload): string {
		$key = hash_hkdf('sha256', $salt, 32, 'tragofone-selfcare-branding-v1');
		return self::base64url(hash_hmac('sha256', self::canonical($subject_uuid, $payload), $key, true));
	}

	public static function verify(string $subject_uuid, string $salt, array $payload, string $signature): bool {
		return $signature !== '' && hash_equals(self::sign($subject_uuid, $salt, $payload), $signature);
	}

	public static function launch_theme(array $query): array {
		$theme = [];
		foreach (self::URL_FIELDS as $field) {
			if (!array_key_exists($field, $query) || is_array($query[$field])) { throw new InvalidArgumentException('The signed branding payload is incomplete.'); }
			$theme[$field] = (string) $query[$field];
		}
		return self::from_config([
			'selfcare_brand_name' => $theme['brand_name'], 'selfcare_brand_logo_url' => $theme['brand_logo'],
			'selfcare_light_background' => $theme['l_bg'], 'selfcare_light_foreground' => $theme['l_fg'],
			'selfcare_light_button' => $theme['l_btn'], 'selfcare_light_button_foreground' => $theme['l_btn_fg'],
			'selfcare_dark_background' => $theme['d_bg'], 'selfcare_dark_foreground' => $theme['d_fg'],
			'selfcare_dark_button' => $theme['d_btn'], 'selfcare_dark_button_foreground' => $theme['d_btn_fg'],
			'selfcare_brand_version' => $theme['brand_v'],
		]);
	}

	public static function signed_payload(array $query): array {
		$payload = [];
		foreach (self::URL_FIELDS as $field) { $payload[$field] = isset($query[$field]) && !is_array($query[$field]) ? (string) $query[$field] : ''; }
		return ['scid' => isset($query['scid']) && !is_array($query['scid']) ? (string) $query['scid'] : '', ...$payload];
	}

	public static function validate_logo_url(string $logo_url, string $portal_base_url): void {
		if ($logo_url === '') { return; }
		$logo = parse_url($logo_url); $base = parse_url($portal_base_url);
		if ($logo === false || $base === false || strtolower((string) ($logo['scheme'] ?? '')) !== 'https'
			|| strtolower((string) ($logo['host'] ?? '')) !== strtolower((string) ($base['host'] ?? ''))
			|| (int) ($logo['port'] ?? 443) !== (int) ($base['port'] ?? 443)) {
			throw new InvalidArgumentException('Brand logo URL must be HTTPS and use the portal host.');
		}
	}

	private static function canonical(string $subject_uuid, array $payload): string {
		$lines = ['scid='.$subject_uuid];
		foreach (self::URL_FIELDS as $field) { $lines[] = $field.'='.(string) ($payload[$field] ?? ''); }
		return implode("\n", $lines);
	}

	private static function base64url(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
}
