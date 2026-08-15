# Plugin maintenance command

`manage.sh` is the supported day-two entry point for installing and maintaining the Tragofone companion. It delegates application deployment and FusionPBX schema/default updates to the existing installer, while adding backups, job-safety checks, health diagnostics, service operations, and logs in one command.

## Install once

Run the manager from an unpacked, approved repository commit:

```bash
sudo ./tragofone/resources/install/manage.sh install
```

The standard command validates the platform through the installer, installs the native app, applies both FusionPBX upgrade passes, restores permissions, enables the timers, and finishes with `doctor`. Existing installations are backed up before they are changed.

Non-standard FusionPBX layouts use explicit options instead of repeating environment-variable assignments:

```bash
sudo ./tragofone/resources/install/manage.sh install \
  --fusionpbx-root /srv/fusionpbx \
  --php-bin /usr/bin/php8.3 \
  --user www-data \
  --group www-data
```

## Upgrade safely

Check out or unpack the approved version outside the FusionPBX web root, then run:

```bash
sudo ./tragofone/resources/install/manage.sh upgrade
```

For an existing installation the manager:

1. Stops the worker and reconciliation timers so no new jobs start.
2. Refuses to continue while a synchronization job is `processing`.
3. Creates a root-only filesystem archive and PostgreSQL custom-format dump under `/var/backups/fusionpbx-tragofone/<UTC timestamp>/`.
4. Runs the existing installer and both FusionPBX upgrade passes.
5. Enables the timers and runs the complete health check.
6. Restores timers that were active before the attempt if maintenance fails.

Because schema migrations are forward-only, the manager compares source and installed versions and refuses a downgrade. A rollback that includes database state requires a release-specific, approved plan.

The default database name is `fusionpbx` and the default local PostgreSQL OS user is `postgres`. Override either when required:

```bash
sudo ./tragofone/resources/install/manage.sh upgrade \
  --database-name fusionpbx_test \
  --database-os-user postgres
```

For a remote or separately backed-up database, use `--skip-database-backup` only after confirming the approved database backup. `--no-backup` and `--allow-processing` are explicit emergency overrides; they should not be part of normal operations.

## Routine commands

The installed copy can handle day-two operations directly:

```bash
MANAGER=/var/www/fusionpbx/app/tragofone/resources/install/manage.sh

sudo "$MANAGER" status
sudo "$MANAGER" doctor
sudo "$MANAGER" backup
sudo "$MANAGER" worker
sudo "$MANAGER" reconcile
sudo "$MANAGER" timers
sudo "$MANAGER" logs worker --lines 100
```

- `status` is read-only and summarizes versions, PHP, protected-config metadata, and timer state.
- `doctor` fails when required PHP extensions, FusionPBX files, protected permissions, systemd units, timers, or job-state access are unhealthy.
- `backup` creates the same protected file/database backup used by upgrades without changing the installation.
- `worker` and `reconcile` run the corresponding oneshot service and show its result and recent journal.
- `timers` reloads systemd and enables both timers.
- `logs` accepts `worker`, `reconcile`, or `all`.
- `repair` may be run from the installed copy to reapply permissions, units, schema/defaults, and timers without fetching code; run it from an approved release tree when application files must also be replaced.

An upgrade cannot obtain or approve source code by itself. This is intentional: operators must deploy from a reviewed tag or recorded commit, and the manager never silently pulls an unreviewed branch.

## Backup handling

Backup directories are created as `root:root` mode `0700`; files and checksums are mode `0600`. The filesystem archive can contain `/etc/fusionpbx/tragofone.env`, and the database dump contains encrypted tenant configuration and operational data. Treat the whole backup directory as sensitive, move it only through an approved secure channel, and apply the site's retention policy.

The automatic dump contains `public.v_tragofone_*` tables in PostgreSQL custom format. FusionPBX core tables are outside its scope and still require the site's normal coordinated database backup. Do not restore an older dump over a live database without an approved rollback plan.
