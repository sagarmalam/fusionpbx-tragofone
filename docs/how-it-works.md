# How it works

Every 30 seconds the scanner loads enabled tenants, reads locally changed extensions, tenant destinations, and shared phonebook contacts, normalizes relevant fields, and compares hashes. A new or changed hash produces an idempotent outbox job. Paused and disabled tenants are excluded from both scanning and job claiming.

For a new extension, the worker creates `{extension}@{domain}` through the existing customer user-create API, using the FusionPBX SIP password as the Tragofone application-login password. It immediately persists `extension_uuid → usr_id`, then calls the separate configuration API. A crash between phases cannot duplicate the user. SIP password rotations update both the application-login password and SIP configuration. Updates use the stored user ID. Effective Caller ID Name changes are sent as Tragofone account-name updates, while SIP, proxy, and feature-policy changes are sent through the configuration API.

The Tragofone application username is immutable. If FusionPBX renumbers an extension, the existing `{original-extension}@{domain}` login and `usr_id` remain unchanged, while SIP username, authentication ID, SIP extension, account name, and the mapping's displayed FusionPBX extension are updated. The existing user-update API does not accept `usr_username`, so the companion never replaces a working user merely to rename its login.

Before creating or updating a SIP user, the scanner resolves its tenant-scoped extension policy. An explicit per-extension choice overrides the tenant default for new extensions. Unmapped exclusions are ignored. Mapped exclusions are disabled and retained with status `excluded`; re-inclusion applies the latest SIP/DID configuration and restores the same user ID.

The normalized FusionPBX Effective Outbound Caller ID is trusted and placed first in `sip_callerid`, even when it is not backed by a direct inbound route. In addition, unambiguous enabled voice inbound routes with one direct extension action become caller-ID choices. Direct DIDs are de-duplicated and naturally sorted after the effective outbound value. When neither source supplies a number, `sip_callerid` is cleared. Companion-owned `destination_uuid → extension_uuid` mappings remain limited to direct routes and are visible on the Mappings page.

FusionPBX phonebook records are normalized from `v_contacts`, `v_contact_phones`, and `v_contact_emails`, then written to the tenant-wide Tragofone Enterprise Directory using the company-admin token. `contact_uuid → ed_id` mappings make updates deterministic and ensure deletion affects only companion-owned records. Tragofone Cloud Contacts remain disabled.

Transient failures retry after 1, 5, and 15 minutes, then 1, 3, and 6 hours. A customer `401` or identity mismatch pauses only the affected tenant and leaves a visible dead job. Operators can retry eligible jobs and run a full tenant reconciliation from the FusionPBX UI. Expired `processing` locks are reclaimable after a worker crash.

Disabling an extension disables the mapped Tragofone user immediately. Deleting an extension does the same, marks the mapping `deletion_pending`, and waits for the configured grace period (24 hours by default) before deleting the mapped user. Recreating the same extension during the grace period rebinds the companion-owned mapping and re-enables the same Tragofone `usr_id`.
