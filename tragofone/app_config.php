<?php

// FusionPBX application manifest. UUIDs are stable release identifiers.
$apps[$x]['name'] = 'Tragofone';
$apps[$x]['uuid'] = '1b9e9c69-7d33-4d44-99ae-ccecb9e5d001';
$apps[$x]['category'] = 'Advanced';
$apps[$x]['version'] = '0.2.0';
$apps[$x]['license'] = 'Proprietary';
$apps[$x]['url'] = 'https://github.com/sagarmalam/fusionpbx-tragofone';
$apps[$x]['description']['en-us'] = 'Tenant-aware Tragofone provisioning companion.';

// Permissions.
$permission_names = [
	'tragofone_global_view', 'tragofone_global_edit', 'tragofone_tenant_view',
	'tragofone_tenant_edit', 'tragofone_mapping_view', 'tragofone_mapping_edit',
	'tragofone_job_view', 'tragofone_job_retry', 'tragofone_initial_sync',
	'tragofone_manual_sync', 'tragofone_log_view', 'tragofone_extension_sync_view',
	'tragofone_extension_sync_edit', 'tragofone_qr_view', 'tragofone_qr_download',
	'tragofone_qr_email',
];
$y = 0;
foreach ($permission_names as $permission_name) {
	$apps[$x]['permissions'][$y]['name'] = $permission_name;
	$apps[$x]['permissions'][$y]['groups'][] = 'superadmin';
	if (!in_array($permission_name, ['tragofone_global_view', 'tragofone_global_edit'], true)) {
		$apps[$x]['permissions'][$y]['groups'][] = 'admin';
	}
	$y++;
}

