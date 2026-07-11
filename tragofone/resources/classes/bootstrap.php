<?php

foreach ([
	'tragofone_http_transport.php', 'tragofone_redactor.php', 'tragofone_url_validator.php',
	'tragofone_client.php', 'tragofone_crypto.php', 'tragofone_config.php', 'tragofone_normalizer.php',
	'tragofone_did_resolver.php', 'tragofone_feature_policy.php', 'tragofone_contact_mapper.php',
	'tragofone_retry_policy.php', 'tragofone_store.php', 'tragofone_scanner.php', 'tragofone_worker.php',
] as $file) { require_once __DIR__.'/'.$file; }
