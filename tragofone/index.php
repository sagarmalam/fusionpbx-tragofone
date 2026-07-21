<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php';
require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_tenant_view')) { echo 'access denied'; exit; }
require_once 'resources/header.php';

$domain_uuid = $_SESSION['domain_uuid'];
$database = new database();
$parameters = ['domain_uuid' => $domain_uuid];
$tenants = $database->select('select * from v_tragofone_tenants where domain_uuid = :domain_uuid', $parameters, 'all') ?: [];
$jobs = $database->select("select status, count(*) as total from v_tragofone_sync_jobs where domain_uuid = :domain_uuid group by status", $parameters, 'all') ?: [];
?>
<div class="card">
	<div class="card-header"><b>Tragofone Integration</b></div>
	<div class="card-body">
		<p>Tenant-aware SIP, direct DID, and supported enterprise-contact synchronization.</p>
		<p><?php if (permission_exists('tragofone_global_edit')) { ?><a class="btn btn-default" href="global_settings.php">Global Settings</a><?php } ?>
		<a class="btn btn-primary" href="tenant_settings.php">Tenant Settings</a>
		<a class="btn btn-default" href="mappings.php">Mappings</a>
		<a class="btn btn-default" href="jobs.php">Jobs</a>
		<a class="btn btn-default" href="reconciliation.php">Reconciliation</a></p>
		<h3>Tenant</h3><pre><?= escape(json_encode(tragofone_redactor::data($tenants), JSON_PRETTY_PRINT)) ?></pre>
		<h3>Job summary</h3><pre><?= escape(json_encode($jobs, JSON_PRETTY_PRINT)) ?></pre>
	</div>
</div>
<?php require_once 'resources/footer.php'; ?>
