<?php

final class tragofone_scanner {
	public function __construct(private readonly tragofone_store $store) {}

	public function scan_tenant(array $tenant, ?string $since = null): int {
		$count = 0; $seen_extensions = []; $extension_numbers = []; $domain_uuid = $tenant['domain_uuid']; $destinations = $this->store->destinations($domain_uuid);
		$default_extension_sync = !array_key_exists('default_extension_sync', $tenant) || $tenant['default_extension_sync'] === null
			? true : tragofone_normalizer::boolean($tenant['default_extension_sync']);
		$extension_policies = [];
		foreach ($this->store->extension_sync_policies($domain_uuid) as $policy) {
			$extension_policies[$policy['extension_uuid']] = $policy;
		}
		foreach ($this->store->changed_extensions($domain_uuid, $since) as $extension) {
			$extension_uuid = $extension['extension_uuid']; $seen_extensions[$extension_uuid] = true;
			$extension_policy = $extension_policies[$extension_uuid] ?? [];
			try { tragofone_normalizer::sip_extension((string) ($extension['extension'] ?? '')); $sync_eligible = true; }
			catch (InvalidArgumentException) { $sync_eligible = false; }
			$sync_enabled = $sync_eligible && (array_key_exists('sync_enabled', $extension_policy) && $extension_policy['sync_enabled'] !== null
				? tragofone_normalizer::boolean($extension_policy['sync_enabled']) : $default_extension_sync);
			$selfcare_user_policy = tragofone_selfcare_policy::normalize($extension_policy['selfcare_policy'] ?? tragofone_selfcare_policy::INHERIT);
			$selfcare_enabled = tragofone_selfcare_policy::enabled(
				$tenant['selfcare_global_policy'] ?? tragofone_selfcare_policy::INHERIT,
				$tenant['selfcare_policy'] ?? tragofone_selfcare_policy::INHERIT,
				$selfcare_user_policy
			);
			if ($sync_enabled) { $extension_numbers[(string) $extension['extension']] = $extension_uuid; }
			$dids = tragofone_did_resolver::caller_ids((string) $extension['extension'], $destinations, $extension['effective_caller_id_number'] ?? null);
			$tenant_policy = array_intersect_key($tenant, array_flip([
				'default_profile_id', 'sip_server', 'sip_port', 'sip_protocol',
				'outbound_proxy_server', 'outbound_proxy_port', 'voicemail_code',
				'selfcare_enabled', 'selfcare_global_policy', 'selfcare_policy', 'selfcare_base_url', 'selfcare_brand_name', 'selfcare_light_background',
				'selfcare_light_foreground', 'selfcare_light_button', 'selfcare_light_button_foreground',
				'selfcare_dark_background', 'selfcare_dark_foreground', 'selfcare_dark_button',
				'selfcare_dark_button_foreground', 'selfcare_brand_version',
			]));
			$source = ['extension' => $extension, 'dids' => $dids, 'sync_enabled' => $sync_enabled,
				'selfcare_policy' => $selfcare_user_policy, 'selfcare_enabled' => $selfcare_enabled,
				'tenant_policy' => $tenant_policy, 'policy_version' => 9];
			$hash = tragofone_normalizer::hash($source);
			$previous = $this->store->snapshot($domain_uuid, 'extension', $extension_uuid);
			$mapping = $this->store->extension_mapping($domain_uuid, $extension_uuid);
			if ($mapping === null) {
				$recoverable = $this->store->extension_mapping_by_extension($domain_uuid, (string) $extension['extension']);
				if ($recoverable !== null && in_array($recoverable['sync_status'] ?? '', ['disable_pending', 'deletion_pending', 'disabled', 'exclude_pending', 'excluded'], true)) {
					$recoverable['extension_uuid'] = $extension_uuid; $recoverable['delete_after'] = null; $recoverable['update_date'] = gmdate('c');
					$this->store->save_extension_mapping($recoverable); $mapping = $recoverable;
				}
			}
			$enabled = tragofone_normalizer::boolean($extension['enabled'] ?? false);
			$status = $mapping['sync_status'] ?? null;
			$needs_transition = $mapping !== null && (
				(!$sync_enabled && !in_array($status, ['exclude_pending', 'excluded'], true))
				|| ($sync_enabled && $enabled && in_array($status, ['exclude_pending', 'excluded'], true))
				|| ($sync_enabled && $enabled && in_array($status, ['disable_pending', 'deletion_pending', 'disabled'], true))
				|| ($sync_enabled && !$enabled && !in_array($status, ['disable_pending', 'disabled'], true))
			);
			if (($previous['record_hash'] ?? null) === $hash && !$needs_transition) { continue; }
			if (!$sync_enabled && $mapping === null) { $operation = null; }
			elseif (!$sync_enabled) { $operation = 'exclude_user'; }
			elseif ($mapping === null) { $operation = 'create_user'; }
			elseif (!$enabled) { $operation = 'disable_user'; }
			elseif (in_array($mapping['sync_status'] ?? '', ['exclude_pending', 'excluded'], true)) { $operation = 'include_user'; }
			elseif (in_array($mapping['sync_status'] ?? '', ['disable_pending', 'deletion_pending', 'disabled'], true)) { $operation = 'enable_user'; }
			else { $operation = 'update_sip_configuration'; }
			if ($mapping !== null && in_array($operation, ['exclude_user', 'include_user'], true)) {
				$mapping['sync_status'] = $operation === 'exclude_user' ? 'exclude_pending' : 'include_pending';
				$mapping['update_date'] = gmdate('c'); $this->store->save_extension_mapping($mapping);
			}
			if ($operation !== null) { $this->store->enqueue([
				'job_uuid' => self::uuid(), 'domain_uuid' => $domain_uuid, 'entity_type' => 'extension', 'entity_uuid' => $extension_uuid,
				'operation' => $operation, 'phase' => 'pending', 'payload' => json_encode($source, JSON_THROW_ON_ERROR),
				'record_hash' => $hash, 'status' => 'pending', 'priority' => 100, 'attempt_count' => 0,
				'correlation_id' => self::uuid(), 'insert_date' => gmdate('c'),
			]); }
			$this->store->save_snapshot([
				'snapshot_uuid' => $previous['snapshot_uuid'] ?? self::uuid(), 'domain_uuid' => $domain_uuid,
				'entity_type' => 'extension', 'entity_uuid' => $extension_uuid, 'record_hash' => $hash,
				'source_update_date' => $extension['update_date'] ?? gmdate('c'), 'last_seen_at' => gmdate('c'),
			]);
			if ($operation !== null) { $count++; }
		}
		if ($since === null) { $this->sync_did_mappings($domain_uuid, $destinations, $extension_numbers); $count += $this->scan_deleted_extensions($tenant, $seen_extensions); }
		$count += $this->scan_contacts($domain_uuid, $since);
		return $count;
	}

