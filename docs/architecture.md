# Architecture

FusionPBX remains authoritative for extensions, SIP secrets, direct DID routing, and contacts. The companion reads those tables through FusionPBX's local database abstraction, records normalized hashes and mappings in companion-owned tables, and places changes in a transactional outbox. A systemd worker calls existing Tragofone APIs outside FusionPBX web requests.

Mappings are immutable-ID based: `domain_uuid` identifies a tenant, `extension_uuid` maps to Tragofone `usr_id`, `destination_uuid` identifies a DID route, and `contact_uuid` maps to enterprise-directory `ed_id`.

Tenant failures are isolated. SIP/DID processing can continue when only the dedicated contact integration user is unavailable. Reconciliation re-scans source state every six hours; it never adopts or deletes records without a companion-owned mapping.

Deletion is confirm-then-disable, followed by a 24-hour grace period. The MVP scanner implements changed-extension convergence; complete deletion and contact execution remain guarded/scaffolded pending target-system contract validation.
