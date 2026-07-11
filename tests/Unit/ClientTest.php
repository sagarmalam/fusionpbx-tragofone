<?php
use PHPUnit\Framework\TestCase;

final class fake_transport implements tragofone_http_transport {
	public array $requests = []; public array $responses = [];
	public function request(string $method, string $url, array $headers = [], ?string $body = null): array {
		$this->requests[] = compact('method', 'url', 'headers', 'body'); return array_shift($this->responses);
	}
}
final class ClientTest extends TestCase {
	public function test_uses_existing_separate_crud_and_configuration_endpoints(): void {
		$transport = new fake_transport();
		$transport->responses = [
			['status' => 200, 'headers' => [], 'body' => '{"token":"abc"}'],
			['status' => 200, 'headers' => [], 'body' => '{"data":{"usr_id":9}}'],
			['status' => 200, 'headers' => [], 'body' => '{"status":"SUCCESS"}'],
		];
		$client = new tragofone_client('https://trago.test', $transport); $client->customer_login('company', 'password');
		$client->create_user(['usr_username' => '1001@company.test']); $client->update_configuration(9, ['IM' => ['im_status' => 'FALSE']]);
		self::assertSame('/api/customer/user/create', parse_url($transport->requests[1]['url'], PHP_URL_PATH));
		self::assertSame('/api/customer/user/update-configurations', parse_url($transport->requests[2]['url'], PHP_URL_PATH));
		foreach ($transport->requests as $request) { self::assertStringNotContainsString('create-or-update-with-config', $request['url']); }
	}
}
