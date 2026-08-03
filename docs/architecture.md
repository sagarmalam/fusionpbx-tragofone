# Architecture

FusionPBX remains authoritative for extensions, SIP secrets, direct DID routing, and contacts. The companion reads those tables through FusionPBX's local database abstraction, records normalized hashes and mappings in companion-owned tables, and places changes in a transactional outbox. A systemd worker calls existing Tragofone APIs outside FusionPBX web requests.

Mappings are immutable-ID based: `domain_uuid` identifies a tenant, `extension_uuid` maps to Tragofone `usr_id`, `destination_uuid` identifies a DID route, and `contact_uuid` maps to enterprise-directory `ed_id`. Tenant-scoped extension policies use `extension_uuid` and override the tenant's default behavior for newly discovered extensions.

Tenant failures are isolated. Contact jobs are independent and lower priority, so SIP/DID processing continues when only the Enterprise Directory API fails. Authentication or identity failures pause only the affected tenant. Reconciliation re-scans source state every six hours; it never adopts or deletes records without a companion-owned mapping. A claimed job has a five-minute lease and can be reclaimed after the lease expires, allowing recovery from a killed worker.

Extension deletion is confirm-then-disable, followed by a 24-hour grace period. Phonebook contact deletion is detected during a full scan and removes only the Tragofone Enterprise Directory record referenced by a companion-owned `contact_uuid → ed_id` mapping.

Self-care adds companion-owned subjects, sessions, consumed assertions, and rate-limit buckets. A subject maps one `extension_uuid` to an encrypted salt and current global brand version. The Tragofone Account URL contains raw theme values protected by a companion HMAC in addition to Tragofone's time-based MD5. Public pages derive domain, extension, mailbox, and permissions only from the authenticated subject.

The portal writes native FusionPBX DND/forwarding and voicemail settings through a compatibility repository. It delegates media playback/deletion and MWI to the installed FusionPBX voicemail class after ownership verification. Expired security records are cleaned during the six-hour reconciliation service.
