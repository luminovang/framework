<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utility;

use \JsonException;
use Luminova\Exceptions\InvalidArgumentException;

final class Encoder
{
    /**
	 * Base32 RFC4648 alphanumeric format (A-Z2-7).
	 * 
	 * @var string BASE32_RFC4648
	 */
	public const BASE32_RFC4648 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	/**
	 * Base32 crockford alphanumeric format (0-9A-Z) excluding (I-L-O-U).
	 * 
	 * @var string BASE32_CROCKFORD
	 */
	public const BASE32_CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

	/**
	 * Base32 PHP session ID alphanumeric format (0-9a-v).
	 * 
	 * 5 bits per character `session.sid_bits_per_character`
	 * @var string BASE32_SID_5BITS
	 */
	public const BASE32_SID_5BITS = '0123456789abcdefghijklmnopqrstuv';

	/**
	 * Compression handlers.
	 * 
	 * In preference order.
	 * 
	 * @var string[] COMPRESSION_HANDLERS
	 */
	private const COMPRESSION_HANDLERS = ['zstd', 'br', 'gzip', 'deflate', 'compress'];
    
    /**
     * Resolved encoding.
     * 
     * @var array<int,string> $encoding
    */
    private static array $encoding = [];

	/**
	 * Output compression enable.
	 *
	 * @var bool|null $obCompression
	 */
	private static ?bool $obCompression = null;

	/**
	 * Save last response compression details.
	 *
	 * @var bool $withLastCompression
	 */
	private static bool $withLastCompression = false;

	/**
	 * Last response compression details.
	 *
	 * @var array<string,mixed> $lastCompression
	 */
	private static array $lastCompression = [];

	/**
	 * Compress content using the specified encoding.
	 *
	 * If no encoding is provided, the best supported encoding is resolved
	 * automatically from the current request.
	 *
	 * Content smaller than `$minLength` bytes is returned without compression.
	 *
	 * @param string $content The content to compress.
	 * @param string|null $encoding  Optional compression encoding (e.g. `gzip`, `deflate`).
	 * @param int|null $minLength Minimum content size required for compression (default: `1024`).
	 *                               Use `null` or `0` to disable the size limit.
	 *
	 * @return array{0:?string,1:string,2:int}  Returns:
	 *         - `[encoding, compressed content, compressed length]` on success.
	 *         - `[null, original content, original length]` on failure.
	 */
	public static function compress(
		string $content,
		?string $encoding = null,
		?int $minLength = 1024
	): array 
	{
		$length = strlen($content);
		$minLength = (int) $minLength;

		if ($minLength > 0 && $length < $minLength) {
			return [null, $content, $length];
		}

		$encoding ??= self::resolveEncoding(forCompression: true);

		if ($encoding === null) {
			return [null, $content, $length];
		}

		$encoding = strtolower($encoding);
		$handler = self::resolveCompressionHandler($encoding);

		if ($handler === null) {
			return [null, $content, $length];
		}

		$level = self::resolveCompressionLevel($encoding);
		$compressed = $handler($content, $level);

		if ($compressed === false || $compressed === null) {
			return [null, $content, $length];
		}

		$compressedLength = strlen($compressed);

		if ($compressedLength >= $length) {
			return [null, $content, $length];
		}

		if(self::$withLastCompression){
			self::$lastCompression = [
				'encoding'  		=> $encoding,
				'handler'   		=> $handler,
				'contentLength' 	=> $length,
				'compressedLength' 	=> $compressedLength,
			];
		}

		return [$encoding, $compressed, $compressedLength];
	}

	/**
	 * Compress HTTP response content.
	 *
	 * Uses configured minimum compression length (`env(output.compression.min.length)`)
	 * and resolves best encoding handle based on request `HTTP_ACCEPT_ENCODING` and available handler.
	 *
	 * @param string $content Response content.
	 *
	 * @return array{0:?string,1:string,2:int}  Returns:
	 *         - `[encoding, compressed content, compressed length]` on success.
	 *         - `[null, original content, original length]` on failure.
	 */
	public static function compressResponse(string $content): array 
	{
		if (!self::isResponseCompressionEnabled()) {
			return [null, $content, strlen($content)];
		}

		self::$withLastCompression = true;
		
		return self::compress(
			$content,
			minLength: (int) env('output.compression.min.length', 1024)
		);
	}

