# Upgrading

Back up companion tables and `/etc/fusionpbx/tragofone.env`, stop timers, replace app files with a verified signed release, run FusionPBX `core/upgrade/upgrade.php`, then restart timers and execute health/reconciliation checks.

Schema migrations are forward-only. Application files may be rolled back after a failed health check, but schema rollback requires a release-specific procedure. Companion and FusionPBX versions are independent; test current and previous stable FusionPBX adapters before rollout.
