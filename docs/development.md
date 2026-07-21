# Development

Install PHP 8.1+, Composer, cURL, and Sodium, then run:

```bash
composer install
composer lint
composer test
```

Core logic uses a mockable HTTP transport and store interface. Add sanitized fixtures for each supported FusionPBX release. Never commit production database dumps, credentials, tokens, SIP secrets, or `.env` files.

Update `CHANGELOG.md` and add tests for every behavior change. Use the repository's currently agreed branch policy; the initial test-environment milestone is published directly to `main`. Releases must be signed and include checksums. Integration testing requires disposable FusionPBX and Tragofone tenants.
