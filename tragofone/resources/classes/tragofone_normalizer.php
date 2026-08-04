<?php

final class tragofone_normalizer {
	public static function boolean(mixed $value): bool {
		return in_array($value, [true, 1, '1', 'true', 'TRUE', 'yes', 'Y'], true);
	}

	public static function sip_extension(string $extension): string {
		$extension = trim($extension);
		$length = mb_strlen($extension);
		if ($length < 2 || $length > 15) {
			throw new InvalidArgumentException('FusionPBX extension must contain 2 to 15 characters for Tragofone.');
		}
		if (!preg_match('/^[A-Za-z0-9]+$/', $extension)) {
			throw new InvalidArgumentException('FusionPBX extension must contain only letters and numbers for Tragofone.');
		}
		return $extension;
	}

	public static function call_timeout(mixed $value): string {
		$value = trim((string) $value);
		if ($value === '') { return '30'; }
		if (!ctype_digit($value) || (int) $value < 1) {
			throw new InvalidArgumentException('FusionPBX call timeout must be a positive whole number.');
		}
		return (string) ((int) $value);
	}

	public static function username(string $extension, string $domain): string {
		$domain = function_exists('idn_to_ascii') ? (idn_to_ascii($domain) ?: $domain) : $domain;
		$value = strtolower(trim($extension).'@'.trim($domain));
		if (!preg_match('/^[\p{L}\p{N}\p{M}@_.-]+$/u', $value)) {
			throw new InvalidArgumentException('Extension and domain cannot produce a valid Tragofone username.');
		}
		if (mb_strlen($value) > 150) { throw new InvalidArgumentException('Generated Tragofone username exceeds 150 characters.'); }
		return $value;
	}

	public static function fallback_username(string $extension, string $domain, string $extension_uuid): string {
		$suffix = substr(str_replace('-', '', strtolower($extension_uuid)), 0, 8);
		return self::username($extension.'.'.$suffix, $domain);
	}

	public static function application_password(string $sip_password): string {
		if ($sip_password === '') { throw new InvalidArgumentException('FusionPBX SIP password cannot be empty.'); }
		if (mb_strlen($sip_password) > 20) {
			throw new InvalidArgumentException('FusionPBX SIP password exceeds the Tragofone 20-character application-password limit.');
		}
		return $sip_password;
	}

	public static function phone(?string $number): ?string {
		if ($number === null || trim($number) === '') { return null; }
		$number = trim($number);
		$prefix = str_starts_with($number, '+') ? '+' : '';
		$digits = preg_replace('/\D+/', '', $number) ?? '';
		return $digits === '' ? null : $prefix.$digits;
	}

	public static function hash(array $record): string {
		$normalize = static function (&$value) use (&$normalize): void {
			if (is_array($value)) { ksort($value); foreach ($value as &$child) { $normalize($child); } }
			elseif ($value === null) { $value = ''; }
		};
		$normalize($record);
		return hash('sha256', json_encode($record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
	}
}
