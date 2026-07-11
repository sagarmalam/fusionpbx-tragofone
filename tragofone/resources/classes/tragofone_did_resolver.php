<?php

final class tragofone_did_resolver {
	/** @return list<string> */
	public static function direct_dids(string $extension, array $destinations, ?string $effective_caller_id = null): array {
		$dids = [];
		foreach ($destinations as $destination) {
			if (!tragofone_normalizer::boolean($destination['destination_enabled'] ?? false) || !tragofone_normalizer::boolean($destination['destination_type_voice'] ?? false)) { continue; }
			if (($destination['destination_type'] ?? 'inbound') !== 'inbound') { continue; }
			$target = self::direct_extension($destination);
			if ($target !== $extension) { continue; }
			$number = tragofone_normalizer::phone($destination['destination_number'] ?? null);
			if ($number !== null) { $dids[$number] = true; }
		}
		$dids = array_keys($dids);
		sort($dids, SORT_NATURAL);
		$preferred = tragofone_normalizer::phone($effective_caller_id);
		if ($preferred !== null && in_array($preferred, $dids, true)) {
			$dids = array_values(array_diff($dids, [$preferred])); array_unshift($dids, $preferred);
		}
		return $dids;
	}

	private static function direct_extension(array $destination): ?string {
		$actions = $destination['destination_actions'] ?? null;
		if (is_string($actions)) { $actions = json_decode($actions, true); }
		if (!is_array($actions) || count($actions) !== 1) { return null; }
		$action = $actions[0];
		$app = strtolower((string) ($action['destination_app'] ?? ''));
		$data = trim((string) ($action['destination_data'] ?? ''));
		if (!in_array($app, ['transfer', 'extension'], true)) { return null; }
		if ($app === 'extension') { return preg_match('/^[\p{L}\p{N}_.-]+$/u', $data) ? $data : null; }
		if (!preg_match('/^([\p{L}\p{N}_.-]+)\s+XML\s+/iu', $data, $matches)) { return null; }
		return $matches[1];
	}
}