	private function sync_did_mappings(string $domain_uuid, array $destinations, array $extension_numbers): void {
		$existing = [];
		foreach ($this->store->did_mappings($domain_uuid) as $mapping) { $existing[$mapping['destination_uuid']] = $mapping; }
		$seen = [];
		foreach ($destinations as $destination) {
			$assignment = tragofone_did_resolver::direct_assignment($destination); $destination_uuid = $destination['destination_uuid'] ?? null;
			if ($assignment === null || $destination_uuid === null || !isset($extension_numbers[$assignment['extension']])) { continue; }
			$seen[$destination_uuid] = true; $mapping = $existing[$destination_uuid] ?? [];
			$this->store->save_did_mapping([
				'mapping_uuid' => $mapping['mapping_uuid'] ?? self::uuid(), 'domain_uuid' => $domain_uuid,
				'destination_uuid' => $destination_uuid, 'extension_uuid' => $extension_numbers[$assignment['extension']],
				'did_number' => $assignment['did'], 'enabled' => true,
				'record_hash' => tragofone_normalizer::hash($assignment), 'last_seen_at' => gmdate('c'),
			]);
		}
		foreach ($existing as $destination_uuid => $mapping) {
			if (isset($seen[$destination_uuid]) || !tragofone_normalizer::boolean($mapping['enabled'] ?? false)) { continue; }
			$mapping['enabled'] = false; $mapping['last_seen_at'] = gmdate('c'); $this->store->save_did_mapping($mapping);
		}
	}

