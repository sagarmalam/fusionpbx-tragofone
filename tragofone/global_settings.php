<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php';
require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_global_edit')) { echo 'access denied'; exit; }

$database = new database(); $error_message = null;
$rows = $database->select('select * from v_tragofone_global_config limit 1', [], 'all') ?: [];
$config = array_replace(tragofone_selfcare_theme::DEFAULTS, $rows[0] ?? []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token_validator = new token;
	if (!$token_validator->validate($_SERVER['PHP_SELF'])) { http_response_code(403); echo 'invalid token'; exit; }
	try {
		$restore = isset($_POST['restore_selfcare_defaults']);
		$rotate_salts = isset($_POST['rotate_selfcare_salts']);
		$base_url = tragofone_url_validator::validate(trim((string) ($_POST['base_url'] ?? '')));
		$customer_username = trim((string) ($_POST['customer_username'] ?? ''));
		if ($customer_username !== '' && empty($_POST['customer_password']) && empty($config['encrypted_customer_password'])) {
			throw new InvalidArgumentException('Global company-admin password is required when a global username is configured.');
		}
		$selfcare_enabled = !$restore && tragofone_normalizer::boolean($_POST['selfcare_enabled'] ?? false);
		$selfcare_base_url = $restore ? '' : trim((string) ($_POST['selfcare_base_url'] ?? ''));
		if ($selfcare_enabled) {
			$selfcare_base_url = tragofone_url_validator::validate($selfcare_base_url);
			$parts = parse_url($selfcare_base_url);
			if (!empty($parts['query']) || !empty($parts['fragment'])) { throw new InvalidArgumentException('Self-care base URL cannot contain a query string or fragment.'); }
		}
		$theme_input = $restore ? tragofone_selfcare_theme::DEFAULTS : [
			'selfcare_brand_name'=>$_POST['selfcare_brand_name'] ?? '',
			'selfcare_light_background'=>$_POST['selfcare_light_background'] ?? '', 'selfcare_light_foreground'=>$_POST['selfcare_light_foreground'] ?? '',
			'selfcare_light_button'=>$_POST['selfcare_light_button'] ?? '', 'selfcare_light_button_foreground'=>$_POST['selfcare_light_button_foreground'] ?? '',
			'selfcare_dark_background'=>$_POST['selfcare_dark_background'] ?? '', 'selfcare_dark_foreground'=>$_POST['selfcare_dark_foreground'] ?? '',
			'selfcare_dark_button'=>$_POST['selfcare_dark_button'] ?? '', 'selfcare_dark_button_foreground'=>$_POST['selfcare_dark_button_foreground'] ?? '',
		];
		$theme = tragofone_selfcare_theme::from_config($theme_input);
		$logo_base64 = $restore || isset($_POST['remove_selfcare_logo']) ? null : ($config['selfcare_brand_logo_base64'] ?? null);
		$logo_mime = $restore || isset($_POST['remove_selfcare_logo']) ? null : ($config['selfcare_brand_logo_mime'] ?? null);
		$logo_upload_error = (int) ($_FILES['selfcare_brand_logo']['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($logo_upload_error !== UPLOAD_ERR_NO_FILE && $logo_upload_error !== UPLOAD_ERR_OK) { throw new InvalidArgumentException('Brand logo upload failed. Check the PHP upload size limits and try again.'); }
		if (!empty($_FILES['selfcare_brand_logo']['tmp_name']) && $logo_upload_error === UPLOAD_ERR_OK) {
			if ((int) $_FILES['selfcare_brand_logo']['size'] > 262144) { throw new InvalidArgumentException('Brand logo must be 256 KB or smaller.'); }
			$finfo = new finfo(FILEINFO_MIME_TYPE); $logo_mime = $finfo->file($_FILES['selfcare_brand_logo']['tmp_name']);
			if (!in_array($logo_mime, ['image/png','image/jpeg','image/webp'], true)) { throw new InvalidArgumentException('Brand logo must be PNG, JPEG, or WebP.'); }
			$dimensions = getimagesize($_FILES['selfcare_brand_logo']['tmp_name']);
			if ($dimensions === false || $dimensions[0] > 512 || $dimensions[1] > 512) { throw new InvalidArgumentException('Brand logo dimensions cannot exceed 512 × 512 pixels.'); }
			$data = file_get_contents($_FILES['selfcare_brand_logo']['tmp_name']);
			if ($data === false) { throw new RuntimeException('Unable to read uploaded brand logo.'); }
			$logo_base64 = base64_encode($data);
		}
		$prefixes = $restore ? '' : trim((string) ($_POST['selfcare_external_prefixes'] ?? ''));
		foreach (array_filter(array_map('trim', explode(',', $prefixes))) as $prefix) {
			if (!preg_match('/^\+?\d{1,15}$/', $prefix)) { throw new InvalidArgumentException('External forwarding prefixes must be comma-separated digits with an optional leading +.'); }
		}
		$idle = $restore ? 900 : (int) ($_POST['selfcare_session_idle_seconds'] ?? 900);
		$absolute = $restore ? 3600 : (int) ($_POST['selfcare_session_absolute_seconds'] ?? 3600);
		if ($idle < 300 || $idle > 3600 || $absolute < $idle || $absolute > 86400) { throw new InvalidArgumentException('Session idle timeout must be 5–60 minutes and absolute timeout must be between idle timeout and 24 hours.'); }

		$brand_fields = ['selfcare_enabled','selfcare_base_url','selfcare_brand_name','selfcare_brand_logo_base64','selfcare_brand_logo_mime','selfcare_light_background','selfcare_light_foreground','selfcare_light_button','selfcare_light_button_foreground','selfcare_dark_background','selfcare_dark_foreground','selfcare_dark_button','selfcare_dark_button_foreground'];
		$old_brand = array_intersect_key($config, array_flip($brand_fields));
		$old_brand['selfcare_enabled'] = tragofone_normalizer::boolean($old_brand['selfcare_enabled'] ?? false) ? 'true' : 'false';
		$old_brand_hash = tragofone_normalizer::hash($old_brand);
		$new_brand = [
			'selfcare_enabled'=>$selfcare_enabled ? 'true' : 'false', 'selfcare_base_url'=>$selfcare_base_url, 'selfcare_brand_name'=>$theme['brand_name'],
			'selfcare_brand_logo_base64'=>$logo_base64, 'selfcare_brand_logo_mime'=>$logo_mime,
			'selfcare_light_background'=>$theme['l_bg'], 'selfcare_light_foreground'=>$theme['l_fg'],
			'selfcare_light_button'=>$theme['l_btn'], 'selfcare_light_button_foreground'=>$theme['l_btn_fg'],
			'selfcare_dark_background'=>$theme['d_bg'], 'selfcare_dark_foreground'=>$theme['d_fg'],
			'selfcare_dark_button'=>$theme['d_btn'], 'selfcare_dark_button_foreground'=>$theme['d_btn_fg'],
		];
		$brand_changed = $old_brand_hash !== tragofone_normalizer::hash($new_brand);
		$record = [
			'config_uuid'=>$config['config_uuid'] ?? uuid(), 'base_url'=>$base_url, 'customer_username'=>$customer_username,
			'verify_tls'=>'true', 'sip_port'=>(int) ($_POST['sip_port'] ?? 5061), 'sip_protocol'=>$_POST['sip_protocol'] ?? 'tls',
			'voicemail_code'=>trim((string) ($_POST['voicemail_code'] ?? '*97')), ...$new_brand,
			'selfcare_brand_version'=>max(1, (int) ($config['selfcare_brand_version'] ?? 1) + ($brand_changed ? 1 : 0)),
			'selfcare_external_forwarding'=>$restore ? 'false' : (tragofone_normalizer::boolean($_POST['selfcare_external_forwarding'] ?? false) ? 'true' : 'false'),
			'selfcare_external_prefixes'=>$prefixes, 'selfcare_session_idle_seconds'=>$idle, 'selfcare_session_absolute_seconds'=>$absolute,
			'insert_date'=>$config['insert_date'] ?? date('c'), 'insert_user'=>$config['insert_user'] ?? $_SESSION['user_uuid'],
			'update_date'=>date('c'), 'update_user'=>$_SESSION['user_uuid'],
		];
		if (!empty($_POST['customer_password'])) { $record['encrypted_customer_password'] = tragofone_crypto::from_environment()->encrypt($_POST['customer_password']); }
		$columns = array_keys($record); $updates = array_values(array_diff($columns, ['config_uuid','insert_date','insert_user']));
		$sql = 'insert into v_tragofone_global_config ('.implode(', ', $columns).') values (:'.implode(', :', $columns).') on conflict (config_uuid) do update set '.implode(', ', array_map(static fn($column)=>$column.' = excluded.'.$column, $updates));
		if ($database->execute($sql, $record) === false) { throw new RuntimeException('Unable to save global settings.'); }
		$database->execute('insert into v_tragofone_audit (audit_uuid,action,entity_type,entity_uuid,summary,insert_date,insert_user) values (:audit_uuid,:action,\'global_config\',:entity_uuid,:summary,now(),:insert_user)', [
			'audit_uuid'=>uuid(), 'action'=>$rotate_salts ? 'selfcare.salts.rotate' : 'selfcare.global.update',
			'entity_uuid'=>$record['config_uuid'], 'summary'=>$rotate_salts ? 'Global self-care settings saved and all self-care salts rotated.' : 'Global Tragofone and self-care settings updated.',
			'insert_user'=>$_SESSION['user_uuid'],
		]);
		if ($rotate_salts) {
			$database->execute('update v_tragofone_selfcare_subjects set active=false,update_date=now() where active=true');
			$database->execute('update v_tragofone_selfcare_sessions set revoked_at=now() where revoked_at is null');
		}
		$queued = 0;
		if ($brand_changed || $rotate_salts) {
			$database->execute("delete from v_tragofone_snapshots where entity_type='extension'");
			$store = new tragofone_fusionpbx_store($database); $scanner = new tragofone_scanner($store);
			foreach ($store->enabled_tenants() as $tenant) { $queued += $scanner->scan_tenant($tenant, null); }
		}
		header('Location: global_settings.php?saved=1&queued='.$queued); exit;
	} catch (Throwable $error) {
		$error_message = tragofone_redactor::message($error->getMessage());
		foreach (['base_url','customer_username','sip_port','sip_protocol','voicemail_code','selfcare_enabled','selfcare_base_url','selfcare_brand_name','selfcare_light_background','selfcare_light_foreground','selfcare_light_button','selfcare_light_button_foreground','selfcare_dark_background','selfcare_dark_foreground','selfcare_dark_button','selfcare_dark_button_foreground','selfcare_external_forwarding','selfcare_external_prefixes','selfcare_session_idle_seconds','selfcare_session_absolute_seconds'] as $field) {
			if (array_key_exists($field, $_POST)) { $config[$field] = $_POST[$field]; }
		}
	}
}

$has_password = !empty($config['encrypted_customer_password']);
$has_credentials = trim((string) ($config['customer_username'] ?? '')) !== '' && $has_password;
$token_generator = new token; $token = $token_generator->create($_SERVER['PHP_SELF']);
require_once 'resources/header.php';
$tragofone_page = 'global'; $tragofone_title = 'Global Tragofone Settings';
$tragofone_subtitle = 'Shared API defaults and globally branded self-care controlled by FusionPBX Superadmins.';
?>
<style>
.tg-alert{padding:12px 15px;border-radius:8px;margin-bottom:16px}.tg-alert.ok{color:#067647;background:#ecfdf3;border:1px solid #abefc6}.tg-alert.error{color:#b42318;background:#fef3f2;border:1px solid #fecdca}.tg-guide{padding:16px 18px;border:1px solid #b2ddff;border-radius:10px;background:#eff8ff;color:#1849a9;margin-bottom:16px}.tg-guide strong{display:block;color:#175cd3;margin-bottom:4px}.tg-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.tg-card{background:var(--card-background-color,#fff);border:1px solid var(--border-color,#d0d5dd);border-radius:10px;overflow:hidden}.tg-card.wide{grid-column:1/-1}.tg-card-title{padding:15px 18px;border-bottom:1px solid var(--border-color,#eaecf0);font-weight:700;display:flex;align-items:center;gap:9px}.tg-card-body{padding:18px}.tg-field{display:grid;grid-template-columns:190px minmax(0,1fr);gap:18px;margin-bottom:16px;align-items:start}.tg-field:last-child{margin-bottom:0}.tg-label{font-weight:600;padding-top:8px}.tg-help{display:block;color:#667085;font-size:12px;line-height:1.4;margin-top:5px}.tg-field .formfld{width:100%;max-width:none}.tg-icon{width:25px;height:25px;border-radius:7px;background:#f2f4f7;display:inline-flex;align-items:center;justify-content:center;color:#344054}.tg-credential-state{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:600}.tg-credential-state.ready{color:#067647;background:#ecfdf3}.tg-credential-state.empty{color:#475467;background:#f2f4f7}.tg-steps{margin:0;padding-left:20px;color:#475467;line-height:1.7}.tg-footer{display:flex;justify-content:flex-end;gap:9px;flex-wrap:wrap;margin-top:18px;padding:14px 0}.tg-theme-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.tg-color{display:grid;grid-template-columns:1fr 54px;gap:8px}.tg-color input[type=color]{width:54px;height:36px;padding:2px}.tg-preview{border-radius:18px;padding:16px;min-height:220px;display:flex;flex-direction:column;gap:13px}.tg-preview-head{display:flex;align-items:center;gap:9px;font-weight:700}.tg-preview-logo{width:30px;height:30px;border-radius:8px;background:currentColor;opacity:.18}.tg-preview-card{border:1px solid currentColor;border-radius:13px;padding:15px;opacity:.9}.tg-preview-button{border:0;border-radius:10px;padding:11px 14px;font-weight:700}.tg-url-example{font-family:monospace;font-size:11px;overflow-wrap:anywhere;background:#f8fafc;padding:12px;border-radius:8px}@media(max-width:850px){.tg-grid,.tg-theme-grid{grid-template-columns:1fr}.tg-card.wide{grid-column:auto}.tg-field{grid-template-columns:1fr;gap:5px}.tg-label{padding-top:0}}
</style>
<div class="tfn-shell">
	<?php require __DIR__.'/resources/views/navigation.php'; ?>
	<?php if (isset($_GET['saved'])) { ?><div class="tg-alert ok">Global settings saved. <?= (int) ($_GET['queued'] ?? 0) ?> user synchronization job(s) queued.</div><?php } ?>
	<?php if ($error_message !== null) { ?><div class="tg-alert error"><?= escape($error_message) ?></div><?php } ?>
	<form method="post" enctype="multipart/form-data"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>">
	<div class="tg-grid">
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">↗</span>Global API Endpoint</div><div class="tg-card-body">
			<div class="tg-field"><div class="tg-label">Tragofone server URL<span class="tg-help">Inherited only by tenants selecting the global endpoint.</span></div><input class="formfld" name="base_url" value="<?= escape($config['base_url'] ?? '') ?>" placeholder="https://tragofone.example.com" required></div>
			<div class="tg-field"><div class="tg-label">TLS verification<span class="tg-help">Certificates are always verified.</span></div><input class="formfld" value="Enabled" readonly></div>
		</div></section>
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">✓</span>Global Tragofone Credentials <span class="tg-credential-state <?= $has_credentials ? 'ready' : 'empty' ?>"><?= $has_credentials ? 'Configured' : 'Not configured' ?></span></div><div class="tg-card-body">
			<div class="tg-field"><div class="tg-label">Company admin username</div><input class="formfld" name="customer_username" value="<?= escape($config['customer_username'] ?? '') ?>" autocomplete="off"></div>
			<div class="tg-field"><div class="tg-label">Company admin password<span class="tg-help"><?= $has_password ? 'Stored securely; leave blank to preserve.' : 'Required when a username is configured.' ?></span></div><input class="formfld" type="password" name="customer_password" autocomplete="new-password"></div>
		</div></section>
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">☎</span>Provisioning Fallbacks</div><div class="tg-card-body">
			<div class="tg-field"><div class="tg-label">SIP port</div><input class="formfld" type="number" min="1" max="65535" name="sip_port" value="<?= escape($config['sip_port'] ?? 5061) ?>"></div>
			<div class="tg-field"><div class="tg-label">SIP transport</div><select class="formfld" name="sip_protocol"><?php foreach (['tls'=>'TLS','tcp'=>'TCP','udp'=>'UDP'] as $value=>$label) { ?><option value="<?= $value ?>" <?= ($config['sip_protocol'] ?? 'tls') === $value ? 'selected' : '' ?>><?= $label ?></option><?php } ?></select></div>
			<div class="tg-field"><div class="tg-label">Voicemail code</div><input class="formfld" name="voicemail_code" value="<?= escape($config['voicemail_code'] ?? '*97') ?>"></div>
		</div></section>
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">i</span>Inheritance</div><div class="tg-card-body"><ol class="tg-steps"><li>Global API URL and credentials remain optional tenant defaults.</li><li>Self-care branding is always global and cannot be overridden by a tenant.</li><li>Only synchronized, enabled extensions receive Account URLs.</li></ol></div></section>

		<section class="tg-card wide"><div class="tg-card-title"><span class="tg-icon">◐</span>Self-Care Portal</div><div class="tg-card-body">
			<div class="tg-guide"><strong>Global branding</strong>These values are signed into every eligible Tragofone Account URL. Saving branding changes queues reprovisioning across enabled companies.</div>
			<div class="tg-field"><div class="tg-label">Enable portal</div><label><input type="checkbox" name="selfcare_enabled" value="true" <?= tragofone_normalizer::boolean($config['selfcare_enabled'] ?? false) ? 'checked' : '' ?>> Enable globally</label></div>
			<div class="tg-field"><div class="tg-label">Public portal base URL<span class="tg-help">Normally https://pbx.example/app/tragofone/selfcare</span></div><input class="formfld" name="selfcare_base_url" value="<?= escape($config['selfcare_base_url'] ?? '') ?>" placeholder="https://pbx.example/app/tragofone/selfcare"></div>
			<div class="tg-field"><div class="tg-label">Portal name</div><input class="formfld" maxlength="40" name="selfcare_brand_name" value="<?= escape($config['selfcare_brand_name'] ?? 'Tragofone') ?>"></div>
			<div class="tg-field"><div class="tg-label">Brand logo<span class="tg-help">PNG, JPEG, or WebP; maximum 256 KB and 512 × 512.</span></div><div><input type="file" name="selfcare_brand_logo" accept="image/png,image/jpeg,image/webp"><?php if (!empty($config['selfcare_brand_logo_base64'])) { ?><label style="display:block;margin-top:8px"><input type="checkbox" name="remove_selfcare_logo" value="true"> Remove stored logo</label><?php } ?></div></div>
			<div class="tg-theme-grid"><?php foreach (['light'=>'Light theme','dark'=>'Dark theme'] as $mode=>$label) { ?><div><b><?= $label ?></b><?php foreach (['background'=>'Background','foreground'=>'Text','button'=>'Button','button_foreground'=>'Button text'] as $field=>$field_label) { $name='selfcare_'.$mode.'_'.$field; $value=$config[$name] ?? tragofone_selfcare_theme::DEFAULTS[$name]; ?><div class="tg-field" style="grid-template-columns:140px 1fr;margin-top:10px"><div class="tg-label"><?= $field_label ?></div><div class="tg-color"><input class="formfld tg-color-text" data-theme="<?= $mode ?>" name="<?= $name ?>" value="<?= escape($value) ?>" maxlength="7"><input type="color" data-color-for="<?= $name ?>" value="#<?= escape(ltrim($value,'#')) ?>"></div></div><?php } ?></div><?php } ?></div>
			<div class="tg-theme-grid" style="margin-top:18px"><div id="preview-light" class="tg-preview"><div class="tg-preview-head"><span class="tg-preview-logo"></span><span class="preview-name"></span></div><div class="tg-preview-card"><b>Extension 1001</b><div style="margin-top:6px">Calls and voicemail in one place.</div></div><button type="button" class="tg-preview-button">Open voicemail</button></div><div id="preview-dark" class="tg-preview"><div class="tg-preview-head"><span class="tg-preview-logo"></span><span class="preview-name"></span></div><div class="tg-preview-card"><b>Extension 1001</b><div style="margin-top:6px">Calls and voicemail in one place.</div></div><button type="button" class="tg-preview-button">Open voicemail</button></div></div>
		</div></section>
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">⇢</span>Forwarding Policy</div><div class="tg-card-body"><div class="tg-field"><div class="tg-label">Default forwarding policy</div><select class="formfld" name="selfcare_external_forwarding"><option value="false" <?= !tragofone_normalizer::boolean($config['selfcare_external_forwarding'] ?? false) ? 'selected' : '' ?>>Same-company extensions only</option><option value="true" <?= tragofone_normalizer::boolean($config['selfcare_external_forwarding'] ?? false) ? 'selected' : '' ?>>Same-company and approved external numbers</option></select></div><div class="tg-field"><div class="tg-label">Allowed prefixes<span class="tg-help">Comma-separated, for example +44,+1. Used only when approved external forwarding is selected.</span></div><input class="formfld" name="selfcare_external_prefixes" value="<?= escape($config['selfcare_external_prefixes'] ?? '') ?>"></div></div></section>
		<section class="tg-card"><div class="tg-card-title"><span class="tg-icon">◷</span>Session Policy</div><div class="tg-card-body"><div class="tg-field"><div class="tg-label">Idle timeout<span class="tg-help">Seconds; 300–3600.</span></div><input class="formfld" type="number" min="300" max="3600" name="selfcare_session_idle_seconds" value="<?= escape($config['selfcare_session_idle_seconds'] ?? 900) ?>"></div><div class="tg-field"><div class="tg-label">Absolute timeout<span class="tg-help">Seconds; up to 86400.</span></div><input class="formfld" type="number" min="300" max="86400" name="selfcare_session_absolute_seconds" value="<?= escape($config['selfcare_session_absolute_seconds'] ?? 3600) ?>"></div></div></section>
		<section class="tg-card wide"><div class="tg-card-title"><span class="tg-icon">#</span>Generated Account URL</div><div class="tg-card-body"><div class="tg-url-example"><?= escape(rtrim((string) ($config['selfcare_base_url'] ?? 'https://pbx.example/app/tragofone/selfcare'), '/').'/launch.php?scid={user}&brand_name={name}&l_bg={light-bg}&…&brand_sig={signature}&tragofone_salt={user-salt}') ?></div><span class="tg-help">The worker replaces placeholders with signed global values and a unique user subject and salt.</span></div></section>
	</div>
	<div class="tg-footer"><a class="btn btn-default" href="index.php">Cancel</a><button class="btn btn-default" type="submit" name="rotate_selfcare_salts" value="true" onclick="return confirm('Rotate every self-care salt? Existing sessions will end and all eligible users will receive a new Account URL.')">Rotate Self-Care Salts</button><button class="btn btn-default" type="submit" name="restore_selfcare_defaults" value="true" onclick="return confirm('Restore the global self-care theme and disable the portal?')">Restore Self-Care Defaults</button><button class="btn btn-primary" type="submit" onclick="return confirm('Saving may update the Account URL for every synchronized Tragofone user. Continue?')">Save Global Defaults</button></div>
	</form>
</div>
<script>
(function(){function val(name){var el=document.querySelector('[name="'+name+'"]');return (el&&el.value?el.value:'').replace('#','');}function render(mode){var box=document.getElementById('preview-'+mode),p='selfcare_'+mode+'_';if(!box)return;box.style.background='#'+val(p+'background');box.style.color='#'+val(p+'foreground');var button=box.querySelector('button');button.style.background='#'+val(p+'button');button.style.color='#'+val(p+'button_foreground');box.querySelector('.preview-name').textContent=document.querySelector('[name=selfcare_brand_name]').value||'Tragofone';}document.querySelectorAll('.tg-color-text').forEach(function(text){var color=document.querySelector('[data-color-for="'+text.name+'"]');text.addEventListener('input',function(){if(/^[#]?[0-9a-f]{6}$/i.test(text.value)){color.value='#'+text.value.replace('#','');render(text.dataset.theme);}});color.addEventListener('input',function(){text.value=color.value.substring(1).toUpperCase();render(text.dataset.theme);});});document.querySelector('[name=selfcare_brand_name]').addEventListener('input',function(){render('light');render('dark');});render('light');render('dark');})();
</script>
<?php require_once 'resources/footer.php'; ?>
