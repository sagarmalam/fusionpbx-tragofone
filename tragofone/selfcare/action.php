<?php
require_once __DIR__.'/_bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}$session=sc_require_session();
if(!$sc_repository->verify_csrf($session,$_POST['csrf']??null)){http_response_code(403);exit;}
$action=isset($_POST['action'])&&!is_array($_POST['action'])?(string)$_POST['action']:'';$target='index.php';
try{
	switch($action){
		case 'save_calls':$sc_repository->update_call_state($session,$_POST);$target='calls.php';$message='Call handling saved.';break;
		case 'save_voicemail':$sc_repository->update_voicemail_settings($session,(string)($_POST['email']??''),(string)($_POST['pin']??''));$target='settings.php';$message='Voicemail settings saved.';break;
		case 'message_read':$owned=$sc_repository->voicemail_message_from_handle($session,(string)($_POST['id']??''));if($owned===null){throw new RuntimeException('Voicemail message was not found.');}$sc_repository->set_message_read($session,(string)$owned['voicemail_message_uuid'],sc_bool($_POST['read']??false));$target='voicemail.php';$message='Voicemail status updated.';break;
		case 'message_delete':$owned=$sc_repository->voicemail_message_from_handle($session,(string)($_POST['id']??''));if($owned===null){throw new RuntimeException('Voicemail message was not found.');}$sc_repository->delete_message($session,(string)$owned['voicemail_message_uuid']);$target='voicemail.php';$message='Voicemail deleted.';break;
		default:throw new InvalidArgumentException('Unsupported self-care action.');
	}
	sc_redirect($target.'?status=success&message='.rawurlencode($message));
}catch(Throwable $error){$message=tragofone_redactor::message($error->getMessage());sc_redirect($target.'?status=error&message='.rawurlencode($message));}
