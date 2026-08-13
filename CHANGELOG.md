# Changelog

## 0.2.3 - 2026-08-13

- Evaluate Tragofone SIP `603 Decline` after the extension bridge completes, then apply FusionPBX **When busy** forwarding before voicemail (#34).
- Invalidate every enabled tenant's FusionPBX dialplan cache after App Defaults and unattended installation so newly imported companion dialplans become active immediately (#34).

## 0.2.2 - 2026-08-13

- Prefer FusionPBX **Outbound Caller ID Number** for Tragofone `sip_callerid`, falling back to Effective Caller ID Number only when the outbound field is blank (#5).
- Treat Tragofone's SIP `603 Decline` as a busy-forward event when FusionPBX **When busy** forwarding is enabled (#34).
- Use session-owned media tokens for both playback and download so Android's native audio-menu download does not depend on WebView cookies (#36).
- Send direct voicemail downloads as binary attachments so iOS does not replace the download with an inline audio page (#36).
- Mark voicemail read on authenticated playback at the server and update the card immediately when playback begins, without relying on background WebView requests (#31).
- Clarify that the 2–15-character Tragofone boundary is enforced at synchronization selection; FusionPBX's native extension editor intentionally remains under FusionPBX control (#11).

## 0.2.1 - 2026-08-12

- Auto-dismiss transient self-care success and validation messages after five seconds.
- Keep call-handling validation errors on the Call Handling page and reject non-numeric forwarding destinations without silently modifying them.
- Require a forwarding mode to be selected when its destination is populated.
- Regenerate FusionPBX extension XML, clear directory cache, and reload FreeSWITCH after DND or forwarding changes.
- Stream voicemail with standard audio, range, length, and attachment headers, and use a two-minute encrypted download token compatible with mobile WebView download handlers.
- Render voicemail timestamps in the device's local time and mark new messages read when playback completes.
- Standardize self-care voicemail action controls.
- Give Extension Synchronization search a consistent border and display a clear empty result when no extension matches.

## 0.2.0 - Release candidate

- Re-license the project under Apache License 2.0 and update ownership to Trago Communications Pvt Ltd.
- Add the signed self-care portal with global light/dark branding referenced by each Tragofone Account URL.
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
- Keep newly provisioned My Account URLs within Tragofone's 200-character limit by signing a compact subject and global brand-version reference, while retaining legacy launch compatibility.
- Render live Tragofone raw QR payloads as validated PNG images with PHP GD and FusionPBX's bundled QR library.
- Load FusionPBX's QR error-correction dependency so raw Tragofone payloads can be previewed and emailed (#15).
- Treat HTTP 200 application-error envelopes as API failures and reject user creation responses without a positive `usr_id`.
- Fix clearing a self-care voicemail notification address and prevent intrinsic mobile navigation overflow at 320px.
- Remove the manual logout control from the Tragofone-embedded self-care header; sessions continue to expire or revoke through the existing security policy.
- Add an authenticated, tenant-isolated self-care QR view so users can enroll another Tragofone device without opening the administrative portal.
- Standardize link, secondary, primary, table, and form actions across every Tragofone administration page, including full-width mobile form actions.
- Preserve the source-label casing for administration buttons instead of inheriting variant-specific uppercase transforms (#19).
- Keep self-care sessions active for 24 hours by default, with configurable idle and absolute limits between 5 minutes and 24 hours.
- Retry all tenant-scoped dead synchronization jobs when full reconciliation runs (#13).
- Confirm successful QR preview refreshes in the administration portal (#18).
- Improve real-client self-care compatibility by provisioning 128-bit hexadecimal salts, accepting documented epoch seconds and client epoch milliseconds, and emitting sanitized launch rejection references (#22).
- Preserve self-care access and the public URL during theme restoration or partial settings submissions, and recompute access from current policy when queued jobs execute (#22).
- Isolate global self-care access behind a dedicated save action so branding, restore, salt rotation, and stale browser submissions cannot disable My Account or erase its URL (#22).
- Accept authentic older compact Account URLs during asynchronous branding rollout while always rendering the current global theme; future, tampered, rotated-salt, and revoked URLs remain rejected (#22).

- Fix tenant outbound-proxy provisioning by exposing its server/port settings, defaulting blank proxy values to the resolved SIP endpoint, and including tenant SIP policy in extension change detection (#4).
- Synchronize Effective Caller ID Name changes to the mapped Tragofone account name while keeping the mapping's current FusionPBX extension number accurate (#6).
- Reuse the FusionPBX SIP password for Tragofone application login and rotate both through the existing user/configuration APIs (#3).
- Trust Effective Outbound Caller ID as the primary caller-ID choice and retain direct DIDs as additional choices (#5).
- Keep Tragofone application usernames and user IDs immutable across FusionPBX extension renumbering while updating SIP identity and mapping display values (#7).
- Document the explicit FusionPBX 5.5 and PHP compatibility matrix, required extensions, verified Debian/PostgreSQL baseline, complete installation validation, and oneshot service behavior.
- Add FusionPBX 5.5-compatible installation detection and installer preflight checks, and honor custom PHP binary, FusionPBX root, runtime user, and runtime group values in generated systemd services.
- Declare runtime PHP extensions in Composer metadata and syntax-check the installer in CI.

## 0.1.0 - 2026-07-22

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
