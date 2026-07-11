<?php

final class tragofone_crypto {
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
}
