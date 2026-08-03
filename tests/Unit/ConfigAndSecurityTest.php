<?php
use PHPUnit\Framework\TestCase;

final class ConfigAndSecurityTest extends TestCase {
	public function test_inheritance_is_explicit(): void {
		$this->expectException(InvalidArgumentException::class);
		tragofone_config::resolve(['base_url' => 'https://global.test', 'customer_username' => 'g', 'encrypted_customer_password' => 'x'], ['inherit_global_url' => false, 'inherit_global_credentials' => false]);
	}
	public function test_postgresql_false_strings_do_not_enable_inheritance(): void {
		$resolved = tragofone_config::resolve(
			['base_url' => 'https://global.test', 'customer_username' => 'global', 'encrypted_customer_password' => 'global-secret'],
			['inherit_global_url' => 'f', 'inherit_global_credentials' => 'false', 'base_url' => 'https://tenant.test', 'customer_username' => 'tenant', 'encrypted_customer_password' => 'tenant-secret']
		);
		self::assertSame('https://tenant.test', $resolved['base_url']); self::assertSame('tenant', $resolved['customer_username']);
	}
	public function test_explicit_inheritance_uses_global_url_and_credentials(): void {
		$resolved = tragofone_config::resolve(
			['base_url' => 'https://global.test', 'customer_username' => 'global', 'encrypted_customer_password' => 'global-secret'],
			['inherit_global_url' => true, 'inherit_global_credentials' => true]
		);
		self::assertSame('https://global.test', $resolved['base_url']); self::assertSame('global', $resolved['customer_username']);
	}
	public function test_selfcare_branding_is_always_global(): void {
		$resolved=tragofone_config::resolve(
			['selfcare_enabled'=>true,'selfcare_brand_name'=>'Global Brand','base_url'=>'https://global.test','customer_username'=>'global','encrypted_customer_password'=>'secret'],
			['inherit_global_url'=>true,'inherit_global_credentials'=>true,'selfcare_brand_name'=>'Tenant Brand']
		);
		self::assertSame('Global Brand',$resolved['selfcare_brand_name']);self::assertTrue($resolved['selfcare_enabled']);
	}
	public function test_selfcare_policy_defaults_to_inherit_and_securely_resolves_to_no(): void {
		self::assertSame('inherit', tragofone_selfcare_policy::normalize(null));
		self::assertFalse(tragofone_selfcare_policy::enabled('inherit', 'inherit', 'inherit'));
	}
	public function test_selfcare_policy_uses_user_then_domain_then_global_precedence(): void {
		self::assertTrue(tragofone_selfcare_policy::enabled('yes', 'inherit', 'inherit'));
		self::assertFalse(tragofone_selfcare_policy::enabled('yes', 'inherit', 'no'));
		self::assertTrue(tragofone_selfcare_policy::enabled('no', 'yes', 'inherit'));
		self::assertTrue(tragofone_selfcare_policy::enabled('no', 'no', 'yes'));
	}
	public function test_resolved_tenant_keeps_domain_policy_separate_from_global_branding(): void {
		$resolved=tragofone_config::resolve(
			['selfcare_policy'=>'no','selfcare_brand_name'=>'Global Brand','base_url'=>'https://global.test','customer_username'=>'global','encrypted_customer_password'=>'secret'],
			['inherit_global_url'=>true,'inherit_global_credentials'=>true,'selfcare_policy'=>'yes']
		);
		self::assertSame('no',$resolved['selfcare_global_policy']);self::assertSame('yes',$resolved['selfcare_policy']);self::assertTrue($resolved['selfcare_enabled']);
	}
	public function test_redacts_nested_secrets(): void {
		$data = tragofone_redactor::data(['token' => 'abc', 'Sip' => ['sip_auth_password' => 'secret', 'sip_authid' => '1001']]);
		self::assertSame('[REDACTED]', $data['token']); self::assertSame('[REDACTED]', $data['Sip']['sip_auth_password']);
	}
	public function test_crypto_round_trip(): void {
		$crypto = new tragofone_crypto(str_repeat('k', 32)); self::assertSame('secret', $crypto->decrypt($crypto->encrypt('secret')));
	}
	public function test_crypto_reads_the_protected_environment_file(): void {
		$previous = getenv('TRAGOFONE_ENCRYPTION_KEY');
		putenv('TRAGOFONE_ENCRYPTION_KEY');
		$file = tempnam(sys_get_temp_dir(), 'tragofone-env-');
		try {
			file_put_contents($file, 'TRAGOFONE_ENCRYPTION_KEY='.str_repeat('f', 32).PHP_EOL);
			$crypto = tragofone_crypto::from_environment($file);
			self::assertSame('secret', $crypto->decrypt($crypto->encrypt('secret')));
		} finally {
			@unlink($file);
			if ($previous === false) { putenv('TRAGOFONE_ENCRYPTION_KEY'); }
			else { putenv('TRAGOFONE_ENCRYPTION_KEY='.$previous); }
		}
	}
}
