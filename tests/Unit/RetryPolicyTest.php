<?php
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase {
	public function test_uses_the_documented_retry_schedule(): void {
		self::assertSame([60, 300, 900, 3600, 10800, 21600, null], array_map(
			static fn (int $attempt): ?int => tragofone_retry_policy::delay($attempt),
			range(1, 7)
		));
	}

	public function test_retries_transient_api_and_transport_failures_only(): void {
		self::assertTrue(tragofone_retry_policy::retryable(new tragofone_api_exception('busy', 503, true)));
		self::assertTrue(tragofone_retry_policy::retryable(new RuntimeException('transport failed')));
		self::assertFalse(tragofone_retry_policy::retryable(new tragofone_api_exception('bad request', 400, false)));
		self::assertFalse(tragofone_retry_policy::retryable(new InvalidArgumentException('invalid payload')));
	}
}
