<?php

final class tragofone_selfcare_policy {
	public const INHERIT = 'inherit';
	public const YES = 'yes';
	public const NO = 'no';

	public static function normalize(mixed $value): string {
		$value = strtolower(trim((string) $value));
		if ($value === '' || $value === self::INHERIT) { return self::INHERIT; }
		if (in_array($value, [self::YES, 'true', '1', 'on'], true)) { return self::YES; }
		if (in_array($value, [self::NO, 'false', '0', 'off'], true)) { return self::NO; }
		throw new InvalidArgumentException('Self-care policy must be Inherit, Yes, or No.');
	}

	public static function global(array $config): string {
		if (isset($config['selfcare_policy']) && trim((string) $config['selfcare_policy']) !== '') {
			return self::normalize($config['selfcare_policy']);
		}
		return tragofone_normalizer::boolean($config['selfcare_enabled'] ?? false) ? self::YES : self::INHERIT;
	}

	public static function enabled(mixed $global, mixed $domain = self::INHERIT, mixed $user = self::INHERIT): bool {
		foreach ([self::normalize($user), self::normalize($domain), self::normalize($global)] as $policy) {
			if ($policy === self::YES) { return true; }
			if ($policy === self::NO) { return false; }
		}
		return false;
	}
}
