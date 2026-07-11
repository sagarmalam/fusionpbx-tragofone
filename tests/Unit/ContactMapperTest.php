<?php
use PHPUnit\Framework\TestCase;

final class ContactMapperTest extends TestCase {
	public function test_capability_detection(): void {
		self::assertTrue(tragofone_contact_mapper::supported(['v_contacts', 'v_contact_phones']));
		self::assertFalse(tragofone_contact_mapper::supported(['v_contacts']));
	}
	public function test_maps_first_phone_of_each_type(): void {
		$mapped = tragofone_contact_mapper::map(
			['contact_name_given' => 'Ada', 'contact_name_family' => 'Lovelace', 'contact_organization' => 'Analytical', 'contact_enabled' => true],
			[['phone_type' => 'mobile', 'phone_number' => '+44 7700 900123'], ['phone_type' => 'work', 'phone_number' => '020 7000 0000']]
		);
		self::assertSame('Ada', $mapped['ed_first_name']); self::assertSame('+447700900123', $mapped['ed_mobile']);
		self::assertSame('02070000000', $mapped['ed_business_phone_number']); self::assertSame('default', $mapped['ed_type']);
	}
}
