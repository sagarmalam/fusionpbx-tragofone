# Supported features

| Feature | Status |
|---|---|
| SIP credentials | Supported |
| Tragofone application password | Same as the current FusionPBX SIP password |
| Application username after extension renumber | Immutable; SIP identity and displayed extension update |
| SIP server/transport/outbound proxy | Supported per tenant with deterministic proxy fallback |
| SIP extension format | Numeric and alphanumeric values supported; 2–15 ASCII letters/digits |
| No-answer timeout | Synchronized from FusionPBX Call Timeout |
| Tragofone account name | Synchronized from FusionPBX Effective Caller ID Name |
| Per-extension SIP user selection | Supported; tenant default plus explicit include/exclude |
| Tragofone QR login | Supported for synchronized mapped users; preview and download from FusionPBX |
| QR delivery by email | Supported through configured FusionPBX SMTP; sent directly with a raster attachment |
| Audio calling/dialpad | Supported through client configuration |
| Effective outbound caller ID | Supported and trusted from the FusionPBX extension |
| Emergency number | Synchronized from FusionPBX Emergency Caller ID Number |
| Multiple direct DID caller IDs | Supported as additional caller-ID choices |
| DID assignment mapping/visibility | Supported |
| One-touch FusionPBX voicemail | Supported; default `*97` |
| Tragofone voicemail enablement | Synchronized from the FusionPBX mailbox's Voicemail Enabled state |
| Local client call history | Supported by existing client; no data sync |
| FusionPBX shared phonebook | Supported when `v_contacts`, `v_contact_phones`, and `v_contact_emails` are present |
| Tragofone Enterprise Directory | Supported using the tenant company-admin API |
| Tragofone Cloud Contacts | Disabled |
| IM | Explicitly disabled |
| SMS/MMS | Explicitly disabled |
| Video | Explicitly disabled |
| Tragofone voicemail integration | Enabled or disabled with the FusionPBX mailbox |
| FusionPBX CDR synchronization | Not supported |
| BLF/presence | Not supported |
| Tragofone-native DND/call-forward synchronization | Not supported; use the companion self-care portal |
| Bidirectional FusionPBX administration | Not supported |

Operational controls include tenant pause/resume, per-extension selection, manual reconciliation, failed-job retry, deletion grace, one-time `401` reauthentication, and expired-lock recovery.

The integration synchronizes SIP/application credentials, supported enterprise contacts, call timeout, voicemail enablement, Effective and Emergency Caller ID, and direct DID caller IDs. Call history remains local to the softphone.

## Self-care portal

| Feature | Status |
|---|---|
| Signed Tragofone My Account launch | Supported |
| Global light/dark branding referenced by signed Account URL version | Supported; compact URL stays within the 200-character API limit |
| Global/domain/user self-care access | Supported; Inherit/Yes/No with user-first precedence |
| Self-care QR for another device | Supported for the authenticated synchronized user; displayed on demand and never stored |
| Account, extension, DID, and caller-ID summary | Supported, read-only |
| DND and all/busy/no-answer/not-registered forwarding | Supported |
| External forwarding | Conditional on global prefix policy |
| Visual Voicemail playback/download/read/delete | Supported when FusionPBX voicemail schema and media are available |
| Voicemail notification email and PIN | Supported |
| Tenant-specific branding | Not supported; branding is global |
| Server recordings and CDR history | Not supported in this release |
| Voicemail greeting management | Not supported in this release |
| Native Tragofone Visual Voicemail | Not supported in this release |
