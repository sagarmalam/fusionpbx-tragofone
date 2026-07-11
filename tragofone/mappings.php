<?php
require_once dirname(__DIR__, 2).'/resources/check_auth.php'; if (!permission_exists('tragofone_mapping_view')) { echo access_denied(); exit; }
require_once 'resources/header.php'; $database = new database(); $params = ['domain_uuid' => $_SESSION['domain_uuid']];
$extensions = $database->select('select extension, tragofone_username, tragofone_user_id, sync_status, last_synced_at, last_error from v_tragofone_extension_mappings where domain_uuid=:domain_uuid order by extension', $params, 'all') ?: [];
$contacts = $database->select('select contact_uuid, tragofone_ed_id, sync_status, last_synced_at, last_error from v_tragofone_contact_mappings where domain_uuid=:domain_uuid order by last_synced_at desc', $params, 'all') ?: [];
?><h2>Tragofone Mappings</h2><h3>Extensions</h3><pre><?= escape(json_encode($extensions, JSON_PRETTY_PRINT)) ?></pre><h3>Contacts</h3><pre><?= escape(json_encode($contacts, JSON_PRETTY_PRINT)) ?></pre><?php require_once 'resources/footer.php'; ?>
