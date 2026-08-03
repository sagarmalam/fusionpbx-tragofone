# Security

- Require HTTPS and certificate verification in production.
- Validate configured URLs and reject embedded credentials/private destinations unless explicitly approved for on-premise use.
- Encrypt stored credentials with Sodium using key material outside the web root. `/etc/fusionpbx/tragofone.env` is owned by `root:www-data` with mode `0640`, allowing only root and the FusionPBX runtime group to read it.
- Verify customer identity after login and isolate tokens by `domain_uuid`.
- Reauthenticate an authenticated request once after `401`; if authentication still fails, pause only that tenant.
- Enforce FusionPBX permission and domain scope on every UI/action.
- Grant module-menu access to `superadmin` and tenant-scoped `admin` by default; grant global configuration permissions only to `superadmin`. Ordinary users receive no Tragofone permissions. Deployments that require Superadmin-only operation must remove the Tragofone permissions and menu access from the `admin` group.
- Redact tokens, passwords, SIP passwords, and secrets from logs and errors.
- By deployment policy, the Tragofone application-login password is the FusionPBX SIP password. Treat it as one shared credential: restrict extension-password visibility and rotate both by changing the FusionPBX extension password.
- Effective Outbound Caller ID is trusted from FusionPBX without validating a direct inbound route. Enforce caller-ID ownership and anti-spoofing controls in FusionPBX and the upstream carrier.
- Expose no generic SQL, proxy, callback, or arbitrary URL-fetch endpoint.
- Run workers as `www-data` with systemd hardening and limited writable paths.
- Never delete a Tragofone user/contact without a companion-owned immutable-ID mapping and confirmed source deletion.
- Require the configured extension-deletion grace period before destructive user deletion; re-enable the same mapping if the extension returns during grace.

Threat-model and penetration-test the target deployment before production activation.

## Self-care security boundary

- Each synchronized extension receives a unique opaque subject and 256-bit random salt encrypted with the existing external key.
- Tragofone authenticates launches with `MD5(salt + epoch)`. The companion separately signs the subject and every raw branding parameter with HMAC-SHA256 because the Tragofone hash does not cover additional URL fields.
- Launches expire after two minutes, allow 60 seconds of future clock skew, and are single-use. Invalid attempts are rate limited without storing raw IP addresses.
- Successful launches redirect to a clean URL and use a Secure, HttpOnly, SameSite=Lax cookie backed by a hashed server-side token. Sessions are bound to keyed IP and user-agent fingerprints and have idle and absolute expiry.
- Every mutation requires CSRF validation. Mailbox and extension identity are derived from the authenticated subject, never from a request parameter.
- CSP, no-store, no-referrer, MIME sniffing protection, same-origin framing, and restricted browser permissions are applied to public pages.
- The configured Account URL contains the per-user salt because Tragofone requires it. Treat the configured URL as a credential and do not copy it into tickets or logs. Tragofone removes `tragofone_salt` before launching the PBX URL, so it is not present in the PBX access request.
- Logo data is stored in the companion database and served same-origin. Uploads are MIME checked and limited to PNG/JPEG/WebP, 256 KB, and 512 × 512; SVG and external tracking URLs are rejected.
