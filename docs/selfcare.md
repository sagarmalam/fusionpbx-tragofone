# Tragofone Self-Care Portal

## Purpose

The Phase 2 portal is a public FusionPBX route opened by Tragofone My Account. Public means that it does not show the FusionPBX login; it is never anonymous. A fresh signed Tragofone launch is required before a short server-side session is issued.

## Superadmin setup

1. Install or upgrade the companion and run both FusionPBX upgrade passes.
2. Open **Advanced → Tragofone Integration → Global**.
3. In **Self-Care Portal**, set global self-care access to **Yes** and enter the public HTTPS directory URL, normally `https://pbx.example/app/tragofone/selfcare`.
4. Set the global portal name and optionally upload a PNG, JPEG, or WebP logo.
5. Enter light and dark background, text, button, and button-text colors. The form rejects invalid hexadecimal values and color pairs below WCAG AA contrast.
6. Choose whether external forwarding is allowed. If enabled, enter comma-separated prefixes such as `+1,+44`; same-company internal extensions remain allowed.
7. Set idle and absolute session timeouts and save. Both default to 86400 seconds (24 hours); shorter values can be selected from 300 seconds upward.
8. Review queued jobs and mappings. Every eligible synchronized extension should receive `myaccount_status=TRUE` and a compact signed `myaccount_url` referencing the current global theme version.

Branding is global. Company admins cannot override it. Saving a theme, logo, portal name, URL, or enable-state change increments the brand version and queues all eligible users for asynchronous reprovisioning.

## Access policy

Global, domain, and user settings each offer **Inherit**, **Yes**, and **No**, defaulting to **Inherit**. The user setting overrides the domain setting, and the domain setting overrides the global setting. If every level inherits, self-care is disabled. User policies are configured on **Extension Synchronization**, independently of the SIP synchronization checkbox.

An explicit **No** revokes affected sessions and sends `myaccount_status=FALSE`; an explicit or inherited effective **Yes** sends the signed Account URL. Domain and user administrators can change only the levels allowed by their existing module permissions. Branding remains global and Superadmin-only.

Use **Rotate Self-Care Salts** after suspected URL exposure or as a security operation. Rotation revokes every active portal session, deactivates the old assertions, and queues a newly signed Account URL for each eligible user. The audit entry records the operation without recording salts, complete URLs, or signatures.

## End-user experience

From Tragofone, open **My Account**. A valid launch opens four responsive tabs:

- **Home:** display name, extension, Effective Outbound Caller ID, direct DIDs, mailbox, DND, and forwarding summary.
- **Call handling:** DND and always/busy/no-answer/not-registered forwarding. Internal destinations must belong to the same company; external numbers must match the Superadmin prefix policy.
- **Voicemail:** owned message list, caller and time, duration, transcription when present, playback, download, read/unread, and confirmed permanent deletion.
- **Settings:** one notification email, a new voicemail PIN, and an on-demand QR code for logging in on another Tragofone device. The current PIN is never displayed and the QR is never stored.

The portal follows the WebView/device light or dark preference. There is no portal theme switch. When the session expires, return to Tragofone and open My Account again.

## Account URL and authentication

Tragofone limits `myaccount_url` to 200 characters. The worker therefore configures a compact extension-specific URL containing an opaque subject (`s`), global brand version (`v`), a 192-bit companion signature (`g`), and the per-user `tragofone_salt`. The raw theme remains in the Superadmin-owned FusionPBX configuration and is loaded only after the compact signature and brand version validate. Tragofone removes the salt and adds `tragofone_hash` and `tragofone_time` at launch.

The portal validates the two-minute Tragofone assertion, 60-second future clock skew, companion HMAC, brand version, replay state, extension/mapping status, and effective global/domain/user access policy. Modified subject or brand-version parameters fail signature validation. It then redirects to a clean URL and stores only a hashed session token server-side. Legacy long-form signed links remain launch-compatible during migration, while all newly provisioned links use the compact format.

## Mockups

- [Interactive responsive mockup](mockups/selfcare.html)
- [Interactive voicemail and state mockups](mockups/selfcare-states.html)
- [Interactive Superadmin theme editor](mockups/selfcare-admin-theme.html)
- [Desktop light screenshot](mockups/selfcare-desktop-light.png)
- [Mobile dark screenshot](mockups/selfcare-mobile-dark.png)
- [Voicemail, settings, and states screenshot](mockups/selfcare-states.png)
- [Global theme editor screenshot](mockups/selfcare-admin-theme.png)

## Boundaries

This release does not expose SIP passwords, caller-ID changes, CDR history, server recordings, voicemail greetings, cross-tenant data, or generic FusionPBX administration. Tragofone-native voicemail, DND, and forwarding controls remain disabled; the companion portal is the authoritative self-care surface.
