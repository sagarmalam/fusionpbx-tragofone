# Tragofone Integration User Manual

This manual explains how FusionPBX Superadmins and company administrators use the Tragofone Integration application after it has been installed. Installation and service setup are covered separately in [Installation](installation.md).

## What the module does

For each enabled FusionPBX domain, the module can synchronize:

- Selected SIP extensions and their current SIP credentials.
- Directly assigned inbound DIDs as Tragofone caller-ID choices.
- The shared FusionPBX phonebook to the Tragofone Enterprise Directory.
- A restricted Tragofone client policy with audio calling, dialpad, local call history, enterprise contacts, and one-touch FusionPBX voicemail.

Phase 1 does not synchronize CDRs, IM, SMS/MMS, video, BLF, DND, call forwarding, or Tragofone Cloud Contacts. Phase 2 lets an authenticated user manage FusionPBX DND and forwarding through the companion portal; those controls are not synchronized into Tragofone's native UI. See [Supported features](supported-features.md) for the complete matrix.

## Roles and access

The **Tragofone Integration** menu is visible by default to both the FusionPBX `superadmin` and `admin` groups. It is not visible to ordinary FusionPBX users. The pages shown inside the module depend on the user's Tragofone permissions and FusionPBX domain scope.

| Capability | Superadmin | Company administrator (`admin`) |
|---|---|---|
| See **Advanced → Tragofone Integration** | Yes | Yes |
| Open Global Settings | Yes | No |
| Select any FusionPBX domain | Yes | No; limited to assigned domain access |
| Configure Tenant Settings | Yes | Yes, for the active authorized domain |
| Select extensions | Yes | Yes, for the active authorized domain |
| View mappings and jobs | Yes | Yes, for the active authorized domain |
| Retry jobs and run reconciliation | Yes | Yes, for the active authorized domain |

If the intended policy is **Superadmin-only module access**, remove the Tragofone permissions and menu access from the FusionPBX `admin` group. That is a deployment policy change and disables the Company administrator workflow described in this manual.

### FusionPBX Superadmin

A Superadmin can:

- Configure optional system-wide Tragofone defaults.
- Select and administer any FusionPBX domain.
- Configure each domain's Tragofone customer connection.
- Choose extensions, inspect mappings and jobs, retry failed work, and run reconciliation.

Only Superadmins receive the `tragofone_global_view` and `tragofone_global_edit` permissions by default.

### Company administrator

In this manual, **Company administrator** means a FusionPBX user in the `admin` group whose access is restricted to a company domain. It is different from the **Tragofone company-admin credential** entered on the settings page.

A Company administrator can manage only the active FusionPBX domain and can:

- Configure or update that domain's Tragofone settings.
- Select which extensions synchronize.
- View extension, DID, and contact mappings.
- View jobs, retry eligible jobs, and run reconciliation.

Global defaults are not visible to the Company administrator. FusionPBX domain and permission enforcement remains authoritative.

## Opening the module

1. Sign in to FusionPBX.
2. Confirm the correct company domain is selected in the domain selector at the top of the page.
3. Open **Advanced → Tragofone Integration**.

Both Superadmins and authorized Company administrators can see this menu. A Company administrator will not see the **Global** navigation item.

The overview provides these pages:

| Page | Purpose |
|---|---|
| Global Settings | Optional defaults available only to Superadmins |
| Tenant Settings | Tragofone connection and provisioning behavior for the active domain |
| Extension Sync | Select the extensions allowed to synchronize |
| Mappings | Inspect companion-owned extension, DID, and contact identifiers |
| Jobs | Inspect background operations and retry eligible failures |
| Reconciliation | Compare FusionPBX state with companion mappings and queue repairs |

Every module page shows the active domain, a persistent navigation bar, and a **Back to Overview** action. On narrow screens, swipe the navigation bar horizontally if all tabs do not fit. Always verify the selected domain before changing settings or extension selection.

## Superadmin setup

### 1. Configure optional global defaults

Open **Advanced → Tragofone Integration → Global Settings** if multiple domains will share values. Configure the Tragofone server URL and the optional company-admin username/password in the clearly labeled **Global Tragofone Credentials** section. SIP port, protocol, and voicemail code are available as provisioning fallbacks.

Global values do not apply automatically. Each tenant must explicitly choose **Inherit global URL** and/or **Inherit global credentials** in **Tenant Settings**. Use global credentials only for tenants mapped to the same Tragofone customer; the tenant-specific expected customer ID is still verified. This prevents a tenant from silently receiving or using the wrong credentials.

Skip global settings when every company uses its own Tragofone URL and credentials.

### 2. Select the company domain

Use the FusionPBX domain selector to activate the company being configured. The selected domain is the security and data-isolation boundary for every tenant page, mapping, and job.

### 3. Configure Tenant Settings

