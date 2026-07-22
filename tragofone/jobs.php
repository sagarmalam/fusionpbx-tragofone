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
	$database->execute("update v_tragofone_sync_jobs set status='pending',attempt_count=0,next_attempt_at=null,error_code=null,error_message=null,lock_owner=null,lock_expires_at=null,completed_at=null where job_uuid=:job_uuid and domain_uuid=:domain_uuid and status in ('retry','dead')", compact('job_uuid', 'domain_uuid'));
	header('Location: jobs.php?retried=1'); exit;
}
$jobs = $database->select('select job_uuid,entity_type,entity_uuid,operation,status,attempt_count,next_attempt_at,error_code,error_message,insert_date from v_tragofone_sync_jobs where domain_uuid=:domain_uuid order by insert_date desc limit 200', compact('domain_uuid'), 'all') ?: [];
$counts = ['pending'=>0,'processing'=>0,'retry'=>0,'dead'=>0,'completed'=>0]; foreach ($jobs as $job) { $counts[$job['status']] = ($counts[$job['status']] ?? 0) + 1; }
$badge_class = static function (?string $status): string { if ($status === 'completed') { return 'ok'; } if (in_array($status, ['pending','processing','retry'], true)) { return 'warn'; } if ($status === 'dead') { return 'error'; } return 'off'; };
$token_generator = new token; $token = $token_generator->create($_SERVER['PHP_SELF']);
require_once 'resources/header.php';
$tragofone_page = 'jobs'; $tragofone_title = 'Synchronization Jobs';
$tragofone_subtitle = 'Inspect background operations, retry failures, and follow synchronization progress.';
?>
<style>
.tj-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}.tj-stat{padding:14px 16px}.tj-stat strong{font-size:22px;display:block}.tj-stat span{font-size:12px;color:#667085}.tj-toolbar{display:flex;justify-content:space-between;gap:10px;padding:12px 14px;border-bottom:1px solid #eaecf0}.tj-toolbar .formfld{max-width:310px}.tj-entity{font-weight:600}.tj-id{display:block;color:#667085;font:11px ui-monospace,SFMono-Regular,Menlo,monospace;margin-top:3px;max-width:240px;overflow:hidden;text-overflow:ellipsis}.tj-error{color:#b42318;font-size:11px;max-width:280px}.tj-muted{color:#667085;font-size:12px}@media(max-width:760px){.tj-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.tj-toolbar{flex-direction:column}.tj-toolbar .formfld{max-width:none;width:100%}}
</style>
<div class="tfn-shell">
	<?php require __DIR__.'/resources/views/navigation.php'; ?>
	<?php if (isset($_GET['retried'])) { ?><div class="alert alert-info">The selected job was returned to the pending queue.</div><?php } ?>
	<div class="tj-summary"><div class="tfn-card tj-stat"><strong><?= $counts['pending'] + $counts['processing'] ?></strong><span>Active</span></div><div class="tfn-card tj-stat"><strong><?= $counts['retry'] ?></strong><span>Retrying</span></div><div class="tfn-card tj-stat"><strong><?= $counts['dead'] ?></strong><span>Needs attention</span></div><div class="tfn-card tj-stat"><strong><?= $counts['completed'] ?></strong><span>Completed in latest 200</span></div></div>
	<section class="tfn-card"><div class="tj-toolbar"><b>Latest 200 jobs</b><input id="job_search" class="formfld" type="search" placeholder="Search operation, entity, status, or error"></div><div class="tfn-table-wrap"><table class="tfn-table"><thead><tr><th>Entity</th><th>Operation</th><th>Status</th><th>Attempts</th><th>Next attempt</th><th>Error</th><th>Created</th><th></th></tr></thead><tbody id="job_rows">
	<?php foreach ($jobs as $job) { $search = strtolower(implode(' ', [$job['entity_type'],$job['entity_uuid'],$job['operation'],$job['status'],$job['error_code'],$job['error_message']])); ?><tr data-search="<?= escape($search) ?>"><td><span class="tj-entity"><?= escape(ucfirst($job['entity_type'])) ?></span><span class="tj-id" title="<?= escape($job['entity_uuid']) ?>"><?= escape($job['entity_uuid']) ?></span></td><td><?= escape(ucwords(str_replace('_', ' ', $job['operation']))) ?></td><td><span class="tfn-badge <?= $badge_class($job['status']) ?>"><?= escape($job['status']) ?></span></td><td><?= (int) $job['attempt_count'] ?></td><td class="tj-muted"><?= escape($job['next_attempt_at'] ?: '—') ?></td><td><div class="tj-error"><?= escape($job['error_message'] ?: '—') ?></div></td><td class="tj-muted"><?= escape($job['insert_date']) ?></td><td><?php if (permission_exists('tragofone_job_retry') && in_array($job['status'], ['retry','dead'], true)) { ?><form method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>"><input type="hidden" name="job_uuid" value="<?= escape($job['job_uuid']) ?>"><button class="btn btn-default" type="submit">Retry</button></form><?php } ?></td></tr><?php } ?>
	<?php if ($jobs === []) { ?><tr><td colspan="8"><div class="tfn-empty">No synchronization jobs exist for this domain.</div></td></tr><?php } ?>
	</tbody></table></div></section>
</div>
<script>(function(){var search=document.getElementById('job_search');search.addEventListener('input',function(){var q=this.value.toLowerCase();document.querySelectorAll('#job_rows tr[data-search]').forEach(function(row){row.style.display=row.getAttribute('data-search').indexOf(q)>=0?'':'none';});});})();</script>
<?php require_once 'resources/footer.php'; ?>
