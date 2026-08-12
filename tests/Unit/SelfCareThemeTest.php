<?php
use PHPUnit\Framework\TestCase;

final class SelfCareThemeTest extends TestCase {
	private function theme(): array {
		return tragofone_selfcare_theme::from_config([
			'selfcare_brand_name'=>'Acme Voice', 'selfcare_brand_logo_url'=>'https://pbx.example/app/tragofone/selfcare/logo.php?v=2',
			'selfcare_light_background'=>'F7F8FA','selfcare_light_foreground'=>'172033','selfcare_light_button'=>'1769E0','selfcare_light_button_foreground'=>'FFFFFF',
			'selfcare_dark_background'=>'10141D','selfcare_dark_foreground'=>'F4F7FB','selfcare_dark_button'=>'6EA8FE','selfcare_dark_button_foreground'=>'08101F','selfcare_brand_version'=>2,
		]);
	}

	public function test_account_url_uses_a_compact_signed_brand_reference(): void {
		$subject=tragofone_scanner::uuid();$salt='per-user-secret-salt';$url=tragofone_selfcare_theme::account_url('https://pbx.example/app/tragofone/selfcare',$subject,$salt,$this->theme());parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
		self::assertSame($subject,$query['s']);self::assertSame('2',$query['v']);self::assertSame($salt,$query['tragofone_salt']);
		self::assertTrue(tragofone_selfcare_theme::verify_compact($subject,$salt,2,$query['g']));
		self::assertTrue(tragofone_selfcare_theme::verify_current_compact($subject,$salt,2,2,$query['g']));
		self::assertTrue(tragofone_selfcare_theme::verify_current_compact($subject,$salt,2,3,$query['g']));
		self::assertFalse(tragofone_selfcare_theme::verify_current_compact($subject,$salt,2,1,$query['g']));
		self::assertFalse(tragofone_selfcare_theme::verify_compact($subject,$salt,3,$query['g']));
		self::assertLessThanOrEqual(200,strlen($url));self::assertStringContainsString('/app/tragofone/sc.php?',$url);
	}

	public function test_every_compact_identity_field_rejects_tampering(): void {
		$subject=tragofone_scanner::uuid();$salt='per-user-secret-salt';$url=tragofone_selfcare_theme::account_url('https://pbx.example/app/tragofone/selfcare',$subject,$salt,$this->theme());parse_str((string)parse_url($url,PHP_URL_QUERY),$query);
		self::assertFalse(tragofone_selfcare_theme::verify_compact($subject.'x',$salt,2,$query['g']));
		self::assertFalse(tragofone_selfcare_theme::verify_compact($subject,$salt,3,$query['g']));
		self::assertFalse(tragofone_selfcare_theme::verify_compact($subject,$salt.'x',2,$query['g']));
		self::assertFalse(tragofone_selfcare_theme::verify_compact($subject,$salt,2,$query['g'].'x'));
	}

	public function test_rejects_low_contrast_and_invalid_colors(): void {
		$this->expectException(InvalidArgumentException::class);
		tragofone_selfcare_theme::from_config(['selfcare_brand_name'=>'Brand','selfcare_light_background'=>'FFFFFF','selfcare_light_foreground'=>'FDFDFD']);
	}

	public function test_logo_must_share_the_portal_origin(): void {
		$this->expectException(InvalidArgumentException::class);
		tragofone_selfcare_theme::validate_logo_url('https://tracker.example/logo.png','https://pbx.example/app/tragofone/selfcare');
	}

	public function test_brand_name_rejects_control_characters(): void {
		$this->expectException(InvalidArgumentException::class);
		tragofone_selfcare_theme::from_config(['selfcare_brand_name'=>"Unsafe\nBrand"]);
	}

	public function test_provisioning_creates_an_encrypted_subject_and_enables_my_account(): void {
		$store=new extension_lifecycle_store();$crypto=new tragofone_crypto(str_repeat('k',32));$tenant=['domain_uuid'=>'domain-1','selfcare_enabled'=>true,'selfcare_base_url'=>'https://pbx.example/app/tragofone/selfcare','selfcare_brand_version'=>3,...tragofone_selfcare_theme::DEFAULTS];$extension=['extension_uuid'=>'ext-1','extension'=>'1001'];
		$account=tragofone_selfcare_provisioning::account($store,$crypto,$tenant,$extension);self::assertSame('TRUE',$account['myaccount_status']);self::assertStringContainsString('/app/tragofone/sc.php?',$account['myaccount_url']);self::assertArrayHasKey('ext-1',$store->selfcare_subjects);$salt=$crypto->decrypt($store->selfcare_subjects['ext-1']['encrypted_salt']);self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/',$salt);self::assertLessThanOrEqual(200,strlen($account['myaccount_url']));
	}

	public function test_launch_accepts_epoch_seconds_and_milliseconds(): void {
		self::assertSame(1_723_391_234,tragofone_selfcare_launch::timestamp_seconds('1723391234'));
		self::assertSame(1_723_391_234,tragofone_selfcare_launch::timestamp_seconds('1723391234000'));
		self::assertNull(tragofone_selfcare_launch::timestamp_seconds('17233912340000'));
		self::assertNull(tragofone_selfcare_launch::timestamp_seconds('not-a-time'));
	}

	public function test_provisioning_rotates_legacy_base64url_salts(): void {
		$store=new extension_lifecycle_store();$crypto=new tragofone_crypto(str_repeat('k',32));$legacy='nwOw1CxWPqUGcd6qqtYQ_g';
		$store->selfcare_subjects['ext-1']=['subject_uuid'=>'00000000-0000-4000-8000-000000000001','domain_uuid'=>'domain-1','extension_uuid'=>'ext-1','encrypted_salt'=>$crypto->encrypt($legacy),'active'=>true,'brand_version'=>2];
		$tenant=['domain_uuid'=>'domain-1','selfcare_enabled'=>true,'selfcare_base_url'=>'https://pbx.example/app/tragofone/selfcare','selfcare_brand_version'=>3,...tragofone_selfcare_theme::DEFAULTS];
		tragofone_selfcare_provisioning::account($store,$crypto,$tenant,['extension_uuid'=>'ext-1','extension'=>'1001']);
		$rotated=$crypto->decrypt($store->selfcare_subjects['ext-1']['encrypted_salt']);self::assertNotSame($legacy,$rotated);self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/',$rotated);self::assertTrue($store->selfcare_subjects['ext-1']['active']);
	}

	public function test_launch_hash_uses_the_original_timestamp_value(): void {
		$salt='0123456789abcdef0123456789abcdef';$time='1723391234000';$hash=md5($salt.$time);
		self::assertTrue(tragofone_selfcare_launch::hash_valid($salt,$time,$hash));
		self::assertTrue(tragofone_selfcare_launch::hash_valid($salt,$time,strtoupper($hash)));
		self::assertFalse(tragofone_selfcare_launch::hash_valid($salt,$time,md5($salt.'1723391234')));
	}

	public function test_launch_rejection_codes_do_not_expose_sensitive_details(): void {
		self::assertSame('SC-LAUNCH-03',tragofone_selfcare_launch::rejection_code(new RuntimeException('Signed launch expired.')));
		self::assertSame('SC-LAUNCH-02',tragofone_selfcare_launch::rejection_code(new RuntimeException('secret implementation detail')));
	}
}
