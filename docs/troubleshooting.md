# Troubleshooting

- **Authentication failed:** re-enter tenant credentials and verify `/api/customer/login` manually without logging the token.
- **Identity mismatch:** credentials belong to a different Tragofone customer; synchronization is intentionally blocked.
- **Jobs remain pending:** inspect timer/service status and PostgreSQL connectivity.
- **Dead user-create job reporting password length:** upgrade to a build that generates an application password within the API's 20-character limit, then retry or reconcile the failed entity.
- **Configuration call succeeds but SIP fields remain empty:** the deployment expects a flat `configurations` object. Upgrade to a build that flattens grouped policy values at the API boundary.
- **Tenant settings report an unreadable encryption key:** set `/etc/fusionpbx/tragofone.env` ownership to `root:www-data` and mode to `0640`; do not expose the file through the web root.
- **No DID:** ensure the destination is enabled, voice-capable, inbound, and contains one direct extension action.
- **Contacts unavailable:** verify FusionPBX contact tables, dedicated app-user login, and enterprise-contact permission.
- **Registration fails:** confirm SIP server, transport, port, proxy, username, and password at source.

Use correlation/job UUIDs when gathering diagnostics. Never attach raw request bodies containing credentials.
