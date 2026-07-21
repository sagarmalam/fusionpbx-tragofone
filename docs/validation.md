# Validation matrix

The MVP was validated on 2026-07-22 against a private FusionPBX test domain and a disposable Tragofone company. Test credentials, tokens, SIP passwords, and encryption material are not stored in this repository.

| Area | Cases | Result |
|---|---|---|
| Tenant boundary | Customer login and `/customer/me` expected-customer verification | Passed |
| SIP users | Create, configuration, password rotation API acceptance, disable, re-enable with the same `usr_id` | Passed |
| SIP transport | Real TLS REGISTER digest challenge/response using the current FusionPBX credential | Passed |
| Extension deletion | Immediate disable, configurable grace, final API deletion, mapping tombstone | Passed |
| Duplicate prevention | Repeated full reconciliation queued zero unchanged jobs; extension recovery keeps mapped identity | Passed |
| DIDs | Direct route, two DIDs, deterministic sort, effective-caller-ID preference, ignored non-direct route | Passed |
| DID removal | Disable one, disable all, clear `sip_callerid`, restore two-DID state | Passed |
| Feature policy | Dial/SIP/hold/transfer/`*97` enabled; IM, SMS, video, hosted voicemail, Cloud Contacts, BLF, Zoom, call forwarding disabled | Passed |
| Phonebook | Schema detection, create, update, field normalization, mapping-owned delete | Passed |
| Failure isolation | Transient contact failure retries while a following SIP job completes | Passed |
| Retry policy | 1, 5, 15 minutes; 1, 3, 6 hours; permanent failures become dead | Passed |
| Manual controls | GUI reconciliation and dead-job Retry | Passed |
| Crash recovery | Expired `processing` lease reclaimed and completed after worker restart | Passed |
| Security | TLS verification, redaction, encrypted credential storage, tenant-scoped actions, no repository secrets | Passed |

The test leaves two active extensions, two active direct DID mappings, and one synchronized phonebook contact for administrator inspection. Disposable user/contact records were deleted from Tragofone and remain only as companion tombstones for auditability.
