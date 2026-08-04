<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php';
require_once __DIR__.'/resources/classes/bootstrap.php';
if (!permission_exists('tragofone_qr_view')) { echo 'access denied'; exit; }

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$database = new database(); $domain_uuid = $_SESSION['domain_uuid']; $extension_uuid = (string) ($_GET['id'] ?? '');
if (!is_uuid($extension_uuid)) { http_response_code(400); echo 'invalid extension'; exit; }
$rows = $database->select(
	"select m.*,e.extension,e.effective_caller_id_name,e.number_alias,e.enabled,p.sync_enabled,
	(select u.user_email from v_extension_users eu join v_users u on u.user_uuid=eu.user_uuid and u.domain_uuid=eu.domain_uuid where eu.domain_uuid=e.domain_uuid and eu.extension_uuid=e.extension_uuid and u.user_enabled=true and u.user_email is not null and u.user_email<>'' order by u.username limit 1) as assigned_user_email,
	(select v.voicemail_mail_to from v_voicemails v where v.domain_uuid=e.domain_uuid and v.voicemail_id=(case when e.number_alias ~ '^[0-9]+$' and e.number_alias<>'' then e.number_alias else e.extension end) limit 1) as voicemail_email
	from v_tragofone_extension_mappings m join v_extensions e on e.domain_uuid=m.domain_uuid and e.extension_uuid=m.extension_uuid left join v_tragofone_extension_policies p on p.domain_uuid=e.domain_uuid and p.extension_uuid=e.extension_uuid
	where m.domain_uuid=:domain_uuid and m.extension_uuid=:extension_uuid and m.deleted_at is null limit 1",
	['domain_uuid'=>$domain_uuid, 'extension_uuid'=>$extension_uuid], 'all'
) ?: [];
$mapping = $rows[0] ?? null;
if ($mapping === null) { http_response_code(404); echo 'mapping not found'; exit; }

$default_email = '';
foreach ([$mapping['assigned_user_email'] ?? '', $mapping['voicemail_email'] ?? ''] as $email_list) {
	foreach (preg_split('/[,;]+/', (string) $email_list) ?: [] as $candidate) {
		$candidate = trim($candidate);
		if (filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) { $default_email = $candidate; break 2; }
	}
}

