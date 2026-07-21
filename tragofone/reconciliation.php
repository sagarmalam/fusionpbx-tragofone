<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php'; require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_manual_sync')) { echo 'access denied'; exit; }
$database = new database(); $domain_uuid = $_SESSION['domain_uuid']; $message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token_validator = new token;
	if (!$token_validator->validate($_SERVER['PHP_SELF'])) { http_response_code(403); echo 'invalid token'; exit; }
	$store = new tragofone_fusionpbx_store($database);
	try {
		$tenant = $store->tenant($domain_uuid);
		if ($tenant === null) { $message = 'The tenant is not enabled.'; }
		else { $count = (new tragofone_scanner($store))->scan_tenant($tenant, null); $message = "Reconciliation queued {$count} repair job(s)."; }
	} catch (tragofone_tenant_configuration_exception $error) { $message = $error->getMessage(); }
}
$token_generator = new token; $token = $token_generator->create($_SERVER['PHP_SELF']);
require_once 'resources/header.php';
?><h2>Reconciliation</h2>
<p>Full reconciliation compares companion-owned mappings with current FusionPBX entities. It never adopts or deletes unrelated Tragofone records.</p>
<?php if ($message !== null) { ?><div class="alert alert-info"><?= escape($message) ?></div><?php } ?>
<form method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>"><button class="btn btn-primary" type="submit">Run reconciliation</button></form>
<?php require_once 'resources/footer.php'; ?>
