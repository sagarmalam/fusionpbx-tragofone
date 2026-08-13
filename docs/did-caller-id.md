# Caller-ID synchronization

The extension's normalized FusionPBX **Outbound Caller ID Number** is trusted as the primary Tragofone caller ID, whether or not it has a direct inbound route. If it is blank, the companion falls back to **Effective Caller ID Number** for compatibility with existing installations. Administrators are responsible for ensuring that FusionPBX permits the tenant and extension to present the selected number.

The resolver also reads enabled voice inbound destinations from the tenant. It accepts exactly one action whose application is `transfer` or `extension` and whose target is the synchronized extension. IVRs, ring groups, queues, time conditions, external targets, and multiple/ambiguous actions do not create direct-DID mappings.

Numbers are normalized while preserving a leading `+`, de-duplicated, and naturally sorted. The selected outbound caller ID is placed first, followed by the extension's direct DIDs. The complete list is written to `sip_callerid` as a comma-separated value.

Disabling or deleting a direct route removes that DID on the next scan. If an Outbound Caller ID Number (or its effective fallback) remains, it remains available; `sip_callerid` is cleared only when both source fields are blank and no direct DID remains.
