<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php'; if (!permission_exists('tragofone_manual_sync')) { echo access_denied(); exit; }
require_once 'resources/header.php';
?><h2>Reconciliation</h2><p>Full reconciliation compares companion-owned mappings with current FusionPBX entities. It never adopts or deletes unrelated Tragofone records.</p><p>Run from the service host:</p><pre>php /var/www/fusionpbx/app/tragofone/resources/jobs/reconcile.php --domain=<?= escape($_SESSION['domain_uuid']) ?></pre><?php require_once 'resources/footer.php'; ?>