	/**
	 * Get details of the last compression operation.
	 *
	 * Returns information about the encoding, handler, and content sizes
	 * from the most recent compression attempt.
	 *
	 * @return array{
	 *     encoding: ?string,
	 *     handler: ?string,
	 *     contentLength: ?int,
	 *     compressedLength: ?int
	 * }|null Return last response if any.
	 */
	public static function getLastResponseCompression(): ?array
	{
		return (self::$lastCompression === []) 
			? null 
			: self::$lastCompression;
	}

	/**
	 * Check whether output compression is available.
	 *
	 * The result is cached after the first evaluation to avoid repeated checks
	 * during the same request lifecycle.
	 *
	 * @return bool True if output compression can be applied.
	 */
	public static function isOutputCompressionEnabled(): bool
	{
		return self::$obCompression ??= self::canCompressResponse();
	}

	/**
	 * Check whether response compression is enabled.
	 *
	 * Verifies that response compression is enabled in configuration and that
	 * the current response environment supports compression (`env(output.compression.enable)`).
	 *
	 * @return bool True if response compression is enabled and available.
	 */
	public static function isResponseCompressionEnabled(): bool
	{
		if ((bool) env('output.compression.enable', false) === false) {
			return false;
		}

		return self::$obCompression ??= self::canCompressResponse();
	}

	/**
	 * Decompress content using the resolved content encoding.
	 * 
	 * If `$encoding` is `null`, the encoding is resolved automatically from the current request headers.
	 *
	 * @param string $content Compressed content.
	 * @param int $maxLength Maximum decompressed length. Use `0` for no limit.
	 * @param string|null $encoding The compression encoding to use (e.g. `gzip`, `deflate`, or `compress`). 
	 *
	 * @return array{0:?string,1:string} Returns:
	 *         - `[encoding, decompressed content]` on success.
	 *         - `[null, original content]` on failure.
	 */
	public static function decompress(string $content, int $maxLength = 0, ?string $encoding = null): array
	{
		$encoding ??= self::resolveEncoding(forCompression: false);

		if ($encoding === null) {
			return [null, $content];
		}

		$encoding = strtolower($encoding);
		$handler = self::resolveCompressionHandler($encoding, false);

		if ($handler === null) {
			return [null, $content];
		}

		$decompressed = $handler($content, $maxLength);

		if ($decompressed === false) {
			return [null, $content];
		}

		return [$encoding, $decompressed];
	}

    /**
	 * Determines whether the given string is Base64-encoded (standard, URL-safe, or MIME style).
	 *
	 * Supports both standard URL-safe, and MIME-safe Base64 strings (with newlines).
	 *
	 * @param string $data The input string to validate.
	 * @param bool $strict If true, base64_decode() will return false on invalid characters.
	 *
	 * @return bool Returns true if the string appears to be valid Base64; false otherwise.
	 */
    public static function isBase64Encoded(string $data, bool $strict = true): bool
    {
		$data = trim($data);

		if ($data === '' || strlen($data) % 4 !== 0) {
			return false;
		}

		$data = preg_replace('/[\r\n]+/', '', $data);

		if (!preg_match('/^[a-zA-Z0-9\/\+_\-]*={0,2}$/', $data)) {
			return false;
		}

		$data = strtr($data, '-_', '+/');

		return base64_encode(base64_decode($data, $strict) ?: '') === $data;
	}

