<?php
// Shared Tragofone module navigation. Set $tragofone_page, $tragofone_title,
// and $tragofone_subtitle before including this file.
$tragofone_page = $tragofone_page ?? 'overview';
$tragofone_title = $tragofone_title ?? 'Tragofone Integration';
$tragofone_subtitle = $tragofone_subtitle ?? 'FusionPBX provisioning companion.';
$tragofone_navigation = [
	['overview', 'Overview', 'index.php', 'tragofone_tenant_view'],
	['global', 'Global', 'global_settings.php', 'tragofone_global_edit'],
	['tenant', 'Tenant', 'tenant_settings.php', 'tragofone_tenant_edit'],
	['extensions', 'Extensions', 'extension_sync.php', 'tragofone_extension_sync_view'],
	['mappings', 'Mappings', 'mappings.php', 'tragofone_mapping_view'],
	['jobs', 'Jobs', 'jobs.php', 'tragofone_job_view'],
	['reconciliation', 'Reconciliation', 'reconciliation.php', 'tragofone_manual_sync'],
];
?>
<style>
.tfn-shell{max-width:1180px;margin:0 auto 32px}.tfn-breadcrumb{display:flex;align-items:center;gap:7px;color:#667085;font-size:12px;margin:6px 0 10px}.tfn-breadcrumb a{color:#475467;text-decoration:none}.tfn-breadcrumb a:hover{text-decoration:underline}.tfn-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:16px}.tfn-title{margin:0 0 5px;font-size:28px;line-height:1.2;color:var(--text-color,#344054)}.tfn-subtitle{color:#667085;font-size:13px;line-height:1.45}.tfn-head-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.tfn-domain{display:inline-flex;align-items:center;max-width:310px;padding:6px 10px;border-radius:999px;background:#f2f4f7;color:#344054;font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.tfn-back{gap:6px;white-space:nowrap}.tfn-nav{display:flex;gap:5px;padding:6px;margin-bottom:18px;border:1px solid var(--border-color,#d0d5dd);border-radius:10px;background:var(--card-background-color,#fff);overflow-x:auto;-webkit-overflow-scrolling:touch}.tfn-nav a{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:7px 12px;border-radius:7px;color:#475467;text-decoration:none;font-size:13px;font-weight:600;white-space:nowrap}.tfn-nav a:hover{background:#f2f4f7;color:#344054}.tfn-nav a.active{background:#1570ef;color:#fff}.tfn-empty{padding:28px 18px;text-align:center;color:#667085}.tfn-card{background:var(--card-background-color,#fff);border:1px solid var(--border-color,#d0d5dd);border-radius:10px;overflow:hidden}.tfn-card-title{padding:14px 17px;border-bottom:1px solid var(--border-color,#eaecf0);font-weight:700}.tfn-card-body{padding:17px}.tfn-table-wrap{overflow-x:auto}.tfn-table{width:100%;border-collapse:collapse}.tfn-table th,.tfn-table td{padding:11px 13px;border-bottom:1px solid var(--border-color,#eaecf0);text-align:left;vertical-align:top}.tfn-table th{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#667085;background:#f8fafc}.tfn-table tr:last-child td{border-bottom:0}.tfn-badge{display:inline-flex;padding:4px 8px;border-radius:999px;font-size:11px;font-weight:600}.tfn-badge.ok{color:#067647;background:#ecfdf3}.tfn-badge.warn{color:#b54708;background:#fffaeb}.tfn-badge.error{color:#b42318;background:#fef3f2}.tfn-badge.off{color:#475467;background:#f2f4f7}
/* FusionPBX themes size anchors, buttons, and submit controls differently. */
.tfn-shell .btn{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:40px!important;padding:8px 14px!important;margin:0!important;border-radius:7px!important;font-family:inherit!important;font-size:13px!important;font-weight:600!important;line-height:1.2!important;text-align:center!important;text-decoration:none!important;white-space:nowrap;box-sizing:border-box!important;vertical-align:middle!important}
.tfn-shell .btn-default{color:var(--text-color,#344054)!important;background:var(--card-background-color,#fff)!important;border:1px solid var(--border-color,#d0d5dd)!important;box-shadow:none!important}.tfn-shell .btn-default:hover{background:#f2f4f7!important;border-color:#98a2b3!important}.tfn-shell .btn-primary{color:#fff!important;background:#1570ef!important;border:1px solid #1570ef!important;box-shadow:none!important}.tfn-shell .btn-primary:hover{background:#175cd3!important;border-color:#175cd3!important}.tfn-shell .btn-danger{color:#fff!important;background:#d92d20!important;border:1px solid #d92d20!important;box-shadow:none!important}.tfn-shell .btn:focus-visible{outline:3px solid rgba(21,112,239,.22)!important;outline-offset:2px}.tfn-shell input.btn{height:40px!important}.tfn-shell .btn+.btn{margin-left:0!important}
@media(max-width:760px){.tfn-shell{margin:0 12px 26px}.tfn-head{flex-direction:column;gap:10px}.tfn-head-actions{justify-content:flex-start}.tfn-title{font-size:24px}.tfn-domain{max-width:250px}.tfn-nav{margin-left:-2px;margin-right:-2px}.tfn-nav a{padding:7px 10px}.tfn-table th,.tfn-table td{padding:10px}.tg-footer,.tf-footer{flex-direction:column;align-items:stretch}.tg-footer .btn,.tf-footer .btn{width:100%}}
</style>
<div class="tfn-breadcrumb"><a href="index.php">Tragofone Integration</a><?php if ($tragofone_page !== 'overview') { ?><span>›</span><span><?= escape($tragofone_title) ?></span><?php } ?></div>
<div class="tfn-head">
	<div><h2 class="tfn-title"><?= escape($tragofone_title) ?></h2><div class="tfn-subtitle"><?= escape($tragofone_subtitle) ?></div></div>
	<div class="tfn-head-actions"><span class="tfn-domain">Domain: <?= escape($_SESSION['domain_name'] ?? 'Unknown') ?></span><?php if ($tragofone_page !== 'overview') { ?><a class="btn btn-default tfn-back" href="index.php">← Back to Overview</a><?php } ?></div>
</div>
<nav class="tfn-nav" aria-label="Tragofone module navigation">
	<?php foreach ($tragofone_navigation as [$page, $label, $url, $permission]) { if (!permission_exists($permission)) { continue; } ?><a href="<?= escape($url) ?>" class="<?= $tragofone_page === $page ? 'active' : '' ?>" <?= $tragofone_page === $page ? 'aria-current="page"' : '' ?>><?= escape($label) ?></a><?php } ?>
</nav>
<?php unset($tragofone_navigation, $page, $label, $url, $permission); ?>
