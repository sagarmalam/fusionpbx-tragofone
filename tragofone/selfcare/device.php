<?php
require_once __DIR__.'/_bootstrap.php';require_once __DIR__.'/_layout.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){sc_redirect('settings.php');}$session=sc_require_session();
if(!$sc_repository->verify_csrf($session,$_POST['csrf']??null)){http_response_code(403);exit;}
$qr=null;$error=null;
try{if(!$sc_repository->rate_limit(sc_remote_address(),'device-qr:'.(string)$session['subject_uuid'])){http_response_code(429);throw new RuntimeException('Too many QR requests.');}$qr=$sc_repository->device_login_qr($session);}
catch(Throwable){$error='The login QR code is temporarily unavailable. Please try again.';}
sc_render_header($session,'settings','Login on another device');
?>
<style nonce="<?=sc_escape($sc_nonce)?>">.sc-device-qr{display:block;width:min(100%,495px);height:auto;margin:18px auto;image-rendering:pixelated}</style>
<?php if($error){?><div class="sc-alert error"><?=sc_escape($error)?></div><?php }else{?><section class="sc-card sc-empty"><h2>Scan with Tragofone</h2><img class="sc-device-qr" src="data:<?=sc_escape($qr['mime_type'])?>;base64,<?=sc_escape($qr['base64'])?>" alt="Tragofone login QR code"><p>On the other device, open Tragofone and choose QR login.</p><p class="sc-muted">Anyone who can scan this code may be able to access your account. Keep it private.</p></section><?php }?>
<div class="sc-divider"></div><div class="sc-actions"><a class="sc-button secondary" href="settings.php">Back to settings</a><form method="post" action="device.php"><input type="hidden" name="csrf" value="<?=sc_escape($session['csrf'])?>"><button class="sc-button" type="submit">Refresh QR code</button></form></div>
<?php sc_render_footer(); ?>