// Companion-owned schema. FusionPBX's upgrade process creates/updates these tables.
$tables = [
	'v_tragofone_global_config' => [
		['config_uuid', 'uuid', 'primary'], ['base_url', 'text'], ['customer_username', 'text'],
		['encrypted_customer_password', 'text'], ['verify_tls', 'boolean'], ['default_profile_id', 'numeric'],
		['sip_port', 'numeric'], ['sip_protocol', 'text'], ['voicemail_code', 'text'],
		['selfcare_enabled', 'boolean'], ['selfcare_policy', 'text'], ['selfcare_base_url', 'text'], ['selfcare_brand_name', 'text'],
		['selfcare_brand_logo_base64', 'text'], ['selfcare_brand_logo_mime', 'text'],
		['selfcare_light_background', 'text'], ['selfcare_light_foreground', 'text'],
		['selfcare_light_button', 'text'], ['selfcare_light_button_foreground', 'text'],
		['selfcare_dark_background', 'text'], ['selfcare_dark_foreground', 'text'],
		['selfcare_dark_button', 'text'], ['selfcare_dark_button_foreground', 'text'],
		['selfcare_brand_version', 'numeric'], ['selfcare_external_forwarding', 'boolean'],
		['selfcare_external_prefixes', 'text'], ['selfcare_session_idle_seconds', 'numeric'],
		['selfcare_session_absolute_seconds', 'numeric'],
		['insert_date', 'timestamp'], ['insert_user', 'uuid'], ['update_date', 'timestamp'], ['update_user', 'uuid'],
	],
	'v_tragofone_tenants' => [
		['tragofone_tenant_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'],
		['enabled', 'boolean'], ['paused', 'boolean'], ['base_url', 'text'],
		['inherit_global_url', 'boolean'], ['customer_username', 'text'],
		['encrypted_customer_password', 'text'], ['inherit_global_credentials', 'boolean'],
		['expected_customer_id', 'numeric'], ['expected_customer_username', 'text'],
		['expected_company_name', 'text'], ['default_profile_id', 'numeric'],
		['sip_server', 'text'], ['sip_port', 'numeric'], ['sip_protocol', 'text'],
		['outbound_proxy_server', 'text'], ['outbound_proxy_port', 'numeric'],
		['voicemail_code', 'text'], ['deletion_grace_seconds', 'numeric'], ['default_extension_sync', 'boolean'], ['selfcare_policy', 'text'],
		['last_auth_status', 'text'], ['last_error', 'text'], ['last_sync_at', 'timestamp'],
		['insert_date', 'timestamp'], ['insert_user', 'uuid'], ['update_date', 'timestamp'], ['update_user', 'uuid'],
	],
	'v_tragofone_extension_mappings' => [
		['mapping_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'],
		['extension_uuid', 'uuid', 'index'], ['extension', 'text'], ['tragofone_username', 'text'],
		['tragofone_customer_id', 'numeric'], ['tragofone_user_id', 'numeric'],
		['tragofone_unique_id', 'text'], ['profile_id', 'numeric'], ['record_hash', 'text'],
		['sync_status', 'text'], ['last_operation', 'text'], ['last_synced_at', 'timestamp'],
		['delete_after', 'timestamp'], ['deleted_at', 'timestamp'], ['last_error', 'text'],
		['insert_date', 'timestamp'], ['update_date', 'timestamp'],
	],
	'v_tragofone_contact_mappings' => [
		['mapping_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'],
		['contact_uuid', 'uuid', 'index'], ['tragofone_ed_id', 'numeric'],
		['record_hash', 'text'], ['sync_status', 'text'], ['last_synced_at', 'timestamp'],
		['deleted_at', 'timestamp'], ['last_error', 'text'], ['insert_date', 'timestamp'], ['update_date', 'timestamp'],
	],
	'v_tragofone_did_mappings' => [
		['mapping_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'],
		['destination_uuid', 'uuid', 'index'], ['extension_uuid', 'uuid', 'index'],
		['did_number', 'text'], ['enabled', 'boolean'], ['record_hash', 'text'], ['last_seen_at', 'timestamp'],
	],
	'v_tragofone_extension_policies' => [
		['policy_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'], ['extension_uuid', 'uuid', 'index'],
		['sync_enabled', 'boolean'], ['selfcare_policy', 'text'], ['insert_date', 'timestamp'], ['insert_user', 'uuid'],
		['update_date', 'timestamp'], ['update_user', 'uuid'],
	],
	'v_tragofone_snapshots' => [
		['snapshot_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'],
		['entity_type', 'text'], ['entity_uuid', 'uuid', 'index'], ['record_hash', 'text'],
		['source_update_date', 'timestamp'], ['scan_id', 'uuid'], ['last_seen_at', 'timestamp'],
	],
	'v_tragofone_sync_jobs' => [
		['job_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'],
		['entity_type', 'text'], ['entity_uuid', 'uuid', 'index'], ['operation', 'text'],
		['phase', 'text'], ['payload', 'text'], ['record_hash', 'text'], ['status', 'text'],
		['priority', 'numeric'], ['attempt_count', 'numeric'], ['next_attempt_at', 'timestamp'],
		['lock_owner', 'text'], ['lock_expires_at', 'timestamp'], ['http_status', 'numeric'],
		['error_code', 'text'], ['error_message', 'text'], ['correlation_id', 'uuid'],
		['insert_date', 'timestamp'], ['started_at', 'timestamp'], ['completed_at', 'timestamp'],
	],
	'v_tragofone_sync_state' => [
		['state_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'],
		['last_scan_at', 'timestamp'], ['last_scan_id', 'uuid'], ['last_reconcile_at', 'timestamp'],
		['fusionpbx_version', 'text'], ['adapter_version', 'text'], ['worker_heartbeat_at', 'timestamp'],
	],
	'v_tragofone_audit' => [
		['audit_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'],
		['action', 'text'], ['entity_type', 'text'], ['entity_uuid', 'uuid'],
		['summary', 'text'], ['correlation_id', 'uuid'], ['insert_date', 'timestamp'], ['insert_user', 'uuid'],
	],
	'v_tragofone_selfcare_subjects' => [
		['subject_uuid', 'uuid', 'primary'], ['domain_uuid', 'uuid', 'index'],
		['extension_uuid', 'uuid', 'index'], ['encrypted_salt', 'text'], ['active', 'boolean'],
		['brand_version', 'numeric'], ['insert_date', 'timestamp'], ['update_date', 'timestamp'],
	],
	'v_tragofone_selfcare_sessions' => [
		['session_uuid', 'uuid', 'primary'], ['subject_uuid', 'uuid', 'index'],
		['token_hash', 'text'], ['csrf_hash', 'text'], ['theme_payload', 'text'], ['ip_hash', 'text'], ['user_agent_hash', 'text'],
		['created_at', 'timestamp'], ['last_seen_at', 'timestamp'], ['idle_expires_at', 'timestamp'],
		['absolute_expires_at', 'timestamp'], ['revoked_at', 'timestamp'],
	],
	'v_tragofone_selfcare_assertions' => [
		['assertion_hash', 'text', 'primary'], ['subject_uuid', 'uuid', 'index'],
		['expires_at', 'timestamp'], ['consumed_at', 'timestamp'], ['insert_date', 'timestamp'],
	],
	'v_tragofone_selfcare_rate_limits' => [
		['bucket_hash', 'text', 'primary'], ['window_start', 'timestamp'], ['attempts', 'numeric'],
		['blocked_until', 'timestamp'], ['update_date', 'timestamp'],
	],
];

$y = 0;
foreach ($tables as $table_name => $fields) {
	$apps[$x]['db'][$y]['table']['name'] = $table_name;
	$z = 0;
	foreach ($fields as $field) {
		$apps[$x]['db'][$y]['fields'][$z]['name'] = $field[0];
		$apps[$x]['db'][$y]['fields'][$z]['type'] = $field[1];
		if (($field[2] ?? null) === 'primary') {
			$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'primary';
		}
		if (($field[2] ?? null) === 'index') {
			$apps[$x]['db'][$y]['fields'][$z]['key']['type'] = 'index';
		}
		$z++;
	}
	$y++;
}

unset($permission_names, $tables, $table_name, $fields, $field);
