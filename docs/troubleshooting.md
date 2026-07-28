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

Use correlation/job UUIDs when gathering diagnostics. Never attach raw request bodies containing credentials.
