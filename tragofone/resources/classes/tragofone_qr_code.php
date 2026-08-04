<?php

final class tragofone_qr_code {
	private const MAX_BYTES = 2097152;

	/** @return array{bytes:string,mime_type:string,extension:string,base64:string} */
	public static function from_response(array $response): array {
		foreach (self::strings($response) as $candidate) {
			$image = self::decode($candidate);
			if ($image !== null) { return $image; }
		}
		throw new RuntimeException('Tragofone QR response did not contain a supported PNG, JPEG, or WebP image.');
	}

	/** @return list<string> */
	private static function strings(mixed $value): array {
		if (is_string($value)) { return [$value]; }
		if (!is_array($value)) { return []; }
		$candidates = [];
		foreach ($value as $child) { array_push($candidates, ...self::strings($child)); }
		return $candidates;
	}

	/** @return array{bytes:string,mime_type:string,extension:string,base64:string}|null */
	private static function decode(string $candidate): ?array {
		$candidate = trim($candidate);
		if ($candidate === '') { return null; }
		if (preg_match('#^data:image/(png|jpe?g|webp);base64,(.+)$#is', $candidate, $matches)) {
			$candidate = $matches[2];
		} elseif (preg_match('#^base64,(.+)$#is', $candidate, $matches)) {
			$candidate = $matches[1];
		}
		$candidate = preg_replace('/\s+/', '', $candidate) ?? '';
		$bytes = base64_decode($candidate, true);
		if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) { return null; }
		[$mime_type, $extension] = self::image_type($bytes);
		if ($mime_type === null) { return null; }
		$image_info = @getimagesizefromstring($bytes);
		if (!is_array($image_info) || ($image_info['mime'] ?? null) !== $mime_type || (int) $image_info[0] > 4096 || (int) $image_info[1] > 4096) { return null; }
		return ['bytes'=>$bytes, 'mime_type'=>$mime_type, 'extension'=>$extension, 'base64'=>base64_encode($bytes)];
	}

	/** @return array{0:?string,1:string} */
	private static function image_type(string $bytes): array {
		if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) { return ['image/png', 'png']; }
		if (str_starts_with($bytes, "\xff\xd8\xff")) { return ['image/jpeg', 'jpg']; }
		if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') { return ['image/webp', 'webp']; }
		return [null, ''];
	}
}
