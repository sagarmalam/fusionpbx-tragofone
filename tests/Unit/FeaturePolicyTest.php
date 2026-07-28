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
		self::assertSame('pbx.test', $config['Sip']['sip_auth_outboundProxyServer']);
		self::assertSame('5061', $config['Sip']['sip_auth_outboundProxyPort']);
		self::assertSame('*97', $config['Call']['call_onetouch_voicemailNumber']);
		self::assertSame('FALSE', $config['IM']['im_status']);
		self::assertSame('FALSE', $config['Sms']['sms_status']);
		self::assertSame('FALSE', $config['Video']['video_enableVideo']);
		self::assertSame('FALSE', $config['cloudcontacts']['cloudcontacts_status']);
		self::assertSame('FALSE', $config['configurations']['configurations_dndVisibility']);
		self::assertSame('FALSE', $config['blf']['autoenable_blf']);
		self::assertSame('FALSE', $config['zoom']['zoom_status']);
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
}
