<?php
use PHPUnit\Framework\TestCase;

final class PackageLayoutTest extends TestCase {
	public function test_native_app_contains_required_discovery_files(): void {
		$app = dirname(__DIR__, 2).'/tragofone';
		self::assertFileExists($app.'/app_config.php');
		self::assertFileExists($app.'/app_defaults.php');
		self::assertFileExists($app.'/app_menu.php');
		self::assertFileExists($app.'/index.php');
		self::assertFileExists($app.'/selfcare/launch.php');
		self::assertFileExists($app.'/selfcare/index.php');
		self::assertFileExists($app.'/selfcare/assets/selfcare.css');
		self::assertFileExists($app.'/qr_code.php');
	}

	public function test_installer_resolves_native_app_not_repository_root(): void {
		$installer = file_get_contents(dirname(__DIR__, 2).'/tragofone/resources/install/install.sh');
		self::assertStringContainsString('/../.." && pwd)', $installer);
		self::assertStringNotContainsString('/../../.." && pwd)', $installer);
	}

	public function test_public_voicemail_pages_use_opaque_message_handles(): void {
		$app = dirname(__DIR__, 2).'/tragofone/selfcare';
		$voicemail = file_get_contents($app.'/voicemail.php');
		self::assertStringContainsString('voicemail_message_handle', $voicemail);
		self::assertStringNotContainsString('rawurlencode($uuid)', $voicemail);
		foreach (['media.php', 'confirm-delete.php', 'action.php'] as $page) {
			self::assertStringContainsString('voicemail_message_from_handle', file_get_contents($app.'/'.$page), $page);
		}
	}

	public function test_global_selfcare_controls_warn_and_support_salt_rotation(): void {
		$page = file_get_contents(dirname(__DIR__, 2).'/tragofone/global_settings.php');
		self::assertStringContainsString('Rotate Self-Care Salts', $page);
		self::assertStringContainsString('Saving may update the Account URL for every synchronized Tragofone user', $page);
		self::assertStringContainsString('selfcare.salts.rotate', $page);
	}

	public function test_selfcare_policy_is_editable_at_global_domain_and_user_levels(): void {
		$root=dirname(__DIR__,2).'/tragofone';
		foreach(['global_settings.php','tenant_settings.php','extension_sync.php'] as $page){$contents=file_get_contents($root.'/'.$page);self::assertStringContainsString('selfcare_policy',$contents,$page);self::assertStringContainsString("'inherit'=>'Inherit'",$contents,$page);self::assertStringContainsString("'yes'=>'Yes'",$contents,$page);self::assertStringContainsString("'no'=>'No'",$contents,$page);}
	}

	public function test_pages_use_declared_permissions_and_portable_access_denied_response(): void {
		$root = dirname(__DIR__, 2);
		$manifest = file_get_contents($root.'/tragofone/app_config.php');
		$pages = ['index.php', 'global_settings.php', 'tenant_settings.php', 'extension_sync.php', 'qr_code.php', 'mappings.php', 'jobs.php', 'reconciliation.php'];

		foreach ($pages as $page) {
			$contents = file_get_contents($root.'/tragofone/'.$page);
			self::assertStringNotContainsString('access_denied(', $contents, $page);
			self::assertStringNotContainsString('token_field(', $contents, $page);
			self::assertStringNotContainsString('check_token(', $contents, $page);
			preg_match_all("/permission_exists\\('([^']+)'\\)/", $contents, $matches);
			foreach ($matches[1] as $permission) {
				self::assertStringContainsString("'{$permission}'", $manifest, "{$page}: {$permission}");
			}
		}
	}

	public function test_qr_page_is_domain_scoped_csrf_protected_and_never_persists_qr_data(): void {
		$page = file_get_contents(dirname(__DIR__, 2).'/tragofone/qr_code.php');
		self::assertStringContainsString('m.domain_uuid=:domain_uuid', $page);
		self::assertStringContainsString('e.enabled,p.sync_enabled', $page);
		self::assertStringContainsString("validate(\$_SERVER['PHP_SELF'])", $page);
		self::assertStringContainsString("Cache-Control: no-store", $page);
		self::assertStringContainsString("method = 'direct'", $page);
		self::assertStringNotContainsString('v_email_queue_attachments', $page);
	}
}
