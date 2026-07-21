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
