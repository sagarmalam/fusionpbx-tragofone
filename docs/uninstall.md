# Uninstall

Disable both timers and the integration first. Removing `/var/www/fusionpbx/app/tragofone` leaves companion tables, mappings, and audit history intact. A full data purge must be a separate explicit database operation after backup.

Uninstall never modifies FusionPBX extensions and never deletes Tragofone users or contacts. Remove `/etc/fusionpbx/tragofone.env` only after deciding that stored encrypted credentials will never be recovered.

Before removing Phase 2, set global, domain, and any explicit user self-care policies to **No** and let the queued configuration jobs set `myaccount_status=FALSE`. Because a more specific **Yes** overrides a global **No**, checking domain and user policies is required. Active self-care sessions are revoked as extension subjects are disabled. A preserve-data uninstall retains subjects and encrypted salts; a full purge must additionally remove the `v_tragofone_selfcare_*` tables after backup.