    /**
	 * Encode binary data into Base32 string using a custom alphabet.
	 *
	 * Converts raw binary input into a Base32 representation using a
	 * 5-bit encoding scheme. The provided alphabet must contain exactly
	 * 32 characters.
	 *
	 * Notes:
	 * - No padding is applied to the output.
	 * - Encoding is deterministic based on the alphabet.
	 *
	 * @param string|null $input Raw binary input data or time-based random.
	 * @param string|null $alphabet Base32 alphabet (default: RFC4648).
	 *
	 * @return string Encoded Base32 string.
	 *
	 * @throws InvalidArgumentException If alphabet is not exactly 32 characters.
	 */
	public static function base32Encoder(?string $input = null, ?string $alphabet = null): string
	{
		$alphabet ??= self::BASE32_RFC4648;

		if (strlen($alphabet) !== 32) {
			throw new InvalidArgumentException(
				'Base32 alphabet must be exactly 32 characters.'
			);
		}

		$input ??= Random::bytes()['bytes'];
		$output = '';
		$buffer = 0;
		$bits = 0;

		$bytes = unpack('C*', $input);

		foreach ($bytes as $byte) {
			$buffer = ($buffer << 8) | $byte;
			$bits += 8;

			while ($bits >= 5) {
				$bits -= 5;
				$output .= $alphabet[($buffer >> $bits) & 0x1F];
			}
		}

		if ($bits > 0) {
			$output .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
		}

		return $output;
	}

    /**
	 * Encode binary data into Base32 string.
	 *
	 * Uses a 5-bit encoding scheme to convert raw bytes into a
	 * Base32 representation using the selected alphabet.
	 *
	 * Output characteristics:
	 * - URL-safe
	 * - Case-insensitive depending on alphabet
	 * - Compact binary-to-text encoding
	 *
	 * Supported alphabets:
	 * - `rfc4648`   Standard RFC 4648 Base32 alphabet
	 * - `crockford` Crockford Base32 (human-friendly, no ambiguous chars)
	 * - `sid5`      PHP session-style 5-bit Base32 variant
	 *
	 * @param string $input Raw binary input.
	 * @param string|null $type Alphabet type (default: `rfc4648`).
	 *
	 * @return string Return Base32 encoded string.
	 * @throws InvalidArgumentException If type is unknown.
	 */
	public static function base32Encode(string $input, ?string $type = null): string
	{
		return self::base32Encoder($input, self::getBase32Alphabet($type));
	}

    /**
	 * Encode a value into a URL-safe Base64 string.
	 *
	 * The value is JSON-encoded, Base64-encoded, and converted
	 * to a URL-safe format by replacing reserved characters.
	 *
	 * Designed for safely transporting structured data
	 * (arrays and scalar values) via URLs.
	 *
	 * @param mixed $value Value to encode (scalars or arrays).
	 *
	 * @return string|null Returns URL-safe Base64 encoded value or null If encoding fails.
	 */
	public static function base64UrlEncode(mixed $value): ?string
	{
		try{
			$json = json_encode(['data' => $value], JSON_THROW_ON_ERROR);

			return strtr(
				base64_encode($json),
				'+/=', '._-'
			);
		}catch(JsonException){
			return null;
		}
	}
	
	/**
	 * Decode a URL-safe Base64 encoded value.
	 *
	 * Reverses the URL-safe transformation, Base64-decodes
	 * the value, and restores the original data from JSON.
	 *
	 * @param string $value URL-safe Base64 encoded value.
	 *
	 * @return mixed|null Decoded value, or null if decoding fails or the payload is invalid.
	 */
	public static function base64UrlDecode(string $value): mixed
	{
		$decoded = base64_decode(
			strtr($value, '._-', '+/='),
			true
		);

		if ($decoded === false) {
			return null;
		}

		try{
			$json = json_decode(
				$decoded, 
				true, 
				512, 
				JSON_THROW_ON_ERROR
			) ?: [];

			return $json['data'] ?? null;
		}catch(JsonException){
			return null;
		}
	}