Open **Tenant Settings** and complete the following sections.

#### General

- **Integration state:** Enable or disable synchronization for this domain.
- **Processing state:** Use **Paused** to stop scanning and job processing without changing existing Tragofone users or mappings.
- **New extensions:** Choose the default for extensions without an explicit selection:
  - **Sync automatically** provisions new extensions unless an administrator excludes them.
  - **Do not sync until selected** implements an allowlist for new extensions.

#### Tragofone Endpoint

- **URL source:** Select the global URL or a tenant-specific URL.
- **Server URL:** Enter the HTTPS base URL for the existing Tragofone customer APIs when not inheriting it.

#### Tragofone Account & Identity

- **Credential source:** Select global or tenant-specific credentials.
- **Company admin username/password:** Enter the existing Tragofone company-admin account. A saved password is masked; leave the password field blank to preserve it.
- **Expected customer ID:** Enter the Tragofone customer ID for this FusionPBX domain. Synchronization pauses if authentication returns a different customer.
- **Default profile ID:** Enter the Tragofone application profile assigned to synchronized users.

Never reuse credentials for a different Tragofone company. Customer-ID verification is an intentional tenant-isolation control.

#### SIP Provisioning

- **SIP server:** Enter the SIP registration server. When blank, the integration uses the FusionPBX domain.
- **SIP port and transport:** Select the registration port and TLS, TCP, or UDP transport.
- **Outbound proxy server and port:** Enter explicit proxy values when required. When either field is blank, the module sends the resolved SIP server or SIP port for that field.
- **Voicemail code:** Enter the FusionPBX voicemail feature code, normally `*97`.

#### Lifecycle & Safety

- **Deletion grace:** The delay before deleting a Tragofone user whose FusionPBX extension was removed. The recommended default is `86400` seconds (24 hours).
- **Choose extensions:** Opens the per-extension selection page.

Select **Save Tenant Settings**. A success message confirms the write. Saving with a blank password preserves the encrypted credential already stored.

### 4. Choose extensions

Open **Manage Extensions** or **Extension Sync**.

1. Search by extension number or caller-ID name if required.
2. Select each extension that Tragofone may provision.
3. Use **Select all** or **Select none** for bulk changes.
4. Select **Save Extension Selection**.

The page reports how many jobs were queued and displays each extension's FusionPBX state, Tragofone mapping status, Tragofone user ID, and last synchronization time.

Extension selection has these safety rules:

- An excluded extension that has never synchronized does not create a Tragofone user.
- Excluding an existing mapped extension disables its Tragofone user but preserves the mapping and `usr_id`.
- Re-selecting an excluded extension re-enables and reconfigures the same Tragofone user.
- Excluding a user is different from deleting the FusionPBX extension. Deletion follows the configured grace period and can ultimately delete the companion-owned Tragofone user.
- The FusionPBX extension's own Enabled/Disabled state remains authoritative.
- Extension selection affects SIP and DID synchronization. The shared company phonebook remains tenant-wide.

### 5. Verify initial synchronization

Open **Mappings** and confirm:

- Each selected extension has a Tragofone username and user ID.
- The extension status becomes `synchronized`.
- Direct DID mappings appear for eligible inbound routes.
- Supported contacts receive Enterprise Directory IDs.

Open **Jobs** and confirm new operations complete. Processing is asynchronous; a short `pending` or `processing` state is normal.

## Company administrator workflow

### Add a new user

1. Create and enable the SIP extension in FusionPBX with its authentication password and caller-ID name.
2. Open **Advanced → Tragofone Integration → Extension Sync**.
3. Select the new extension if the tenant uses an allowlist, or confirm it is selected automatically.
4. Save the selection.
5. Confirm the mapping becomes `synchronized` and record the generated Tragofone application username, normally `{extension}@{domain}`.

The SIP username remains the FusionPBX extension number. Do not manually create a duplicate user in Tragofone.

### Temporarily remove Tragofone access

1. Open **Extension Sync**.
2. Clear the user's checkbox.
3. Save the selection.
4. Confirm the mapping changes from `exclude_pending` to `excluded`.

This disables the mapped Tragofone user without deleting it. Re-select the extension to restore the same user ID.

### Rotate SIP credentials

The Tragofone application login uses `{original-extension}@{domain}` as its username and the current FusionPBX SIP password as its password. Change the extension password in FusionPBX to rotate both the Tragofone application-login password and SIP authentication password. The scanner detects the update and queues both existing API changes. Confirm completion on **Jobs** or **Mappings**. Do not edit either password independently in Tragofone because FusionPBX is the source of truth.

The Tragofone application username remains unchanged when an extension is renumbered. For example, renumbering `201` to `2001` keeps the login `201@company.example` and the same Tragofone user ID, but updates the SIP username, authentication ID, SIP extension, account name, and displayed FusionPBX extension to the current values.

