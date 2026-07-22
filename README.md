# FusionPBX Companion for Tragofone

Private, native FusionPBX application for tenant-aware Tragofone provisioning. It reads FusionPBX extensions, direct DID assignments, and supported contacts locally, then calls existing Tragofone APIs asynchronously.

## MVP capabilities

- Per-domain Tragofone credentials with explicit global inheritance
- Role-aware UI: Superadmins and tenant-scoped admins can open the module; Global Settings remain Superadmin-only
- Customer identity verification and tenant-isolated tokens
- SIP user creation and configuration using existing CRUD APIs
- Per-extension include/exclude controls with a tenant default for newly created extensions
- Multiple direct DID caller IDs with deterministic ordering
- Restricted client policy: audio calling, dialpad, local history, enterprise phonebook, and one-touch voicemail
- Transactional job outbox, retries, reconciliation, and deletion grace period
- In-GUI reconciliation and failed-job retry controls
- Crash recovery for expired worker locks and automatic tenant pause on authentication/identity failures
- FusionPBX phonebook synchronization to the tenant-wide Tragofone Enterprise Directory

No FusionPBX licensed API, remote database access, FusionPBX core patch, or Tragofone server change is required.

## Quick start

See [Installation](docs/installation.md), then [Configuration](docs/configuration.md). Superadmins and company administrators should use the [User manual](docs/user-manual.md) for daily operation. The project is an MVP and must be validated against the target FusionPBX and Tragofone versions before production rollout.

## Documentation

- [Architecture](docs/architecture.md)
- [User manual](docs/user-manual.md)
- [How it works](docs/how-it-works.md)
- [Supported features](docs/supported-features.md)
- [Tragofone API contract](docs/tragofone-api-contract.md)
- [Security](docs/security.md)
- [Troubleshooting](docs/troubleshooting.md)
- [Validation matrix](docs/validation.md)

## Development

```bash
composer install
composer test
composer lint
```

Copyright (c) 2026 Ecosmob Technologies. All rights reserved.
