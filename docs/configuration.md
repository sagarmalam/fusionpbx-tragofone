# Configuration

Each FusionPBX domain maps to exactly one Tragofone customer. Enter the HTTPS base URL, company-admin username/password, expected Tragofone customer ID, default profile ID, SIP server/port/protocol, optional outbound proxy, and voicemail code (`*97` by default). When the outbound proxy server or port is blank, the resolved SIP server or SIP port is sent to Tragofone instead; the proxy fields are never silently sent empty. Authentication is accepted only when `/api/customer/me` returns the configured customer ID.

Global URL/credentials are optional. A Superadmin configures them under **Advanced → Tragofone Integration → Global Settings**, in the **Global API Endpoint** and **Global Tragofone Credentials** sections. Set **Inherit global URL** and **Inherit global credentials** independently in each domain's Tenant Settings. A domain inherits a value only when the corresponding choice is enabled; silent fallback is prohibited. Use shared credentials only for domains mapped to the same Tragofone customer. The expected customer ID remains tenant-specific and is always verified after login.

**Paused** stops scanning only for the selected domain. The worker automatically sets it after a `401` or customer-identity mismatch; correct the credentials/identity and explicitly change **Paused** to `False` to resume. **Deletion grace (seconds)** defaults to `86400` (24 hours), with a minimum of 60 seconds.

## Extension selection

Open **Tenant Settings → Manage Extensions** to choose which FusionPBX SIP extensions are synchronized. The tenant's **New extensions** setting controls extensions with no explicit policy:

- **Sync automatically** preserves the original behavior and provisions new extensions unless an administrator excludes them.
- **Do not sync until selected** provides an allowlist workflow for new extensions.

Saving the extension list creates tenant-scoped policies keyed by `extension_uuid`. Excluding an extension that has never been provisioned creates no Tragofone user. Excluding an existing mapping disables the Tragofone user and marks it `excluded`; it does not delete the user or mapping. Selecting it again re-enables and reconfigures the same Tragofone `usr_id`. The FusionPBX extension's own Enabled/Disabled state remains authoritative in addition to this selection.

Extension selection affects SIP-user, direct-DID, and self-care provisioning. Excluding an extension disables its Tragofone My Account link and revokes companion self-care sessions. The tenant-wide FusionPBX phonebook continues to follow the tenant enable/pause state.

Self-care access has a separate three-level policy. Every selector defaults to **Inherit**, and resolution uses the first explicit value in this order: user, domain, global. If all three values are **Inherit**, the secure system default is **No**. Thus a user-level **No** can disable My Account while leaving that user's SIP synchronization enabled, and a user-level **Yes** can explicitly override a domain or global **No**. Saving a policy change revokes affected active portal sessions and queues user-configuration updates.

Changing the SIP server, SIP port, transport, outbound proxy, profile, or voicemail code invalidates the tenant's feature-policy hash and queues configuration updates for selected extensions. Changing an extension's **Effective Caller ID Name** updates the mapped Tragofone account name through the existing user-update API.

The FusionPBX SIP password is also the Tragofone application-login password. A SIP password rotation updates both credentials. The application username created as `{extension}@{domain}` remains immutable; extension renumbering updates the mapped SIP identity but does not rename or replace the Tragofone user.

Effective Outbound Caller ID is trusted as configured in FusionPBX and is sent first in `sip_callerid`, followed by direct DIDs. FusionPBX administrators remain responsible for outbound caller-ID authorization.

The shared FusionPBX phonebook uses the same tenant company-admin credentials through the customer enterprise-directory API. No dedicated app user is needed on the validated deployment. Missing contact tables or a contact-specific failure never blocks SIP/DID processing. Tragofone Cloud Contacts remain disabled; phonebook records are tenant-wide Enterprise Directory entries.

The encryption key lives only in `/etc/fusionpbx/tragofone.env`, owned by `root:www-data` with mode `0640`. Changing it without re-entering credentials makes saved ciphertext unreadable.

## Global self-care

Branding and portal security settings are global and Superadmin-only; they cannot be overridden per tenant. Self-care access itself can be set to **Inherit**, **Yes**, or **No** globally, per domain, and per user. Configure the public HTTPS portal directory, name, logo, light/dark background and foreground, button colors, external-forwarding prefixes, and session timeouts. Color pairs must meet 4.5:1 contrast.

The default forwarding policy is either same-company extensions only or same-company extensions plus approved external prefixes. **Rotate Self-Care Salts** revokes active sessions and asynchronously installs new signed Account URLs for eligible users.

Saving a branding or access-policy change increments the global brand version and queues configuration updates for every enabled, selected, synchronized extension. The generated Account URL contains the raw global values plus a per-user subject, salt, and companion signature. See [Self-care portal](selfcare.md).
