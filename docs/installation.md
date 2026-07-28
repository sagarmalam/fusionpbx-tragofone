# Installation

## Supported platform

The companion has its own runtime requirements in addition to FusionPBX's requirements.

| Component | Requirement | Validation status |
|---|---|---|
| FusionPBX | 5.5 series | Integration-tested with FusionPBX `5.5.12`, branch commit `369d1f68c93912a1659a41c7e89f7acffc85e25b` |
| PHP | 8.1 or newer; 8.1, 8.2, and 8.3 are covered by CI | Integration-tested with PHP CLI/FPM 8.2.32; PHP 8.4 and 8.5 are not yet supported because the complete FusionPBX integration has not been tested on them |
| PHP extensions | `curl`, `json`, `mbstring`, `PDO`, `pdo_pgsql`, and `sodium` | All required; `intl` is optional and improves international-domain normalization |
| Database | The PostgreSQL database used by the supported FusionPBX installation | Integration-tested with PostgreSQL 18.4; the companion has no separate PostgreSQL version requirement |
| Operating system | Linux with systemd and the standard FusionPBX filesystem layout | Integration-tested on Debian 13; the supplied worker units do not support FreeBSD or non-systemd hosts |
| FusionPBX runtime account | `www-data:www-data` by default | Other accounts are supported through installer variables |

FusionPBX 5.4 and older have not completed integration validation for this release and are not supported deployment targets. The module does not require a FusionPBX commercial license or licensed API.

PHP 8.1 is the code-level minimum, but it is end-of-life upstream. For a production installation, use a PHP branch still receiving security fixes and supported by the selected FusionPBX/operating-system release. As of 22 July 2026, PHP 8.2 receives security fixes only through 31 December 2026 and PHP 8.3 through 31 December 2027; PHP 8.3 is therefore the preferred production target after validating the complete PBX installation on it.

## Other prerequisites

- An installed and working FusionPBX 5.5 system backed by PostgreSQL.
- Root or passwordless `sudo` access for copying the app, creating the encryption-key file, running the FusionPBX upgrade tool, and installing systemd units.
- Bash, OpenSSL CLI, and standard Linux commands (`install`, `cp`, `chown`, `chmod`, `sed`, and `systemctl`).
- Outbound HTTPS connectivity from the FusionPBX host to the Tragofone server.
- The Tragofone HTTPS base URL, company-admin username/password, expected customer ID, and profile ID for each company that will synchronize.
- A backup before upgrading or replacing an existing companion installation.

No Composer installation is needed on the FusionPBX server; the deployed application does not load development dependencies.

## 1. Confirm the FusionPBX and PHP versions

Run these commands on the FusionPBX host:

```bash
sudo php /var/www/fusionpbx/core/upgrade/upgrade.php --version
php -v
php -r '
$required = ["curl", "json", "mbstring", "PDO", "pdo_pgsql", "sodium"];
foreach ($required as $extension) {
    printf("%-12s %s\n", $extension, extension_loaded($extension) ? "OK" : "MISSING");
}
'
```

The first command must report a FusionPBX 5.5 version. PHP must be 8.1 or newer, and every required extension must report `OK`. Make sure the CLI binary is the same PHP minor version used by PHP-FPM; mixing versions can produce a module that works in the browser but fails in the worker.

Install missing PHP packages using the package names for the PHP minor version selected by the FusionPBX installation. On Debian-family systems, cURL, mbstring, and PostgreSQL PDO support are commonly supplied by packages such as `phpX.Y-curl`, `phpX.Y-mbstring`, and `phpX.Y-pgsql`; Sodium and JSON are normally provided with the PHP build/common package. Re-run the check after installation and restart the matching PHP-FPM service when its modules change.

## 2. Obtain the private repository

No signed release archive has been published yet. Clone the private repository with an authenticated GitHub account, or transfer an approved repository archive to a directory outside the FusionPBX web root:

```bash
mkdir -p "$HOME/src"
cd "$HOME/src"
gh repo clone sagarmalam/fusionpbx-tragofone
cd fusionpbx-tragofone
git status --short
```

The final command should print nothing. Record the commit being installed:

```bash
git rev-parse HEAD
```

If GitHub CLI is unavailable, use an authenticated `git clone` or securely copy an approved archive. Do not place GitHub tokens, Tragofone credentials, SIP passwords, or encryption keys in the repository.

## 3. Run the installer

For the standard FusionPBX layout:

```bash
cd "$HOME/src/fusionpbx-tragofone"
sudo ./tragofone/resources/install/install.sh
```

The default values are:

| Variable | Default | Purpose |
|---|---|---|
| `FUSIONPBX_ROOT` | `/var/www/fusionpbx` | FusionPBX document root |
| `PHP_BIN` | Result of `command -v php` | PHP CLI used by upgrades and background jobs |
| `FUSIONPBX_USER` | `www-data` | PHP-FPM/worker account |
| `FUSIONPBX_GROUP` | `www-data` | Group allowed to read the external encryption key |

