<?php
use PHPUnit\Framework\TestCase;

final class MaintenanceScriptTest extends TestCase {
	private string $script;

	protected function setUp(): void {
		$this->script = dirname(__DIR__, 2).'/tragofone/resources/install/manage.sh';
	}

	public function test_manager_is_executable_and_documents_routine_operations(): void {
		self::assertFileExists($this->script);
		self::assertTrue(is_executable($this->script));

		[$exit_code, $output] = $this->executeManager(['help']);
		self::assertSame(0, $exit_code, $output);
		foreach (['install', 'upgrade', 'repair', 'backup', 'status', 'doctor', 'worker', 'reconcile', 'timers', 'logs'] as $command) {
			self::assertStringContainsString($command, $output);
		}
		self::assertStringContainsString('--skip-database-backup', $output);
		self::assertStringContainsString('--allow-processing', $output);
	}

	public function test_status_is_read_only_for_an_uninstalled_target(): void {
		$missing_root = sys_get_temp_dir().'/tragofone-manager-missing-'.bin2hex(random_bytes(6));
		self::assertDirectoryDoesNotExist($missing_root);

		[$exit_code, $output] = $this->executeManager([
			'status',
			'--fusionpbx-root', $missing_root,
			'--php-bin', PHP_BINARY,
		]);

		self::assertSame(0, $exit_code, $output);
		self::assertStringContainsString('Installed version', $output);
		self::assertStringContainsString('not-installed', $output);
		self::assertDirectoryDoesNotExist($missing_root);
	}

	public function test_unknown_commands_and_unsafe_roots_fail_closed(): void {
		[$unknown_exit, $unknown_output] = $this->executeManager(['unknown-command']);
		self::assertNotSame(0, $unknown_exit);
		self::assertStringContainsString('Unknown command', $unknown_output);

		[$root_exit, $root_output] = $this->executeManager(['status', '--fusionpbx-root', '/']);
		self::assertNotSame(0, $root_exit);
		self::assertStringContainsString('non-root absolute path', $root_output);

		[$value_exit, $value_output] = $this->executeManager(['status', '--php-bin']);
		self::assertNotSame(0, $value_exit);
		self::assertStringContainsString('requires a value', $value_output);
	}

	public function test_installer_can_repair_from_the_installed_application_tree(): void {
		$installer = file_get_contents(dirname(__DIR__, 2).'/tragofone/resources/install/install.sh');
		$manager = file_get_contents($this->script);
		self::assertStringContainsString('readlink -f "${SOURCE_DIR}"', $installer);
		self::assertStringContainsString('readlink -f "${TARGET}"', $installer);
		self::assertStringContainsString('cp -a "${SOURCE_DIR}/." "${TARGET}/"', $installer);
		self::assertStringContainsString('version_compare($argv[1], $argv[2], "<")', $manager);
		self::assertStringContainsString('schema migrations are forward-only', $manager);
	}

	/** @return array{0:int,1:string} */
	private function executeManager(array $arguments): array {
		$command = array_merge(['bash', $this->script], $arguments);
		$descriptors = [
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$process = proc_open($command, $descriptors, $pipes);
		self::assertIsResource($process);
		$output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		return [proc_close($process), $output];
	}
}
