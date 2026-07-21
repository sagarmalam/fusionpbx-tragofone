# How it works

Every 30 seconds the scanner loads enabled tenants, reads locally changed extensions, tenant destinations, and shared phonebook contacts, normalizes relevant fields, and compares hashes. A new or changed hash produces an idempotent outbox job.

For a new extension, the worker creates `{extension}@{domain}` through the existing customer user-create API, immediately persists `extension_uuid → usr_id`, then calls the separate configuration API. A crash between phases cannot duplicate the user. Updates use the stored user ID.

Only unambiguous, enabled, voice inbound routes with one direct extension action become DIDs. All DIDs are sent as `sip_callerid`; an effective caller-ID match is ordered first.

FusionPBX phonebook records are normalized from `v_contacts`, `v_contact_phones`, and `v_contact_emails`, then written to the tenant-wide Tragofone Enterprise Directory using the company-admin token. `contact_uuid → ed_id` mappings make updates deterministic and ensure deletion affects only companion-owned records. Tragofone Cloud Contacts remain disabled.

Transient failures retry after 1, 5, and 15 minutes, then 1, 3, and 6 hours. Permanent authentication, identity, validation, and permission failures become visible dead jobs. Reconciliation re-runs source normalization without deleting unrelated Tragofone records.