For a non-standard installation, pass absolute paths and the real runtime account explicitly:

```bash
sudo FUSIONPBX_ROOT=/srv/fusionpbx \
  PHP_BIN=/usr/bin/php8.3 \
  FUSIONPBX_USER=www-data \
  FUSIONPBX_GROUP=www-data \
  ./tragofone/resources/install/install.sh
```

The installer validates the PHP version/extensions and runtime account before changing files. It then:

1. Copies the native `tragofone/` application and repository `VERSION` metadata to `<FUSIONPBX_ROOT>/app/tragofone`.
2. Owns the installed app as the configured FusionPBX runtime user/group.
3. Installs two oneshot services and two timers in `/etc/systemd/system` and renders their PHP, document-root, user, and group settings from the installer variables.
4. Creates `/etc/fusionpbx/tragofone.env` only when it does not already exist. The file is `root:<FUSIONPBX_GROUP>` mode `0640` and contains a random encryption key outside the web root.
5. Runs the FusionPBX schema/default upgrade and then `upgrade.php -g` to restore the group permissions declared by the app.
6. Enables the 30-second worker timer and six-hour reconciliation timer.

Re-running the installer preserves an existing `/etc/fusionpbx/tragofone.env`. Do not delete or replace that file unless all stored Tragofone credentials will be entered again; old ciphertext cannot be decrypted with a new key.

## 4. Verify the installation

Check the installed files, encryption key, timers, and one manual worker execution:

```bash
sudo test -f /var/www/fusionpbx/app/tragofone/app_config.php
sudo stat -c '%U:%G %a %n' /etc/fusionpbx/tragofone.env
sudo systemctl is-enabled tragofone-worker.timer tragofone-reconcile.timer
sudo systemctl is-active tragofone-worker.timer tragofone-reconcile.timer
sudo systemctl list-timers 'tragofone-*'
sudo systemctl start tragofone-worker.service
sudo journalctl -u tragofone-worker.service -n 50 --no-pager
```

Expected results:

- The key file is `root:www-data 640` for a default installation.
- Both timers are `enabled` and `active`.
- The manual worker run exits with `status=0/SUCCESS`.
- `tragofone-worker.service` and `tragofone-reconcile.service` normally show `inactive (dead)` after a successful run because they are oneshot services. This is not a failure; their timers must remain active.

Confirm what systemd actually installed, especially after using overrides:

```bash
sudo systemctl show \
  -p User -p Group -p WorkingDirectory -p ExecStart \
  tragofone-worker.service tragofone-reconcile.service
```

## 5. Verify the FusionPBX UI and permissions

Sign out of FusionPBX and sign in again, then open **Advanced → Tragofone Integration**.

Default access is role-based:

- `superadmin` sees every module page, including **Global Settings**, and can work with any permitted domain.
- Tenant-scoped `admin` sees the module for the active authorized domain but never receives Global Settings permission.
- Ordinary FusionPBX users receive no module permission or menu entry.

If the menu is missing, rerun both upgrade passes and then sign out/in:

```bash
sudo php /var/www/fusionpbx/core/upgrade/upgrade.php
sudo php /var/www/fusionpbx/core/upgrade/upgrade.php -g
```

Use the same `PHP_BIN` and `FUSIONPBX_ROOT` values supplied to the installer on a custom installation. The `-g` pass is essential because it applies the group permissions declared in `app_config.php`.

## 6. Configure and prove synchronization

1. As a Superadmin, open **Global Settings** only if multiple tenants should explicitly inherit the same URL or credentials.
2. Select the target FusionPBX domain and open **Tenant Settings**.
3. Enter the Tragofone server/customer identity, SIP settings, profile, voicemail code, and extension-selection default.
4. Use **Test Connection** before enabling synchronization.
5. Open **Extensions**, explicitly select the users to synchronize, and save.
6. Run **Reconciliation**, then inspect **Jobs** and **Mappings**.
7. Confirm the created Tragofone user, shared application/SIP password, SIP registration, Effective Outbound Caller ID and direct-DID choices, restricted feature policy, one-touch voicemail, and supported shared phonebook entries.

See [Configuration](configuration.md), [User manual](user-manual.md), and [Validation matrix](validation.md) for the full operational checks.

## Installation boundaries

- The supported installer targets Linux/systemd. A non-systemd port needs separately maintained worker scheduling and is not currently supported.
- The app never modifies FusionPBX core files or core tables.
- Installation does not enable a tenant automatically and does not send data until an administrator configures and enables that tenant.
- There is currently no signed release artifact. Install an approved commit and record its hash until the release process publishes checksummed, signed packages.

## Compatibility references

- [FusionPBX official releases](https://github.com/fusionpbx/fusionpbx/releases)
- [FusionPBX official version-upgrade procedure](https://docs.fusionpbx.com/en/latest/advanced/version_upgrade.html)
- [PHP official supported-version schedule](https://www.php.net/supported-versions.php)
