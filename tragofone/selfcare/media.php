<?php
require_once __DIR__.'/_bootstrap.php';$download=isset($_GET['download']);
if($download&&isset($_GET['token'])&&!is_array($_GET['token'])){$message=$sc_repository->voicemail_download_from_token((string)$_GET['token']);if($message===null){http_response_code(404);exit;}$sc_repository->stream_downloaded_voicemail($message);}
$session=sc_require_session();$id=isset($_GET['id'])&&!is_array($_GET['id'])?(string)$_GET['id']:'';$message=$sc_repository->voicemail_message_from_handle($session,$id);if($message===null){http_response_code(404);exit;}$sc_repository->stream_voicemail($session,(string)$message['voicemail_message_uuid'],$download);
