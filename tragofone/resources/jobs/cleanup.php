<?php
require_once __DIR__.'/bootstrap.php';
$database->execute("delete from v_tragofone_sync_jobs where status = 'completed' and completed_at < now() - interval '30 days'");
echo "Expired completed jobs removed.\n";
