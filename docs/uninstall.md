# Uninstall

Disable both timers and the integration first. Removing `/var/www/fusionpbx/app/tragofone` leaves companion tables, mappings, and audit history intact. A full data purge must be a separate explicit database operation after backup.

Uninstall never modifies FusionPBX extensions and never deletes Tragofone users or contacts. Remove `/etc/fusionpbx/tragofone.env` only after deciding that stored encrypted credentials will never be recovered.

Before removing Phase 2, disable global self-care and let the queued configuration jobs set `myaccount_status=FALSE` for eligible Tragofone users. Active self-care sessions are revoked as extension subjects are disabled. A preserve-data uninstall retains subjects and encrypted salts; a full purge must additionally remove the `v_tragofone_selfcare_*` tables after backup.
