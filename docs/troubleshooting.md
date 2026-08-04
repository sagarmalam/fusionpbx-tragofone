# Troubleshooting

- **Authentication failed:** re-enter tenant credentials and verify `/api/customer/login` manually without logging the token. The tenant is paused automatically; unpause it after correction.
- **Identity mismatch:** credentials belong to a different Tragofone customer; synchronization is intentionally blocked.
- **Jobs remain pending:** inspect timer/service status and PostgreSQL connectivity.
- **Dead or delayed job:** open **Synchronization Jobs** and use **Retry** after correcting the cause. Retry is tenant-scoped and available only with `tragofone_job_retry`.
- **Possible drift or missed deletion:** open **Reconciliation** and choose **Run reconciliation**. An already synchronized tenant should queue zero jobs.
- **Dead user create/update job reporting password length:** the deployment reuses the FusionPBX SIP password for Tragofone application login, whose API limit is 20 characters. Set a non-empty SIP password of at most 20 characters, then retry or reconcile the failed entity.
- **Configuration call succeeds but SIP fields remain empty:** the deployment expects a flat `configurations` object. Upgrade to a build that flattens grouped policy values at the API boundary.
- **Tenant settings report an unreadable encryption key:** set `/etc/fusionpbx/tragofone.env` ownership to `root:www-data` and mode to `0640`; do not expose the file through the web root.
- **No caller ID:** verify the extension's Effective Outbound Caller ID. For additional direct-DID choices, ensure each destination is enabled, voice-capable, inbound, and contains one direct extension action.
- **Phonebook contacts unavailable:** verify `v_contacts`, `v_contact_phones`, and `v_contact_emails`, then inspect contact jobs and the customer enterprise-directory API response. Do not enable Cloud Contacts as a workaround.
- **Registration fails:** confirm SIP server, transport, port, proxy, username, and password at source.
- **Extension cannot be selected:** Tragofone extensions must contain 2–15 ASCII letters or digits. FusionPBX permits broader identifiers, but the companion marks them unsupported and never sends them remotely. Numeric and alphanumeric values are both supported.
- **Timeout, emergency number, or voicemail state is stale:** verify FusionPBX Call Timeout, Emergency Caller ID Number, and Voicemail Enabled, then run a full reconciliation. They are sent as `call_noAnswerTimeout`, `emergency_numbers`, and `voicemail_status`; blank emergency values clear the remote field and a missing mailbox disables voicemail.
- **My Account is missing:** check the effective user → domain → global self-care policy, confirm the extension is selected and synchronized, and confirm its latest configuration job completed with `myaccount_status=TRUE`. An all-Inherit chain resolves to No.
- **My Account link is rejected:** open My Account again; launches expire in two minutes and are single-use. Verify PBX/Tragofone clocks and the global brand version if every fresh link fails.
- **Branding did not update:** save Global Settings, confirm user update jobs were queued, and run reconciliation. Raw theme values live in each Account URL, so propagation requires successful reprovisioning.
- **Portal immediately expires:** confirm HTTPS, cookie support in the Tragofone WebView, stable source IP/user agent during the session, and configured idle/absolute timeouts.
- **Visual Voicemail unavailable:** verify enabled `v_voicemails` and `v_voicemail_messages` records, PHP-FPM read access to the FusionPBX voicemail storage directory, and database/base64 storage configuration.
- **Forwarding rejected:** internal destinations must be enabled extensions in the same domain. External destinations require the global enable flag and an allowed prefix.

Use correlation/job UUIDs when gathering diagnostics. Never attach raw request bodies containing credentials.
