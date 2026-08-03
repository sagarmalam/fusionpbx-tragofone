<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php'; require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_extension_sync_view')) { echo 'access denied'; exit; }
$database = new database(); $domain_uuid = $_SESSION['domain_uuid']; $parameters = ['domain_uuid'=>$domain_uuid]; $error_message = null;
$tenant_rows = $database->select('select * from v_tragofone_tenants where domain_uuid=:domain_uuid', $parameters, 'all') ?: []; $tenant = $tenant_rows[0] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!permission_exists('tragofone_extension_sync_edit')) { echo 'access denied'; exit; }
	$token_validator = new token; if (!$token_validator->validate($_SERVER['PHP_SELF'])) { http_response_code(403); echo 'invalid token'; exit; }
	try {
	$posted_extensions = $_POST['sync_extensions'] ?? []; if (!is_array($posted_extensions)) { $posted_extensions = []; }
	$posted_selfcare = $_POST['selfcare_policy'] ?? []; if (!is_array($posted_selfcare)) { $posted_selfcare = []; }
	$selected = array_fill_keys(array_map('strval', $posted_extensions), true);
	$extensions = $database->select('select extension_uuid from v_extensions where domain_uuid=:domain_uuid', $parameters, 'all') ?: [];
	$policy_rows = $database->select('select * from v_tragofone_extension_policies where domain_uuid=:domain_uuid', $parameters, 'all') ?: [];
	$policies = []; foreach ($policy_rows as $policy) { $policies[$policy['extension_uuid']] = $policy; }
	foreach ($extensions as $extension) {
		$extension_uuid = $extension['extension_uuid']; $existing = $policies[$extension_uuid] ?? [];
		$selfcare_policy = tragofone_selfcare_policy::normalize($posted_selfcare[$extension_uuid] ?? tragofone_selfcare_policy::INHERIT);
		$selfcare_changed = tragofone_selfcare_policy::normalize($existing['selfcare_policy'] ?? tragofone_selfcare_policy::INHERIT) !== $selfcare_policy;
		$record = [
			'policy_uuid'=>$existing['policy_uuid'] ?? uuid(), 'domain_uuid'=>$domain_uuid, 'extension_uuid'=>$extension_uuid,
			// PDO treats execute-array values as strings. Use PostgreSQL boolean
			// literals so an unchecked box is not bound as an empty string.
			'sync_enabled'=>isset($selected[$extension_uuid]) ? 'true' : 'false', 'selfcare_policy'=>$selfcare_policy,
			'insert_date'=>$existing['insert_date'] ?? date('c'),
			'insert_user'=>$existing['insert_user'] ?? $_SESSION['user_uuid'], 'update_date'=>date('c'), 'update_user'=>$_SESSION['user_uuid'],
		];
		if ($database->execute('insert into v_tragofone_extension_policies (policy_uuid,domain_uuid,extension_uuid,sync_enabled,selfcare_policy,insert_date,insert_user,update_date,update_user) values (:policy_uuid,:domain_uuid,:extension_uuid,:sync_enabled,:selfcare_policy,:insert_date,:insert_user,:update_date,:update_user) on conflict (policy_uuid) do update set sync_enabled=excluded.sync_enabled,selfcare_policy=excluded.selfcare_policy,update_date=excluded.update_date,update_user=excluded.update_user', $record) === false) {
			throw new RuntimeException('Unable to save the extension synchronization policy.');
		}
		if ($selfcare_changed) { $database->execute('update v_tragofone_selfcare_sessions set revoked_at=now() where subject_uuid in (select subject_uuid from v_tragofone_selfcare_subjects where domain_uuid=:domain_uuid and extension_uuid=:extension_uuid) and revoked_at is null', ['domain_uuid'=>$domain_uuid,'extension_uuid'=>$extension_uuid]); }
	}
	$queued = 0;
	if (tragofone_normalizer::boolean($tenant['enabled'] ?? false) && !tragofone_normalizer::boolean($tenant['paused'] ?? false)) {
		$store = new tragofone_fusionpbx_store($database); $resolved_tenant = $store->tenant($domain_uuid);
		if ($resolved_tenant !== null) { $queued = (new tragofone_scanner($store))->scan_tenant($resolved_tenant, null); }
	}
	header('Location: extension_sync.php?saved=1&queued='.$queued); exit;
	} catch (Throwable $error) {
		$error_message = tragofone_redactor::message($error->getMessage());
	}
}
$default_sync = !array_key_exists('default_extension_sync', $tenant) || $tenant['default_extension_sync'] === null ? true : tragofone_normalizer::boolean($tenant['default_extension_sync']);
$global_rows=$database->select('select * from v_tragofone_global_config order by update_date desc nulls last limit 1',[],'all')?:[];$global=$global_rows[0]??[];
$global_selfcare_policy=tragofone_selfcare_policy::global($global);$tenant_selfcare_policy=tragofone_selfcare_policy::normalize($tenant['selfcare_policy']??tragofone_selfcare_policy::INHERIT);
$extensions = $database->select("select e.extension_uuid,e.extension,e.effective_caller_id_name,e.enabled,p.sync_enabled,p.selfcare_policy,m.tragofone_user_id,m.sync_status,m.last_synced_at from v_extensions e left join v_tragofone_extension_policies p on p.domain_uuid=e.domain_uuid and p.extension_uuid=e.extension_uuid left join v_tragofone_extension_mappings m on m.domain_uuid=e.domain_uuid and m.extension_uuid=e.extension_uuid and m.deleted_at is null where e.domain_uuid=:domain_uuid order by e.extension", $parameters, 'all') ?: [];
$selected_count = 0;$selfcare_count=0; foreach ($extensions as &$extension) { $extension['effective_sync'] = $extension['sync_enabled'] === null ? $default_sync : tragofone_normalizer::boolean($extension['sync_enabled']);$extension['selfcare_policy']=tragofone_selfcare_policy::normalize($extension['selfcare_policy']??tragofone_selfcare_policy::INHERIT);$extension['effective_selfcare']=tragofone_selfcare_policy::enabled($global_selfcare_policy,$tenant_selfcare_policy,$extension['selfcare_policy']); if ($extension['effective_sync']) { $selected_count++; }if($extension['effective_sync']&&$extension['effective_selfcare']){$selfcare_count++;} } unset($extension);
$token_generator = new token; $token = $token_generator->create($_SERVER['PHP_SELF']); require_once 'resources/header.php';
$tragofone_page = 'extensions'; $tragofone_title = 'Extension Synchronization';
$tragofone_subtitle = 'Choose which FusionPBX SIP extensions may be provisioned into Tragofone.';
?>
<style>
.tf-wrap{max-width:1180px;margin:0 auto 32px}.tf-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin:8px 0 18px}.tf-head h2{margin:0 0 5px}.tf-subtle{color:#667085;font-size:13px}.tf-actions{display:flex;gap:8px;flex-wrap:wrap}.tf-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px}.tf-stat{background:var(--card-background-color,#fff);border:1px solid var(--border-color,#d0d5dd);border-radius:9px;padding:14px 16px}.tf-stat strong{font-size:22px;display:block}.tf-card{background:var(--card-background-color,#fff);border:1px solid var(--border-color,#d0d5dd);border-radius:10px;overflow:hidden}.tf-toolbar{display:flex;justify-content:space-between;gap:12px;padding:13px 15px;border-bottom:1px solid var(--border-color,#eaecf0);align-items:center}.tf-toolbar-left{display:flex;gap:8px;align-items:center}.tf-search{min-width:260px}.tf-table{width:100%;border-collapse:collapse}.tf-table th,.tf-table td{padding:12px 14px;border-bottom:1px solid var(--border-color,#eaecf0);text-align:left;vertical-align:middle}.tf-table th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#667085;background:#f8fafc}.tf-table tr:last-child td{border-bottom:0}.tf-extension{font-weight:700}.tf-name{display:block;color:#667085;font-size:12px;margin-top:2px}.tf-badge{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:600}.tf-badge.ok{color:#067647;background:#ecfdf3}.tf-badge.off{color:#475467;background:#f2f4f7}.tf-badge.warn{color:#b54708;background:#fffaeb}.tf-switch{width:18px;height:18px}.tf-policy{min-width:105px}.tf-alert{padding:12px 15px;border-radius:8px;margin-bottom:16px;color:#067647;background:#ecfdf3;border:1px solid #abefc6}.tf-alert.error{color:#b42318;background:#fef3f2;border-color:#fecdca}.tf-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px}.tf-note{color:#667085;font-size:12px}@media(max-width:760px){.tf-head,.tf-toolbar,.tf-footer{flex-direction:column;align-items:stretch}.tf-summary{grid-template-columns:1fr}.tf-search{min-width:0;width:100%}.tf-table{display:block;overflow-x:auto}}
</style>
<div class="tfn-shell">
	<?php require __DIR__.'/resources/views/navigation.php'; ?>
	<?php if (isset($_GET['saved'])) { ?><div class="tf-alert">Extension selection saved. <?= (int) ($_GET['queued'] ?? 0) ?> synchronization job(s) queued.</div><?php } ?>
	<?php if ($error_message !== null) { ?><div class="tf-alert error"><?= escape($error_message) ?></div><?php } ?>
	<div class="tf-summary"><div class="tf-stat"><strong><?= count($extensions) ?></strong><span class="tf-subtle">FusionPBX extensions</span></div><div class="tf-stat"><strong id="selected_count"><?= $selected_count ?></strong><span class="tf-subtle">Selected for Tragofone</span></div><div class="tf-stat"><strong id="excluded_count"><?= count($extensions)-$selected_count ?></strong><span class="tf-subtle">Excluded</span></div><div class="tf-stat"><strong><?= $selfcare_count ?></strong><span class="tf-subtle">Self-care enabled</span></div></div>
	<form method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>">
	<div class="tf-card"><div class="tf-toolbar"><div class="tf-toolbar-left"><button class="btn btn-default" id="select_all" type="button">Select all</button><button class="btn btn-default" id="select_none" type="button">Select none</button></div><input id="extension_search" class="formfld tf-search" type="search" placeholder="Search extension or name"></div>
	<table class="tf-table"><thead><tr><th style="width:65px">Sync</th><th>Extension</th><th>FusionPBX</th><th>Self-care</th><th>Tragofone mapping</th><th>Last synchronized</th></tr></thead><tbody id="extension_rows">
	<?php foreach ($extensions as $extension) { $pbx_enabled=tragofone_normalizer::boolean($extension['enabled'] ?? false); ?><tr data-search="<?= escape(strtolower(($extension['extension'] ?? '').' '.($extension['effective_caller_id_name'] ?? ''))) ?>">
		<td><input class="tf-switch sync-choice" type="checkbox" name="sync_extensions[]" value="<?= escape($extension['extension_uuid']) ?>" <?= $extension['effective_sync'] ? 'checked' : '' ?> <?= !permission_exists('tragofone_extension_sync_edit') ? 'disabled' : '' ?>></td>
		<td><span class="tf-extension"><?= escape($extension['extension']) ?></span><span class="tf-name"><?= escape($extension['effective_caller_id_name'] ?: 'No caller-ID name') ?></span></td>
		<td><span class="tf-badge <?= $pbx_enabled ? 'ok' : 'off' ?>"><?= $pbx_enabled ? 'Enabled' : 'Disabled' ?></span></td>
		<td><select class="formfld tf-policy" name="selfcare_policy[<?= escape($extension['extension_uuid']) ?>]" <?= !permission_exists('tragofone_extension_sync_edit')?'disabled':'' ?>><?php foreach(['inherit'=>'Inherit','yes'=>'Yes','no'=>'No'] as $value=>$label){?><option value="<?= $value ?>" <?= $extension['selfcare_policy']===$value?'selected':'' ?>><?= $label ?></option><?php }?></select><span class="tf-name">Effective: <?= $extension['effective_selfcare']?'Yes':'No' ?></span></td>
		<td><?php if (!empty($extension['tragofone_user_id'])) { ?><span class="tf-badge <?= in_array($extension['sync_status'], ['synchronized','created'], true) ? 'ok' : (in_array($extension['sync_status'], ['excluded','disabled'], true) ? 'off' : 'warn') ?>"><?= escape($extension['sync_status']) ?></span><span class="tf-name">User ID <?= escape($extension['tragofone_user_id']) ?></span><?php } else { ?><span class="tf-badge off">Not provisioned</span><?php } ?></td>
		<td><?= escape($extension['last_synced_at'] ?: '—') ?></td>
	</tr><?php } ?>
	<?php if ($extensions === []) { ?><tr><td colspan="6"><div class="tfn-empty"><b>No extensions in this domain.</b><br>Verify the selected domain or create a SIP extension under Accounts → Extensions.</div></td></tr><?php } ?>
	</tbody></table></div>
	<div class="tf-footer"><span class="tf-note">Excluded mapped users are disabled, not deleted. Re-selecting them restores the same Tragofone user ID.</span><?php if (permission_exists('tragofone_extension_sync_edit')) { ?><button class="btn btn-primary" type="submit">Save Extension Selection</button><?php } ?></div>
	</form>
</div>
<script>
(function(){
	var choices=Array.prototype.slice.call(document.querySelectorAll('.sync-choice')), count=document.getElementById('selected_count'), excluded=document.getElementById('excluded_count');
	function update(){var selected=choices.filter(function(c){return c.checked;}).length;count.textContent=selected;excluded.textContent=choices.length-selected;}
	document.getElementById('select_all').addEventListener('click',function(){choices.forEach(function(c){if(!c.disabled)c.checked=true;});update();});
	document.getElementById('select_none').addEventListener('click',function(){choices.forEach(function(c){if(!c.disabled)c.checked=false;});update();});
	document.getElementById('extension_search').addEventListener('input',function(){var q=this.value.toLowerCase();document.querySelectorAll('#extension_rows tr[data-search]').forEach(function(row){row.style.display=row.getAttribute('data-search').indexOf(q)>=0?'':'none';});});
	choices.forEach(function(c){c.addEventListener('change',update);});
})();
</script>
<?php require_once 'resources/footer.php'; ?>
