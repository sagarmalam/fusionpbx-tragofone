<?php

interface tragofone_http_transport {
	/** @return array{status:int,headers:array<string,string>,body:string} */
	public function request(string $method, string $url, array $headers = [], ?string $body = null): array;
}

final class tragofone_curl_transport implements tragofone_http_transport {
	public function __construct(
		private readonly int $connect_timeout = 10,
		private readonly int $request_timeout = 30,
		private readonly bool $verify_tls = true,
	) {}

	public function request(string $method, string $url, array $headers = [], ?string $body = null): array {
		$handle = curl_init($url);
		if ($handle === false) {
			throw new RuntimeException('Unable to initialize HTTP transport.');
		}
		$response_headers = [];
		curl_setopt_array($handle, [
			CURLOPT_CUSTOMREQUEST => strtoupper($method), CURLOPT_RETURNTRANSFER => true,
			CURLOPT_CONNECTTIMEOUT => $this->connect_timeout, CURLOPT_TIMEOUT => $this->request_timeout,
			CURLOPT_SSL_VERIFYPEER => $this->verify_tls, CURLOPT_SSL_VERIFYHOST => $this->verify_tls ? 2 : 0,
			CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$response_headers): int {
				$parts = explode(':', $line, 2);
				if (count($parts) === 2) { $response_headers[strtolower(trim($parts[0]))] = trim($parts[1]); }
				return strlen($line);
			},
		]);
		if ($body !== null) { curl_setopt($handle, CURLOPT_POSTFIELDS, $body); }
		$response_body = curl_exec($handle);
		if ($response_body === false) {
			$error = curl_error($handle); curl_close($handle);
			throw new RuntimeException('Tragofone request failed: '.$error);
		}
		$status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
		curl_close($handle);
		return ['status' => $status, 'headers' => $response_headers, 'body' => $response_body];
	}
}
