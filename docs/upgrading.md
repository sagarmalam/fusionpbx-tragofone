# Upgrading

The companion schema migrations are forward-only. Test every upgrade against the supported FusionPBX/PHP matrix in [Installation](installation.md) and take a backup before replacing application files.

## Before upgrading

1. Record the installed companion and FusionPBX versions:

   ```bash
   cat /var/www/fusionpbx/app/tragofone/VERSION
   php /var/www/fusionpbx/core/upgrade/upgrade.php --version
   php -v
   ```

2. Back up `/etc/fusionpbx/tragofone.env` while preserving its permissions.
3. Back up all `v_tragofone_*` tables from the FusionPBX PostgreSQL database. Do not include the key file or database dump in Git, tickets, or chat.
4. Confirm that no job is currently `processing`, then stop both timers:

   ```bash
   sudo systemctl stop tragofone-worker.timer tragofone-reconcile.timer
   ```

## Upgrade

Obtain and verify the approved source commit. From the repository root, rerun the installer using the same overrides as the original installation:

```bash
sudo FUSIONPBX_ROOT=/var/www/fusionpbx \
  PHP_BIN=/usr/bin/php \
  FUSIONPBX_USER=www-data \
  FUSIONPBX_GROUP=www-data \
  ./tragofone/resources/install/install.sh
```

The installer preserves the existing encryption-key file, replaces/updates application files, runs the FusionPBX schema/default upgrade, restores declared group permissions, reloads systemd, and enables both timers.

Upgrading from 0.1.x to 0.2.x creates the companion-owned self-care subject, session, assertion, and rate-limit tables and adds global branding fields. Self-care remains disabled until a Superadmin saves a valid public URL and theme. Existing Phase 1 users are not exposed automatically.

## Verify

```bash
sudo systemctl is-active tragofone-worker.timer tragofone-reconcile.timer
sudo systemctl start tragofone-worker.service
sudo journalctl -u tragofone-worker.service -n 50 --no-pager
```

Sign out/in to FusionPBX, open the module, confirm the active tenant configuration, and run reconciliation. A successful oneshot service becomes `inactive (dead)` after it exits; verify its last result and the active timer instead of expecting the service to remain running.

## Rollback

Application files can be restored to the recorded previous commit after a failed health check. Do not restore an older database dump over a live FusionPBX database. If the release changed the companion schema, database rollback requires a release-specific migration or restoration of a coordinated full backup during an outage window.

The companion and FusionPBX release cycles are independent. Do not upgrade FusionPBX or PHP beyond the matrix documented in [Installation](installation.md) without repeating the integration tests first.
