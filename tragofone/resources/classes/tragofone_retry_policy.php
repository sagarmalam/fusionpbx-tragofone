<?php

final class tragofone_retry_policy {
	private const DELAYS = [60, 300, 900, 3600, 10800, 21600];
	public static function delay(int $attempt): ?int { return self::DELAYS[$attempt - 1] ?? null; }
	public static function retryable(Throwable $error): bool {
		return $error instanceof tragofone_api_exception ? $error->retryable : $error instanceof RuntimeException;
	}
}
