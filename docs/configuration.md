# Configuration

Each FusionPBX domain maps to exactly one Tragofone customer. Enter the HTTPS base URL, company-admin username/password, expected Tragofone customer ID, default profile ID, SIP server/port/protocol, optional outbound proxy, and voicemail code (`*97` by default). Authentication is accepted only when `/api/customer/me` returns the configured customer ID.

Global URL/credentials are optional. Set **Inherit global URL** and **Inherit global credentials** independently for each domain. A domain inherits a value only when the corresponding switch is `True`; silent fallback is prohibited. The expected customer ID remains tenant-specific and is always verified after login.

**Paused** stops scanning only for the selected domain. The worker automatically sets it after a `401` or customer-identity mismatch; correct the credentials/identity and explicitly change **Paused** to `False` to resume. **Deletion grace (seconds)** defaults to `86400` (24 hours), with a minimum of 60 seconds.

## Extension selection

Open **Tenant Settings → Manage Extensions** to choose which FusionPBX SIP extensions are synchronized. The tenant's **New extensions** setting controls extensions with no explicit policy:

- **Sync automatically** preserves the original behavior and provisions new extensions unless an administrator excludes them.
- **Do not sync until selected** provides an allowlist workflow for new extensions.

Saving the extension list creates tenant-scoped policies keyed by `extension_uuid`. Excluding an extension that has never been provisioned creates no Tragofone user. Excluding an existing mapping disables the Tragofone user and marks it `excluded`; it does not delete the user or mapping. Selecting it again re-enables and reconfigures the same Tragofone `usr_id`. The FusionPBX extension's own Enabled/Disabled state remains authoritative in addition to this selection.

Extension selection affects SIP-user and direct-DID provisioning only. The tenant-wide FusionPBX phonebook continues to follow the tenant enable/pause state.

The shared FusionPBX phonebook uses the same tenant company-admin credentials through the customer enterprise-directory API. No dedicated app user is needed on the validated deployment. Missing contact tables or a contact-specific failure never blocks SIP/DID processing. Tragofone Cloud Contacts remain disabled; phonebook records are tenant-wide Enterprise Directory entries.

The encryption key lives only in `/etc/fusionpbx/tragofone.env`, owned by `root:www-data` with mode `0640`. Changing it without re-entering credentials makes saved ciphertext unreadable.
