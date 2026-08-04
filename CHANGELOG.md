# Changelog

## Unreleased

- Add the Phase 2 signed self-care portal with global light/dark branding embedded in each Tragofone Account URL.
- Add per-extension one-time launch subjects, encrypted salts, server-side sessions, replay protection, rate limiting, CSRF protection, and session revocation.
- Add responsive account summary, DND and forwarding, FusionPBX Visual Voicemail, voicemail email, and PIN self-care.
- Add Inherit/Yes/No self-care access controls at global, domain, and individual-user levels.
- Synchronize FusionPBX Call Timeout to Tragofone No Answer Timeout (#9).
- Synchronize Emergency Caller ID Number to Tragofone Emergency Numbers, including removal (#10).
- Enforce Tragofone's 2–15 character SIP-extension boundary while supporting numeric and alphanumeric values (#11).
- Synchronize the FusionPBX mailbox Voicemail Enabled state to Tragofone voicemail enablement (#12).
- Add domain-scoped Tragofone QR login preview, download, and direct FusionPBX SMTP delivery for synchronized users.
- Add dedicated QR permissions, no-store handling, raster validation, sanitized audit events, and tests without persisting QR credentials.
- Add Superadmin branding controls, image validation, WCAG contrast checks, live previews, global reprovisioning, interactive mockups, and portal documentation.

- Fix tenant outbound-proxy provisioning by exposing its server/port settings, defaulting blank proxy values to the resolved SIP endpoint, and including tenant SIP policy in extension change detection (#4).
- Synchronize Effective Caller ID Name changes to the mapped Tragofone account name while keeping the mapping's current FusionPBX extension number accurate (#6).
- Reuse the FusionPBX SIP password for Tragofone application login and rotate both through the existing user/configuration APIs (#3).
- Trust Effective Outbound Caller ID as the primary caller-ID choice and retain direct DIDs as additional choices (#5).
- Keep Tragofone application usernames and user IDs immutable across FusionPBX extension renumbering while updating SIP identity and mapping display values (#7).
- Document the explicit FusionPBX 5.5 and PHP compatibility matrix, required extensions, verified Debian/PostgreSQL baseline, complete installation validation, and oneshot service behavior.
- Add FusionPBX 5.5-compatible installation detection and installer preflight checks, and honor custom PHP binary, FusionPBX root, runtime user, and runtime group values in generated systemd services.
- Declare runtime PHP extensions in Composer metadata and syntax-check the installer in CI.

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
- Add SIP disable/re-enable, 24-hour deletion grace, final mapped-user deletion, and mapping recovery when an extension returns during grace.
- Add one-time `401` reauthentication and automatic tenant pause for authentication/customer-identity failures.
- Clear caller ID when no direct DID exists, persist DID assignment mappings, and expose them on the Mappings page.
- Reclaim expired processing locks after worker crashes and add tenant-scoped GUI controls for reconciliation and failed-job retry.
- Validate the live extension, DID, phonebook, retry, recovery, restricted-feature, and TLS SIP registration matrix.
- Redesign tenant settings into responsive, clearly grouped FusionPBX-native sections with visible integration status and inheritance behavior.
- Add tenant-scoped per-extension include/exclude policies, a default for new extensions, searchable bulk-selection UI, and safe disable/re-enable mapping reuse.
- Prevent workers from claiming jobs for paused or disabled tenants.
- Restore FusionPBX group permissions during installation and document the required upgrade pass.
- Serialize PostgreSQL boolean parameters explicitly and surface failed companion writes instead of silently accepting them.
- Add a role-based user manual for FusionPBX Superadmins and company administrators.
- Redesign Global Settings with dedicated credential, endpoint, fallback, and inheritance guidance sections.
- Add consistent responsive module navigation and modernize Overview, Mappings, Jobs, and Reconciliation pages.
- Clarify default Superadmin versus tenant-admin menu visibility and permissions throughout the operator documentation.
