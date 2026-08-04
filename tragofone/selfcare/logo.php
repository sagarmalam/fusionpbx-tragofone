<?php
require_once __DIR__.'/_bootstrap.php';
$logo=$sc_repository->logo(); if($logo===null){http_response_code(404);exit;}
header('Content-Type: '.$logo['mime']);header('Content-Length: '.strlen($logo['data']));header('Cache-Control: public,max-age=3600,immutable');echo $logo['data'];
