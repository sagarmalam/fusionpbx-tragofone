# DID caller-ID synchronization

The resolver reads enabled voice inbound destinations from the tenant. It accepts exactly one action whose application is `transfer` or `extension` and whose target is the synchronized extension. IVRs, ring groups, queues, time conditions, external targets, and multiple/ambiguous actions are ignored.

Numbers are normalized while preserving a leading `+`, de-duplicated, and naturally sorted. If the extension's effective outbound caller ID matches a DID, it is moved first; otherwise the first sorted DID is the default. The complete list is written to `sip_callerid` as a comma-separated value.
