<?php

final class tragofone_did_resolver {
	/** @return list<string> */
	public static function direct_dids(string $extension, array $destinations): array {
		$dids = [];
		foreach ($destinations as $destination) {
			$assignment = self::direct_assignment($destination);
			if ($assignment !== null && $assignment['extension'] === $extension) { $dids[$assignment['did']] = true; }
		}
		$dids = array_keys($dids);
		sort($dids, SORT_NATURAL);
		return $dids;
	}

	/** @return list<string> */
	public static function caller_ids(string $extension, array $destinations, ?string $effective_caller_id = null): array {
		$dids = self::direct_dids($extension, $destinations);
		$preferred = tragofone_normalizer::phone($effective_caller_id);
		if ($preferred !== null) {
			$dids = array_values(array_diff($dids, [$preferred])); array_unshift($dids, $preferred);
		}
		return $dids;
	}

	/** @return array{extension:string,did:string}|null */
	public static function direct_assignment(array $destination): ?array {
		if (!tragofone_normalizer::boolean($destination['destination_enabled'] ?? false) || !tragofone_normalizer::boolean($destination['destination_type_voice'] ?? false)) { return null; }
		if (($destination['destination_type'] ?? 'inbound') !== 'inbound') { return null; }
		$extension = self::direct_extension($destination); $did = tragofone_normalizer::phone($destination['destination_number'] ?? null);
		return $extension === null || $did === null ? null : compact('extension', 'did');
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
