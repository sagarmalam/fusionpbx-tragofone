<?php

final class tragofone_qr_code {
	private const MAX_BYTES = 2097152;

	/** @return array{bytes:string,mime_type:string,extension:string,base64:string} */
	public static function from_response(array $response, ?Closure $payload_renderer = null): array {
		foreach (self::strings($response) as $candidate) {
			$image = self::decode($candidate);
			if ($image !== null) { return $image; }
		}
		$payload = $response['data']['qr_code'] ?? null;
		if (is_string($payload) && $payload !== '' && strlen($payload) <= 4096 && !preg_match('/[\x00-\x1F\x7F]/', $payload)) {
			$bytes = $payload_renderer !== null ? $payload_renderer($payload) : self::render_payload($payload);
			$image = self::decode($bytes);
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
		$image = self::image($candidate);
		if ($image !== null) { return $image; }
		if (preg_match('#^data:image/(png|jpe?g|webp);base64,(.+)$#is', $candidate, $matches)) {
			$candidate = $matches[2];
		} elseif (preg_match('#^base64,(.+)$#is', $candidate, $matches)) {
			$candidate = $matches[1];
		}
		$candidate = preg_replace('/\s+/', '', $candidate) ?? '';
		$bytes = base64_decode($candidate, true);
		if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) { return null; }
		return self::image($bytes);
	}

	/** @return array{bytes:string,mime_type:string,extension:string,base64:string}|null */
	private static function image(string $bytes): ?array {
		if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) { return null; }
		[$mime_type, $extension] = self::image_type($bytes);
		if ($mime_type === null) { return null; }
		$image_info = @getimagesizefromstring($bytes);
		if (!is_array($image_info) || ($image_info['mime'] ?? null) !== $mime_type || (int) $image_info[0] > 4096 || (int) $image_info[1] > 4096) { return null; }
		return ['bytes'=>$bytes, 'mime_type'=>$mime_type, 'extension'=>$extension, 'base64'=>base64_encode($bytes)];
	}

	private static function render_payload(string $payload): string {
		if (!extension_loaded('gd')) { throw new RuntimeException('PHP GD is required to render the Tragofone QR payload.'); }
		$qr_directory = dirname(__DIR__, 4).'/resources/qr_code';
		if (!is_file($qr_directory.'/QRCode.php')) { throw new RuntimeException('FusionPBX QR rendering support is unavailable.'); }
		$previous_include_path = get_include_path();
		set_include_path($qr_directory.PATH_SEPARATOR.$previous_include_path);
		try {
			require_once $qr_directory.'/QRCode.php';
			$code = new QRCode(0, QRErrorCorrectLevel::M);
			$code->addData($payload); $code->make();
			$modules = (int) $code->getModuleCount(); $quiet = 4; $scale = max(1, intdiv(512, $modules + ($quiet * 2)));
			$size = ($modules + ($quiet * 2)) * $scale; $canvas = imagecreatetruecolor($size, $size);
			if ($canvas === false) { throw new RuntimeException('Unable to allocate QR image.'); }
			$white = imagecolorallocate($canvas, 255, 255, 255); $black = imagecolorallocate($canvas, 0, 0, 0);
			if ($white === false || $black === false) { imagedestroy($canvas); throw new RuntimeException('Unable to allocate QR colors.'); }
			imagefill($canvas, 0, 0, $white);
			for ($row = 0; $row < $modules; $row++) {
				for ($column = 0; $column < $modules; $column++) {
					if (!$code->isDark($row, $column)) { continue; }
					$x = ($column + $quiet) * $scale; $y = ($row + $quiet) * $scale;
					imagefilledrectangle($canvas, $x, $y, $x + $scale - 1, $y + $scale - 1, $black);
				}
			}
			ob_start(); $written = imagepng($canvas, null, 6); $bytes = ob_get_clean(); imagedestroy($canvas);
			if (!$written || !is_string($bytes) || $bytes === '') { throw new RuntimeException('Unable to encode QR image.'); }
			return $bytes;
		} finally {
			set_include_path($previous_include_path);
		}
	}

	/** @return array{0:?string,1:string} */
	private static function image_type(string $bytes): array {
		if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) { return ['image/png', 'png']; }
		if (str_starts_with($bytes, "\xff\xd8\xff")) { return ['image/jpeg', 'jpg']; }
		if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') { return ['image/webp', 'webp']; }
		return [null, ''];
	}
}
