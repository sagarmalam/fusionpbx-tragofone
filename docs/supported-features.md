# Supported features

| Feature | Status |
|---|---|
| SIP credentials | Supported in MVP |
| Audio calling/dialpad | Supported through client configuration |
| Multiple direct DID caller IDs | Supported in MVP |
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

Operational controls include tenant pause/resume, manual reconciliation, failed-job retry, deletion grace, one-time `401` reauthentication, and expired-lock recovery.

The integration synchronizes only SIP registration data, supported enterprise contacts, and direct DID caller IDs. Call history remains local to the softphone.
