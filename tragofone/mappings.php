<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php';
if (!permission_exists('tragofone_mapping_view')) { echo 'access denied'; exit; }
$database = new database(); $params = ['domain_uuid' => $_SESSION['domain_uuid']];
$extensions = $database->select('select extension,tragofone_username,tragofone_user_id,sync_status,last_synced_at,last_error from v_tragofone_extension_mappings where domain_uuid=:domain_uuid and deleted_at is null order by extension', $params, 'all') ?: [];
$contacts = $database->select('select contact_uuid,tragofone_ed_id,sync_status,last_synced_at,last_error from v_tragofone_contact_mappings where domain_uuid=:domain_uuid and deleted_at is null order by last_synced_at desc', $params, 'all') ?: [];
$dids = $database->select('select d.destination_uuid,d.extension_uuid,d.did_number,d.enabled,d.last_seen_at,e.extension from v_tragofone_did_mappings d left join v_extensions e on e.domain_uuid=d.domain_uuid and e.extension_uuid=d.extension_uuid where d.domain_uuid=:domain_uuid order by d.did_number', $params, 'all') ?: [];
$badge_class = static function (?string $status): string {
	if (in_array($status, ['synchronized','created','completed'], true)) { return 'ok'; }
	if (in_array($status, ['retry','exclude_pending','include_pending','disable_pending','deletion_pending','delete_pending'], true)) { return 'warn'; }
	if (in_array($status, ['dead','failed'], true)) { return 'error'; }
	return 'off';
};
require_once 'resources/header.php';
$tragofone_page = 'mappings'; $tragofone_title = 'Mappings';
$tragofone_subtitle = 'Companion-owned links between FusionPBX entities and Tragofone records.';
?>
<style>
.tm-grid{display:grid;gap:16px}.tm-id{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px}.tm-error{display:block;color:#b42318;font-size:11px;margin-top:4px;max-width:330px}.tm-muted{color:#667085;font-size:12px}
</style>
<div class="tfn-shell">
	<?php require __DIR__.'/resources/views/navigation.php'; ?>
	<div class="tm-grid">
		<section class="tfn-card"><div class="tfn-card-title">Extensions <span class="tfn-badge off"><?= count($extensions) ?></span></div><div class="tfn-table-wrap"><table class="tfn-table"><thead><tr><th>Extension</th><th>Tragofone username</th><th>User ID</th><th>Status</th><th>Last synchronized</th></tr></thead><tbody>
		<?php foreach ($extensions as $mapping) { ?><tr><td><b><?= escape($mapping['extension']) ?></b></td><td><?= escape($mapping['tragofone_username'] ?: '—') ?></td><td class="tm-id"><?= escape($mapping['tragofone_user_id'] ?: '—') ?></td><td><span class="tfn-badge <?= $badge_class($mapping['sync_status'] ?? null) ?>"><?= escape($mapping['sync_status'] ?: 'unknown') ?></span><?php if (!empty($mapping['last_error'])) { ?><span class="tm-error"><?= escape($mapping['last_error']) ?></span><?php } ?></td><td class="tm-muted"><?= escape($mapping['last_synced_at'] ?: '—') ?></td></tr><?php } ?>
		<?php if ($extensions === []) { ?><tr><td colspan="5"><div class="tfn-empty">No extension mappings exist for this domain.</div></td></tr><?php } ?>
		</tbody></table></div></section>
		<section class="tfn-card"><div class="tfn-card-title">Direct DID Assignments <span class="tfn-badge off"><?= count($dids) ?></span></div><div class="tfn-table-wrap"><table class="tfn-table"><thead><tr><th>DID</th><th>Extension</th><th>State</th><th>Last observed</th><th>Destination UUID</th></tr></thead><tbody>
		<?php foreach ($dids as $mapping) { $did_enabled = in_array($mapping['enabled'], [true,1,'1','true','t'], true); ?><tr><td><b><?= escape($mapping['did_number']) ?></b></td><td><?= escape($mapping['extension'] ?: $mapping['extension_uuid']) ?></td><td><span class="tfn-badge <?= $did_enabled ? 'ok' : 'off' ?>"><?= $did_enabled ? 'Enabled' : 'Removed' ?></span></td><td class="tm-muted"><?= escape($mapping['last_seen_at'] ?: '—') ?></td><td class="tm-id"><?= escape($mapping['destination_uuid']) ?></td></tr><?php } ?>
		<?php if ($dids === []) { ?><tr><td colspan="5"><div class="tfn-empty">No direct DID mappings exist for this domain.</div></td></tr><?php } ?>
		</tbody></table></div></section>
		<section class="tfn-card"><div class="tfn-card-title">Enterprise Contacts <span class="tfn-badge off"><?= count($contacts) ?></span></div><div class="tfn-table-wrap"><table class="tfn-table"><thead><tr><th>FusionPBX contact UUID</th><th>Enterprise directory ID</th><th>Status</th><th>Last synchronized</th></tr></thead><tbody>
		<?php foreach ($contacts as $mapping) { ?><tr><td class="tm-id"><?= escape($mapping['contact_uuid']) ?></td><td class="tm-id"><?= escape($mapping['tragofone_ed_id']) ?></td><td><span class="tfn-badge <?= $badge_class($mapping['sync_status'] ?? null) ?>"><?= escape($mapping['sync_status'] ?: 'unknown') ?></span><?php if (!empty($mapping['last_error'])) { ?><span class="tm-error"><?= escape($mapping['last_error']) ?></span><?php } ?></td><td class="tm-muted"><?= escape($mapping['last_synced_at'] ?: '—') ?></td></tr><?php } ?>
		<?php if ($contacts === []) { ?><tr><td colspan="4"><div class="tfn-empty">No enterprise contact mappings exist for this domain.</div></td></tr><?php } ?>
		</tbody></table></div></section>
	</div>
</div>
<?php require_once 'resources/footer.php'; ?>
