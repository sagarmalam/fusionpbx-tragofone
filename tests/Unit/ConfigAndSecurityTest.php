<?php
use PHPUnit\Framework\TestCase;

final class ConfigAndSecurityTest extends TestCase {
	public function test_inheritance_is_explicit(): void {
		$this->expectException(InvalidArgumentException::class);
		tragofone_config::resolve(['base_url' => 'https://global.test', 'customer_username' => 'g', 'encrypted_customer_password' => 'x'], ['inherit_global_url' => false, 'inherit_global_credentials' => false]);
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
