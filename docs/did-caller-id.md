# DID caller-ID synchronization

The resolver reads enabled voice inbound destinations from the tenant. It accepts exactly one action whose application is `transfer` or `extension` and whose target is the synchronized extension. IVRs, ring groups, queues, time conditions, external targets, and multiple/ambiguous actions are ignored.

Numbers are normalized while preserving a leading `+`, de-duplicated, and naturally sorted. If the extension's effective outbound caller ID matches a DID, it is moved first; otherwise the first sorted DID is the default. The complete list is written to `sip_callerid` as a comma-separated value.

Disabling or deleting a direct route removes that DID on the next scan. When the last direct DID is removed, `sip_callerid` is cleared. The companion never substitutes the extension number or an outbound caller ID that is not backed by a direct, enabled DID route.
