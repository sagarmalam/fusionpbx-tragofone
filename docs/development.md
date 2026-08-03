# Development

Install PHP 8.1, 8.2, or 8.3, Composer, and the cURL, Fileinfo, JSON, mbstring, PDO/PostgreSQL, and Sodium extensions, then run:

```bash
composer install
composer lint
composer test
```

Core logic uses a mockable HTTP transport and store interface. Add sanitized fixtures for each supported FusionPBX release. Never commit production database dumps, credentials, tokens, SIP secrets, or `.env` files.

CI currently exercises PHP 8.1 through 8.3. The live integration baseline is FusionPBX 5.5.12 on PHP 8.2.32; see [Installation](installation.md) before changing the supported matrix. PHP 8.4/8.5 support requires both unit/CI coverage and a complete FusionPBX integration run.

Update `CHANGELOG.md` and add tests for every behavior change. Use the repository's currently agreed branch policy; the initial test-environment milestone is published directly to `main`. Releases must be signed and include checksums. Integration testing requires disposable FusionPBX and Tragofone tenants.

Phase 2 security tests must cover signed launch tampering, expiration/future skew, replay, subject/session revocation, tenant and mailbox ownership, CSRF, forwarding policy and loops, and opaque voicemail handles. Render `docs/mockups/selfcare.html` at 320, 375, 430, 768, and desktop widths after changing portal CSS; verify both color schemes and safe-area navigation visually.
