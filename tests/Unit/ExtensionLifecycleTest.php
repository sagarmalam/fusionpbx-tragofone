<?php

use PHPUnit\Framework\TestCase;

final class extension_lifecycle_store implements tragofone_store {
	public array $extensions = [];
	public array $destination_rows = [];
	public array $sync_policies = [];
	public array $jobs = [];
	public array $extension_map = [];
	public array $snapshots = [];
	public ?array $claimed_job = null;
	public array $completed = [];
	public array $retried = [];
	public array $failed = [];
	public array $paused = [];
	public array $did_map = [];
	public array $selfcare_subjects = [];
	public array $tenant_config = [];

	public function enabled_tenants(): array { return []; }
	public function tenant(string $domain_uuid): ?array { return array_replace(['domain_uuid' => $domain_uuid, 'default_profile_id' => 1, 'sip_server' => 'pbx.test', 'sip_port' => 5061, 'sip_protocol' => 'tls', 'voicemail_code' => '*97'], $this->tenant_config); }
	public function changed_extensions(string $domain_uuid, ?string $since): array { return $this->extensions; }
	public function destinations(string $domain_uuid): array { return $this->destination_rows; }
	public function extension_sync_policies(string $domain_uuid): array { return $this->sync_policies; }
	public function snapshot(string $domain_uuid, string $entity_type, string $entity_uuid): ?array { return $this->snapshots[$entity_type.':'.$entity_uuid] ?? null; }
	public function save_snapshot(array $snapshot): void { $this->snapshots[$snapshot['entity_type'].':'.$snapshot['entity_uuid']] = $snapshot; }
	public function delete_snapshot(string $domain_uuid, string $entity_type, string $entity_uuid): void { unset($this->snapshots[$entity_type.':'.$entity_uuid]); }
	public function enqueue(array $job): void { $this->jobs[] = $job; }
	public function claim_job(string $worker_id): ?array {
		if ($this->claimed_job !== null) { $job = $this->claimed_job; $this->claimed_job = null; return $job; }
		return array_shift($this->jobs);
	}
	public function complete_job(string $job_uuid): void { $this->completed[] = $job_uuid; }
	public function retry_job(string $job_uuid, int $attempt, int $delay, string $message): void { $this->retried[] = compact('job_uuid', 'attempt', 'delay', 'message'); }
	public function fail_job(string $job_uuid, string $message): void { $this->failed[] = compact('job_uuid', 'message'); }
	public function extension_mapping(string $domain_uuid, string $extension_uuid): ?array { return $this->extension_map[$extension_uuid] ?? null; }
	public function extension_mapping_by_extension(string $domain_uuid, string $extension): ?array {
		foreach ($this->extension_map as $mapping) { if ($mapping['extension'] === $extension && empty($mapping['deleted_at'])) { return $mapping; } }
		return null;
	}
	public function extension_mappings(string $domain_uuid): array { return array_values($this->extension_map); }
	public function save_extension_mapping(array $mapping): void {
		foreach ($this->extension_map as $key => $existing) { if (($existing['mapping_uuid'] ?? null) === ($mapping['mapping_uuid'] ?? null)) { unset($this->extension_map[$key]); } }
		$this->extension_map[$mapping['extension_uuid']] = $mapping;
	}
	public function did_mappings(string $domain_uuid): array { return array_values($this->did_map); }
	public function save_did_mapping(array $mapping): void { $this->did_map[$mapping['destination_uuid']] = $mapping; }
	public function pause_tenant(string $domain_uuid, string $message): void { $this->paused[] = compact('domain_uuid', 'message'); }
	public function contact_schema_supported(): bool { return false; }
	public function changed_contacts(string $domain_uuid, ?string $since): array { return []; }
	public function contact_phones(string $domain_uuid, string $contact_uuid): array { return []; }
	public function contact_emails(string $domain_uuid, string $contact_uuid): array { return []; }
	public function contact_mapping(string $domain_uuid, string $contact_uuid): ?array { return null; }
	public function contact_mappings(string $domain_uuid): array { return []; }
	public function save_contact_mapping(array $mapping): void {}
	public function selfcare_subject(string $domain_uuid, string $extension_uuid): ?array { return $this->selfcare_subjects[$extension_uuid] ?? null; }
	public function save_selfcare_subject(array $subject): void { $this->selfcare_subjects[$subject['extension_uuid']] = $subject; }
	public function revoke_selfcare_subject(string $domain_uuid, string $extension_uuid): void { if (isset($this->selfcare_subjects[$extension_uuid])) { $this->selfcare_subjects[$extension_uuid]['active'] = false; } }
}

