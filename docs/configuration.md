# Configuration

Each FusionPBX domain maps to exactly one Tragofone customer. Enter the HTTPS base URL, company-admin username/password, expected Tragofone customer ID, default profile ID, SIP server/port/protocol, optional outbound proxy, and voicemail code (`*97` by default). Authentication is accepted only when `/api/customer/me` returns the configured customer ID.

Global URL/credentials are optional. A domain inherits them only through explicit inheritance flags; silent fallback is prohibited.

The shared FusionPBX phonebook uses the same tenant company-admin credentials through the customer enterprise-directory API. No dedicated app user is needed on the validated deployment. Missing contact tables or a contact-specific failure never blocks SIP/DID processing. Tragofone Cloud Contacts remain disabled; phonebook records are tenant-wide Enterprise Directory entries.

The encryption key lives only in `/etc/fusionpbx/tragofone.env`, owned by `root:www-data` with mode `0640`. Changing it without re-entering credentials makes saved ciphertext unreadable.
