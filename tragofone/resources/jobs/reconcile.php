<?php
require_once __DIR__.'/bootstrap.php';

// Reconciliation intentionally reuses scanner normalization and idempotent jobs.
$domain = null;
foreach ($argv as $argument) { if (str_starts_with($argument, '--domain=')) { $domain = substr($argument, 9); } }
$scanner = new tragofone_scanner($store); $total = 0;
foreach ($store->enabled_tenants() as $tenant) {
	if ($domain !== null && $tenant['domain_uuid'] !== $domain) { continue; }
	$total += $scanner->scan_tenant($tenant, null);
}
echo "Reconciliation queued {$total} repair job(s).\n";
