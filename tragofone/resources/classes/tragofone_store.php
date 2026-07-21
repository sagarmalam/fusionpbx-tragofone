<?php

interface tragofone_store {
	public function enabled_tenants(): array;
	public function tenant(string $domain_uuid): ?array;
	public function changed_extensions(string $domain_uuid, ?string $since): array;
	public function destinations(string $domain_uuid): array;
	public function snapshot(string $domain_uuid, string $entity_type, string $entity_uuid): ?array;
	public function save_snapshot(array $snapshot): void;
	public function enqueue(array $job): void;
	public function claim_job(string $worker_id): ?array;
	public function complete_job(string $job_uuid): void;
	public function retry_job(string $job_uuid, int $attempt, int $delay, string $message): void;
	public function fail_job(string $job_uuid, string $message): void;
	public function extension_mapping(string $domain_uuid, string $extension_uuid): ?array;
	public function save_extension_mapping(array $mapping): void;
	public function contact_schema_supported(): bool;
	public function changed_contacts(string $domain_uuid, ?string $since): array;
	public function contact_phones(string $domain_uuid, string $contact_uuid): array;
	public function contact_emails(string $domain_uuid, string $contact_uuid): array;
	public function contact_mapping(string $domain_uuid, string $contact_uuid): ?array;
	public function contact_mappings(string $domain_uuid): array;
	public function save_contact_mapping(array $mapping): void;
}

/** Adapter around FusionPBX's database class. */
final class tragofone_fusionpbx_store implements tragofone_store {
	public function __construct(private readonly database $database) {}

