<?php
use PHPUnit\Framework\TestCase;

final class FeaturePolicyTest extends TestCase {
	public function test_restricted_policy(): void {
		$config = tragofone_feature_policy::configuration(
			['extension' => '1001', 'password' => 'secret', 'domain_name' => 'pbx.test'],
			['sip_server' => '', 'sip_port' => 5061, 'sip_protocol' => 'tls', 'voicemail_code' => '*97'],
			['+14155550100', '+14155550101']
		);
		self::assertSame('1001', $config['Sip']['sip_auth_username']);
		self::assertSame('+14155550100,+14155550101', $config['Sip']['sip_callerid']);
		self::assertSame('*97', $config['Call']['call_onetouch_voicemailNumber']);
		self::assertSame('FALSE', $config['IM']['im_status']);
		self::assertSame('FALSE', $config['SMS']['sms_status']);
		self::assertSame('FALSE', $config['Video']['video_enableVideo']);
		self::assertSame('TRUE', $config['CloudContacts']['cloudcontacts_status']);
	}
}
