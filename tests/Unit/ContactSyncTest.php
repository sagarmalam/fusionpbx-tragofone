<?php

use PHPUnit\Framework\TestCase;

final class contact_sync_store implements tragofone_store {
	public array $contacts = [];
	public array $phones = [];
	public array $emails = [];
	public array $jobs = [];
	public array $mappings = [];
	public array $snapshots = [];
	public ?array $claimed_job = null;

	public function enabled_tenants(): array { return []; }
	public function tenant(string $domain_uuid): ?array { return ['domain_uuid' => $domain_uuid, 'default_profile_id' => 1]; }
	public function changed_extensions(string $domain_uuid, ?string $since): array { return []; }
	public function destinations(string $domain_uuid): array { return []; }
	public function extension_sync_policies(string $domain_uuid): array { return []; }
	public function snapshot(string $domain_uuid, string $entity_type, string $entity_uuid): ?array { return $this->snapshots[$entity_type.':'.$entity_uuid] ?? null; }
	public function save_snapshot(array $snapshot): void { $this->snapshots[$snapshot['entity_type'].':'.$snapshot['entity_uuid']] = $snapshot; }
	public function delete_snapshot(string $domain_uuid, string $entity_type, string $entity_uuid): void { unset($this->snapshots[$entity_type.':'.$entity_uuid]); }
	public function enqueue(array $job): void { $this->jobs[] = $job; }
	public function claim_job(string $worker_id): ?array { $job = $this->claimed_job; $this->claimed_job = null; return $job; }
	public function complete_job(string $job_uuid): void {}
	public function retry_job(string $job_uuid, int $attempt, int $delay, string $message): void { throw new RuntimeException($message); }
	public function retry_dead_jobs(string $domain_uuid): int { return 0; }
	public function fail_job(string $job_uuid, string $message): void { throw new RuntimeException($message); }
	public function extension_mapping(string $domain_uuid, string $extension_uuid): ?array { return null; }
	public function extension_mapping_by_extension(string $domain_uuid, string $extension): ?array { return null; }
	public function extension_mappings(string $domain_uuid): array { return []; }
	public function save_extension_mapping(array $mapping): void {}
	public function did_mappings(string $domain_uuid): array { return []; }
	public function save_did_mapping(array $mapping): void {}
	public function pause_tenant(string $domain_uuid, string $message): void {}
	public function contact_schema_supported(): bool { return true; }
	public function changed_contacts(string $domain_uuid, ?string $since): array { return $this->contacts; }
	public function contact_phones(string $domain_uuid, string $contact_uuid): array { return $this->phones[$contact_uuid] ?? []; }
	public function contact_emails(string $domain_uuid, string $contact_uuid): array { return $this->emails[$contact_uuid] ?? []; }
	public function contact_mapping(string $domain_uuid, string $contact_uuid): ?array { return $this->mappings[$contact_uuid] ?? null; }
	public function contact_mappings(string $domain_uuid): array { return array_values($this->mappings); }
	public function save_contact_mapping(array $mapping): void { $this->mappings[$mapping['contact_uuid']] = $mapping; }
	public function selfcare_subject(string $domain_uuid, string $extension_uuid): ?array { return null; }
	public function save_selfcare_subject(array $subject): void {}
	public function revoke_selfcare_subject(string $domain_uuid, string $extension_uuid): void {}
}

final class contact_sync_transport implements tragofone_http_transport {
	public array $responses = [];
	public array $requests = [];
	public function request(string $method, string $url, array $headers = [], ?string $body = null): array {
		$this->requests[] = compact('method', 'url', 'headers', 'body');
		return array_shift($this->responses);
	}
}

final class ContactSyncTest extends TestCase {
	public function test_scanner_queues_a_mapped_fusionpbx_phonebook_contact(): void {
		$store = new contact_sync_store();
		$store->contacts = [['contact_uuid' => 'contact-1', 'contact_name_given' => 'Ada', 'contact_name_family' => 'Lovelace']];
		$store->phones['contact-1'] = [['phone_label' => 'Mobile', 'phone_type_voice' => 1, 'phone_number' => '+44 7700 900123']];
		$store->emails['contact-1'] = [['email_address' => 'ada@example.test', 'email_primary' => true]];
		$count = (new tragofone_scanner($store))->scan_tenant(['domain_uuid' => 'domain-1'], null);
		self::assertSame(1, $count); self::assertSame('create_contact', $store->jobs[0]['operation']);
		$payload = json_decode($store->jobs[0]['payload'], true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('+447700900123', $payload['contact']['ed_mobile']);
		self::assertSame('ada@example.test', $payload['contact']['ed_email_id']);
	}

	public function test_full_scan_queues_mapping_owned_contact_deletion(): void {
		$store = new contact_sync_store();
		$store->mappings['gone'] = ['mapping_uuid' => 'mapping-1', 'domain_uuid' => 'domain-1', 'contact_uuid' => 'gone', 'tragofone_ed_id' => 77, 'sync_status' => 'synchronized'];
		$count = (new tragofone_scanner($store))->scan_tenant(['domain_uuid' => 'domain-1'], null);
		self::assertSame(1, $count); self::assertSame('delete_contact', $store->jobs[0]['operation']);
		self::assertSame('delete_pending', $store->mappings['gone']['sync_status']);
	}

	public function test_worker_creates_and_maps_enterprise_directory_contact(): void {
		$store = new contact_sync_store(); $transport = new contact_sync_transport();
		$source = ['contact' => ['ed_first_name' => 'Ada', 'ed_status' => 'Y', 'ed_type' => 'default']];
		$store->claimed_job = ['job_uuid' => 'job-1', 'domain_uuid' => 'domain-1', 'entity_type' => 'contact', 'entity_uuid' => 'contact-1', 'operation' => 'create_contact', 'payload' => json_encode($source), 'record_hash' => 'hash', 'attempt_count' => 0];
		$transport->responses = [
			['status' => 200, 'headers' => [], 'body' => '{"access_token":"abc"}'],
			['status' => 200, 'headers' => [], 'body' => '{"data":{"ed_id":77}}'],
		];
		$factory = static function () use ($transport): tragofone_client {
			$client = new tragofone_client('https://trago.test', $transport); $client->customer_login('company', 'password'); return $client;
		};
		self::assertTrue((new tragofone_worker($store, $factory))->run_once('worker-1'));
		self::assertSame(77, $store->mappings['contact-1']['tragofone_ed_id']);
		self::assertSame('synchronized', $store->mappings['contact-1']['sync_status']);
		self::assertSame('/api/customer/enterprise/create', parse_url($transport->requests[1]['url'], PHP_URL_PATH));
	}
}
