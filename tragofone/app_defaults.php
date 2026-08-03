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
	['selfcare', 'enabled', 'false', 'boolean'],
	['selfcare', 'brand_name', 'Tragofone', 'text'],
	['selfcare', 'light_background', 'F7F8FA', 'text'],
	['selfcare', 'light_foreground', '172033', 'text'],
	['selfcare', 'light_button', '1769E0', 'text'],
	['selfcare', 'light_button_foreground', 'FFFFFF', 'text'],
	['selfcare', 'dark_background', '10141D', 'text'],
	['selfcare', 'dark_foreground', 'F4F7FB', 'text'],
	['selfcare', 'dark_button', '6EA8FE', 'text'],
	['selfcare', 'dark_button_foreground', '08101F', 'text'],
	['selfcare', 'external_forwarding', 'false', 'boolean'],
	['selfcare', 'session_idle_seconds', '900', 'numeric'],
	['selfcare', 'session_absolute_seconds', '3600', 'numeric'],
];

foreach ($defaults as [$category, $subcategory, $value, $type]) {
	$settings = new settings(['database' => $database, 'domain_uuid' => $_SESSION['domain_uuid'] ?? null]);
	$settings->set('tragofone_'.$category, $subcategory, $value, $type);
}

unset($defaults, $settings, $category, $subcategory, $value, $type);
