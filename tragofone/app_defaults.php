<?php

// Defaults are installed by FusionPBX App Defaults and are never used to store secrets.
$defaults = [
	['global', 'enabled', 'false', 'boolean'],
	['global', 'base_url', '', 'text'],
	['global', 'verify_tls', 'true', 'boolean'],
	['global', 'connect_timeout', '10', 'numeric'],
	['global', 'request_timeout', '30', 'numeric'],
	['sync', 'scan_interval', '30', 'numeric'],
	['sync', 'reconcile_interval', '21600', 'numeric'],
	['sync', 'deletion_grace_seconds', '86400', 'numeric'],
	['sync', 'retry_schedule', '60,300,900,3600,10800,21600', 'array'],
	['features', 'voicemail_code', '*97', 'text'],
];

foreach ($defaults as [$category, $subcategory, $value, $type]) {
	$settings = new settings(['database' => $database, 'domain_uuid' => $_SESSION['domain_uuid'] ?? null]);
	$settings->set('tragofone_'.$category, $subcategory, $value, $type);
}

unset($defaults, $settings, $category, $subcategory, $value, $type);
