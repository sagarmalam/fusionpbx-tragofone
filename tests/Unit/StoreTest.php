<?php

use PHPUnit\Framework\TestCase;

if (!class_exists('database', false)) {
	class database {
		public string $sql = '';
		public array $parameters = [];
		public string $return_type = '';
		public mixed $result = false;

		public function execute($sql, $parameters = null, $return_type = 'all'): mixed {
			$this->sql = $sql;
			$this->parameters = $parameters ?? [];
			$this->return_type = $return_type;
			return $this->result;
		}

		public function select($sql, $parameters = null, $return_type = 'all'): mixed {
			$this->sql = $sql;
			$this->parameters = $parameters ?? [];
			$this->return_type = $return_type;
			return [];
		}
	}
}

final class StoreTest extends TestCase {
	public function test_claim_job_uses_one_atomic_update_returning_statement(): void {
		$database = new database();
		$database->result = ['job_uuid' => 'job-1', 'status' => 'processing'];
		$store = new tragofone_fusionpbx_store($database);

		$job = $store->claim_job('worker-1');

		self::assertSame('job-1', $job['job_uuid']);
		self::assertSame(['worker' => 'worker-1'], $database->parameters);
		self::assertSame('row', $database->return_type);
		self::assertStringContainsString('for update of j skip locked', strtolower($database->sql));
		self::assertStringContainsString('returning *', strtolower($database->sql));
		self::assertStringContainsString('t.enabled=true', strtolower($database->sql));
		self::assertStringContainsString('t.paused=false', strtolower($database->sql));
		self::assertStringNotContainsString('begintransaction', strtolower($database->sql));
	}

	public function test_claim_job_returns_null_when_queue_is_empty(): void {
		$database = new database();
		$store = new tragofone_fusionpbx_store($database);

		self::assertNull($store->claim_job('worker-2'));
	}

	public function test_reconciliation_retries_only_dead_jobs_for_the_selected_tenant(): void {
		$database = new database(); $database->result = ['total' => '2'];
		$store = new tragofone_fusionpbx_store($database);

		self::assertSame(2, $store->retry_dead_jobs('domain-1'));
		self::assertSame(['domain_uuid' => 'domain-1'], $database->parameters);
		self::assertSame('row', $database->return_type);
		self::assertStringContainsString("domain_uuid=:domain_uuid and status='dead'", strtolower($database->sql));
		self::assertStringContainsString("set status='pending', attempt_count=0", strtolower($database->sql));
	}

	public function test_upsert_serializes_false_as_postgresql_boolean_literal(): void {
		$database = new database(); $database->result = [];
		$store = new tragofone_fusionpbx_store($database);
		$store->save_did_mapping(['mapping_uuid'=>'map-1','domain_uuid'=>'domain-1','destination_uuid'=>'did-1','extension_uuid'=>'ext-1','enabled'=>false]);
		self::assertSame('false', $database->parameters['enabled']);
	}

	public function test_failed_upsert_is_not_silently_ignored(): void {
		$this->expectException(RuntimeException::class);
		$store = new tragofone_fusionpbx_store(new database());
		$store->save_did_mapping(['mapping_uuid'=>'map-1','enabled'=>false]);
	}

	public function test_extension_source_includes_voicemail_state_and_its_update_time(): void {
		$database = new database(); $store = new tragofone_fusionpbx_store($database);
		$store->changed_extensions('domain-1', '2026-08-01T00:00:00Z');
		$sql = strtolower($database->sql);
		self::assertStringContainsString('left join v_voicemails', $sql);
		self::assertStringContainsString('v.voicemail_enabled', $sql);
		self::assertStringContainsString('v.update_date > :since', $sql);
	}
}
