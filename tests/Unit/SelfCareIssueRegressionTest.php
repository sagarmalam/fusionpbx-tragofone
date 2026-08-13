<?php

use PHPUnit\Framework\TestCase;

final class selfcare_issue_database extends database {
	public array $writes = [];

	public function select($sql, $parameters = null, $return_type = 'all'): mixed {
		$this->sql = $sql;
		$this->parameters = $parameters ?? [];
		$this->return_type = $return_type;
		if (str_contains($sql, 'from v_extensions where domain_uuid=:domain_uuid and extension_uuid=:extension_uuid')) {
			return [[
				'update_date'=>'2026-08-12 12:00:00+00', 'do_not_disturb'=>'false', 'follow_me_enabled'=>'false',
				'forward_all_enabled'=>'false', 'forward_all_destination'=>'', 'forward_busy_enabled'=>'false', 'forward_busy_destination'=>'',
				'forward_no_answer_enabled'=>'false', 'forward_no_answer_destination'=>'',
				'forward_user_not_registered_enabled'=>'false', 'forward_user_not_registered_destination'=>'',
			]];
		}
		if (str_contains($sql, 'from v_tragofone_global_config')) { return []; }
		return [];
	}

	public function execute($sql, $parameters = null, $return_type = 'all'): mixed {
		$this->writes[] = [$sql, $parameters, $return_type];
		return $return_type === 'row' ? ['extension_uuid'=>$parameters['extension_uuid'] ?? ''] : true;
	}
}

final class SelfCareIssueRegressionTest extends TestCase {
	private function repository(): tragofone_selfcare_repository {
		return new tragofone_selfcare_repository(new selfcare_issue_database(), new tragofone_crypto(str_repeat('k', 32)));
	}

	private function session(): array {
		return [
			'domain_uuid'=>'00000000-0000-4000-8000-000000000001',
			'extension_uuid'=>'00000000-0000-4000-8000-000000000002',
			'extension'=>'1001', 'number_alias'=>'', 'domain_name'=>'pbx.example.test',
		];
	}

	public function test_forwarding_rejects_non_numeric_characters_without_silently_removing_them(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('only numbers');
		$this->repository()->update_call_state($this->session(), [
			'expected_update_date'=>'2026-08-12 12:00:00+00', 'forward_busy_enabled'=>'true', 'forward_busy_destination'=>'10A0',
		]);
	}

	public function test_forwarding_destination_requires_its_mode_to_be_selected(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Select When busy');
		$this->repository()->update_call_state($this->session(), [
			'expected_update_date'=>'2026-08-12 12:00:00+00', 'forward_busy_destination'=>'1002',
		]);
	}

	public function test_selfcare_pages_include_transient_alerts_local_times_and_auto_read(): void {
		$app=dirname(__DIR__,2).'/tragofone/selfcare';
		$layout=file_get_contents($app.'/_layout.php');$voicemail=file_get_contents($app.'/voicemail.php');$script=file_get_contents($app.'/assets/selfcare.js');
		self::assertStringContainsString('data-auto-dismiss',$layout);
		self::assertStringContainsString('time datetime=', $voicemail);
		self::assertStringContainsString('data-epoch=', $voicemail);
		self::assertStringContainsString("audio.addEventListener('playing'",$script);
		self::assertStringNotContainsString('fetch(form.action',$script);
		self::assertStringContainsString("Intl.DateTimeFormat",$script);
	}

	public function test_call_errors_return_to_call_handling_and_mobile_download_uses_owned_media(): void {
		$app=dirname(__DIR__,2).'/tragofone/selfcare';
		$action=file_get_contents($app.'/action.php');$voicemail=file_get_contents($app.'/voicemail.php');$repository=file_get_contents(dirname($app).'/resources/classes/tragofone_selfcare_repository.php');
		self::assertStringContainsString("'save_calls'=>'calls.php'",$action);
		self::assertStringContainsString('voicemail_playback_token',$voicemail);
		self::assertStringContainsString(' download>Download</a>',$voicemail);
		self::assertStringContainsString("header('Accept-Ranges: bytes')",$repository);
		self::assertStringContainsString('application/octet-stream',$repository);
		self::assertStringContainsString("Content-Disposition:",$repository);
	}

	public function test_mobile_download_token_is_opaque_and_short_lived(): void {
		$crypto=new tragofone_crypto(str_repeat('k',32));$repository=new tragofone_selfcare_repository(new selfcare_issue_database(),$crypto);
		$session_uuid='00000000-0000-4000-8000-000000000003';$message_uuid='00000000-0000-4000-8000-000000000004';
		$token=$repository->voicemail_download_token(['session_uuid'=>$session_uuid],$message_uuid);
		self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/',$token);self::assertStringNotContainsString($session_uuid,$token);self::assertStringNotContainsString($message_uuid,$token);
		$encoded=strtr($token,'-_','+/').str_repeat('=',(4-strlen($token)%4)%4);$payload=json_decode($crypto->decrypt($encoded),true,8,JSON_THROW_ON_ERROR);
		self::assertSame($session_uuid,$payload['session']);self::assertSame($message_uuid,$payload['message']);self::assertTrue($payload['download']);self::assertGreaterThanOrEqual(time()+895,$payload['expires']);self::assertLessThanOrEqual(time()+900,$payload['expires']);
		$playback=$repository->voicemail_playback_token(['session_uuid'=>$session_uuid],$message_uuid);
		$encoded=strtr($playback,'-_','+/').str_repeat('=',(4-strlen($playback)%4)%4);$playback_payload=json_decode($crypto->decrypt($encoded),true,8,JSON_THROW_ON_ERROR);
		self::assertFalse($playback_payload['download']);
	}

	public function test_rejected_tragofone_calls_have_a_companion_busy_forward_dialplan(): void {
		$dialplan=file_get_contents(dirname(__DIR__,2).'/tragofone/resources/switch/conf/dialplan/894_tragofone-forward-rejected.xml');
		self::assertStringContainsString('^CALL_REJECTED$',$dialplan);
		self::assertStringContainsString('${forward_busy_enabled}',$dialplan);
		self::assertStringContainsString('${forward_busy_destination}',$dialplan);
		self::assertStringContainsString('application="transfer"',$dialplan);
	}

	public function test_extension_search_has_consistent_border_and_empty_result_state(): void {
		$page=file_get_contents(dirname(__DIR__,2).'/tragofone/extension_sync.php');
		self::assertStringContainsString('.tf-search{min-height:40px!important;border:1px solid',$page);
		self::assertStringContainsString('No matching records found.',$page);
		self::assertStringContainsString("empty.hidden=visible!==0",$page);
	}
}