	/**
	 * Decode Base32 string into binary data.
	 *
	 * Accepts input encoded with the selected Base32 alphabet.
	 * 
	 * Supported alphabets:
	 * - `rfc4648`   Standard RFC 4648 Base32 alphabet
	 * - `crockford` Crockford Base32 (human-friendly, no ambiguous chars)
	 * - `sid5`      PHP session-style 5-bit Base32 variant
	 *
	 * @param string $input Base32 encoded string.
	 * @param string|null $type Alphabet type used during encoding (default: `rfc4648`).
	 *
	 * @return string Return raw binary output.
	 * @throws InvalidArgumentException If type is unknown.
	 */
	public static function base32Decode(string $input, ?string $type = null): string
	{
		return self::base32Decoder($input, self::getBase32Alphabet($type));
	}

	/**
	 * Decode a Base32 encoded string into raw binary data.
	 *
	 * Decodes a Base32 string using the provided alphabet. The input must
	 * strictly contain valid characters from the selected alphabet.
	 *
	 * Notes:
	 * - Input is not automatically normalized (case-sensitive unless alphabet supports it).
	 * - Invalid characters will trigger an exception.
	 *
	 * @param string $input Base32 encoded string.
	 * @param string|null $alphabet Base32 alphabet used for decoding (default: RFC4648).
	 *
	 * @return string Decoded raw binary data.
	 *
	 * @throws InvalidArgumentException If alphabet is invalid or input contains invalid characters.
	 */
	public static function base32Decoder(string $input, ?string $alphabet = null): string
	{
		$alphabet ??= self::BASE32_RFC4648;

		if (strlen($alphabet) !== 32) {
			throw new InvalidArgumentException(
				'Base32 alphabet must be exactly 32 characters.'
			);
		}

		$map = array_flip(str_split($alphabet));

		$buffer = 0;
		$bits = 0;
		$output = '';

		$length = strlen($input);

		for ($i = 0; $i < $length; $i++) {
			$char = $input[$i];

			if (!isset($map[$char])) {
				throw new InvalidArgumentException("Invalid Base32 character: {$char}");
			}

			$buffer = ($buffer << 5) | $map[$char];
			$bits += 5;

			while ($bits >= 8) {
				$bits -= 8;
				$output .= chr(($buffer >> $bits) & 0xFF);
			}
		}

		return $output;
	}

	/**
	 * Resolve the content encoding or response content encoding.
	 * 
	 * Checks whether compression is enabled and whether the client accepts
	 * the configured compression encoding.
	 *
	 * Resolution order:
	 * 1. Cached encoding.
	 * 2. Request `Content-Encoding` header.
	 * 3. Configured compression encoding if accepted by the client.
	 * 
	 * @param bool $forCompression Resolve for compression or decompression encoding.
	 *
	 * @return string|null The resolved encoding, or `null` if none is available.
	 */
	public static function resolveEncoding(bool $forCompression): ?string
	{
		$key = (int) $forCompression;

		if (isset(self::$encoding[$key])) {
			return self::$encoding[$key];
		}

		if($forCompression){
			return self::$encoding[$key] = self::resolveBestEncoding();
		}

		$encoding = $_SERVER['HTTP_CONTENT_ENCODING'] ?? null;

		if ($encoding !== null) {
			return self::$encoding[$key] = strtolower($encoding);
		}

		return null;
	}

	/**
	 * Resolve the PHP compression handler.
	 *
	 * @param string $encoding The content encoding (e.g. `gzip`, `deflate`, `compress`).
	 * @param bool $encoder Whether to return the encoder (`true`) or decoder (`false`).
	 *
	 * @return string|null The corresponding PHP function name, or `null` if unsupported.
	 */
	public static function resolveCompressionHandler(string $encoding, bool $encoder = true): ?string
	{
		$handler = match (strtolower($encoding)) {
			'gzip', 'x-gzip' => $encoder ? 'gzencode' : 'gzdecode',
			'deflate'        => $encoder ? 'gzdeflate' : 'gzdecode',
			'compress'       => $encoder ? 'gzcompress' : 'gzuncompress',
			'zstd' 			 => $encoder ? 'zstd_compress' : 'zstd_uncompress',
			'br' 			 => $encoder ? 'brotli_compress' : 'brotli_uncompress',
			default          => null,
		};

		if ($handler === null) {
			return null;
		}

		return function_exists($handler) ? $handler : null;
	}

