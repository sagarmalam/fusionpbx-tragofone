<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php'; require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_global_edit')) { echo access_denied(); exit; }
require_once 'resources/header.php'; $database = new database();
$rows = $database->select('select * from v_tragofone_global_config limit 1', [], 'all') ?: []; $config = $rows[0] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_token($_SERVER['PHP_SELF'])) {
	$record = ['config_uuid' => $config['config_uuid'] ?? uuid(), 'base_url' => tragofone_url_validator::validate($_POST['base_url']),
		'customer_username' => trim($_POST['customer_username'] ?? ''), 'verify_tls' => true,
		'default_profile_id' => (int) ($_POST['default_profile_id'] ?? 0), 'sip_port' => (int) ($_POST['sip_port'] ?? 5061),
		'sip_protocol' => $_POST['sip_protocol'] ?? 'tls', 'voicemail_code' => trim($_POST['voicemail_code'] ?? '*97'),
		'update_date' => date('c'), 'update_user' => $_SESSION['user_uuid']];
	if (!empty($_POST['customer_password'])) {
		$key = getenv('TRAGOFONE_ENCRYPTION_KEY'); if (!$key) { throw new RuntimeException('TRAGOFONE_ENCRYPTION_KEY is not configured.'); }
		$record['encrypted_customer_password'] = (new tragofone_crypto($key))->encrypt($_POST['customer_password']);
	}
	$array['v_tragofone_global_config'][0] = ['uuid' => $record['config_uuid'], ...$record];
	$database->app_name = 'tragofone'; $database->app_uuid = '1b9e9c69-7d33-4d44-99ae-ccecb9e5d001'; $database->save($array);
	header('Location: global_settings.php'); exit;
}
?>
<form method="post"><?= token_field() ?><div class="card"><div class="card-header"><b>Global Tragofone Defaults</b></div><div class="card-body">
<p>Tenants use these values only when explicit inheritance is enabled.</p>
<label>Base URL</label><input class="formfld" name="base_url" value="<?= escape($config['base_url'] ?? '') ?>" required><br>
<label>Company admin username</label><input class="formfld" name="customer_username" value="<?= escape($config['customer_username'] ?? '') ?>"><br>
<label>Company admin password</label><input class="formfld" type="password" name="customer_password" placeholder="Leave blank to preserve"><br>
<label>Default profile ID</label><input class="formfld" type="number" name="default_profile_id" value="<?= escape($config['default_profile_id'] ?? 0) ?>"><br>
<label>SIP port</label><input class="formfld" type="number" name="sip_port" value="<?= escape($config['sip_port'] ?? 5061) ?>"><br>
<label>SIP protocol</label><select class="formfld" name="sip_protocol"><?php foreach (['tls','tcp','udp'] as $p) { ?><option <?= ($config['sip_protocol'] ?? 'tls') === $p ? 'selected' : '' ?>><?= $p ?></option><?php } ?></select><br>
<label>Voicemail code</label><input class="formfld" name="voicemail_code" value="<?= escape($config['voicemail_code'] ?? '*97') ?>"><br>
<button class="btn btn-primary">Save</button></div></div></form><?php require_once 'resources/footer.php'; ?>
