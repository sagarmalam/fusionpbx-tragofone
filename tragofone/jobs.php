<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php';
if (!permission_exists('tragofone_job_view')) { echo 'access denied'; exit; }
$database = new database(); $domain_uuid = $_SESSION['domain_uuid'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!permission_exists('tragofone_job_retry')) { echo 'access denied'; exit; }
	$token_validator = new token;
	if (!$token_validator->validate($_SERVER['PHP_SELF'])) { http_response_code(403); echo 'invalid token'; exit; }
	$job_uuid = (string) ($_POST['job_uuid'] ?? '');
	if (!preg_match('/^[0-9a-f-]{36}$/i', $job_uuid)) { http_response_code(400); echo 'invalid job'; exit; }
	$database->execute(
		"update v_tragofone_sync_jobs set status='pending', attempt_count=0, next_attempt_at=null, error_code=null, error_message=null, lock_owner=null, lock_expires_at=null, completed_at=null where job_uuid=:job_uuid and domain_uuid=:domain_uuid and status in ('retry','dead')",
		compact('job_uuid', 'domain_uuid')
	);
	header('Location: jobs.php'); exit;
}
$jobs = $database->select('select job_uuid, entity_type, entity_uuid, operation, status, attempt_count, next_attempt_at, error_code, error_message, insert_date from v_tragofone_sync_jobs where domain_uuid=:domain_uuid order by insert_date desc limit 200', compact('domain_uuid'), 'all') ?: [];
$token_generator = new token; $token = $token_generator->create($_SERVER['PHP_SELF']);
require_once 'resources/header.php';
?><h2>Synchronization Jobs</h2>
<table class="list"><tr><th>Entity</th><th>Operation</th><th>Status</th><th>Attempts</th><th>Next attempt</th><th>Error</th><th>Created</th><th></th></tr>
<?php foreach ($jobs as $job) { ?><tr>
	<td><?= escape($job['entity_type'].' / '.$job['entity_uuid']) ?></td><td><?= escape($job['operation']) ?></td>
	<td><?= escape($job['status']) ?></td><td><?= escape($job['attempt_count']) ?></td><td><?= escape($job['next_attempt_at']) ?></td>
	<td><?= escape($job['error_message']) ?></td><td><?= escape($job['insert_date']) ?></td><td>
	<?php if (permission_exists('tragofone_job_retry') && in_array($job['status'], ['retry','dead'], true)) { ?>
	<form method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>"><input type="hidden" name="job_uuid" value="<?= escape($job['job_uuid']) ?>"><button class="btn btn-default" type="submit">Retry</button></form>
	<?php } ?></td></tr><?php } ?></table>
<?php require_once 'resources/footer.php'; ?>
