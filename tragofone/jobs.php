<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php'; if (!permission_exists('tragofone_job_view')) { echo access_denied(); exit; }
require_once 'resources/header.php'; $database = new database();
$jobs = $database->select('select job_uuid, entity_type, entity_uuid, operation, status, attempt_count, next_attempt_at, error_code, error_message, insert_date from v_tragofone_sync_jobs where domain_uuid=:domain_uuid order by insert_date desc limit 200', ['domain_uuid' => $_SESSION['domain_uuid']], 'all') ?: [];
?><h2>Synchronization Jobs</h2><pre><?= escape(json_encode($jobs, JSON_PRETTY_PRINT)) ?></pre><?php require_once 'resources/footer.php'; ?>
