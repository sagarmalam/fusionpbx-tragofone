# Caller-ID synchronization

The extension's normalized FusionPBX **Effective Outbound Caller ID** is trusted as the primary Tragofone caller ID, whether or not it has a direct inbound route. Administrators are responsible for ensuring that FusionPBX permits the tenant and extension to present that number.

The resolver also reads enabled voice inbound destinations from the tenant. It accepts exactly one action whose application is `transfer` or `extension` and whose target is the synchronized extension. IVRs, ring groups, queues, time conditions, external targets, and multiple/ambiguous actions do not create direct-DID mappings.

Numbers are normalized while preserving a leading `+`, de-duplicated, and naturally sorted. The effective outbound caller ID is placed first, followed by the extension's direct DIDs. The complete list is written to `sip_callerid` as a comma-separated value.

Disabling or deleting a direct route removes that DID on the next scan. If an Effective Outbound Caller ID remains, it remains available; `sip_callerid` is cleared only when the effective value is blank and no direct DID remains.
