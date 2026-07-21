# Tragofone API contract

The companion makes no Tragofone code changes and deliberately avoids `create-or-update-with-config` because its lookup is unsuitable for safe multi-tenant idempotency.

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

App-user contact calls:

- `POST /api/user/enterprise/create`
- `POST /api/user/enterprise/update`
- `DELETE /api/user/enterprise/delete`

Tokens are isolated by tenant. `/customer/me` must match the configured customer ID. Exact response envelopes, configuration grouping, app-user login, customer validation policies, and enterprise-contact permission must be contract-tested against the deployment before enabling production writes.

The tested customer login response uses a top-level `access_token`; the client also accepts the older `token` and nested token envelopes. `update-configurations` accepts one flat `configurations` object even though `get-configurations` returns values grouped into sections such as `Sip`, `Call`, `IM`, and `cloudcontacts`. The client flattens the policy at the wire boundary. User creation and configuration remain separate calls, and the generated Tragofone application password is limited to 16 characters to remain within the API's 20-character maximum.
