# Configuration

Each FusionPBX domain maps to exactly one Tragofone customer. Enter the HTTPS base URL, company-admin username/password, SIP server/port/protocol, optional outbound proxy, profile, and voicemail code (`*97` by default). A successful connection test must confirm the returned Tragofone customer ID before production activation.

Global URL/credentials are optional. A domain inherits them only through explicit inheritance flags; silent fallback is prohibited.

Contacts require a dedicated Tragofone app user with enterprise-contact modification permission. Configure its credentials per tenant after validating the exact user-login contract. Missing contact capability never blocks SIP/DID processing.

The encryption key lives only in `/etc/fusionpbx/tragofone.env`. Changing it without re-entering credentials makes saved ciphertext unreadable.