final class extension_lifecycle_transport implements tragofone_http_transport {
	public array $responses = [];
	public array $requests = [];
	public function request(string $method, string $url, array $headers = [], ?string $body = null): array {
		$this->requests[] = compact('method', 'url', 'headers', 'body'); return array_shift($this->responses);
	}
}

final class ExtensionLifecycleTest extends TestCase {
	private function mapping(string $uuid = 'ext-1', string $status = 'synchronized'): array {
		return ['mapping_uuid' => 'map-1', 'domain_uuid' => 'domain-1', 'extension_uuid' => $uuid, 'extension' => '1001', 'tragofone_user_id' => 9, 'sync_status' => $status, 'insert_date' => gmdate('c')];
	}

	private function extension(string $uuid = 'ext-1', bool $enabled = true): array {
		return ['domain_uuid' => 'domain-1', 'domain_name' => 'company.test', 'extension_uuid' => $uuid, 'extension' => '1001', 'password' => 'secret', 'enabled' => $enabled, 'effective_caller_id_name' => 'Test', 'effective_caller_id_number' => '1001'];
	}

	public function test_scanner_queues_disable_and_enable_transitions(): void {
		$store = new extension_lifecycle_store(); $store->extension_map['ext-1'] = $this->mapping(); $store->extensions = [$this->extension('ext-1', false)];
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant(['domain_uuid' => 'domain-1'], null));
		self::assertSame('disable_user', $store->jobs[0]['operation']);
		$store->jobs = []; $store->snapshots = []; $store->extension_map['ext-1']['sync_status'] = 'disabled'; $store->extensions = [$this->extension('ext-1', true)];
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant(['domain_uuid' => 'domain-1'], null));
		self::assertSame('enable_user', $store->jobs[0]['operation']);
	}

	public function test_scanner_schedules_and_expires_deletion_grace(): void {
		$store = new extension_lifecycle_store(); $store->extension_map['ext-1'] = $this->mapping();
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant(['domain_uuid' => 'domain-1', 'deletion_grace_seconds' => 60], null));
		self::assertSame('schedule_user_deletion', $store->jobs[0]['operation']); self::assertSame('disable_pending', $store->extension_map['ext-1']['sync_status']);
		$store->jobs = []; $store->extension_map['ext-1']['sync_status'] = 'deletion_pending'; $store->extension_map['ext-1']['delete_after'] = gmdate('c', time() - 1);
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant(['domain_uuid' => 'domain-1', 'deletion_grace_seconds' => 60], null));
		self::assertSame('delete_user', $store->jobs[0]['operation']); self::assertSame('delete_pending', $store->extension_map['ext-1']['sync_status']);
	}

	public function test_full_scan_tracks_and_disables_direct_did_mappings(): void {
		$store = new extension_lifecycle_store(); $store->extensions = [$this->extension()];
		$store->destination_rows = [[
			'destination_uuid'=>'did-1','destination_enabled'=>true,'destination_type_voice'=>true,'destination_type'=>'inbound',
			'destination_number'=>'+14155550100','destination_actions'=>json_encode([['destination_app'=>'transfer','destination_data'=>'1001 XML company.test']]),
		]];
		(new tragofone_scanner($store))->scan_tenant(['domain_uuid'=>'domain-1'], null);
		self::assertTrue($store->did_map['did-1']['enabled']); self::assertSame('ext-1', $store->did_map['did-1']['extension_uuid']);
		$store->destination_rows = []; $store->jobs = [];
		(new tragofone_scanner($store))->scan_tenant(['domain_uuid'=>'domain-1'], null);
		self::assertFalse($store->did_map['did-1']['enabled']);
	}

	public function test_excluded_extension_is_removed_from_direct_did_mappings(): void {
		$store = new extension_lifecycle_store(); $store->extensions = [$this->extension()];
		$store->destination_rows = [[
			'destination_uuid'=>'did-1','destination_enabled'=>true,'destination_type_voice'=>true,'destination_type'=>'inbound',
			'destination_number'=>'+14155550100','destination_actions'=>json_encode([['destination_app'=>'transfer','destination_data'=>'1001 XML company.test']]),
		]];
		(new tragofone_scanner($store))->scan_tenant(['domain_uuid'=>'domain-1'], null);
		self::assertTrue($store->did_map['did-1']['enabled']);
		$store->sync_policies = [['extension_uuid'=>'ext-1','sync_enabled'=>false]]; $store->jobs = []; $store->snapshots = [];
		(new tragofone_scanner($store))->scan_tenant(['domain_uuid'=>'domain-1'], null);
		self::assertFalse($store->did_map['did-1']['enabled']);
	}

	public function test_recreated_extension_recovers_companion_owned_mapping(): void {
		$store = new extension_lifecycle_store(); $store->extension_map['old-uuid'] = $this->mapping('old-uuid', 'deletion_pending'); $store->extensions = [$this->extension('new-uuid', true)];
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant(['domain_uuid' => 'domain-1'], null));
		self::assertSame('enable_user', $store->jobs[0]['operation']); self::assertArrayHasKey('new-uuid', $store->extension_map);
	}

	public function test_same_uuid_returning_during_grace_is_reenabled_even_when_hash_is_unchanged(): void {
		$store = new extension_lifecycle_store(); $store->extension_map['ext-1'] = $this->mapping('ext-1', 'deletion_pending'); $store->extensions = [$this->extension('ext-1', true)];
		$hash = tragofone_normalizer::hash(['extension'=>$store->extensions[0], 'dids'=>['1001'], 'sync_enabled'=>true, 'tenant_policy'=>[], 'policy_version'=>6]);
		$store->snapshots['extension:ext-1'] = ['snapshot_uuid'=>'snapshot-1','record_hash'=>$hash];
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant(['domain_uuid'=>'domain-1'], null));
		self::assertSame('enable_user', $store->jobs[0]['operation']);
	}

	public function test_default_and_explicit_extension_sync_selection(): void {
		$store = new extension_lifecycle_store(); $store->extensions = [$this->extension()];
		self::assertSame(0, (new tragofone_scanner($store))->scan_tenant(['domain_uuid'=>'domain-1','default_extension_sync'=>false], null));
		self::assertSame([], $store->jobs); self::assertArrayHasKey('extension:ext-1', $store->snapshots);
		$store->snapshots = []; $store->sync_policies = [['extension_uuid'=>'ext-1','sync_enabled'=>true]];
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant(['domain_uuid'=>'domain-1','default_extension_sync'=>false], null));
		self::assertSame('create_user', $store->jobs[0]['operation']);
	}

	public function test_tenant_sip_policy_change_queues_configuration_update(): void {
		$store = new extension_lifecycle_store();
		$store->extensions = [$this->extension()];
		$store->extension_map['ext-1'] = $this->mapping();
		$tenant = [
			'domain_uuid'=>'domain-1', 'default_profile_id'=>1, 'sip_server'=>'pbx.test',
			'sip_port'=>5061, 'sip_protocol'=>'tls', 'voicemail_code'=>'*97',
		];
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant($tenant, null));
		$store->jobs = [];
		self::assertSame(0, (new tragofone_scanner($store))->scan_tenant($tenant, null));
		$tenant['outbound_proxy_server'] = 'proxy.pbx.test';
		$tenant['outbound_proxy_port'] = 5081;
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant($tenant, null));
		self::assertSame('update_sip_configuration', $store->jobs[0]['operation']);
	}

	public function test_exclusion_disables_and_reinclusion_reuses_the_mapping(): void {
		$store = new extension_lifecycle_store(); $store->extensions = [$this->extension()]; $store->extension_map['ext-1'] = $this->mapping();
		$store->sync_policies = [['extension_uuid'=>'ext-1','sync_enabled'=>false]];
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant(['domain_uuid'=>'domain-1'], null)); self::assertSame('exclude_user', $store->jobs[0]['operation']);
		$transport = new extension_lifecycle_transport(); $transport->responses = [['status'=>200,'headers'=>[],'body'=>'{"access_token":"a"}'],['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}']];
		$factory = static function () use ($transport): tragofone_client { $client = new tragofone_client('https://trago.test', $transport); $client->customer_login('company', 'password'); return $client; };
		$store->claimed_job = array_shift($store->jobs); (new tragofone_worker($store, $factory))->run_once('worker'); self::assertSame('excluded', $store->extension_map['ext-1']['sync_status']);
		$store->snapshots = []; $store->sync_policies = [['extension_uuid'=>'ext-1','sync_enabled'=>true]];
		self::assertSame(1, (new tragofone_scanner($store))->scan_tenant(['domain_uuid'=>'domain-1'], null)); self::assertSame('include_user', $store->jobs[0]['operation']);
		$transport->responses = [['status'=>200,'headers'=>[],'body'=>'{"access_token":"b"}'],['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}'],['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}']];
		$store->claimed_job = array_shift($store->jobs); (new tragofone_worker($store, $factory))->run_once('worker'); self::assertSame('synchronized', $store->extension_map['ext-1']['sync_status']); self::assertSame(9, $store->extension_map['ext-1']['tragofone_user_id']);
	}

	public function test_worker_persists_disable_enable_and_deletion_states(): void {
		$store = new extension_lifecycle_store(); $store->extension_map['ext-1'] = $this->mapping(); $transport = new extension_lifecycle_transport();
		$factory = static function () use ($transport): tragofone_client { $client = new tragofone_client('https://trago.test', $transport); $client->customer_login('company', 'password'); return $client; };
		$payload = json_encode(['extension' => $this->extension('ext-1', false), 'dids' => []], JSON_THROW_ON_ERROR);
		$transport->responses = [['status'=>200,'headers'=>[],'body'=>'{"access_token":"a"}'],['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}']];
		$store->claimed_job = ['job_uuid'=>'disable','domain_uuid'=>'domain-1','entity_type'=>'extension','entity_uuid'=>'ext-1','operation'=>'disable_user','payload'=>$payload,'record_hash'=>'disabled-hash','attempt_count'=>0];
		(new tragofone_worker($store, $factory))->run_once('worker'); self::assertSame('disabled', $store->extension_map['ext-1']['sync_status']);

		$payload = json_encode(['extension' => $this->extension('ext-1', true), 'dids' => []], JSON_THROW_ON_ERROR);
		$transport->responses = [['status'=>200,'headers'=>[],'body'=>'{"access_token":"b"}'],['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}'],['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}']];
		$store->claimed_job = ['job_uuid'=>'enable','domain_uuid'=>'domain-1','entity_type'=>'extension','entity_uuid'=>'ext-1','operation'=>'enable_user','payload'=>$payload,'record_hash'=>'enabled-hash','attempt_count'=>0];
		(new tragofone_worker($store, $factory))->run_once('worker'); self::assertSame('synchronized', $store->extension_map['ext-1']['sync_status']);

		$transport->responses = [['status'=>200,'headers'=>[],'body'=>'{"access_token":"c"}'],['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}']];
		$store->claimed_job = ['job_uuid'=>'schedule','domain_uuid'=>'domain-1','entity_type'=>'extension','entity_uuid'=>'ext-1','operation'=>'schedule_user_deletion','payload'=>'{"grace_seconds":60}','record_hash'=>'enabled-hash','attempt_count'=>0];
		(new tragofone_worker($store, $factory))->run_once('worker'); self::assertSame('deletion_pending', $store->extension_map['ext-1']['sync_status']); self::assertNotEmpty($store->extension_map['ext-1']['delete_after']);

		$transport->responses = [['status'=>200,'headers'=>[],'body'=>'{"access_token":"d"}'],['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}']];
		$store->snapshots['extension:ext-1'] = ['snapshot_uuid'=>'snapshot-1','record_hash'=>'enabled-hash'];
		$store->claimed_job = ['job_uuid'=>'delete','domain_uuid'=>'domain-1','entity_type'=>'extension','entity_uuid'=>'ext-1','operation'=>'delete_user','payload'=>'{}','record_hash'=>'enabled-hash','attempt_count'=>0];
		(new tragofone_worker($store, $factory))->run_once('worker'); self::assertSame('deleted', $store->extension_map['ext-1']['sync_status']); self::assertNotEmpty($store->extension_map['ext-1']['deleted_at']); self::assertArrayNotHasKey('extension:ext-1', $store->snapshots);
	}

	public function test_worker_updates_account_name_and_current_extension_number(): void {
		$store = new extension_lifecycle_store();
		$mapping = $this->mapping();
		$mapping['extension'] = '201';
		$mapping['tragofone_username'] = '201@company.test';
		$store->extension_map['ext-1'] = $mapping;
		$extension = $this->extension();
		$extension['extension'] = '2001';
		$extension['effective_caller_id_name'] = 'Updated Account Name';
		$store->claimed_job = [
			'job_uuid'=>'update-account', 'domain_uuid'=>'domain-1', 'entity_type'=>'extension',
			'entity_uuid'=>'ext-1', 'operation'=>'update_sip_configuration',
			'payload'=>json_encode(['extension'=>$extension,'dids'=>[]], JSON_THROW_ON_ERROR),
			'record_hash'=>'updated-hash', 'attempt_count'=>0,
		];
		$transport = new extension_lifecycle_transport();
		$transport->responses = [
			['status'=>200,'headers'=>[],'body'=>'{"access_token":"a"}'],
			['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}'],
			['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}'],
		];
		$factory = static function () use ($transport): tragofone_client {
			$client = new tragofone_client('https://trago.test', $transport);
			$client->customer_login('company', 'password');
			return $client;
		};
		self::assertTrue((new tragofone_worker($store, $factory))->run_once('worker'));
		$user_update = json_decode($transport->requests[1]['body'], true, 512, JSON_THROW_ON_ERROR);
		$sip_update = json_decode($transport->requests[2]['body'], true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('Updated Account Name', $user_update['usr_account_name']);
		self::assertSame('secret', $user_update['usr_password']);
		self::assertSame(1, $user_update['profile_id']);
		self::assertArrayNotHasKey('usr_username', $user_update);
		self::assertSame('2001', $sip_update['configurations']['sip_auth_username']);
		self::assertSame('2001', $store->extension_map['ext-1']['extension']);
		self::assertSame('201@company.test', $store->extension_map['ext-1']['tragofone_username']);
	}

	public function test_worker_uses_sip_password_for_new_tragofone_login(): void {
		$store = new extension_lifecycle_store();
		$extension = $this->extension();
		$extension['password'] = 'SharedSipPassword!';
		$store->claimed_job = [
			'job_uuid'=>'create', 'domain_uuid'=>'domain-1', 'entity_type'=>'extension',
			'entity_uuid'=>'ext-1', 'operation'=>'create_user',
			'payload'=>json_encode(['extension'=>$extension,'dids'=>[]], JSON_THROW_ON_ERROR),
			'record_hash'=>'created-hash', 'attempt_count'=>0,
		];
		$transport = new extension_lifecycle_transport();
		$transport->responses = [
			['status'=>200,'headers'=>[],'body'=>'{"access_token":"a"}'],
			['status'=>200,'headers'=>[],'body'=>'{"data":{"usr_id":9,"usr_unique_id":"unique-9","cust_id":1}}'],
			['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}'],
		];
		$factory = static function () use ($transport): tragofone_client {
			$client = new tragofone_client('https://trago.test', $transport);
			$client->customer_login('company', 'password');
			return $client;
		};
		self::assertTrue((new tragofone_worker($store, $factory))->run_once('worker'));
		$create = json_decode($transport->requests[1]['body'], true, 512, JSON_THROW_ON_ERROR);
		$configuration = json_decode($transport->requests[2]['body'], true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('SharedSipPassword!', $create['usr_password']);
		self::assertSame('SharedSipPassword!', $configuration['configurations']['sip_auth_password']);
		self::assertSame('1001@company.test', $store->extension_map['ext-1']['tragofone_username']);
	}

	public function test_worker_provisions_a_signed_global_selfcare_url(): void {
		$store = new extension_lifecycle_store();
		$store->tenant_config = ['selfcare_enabled'=>true,'selfcare_base_url'=>'https://pbx.example/app/tragofone/selfcare','selfcare_brand_version'=>4,...tragofone_selfcare_theme::DEFAULTS];
		$store->claimed_job = ['job_uuid'=>'selfcare-create','domain_uuid'=>'domain-1','entity_type'=>'extension','entity_uuid'=>'ext-1','operation'=>'create_user','payload'=>json_encode(['extension'=>$this->extension(),'dids'=>[]],JSON_THROW_ON_ERROR),'record_hash'=>'selfcare-hash','attempt_count'=>0];
		$transport = new extension_lifecycle_transport(); $transport->responses = [
			['status'=>200,'headers'=>[],'body'=>'{"access_token":"a"}'],['status'=>200,'headers'=>[],'body'=>'{"data":{"usr_id":9,"cust_id":1}}'],['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}'],
		];
		$factory=static function()use($transport):tragofone_client{$client=new tragofone_client('https://trago.test',$transport);$client->customer_login('company','password');return $client;};
		self::assertTrue((new tragofone_worker($store,$factory,new tragofone_crypto(str_repeat('k',32))))->run_once('worker'));
		$configuration=json_decode($transport->requests[2]['body'],true,512,JSON_THROW_ON_ERROR)['configurations'];
		self::assertSame('TRUE',$configuration['myaccount_status']);self::assertStringContainsString('brand_v=4',$configuration['myaccount_url']);self::assertStringContainsString('tragofone_salt=',$configuration['myaccount_url']);self::assertTrue($store->selfcare_subjects['ext-1']['active']);
	}

	public function test_delete_is_idempotent_when_tragofone_already_removed_the_user(): void {
		$store = new extension_lifecycle_store(); $store->extension_map['ext-1'] = $this->mapping('ext-1', 'delete_pending'); $transport = new extension_lifecycle_transport();
		$transport->responses = [['status'=>200,'headers'=>[],'body'=>'{"access_token":"a"}'],['status'=>404,'headers'=>[],'body'=>'{"message":"not found"}']];
		$factory = static function () use ($transport): tragofone_client { $client = new tragofone_client('https://trago.test', $transport); $client->customer_login('company', 'password'); return $client; };
		$store->claimed_job = ['job_uuid'=>'delete','domain_uuid'=>'domain-1','entity_type'=>'extension','entity_uuid'=>'ext-1','operation'=>'delete_user','payload'=>'{}','record_hash'=>'x','attempt_count'=>1];
		self::assertTrue((new tragofone_worker($store, $factory))->run_once('worker'));
		self::assertSame('deleted', $store->extension_map['ext-1']['sync_status']); self::assertContains('delete', $store->completed);
	}

	public function test_contact_failure_does_not_block_a_following_sip_job(): void {
		$store = new extension_lifecycle_store(); $store->extension_map['ext-1'] = $this->mapping(); $transport = new extension_lifecycle_transport();
		$store->jobs = [
			['job_uuid'=>'contact-failure','domain_uuid'=>'domain-1','entity_type'=>'contact','entity_uuid'=>'contact-1','operation'=>'create_contact','payload'=>'{"contact":{"ed_first_name":"Failure"}}','record_hash'=>'contact-hash','attempt_count'=>0],
			['job_uuid'=>'sip-after-contact','domain_uuid'=>'domain-1','entity_type'=>'extension','entity_uuid'=>'ext-1','operation'=>'disable_user','payload'=>json_encode(['extension'=>$this->extension('ext-1', false),'dids'=>[]], JSON_THROW_ON_ERROR),'record_hash'=>'disabled-hash','attempt_count'=>0],
		];
		$transport->responses = [
			['status'=>200,'headers'=>[],'body'=>'{"access_token":"a"}'], ['status'=>503,'headers'=>[],'body'=>'{"message":"temporarily unavailable"}'],
			['status'=>200,'headers'=>[],'body'=>'{"access_token":"b"}'], ['status'=>200,'headers'=>[],'body'=>'{"status":"SUCCESS"}'],
		];
		$factory = static function () use ($transport): tragofone_client { $client = new tragofone_client('https://trago.test', $transport); $client->customer_login('company', 'password'); return $client; };
		$worker = new tragofone_worker($store, $factory);
		self::assertTrue($worker->run_once('worker'));
		self::assertSame('contact-failure', $store->retried[0]['job_uuid']); self::assertSame(60, $store->retried[0]['delay']);
		self::assertTrue($worker->run_once('worker'));
		self::assertSame('disabled', $store->extension_map['ext-1']['sync_status']);
		self::assertContains('sip-after-contact', $store->completed);
	}

	public function test_authentication_failure_pauses_only_the_affected_tenant(): void {
		$store = new extension_lifecycle_store(); $store->extension_map['ext-1'] = $this->mapping(); $transport = new extension_lifecycle_transport();
		$store->claimed_job = ['job_uuid'=>'auth-failure','domain_uuid'=>'domain-1','entity_type'=>'extension','entity_uuid'=>'ext-1','operation'=>'disable_user','payload'=>json_encode(['extension'=>$this->extension('ext-1', false),'dids'=>[]], JSON_THROW_ON_ERROR),'record_hash'=>'x','attempt_count'=>0];
		$transport->responses = [['status'=>401,'headers'=>[],'body'=>'{"message":"invalid credentials"}']];
		$factory = static function () use ($transport): tragofone_client { $client = new tragofone_client('https://trago.test', $transport); $client->customer_login('company', 'wrong'); return $client; };
		self::assertTrue((new tragofone_worker($store, $factory))->run_once('worker'));
		self::assertSame('domain-1', $store->paused[0]['domain_uuid']); self::assertSame('auth-failure', $store->failed[0]['job_uuid']);
	}
}
