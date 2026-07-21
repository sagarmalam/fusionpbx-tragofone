<?php

final class tragofone_contact_mapper {
	public static function supported(array $table_names): bool {
		return in_array('v_contacts', $table_names, true) && in_array('v_contact_phones', $table_names, true) && in_array('v_contact_emails', $table_names, true);
	}

	public static function map(array $contact, array $phones, array $emails = []): ?array {
		if (strtolower(trim((string) ($contact['contact_type'] ?? ''))) === 'private') { return null; }
		$first = trim((string) ($contact['contact_name_given'] ?? ''));
		$last = trim((string) ($contact['contact_name_family'] ?? ''));
		$selected = ['mobile' => null, 'work' => null, 'extension' => null, 'other' => null];
		foreach ($phones as $phone) {
			if (isset($phone['phone_type_voice']) && (int) $phone['phone_type_voice'] === 0) { continue; }
			$number = tragofone_normalizer::phone($phone['phone_number'] ?? null);
			$extension = tragofone_normalizer::phone($phone['phone_extension'] ?? null);
			$label = strtolower((string) ($phone['phone_label'] ?? $phone['phone_type'] ?? 'other'));
			$key = (str_contains($label, 'mobile') || str_contains($label, 'cell')) ? 'mobile'
				: ((str_contains($label, 'work') || str_contains($label, 'business') || str_contains($label, 'office')) ? 'work'
				: ((str_contains($label, 'extension') || $label === 'ext') ? 'extension' : 'other'));
			if ($number !== null) { $selected[$key] ??= $number; }
			if ($extension !== null) { $selected['extension'] ??= $extension; }
		}
		usort($emails, static fn ($a, $b) => (int) tragofone_normalizer::boolean($b['email_primary'] ?? false) <=> (int) tragofone_normalizer::boolean($a['email_primary'] ?? false));
		$email = '';
		foreach ($emails as $candidate) {
			$email = trim((string) ($candidate['email_address'] ?? ''));
			if ($email !== '') { break; }
		}
		$organization = trim((string) ($contact['contact_organization'] ?? ''));
		if ($first === '' && $last === '' && $organization === '' && array_filter($selected) === []) { return null; }
		if ($first === '') { $first = $last !== '' ? $last : ($organization !== '' ? $organization : 'Contact'); $last = ''; }
		$enabled = !array_key_exists('contact_enabled', $contact) || tragofone_normalizer::boolean($contact['contact_enabled']);
		return [
			'ed_first_name' => $first, 'ed_last_name' => $last,
			'ed_company' => $organization, 'ed_title' => (string) ($contact['contact_title'] ?? ''),
			'ed_role' => (string) ($contact['contact_role'] ?? ''), 'ed_email_id' => $email,
			'ed_mobile' => $selected['mobile'],
			'ed_business_phone_number' => $selected['work'], 'ed_extension' => $selected['extension'],
			'ed_other_number' => $selected['other'], 'ed_status' => $enabled ? 'Y' : 'N', 'ed_type' => 'default',
		];
	}
}
