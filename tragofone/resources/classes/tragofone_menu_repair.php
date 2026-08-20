<?php

final class tragofone_menu_repair {
	private const SOURCE_UUID = '1b9e9c69-7d33-4d44-99ae-ccecb9e5d002';

	/**
	 * Remove duplicate child records that make FusionPBX render one app menu item
	 * more than once. The source UUID is stable, while menu_item_uuid is generated
	 * independently by each FusionPBX installation.
	 *
	 * @return array{languages:int,groups:int}
	 */
	public static function repair(object $database): array {
		$deleted = ['languages'=>0, 'groups'=>0];
		$items = $database->select(
			'select menu_item_uuid from v_menu_items where uuid=:uuid',
			['uuid'=>self::SOURCE_UUID],
			'all'
		) ?: [];

		foreach ($items as $item) {
			$menu_item_uuid = (string) ($item['menu_item_uuid'] ?? '');
			if ($menu_item_uuid === '') { continue; }

			$languages = $database->select(
				'select menu_language_uuid,menu_language from v_menu_languages where menu_item_uuid=:menu_item_uuid order by menu_language_uuid',
				['menu_item_uuid'=>$menu_item_uuid],
				'all'
			) ?: [];
			$deleted['languages'] += self::delete_duplicates(
				$database,
				$languages,
				'menu_language',
				'menu_language_uuid',
				'delete from v_menu_languages where menu_language_uuid=:uuid'
			);

			$groups = $database->select(
				'select menu_item_group_uuid,group_name from v_menu_item_groups where menu_item_uuid=:menu_item_uuid order by menu_item_group_uuid',
				['menu_item_uuid'=>$menu_item_uuid],
				'all'
			) ?: [];
			$deleted['groups'] += self::delete_duplicates(
				$database,
				$groups,
				'group_name',
				'menu_item_group_uuid',
				'delete from v_menu_item_groups where menu_item_group_uuid=:uuid'
			);
		}

		return $deleted;
	}

	private static function delete_duplicates(object $database, array $rows, string $key_field, string $uuid_field, string $delete_sql): int {
		$seen = [];
		$deleted = 0;
		foreach ($rows as $row) {
			$key = strtolower(trim((string) ($row[$key_field] ?? '')));
			$uuid = (string) ($row[$uuid_field] ?? '');
			if ($key === '' || $uuid === '') { continue; }
			if (!isset($seen[$key])) {
				$seen[$key] = true;
				continue;
			}
			if ($database->execute($delete_sql, ['uuid'=>$uuid]) === false) {
				throw new RuntimeException('Unable to repair duplicate Tragofone menu metadata.');
			}
			$deleted++;
		}
		return $deleted;
	}
}
