<?php

final class tragofone_selfcare_launch {
	/**
	 * Accept epoch-second and epoch-millisecond client timestamps. Keep the
	 * original value for the MD5 input, but normalize it to seconds before
	 * applying the launch window.
	 */
	public static function timestamp_seconds(string $value): ?int {
		if (!preg_match('/^\d{9,13}$/', $value)) { return null; }
		$timestamp = (int) $value;
		return strlen($value) === 13 ? intdiv($timestamp, 1000) : $timestamp;
	}

	public static function hash_valid(string $salt, string $time, string $hash): bool {
		$hash = strtolower($hash);
		return preg_match('/^[a-f0-9]{32}$/', $hash) === 1
			&& hash_equals(md5($salt.$time), $hash);
	}

	public static function rejection_code(Throwable $error): string {
		return match ($error->getMessage()) {
			'Too many launch attempts.' => 'SC-LAUNCH-01',
			'Signed launch expired.' => 'SC-LAUNCH-03',
			'Self-care access is unavailable.' => 'SC-LAUNCH-04',
			'Invalid branding signature.', 'Account URL is no longer current.' => 'SC-LAUNCH-05',
			'Self-care is disabled.' => 'SC-LAUNCH-06',
			'Signed launch was already used.' => 'SC-LAUNCH-07',
			default => 'SC-LAUNCH-02',
		};
	}
}