### Manage outbound caller ID and direct DIDs

Set the extension's Effective Outbound Caller ID and, when required, configure enabled voice-capable inbound destinations that route directly and unambiguously to the extension. On the next scan:

- The extension's Effective Outbound Caller ID is trusted and becomes the first caller-ID choice.
- Every eligible direct DID becomes an additional caller-ID choice.
- Removing or disabling the direct route removes that DID.
- Removing the final direct DID leaves the effective outbound value in place. The list is cleared only when that value is also blank.

IVRs, queues, ring groups, time conditions, external routes, and ambiguous action chains are ignored for direct-DID mappings. They do not prevent the explicitly configured Effective Outbound Caller ID from synchronizing. See [Caller-ID synchronization](did-caller-id.md).

### Manage the company phonebook

Create or update shared contacts in FusionPBX. Supported public contact fields synchronize to the tenant-wide Tragofone Enterprise Directory. Private contacts, photos, attachments, notes, fax-only entries, and empty contacts are excluded.

Phonebook synchronization is independent of individual extension selection. See [Enterprise contact synchronization](contact-sync.md).

### Permanently remove a user

Disable or remove the FusionPBX extension according to the company's offboarding policy. The integration first disables the mapped Tragofone user and applies the configured deletion grace period. If the extension returns during the grace period, the mapping can be recovered. After the grace period, the worker may delete only the Tragofone user owned by that companion mapping.

Use extension exclusion instead when the intention is temporary Tragofone suspension without deletion.

## Monitoring and recovery

### Mapping statuses

| Status | Meaning |
|---|---|
| `created` | Tragofone user exists; configuration is still being applied |
| `synchronized` | Latest SIP, DID, and feature policy was applied successfully |
| `exclude_pending` | An administrator excluded the user; disable job is queued |
| `excluded` | Tragofone user is disabled and its mapping is preserved |
| `include_pending` | An excluded user was selected again; restore job is queued |
| `disable_pending` / `disabled` | FusionPBX extension lifecycle requires the user to be disabled |
| `deletion_pending` | The deletion grace period is active |
| `delete_pending` / `deleted` | Final mapped-user deletion is queued or complete |

### Job statuses

| Status | Action |
|---|---|
| `pending` | Wait for the worker timer |
| `processing` | Worker currently owns the job |
| `completed` | No action required |
| `retry` | A transient failure will retry automatically; inspect its error |
| `dead` | Correct the cause, then use **Retry** or run reconciliation |

Retries are tenant-scoped. Never retry authentication failures before correcting credentials and customer identity.

### Run reconciliation

Use **Reconciliation → Run reconciliation** after correcting configuration, recovering from an outage, or investigating drift. Reconciliation compares current FusionPBX entities with companion-owned mappings and queues only required repairs. It does not adopt or delete unrelated Tragofone records.

### Resume a paused tenant

Authentication failure, `401`, or customer-ID mismatch can pause only the affected tenant.

1. Correct the URL, credentials, or expected customer ID in **Tenant Settings**.
2. Set **Processing state** to **Running**.
3. Save settings.
4. Run reconciliation.
5. Confirm jobs complete and mappings return to `synchronized`.

## Security and operating rules

- Confirm the active FusionPBX domain before every administrative change.
- Use a dedicated Tragofone company-admin credential for the intended customer.
- Never paste passwords, API tokens, encryption keys, or full job payloads into tickets or chat.
- Leave TLS enabled and use an HTTPS Tragofone endpoint.
- Do not modify companion mapping tables or Tragofone-owned IDs manually.
- Do not manually delete Tragofone users or contacts managed by an active mapping.
- Use **Paused** before planned Tragofone maintenance when jobs should not be processed.

For operational failures, see [Troubleshooting](troubleshooting.md). For security ownership and threat boundaries, see [Security](security.md).

## Phase 2 self-care

Only a Superadmin can configure branding. Open **Global → Self-Care Portal**, enter the public HTTPS portal directory, upload an optional logo, set both theme palettes, choose forwarding/session policy, and save. Branding remains identical for every company.

Self-care access has **Inherit**, **Yes**, and **No** at global, domain, and user levels. The default is **Inherit**. User overrides domain, domain overrides global, and an all-Inherit chain resolves to No. Configure the domain value in **Tenant Settings** and individual values in **Extension Synchronization**. This policy is independent of the SIP synchronization checkbox, although an excluded SIP user cannot receive self-care.

End users open **My Account** inside Tragofone. They can see their own extension/caller IDs, manage DND and approved forwarding, play or delete their own FusionPBX voicemail, and update voicemail email/PIN. They do not use FusionPBX credentials. When a session expires, they reopen My Account to obtain a fresh signed launch. See [Self-care portal](selfcare.md).
