<?php
use PHPUnit\Framework\TestCase;

final class NormalizerTest extends TestCase {
	public function test_username_is_tenant_unique(): void {
		self::assertSame('1001@company-a.example.com', tragofone_normalizer::username('1001', 'Company-A.Example.com'));
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
}
