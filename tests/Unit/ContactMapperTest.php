<?php
use PHPUnit\Framework\TestCase;

final class ContactMapperTest extends TestCase {
	public function test_capability_detection(): void {
		self::assertTrue(tragofone_contact_mapper::supported(['v_contacts', 'v_contact_phones', 'v_contact_emails']));
		self::assertFalse(tragofone_contact_mapper::supported(['v_contacts']));
	}
	public function test_maps_first_phone_of_each_type(): void {
		$mapped = tragofone_contact_mapper::map(
			['contact_name_given' => 'Ada', 'contact_name_family' => 'Lovelace', 'contact_organization' => 'Analytical'],
			[['phone_label' => 'Mobile', 'phone_type_voice' => 1, 'phone_number' => '+44 7700 900123'], ['phone_label' => 'Work', 'phone_type_voice' => 1, 'phone_number' => '020 7000 0000']],
			[['email_address' => 'ada@example.test', 'email_primary' => true]]
		);
		self::assertSame('Ada', $mapped['ed_first_name']); self::assertSame('+447700900123', $mapped['ed_mobile']);
		self::assertSame('02070000000', $mapped['ed_business_phone_number']); self::assertSame('ada@example.test', $mapped['ed_email_id']);
		self::assertSame('Y', $mapped['ed_status']); self::assertSame('default', $mapped['ed_type']);
	}
	public function test_excludes_private_and_non_voice_contacts(): void {
		self::assertNull(tragofone_contact_mapper::map(['contact_type' => 'private', 'contact_name_given' => 'Hidden'], []));
		$mapped = tragofone_contact_mapper::map(
			['contact_name_given' => 'Voice'],
			[['phone_label' => 'Mobile', 'phone_type_voice' => 0, 'phone_number' => '+14155550100']]
		);
		self::assertNull($mapped['ed_mobile']);
	}
}
