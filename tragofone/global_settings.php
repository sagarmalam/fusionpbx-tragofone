<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php';
require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_global_edit')) { echo 'access denied'; exit; }
$database = new database(); $error_message = null;
$rows = $database->select('select * from v_tragofone_global_config limit 1', [], 'all') ?: []; $config = $rows[0] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token_validator = new token;
	if (!$token_validator->validate($_SERVER['PHP_SELF'])) { http_response_code(403); echo 'invalid token'; exit; }
	try {
		$base_url = tragofone_url_validator::validate(trim($_POST['base_url'] ?? ''));
		$customer_username = trim($_POST['customer_username'] ?? '');
		if ($customer_username !== '' && empty($_POST['customer_password']) && empty($config['encrypted_customer_password'])) {
			throw new InvalidArgumentException('Global company-admin password is required when a global username is configured.');
		}
		$record = [
			'config_uuid' => $config['config_uuid'] ?? uuid(), 'base_url' => $base_url,
			'customer_username' => $customer_username, 'verify_tls' => 'true',
			'sip_port' => (int) ($_POST['sip_port'] ?? 5061), 'sip_protocol' => $_POST['sip_protocol'] ?? 'tls',
			'voicemail_code' => trim($_POST['voicemail_code'] ?? '*97'),
			'insert_date' => $config['insert_date'] ?? date('c'), 'insert_user' => $config['insert_user'] ?? $_SESSION['user_uuid'],
			'update_date' => date('c'), 'update_user' => $_SESSION['user_uuid'],
		];
		if (!empty($_POST['customer_password'])) { $record['encrypted_customer_password'] = tragofone_crypto::from_environment()->encrypt($_POST['customer_password']); }
		$columns = array_keys($record); $updates = array_values(array_diff($columns, ['config_uuid', 'insert_date', 'insert_user']));
		$sql = 'insert into v_tragofone_global_config ('.implode(', ', $columns).') values (:'.implode(', :', $columns).') ';
		$sql .= 'on conflict (config_uuid) do update set '.implode(', ', array_map(static fn ($column) => $column.' = excluded.'.$column, $updates));
		if ($database->execute($sql, $record) === false) { throw new RuntimeException('Unable to save global settings.'); }
		header('Location: global_settings.php?saved=1'); exit;
	} catch (Throwable $error) {
		$error_message = tragofone_redactor::message($error->getMessage());
		foreach (['base_url','customer_username','sip_port','sip_protocol','voicemail_code'] as $field) {
			if (array_key_exists($field, $_POST)) { $config[$field] = $_POST[$field]; }
		}
	}
}
$has_password = !empty($config['encrypted_customer_password']);
$has_credentials = trim((string) ($config['customer_username'] ?? '')) !== '' && $has_password;
$token_generator = new token; $token = $token_generator->create($_SERVER['PHP_SELF']);
require_once 'resources/header.php';
$tragofone_page = 'global'; $tragofone_title = 'Global Tragofone Settings';
$tragofone_subtitle = 'Optional shared defaults controlled by FusionPBX Superadmins.';
?>
<style>
.tg-wrap{max-width:1180px;margin:0 auto 32px}.tg-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin:8px 0 18px}.tg-head h2{margin:0 0 5px}.tg-subtle{color:#667085;font-size:13px}.tg-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.tg-state{display:inline-flex;align-items:center;gap:7px;padding:6px 11px;border-radius:999px;font-weight:600;font-size:12px;color:#344054;background:#f2f4f7}.tg-state:before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}.tg-alert{padding:12px 15px;border-radius:8px;margin-bottom:16px}.tg-alert.ok{color:#067647;background:#ecfdf3;border:1px solid #abefc6}.tg-alert.error{color:#b42318;background:#fef3f2;border:1px solid #fecdca}.tg-guide{padding:16px 18px;border:1px solid #b2ddff;border-radius:10px;background:#eff8ff;color:#1849a9;margin-bottom:16px}.tg-guide strong{display:block;color:#175cd3;margin-bottom:4px}.tg-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.tg-card{background:var(--card-background-color,#fff);border:1px solid var(--border-color,#d0d5dd);border-radius:10px;overflow:hidden}.tg-card.wide{grid-column:1/-1}.tg-card-title{padding:15px 18px;border-bottom:1px solid var(--border-color,#eaecf0);font-weight:700;display:flex;align-items:center;gap:9px}.tg-card-body{padding:18px}.tg-field{display:grid;grid-template-columns:190px minmax(0,1fr);gap:18px;margin-bottom:16px;align-items:start}.tg-field:last-child{margin-bottom:0}.tg-label{font-weight:600;padding-top:8px}.tg-help{display:block;color:#667085;font-size:12px;line-height:1.4;margin-top:5px}.tg-field .formfld{width:100%;max-width:none}.tg-icon{width:25px;height:25px;border-radius:7px;background:#f2f4f7;display:inline-flex;align-items:center;justify-content:center;color:#344054}.tg-credential-state{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:600;margin-left:8px}.tg-credential-state.ready{color:#067647;background:#ecfdf3}.tg-credential-state.empty{color:#475467;background:#f2f4f7}.tg-steps{margin:0;padding-left:20px;color:#475467;line-height:1.7}.tg-footer{display:flex;justify-content:flex-end;gap:9px;margin-top:18px;padding:14px 0}@media(max-width:850px){.tg-grid{grid-template-columns:1fr}.tg-card.wide{grid-column:auto}.tg-field{grid-template-columns:1fr;gap:5px}.tg-label{padding-top:0}.tg-head{flex-direction:column}}
</style>
<div class="tfn-shell">
	<?php require __DIR__.'/resources/views/navigation.php'; ?>
	<?php if (isset($_GET['saved'])) { ?><div class="tg-alert ok">Global defaults saved successfully. Existing tenants use them only when inheritance is enabled.</div><?php } ?>
	<?php if ($error_message !== null) { ?><div class="tg-alert error"><?= escape($error_message) ?></div><?php } ?>
	<div class="tg-guide"><strong>Where are global credentials configured?</strong>Enter the Tragofone company-admin username and password in the <b>Global Tragofone Credentials</b> section below. A tenant uses them only after its <b>Tenant Settings → Credential source</b> is changed to <b>Inherit global credentials</b>.</div>
	<form method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>">
	<div class="tg-grid">
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">↗</span>Global API Endpoint</div><div class="tg-card-body">
			<div class="tg-field"><div class="tg-label">Tragofone server URL<span class="tg-help">HTTPS base URL inherited only by tenants that select Inherit global URL.</span></div><input class="formfld" name="base_url" value="<?= escape($config['base_url'] ?? '') ?>" placeholder="https://tragofone.example.com" required></div>
			<div class="tg-field"><div class="tg-label">TLS verification<span class="tg-help">Server certificates are always verified. This cannot be disabled from the UI.</span></div><input class="formfld" value="Enabled" readonly></div>
		</div></section>
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">✓</span>Global Tragofone Credentials <span class="tg-credential-state <?= $has_credentials ? 'ready' : 'empty' ?>"><?= $has_credentials ? 'Configured' : 'Not configured' ?></span></div><div class="tg-card-body">
			<div class="tg-field"><div class="tg-label">Company admin username<span class="tg-help">Use credentials only for tenants mapped to this same Tragofone customer.</span></div><input class="formfld" name="customer_username" value="<?= escape($config['customer_username'] ?? '') ?>" autocomplete="off"></div>
			<div class="tg-field"><div class="tg-label">Company admin password<span class="tg-help"><?= $has_password ? 'A global password is stored. Leave blank to preserve it.' : 'Enter the existing Tragofone company-admin password.' ?></span></div><input class="formfld" type="password" name="customer_password" autocomplete="new-password" placeholder="<?= $has_password ? 'Stored securely — leave blank to preserve' : 'Optional until a username is configured' ?>"></div>
		</div></section>
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">☎</span>Provisioning Fallbacks</div><div class="tg-card-body">
			<div class="tg-field"><div class="tg-label">SIP port<span class="tg-help">Fallback used when a tenant does not provide a SIP port.</span></div><input class="formfld" type="number" min="1" max="65535" name="sip_port" value="<?= escape($config['sip_port'] ?? 5061) ?>"></div>
			<div class="tg-field"><div class="tg-label">SIP transport<span class="tg-help">Fallback registration transport.</span></div><select class="formfld" name="sip_protocol"><?php foreach (['tls'=>'TLS','tcp'=>'TCP','udp'=>'UDP'] as $value=>$label) { ?><option value="<?= $value ?>" <?= ($config['sip_protocol'] ?? 'tls') === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div>
			<div class="tg-field"><div class="tg-label">Voicemail code<span class="tg-help">Fallback for one-touch FusionPBX voicemail.</span></div><input class="formfld" name="voicemail_code" value="<?= escape($config['voicemail_code'] ?? '*97') ?>"></div>
		</div></section>
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">i</span>How Inheritance Works</div><div class="tg-card-body">
			<ol class="tg-steps"><li>Save the shared URL and/or credentials on this page.</li><li>Select the target company domain in FusionPBX.</li><li>Open Tenant Settings for that domain.</li><li>Choose Inherit global URL and/or Inherit global credentials.</li><li>Keep the tenant-specific Expected customer ID correct; synchronization stops on a mismatch.</li></ol>
		</div></section>
	</div>
	<div class="tg-footer"><a class="btn btn-default" href="index.php">Cancel</a><button class="btn btn-primary" type="submit">Save Global Defaults</button></div>
	</form>
</div>
<?php require_once 'resources/footer.php'; ?>
