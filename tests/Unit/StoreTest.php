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
}
