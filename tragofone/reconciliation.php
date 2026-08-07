<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php'; require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_manual_sync')) { echo 'access denied'; exit; }
$database = new database(); $domain_uuid = $_SESSION['domain_uuid']; $message = null; $message_class = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token_validator = new token;
	if (!$token_validator->validate($_SERVER['PHP_SELF'])) { http_response_code(403); echo 'invalid token'; exit; }
	$store = new tragofone_fusionpbx_store($database);
	try {
		$tenant = $store->tenant($domain_uuid);
		if ($tenant === null) { $message = 'The tenant is disabled or paused. Resume it in Tenant Settings before reconciliation.'; $message_class = 'warning'; }
		else { $count = (new tragofone_scanner($store))->reconcile_tenant($tenant); $message = "Reconciliation completed its comparison and queued {$count} repair job(s)."; }
	} catch (tragofone_tenant_configuration_exception $error) { $message = $error->getMessage(); $message_class = 'warning'; }
}
$state_rows = $database->select('select last_scan_at,last_reconcile_at,worker_heartbeat_at,fusionpbx_version,adapter_version from v_tragofone_sync_state where domain_uuid=:domain_uuid order by last_scan_at desc nulls last limit 1', compact('domain_uuid'), 'all') ?: []; $state = $state_rows[0] ?? [];
$open_rows = $database->select("select count(*) as total from v_tragofone_sync_jobs where domain_uuid=:domain_uuid and status in ('pending','processing','retry','dead')", compact('domain_uuid'), 'all') ?: []; $open_jobs = (int) ($open_rows[0]['total'] ?? 0);
$token_generator = new token; $token = $token_generator->create($_SERVER['PHP_SELF']);
require_once 'resources/header.php';
$tragofone_page = 'reconciliation'; $tragofone_title = 'Reconciliation';
$tragofone_subtitle = 'Compare current FusionPBX state with companion-owned mappings and queue safe repairs.';
?>
<style>
.tr-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:16px}.tr-checks{display:grid;gap:12px;margin:15px 0}.tr-check{display:flex;gap:10px;align-items:flex-start}.tr-check-mark{display:flex;align-items:center;justify-content:center;flex:0 0 22px;height:22px;border-radius:50%;background:#ecfdf3;color:#067647;font-weight:700}.tr-check span{color:#667085;font-size:12px;line-height:1.5}.tr-health{display:grid;gap:12px}.tr-health-row{display:flex;justify-content:space-between;gap:15px;padding-bottom:11px;border-bottom:1px solid #eaecf0}.tr-health-row:last-child{border-bottom:0;padding-bottom:0}.tr-health-row b{text-align:right}.tr-action{margin-top:18px}@media(max-width:800px){.tr-grid{grid-template-columns:1fr}}
</style>
<div class="tfn-shell">
	<?php require __DIR__.'/resources/views/navigation.php'; ?>
	<?php if ($message !== null) { ?><div class="alert alert-<?= escape($message_class) ?>"><?= escape($message) ?></div><?php } ?>
	<div class="tr-grid">
		<section class="tfn-card"><div class="tfn-card-title">Run Full Reconciliation</div><div class="tfn-card-body"><p>Use reconciliation after correcting configuration, recovering from an outage, or investigating drift.</p><div class="tr-checks"><div class="tr-check"><span class="tr-check-mark">✓</span><span>Scans extensions, direct DID routes, and supported enterprise contacts for the active domain.</span></div><div class="tr-check"><span class="tr-check-mark">✓</span><span>Queues only the operations required to match current FusionPBX state.</span></div><div class="tr-check"><span class="tr-check-mark">✓</span><span>Never adopts or deletes unrelated Tragofone users or contacts.</span></div></div><form class="tr-action" method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>"><button class="btn btn-primary" type="submit">Run Reconciliation</button></form></div></section>
		<section class="tfn-card"><div class="tfn-card-title">Synchronization State</div><div class="tfn-card-body"><div class="tr-health"><div class="tr-health-row"><span>Open jobs</span><b><?= $open_jobs ?></b></div><div class="tr-health-row"><span>Last scan</span><b><?= escape($state['last_scan_at'] ?? '—') ?></b></div><div class="tr-health-row"><span>Last reconciliation</span><b><?= escape($state['last_reconcile_at'] ?? '—') ?></b></div><div class="tr-health-row"><span>Worker heartbeat</span><b><?= escape($state['worker_heartbeat_at'] ?? '—') ?></b></div><div class="tr-health-row"><span>Adapter version</span><b><?= escape($state['adapter_version'] ?? '—') ?></b></div></div><div class="tr-action"><?php if (permission_exists('tragofone_job_view')) { ?><a class="btn btn-default" href="jobs.php">View Synchronization Jobs</a><?php } ?></div></div></section>
	</div>
</div>
<?php require_once 'resources/footer.php'; ?>
