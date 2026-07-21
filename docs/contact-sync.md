# Enterprise contact synchronization

This integration synchronizes the shared FusionPBX tenant phonebook to the Tragofone Enterprise Directory. It does not use or enable Tragofone Cloud Contacts.

Capability requires `v_contacts`, `v_contact_phones`, and `v_contact_emails`. The mapper supports given/family name, organization, title, role, primary email, and the first enabled voice mobile/work/extension/other numbers. It sends `ed_type=default` and excludes photos, attachments, notes, empty records, cross-domain records, private contacts, fax-only numbers, video-only numbers, and text-only numbers.

The existing tenant company-admin token calls `/api/customer/enterprise/list`, `create`, `update`, and `delete`; no dedicated contact app user is required on the validated Tragofone deployment. The companion stores `contact_uuid → ed_id`. Creates and updates use that immutable mapping, and a full scan deletes only directory records owned by a companion mapping. Contact jobs use a lower priority than SIP jobs, so a contact failure does not block extension provisioning.
