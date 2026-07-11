# Uninstall

Disable both timers and the integration first. Removing `/var/www/fusionpbx/app/tragofone` leaves companion tables, mappings, and audit history intact. A full data purge must be a separate explicit database operation after backup.

Uninstall never modifies FusionPBX extensions and never deletes Tragofone users or contacts. Remove `/etc/fusionpbx/tragofone.env` only after deciding that stored encrypted credentials will never be recovered.