	public function enabled_tenants(): array { return $this->select("select * from v_tragofone_tenants where enabled = true and (paused is null or paused = false)"); }
	public function tenant(string $domain_uuid): ?array { return $this->first('select * from v_tragofone_tenants where domain_uuid=:domain_uuid and enabled=true', ['domain_uuid' => $domain_uuid]); }
	public function changed_extensions(string $domain_uuid, ?string $since): array {
		$sql = "select e.*, d.domain_name from v_extensions e join v_domains d on d.domain_uuid = e.domain_uuid where e.domain_uuid = :domain_uuid";
		$params = ['domain_uuid' => $domain_uuid];
		if ($since !== null) { $sql .= ' and e.update_date > :since'; $params['since'] = $since; }
		return $this->select($sql, $params);
	}
	public function destinations(string $domain_uuid): array { return $this->select('select * from v_destinations where domain_uuid = :domain_uuid', ['domain_uuid' => $domain_uuid]); }
	public function snapshot(string $domain_uuid, string $entity_type, string $entity_uuid): ?array {
		return $this->first('select * from v_tragofone_snapshots where domain_uuid = :domain_uuid and entity_type = :entity_type and entity_uuid = :entity_uuid', compact('domain_uuid', 'entity_type', 'entity_uuid'));
	}
	public function save_snapshot(array $snapshot): void { $this->upsert('v_tragofone_snapshots', 'snapshot_uuid', $snapshot); }
	public function enqueue(array $job): void { $this->upsert('v_tragofone_sync_jobs', 'job_uuid', $job); }
	public function claim_job(string $worker_id): ?array {
		// One PostgreSQL statement keeps claiming atomic without relying on PDO
		// transaction methods that FusionPBX's database wrapper does not expose.
		$sql = "update v_tragofone_sync_jobs set status = 'processing', lock_owner = :worker, lock_expires_at = now() + interval '5 minutes', started_at = now() where job_uuid = (select job_uuid from v_tragofone_sync_jobs where status in ('pending','retry') and (next_attempt_at is null or next_attempt_at <= now()) and (lock_expires_at is null or lock_expires_at < now()) order by priority desc, insert_date asc limit 1 for update skip locked) returning *";
		$job = $this->database->execute($sql, ['worker' => $worker_id], 'row');
		return is_array($job) ? $job : null;
	}
	public function complete_job(string $job_uuid): void { $this->execute("update v_tragofone_sync_jobs set status='completed', completed_at=now(), lock_owner=null, lock_expires_at=null where job_uuid=:uuid", ['uuid' => $job_uuid]); }
	public function retry_job(string $job_uuid, int $attempt, int $delay, string $message): void {
		$this->execute("update v_tragofone_sync_jobs set status='retry', attempt_count=:attempt, next_attempt_at=now() + (:delay || ' seconds')::interval, error_message=:message, lock_owner=null, lock_expires_at=null where job_uuid=:uuid", ['attempt' => $attempt, 'delay' => $delay, 'message' => tragofone_redactor::message($message), 'uuid' => $job_uuid]);
	}
	public function fail_job(string $job_uuid, string $message): void { $this->execute("update v_tragofone_sync_jobs set status='dead', error_message=:message, lock_owner=null, lock_expires_at=null where job_uuid=:uuid", ['message' => tragofone_redactor::message($message), 'uuid' => $job_uuid]); }
	public function extension_mapping(string $domain_uuid, string $extension_uuid): ?array { return $this->first('select * from v_tragofone_extension_mappings where domain_uuid=:domain_uuid and extension_uuid=:extension_uuid and deleted_at is null', compact('domain_uuid', 'extension_uuid')); }
	public function save_extension_mapping(array $mapping): void { $this->upsert('v_tragofone_extension_mappings', 'mapping_uuid', $mapping); }
	public function contact_schema_supported(): bool {
		$row = $this->first("select to_regclass('v_contacts') as contacts, to_regclass('v_contact_phones') as phones, to_regclass('v_contact_emails') as emails");
		return !empty($row['contacts']) && !empty($row['phones']) && !empty($row['emails']);
	}
	public function changed_contacts(string $domain_uuid, ?string $since): array {
		$sql = 'select c.* from v_contacts c where c.domain_uuid = :domain_uuid';
		$params = ['domain_uuid' => $domain_uuid];
		if ($since !== null) {
			$sql .= ' and (coalesce(c.update_date, c.insert_date) > :since';
			$sql .= ' or exists (select 1 from v_contact_phones p where p.domain_uuid=c.domain_uuid and p.contact_uuid=c.contact_uuid and coalesce(p.update_date,p.insert_date) > :since)';
			$sql .= ' or exists (select 1 from v_contact_emails e where e.domain_uuid=c.domain_uuid and e.contact_uuid=c.contact_uuid and coalesce(e.update_date,e.insert_date) > :since))';
			$params['since'] = $since;
		}
		return $this->select($sql, $params);
	}
	public function contact_phones(string $domain_uuid, string $contact_uuid): array {
		return $this->select('select * from v_contact_phones where domain_uuid=:domain_uuid and contact_uuid=:contact_uuid order by phone_primary desc nulls last, insert_date asc', compact('domain_uuid', 'contact_uuid'));
	}
	public function contact_emails(string $domain_uuid, string $contact_uuid): array {
		return $this->select('select * from v_contact_emails where domain_uuid=:domain_uuid and contact_uuid=:contact_uuid order by email_primary desc nulls last, insert_date asc', compact('domain_uuid', 'contact_uuid'));
	}
	public function contact_mapping(string $domain_uuid, string $contact_uuid): ?array {
		return $this->first('select * from v_tragofone_contact_mappings where domain_uuid=:domain_uuid and contact_uuid=:contact_uuid and deleted_at is null', compact('domain_uuid', 'contact_uuid'));
	}
	public function contact_mappings(string $domain_uuid): array {
		return $this->select('select * from v_tragofone_contact_mappings where domain_uuid=:domain_uuid and deleted_at is null', compact('domain_uuid'));
	}
	public function save_contact_mapping(array $mapping): void { $this->upsert('v_tragofone_contact_mappings', 'mapping_uuid', $mapping); }

	private function select(string $sql, array $parameters = []): array { return $this->database->select($sql, $parameters, 'all') ?: []; }
	private function first(string $sql, array $parameters = []): ?array { $rows = $this->select($sql, $parameters); return $rows[0] ?? null; }
	private function execute(string $sql, array $parameters = []): void { $this->database->execute($sql, $parameters); }
	private function upsert(string $table, string $primary_key, array $record): void {
		if (!isset($record[$primary_key])) { throw new InvalidArgumentException("Missing primary key {$primary_key}."); }
		$columns = array_keys($record);
		foreach ([$table, $primary_key, ...$columns] as $identifier) {
			if (!preg_match('/^[a-z][a-z0-9_]*$/', $identifier)) { throw new InvalidArgumentException('Unsafe database identifier.'); }
		}
		$updates = array_values(array_diff($columns, [$primary_key, 'insert_date', 'insert_user']));
		$sql = 'insert into '.$table.' ('.implode(', ', $columns).') values (:'.implode(', :', $columns).') ';
		$sql .= 'on conflict ('.$primary_key.') do update set '.implode(', ', array_map(static fn ($column) => $column.' = excluded.'.$column, $updates));
		$this->database->execute($sql, $record);
	}
}
