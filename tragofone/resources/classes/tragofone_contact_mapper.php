<?php

final class tragofone_contact_mapper {
	public static function supported(array $table_names): bool {
		return in_array('v_contacts', $table_names, true) && in_array('v_contact_phones', $table_names, true);
	}

	public static function map(array $contact, array $phones): ?array {
		$first = trim((string) ($contact['contact_name_given'] ?? ''));
		$last = trim((string) ($contact['contact_name_family'] ?? ''));
		$selected = ['mobile' => null, 'work' => null, 'extension' => null, 'other' => null];
		foreach ($phones as $phone) {
			if (isset($phone['enabled']) && !tragofone_normalizer::boolean($phone['enabled'])) { continue; }
			$number = tragofone_normalizer::phone($phone['phone_number'] ?? null);
			if ($number === null) { continue; }
			$type = strtolower((string) ($phone['phone_type_voice'] ?? $phone['phone_type'] ?? 'other'));
			$key = str_contains($type, 'mobile') ? 'mobile' : (str_contains($type, 'work') ? 'work' : (str_contains($type, 'extension') ? 'extension' : 'other'));
			$selected[$key] ??= $number;
		}
		if ($first === '' && $last === '' && array_filter($selected) === []) { return null; }
		return [
			'ed_first_name' => $first !== '' ? $first : ($last !== '' ? $last : 'Contact'), 'ed_last_name' => $first !== '' ? $last : '',
			'ed_company' => (string) ($contact['contact_organization'] ?? ''), 'ed_title' => (string) ($contact['contact_title'] ?? ''),
			'ed_email_id' => (string) ($contact['contact_email'] ?? ''), 'ed_mobile' => $selected['mobile'],
			'ed_business_phone_number' => $selected['work'], 'ed_extension' => $selected['extension'],
			'ed_other_number' => $selected['other'], 'ed_status' => tragofone_normalizer::boolean($contact['contact_enabled'] ?? false) ? 'Y' : 'N', 'ed_type' => 'default',
		];
	}
}
