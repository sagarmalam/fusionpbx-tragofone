<?php

final class tragofone_scanner {
	public function __construct(private readonly tragofone_store $store) {}

	public function scan_tenant(array $tenant, ?string $since = null): int {
		$count = 0; $domain_uuid = $tenant['domain_uuid']; $destinations = $this->store->destinations($domain_uuid);
		foreach ($this->store->changed_extensions($domain_uuid, $since) as $extension) {
			$extension_uuid = $extension['extension_uuid'];
			$dids = tragofone_did_resolver::direct_dids((string) $extension['extension'], $destinations, $extension['effective_caller_id_number'] ?? null);
			$source = ['extension' => $extension, 'dids' => $dids, 'policy_version' => 1];
			$hash = tragofone_normalizer::hash($source);
			$previous = $this->store->snapshot($domain_uuid, 'extension', $extension_uuid);
			if (($previous['record_hash'] ?? null) === $hash) { continue; }
			$mapping = $this->store->extension_mapping($domain_uuid, $extension_uuid);
			$operation = $mapping === null ? 'create_user' : (!tragofone_normalizer::boolean($extension['enabled'] ?? false) ? 'disable_user' : 'update_sip_configuration');
			$this->store->enqueue([
				'job_uuid' => self::uuid(), 'domain_uuid' => $domain_uuid, 'entity_type' => 'extension', 'entity_uuid' => $extension_uuid,
				'operation' => $operation, 'phase' => 'pending', 'payload' => json_encode($source, JSON_THROW_ON_ERROR),
				'record_hash' => $hash, 'status' => 'pending', 'priority' => 100, 'attempt_count' => 0,
				'correlation_id' => self::uuid(), 'insert_date' => gmdate('c'),
			]);
			$this->store->save_snapshot([
				'snapshot_uuid' => $previous['snapshot_uuid'] ?? self::uuid(), 'domain_uuid' => $domain_uuid,
				'entity_type' => 'extension', 'entity_uuid' => $extension_uuid, 'record_hash' => $hash,
				'source_update_date' => $extension['update_date'] ?? gmdate('c'), 'last_seen_at' => gmdate('c'),
			]);
			$count++;
		}
		return $count;
	}

	public static function uuid(): string {
		$data = random_bytes(16); $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
}