$message = null; $error_message = null; $qr = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token_validator = new token;
	if (!$token_validator->validate($_SERVER['PHP_SELF'])) { http_response_code(403); echo 'invalid token'; exit; }
	$action = (string) ($_POST['action'] ?? 'preview');
	try {
		if (($mapping['sync_status'] ?? '') !== 'synchronized') { throw new RuntimeException('QR login is available only after the user reaches synchronized status.'); }
		if (!tragofone_normalizer::boolean($mapping['enabled'] ?? false) || empty($mapping['tragofone_user_id'])) { throw new RuntimeException('QR login is unavailable for this inactive extension.'); }
		$store = new tragofone_fusionpbx_store($database); $tenant = $store->tenant($domain_uuid);
		if ($tenant === null) { throw new RuntimeException('The Tragofone tenant is disabled or paused.'); }
		$selected = $mapping['sync_enabled'] === null
			? (!array_key_exists('default_extension_sync', $tenant) || $tenant['default_extension_sync'] === null || tragofone_normalizer::boolean($tenant['default_extension_sync']))
			: tragofone_normalizer::boolean($mapping['sync_enabled']);
		if (!$selected) { throw new RuntimeException('QR login is unavailable because this extension is excluded from synchronization.'); }
		$client = tragofone_customer_client_factory::create($tenant, tragofone_crypto::from_environment());
		$qr = tragofone_qr_code::from_response($client->get_qr_code((int) $mapping['tragofone_user_id']));
		$filename = 'tragofone-qr-'.preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $mapping['extension']).'.'.$qr['extension'];
		if ($action === 'download') {
			if (!permission_exists('tragofone_qr_download')) { echo 'access denied'; exit; }
			tragofone_qr_audit($database, $domain_uuid, $extension_uuid, 'tragofone.qr.download', 'Tragofone QR downloaded for extension '.$mapping['extension'].'.');
			header('Content-Type: '.$qr['mime_type']); header('Content-Disposition: attachment; filename="'.$filename.'"'); header('Content-Length: '.strlen($qr['bytes'])); echo $qr['bytes']; exit;
		}
		if ($action === 'email') {
			if (!permission_exists('tragofone_qr_email')) { echo 'access denied'; exit; }
			$recipient = trim((string) ($_POST['recipient'] ?? ''));
			if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) { throw new InvalidArgumentException('Enter one valid recipient email address.'); }
			$settings = new settings(['database'=>$database,'domain_uuid'=>$domain_uuid,'user_uuid'=>$_SESSION['user_uuid']]);
			$email = new email(['database'=>$database,'settings'=>$settings,'domain_uuid'=>$domain_uuid,'user_uuid'=>$_SESSION['user_uuid']]);
			$email->method = 'direct'; $email->debug_level = 0; $email->recipients = $recipient;
			$email->subject = 'Your Tragofone QR login - Extension '.$mapping['extension'];
			$email->body = '<p>Hello,</p><p>Your Tragofone QR login for extension <strong>'.htmlspecialchars((string) $mapping['extension'], ENT_QUOTES, 'UTF-8').'</strong> is attached.</p><p>Open Tragofone and use its QR login option. Treat this QR code like a password and delete the email after enrollment.</p>';
			$email->attachments = [['name'=>$filename,'type'=>$qr['extension'],'mime_type'=>$qr['mime_type'],'base64'=>$qr['base64'],'path'=>'','cid'=>'']];
			if ($email->send() !== true) { throw new RuntimeException('FusionPBX could not send the QR email. Verify its SMTP settings and try again.'); }
			tragofone_qr_audit($database, $domain_uuid, $extension_uuid, 'tragofone.qr.email', 'Tragofone QR emailed for extension '.$mapping['extension'].'.');
			$message = 'The QR code was sent successfully.'; $default_email = $recipient; $qr = null;
		} else {
			tragofone_qr_audit($database, $domain_uuid, $extension_uuid, 'tragofone.qr.preview', 'Tragofone QR viewed for extension '.$mapping['extension'].'.');
		}
	} catch (Throwable $error) {
		if (($error instanceof tragofone_api_exception && $error->http_status === 401) || $error instanceof tragofone_tenant_identity_exception || $error instanceof tragofone_tenant_configuration_exception) {
			(new tragofone_fusionpbx_store($database))->pause_tenant($domain_uuid, $error->getMessage());
		}
		$error_message = tragofone_redactor::message($error->getMessage()); $qr = null;
	}
}

function tragofone_qr_audit(database $database, string $domain_uuid, string $extension_uuid, string $action, string $summary): void {
	$database->execute('insert into v_tragofone_audit (audit_uuid,domain_uuid,action,entity_type,entity_uuid,summary,correlation_id,insert_date,insert_user) values (:audit_uuid,:domain_uuid,:action,\'extension\',:entity_uuid,:summary,:correlation_id,now(),:insert_user)', ['audit_uuid'=>uuid(),'domain_uuid'=>$domain_uuid,'action'=>$action,'entity_uuid'=>$extension_uuid,'summary'=>$summary,'correlation_id'=>uuid(),'insert_user'=>$_SESSION['user_uuid']]);
}

