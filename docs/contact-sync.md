# Enterprise contact synchronization

Capability requires `v_contacts` and `v_contact_phones`. The mapper supports given/family name, organization, title, email, and first enabled mobile/work/extension/other numbers. It emits `ed_type=default` and excludes photos, attachments, notes, empty records, cross-domain records, and private records by default.

The companion stores `contact_uuid → ed_id`; only mapped contacts may be updated or deleted. Contact APIs require a dedicated app-user token with enterprise-contact modification permission. The MVP contains capability detection, normalization, schema, client methods, and mapping infrastructure; enable live contact writes only after the target app-user authentication contract is validated.
