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
			['status' => 200, 'headers' => [], 'body' => '{"access_token":"abc"}'],
			['status' => 200, 'headers' => [], 'body' => '{"data":{"usr_id":9}}'],
			['status' => 200, 'headers' => [], 'body' => '{"status":"SUCCESS"}'],
		];
		$client = new tragofone_client('https://trago.test', $transport); $client->customer_login('company', 'password');
		$client->create_user(['usr_username' => '1001@company.test']); $client->update_configuration(9, ['IM' => ['im_status' => 'FALSE']]);
		self::assertSame('/api/customer/user/create', parse_url($transport->requests[1]['url'], PHP_URL_PATH));
		self::assertSame('/api/customer/user/update-configurations', parse_url($transport->requests[2]['url'], PHP_URL_PATH));
		$configuration_request = json_decode($transport->requests[2]['body'], true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('FALSE', $configuration_request['configurations']['im_status']);
		self::assertArrayNotHasKey('IM', $configuration_request['configurations']);
		foreach ($transport->requests as $request) { self::assertStringNotContainsString('create-or-update-with-config', $request['url']); }
	}

	public function test_accepts_nested_legacy_token_shape(): void {
		$transport = new fake_transport();
		$transport->responses = [
			['status' => 200, 'headers' => [], 'body' => '{"data":{"token":"legacy"}}'],
			['status' => 200, 'headers' => [], 'body' => '{"data":{"cust_id":1}}'],
		];
		$client = new tragofone_client('https://trago.test', $transport);
		$client->customer_login('company', 'password');
		$client->customer_me();
		self::assertContains('Authorization: Bearer legacy', $transport->requests[1]['headers']);
	}

	public function test_uses_customer_enterprise_directory_endpoints(): void {
		$transport = new fake_transport();
		$transport->responses = [
			['status' => 200, 'headers' => [], 'body' => '{"access_token":"abc"}'],
			['status' => 200, 'headers' => [], 'body' => '{"data":{"ed_id":7}}'],
			['status' => 200, 'headers' => [], 'body' => '{"status":"SUCCESS"}'],
			['status' => 200, 'headers' => [], 'body' => '{"status":"SUCCESS"}'],
		];
		$client = new tragofone_client('https://trago.test', $transport);
		$client->customer_login('company', 'password');
		$client->create_contact(['ed_first_name' => 'Ada']);
		$client->update_contact(['ed_id' => '7', 'ed_first_name' => 'Grace']);
		$client->delete_contact(7);
		self::assertSame('/api/customer/enterprise/create', parse_url($transport->requests[1]['url'], PHP_URL_PATH));
		self::assertSame('/api/customer/enterprise/update', parse_url($transport->requests[2]['url'], PHP_URL_PATH));
		self::assertSame('/api/customer/enterprise/delete', parse_url($transport->requests[3]['url'], PHP_URL_PATH));
		self::assertSame('DELETE', $transport->requests[3]['method']);
	}

	public function test_reauthenticates_once_after_authenticated_401(): void {
		$transport = new fake_transport();
		$transport->responses = [
			['status' => 200, 'headers' => [], 'body' => '{"access_token":"expired"}'],
			['status' => 401, 'headers' => [], 'body' => '{"message":"expired token"}'],
			['status' => 200, 'headers' => [], 'body' => '{"access_token":"fresh"}'],
			['status' => 200, 'headers' => [], 'body' => '{"data":[]}'],
		];
		$client = new tragofone_client('https://trago.test', $transport);
		$client->customer_login('company', 'password');
		self::assertSame(['data' => []], $client->list_users());
		self::assertCount(4, $transport->requests);
		self::assertContains('Authorization: Bearer expired', $transport->requests[1]['headers']);
		self::assertSame('/api/customer/login', parse_url($transport->requests[2]['url'], PHP_URL_PATH));
		self::assertContains('Authorization: Bearer fresh', $transport->requests[3]['headers']);
	}

	public function test_does_not_loop_when_reauthenticated_request_is_still_unauthorized(): void {
		$transport = new fake_transport();
		$transport->responses = [
			['status' => 200, 'headers' => [], 'body' => '{"access_token":"expired"}'],
			['status' => 401, 'headers' => [], 'body' => '{"message":"expired token"}'],
			['status' => 200, 'headers' => [], 'body' => '{"access_token":"fresh"}'],
			['status' => 401, 'headers' => [], 'body' => '{"message":"not authorized"}'],
		];
		$client = new tragofone_client('https://trago.test', $transport);
		$client->customer_login('company', 'password');
		try {
			$client->list_users();
			self::fail('Expected an API exception.');
		} catch (tragofone_api_exception $error) {
			self::assertSame(401, $error->http_status);
			self::assertFalse($error->retryable);
		}
		self::assertCount(4, $transport->requests);
	}
}
