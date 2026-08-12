<?php

final class tragofone_selfcare_settings {
	/**
	 * Resolve access independently from branding. A partial form submission or
	 * a theme-default restore must never disable an already enabled portal.
	 *
	 * @return array{policy:string,enabled:bool,base_url:string}
	 */
	public static function access(array $current, array $input, bool $restore_theme = false): array {
		$current_policy = tragofone_selfcare_policy::global($current);
		$policy = $restore_theme || !array_key_exists('selfcare_policy', $input)
			? $current_policy : tragofone_selfcare_policy::normalize($input['selfcare_policy']);
		$base_url = $restore_theme || !array_key_exists('selfcare_base_url', $input)
			? trim((string) ($current['selfcare_base_url'] ?? '')) : trim((string) $input['selfcare_base_url']);
		return ['policy'=>$policy, 'enabled'=>tragofone_selfcare_policy::enabled($policy), 'base_url'=>$base_url];
	}

	public static function effective_for_job(array $tenant, array $payload): bool {
		$global = array_key_exists('selfcare_global_policy', $tenant)
			? $tenant['selfcare_global_policy']
			: (tragofone_normalizer::boolean($tenant['selfcare_enabled'] ?? false)
				? tragofone_selfcare_policy::YES : tragofone_selfcare_policy::INHERIT);
		return tragofone_selfcare_policy::enabled(
			$global,
			$tenant['selfcare_policy'] ?? tragofone_selfcare_policy::INHERIT,
			$payload['selfcare_policy'] ?? tragofone_selfcare_policy::INHERIT
		);
	}
}
