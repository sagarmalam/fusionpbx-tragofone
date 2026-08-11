<?php
use PHPUnit\Framework\TestCase;

final class QrCodeTest extends TestCase {
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

	public function test_extracts_nested_data_uri_without_changing_image_bytes(): void {
		$qr = tragofone_qr_code::from_response(['status'=>'SUCCESS','data'=>['qr_code'=>'data:image/png;base64,'.self::PNG]]);
		self::assertSame('image/png', $qr['mime_type']);
		self::assertSame('png', $qr['extension']);
		self::assertSame(self::PNG, $qr['base64']);
	}

	public function test_accepts_plain_base64_in_a_generic_success_envelope(): void {
		$qr = tragofone_qr_code::from_response(['data'=>['image'=>self::PNG]]);
		self::assertStringStartsWith("\x89PNG", $qr['bytes']);
	}

	public function test_renders_a_plain_tragofone_qr_payload(): void {
		$seen = null;
		$qr = tragofone_qr_code::from_response(
			['status'=>'SUCCESS','data'=>['qr_code'=>'short-enrollment-payload']],
			static function (string $payload) use (&$seen): string { $seen = $payload; return base64_decode(self::PNG, true); }
		);
		self::assertSame('short-enrollment-payload', $seen);
		self::assertSame('image/png', $qr['mime_type']);
	}

	public function test_loads_fusionpbx_error_correction_level_before_qr_code(): void {
		$directory = sys_get_temp_dir().'/tragofone-qr-'.bin2hex(random_bytes(8));
		self::assertTrue(mkdir($directory));
		try {
			file_put_contents($directory.'/QRErrorCorrectLevel.php', "<?php\nclass QRErrorCorrectLevel { public const M = 0; }\n");
			file_put_contents($directory.'/QRCode.php', "<?php\nif (!class_exists('QRErrorCorrectLevel', false)) { throw new RuntimeException('error correction level was not loaded first'); }\nclass QRCode {}\n");
			$loader = new ReflectionMethod(tragofone_qr_code::class, 'load_renderer');
			$loader->invoke(null, $directory);
			self::assertTrue(class_exists(QRErrorCorrectLevel::class, false));
			self::assertTrue(class_exists(QRCode::class, false));
		} finally {
			@unlink($directory.'/QRCode.php');
			@unlink($directory.'/QRErrorCorrectLevel.php');
			@rmdir($directory);
		}
	}

	/** @dataProvider invalid_responses */
	public function test_rejects_non_images_and_spoofed_image_signatures(array $response): void {
		$this->expectException(RuntimeException::class);
		tragofone_qr_code::from_response($response);
	}

	public static function invalid_responses(): array {
		return [
			[['status'=>'SUCCESS','data'=>['message'=>base64_encode('not an image')]]],
			[['data'=>base64_encode("\x89PNG\r\n\x1a\nnot-a-real-png")]],
		];
	}
}
