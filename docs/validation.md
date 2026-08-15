# Validation matrix

The provisioning baseline through commit `376fbd4` was validated on 2026-07-22 against a private FusionPBX test domain and a disposable Tragofone company. Test credentials, tokens, SIP passwords, encryption material, self-care salts, hashes, and full Account URLs are not stored in this repository.

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
| #5 Outbound Caller ID Number | Source-field priority, normalization, de-duplication, fallback, and no-direct-route tests | Passed live; extension 304 synchronized and remote `sip_callerid` read back `30000004` while Effective Caller ID Number was blank |
| #6 account name | User-update payload and mapping-state tests against `usr_account_name` contract | Passed; remote account name matched FusionPBX Effective Caller ID Name |
| #7 extension renumbering | Immutable username/user ID plus updated SIP identity and mapping display tests; OpenAPI confirms `usr_username` is not updateable | Automated/contract validation passed; no destructive live renumber was performed in this deployment pass |

The issue build is installed in the disposable environment. Live verification reads back only non-secret remote fields; passwords, tokens, salts, and full Account URLs are never printed or stored in this document.

## 2026-08-04 extension and QR regression validation

| Area | Automated/contract validation | Live status |
|---|---|---|
| #9 Call Timeout | `call_timeout` normalization and `call_noAnswerTimeout` payload tests | Passed; remote configuration read back `30` seconds |
| #10 Emergency Caller ID | Number normalization, set, and clear payload tests for `emergency_numbers` | Automated set/clear coverage passed; the inspected live extension had no non-empty emergency value |
| #11 Extension validation | Numeric/alphanumeric acceptance and 2–15 character boundary tests; invalid users are not queued | Companion boundary passed; FusionPBX's native editor may still store broader identifiers, which remain visibly ineligible and unsynchronized |
| #12 Voicemail Enabled | Mailbox join/change detection and TRUE/FALSE `voicemail_status` tests | Passed; enabled live mailbox read back `TRUE` remotely |
| QR login | Mapped-user API request, tenant identity, image/payload extraction, malformed-image rejection, permission/CSRF/no-store package checks | Live raw `data.qr_code` payload rendered, previewed, and downloaded; email delivery awaits a configured PBX mail transport |

The live QR response contains a short raw payload rather than image bytes. The companion renders it through FusionPBX's bundled QR library with PHP GD, validates the PNG, and does not persist it. The test host has no active Sendmail, Postfix, Exim, or configured equivalent, so actual QR email delivery is the only remaining environment-dependent QR check.

## Self-care validation status

The customer-release suite has 122 unit/contract tests with 506 assertions and passes on PHP 8.1, 8.2, and 8.3. Live acceptance covered valid launch and clean redirect; expiry, replay, and branding-signature rejection; account/DID summary; DND and forwarding conflicts; database/base64 Visual Voicemail listing, playback, read/unread, and deletion; notification email/PIN updates and blank-email clearing; user-level No/Inherit revocation and restoration with the same Tragofone user ID; compact remote My Account configuration; absence of a manual logout control; and a session-owned self-care device QR rendered from the live Tragofone payload as a valid 495 × 495 PNG. A Chrome device-metrics run at 320 × 700 confirmed 320px document width, four visible bottom tabs, and no horizontal overflow. Actual SMTP delivery remains environment-dependent.

## 2026-08-12 My Account regression validation

Issue #22 was reopened repeatedly because the Tragofone desktop application could retain a previously provisioned compact Account URL while a global branding update advanced the PBX brand version. The permanent compatibility rule now accepts any authentic non-future compact version, always renders the current trusted PBX theme, and preserves salt rotation and subject revocation as explicit invalidation boundaries.

The exact merged release commit was deployed to FusionPBX 5.5.12 on PHP 8.2.32. A live matrix passed 11/11 cases across multiple synchronized extensions: current URL, stale-but-authentic URL, future-version rejection, modified-signature rejection, expired assertion rejection, first-use acceptance, replay rejection, clean portal session, and 24-hour idle/absolute policy. The real Tragofone 3.38.21 desktop application also opened My Account successfully with both a cached prior-brand URL and the current reprovisioned URL. Worker and reconciliation services completed successfully after restart.

## 2026-08-13 reopened-issue validation

Version 0.2.3 was installed on the FusionPBX 5.5.12 / PHP 8.2.32 QA host before these checks.

| Issue | Live validation | Result |
|---|---|---|
| #5 Outbound caller ID | Extension 304 had Outbound Caller ID `30000004` and a blank Effective number; the completed job contained the same DID and Tragofone configuration readback returned `sip_callerid=30000004` | Passed |
| #11 Extension length | The installed normalizer accepted 2 and 15 alphanumeric characters and rejected 1, 16, 18, and punctuation-containing values before synchronization | Passed |
| #31 Voicemail Read state | An unread filesystem-backed message changed immediately from **New / Mark read** to **Read / Mark unread** when native playback began; PostgreSQL recorded `message_status=saved` and `read_epoch` | Passed |
| #34 When busy after Reject | A controlled `CALL_REJECTED` bridge for extension 304 executed the post-bridge hook and transferred to configured busy destination 301 before voicemail; temporary dial-string state was restored | Passed |
| #36 Mobile voicemail media | Cookie-free playback returned `206 audio/wav` with a valid byte range; cookie-free direct download returned a complete valid 97,324-byte WAV | Android passed physical-device QA on 0.2.3; 0.2.4 changes direct download to the real audio type plus attachment disposition for iOS WebView preview/share compatibility |

## 2026-08-15 queued-job and iOS regression hardening

Version 0.2.4 adds an execution-time read of the current FusionPBX extension before every remote user operation. The automated regression queues a valid create snapshot, changes the live extension to one character, and verifies that no Tragofone create request is sent. The Extension Synchronization UI now gives invalid rows a dedicated **Not eligible** badge and exact validation reason. The iOS download response keeps `audio/wav`, uses attachment disposition, supplies an explicit filename in both HTTP and HTML, and omits the email-only `Content-Transfer-Encoding` header that caused WKWebView to display raw bytes.

## 2026-08-15 maintenance-command validation

Version 0.2.5 was tested on the FusionPBX 5.5.12 / PHP 8.2.32 QA host. The candidate `status` and `doctor` checks passed before installation. A real protected backup produced root-only mode-0700/0600 artifacts, verified every SHA-256 checksum, contained 42 Tragofone PostgreSQL objects, and archived the installed files, protected configuration, and systemd units. The managed 0.2.4 → 0.2.5 upgrade, installed-copy `repair`, timer repair, worker, reconciliation, and log commands all completed successfully. A subsequent attempt to upgrade from the original 0.2.3 source was refused as a downgrade while both timers remained active. The integrated suite passes 122 tests with 506 assertions on PHP 8.1, 8.2, and 8.3.
