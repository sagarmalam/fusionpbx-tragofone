<?php

final class tragofone_feature_policy {
	public static function configuration(array $extension, array $tenant, array $dids, ?array $selfcare_account = null): array {
		$sip_extension = tragofone_normalizer::sip_extension((string) ($extension['extension'] ?? ''));
		$sip_server = trim((string) ($tenant['sip_server'] ?? ''));
		if ($sip_server === '') { $sip_server = (string) $extension['domain_name']; }
		$sip_port = (string) ($tenant['sip_port'] ?? 5061);
		$outbound_proxy_server = trim((string) ($tenant['outbound_proxy_server'] ?? ''));
		if ($outbound_proxy_server === '') { $outbound_proxy_server = $sip_server; }
		$outbound_proxy_port = trim((string) ($tenant['outbound_proxy_port'] ?? ''));
		if ($outbound_proxy_port === '' || $outbound_proxy_port === '0') { $outbound_proxy_port = $sip_port; }
		return [
			'Sip' => [
				'sip_auth_username' => $sip_extension, 'sip_authid' => $sip_extension,
				'sip_auth_password' => (string) $extension['password'],
				'sip_auth_sipServer' => $sip_server,
				'sip_auth_sipPort' => $sip_port, 'sip_auth_sipProtocol' => $tenant['sip_protocol'],
				'sip_auth_outboundProxyServer' => $outbound_proxy_server,
				'sip_auth_outboundProxyPort' => $outbound_proxy_port,
				// The scanner places the trusted FusionPBX Outbound Caller ID first,
				// followed by normalized direct-DID choices.
				'sip_extension' => $sip_extension, 'sip_callerid' => implode(',', $dids),
				'sip_register_interval' => '3600', 'sip_register_respectServerExpires' => 'TRUE',
			],
			'Call' => [
				'call_allowHold' => 'TRUE', 'call_allowTransfer' => 'TRUE',
				'call_onetouch_voicemailNumber' => $tenant['voicemail_code'],
				'call_noAnswerTimeout' => tragofone_normalizer::call_timeout($extension['call_timeout'] ?? null),
			],
			'Video' => ['video_enableVideo' => 'FALSE'], 'IM' => ['im_status' => 'FALSE'],
			'Sms' => ['sms_status' => 'FALSE', 'sms_callerId' => ''],
			'voicemail' => ['voicemail_status' => tragofone_normalizer::boolean($extension['voicemail_enabled'] ?? false) ? 'TRUE' : 'FALSE'],
			'emergency' => ['emergency_numbers' => tragofone_normalizer::phone($extension['emergency_caller_id_number'] ?? null) ?? ''],
			'cloudcontacts' => ['cloudcontacts_status' => 'FALSE'], 'crm' => ['crm_integration' => 'FALSE'],
			'textableintegration' => ['textable_integration' => 'FALSE'], 'CallForwarding' => ['callforwarding' => 'FALSE'],
			'configurations' => ['configurations_dndVisibility' => 'FALSE', 'configurations_autoanswerVisibility' => 'FALSE'],
			'blf' => ['autoenable_blf' => 'FALSE'], 'zoom' => ['zoom_status' => 'FALSE'],
			'account' => $selfcare_account ?? ['myaccount_status' => 'FALSE', 'myaccount_url' => ''],
			'extendedsidepanel' => ['extendedsidepanel_status' => 'FALSE', 'extendedsidepanel_url' => ''],
			'customlink' => ['customlink_status1' => 'FALSE', 'customlink_status2' => 'FALSE'],
		];
	}
}
