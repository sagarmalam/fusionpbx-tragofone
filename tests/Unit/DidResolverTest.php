<?php
use PHPUnit\Framework\TestCase;

final class DidResolverTest extends TestCase {
	public function test_resolves_all_direct_dids_and_prefers_effective_caller_id(): void {
		$rows = [
			['destination_enabled' => true, 'destination_type_voice' => true, 'destination_type' => 'inbound', 'destination_number' => '+1 415 555 0101', 'destination_actions' => json_encode([['destination_app' => 'transfer', 'destination_data' => '1001 XML company.test']])],
			['destination_enabled' => true, 'destination_type_voice' => true, 'destination_type' => 'inbound', 'destination_number' => '+14155550100', 'destination_actions' => json_encode([['destination_app' => 'transfer', 'destination_data' => '1001 XML company.test']])],
		];
		self::assertSame(['+14155550101', '+14155550100'], tragofone_did_resolver::direct_dids('1001', $rows, '+14155550101'));
	}
	public function test_ignores_ambiguous_and_non_extension_routes(): void {
		$rows = [
			['destination_enabled' => true, 'destination_type_voice' => true, 'destination_type' => 'inbound', 'destination_number' => '1800', 'destination_actions' => json_encode([['destination_app' => 'transfer', 'destination_data' => '1001 XML x'], ['destination_app' => 'record', 'destination_data' => 'x']])],
			['destination_enabled' => true, 'destination_type_voice' => true, 'destination_type' => 'inbound', 'destination_number' => '1801', 'destination_actions' => json_encode([['destination_app' => 'ivr', 'destination_data' => 'menu']])],
		];
		self::assertSame([], tragofone_did_resolver::direct_dids('1001', $rows));
	}
}
