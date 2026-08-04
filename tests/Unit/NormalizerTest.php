<?php
use PHPUnit\Framework\TestCase;

final class NormalizerTest extends TestCase {
	public function test_username_is_tenant_unique(): void {
		self::assertSame('1001@company-a.example.com', tragofone_normalizer::username('1001', 'Company-A.Example.com'));
		self::assertNotSame(
			tragofone_normalizer::username('1001', 'company-a.example.com'),
			tragofone_normalizer::username('1001', 'company-b.example.com')
		);
	}
	public function test_fallback_contains_uuid_prefix(): void {
		self::assertSame('1001.a81e39b2@company.test', tragofone_normalizer::fallback_username('1001', 'company.test', 'a81e39b2-1111-2222-3333-444444444444'));
	}
	public function test_hash_is_key_order_independent(): void {
		self::assertSame(tragofone_normalizer::hash(['b' => 2, 'a' => 1]), tragofone_normalizer::hash(['a' => 1, 'b' => 2]));
	}
	public function test_fusionpbx_string_booleans_are_normalized(): void {
		self::assertTrue(tragofone_normalizer::boolean('true'));
		self::assertFalse(tragofone_normalizer::boolean('false'));
	}
	public function test_application_password_reuses_sip_password_with_api_limit(): void {
		$password = 'Abc123!@#4567890xyZ';
		self::assertSame($password, tragofone_normalizer::application_password($password));
		$this->expectException(InvalidArgumentException::class);
		tragofone_normalizer::application_password(str_repeat('x', 21));
	}
	public function test_application_password_rejects_empty_sip_password(): void {
		$this->expectException(InvalidArgumentException::class);
		tragofone_normalizer::application_password('');
	}

	public function test_sip_extension_accepts_numeric_and_alphanumeric_values(): void {
		self::assertSame('10', tragofone_normalizer::sip_extension('10'));
		self::assertSame('Sales15', tragofone_normalizer::sip_extension(' Sales15 '));
		self::assertSame(str_repeat('A', 15), tragofone_normalizer::sip_extension(str_repeat('A', 15)));
	}

	/** @dataProvider invalid_sip_extensions */
	public function test_sip_extension_enforces_tragofone_boundary(string $extension): void {
		$this->expectException(InvalidArgumentException::class);
		tragofone_normalizer::sip_extension($extension);
	}

	public static function invalid_sip_extensions(): array {
		return [['1'], [str_repeat('1', 16)], ['10 01'], ['10+01'], ['å100']];
	}

	public function test_call_timeout_is_normalized_without_changing_its_value(): void {
		self::assertSame('60', tragofone_normalizer::call_timeout('060'));
		self::assertSame('30', tragofone_normalizer::call_timeout(null));
	}
}
