<?php

final class tragofone_crypto {
	public static function from_environment(string $environment_file = '/etc/fusionpbx/tragofone.env'): self {
		$key_material = getenv('TRAGOFONE_ENCRYPTION_KEY');
		if (($key_material === false || $key_material === '') && is_readable($environment_file)) {
			foreach (file($environment_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
				if (!str_starts_with($line, 'TRAGOFONE_ENCRYPTION_KEY=')) { continue; }
				$key_material = trim(substr($line, strlen('TRAGOFONE_ENCRYPTION_KEY=')), " \t\n\r\0\x0B\"'");
				break;
			}
		}
		if (!is_string($key_material) || $key_material === '') {
			throw new RuntimeException('TRAGOFONE_ENCRYPTION_KEY is not configured or readable.');
		}
		return new self($key_material);
	}

	public function __construct(private readonly string $key_material) {
		if (strlen($key_material) < 32) { throw new InvalidArgumentException('Encryption key material must contain at least 32 characters.'); }
	}

	public function encrypt(string $plaintext): string {
		$key = hash('sha256', $this->key_material, true);
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		return base64_encode($nonce.sodium_crypto_secretbox($plaintext, $nonce, $key));
	}

	public function decrypt(string $ciphertext): string {
		$decoded = base64_decode($ciphertext, true);
		if ($decoded === false || strlen($decoded) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
			throw new RuntimeException('Invalid encrypted credential.');
		}
		$key = hash('sha256', $this->key_material, true);
		$nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
		$value = sodium_crypto_secretbox_open(substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $key);
		if ($value === false) { throw new RuntimeException('Unable to decrypt credential.'); }
		return $value;
	}

	public function fingerprint(string $context, string $value): string {
		return hash_hmac('sha256', $context."\0".$value, hash('sha256', $this->key_material, true));
	}
}