	private function scan_deleted_extensions(array $tenant, array $seen_extensions): int {
		$count = 0; $now = time(); $grace = max(60, (int) ($tenant['deletion_grace_seconds'] ?? 86400));
		foreach ($this->store->extension_mappings($tenant['domain_uuid']) as $mapping) {
			if (isset($seen_extensions[$mapping['extension_uuid']])) { continue; }
			$status = $mapping['sync_status'] ?? '';
			if (in_array($status, ['disable_pending', 'delete_pending'], true)) { continue; }
			if ($status === 'deletion_pending') {
				if (empty($mapping['delete_after']) || strtotime((string) $mapping['delete_after']) > $now) { continue; }
				$operation = 'delete_user'; $mapping['sync_status'] = 'delete_pending';
			} else {
				$operation = 'schedule_user_deletion'; $mapping['sync_status'] = 'disable_pending';
			}
			$this->store->enqueue([
				'job_uuid' => self::uuid(), 'domain_uuid' => $mapping['domain_uuid'], 'entity_type' => 'extension', 'entity_uuid' => $mapping['extension_uuid'],
				'operation' => $operation, 'phase' => 'pending', 'payload' => json_encode(['grace_seconds' => $grace], JSON_THROW_ON_ERROR),
				'record_hash' => $mapping['record_hash'] ?? null, 'status' => 'pending', 'priority' => 90, 'attempt_count' => 0,
				'correlation_id' => self::uuid(), 'insert_date' => gmdate('c'),
			]);
			$mapping['update_date'] = gmdate('c'); $this->store->save_extension_mapping($mapping); $count++;
		}
		return $count;
	}

	private function scan_contacts(string $domain_uuid, ?string $since): int {
		if (!$this->store->contact_schema_supported()) { return 0; }
		$count = 0; $seen = [];
		foreach ($this->store->changed_contacts($domain_uuid, $since) as $source_contact) {
			$contact_uuid = $source_contact['contact_uuid']; $seen[$contact_uuid] = true;
			$contact = tragofone_contact_mapper::map(
				$source_contact,
				$this->store->contact_phones($domain_uuid, $contact_uuid),
				$this->store->contact_emails($domain_uuid, $contact_uuid)
			);
			$mapping = $this->store->contact_mapping($domain_uuid, $contact_uuid);
			if ($contact === null) {
				if ($mapping !== null && ($mapping['sync_status'] ?? '') !== 'delete_pending') {
					$count += $this->enqueue_contact_delete($mapping);
				}
				continue;
			}
			$source = ['contact' => $contact, 'policy_version' => 1];
			$hash = tragofone_normalizer::hash($source);
			$previous = $this->store->snapshot($domain_uuid, 'contact', $contact_uuid);
			if (($previous['record_hash'] ?? null) === $hash) { continue; }
			$this->store->enqueue([
				'job_uuid' => self::uuid(), 'domain_uuid' => $domain_uuid, 'entity_type' => 'contact', 'entity_uuid' => $contact_uuid,
				'operation' => $mapping === null ? 'create_contact' : 'update_contact', 'phase' => 'pending',
				'payload' => json_encode($source, JSON_THROW_ON_ERROR), 'record_hash' => $hash,
				'status' => 'pending', 'priority' => 50, 'attempt_count' => 0,
				'correlation_id' => self::uuid(), 'insert_date' => gmdate('c'),
			]);
			$this->store->save_snapshot([
				'snapshot_uuid' => $previous['snapshot_uuid'] ?? self::uuid(), 'domain_uuid' => $domain_uuid,
				'entity_type' => 'contact', 'entity_uuid' => $contact_uuid, 'record_hash' => $hash,
				'source_update_date' => $source_contact['update_date'] ?? $source_contact['insert_date'] ?? gmdate('c'),
				'last_seen_at' => gmdate('c'),
			]);
			$count++;
		}
		if ($since === null) {
			foreach ($this->store->contact_mappings($domain_uuid) as $mapping) {
				if (isset($seen[$mapping['contact_uuid']]) || ($mapping['sync_status'] ?? '') === 'delete_pending') { continue; }
				$count += $this->enqueue_contact_delete($mapping);
			}
		}
		return $count;
	}

	private function enqueue_contact_delete(array $mapping): int {
		$this->store->enqueue([
			'job_uuid' => self::uuid(), 'domain_uuid' => $mapping['domain_uuid'], 'entity_type' => 'contact', 'entity_uuid' => $mapping['contact_uuid'],
			'operation' => 'delete_contact', 'phase' => 'pending', 'payload' => '{}',
			'record_hash' => $mapping['record_hash'] ?? null, 'status' => 'pending', 'priority' => 40,
			'attempt_count' => 0, 'correlation_id' => self::uuid(), 'insert_date' => gmdate('c'),
		]);
		$mapping['sync_status'] = 'delete_pending'; $mapping['update_date'] = gmdate('c');
		$this->store->save_contact_mapping($mapping);
		return 1;
	}

	public static function uuid(): string {
		$data = random_bytes(16); $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
}
