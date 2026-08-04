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
| Feature policy | Dial/SIP/hold/transfer/`*97` enabled; FusionPBX voicemail state synchronized; IM, SMS, video, Cloud Contacts, BLF, Zoom, call forwarding disabled | Passed |
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
| #3 shared application/SIP password | Create and update payload tests; 20-character boundary tests; current OpenAPI user-update contract | Deployed; new users and separate SIP configuration completed successfully |
| #4 outbound proxy | Default and explicit proxy payload tests; tenant-policy change detection | Passed; remote user configuration contained the resolved TLS proxy host and port |
| #5 Effective Outbound Caller ID | Normalization, priority, de-duplication, and no-direct-route tests | Passed; remote `sip_callerid` placed the effective value before the second direct DID |
| #6 account name | User-update payload and mapping-state tests against `usr_account_name` contract | Passed; remote account name matched FusionPBX Effective Caller ID Name |
| #7 extension renumbering | Immutable username/user ID plus updated SIP identity and mapping display tests; OpenAPI confirms `usr_username` is not updateable | Automated/contract validation passed; no destructive live renumber was performed in this deployment pass |

The issue build is installed in the disposable environment. Live verification reads back only non-secret remote fields; passwords, tokens, salts, and full Account URLs are never printed or stored in this document.

## 2026-08-04 extension and QR regression validation

| Area | Automated/contract validation | Live status |
|---|---|---|
| #9 Call Timeout | `call_timeout` normalization and `call_noAnswerTimeout` payload tests | Passed; remote configuration read back `30` seconds |
| #10 Emergency Caller ID | Number normalization, set, and clear payload tests for `emergency_numbers` | Automated set/clear coverage passed; the inspected live extension had no non-empty emergency value |
| #11 Extension validation | Numeric/alphanumeric acceptance and 2–15 character boundary tests; invalid users are not queued | Passed in automated boundary coverage; four valid live test users synchronized |
| #12 Voicemail Enabled | Mailbox join/change detection and TRUE/FALSE `voicemail_status` tests | Passed; enabled live mailbox read back `TRUE` remotely |
| QR login | Mapped-user API request, tenant identity, image/payload extraction, malformed-image rejection, permission/CSRF/no-store package checks | Live raw `data.qr_code` payload rendered, previewed, and downloaded; email delivery awaits a configured PBX mail transport |

The live QR response contains a short raw payload rather than image bytes. The companion renders it through FusionPBX's bundled QR library with PHP GD, validates the PNG, and does not persist it. The test host has no active Sendmail, Postfix, Exim, or configured equivalent, so actual QR email delivery is the only remaining environment-dependent QR check.

## Phase 2 validation status

Phase 2 has 92 unit/contract tests with 341 assertions on the deployed PHP 8.2.32 host, plus the PHP 8.1–8.3 CI matrix. Live acceptance covered valid launch and clean redirect; expiry, replay, and branding-signature rejection; account/DID summary; DND and forwarding conflicts; database/base64 Visual Voicemail listing, playback, read/unread, and deletion; notification email/PIN updates and blank-email clearing; user-level No/Inherit revocation and restoration with the same Tragofone user ID; and compact remote My Account configuration. A Chrome device-metrics run at 320 × 700 confirmed 320px document width, four visible bottom tabs, and no horizontal overflow. Actual SMTP delivery and filesystem-backed voicemail media remain environment-dependent checks.
