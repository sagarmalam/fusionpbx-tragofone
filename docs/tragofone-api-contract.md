# Tragofone API contract

The companion makes no Tragofone code changes and deliberately avoids `create-or-update-with-config` because its lookup is unsuitable for safe multi-tenant idempotency. A configured default profile must already exist for the Tragofone customer; an invalid or missing profile is treated as an API failure and no mapping is persisted unless user creation returns a positive `usr_id`.

Company-admin calls:

- `POST /api/customer/login`
- `GET /api/customer/me`
- `POST /api/customer/user/list`
- `POST /api/customer/user/create`
- `POST /api/customer/user/update`
- `POST /api/customer/user/delete`
- `POST /api/customer/user/update-configurations`
- `POST /api/customer/user/get-configurations`
- `POST /api/customer/user/get-qr-code`

Company-admin enterprise-directory calls:

- `POST /api/customer/enterprise/list`
- `POST /api/customer/enterprise/create`
- `POST /api/customer/enterprise/update`
- `DELETE /api/customer/enterprise/delete`

Tokens are isolated by tenant. `/customer/me` must match the configured customer ID. The validated enterprise-directory endpoints use the same customer bearer token, so contact synchronization does not require an app-user login.

The tested customer login response uses a top-level `access_token`; the client also accepts the older `token` and nested token envelopes. `update-configurations` accepts one flat `configurations` object even though `get-configurations` returns values grouped into sections such as `Sip`, `Call`, `IM`, and `cloudcontacts`. The client flattens the policy at the wire boundary. User creation and configuration remain separate calls.

The current FusionPBX SIP password is sent as `usr_password` on user creation/update and as `sip_auth_password` in SIP configuration. The API's 20-character application-password maximum is enforced before sending. Account-name changes use `user/update` with `user_id` and `usr_account_name`.

The companion also maps FusionPBX extension fields through the existing configuration endpoint:

| FusionPBX source | Tragofone configuration key |
|---|---|
| Extension | `sip_extension`, `sip_auth_username`, and `sip_authid` |
| Call Timeout | `call_noAnswerTimeout` |
| Emergency Caller ID Number | `emergency_numbers` |
| Mailbox Voicemail Enabled | `voicemail_status` |

The published Tragofone schema defines the SIP extension as a string. The companion therefore accepts numeric or alphanumeric identifiers, while enforcing the portal's 2–15 character boundary and ASCII letters/digits. Invalid extensions remain visible in Extension Synchronization but cannot be selected or provisioned. Blank emergency caller IDs are sent as an empty value so a removed assignment is also removed from Tragofone. A missing voicemail mailbox is treated as disabled.

The documented user-update request supports password, account name, status, profile, email, and phone fields, but not `usr_username`. Consequently, the application username is immutable; extension renumbering updates SIP configuration and the local mapping while retaining the existing Tragofone login and `usr_id`.

## QR login

For a synchronized companion-owned mapping, FusionPBX calls `POST /api/customer/user/get-qr-code` with the stored Tragofone `user_id`. The live API may return either an image or a short raw QR payload in `data.qr_code`. Image responses are decoded and validated; raw payloads are rendered locally with FusionPBX's bundled QR library and PHP GD. Only validated PNG, JPEG, or WebP output up to 2 MB and 4096 × 4096 is accepted.

QR data is fetched only after an authenticated, domain-scoped, CSRF-protected administrator action. It is held in request memory for preview, download, or immediate SMTP delivery and is never stored in companion tables, synchronization jobs, logs, or FusionPBX's email queue.

## My Account configuration

Self-care uses the existing `POST /api/customer/user/update-configurations` endpoint with `myaccount_status=TRUE` and a signed FusionPBX `myaccount_url`.

The newly configured URL is a compact `/app/tragofone/sc.php` link containing the opaque subject, global brand version, a 192-bit companion HMAC, and `tragofone_salt`; the complete URL is rejected locally if it exceeds the API's 200-character limit. On mobile and desktop launch, Tragofone removes the salt and appends `tragofone_hash=MD5(salt + epoch)` and `tragofone_time=<epoch>`. No new Tragofone endpoint or server change is required. The PBX validates the compact signature before loading the matching global theme.

The live API sometimes reports application-level `ERROR`, `FAILED`, or `FAILURE` inside an HTTP 200 response. The client treats those envelopes as failed requests, so an invalid profile or rejected configuration cannot be recorded as a successful synchronization. Authentic older compact URLs remain accepted during asynchronous branding rollout and resolve the current trusted PBX theme; future versions, modified signatures, rotated salts, and revoked subjects remain invalid.
