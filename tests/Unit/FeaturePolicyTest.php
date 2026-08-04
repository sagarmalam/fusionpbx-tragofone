<?php
use PHPUnit\Framework\TestCase;

final class FeaturePolicyTest extends TestCase {
	public function test_restricted_policy(): void {
		$config = tragofone_feature_policy::configuration(
			[
				'extension' => '1001', 'password' => 'secret', 'domain_name' => 'pbx.test',
				'call_timeout' => '45', 'emergency_caller_id_number' => '+1 (415) 555-0911',
				'voicemail_enabled' => 'true',
			],
			['sip_server' => '', 'sip_port' => 5061, 'sip_protocol' => 'tls', 'voicemail_code' => '*97'],
			['+14155550100', '+14155550101']
		);
		self::assertSame('1001', $config['Sip']['sip_auth_username']);
		self::assertSame('+14155550100,+14155550101', $config['Sip']['sip_callerid']);
		self::assertSame('pbx.test', $config['Sip']['sip_auth_outboundProxyServer']);
		self::assertSame('5061', $config['Sip']['sip_auth_outboundProxyPort']);
		self::assertSame('*97', $config['Call']['call_onetouch_voicemailNumber']);
		self::assertSame('45', $config['Call']['call_noAnswerTimeout']);
		self::assertSame('+14155550911', $config['emergency']['emergency_numbers']);
		self::assertSame('TRUE', $config['voicemail']['voicemail_status']);
		self::assertSame('FALSE', $config['IM']['im_status']);
		self::assertSame('FALSE', $config['Sms']['sms_status']);
		self::assertSame('FALSE', $config['Video']['video_enableVideo']);
		self::assertSame('FALSE', $config['cloudcontacts']['cloudcontacts_status']);
		self::assertSame('FALSE', $config['configurations']['configurations_dndVisibility']);
		self::assertSame('FALSE', $config['blf']['autoenable_blf']);
		self::assertSame('FALSE', $config['zoom']['zoom_status']);
	}

	public function test_clears_emergency_number_and_disables_voicemail_from_fusionpbx(): void {
		$config = tragofone_feature_policy::configuration(
			['extension'=>'AB12','password'=>'secret','domain_name'=>'pbx.test','call_timeout'=>null,'emergency_caller_id_number'=>'','voicemail_enabled'=>'false'],
			['sip_server'=>'','sip_port'=>5061,'sip_protocol'=>'tls','voicemail_code'=>'*97'], []
		);
		self::assertSame('AB12', $config['Sip']['sip_extension']);
		self::assertSame('30', $config['Call']['call_noAnswerTimeout']);
		self::assertSame('', $config['emergency']['emergency_numbers']);
		self::assertSame('FALSE', $config['voicemail']['voicemail_status']);
	}

	public function test_uses_explicit_outbound_proxy_when_configured(): void {
		$config = tragofone_feature_policy::configuration(
			['extension' => '1001', 'password' => 'secret', 'domain_name' => 'pbx.test'],
			[
				'sip_server' => 'sip.pbx.test', 'sip_port' => 5061, 'sip_protocol' => 'tls',
				'outbound_proxy_server' => 'proxy.pbx.test', 'outbound_proxy_port' => 5081,
				'voicemail_code' => '*97',
			],
			[]
		);
		self::assertSame('proxy.pbx.test', $config['Sip']['sip_auth_outboundProxyServer']);
		self::assertSame('5081', $config['Sip']['sip_auth_outboundProxyPort']);
	}

	public function test_clears_caller_id_when_resolver_supplies_none(): void {
		$config = tragofone_feature_policy::configuration(
			['extension' => '1001', 'password' => 'secret', 'domain_name' => 'pbx.test', 'effective_caller_id_number' => '+14155559999'],
			['sip_server' => '', 'sip_port' => 5061, 'sip_protocol' => 'tls', 'voicemail_code' => '*97'],
			[]
		);
		self::assertSame('', $config['Sip']['sip_callerid']);
	}

	public function test_enables_only_the_signed_companion_account_url(): void {
		$config = tragofone_feature_policy::configuration(
			['extension'=>'1001','password'=>'secret','domain_name'=>'pbx.test'],
			['sip_server'=>'','sip_port'=>5061,'sip_protocol'=>'tls','voicemail_code'=>'*97'], [],
			['myaccount_status'=>'TRUE','myaccount_url'=>'https://pbx.test/app/tragofone/selfcare/launch.php?signed=1']
		);
		self::assertSame('TRUE',$config['account']['myaccount_status']);self::assertStringContainsString('/selfcare/launch.php',$config['account']['myaccount_url']);
		self::assertSame('FALSE',$config['voicemail']['voicemail_status']);self::assertSame('FALSE',$config['CallForwarding']['callforwarding']);
	}
}
