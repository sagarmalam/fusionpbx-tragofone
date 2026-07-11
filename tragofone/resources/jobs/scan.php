<?php
require_once __DIR__.'/bootstrap.php';
$scanner = new tragofone_scanner($store); $total = 0;
foreach ($store->enabled_tenants() as $tenant) { $total += $scanner->scan_tenant($tenant, $tenant['last_sync_at'] ?? null); }
echo "Queued {$total} changed extension(s).\n";
