# Development

Install PHP 8.1+, Composer, cURL, and Sodium, then run:

```bash
composer install
composer lint
composer test
```

Core logic uses a mockable HTTP transport and store interface. Add sanitized fixtures for each supported FusionPBX release. Never commit production database dumps, credentials, tokens, SIP secrets, or `.env` files.

Branch from `main`, update `CHANGELOG.md`, add tests, and open a draft PR. Releases must be signed and include checksums. Integration testing requires disposable FusionPBX and Tragofone tenants.
