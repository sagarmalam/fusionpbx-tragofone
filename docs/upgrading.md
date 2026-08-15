# Upgrading

The companion schema migrations are forward-only. Test every upgrade against the supported FusionPBX/PHP matrix in [Installation](installation.md) and take a backup before replacing application files.

## Upgrade

Obtain and verify the approved source commit outside the FusionPBX web root. The maintenance command records versions, stops timers, blocks while jobs are processing, creates a protected file/database backup, runs the installer, and finishes with a health check:

```bash
sudo ./tragofone/resources/install/manage.sh upgrade
```

Use `--database-name` or `--database-os-user` for a non-default local PostgreSQL setup. For a remote or separately backed-up database, use `--skip-database-backup` only after confirming the approved database backup. The low-level `install.sh` remains available for recovery, but routine upgrades should use the manager. See [Plugin maintenance](maintenance.md) for every command and safety override.

Upgrading from 0.1.x to 0.2.x creates the companion-owned self-care subject, session, assertion, and rate-limit tables and adds global branding fields. Later 0.2.x upgrades add global, domain, and user `selfcare_policy` fields. New policy values default to **Inherit**; an all-Inherit chain resolves to No. A legacy global `selfcare_enabled=true` is interpreted as global **Yes** until the Superadmin explicitly saves the new selector.

The QR enrollment update adds `tragofone_qr_view`, `tragofone_qr_download`, and `tragofone_qr_email`. Rerunning the installer and its `upgrade.php -g` pass is required before the QR action appears for Superadmins and tenant administrators.

## Verify

```bash
sudo /var/www/fusionpbx/app/tragofone/resources/install/manage.sh doctor
sudo /var/www/fusionpbx/app/tragofone/resources/install/manage.sh worker
```

Sign out/in to FusionPBX, open the module, confirm the active tenant configuration, and run reconciliation. A successful oneshot service becomes `inactive (dead)` after it exits; verify its last result and the active timer instead of expecting the service to remain running.

## Rollback

Application files can be restored to the recorded previous commit after a failed health check. Do not restore an older database dump over a live FusionPBX database. If the release changed the companion schema, database rollback requires a release-specific migration or restoration of a coordinated full backup during an outage window.

The companion and FusionPBX release cycles are independent. Do not upgrade FusionPBX or PHP beyond the matrix documented in [Installation](installation.md) without repeating the integration tests first.
