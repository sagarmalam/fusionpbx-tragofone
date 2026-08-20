<?php

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/tragofone/resources/classes/tragofone_menu_repair.php';

final class menu_repair_database extends database {
	public array $deleted = [];

	public function select($sql, $parameters = null, $return_type = 'all'): mixed {
		if (str_contains($sql, 'from v_menu_items')) {
			return [['menu_item_uuid'=>'00000000-0000-4000-8000-000000000001']];
		}
		if (str_contains($sql, 'from v_menu_languages')) {
			return [
				['menu_language_uuid'=>'00000000-0000-4000-8000-000000000010', 'menu_language'=>'en-us'],
				['menu_language_uuid'=>'00000000-0000-4000-8000-000000000011', 'menu_language'=>'en-us'],
				['menu_language_uuid'=>'00000000-0000-4000-8000-000000000012', 'menu_language'=>'en-gb'],
			];
		}
		if (str_contains($sql, 'from v_menu_item_groups')) {
			return [
				['menu_item_group_uuid'=>'00000000-0000-4000-8000-000000000020', 'group_name'=>'superadmin'],
				['menu_item_group_uuid'=>'00000000-0000-4000-8000-000000000021', 'group_name'=>'superadmin'],
				['menu_item_group_uuid'=>'00000000-0000-4000-8000-000000000022', 'group_name'=>'admin'],
			];
		}
		return [];
	}

	public function execute($sql, $parameters = null, $return_type = 'all'): mixed {
		$this->deleted[] = [$sql, $parameters];
		return true;
	}
}

final class MenuRepairTest extends TestCase {
	public function test_duplicate_language_and_group_rows_are_removed_without_changing_distinct_assignments(): void {
		$database = new menu_repair_database();
		$result = tragofone_menu_repair::repair($database);

		self::assertSame(['languages'=>1, 'groups'=>1], $result);
		self::assertCount(2, $database->deleted);
		self::assertStringContainsString('v_menu_languages', $database->deleted[0][0]);
		self::assertSame('00000000-0000-4000-8000-000000000011', $database->deleted[0][1]['uuid']);
		self::assertStringContainsString('v_menu_item_groups', $database->deleted[1][0]);
		self::assertSame('00000000-0000-4000-8000-000000000021', $database->deleted[1][1]['uuid']);
	}

	public function test_application_defaults_runs_the_targeted_menu_repair(): void {
		$defaults = file_get_contents(dirname(__DIR__, 2).'/tragofone/app_defaults.php');
		self::assertStringContainsString("tragofone_menu_repair::repair(\$database)", $defaults);
		self::assertStringContainsString("where uuid=:uuid", file_get_contents(dirname(__DIR__, 2).'/tragofone/resources/classes/tragofone_menu_repair.php'));
	}
}
