<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php';
require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_tenant_view')) { echo 'access denied'; exit; }
$domain_uuid = $_SESSION['domain_uuid']; $database = new database(); $parameters = ['domain_uuid' => $domain_uuid];
$tenant_rows = $database->select('select * from v_tragofone_tenants where domain_uuid=:domain_uuid', $parameters, 'all') ?: []; $tenant = $tenant_rows[0] ?? null;
$jobs = $database->select('select status,count(*) as total from v_tragofone_sync_jobs where domain_uuid=:domain_uuid group by status', $parameters, 'all') ?: [];
$counts = $database->select("select (select count(*) from v_tragofone_extension_mappings where domain_uuid=:extension_domain and deleted_at is null) as extensions,(select count(*) from v_tragofone_did_mappings where domain_uuid=:did_domain and enabled=true) as dids,(select count(*) from v_tragofone_contact_mappings where domain_uuid=:contact_domain and deleted_at is null) as contacts", ['extension_domain'=>$domain_uuid,'did_domain'=>$domain_uuid,'contact_domain'=>$domain_uuid], 'all') ?: [];
$counts = $counts[0] ?? ['extensions'=>0,'dids'=>0,'contacts'=>0]; $job_counts = []; $open_jobs = 0;
foreach ($jobs as $job) { $job_counts[$job['status']] = (int) $job['total']; if (!in_array($job['status'], ['completed'], true)) { $open_jobs += (int) $job['total']; } }
$enabled = $tenant !== null && tragofone_normalizer::boolean($tenant['enabled'] ?? false); $paused = $tenant !== null && tragofone_normalizer::boolean($tenant['paused'] ?? false);
$tenant_status = $tenant === null ? 'Not configured' : (!$enabled ? 'Disabled' : ($paused ? 'Paused' : 'Active'));
$status_class = $tenant === null || !$enabled ? 'off' : ($paused ? 'warn' : 'ok');
require_once 'resources/header.php';
$tragofone_page = 'overview'; $tragofone_title = 'Tragofone Integration';
$tragofone_subtitle = 'Tenant-aware SIP, direct DID, and enterprise phonebook synchronization.';
?>
<style>
.to-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}.to-stat{padding:16px}.to-stat strong{display:block;font-size:24px;margin-bottom:4px}.to-stat-label{color:#667085;font-size:12px}.to-grid{display:grid;grid-template-columns:1.3fr .7fr;gap:16px}.to-list{display:grid;gap:10px}.to-step{display:flex;gap:12px;align-items:flex-start;padding:12px;border-radius:8px;background:#f8fafc}.to-step-num{display:flex;align-items:center;justify-content:center;flex:0 0 26px;height:26px;border-radius:50%;background:#1570ef;color:#fff;font-weight:700;font-size:12px}.to-step b{display:block;margin-bottom:3px}.to-step span{color:#667085;font-size:12px}.to-health{display:grid;gap:12px}.to-health-row{display:flex;justify-content:space-between;gap:15px;padding-bottom:11px;border-bottom:1px solid #eaecf0}.to-health-row:last-child{border-bottom:0;padding-bottom:0}.to-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}@media(max-width:850px){.to-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.to-grid{grid-template-columns:1fr}}@media(max-width:480px){.to-summary{grid-template-columns:1fr}}
</style>
<div class="tfn-shell">
	<?php require __DIR__.'/resources/views/navigation.php'; ?>
	<div class="to-summary">
		<div class="tfn-card to-stat"><strong><span class="tfn-badge <?= $status_class ?>"><?= escape($tenant_status) ?></span></strong><span class="to-stat-label">Tenant integration</span></div>
		<div class="tfn-card to-stat"><strong><?= (int) $counts['extensions'] ?></strong><span class="to-stat-label">Mapped extensions</span></div>
		<div class="tfn-card to-stat"><strong><?= (int) $counts['dids'] ?></strong><span class="to-stat-label">Active direct DIDs</span></div>
		<div class="tfn-card to-stat"><strong><?= (int) $counts['contacts'] ?></strong><span class="to-stat-label">Mapped contacts</span></div>
	</div>
	<div class="to-grid">
		<section class="tfn-card"><div class="tfn-card-title"><?= $tenant === null ? 'Get Started' : 'Administration' ?></div><div class="tfn-card-body">
			<div class="to-list">
				<div class="to-step"><span class="to-step-num">1</span><div><b>Configure this tenant</b><span>Set the Tragofone endpoint, credentials, identity, SIP transport, and lifecycle policy.</span></div></div>
				<div class="to-step"><span class="to-step-num">2</span><div><b>Select extensions</b><span>Choose the SIP users that may be provisioned into Tragofone.</span></div></div>
				<div class="to-step"><span class="to-step-num">3</span><div><b>Verify mappings and jobs</b><span>Confirm users, DIDs, and contacts reach a synchronized state.</span></div></div>
			</div>
			<div class="to-actions"><?php if (permission_exists('tragofone_tenant_edit')) { ?><a class="btn btn-primary" href="tenant_settings.php">Open Tenant Settings</a><?php } ?><?php if (permission_exists('tragofone_extension_sync_view')) { ?><a class="btn btn-default" href="extension_sync.php">Choose Extensions</a><?php } ?></div>
		</div></section>
		<section class="tfn-card"><div class="tfn-card-title">Synchronization Health</div><div class="tfn-card-body"><div class="to-health">
			<div class="to-health-row"><span>Open jobs</span><b><?= $open_jobs ?></b></div>
			<div class="to-health-row"><span>Retrying</span><b><?= $job_counts['retry'] ?? 0 ?></b></div>
			<div class="to-health-row"><span>Dead</span><b><?= $job_counts['dead'] ?? 0 ?></b></div>
			<div class="to-health-row"><span>Last tenant sync</span><b><?= escape($tenant['last_sync_at'] ?? '—') ?></b></div>
		</div><div class="to-actions"><?php if (permission_exists('tragofone_job_view')) { ?><a class="btn btn-default" href="jobs.php">View Jobs</a><?php } ?><?php if (permission_exists('tragofone_manual_sync')) { ?><a class="btn btn-default" href="reconciliation.php">Reconcile</a><?php } ?></div></div></section>
	</div>
</div>
<?php require_once 'resources/footer.php'; ?>
