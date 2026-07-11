# Security

- Require HTTPS and certificate verification in production.
- Validate configured URLs and reject embedded credentials/private destinations unless explicitly approved for on-premise use.
- Encrypt stored credentials with Sodium using key material outside the web root.
- Verify customer identity after login and isolate tokens by `domain_uuid`.
- Enforce FusionPBX permission and domain scope on every UI/action.
- Redact tokens, passwords, SIP passwords, and secrets from logs and errors.
- Expose no generic SQL, proxy, callback, or arbitrary URL-fetch endpoint.
- Run workers as `www-data` with systemd hardening and limited writable paths.
- Never delete a Tragofone user/contact without a companion-owned immutable-ID mapping and confirmed source deletion.

Threat-model and penetration-test the target deployment before production activation.
