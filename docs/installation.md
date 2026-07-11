# Installation

## Prerequisites

- Supported FusionPBX release (current or previous stable; validate exact target before production)
- PHP 8.1+, cURL and Sodium extensions
- PostgreSQL-backed standard FusionPBX database
- HTTPS Tragofone endpoint and company-admin credentials
- Root access for app and systemd installation

## Install

Obtain a signed release on the FusionPBX host, verify its checksum/signature, then:

```bash
sudo FUSIONPBX_ROOT=/var/www/fusionpbx ./tragofone/resources/install/install.sh
```

The installer copies the app, creates `/etc/fusionpbx/tragofone.env` with mode `0600`, runs FusionPBX upgrade defaults/schema, and enables the 30-second worker and six-hour reconciliation timers.

Alternatively copy `tragofone/` to `/var/www/fusionpbx/app/tragofone`, run `php /var/www/fusionpbx/core/upgrade/upgrade.php`, install the four systemd unit files, and enable both timers.

Verify with:

```bash
systemctl status tragofone-worker.timer tragofone-reconcile.timer
systemctl start tragofone-worker.service
journalctl -u tragofone-worker.service --since today
```

Open **Advanced → Tragofone Integration** and configure a tenant before enabling synchronization.
