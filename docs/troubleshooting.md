# Troubleshooting

- **Authentication failed:** re-enter tenant credentials and verify `/api/customer/login` manually without logging the token.
- **Identity mismatch:** credentials belong to a different Tragofone customer; synchronization is intentionally blocked.
- **Jobs remain pending:** inspect timer/service status and PostgreSQL connectivity.
- **Dead configuration job:** validate profile ID and exact `update-configurations` wire shape.
- **No DID:** ensure the destination is enabled, voice-capable, inbound, and contains one direct extension action.
- **Contacts unavailable:** verify FusionPBX contact tables, dedicated app-user login, and enterprise-contact permission.
- **Registration fails:** confirm SIP server, transport, port, proxy, username, and password at source.

Use correlation/job UUIDs when gathering diagnostics. Never attach raw request bodies containing credentials.
