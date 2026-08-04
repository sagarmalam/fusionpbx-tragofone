<?php

$document_root = dirname(__DIR__, 3);
require_once $document_root.'/resources/require.php';
require_once dirname(__DIR__).'/resources/classes/bootstrap.php';

header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
$sc_nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'; img-src 'self' data:; media-src 'self'; style-src 'self' 'nonce-{$sc_nonce}'");

$database = new database();
$sc_crypto = tragofone_crypto::from_environment();
$sc_repository = new tragofone_selfcare_repository($database, $sc_crypto);

function sc_remote_address(): string { return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'); }
function sc_user_agent(): string { return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 512); }
function sc_cookie_path(): string { return (defined('PROJECT_PATH') ? PROJECT_PATH : '').'/app/tragofone/selfcare'; }
function sc_redirect(string $target): never { header('Location: '.$target, true, 303); exit; }
function sc_escape(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function sc_bool(mixed $value): bool { return tragofone_normalizer::boolean($value) || in_array($value, ['t','on'], true); }
function sc_set_cookie(string $value): void {
	setcookie('tfn_sc', $value, ['expires'=>0, 'path'=>sc_cookie_path(), 'secure'=>true, 'httponly'=>true, 'samesite'=>'Lax']);
}
function sc_clear_cookie(): void {
	setcookie('tfn_sc', '', ['expires'=>1, 'path'=>sc_cookie_path(), 'secure'=>true, 'httponly'=>true, 'samesite'=>'Lax']);
}
function sc_require_session(): array {
	global $sc_repository;
	$session = $sc_repository->authenticate($_COOKIE['tfn_sc'] ?? null, sc_remote_address(), sc_user_agent());
	if ($session === null) { sc_clear_cookie(); sc_redirect('expired.php'); }
	return $session;
}
function sc_message(): ?array {
	if (empty($_GET['message']) || is_array($_GET['message'])) { return null; }
	$type = ($_GET['status'] ?? '') === 'error' ? 'error' : 'success';
	return ['type'=>$type, 'text'=>substr((string) $_GET['message'], 0, 240)];
}
