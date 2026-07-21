<?php

final class tragofone_api_exception extends RuntimeException {
	public function __construct(string $message, public readonly int $http_status = 0, public readonly bool $retryable = false) {
		parent::__construct($message, $http_status);
	}
}

final class tragofone_client {
	private ?string $customer_token = null;

	public function __construct(private readonly string $base_url, private readonly tragofone_http_transport $transport) {}

	public function customer_login(string $username, string $password): array {
		$response = $this->json('POST', '/api/customer/login', ['username' => $username, 'password' => $password, 'device_type' => 'web']);
		$this->customer_token = $response['access_token']
			?? $response['token']
			?? $response['data']['access_token']
			?? $response['data']['token']
			?? null;
		if ($this->customer_token === null) { throw new tragofone_api_exception('Customer login did not return a token.'); }
		return $response;
	}

	public function customer_me(): array { return $this->json('GET', '/api/customer/me', null, $this->require_token($this->customer_token)); }
	public function list_users(array $filter = []): array { return $this->json('POST', '/api/customer/user/list', $filter, $this->require_token($this->customer_token)); }
	public function create_user(array $user): array { return $this->json('POST', '/api/customer/user/create', $user, $this->require_token($this->customer_token)); }
	public function update_user(array $user): array { return $this->json('POST', '/api/customer/user/update', $user, $this->require_token($this->customer_token)); }
	public function delete_user(int $user_id): array { return $this->json('POST', '/api/customer/user/delete', ['user_id' => $user_id], $this->require_token($this->customer_token)); }
	public function update_configuration(int $user_id, array $configuration): array {
		return $this->json('POST', '/api/customer/user/update-configurations', ['user_id' => $user_id, 'configurations' => $this->flatten_configuration($configuration)], $this->require_token($this->customer_token));
	}
	public function get_configuration(int $user_id): array { return $this->json('POST', '/api/customer/user/get-configurations', ['user_id' => $user_id], $this->require_token($this->customer_token)); }
	public function get_qr_code(int $user_id): array { return $this->json('POST', '/api/customer/user/get-qr-code', ['user_id' => $user_id], $this->require_token($this->customer_token)); }

	public function list_contacts(array $filter = []): array { return $this->json('POST', '/api/customer/enterprise/list', $filter, $this->require_token($this->customer_token)); }
	public function create_contact(array $contact): array { return $this->json('POST', '/api/customer/enterprise/create', $contact, $this->require_token($this->customer_token)); }
	public function update_contact(array $contact): array { return $this->json('POST', '/api/customer/enterprise/update', $contact, $this->require_token($this->customer_token)); }
	public function delete_contact(int $ed_id): array { return $this->json('DELETE', '/api/customer/enterprise/delete', ['ed_id' => $ed_id], $this->require_token($this->customer_token)); }

	private function require_token(?string $token): string {
		if ($token === null) { throw new LogicException('Authentication is required before this API call.'); }
		return $token;
	}

	private function flatten_configuration(array $configuration): array {
		$flat = [];
		foreach ($configuration as $key => $value) {
			if (!is_array($value)) { $flat[$key] = $value; continue; }
			foreach ($value as $configuration_key => $configuration_value) {
				if (is_array($configuration_value)) { throw new InvalidArgumentException('Tragofone configuration values must be scalar.'); }
				$flat[$configuration_key] = $configuration_value;
			}
		}
		return $flat;
	}

	private function json(string $method, string $path, ?array $payload = null, ?string $token = null): array {
		$headers = ['Accept: application/json', 'Content-Type: application/json'];
		if ($token !== null) { $headers[] = 'Authorization: Bearer '.$token; }
		$body = $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR);
		$response = $this->transport->request($method, $this->base_url.$path, $headers, $body);
		$decoded = json_decode($response['body'], true);
		if ($response['status'] < 200 || $response['status'] >= 300) {
			$message = is_array($decoded) ? ($decoded['message'] ?? 'Tragofone API error.') : 'Tragofone API returned invalid JSON.';
			$retryable = in_array($response['status'], [408, 425, 429], true) || $response['status'] >= 500;
			throw new tragofone_api_exception(tragofone_redactor::message((string) $message), $response['status'], $retryable);
		}
		if (!is_array($decoded)) { throw new tragofone_api_exception('Tragofone API returned invalid JSON.', $response['status']); }
		return $decoded;
	}
}
