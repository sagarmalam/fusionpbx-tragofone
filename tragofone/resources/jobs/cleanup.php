<?php
require_once __DIR__.'/bootstrap.php';
$database->execute("delete from v_tragofone_sync_jobs where status = 'completed' and completed_at < now() - interval '30 days'");
$database->execute("delete from v_tragofone_selfcare_sessions where absolute_expires_at < now() - interval '1 day' or revoked_at < now() - interval '1 day'");
$database->execute("delete from v_tragofone_selfcare_assertions where expires_at < now() - interval '1 day'");
$database->execute("delete from v_tragofone_selfcare_rate_limits where update_date < now() - interval '1 day'");
echo "Expired completed jobs and self-care security records removed.\n";
