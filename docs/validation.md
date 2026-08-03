# Validation matrix

The original MVP baseline through commit `376fbd4` was validated on 2026-07-22 against a private FusionPBX test domain and a disposable Tragofone company. Test credentials, tokens, SIP passwords, and encryption material are not stored in this repository.

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

## 2026-07-28 issue regression validation

| Issue | Automated/contract validation | Live status |
|---|---|---|
| #3 shared application/SIP password | Create and update payload tests; 20-character boundary tests; current OpenAPI user-update contract | Awaiting deployment to the disposable PBX |
| #4 outbound proxy | Default and explicit proxy payload tests; tenant-policy change detection | Awaiting deployment to the disposable PBX |
| #5 Effective Outbound Caller ID | Normalization, priority, de-duplication, and no-direct-route tests | Awaiting deployment to the disposable PBX |
| #6 account name | User-update payload and mapping-state tests against `usr_account_name` contract | Awaiting deployment to the disposable PBX |
| #7 extension renumbering | Immutable username/user ID plus updated SIP identity and mapping display tests; OpenAPI confirms `usr_username` is not updateable | Awaiting deployment to the disposable PBX |

The live server remained reachable over HTTPS during this regression pass, but its SSH port was filtered from the test runner, so the updated build could not yet be installed there. Do not interpret automated success as completion of the live rows above.

## Phase 2 validation status

Phase 2 is covered by the PHP 8.1–8.3 unit/contract matrix for branding normalization and contrast, signed Account URLs and tampering, global configuration inheritance, feature-policy payloads, encrypted subjects, lifecycle reprovisioning, and public package/security invariants. Responsive light/dark mockups are stored under `docs/mockups/` and visually reviewed. Live Tragofone WebView launches, native FusionPBX forwarding/cache notifications, and both voicemail storage modes still require the disposable integration environment before production acceptance.