$token_generator = new token; $token = $token_generator->create($_SERVER['PHP_SELF']);
require_once 'resources/header.php';
$tragofone_page = 'extensions'; $tragofone_title = 'Tragofone QR Login';
$tragofone_subtitle = 'Secure enrollment for extension '.$mapping['extension'].'.';
?>
<style>
.tq-grid{display:grid;grid-template-columns:minmax(280px,.85fr) minmax(320px,1.15fr);gap:16px}.tq-panel{padding:20px}.tq-summary{display:grid;gap:12px}.tq-row{display:flex;justify-content:space-between;gap:18px;padding-bottom:11px;border-bottom:1px solid #eaecf0}.tq-row:last-child{border-bottom:0}.tq-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}.tq-actions form{margin:0}.tq-qr{display:grid;place-items:center;min-height:320px;border:1px dashed #98a2b3;border-radius:12px;background:#fff;padding:20px}.tq-qr img{width:min(100%,300px);height:auto;image-rendering:pixelated}.tq-empty{text-align:center;color:#667085;max-width:360px}.tq-warning{margin-top:15px;padding:12px;border-radius:8px;color:#b54708;background:#fffaeb}.tq-email{display:grid;grid-template-columns:1fr auto;gap:8px;margin-top:16px}.tq-email .formfld{width:100%}@media(max-width:760px){.tq-grid{grid-template-columns:1fr}.tq-email{grid-template-columns:1fr}.tq-qr{min-height:260px}}
</style>
<div class="tfn-shell">
	<?php require __DIR__.'/resources/views/navigation.php'; ?>
	<?php if ($message !== null) { ?><div class="alert alert-success"><?= escape($message) ?></div><?php } ?>
	<?php if ($error_message !== null) { ?><div class="alert alert-danger"><?= escape($error_message) ?></div><?php } ?>
	<div class="tq-grid">
		<section class="tfn-card tq-panel"><div class="tq-summary"><div class="tq-row"><span>Extension</span><b><?= escape($mapping['extension']) ?></b></div><div class="tq-row"><span>User</span><b><?= escape($mapping['effective_caller_id_name'] ?: $mapping['extension']) ?></b></div><div class="tq-row"><span>Mapping status</span><span class="tfn-badge <?= ($mapping['sync_status'] ?? '') === 'synchronized' ? 'ok' : 'warn' ?>"><?= escape($mapping['sync_status'] ?? 'unknown') ?></span></div></div><div class="tq-warning"><b>Sensitive credential</b><br>Anyone with this QR code may be able to enroll as this user. Share it only with the intended recipient.</div><div class="tq-actions"><a class="btn btn-default" href="extension_sync.php">← Back to Extensions</a></div></section>
		<section class="tfn-card tq-panel"><div class="tq-qr"><?php if ($qr !== null) { ?><img src="data:<?= escape($qr['mime_type']) ?>;base64,<?= escape($qr['base64']) ?>" alt="Tragofone QR login for extension <?= escape($mapping['extension']) ?>"><?php } else { ?><div class="tq-empty"><b>QR code is not loaded</b><p>Fetches a fresh QR code directly from Tragofone. It is not stored on the PBX.</p></div><?php } ?></div>
			<div class="tq-actions"><form method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>"><input type="hidden" name="action" value="preview"><button class="btn btn-primary" type="submit"><?= $qr === null ? 'Show QR Code' : 'Refresh QR Code' ?></button></form><?php if ($qr !== null && permission_exists('tragofone_qr_download')) { ?><form method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>"><input type="hidden" name="action" value="download"><button class="btn btn-default" type="submit">Download QR</button></form><?php } ?></div>
			<?php if (permission_exists('tragofone_qr_email')) { ?><form class="tq-email" method="post"><input type="hidden" name="<?= escape($token['name']) ?>" value="<?= escape($token['hash']) ?>"><input type="hidden" name="action" value="email"><input class="formfld" type="email" name="recipient" value="<?= escape($default_email) ?>" required placeholder="user@example.com" aria-label="QR recipient email"><button class="btn btn-default" type="submit">Email QR</button></form><div class="tfn-subtitle" style="margin-top:7px">Uses FusionPBX SMTP settings and sends immediately without storing the QR in the email queue.</div><?php } ?>
		</section>
	</div>
</div>
<?php require_once 'resources/footer.php'; ?>
