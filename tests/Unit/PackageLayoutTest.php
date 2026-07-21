<?php
use PHPUnit\Framework\TestCase;

final class PackageLayoutTest extends TestCase {
	public function test_native_app_contains_required_discovery_files(): void {
		$app = dirname(__DIR__, 2).'/tragofone';
		self::assertFileExists($app.'/app_config.php');
		self::assertFileExists($app.'/app_defaults.php');
		self::assertFileExists($app.'/app_menu.php');
		self::assertFileExists($app.'/index.php');
	}

	public function test_installer_resolves_native_app_not_repository_root(): void {
		$installer = file_get_contents(dirname(__DIR__, 2).'/tragofone/resources/install/install.sh');
		self::assertStringContainsString('/../.." && pwd)', $installer);
		self::assertStringNotContainsString('/../../.." && pwd)', $installer);
	}

	public function test_pages_use_declared_permissions_and_portable_access_denied_response(): void {
		$root = dirname(__DIR__, 2);
		$manifest = file_get_contents($root.'/tragofone/app_config.php');
		$pages = ['index.php', 'global_settings.php', 'tenant_settings.php', 'mappings.php', 'jobs.php', 'reconciliation.php'];

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
}