	/**
	 * Resolve the best supported response encoding.
	 *
	 * Uses configured compression encoding when available and accepted.
	 * Otherwise selects the best encoding supported by both the server and client.
	 *
	 * @return string|null
	 */
	private static function resolveBestEncoding(): ?string
	{
		$accepts = self::getAcceptedEncodings();

		if ($accepts === []) {
			return null;
		}

		$configured = env('output.compression.encoding');

		if ($configured !== null && $configured !== 'auto') {
			$configured = strtolower($configured);

			if (isset($accepts[$configured]) && self::resolveCompressionHandler($configured)) {
				return $configured;
			}
		}

		foreach (self::COMPRESSION_HANDLERS as $encoding) {
			if (isset($accepts[$encoding]) && self::resolveCompressionHandler($encoding)) {
				return $encoding;
			}
		}

		return null;
	}

	/**
	 * Parse the Accept-Encoding header.
	 *
	 * @return array<string,float>
	 */
	private static function getAcceptedEncodings(): array
	{
		$header = strtolower($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');

		if ($header === '') {
			return [];
		}

		$encodings = [];

		foreach (explode(',', $header) as $item) {
			[$encoding, $options] = array_pad(
				explode(';', trim($item), 2),
				2,
				null
			);

			$quality = 1.0;

			if ($options !== null && preg_match('/q=([0-9.]+)/', $options, $match)) {
				$quality = (float) $match[1];
			}

			if ($quality > 0) {
				$encodings[$encoding] = $quality;
			}
		}

		return $encodings;
	}

	/**
	 * Resolve compression level.
	 *
	 * @param string $encoding
	 * 
	 * @return int
	 */
	private static function resolveCompressionLevel(string $encoding): int
	{
		$level = (int) env('output.compression.level', 6);

		return match (strtolower($encoding)) {
			'gzip', 'x-gzip', 'deflate', 'compress'
				=> max(-1, min(9, $level)),
			'br' 	=> max(0, min(11, $level)),
			'zstd' 	=> max(1, min(22, $level)),
			default => $level,
		};
	}

	/**
	 * Determine whether the current response can be compressed.
	 *
	 * Prevents compression when PHP output compression is already enabled,
	 * headers have been sent, the request uses range delivery, the response
	 * already has a Content-Encoding header, or another gzip output buffer
	 * handler is active.
	 *
	 * @return bool True if the response is safe to compress.
	 */
	private static function canCompressResponse(): bool
	{
		if (filter_var(ini_get('zlib.output_compression'), FILTER_VALIDATE_BOOL)) {
			return false;
		}

		if (headers_sent() || isset($_SERVER['HTTP_RANGE'])) {
			return false;
		}

		foreach (headers_list() as $header) {
			if (stripos($header, 'Content-Encoding:') === 0) {
				return false;
			}
		}

		if (!function_exists('ob_get_status')) {
			return false;
		}

		foreach (ob_get_status(true) as $buffer) {
			if (($buffer['name'] ?? '') === 'ob_gzhandler') {
				return false;
			}
		}

		return true;
	}

    /**
	 * Resolve Base32 alphabet by type.
	 *
	 * @param string|null $type Alphabet selector.
	 *
	 * @return string Alphabet string used for encoding/decoding.
	 *
	 * @throws InvalidArgumentException If type is unknown.
	 */
	private static function getBase32Alphabet(?string $type = null): string
	{
		if($type === null){
			return self::BASE32_RFC4648;
		}

		return match (strtolower($type)) {
			'rfc4648'   => self::BASE32_RFC4648,
			'crockford' => self::BASE32_CROCKFORD,
			'sid5'      => self::BASE32_SID_5BITS,
			default => throw new InvalidArgumentException(sprintf(
				'Unknown Base32 type: %s',
				$type
			)),
		};
	}
}