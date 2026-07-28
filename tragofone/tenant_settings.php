<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php';
require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_tenant_edit')) { echo 'access denied'; exit; }
$database = new database(); $domain_uuid = $_SESSION['domain_uuid']; $error_message = null;
$rows = $database->select('select * from v_tragofone_tenants where domain_uuid = :domain_uuid', ['domain_uuid' => $domain_uuid], 'all') ?: [];
$tenant = $rows[0] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token_validator = new token;
	if (!$token_validator->validate($_SERVER['PHP_SELF'])) { http_response_code(403); echo 'invalid token'; exit; }
	try {
		$inherit_global_url = ($_POST['inherit_global_url'] ?? '') === 'true';
		$inherit_global_credentials = ($_POST['inherit_global_credentials'] ?? '') === 'true';
		$base_url = trim($_POST['base_url'] ?? '');
		if (!$inherit_global_url) { $base_url = tragofone_url_validator::validate($base_url); }
		if (!$inherit_global_credentials && trim($_POST['customer_username'] ?? '') === '') { throw new InvalidArgumentException('Company admin username is required unless global credentials are inherited.'); }
		if (!$inherit_global_credentials && empty($_POST['customer_password']) && empty($tenant['encrypted_customer_password'])) { throw new InvalidArgumentException('Company admin password is required unless global credentials are inherited.'); }
		$outbound_proxy_port = trim((string) ($_POST['outbound_proxy_port'] ?? ''));
		if ($outbound_proxy_port !== '' && ((int) $outbound_proxy_port < 1 || (int) $outbound_proxy_port > 65535)) {
			throw new InvalidArgumentException('Outbound proxy port must be between 1 and 65535.');
		}
		$record = [
			'tragofone_tenant_uuid' => $tenant['tragofone_tenant_uuid'] ?? uuid(), 'domain_uuid' => $domain_uuid,
			'enabled' => ($_POST['enabled'] ?? '') === 'true' ? 'true' : 'false', 'paused' => ($_POST['paused'] ?? '') === 'true' ? 'true' : 'false', 'base_url' => $base_url,
			'inherit_global_url' => $inherit_global_url ? 'true' : 'false', 'inherit_global_credentials' => $inherit_global_credentials ? 'true' : 'false',
			'customer_username' => trim($_POST['customer_username'] ?? ''), 'sip_server' => trim($_POST['sip_server'] ?? ''),
			'expected_customer_id' => (int) ($_POST['expected_customer_id'] ?? 0),
			'default_profile_id' => (int) ($_POST['default_profile_id'] ?? 0),
			'sip_port' => (int) ($_POST['sip_port'] ?? 5061), 'sip_protocol' => $_POST['sip_protocol'] ?? 'tls',
			'outbound_proxy_server' => trim($_POST['outbound_proxy_server'] ?? ''),
			'outbound_proxy_port' => $outbound_proxy_port === '' ? null : (int) $outbound_proxy_port,
			'voicemail_code' => trim($_POST['voicemail_code'] ?? '*97'),
			'deletion_grace_seconds' => max(60, (int) ($_POST['deletion_grace_seconds'] ?? 86400)),
			'default_extension_sync' => ($_POST['default_extension_sync'] ?? 'true') === 'true' ? 'true' : 'false',
			'insert_date' => $tenant['insert_date'] ?? date('c'), 'insert_user' => $tenant['insert_user'] ?? $_SESSION['user_uuid'],
			'update_date' => date('c'), 'update_user' => $_SESSION['user_uuid'],
		];
		if (!empty($_POST['customer_password'])) { $record['encrypted_customer_password'] = tragofone_crypto::from_environment()->encrypt($_POST['customer_password']); }
		$columns = array_keys($record); $updates = array_values(array_diff($columns, ['tragofone_tenant_uuid', 'insert_date', 'insert_user']));
		$sql = 'insert into v_tragofone_tenants ('.implode(', ', $columns).') values (:'.implode(', :', $columns).') ';
		$sql .= 'on conflict (tragofone_tenant_uuid) do update set '.implode(', ', array_map(static fn ($column) => $column.' = excluded.'.$column, $updates));
		if ($database->execute($sql, $record) === false) { throw new RuntimeException('Unable to save tenant settings.'); }
		header('Location: tenant_settings.php?saved=1'); exit;
	} catch (Throwable $error) {
		$error_message = tragofone_redactor::message($error->getMessage());
		foreach (['enabled','paused','inherit_global_url','inherit_global_credentials','base_url','customer_username','expected_customer_id','default_profile_id','sip_server','sip_port','sip_protocol','outbound_proxy_server','outbound_proxy_port','voicemail_code','deletion_grace_seconds','default_extension_sync'] as $field) {
			if (array_key_exists($field, $_POST)) { $tenant[$field] = $_POST[$field]; }
		}
	}
}
$is_enabled = tragofone_normalizer::boolean($tenant['enabled'] ?? false); $is_paused = tragofone_normalizer::boolean($tenant['paused'] ?? false);
$status_label = !$is_enabled ? 'Disabled' : ($is_paused ? 'Paused' : 'Active'); $status_class = !$is_enabled ? 'off' : ($is_paused ? 'paused' : 'active');
$default_extension_sync = !array_key_exists('default_extension_sync', $tenant) || $tenant['default_extension_sync'] === null ? true : tragofone_normalizer::boolean($tenant['default_extension_sync']);
$token_generator = new token; $token = $token_generator->create($_SERVER['PHP_SELF']);
require_once 'resources/header.php';
$tragofone_page = 'tenant'; $tragofone_title = 'Tenant Settings';
$tragofone_subtitle = 'Configure Tragofone provisioning for '.($_SESSION['domain_name'] ?? $domain_uuid).'.';
?>
<style>
.tf-wrap{max-width:1180px;margin:0 auto 32px}.tf-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin:8px 0 18px}.tf-head h2{margin:0 0 5px}.tf-subtle{color:#667085;font-size:13px}.tf-actions{display:flex;gap:8px;flex-wrap:wrap}.tf-state{display:inline-flex;align-items:center;gap:7px;padding:6px 11px;border-radius:999px;font-weight:600;font-size:12px}.tf-state:before{content:"";width:8px;height:8px;border-radius:50%;background:currentColor}.tf-state.active{color:#067647;background:#ecfdf3}.tf-state.paused{color:#b54708;background:#fffaeb}.tf-state.off{color:#475467;background:#f2f4f7}.tf-alert{padding:12px 15px;border-radius:8px;margin-bottom:16px}.tf-alert.ok{color:#067647;background:#ecfdf3;border:1px solid #abefc6}.tf-alert.error{color:#b42318;background:#fef3f2;border:1px solid #fecdca}.tf-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.tf-card{background:var(--card-background-color,#fff);border:1px solid var(--border-color,#d0d5dd);border-radius:10px;overflow:hidden}.tf-card.wide{grid-column:1/-1}.tf-card-title{padding:15px 18px;border-bottom:1px solid var(--border-color,#eaecf0);font-weight:700;display:flex;align-items:center;gap:9px}.tf-card-body{padding:18px}.tf-field{display:grid;grid-template-columns:190px minmax(0,1fr);gap:18px;margin-bottom:16px;align-items:start}.tf-field:last-child{margin-bottom:0}.tf-label{font-weight:600;padding-top:8px}.tf-help{display:block;color:#667085;font-size:12px;line-height:1.4;margin-top:5px}.tf-field .formfld{width:100%;max-width:none}.tf-readonly{opacity:.58}.tf-footer{display:flex;justify-content:flex-end;gap:9px;margin-top:18px;padding:14px 0}.tf-icon{width:25px;height:25px;border-radius:7px;background:#f2f4f7;display:inline-flex;align-items:center;justify-content:center;color:#344054}@media(max-width:850px){.tf-grid{grid-template-columns:1fr}.tf-card.wide{grid-column:auto}.tf-field{grid-template-columns:1fr;gap:5px}.tf-label{padding-top:0}.tf-head{flex-direction:column}}
</style>
<div class="tfn-shell">
	<?php require __DIR__.'/resources/views/navigation.php'; ?>
	<div style="display:flex;justify-content:flex-end;margin:-4px 0 14px"><span class="tf-state <?= $status_class ?>">Integration <?= $status_label ?></span></div>
	<?php if (isset($_GET['saved'])) { ?><div class="tf-alert ok">Settings saved successfully. The background worker will apply relevant changes.</div><?php } ?>
	<?php if ($error_message !== null) { ?><div class="tf-alert error"><?= escape($error_message) ?></div><?php } ?>
	<form method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>">
	<div class="tf-grid">
		<section class="tf-card"><div class="tf-card-title"><span class="tf-icon">●</span>General</div><div class="tf-card-body">
			<div class="tf-field"><div class="tf-label">Integration state<span class="tf-help">Enable provisioning for this FusionPBX domain.</span></div><select name="enabled" class="formfld"><option value="true" <?= $is_enabled ? 'selected' : '' ?>>Enabled</option><option value="false" <?= !$is_enabled ? 'selected' : '' ?>>Disabled</option></select></div>
			<div class="tf-field"><div class="tf-label">Processing state<span class="tf-help">Pause this tenant without changing user mappings.</span></div><select name="paused" class="formfld"><option value="false" <?= !$is_paused ? 'selected' : '' ?>>Running</option><option value="true" <?= $is_paused ? 'selected' : '' ?>>Paused</option></select></div>
			<div class="tf-field"><div class="tf-label">New extensions<span class="tf-help">Default for extensions that have no explicit selection yet.</span></div><select name="default_extension_sync" class="formfld"><option value="true" <?= $default_extension_sync ? 'selected' : '' ?>>Sync automatically</option><option value="false" <?= !$default_extension_sync ? 'selected' : '' ?>>Do not sync until selected</option></select></div>
		</div></section>
		<section class="tf-card"><div class="tf-card-title"><span class="tf-icon">↗</span>Tragofone Endpoint</div><div class="tf-card-body">
			<div class="tf-field"><div class="tf-label">URL source<span class="tf-help">Use the global endpoint or a tenant-specific URL.</span></div><select id="inherit_global_url" name="inherit_global_url" class="formfld"><option value="false" <?= !tragofone_normalizer::boolean($tenant['inherit_global_url'] ?? false) ? 'selected' : '' ?>>Tenant-specific URL</option><option value="true" <?= tragofone_normalizer::boolean($tenant['inherit_global_url'] ?? false) ? 'selected' : '' ?>>Inherit global URL</option></select></div>
			<div class="tf-field"><div class="tf-label">Server URL<span class="tf-help">HTTPS base URL for existing Tragofone customer APIs.</span></div><input id="base_url" class="formfld" name="base_url" value="<?= escape($tenant['base_url'] ?? '') ?>" placeholder="https://tragofone.example.com"></div>
		</div></section>
		<section class="tf-card wide"><div class="tf-card-title"><span class="tf-icon">✓</span>Tragofone Account &amp; Identity</div><div class="tf-card-body">
			<div class="tf-field"><div class="tf-label">Credential source<span class="tf-help">Credentials remain tenant-isolated even when inherited.</span></div><select id="inherit_global_credentials" name="inherit_global_credentials" class="formfld"><option value="false" <?= !tragofone_normalizer::boolean($tenant['inherit_global_credentials'] ?? false) ? 'selected' : '' ?>>Tenant-specific credentials</option><option value="true" <?= tragofone_normalizer::boolean($tenant['inherit_global_credentials'] ?? false) ? 'selected' : '' ?>>Inherit global credentials</option></select></div>
			<div class="tf-field"><div class="tf-label">Company admin username</div><input id="customer_username" class="formfld" name="customer_username" value="<?= escape($tenant['customer_username'] ?? '') ?>" autocomplete="off"></div>
			<div class="tf-field"><div class="tf-label">Company admin password<span class="tf-help"><?= !empty($tenant['encrypted_customer_password']) ? 'A password is stored. Leave blank to preserve it.' : 'Enter the existing Tragofone company-admin password.' ?></span></div><input id="customer_password" class="formfld" type="password" name="customer_password" autocomplete="new-password" placeholder="<?= !empty($tenant['encrypted_customer_password']) ? 'Stored securely — leave blank to preserve' : 'Required' ?>"></div>
			<div class="tf-field"><div class="tf-label">Expected customer ID<span class="tf-help">Synchronization stops if the authenticated company does not match.</span></div><input class="formfld" type="number" min="1" name="expected_customer_id" value="<?= escape($tenant['expected_customer_id'] ?? '') ?>" required></div>
			<div class="tf-field"><div class="tf-label">Default profile ID<span class="tf-help">Tragofone profile assigned when creating application users.</span></div><input class="formfld" type="number" min="1" name="default_profile_id" value="<?= escape($tenant['default_profile_id'] ?? '') ?>" required></div>
		</div></section>
		<section class="tf-card"><div class="tf-card-title"><span class="tf-icon">☎</span>SIP Provisioning</div><div class="tf-card-body">
			<div class="tf-field"><div class="tf-label">SIP server<span class="tf-help">Defaults to the FusionPBX domain when blank.</span></div><input class="formfld" name="sip_server" value="<?= escape($tenant['sip_server'] ?? '') ?>"></div>
			<div class="tf-field"><div class="tf-label">SIP port</div><input class="formfld" type="number" min="1" max="65535" name="sip_port" value="<?= escape($tenant['sip_port'] ?? '5061') ?>"></div>
			<div class="tf-field"><div class="tf-label">Transport</div><select class="formfld" name="sip_protocol"><?php foreach (['tls'=>'TLS','tcp'=>'TCP','udp'=>'UDP'] as $value=>$label) { ?><option value="<?= $value ?>" <?= ($tenant['sip_protocol'] ?? 'tls') === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div>
			<div class="tf-field"><div class="tf-label">Outbound proxy server<span class="tf-help">Optional. When blank, the resolved SIP server is sent to Tragofone.</span></div><input class="formfld" name="outbound_proxy_server" value="<?= escape($tenant['outbound_proxy_server'] ?? '') ?>" placeholder="Defaults to SIP server"></div>
			<div class="tf-field"><div class="tf-label">Outbound proxy port<span class="tf-help">Optional. When blank, the SIP port is sent to Tragofone.</span></div><input class="formfld" type="number" min="1" max="65535" name="outbound_proxy_port" value="<?= escape($tenant['outbound_proxy_port'] ?? '') ?>" placeholder="Defaults to SIP port"></div>
			<div class="tf-field"><div class="tf-label">Voicemail code<span class="tf-help">Used for one-touch FusionPBX voicemail.</span></div><input class="formfld" name="voicemail_code" value="<?= escape($tenant['voicemail_code'] ?? '*97') ?>"></div>
		</div></section>
		<section class="tf-card"><div class="tf-card-title"><span class="tf-icon">⌛</span>Lifecycle &amp; Safety</div><div class="tf-card-body">
			<div class="tf-field"><div class="tf-label">Deletion grace<span class="tf-help">Seconds to keep a disabled mapping before deleting the Tragofone user. Recommended: 86400.</span></div><input class="formfld" type="number" min="60" name="deletion_grace_seconds" value="<?= escape($tenant['deletion_grace_seconds'] ?? '86400') ?>"></div>
			<div class="tf-field"><div class="tf-label">Excluded extensions<span class="tf-help">Exclusions disable the Tragofone user and preserve its mapping. Re-including restores the same user.</span></div><a class="btn btn-default" href="extension_sync.php">Choose extensions</a></div>
		</div></section>
	</div>
	<div class="tf-footer"><a class="btn btn-default" href="index.php">Cancel</a><button class="btn btn-primary" type="submit">Save Tenant Settings</button></div>
	</form>
</div>
<script>
(function(){
	function applyInheritance(){
		var inheritUrl=document.getElementById('inherit_global_url').value==='true';
		var inheritCredentials=document.getElementById('inherit_global_credentials').value==='true';
		[['base_url',inheritUrl],['customer_username',inheritCredentials],['customer_password',inheritCredentials]].forEach(function(item){var field=document.getElementById(item[0]);field.readOnly=item[1];field.classList.toggle('tf-readonly',item[1]);});
	}
	document.getElementById('inherit_global_url').addEventListener('change',applyInheritance);
	document.getElementById('inherit_global_credentials').addEventListener('change',applyInheritance); applyInheritance();
})();
</script>
<?php require_once 'resources/footer.php'; ?>
