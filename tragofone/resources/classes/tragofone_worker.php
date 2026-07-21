<?php

final class tragofone_worker {
	/** @param Closure(array):tragofone_client $client_factory */
	public function __construct(private readonly tragofone_store $store, private readonly Closure $client_factory) {}

	public function run_once(string $worker_id): bool {
		$job = $this->store->claim_job($worker_id);
		if ($job === null) { return false; }
		try {
			$this->process($job); $this->store->complete_job($job['job_uuid']);
		} catch (Throwable $error) {
			$attempt = ((int) ($job['attempt_count'] ?? 0)) + 1; $delay = tragofone_retry_policy::delay($attempt);
			if ($delay !== null && tragofone_retry_policy::retryable($error)) { $this->store->retry_job($job['job_uuid'], $attempt, $delay, $error->getMessage()); }
			else { $this->store->fail_job($job['job_uuid'], $error->getMessage()); }
		}
		return true;
	}

	private function process(array $job): void {
		$payload = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR); $extension = $payload['extension'];
		$tenant = $this->store->tenant($job['domain_uuid']);
		if ($tenant === null) { throw new RuntimeException('Enabled tenant configuration is missing.'); }
		/** @var tragofone_client $client */ $client = ($this->client_factory)($tenant);
		$mapping = $this->store->extension_mapping($job['domain_uuid'], $job['entity_uuid']);
		if ($job['operation'] === 'create_user') {
			$username = tragofone_normalizer::username($extension['extension'], $extension['domain_name']);
			$result = $client->create_user([
				// The existing Tragofone API accepts a maximum 20-character application password.
				'usr_username' => $username, 'usr_password' => bin2hex(random_bytes(8)),
				'usr_account_name' => $extension['effective_caller_id_name'] ?: $extension['extension'],
				'profile_id' => $tenant['default_profile_id'] ?? null, 'send_qr_code' => 'N',
			]);
			$user = $result['data'] ?? $result;
			$mapping = ['mapping_uuid' => tragofone_scanner::uuid(), 'domain_uuid' => $job['domain_uuid'], 'extension_uuid' => $job['entity_uuid'],
				'extension' => $extension['extension'], 'tragofone_username' => $username,
				'tragofone_customer_id' => $user['cust_id'] ?? $tenant['expected_customer_id'] ?? null,
				'tragofone_user_id' => $user['usr_id'], 'tragofone_unique_id' => $user['usr_unique_id'] ?? null,
				'profile_id' => $tenant['default_profile_id'] ?? null,
				'sync_status' => 'created', 'insert_date' => gmdate('c'), 'update_date' => gmdate('c')];
			$this->store->save_extension_mapping($mapping);
		}
		if ($mapping === null) { throw new RuntimeException('Extension mapping is missing.'); }
		if ($job['operation'] === 'disable_user') { $client->update_user(['user_id' => (int) $mapping['tragofone_user_id'], 'usr_status' => 'N']); return; }
		$configuration = tragofone_feature_policy::configuration($extension, $tenant, $payload['dids'] ?? []);
		$client->update_configuration((int) $mapping['tragofone_user_id'], $configuration);
		$mapping['profile_id'] = $tenant['default_profile_id'] ?? $mapping['profile_id'] ?? null;
		$mapping['record_hash'] = $job['record_hash']; $mapping['sync_status'] = 'synchronized'; $mapping['last_synced_at'] = gmdate('c'); $mapping['update_date'] = gmdate('c');
		$this->store->save_extension_mapping($mapping);
	}
}
