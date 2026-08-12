# Development

Install PHP 8.1, 8.2, or 8.3, Composer, and the cURL, Fileinfo, GD, JSON, mbstring, PDO/PostgreSQL, and Sodium extensions, then run:

```bash
composer install
composer lint
composer test
```

Core logic uses a mockable HTTP transport and store interface. Add sanitized fixtures for each supported FusionPBX release. Never commit production database dumps, credentials, tokens, SIP secrets, or `.env` files.

CI currently exercises PHP 8.1 through 8.3. The live integration baseline is FusionPBX 5.5.12 on PHP 8.2.32; see [Installation](installation.md) before changing the supported matrix. PHP 8.4/8.5 support requires both unit/CI coverage and a complete FusionPBX integration run.

Update `CHANGELOG.md` and add tests for every behavior change. Develop on a `codex/` or project-approved feature branch, require passing CI and review before merging to `main`, and deploy customers only from an approved version tag or recorded commit. Customer release packages must include checksums and should be signed. Integration testing requires disposable FusionPBX and Tragofone tenants.

Self-care security tests must cover signed launch tampering, expiration/future skew, replay, subject/session revocation, tenant and mailbox ownership, CSRF, forwarding policy and loops, and opaque voicemail handles. Test the deployed portal at 320, 375, 430, 768, and desktop widths after changing portal CSS; verify both color schemes and safe-area navigation visually, then refresh the customer screenshots when the visible interface changes.
