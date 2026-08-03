<?php
require_once __DIR__.'/_bootstrap.php';

try {
	$scid = isset($_GET['scid']) && !is_array($_GET['scid']) ? (string) $_GET['scid'] : '';
	if (!$sc_repository->rate_limit(sc_remote_address(), $scid)) { http_response_code(429); throw new RuntimeException('Too many launch attempts.'); }
	$time = isset($_GET['tragofone_time']) && !is_array($_GET['tragofone_time']) ? (string) $_GET['tragofone_time'] : '';
	$hash = isset($_GET['tragofone_hash']) && !is_array($_GET['tragofone_hash']) ? strtolower((string) $_GET['tragofone_hash']) : '';
	$signature = isset($_GET['brand_sig']) && !is_array($_GET['brand_sig']) ? (string) $_GET['brand_sig'] : '';
	if (!preg_match('/^\d{9,12}$/', $time) || !preg_match('/^[a-f0-9]{32}$/', $hash)) { throw new RuntimeException('Invalid signed launch.'); }
	$timestamp = (int) $time; $now = time();
	if ($timestamp < $now - 120 || $timestamp > $now + 60) { throw new RuntimeException('Signed launch expired.'); }
	$subject = $sc_repository->subject($scid); if ($subject === null) { throw new RuntimeException('Self-care access is unavailable.'); }
	$salt = $sc_crypto->decrypt((string) $subject['encrypted_salt']);
	if (!hash_equals(md5($salt.$time), $hash)) { throw new RuntimeException('Invalid signed launch.'); }
	$payload = tragofone_selfcare_theme::signed_payload($_GET);
	if (!tragofone_selfcare_theme::verify($scid, $salt, $payload, $signature)) { throw new RuntimeException('Invalid branding signature.'); }
	$theme = tragofone_selfcare_theme::launch_theme($_GET);
	if ((int) $theme['brand_v'] !== (int) $subject['brand_version']) { throw new RuntimeException('Account URL is no longer current.'); }
	$config = $sc_repository->global_config();
	if (!tragofone_selfcare_policy::enabled(tragofone_selfcare_policy::global($config), $subject['domain_selfcare_policy'] ?? 'inherit', $subject['user_selfcare_policy'] ?? 'inherit')) { throw new RuntimeException('Self-care is disabled.'); }
	tragofone_selfcare_theme::validate_logo_url((string) $theme['brand_logo'], (string) $config['selfcare_base_url']);
	if (!$sc_repository->consume_assertion($scid, $time, $hash)) { throw new RuntimeException('Signed launch was already used.'); }
	$session = $sc_repository->create_session($subject, $theme, (int) ($config['selfcare_session_idle_seconds'] ?? 900), (int) ($config['selfcare_session_absolute_seconds'] ?? 3600), sc_remote_address(), sc_user_agent());
	sc_set_cookie($session['cookie']); sc_redirect('index.php');
} catch (Throwable $error) {
	http_response_code(http_response_code() === 429 ? 429 : 403);
	require __DIR__.'/signed_error.php';
}
