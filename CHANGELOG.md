# Changelog

## 0.1.0 - Unreleased

- Bootstrap native FusionPBX companion application.
- Add tenant configuration, mappings, snapshots, jobs, and audit schema.
- Add Tragofone HTTP client, normalization, DID resolution, feature policy, scanner, worker, and reconciliation foundations.
- Add installer, systemd units, documentation, fixtures, tests, and CI.
- Fix native app packaging so the installer copies only the FusionPBX app directory.
- Add current FusionPBX `app_menu.php` discovery with the correct Advanced parent.
- Use declared permissions and the native FusionPBX token API across GUI pages.
- Claim jobs atomically without calling transaction methods absent from FusionPBX's database wrapper.
- Keep the development test stack compatible with the supported PHP 8.1 runtime.
- Accept the live API's `access_token` login response and flatten grouped configuration policy values for `update-configurations`.
- Enforce the API's application-password length and persist the configured profile in extension mappings.
- Add tenant customer-ID/profile controls and enforce customer identity before synchronization.
- Make the protected encryption key readable to PHP-FPM and workers through `root:www-data` mode `0640`.
- Use parameterized companion-table upserts without depending on FusionPBX table-specific CRUD permissions.
- Synchronize the FusionPBX shared phonebook to Tragofone Enterprise Directory using customer-scoped APIs and immutable contact mappings.
- Keep Tragofone Cloud Contacts disabled while supporting enterprise phonebook create, update, and mapping-owned deletion.
