# Security

- Require HTTPS and certificate verification in production.
- Validate configured URLs and reject embedded credentials/private destinations unless explicitly approved for on-premise use.
- Encrypt stored credentials with Sodium using key material outside the web root. `/etc/fusionpbx/tragofone.env` is owned by `root:www-data` with mode `0640`, allowing only root and the FusionPBX runtime group to read it.
- Verify customer identity after login and isolate tokens by `domain_uuid`.
- Reauthenticate an authenticated request once after `401`; if authentication still fails, pause only that tenant.
- Enforce FusionPBX permission and domain scope on every UI/action.
- Grant module-menu access to `superadmin` and tenant-scoped `admin` by default; grant global configuration permissions only to `superadmin`. Ordinary users receive no Tragofone permissions. Deployments that require Superadmin-only operation must remove the Tragofone permissions and menu access from the `admin` group.
- Redact tokens, passwords, SIP passwords, and secrets from logs and errors.
- Expose no generic SQL, proxy, callback, or arbitrary URL-fetch endpoint.
- Run workers as `www-data` with systemd hardening and limited writable paths.
- Never delete a Tragofone user/contact without a companion-owned immutable-ID mapping and confirmed source deletion.
- Require the configured extension-deletion grace period before destructive user deletion; re-enable the same mapping if the extension returns during grace.

Threat-model and penetration-test the target deployment before production activation.
