# Supported features

| Feature | Status |
|---|---|
| SIP credentials | Supported in MVP |
| Tragofone application password | Same as the current FusionPBX SIP password |
| Application username after extension renumber | Immutable; SIP identity and displayed extension update |
| SIP server/transport/outbound proxy | Supported per tenant with deterministic proxy fallback |
| Tragofone account name | Synchronized from FusionPBX Effective Caller ID Name |
| Per-extension SIP user selection | Supported; tenant default plus explicit include/exclude |
| Audio calling/dialpad | Supported through client configuration |
| Effective outbound caller ID | Supported and trusted from the FusionPBX extension |
| Multiple direct DID caller IDs | Supported as additional caller-ID choices |
| DID assignment mapping/visibility | Supported in MVP |
| One-touch FusionPBX voicemail | Supported; default `*97` |
| Local client call history | Supported by existing client; no data sync |
| FusionPBX shared phonebook | Supported when `v_contacts`, `v_contact_phones`, and `v_contact_emails` are present |
| Tragofone Enterprise Directory | Supported using the tenant company-admin API |
| Tragofone Cloud Contacts | Disabled |
| IM | Explicitly disabled |
| SMS/MMS | Explicitly disabled |
| Video | Explicitly disabled |
| Tragofone-hosted voicemail | Disabled |
| FusionPBX CDR synchronization | Not supported |
| BLF/presence | Not supported |
| DND/call-forward synchronization | Not supported |
| Bidirectional FusionPBX administration | Not supported |

Operational controls include tenant pause/resume, per-extension selection, manual reconciliation, failed-job retry, deletion grace, one-time `401` reauthentication, and expired-lock recovery.

The integration synchronizes only SIP/application credentials, supported enterprise contacts, Effective Outbound Caller ID, and direct DID caller IDs. Call history remains local to the softphone.
