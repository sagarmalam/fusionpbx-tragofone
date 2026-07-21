<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php';
require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_tenant_edit')) { echo 'access denied'; exit; }
$database = new database(); $domain_uuid = $_SESSION['domain_uuid'];
$rows = $database->select('select * from v_tragofone_tenants where domain_uuid = :domain_uuid', ['domain_uuid' => $domain_uuid], 'all') ?: [];
$tenant = $rows[0] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token_validator = new token;
	if (!$token_validator->validate($_SERVER['PHP_SELF'])) {
		http_response_code(403);
		echo 'invalid token';
		exit;
	}
	// Save is intentionally explicit; blank password preserves the encrypted value.
	$record = [
		'tragofone_tenant_uuid' => $tenant['tragofone_tenant_uuid'] ?? uuid(), 'domain_uuid' => $domain_uuid,
		'enabled' => ($_POST['enabled'] ?? '') === 'true', 'base_url' => trim($_POST['base_url'] ?? ''),
		'customer_username' => trim($_POST['customer_username'] ?? ''), 'sip_server' => trim($_POST['sip_server'] ?? ''),
		'sip_port' => (int) ($_POST['sip_port'] ?? 5061), 'sip_protocol' => $_POST['sip_protocol'] ?? 'tls',
		'voicemail_code' => trim($_POST['voicemail_code'] ?? '*97'), 'update_date' => date('c'), 'update_user' => $_SESSION['user_uuid'],
	];
	if (!empty($_POST['customer_password'])) {
		$key = getenv('TRAGOFONE_ENCRYPTION_KEY');
		if (!$key) { throw new RuntimeException('TRAGOFONE_ENCRYPTION_KEY is not configured.'); }
		$record['encrypted_customer_password'] = (new tragofone_crypto($key))->encrypt($_POST['customer_password']);
	}
	$array['v_tragofone_tenants'][0] = ['uuid' => $record['tragofone_tenant_uuid'], ...$record];
	$database->app_name = 'tragofone'; $database->app_uuid = '1b9e9c69-7d33-4d44-99ae-ccecb9e5d001'; $database->save($array);
	header('Location: tenant_settings.php'); exit;
}
$token_generator = new token;
$token = $token_generator->create($_SERVER['PHP_SELF']);
require_once 'resources/header.php';
?>
<form method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>">
<div class="card"><div class="card-header"><b>Tragofone Tenant Settings</b></div><div class="card-body">
	<label>Enabled</label><select name="enabled" class="formfld"><option value="false">False</option><option value="true" <?= !empty($tenant['enabled']) ? 'selected' : '' ?>>True</option></select><br>
	<label>Tragofone URL</label><input class="formfld" name="base_url" value="<?= escape($tenant['base_url'] ?? '') ?>" required><br>
	<label>Company admin username</label><input class="formfld" name="customer_username" value="<?= escape($tenant['customer_username'] ?? '') ?>" required><br>
	<label>Company admin password</label><input class="formfld" type="password" name="customer_password" autocomplete="new-password" placeholder="Leave blank to preserve"><br>
	<label>SIP server</label><input class="formfld" name="sip_server" value="<?= escape($tenant['sip_server'] ?? '') ?>"><br>
	<label>SIP port</label><input class="formfld" type="number" name="sip_port" value="<?= escape($tenant['sip_port'] ?? '5061') ?>"><br>
	<label>SIP protocol</label><select class="formfld" name="sip_protocol"><?php foreach (['tls','tcp','udp'] as $p) { ?><option <?= ($tenant['sip_protocol'] ?? 'tls') === $p ? 'selected' : '' ?>><?= $p ?></option><?php } ?></select><br>
	<label>Voicemail code</label><input class="formfld" name="voicemail_code" value="<?= escape($tenant['voicemail_code'] ?? '*97') ?>"><br>
	<button class="btn btn-primary" type="submit">Save</button>
</div></div></form>
<?php require_once 'resources/footer.php'; ?>
