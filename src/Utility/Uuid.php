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

use Luminova\Exceptions\InvalidArgumentException;

final Class Uuid
{
	/**
	 * UUID default patterns.
	 * 
	 * @var string UUID_PATTERN
	 */
	private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[13457][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

	/**
	 * UUID version patterns.
	 * 
	 * @var array UUID_PATTERNS
	 */
	private const UUID_PATTERNS = [
		1 => '/^[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
		3 => '/^[0-9a-f]{8}-[0-9a-f]{4}-3[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
		4 => '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
		5 => '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
		7 => '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
	];

	/**
	 * UUID V3/V5 namespace.
	 * 
	 * @var array<string,string> UUID_NAMESPACE
	 */
	public const UUID_NAMESPACE = [
		'dns'  => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
		'url'  => '6ba7b811-9dad-11d1-80b4-00c04fd430c8',
		'oid'  => '6ba7b812-9dad-11d1-80b4-00c04fd430c8',
		'x500' => '6ba7b814-9dad-11d1-80b4-00c04fd430c8'
	];

	/**
	 * Generate a UUID of the specified version.
	 *
	 * Supported versions:
	 * - `1` - Time-based.
	 * - `3` - Namespace-based (MD5).
	 * - `4` - Random.
	 * - `5` - Namespace-based (SHA-1).
	 * - `7` - Unix time-ordered (sortable UUID).
	 *
	 * Versions `3` and `5` require a namespace and name.
	 * The namespace may be a UUID or one of the predefined aliases:
	 *
	 * - `dns`  - Domain names
	 * - `url`  - URLs
	 * - `oid`  - Object identifiers
	 * - `x500` - X.500 distinguished names
	 *
	 * Time parameter behavior:
	 * - `v1` - Optional 100-nanosecond timestamp (UUID epoch: 1582-10-15)
	 * - `v7` - Optional timestamp in milliseconds (Unix epoch: 1970-01-01)
	 *
	 * If not provided, the current system time is used.
	 *
	 * @param int $version UUID version (`1`, `3`, `4`, `5`, or `7`).
	 * @param string|null $namespace Namespace UUID or alias (required for `v3` and `v5`).
	 * @param string|null $name Name used for namespace-based UUIDs (`v3`, `v5`).
	 * @param int|null $time Optional timestamp:
	 *      - For `v1`: 100-nanosecond intervals since UUID epoch.
	 *      - For `v7`: milliseconds since Unix epoch.
	 *
	 * @return string Generated UUID string.
	 *
	 * @throws InvalidArgumentException If:
	 * - the version is unsupported,
	 * - namespace or name is missing for v3/v5,
	 * - namespace is invalid,
	 * - or timestamp is invalid for v1/v7.
	 *
	 * @see UUID::isValid()
	 */
	public static function generate(
		int $version = 4,
		?string $namespace = null,
		?string $name = null,
		?int $time = null
	): string
	{
		return match ($version) {
			1 => self::v1($time),
			4 => self::v4(),
			7 => self::v7($time),
			3, 5 => self::uuidV3V5($namespace, $name, $version),
			default => throw new InvalidArgumentException(
				sprintf(
					'Unsupported UUID version "%d". Supported versions are: 1, 3, 4, 5 and 7.',
					$version
				)
			)
		};
	}

	/**
	 * Validate a UUID value.
	 *
	 * Supports RFC 4122 / RFC 9562 UUID versions 1, 3, 4, 5, and 7.
	 *
	 * Validation includes:
	 * - UUID format, version, and RFC variant bits.
	 * - Optional namespace verification for v3 and v5 by regenerating the UUID.
	 * - Optional timestamp validation for v1 and v7 using configurable tolerances.
	 *
	 * Predefined namespace aliases for v3/v5:
	 * - `dns`  - Domain names
	 * - `url`  - URLs
	 * - `oid`  - Object identifiers
	 * - `x500` - X.500 distinguished names
	 *
	 * @param string $uuid UUID string to validate.
	 * @param int|null $version Expected UUID version (`1`, `3`, `4`, `5`, or `7`).
	 *      Pass `null` or `0` to accept any supported version.
	 * @param string|null $namespace Namespace UUID or alias used to verify v3/v5 UUIDs.
	 *      Ignored for other versions.
	 * @param string|null $name Name used to verify v3/v5 UUIDs.
	 *      Ignored for other versions.
	 * @param int|null $maxPastAgeMs Maximum allowed age (in milliseconds) for UUID
	 *      timestamps (applies only to v1 and v7). Null disables the check.
	 * @param int|null $maxFutureSkewMs Maximum allowed clock drift into the future
	 *      (in milliseconds) for UUID timestamps (applies only to v1 and v7).
	 *      Null disables the check.
	 *
	 * @return bool True if the UUID is valid, otherwise false.
	 *
	 * @throws InvalidArgumentException If:
	 * - the version is unsupported,
	 * - or namespace/name is required but missing for v3/v5.
	 *
	 * > **Note:**
	 * > - Timestamp validation applies only to UUID `v1` and `v7`.
	 * > - RFC 9562 does not define timestamp range validation.
	 *   Use tolerances only in trusted, time-synchronized environments.
	 *
	 * @example - Example:
	 *
	 * ```php
	 * // Validate any supported UUID.
	 * Uuid::isValid($uuid);
	 *
	 * // Validate UUID v4.
	 * Uuid::isValid($uuid, 4);
	 *
	 * // Validate UUID v5 with DNS namespace.
	 * Uuid::isValid($uuid, 5, 'dns', 'example.com');
	 *
	 * // Validate UUID v5 with custom namespace.
	 * Uuid::isValid(
	 *     $uuid,
	 *     5,
	 *     '550e8400-e29b-41d4-a716-446655440000',
	 *     'user:1001'
	 * );
	 *
	 * // Validate UUID v7 with:
	 * // - 5 min past tolerance
	 * // - 30 sec future tolerance
	 * Uuid::isValid(
	 *     $uuid,
	 *     7,
	 *     null,
	 *     null,
	 *     300000,
	 *     30000
	 * );
	 * ```
	 */
	public static function isValid(
		string $uuid,
		?int $version = null,
		?string $namespace = null,
		?string $name = null,
		?int $maxPastAgeMs = null,
		?int $maxFutureSkewMs = null
	): bool
	{
		$uuid = strtolower(trim($uuid));

		if ($uuid === '' || strlen($uuid) !== 36) {
			return false;
		}

		if ($version === null || $version === 0) {
			if (!preg_match(self::UUID_PATTERN, $uuid)) {
				return false;
			}

			$version = self::version($uuid);

			if($version === null){
				return false;
			}
		} else {
			if (!isset(self::UUID_PATTERNS[$version])) {
				return false;
			}

			if (!preg_match(self::UUID_PATTERNS[$version], $uuid)) {
				return false;
			}
		}

		if (
			($version === 1  || $version === 7)
			&& !self::isValidV1V7Timestamp(
				$uuid, 
				$version, 
				$maxPastAgeMs, 
				$maxFutureSkewMs
			)
		) {
			return false;
		}

		if (
			($version === 3 || $version === 5)
			&& $namespace !== null
			&& $name !== null
		) {
			return self::uuidV3V5($namespace, $name, $version) === $uuid;
		}

		return true;
	}

	/**
	 * Extract the UUID version.
	 *
	 * Returns the embedded version if the UUID has a valid structural layout,
	 * otherwise returns `null`.
	 *
	 * @param string $uuid UUID string.
	 *
	 * @return int|null UUID version, or null if the format is invalid.
	 */
	public static function version(string $uuid): ?int
	{
		$uuid = trim($uuid);

		if (strlen($uuid) !== 36) {
			return null;
		}

		if (
			$uuid[8] !== '-'
			|| $uuid[13] !== '-'
			|| $uuid[18] !== '-'
			|| $uuid[23] !== '-'
		) {
			return null;
		}

		$char = strtolower($uuid[14]);

		if (!ctype_xdigit($char)) {
			return null;
		}

		return hexdec($char);
	}

	/**
     * Generates a legacy UUID version 1.
     *
     * @return string The generated UUID string.
     */
	public static function v1(?int $time): string
	{
		$time = ($time === null) 
			? microtime(true) * 1e7 + 0x01B21DD213814000
			: self::parseTimestampMs(7, $time, 60);

		$timeHex = sprintf('%016x', $time);

		$clockSeq = random_bytes(2);
		$node = random_bytes(6);

		return sprintf(
			'%08s-%04s-1%03s-%04x-%012s',
			substr($timeHex, -8),
			substr($timeHex, -12, 4),
			substr($timeHex, -15, 3),
			hexdec(bin2hex($clockSeq)) & 0x3fff | 0x8000,
			bin2hex($node)
		);
	}

	/**
     * Generates a legacy UUID version 4.
     *
     * @return string The generated UUID string.
     */
	public static function v4(): string
	{
		$data = random_bytes(16);

		$data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

		return vsprintf(
			'%s%s-%s-%s-%s-%s%s%s',
			str_split(bin2hex($data), 4)
		);
	}

	/**
     * Generates a legacy UUID version 7.
     *
     * @return string The generated UUID string.
     */
	public static function v7(?int $timeMs = null): string
	{
		$timeMs = ($timeMs === null) 
			? (int) (microtime(true) * 1000)
			: self::parseTimestampMs(7, $timeMs);

		$timeHex = sprintf('%012x', $timeMs);

		$random = random_bytes(10);
		$bytes = hex2bin($timeHex) . $random;

		// version 7
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
		// RFC 4122 variant
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

		return vsprintf(
			'%s%s-%s-%s-%s-%s%s%s',
			str_split(bin2hex($bytes), 4)
		);
	}
	
	/**
	 * Generate a namespace-based UUID (version 3 or 5).
	 *
	 * Accepts either a namespace UUID or one of the predefined namespace
	 * aliases (`dns`, `url`, `oid`, or `x500`).
	 *
	 * @param string|null $namespace Namespace UUID or predefined alias.
	 * @param string $name Name used to generate the UUID.
	 *
	 * @return string The generated UUID.
	 *
	 * @throws InvalidArgumentException If the namespace or name is empty,
	 *                                  or the namespace is not a valid UUID.
	 */
	public static function v3(?string $namespace, string $name): string
	{
		return self::uuidV3V5(
			$namespace,
			$name,
            3
		);
	}

    /**
	 * Generate a namespace-based UUID (version 3 or 5).
	 *
	 * Accepts either a namespace UUID or one of the predefined namespace
	 * aliases (`dns`, `url`, `oid`, or `x500`).
	 *
	 * @param string|null $namespace Namespace UUID or predefined alias.
	 * @param string $name Name used to generate the UUID.
	 *
	 * @return string The generated UUID.
	 *
	 * @throws InvalidArgumentException If the namespace or name is empty,
	 *                                  or the namespace is not a valid UUID.
	 */
	public static function v5(?string $namespace, string $name): string
	{
		return self::uuidV3V5(
			$namespace,
			$name,
            5
		);
	}

    /**
	 * Generate a namespace-based UUID (version 3 or 5).
	 *
	 * Accepts either a namespace UUID or one of the predefined namespace
	 * aliases (`dns`, `url`, `oid`, or `x500`).
	 *
	 * @param string|null $namespace Namespace UUID or predefined alias.
	 * @param string $name Name used to generate the UUID.
	 * @param int $version UUID version (`3` or `5`).
	 *
	 * @return string The generated UUID.
	 *
	 * @throws InvalidArgumentException If the namespace or name is empty,
	 *                                  or the namespace is not a valid UUID.
	 */
	private static function uuidV3V5(
		?string $namespace,
		string $name,
		int $version = 3
	): string
	{
		$namespace = trim((string) $namespace);
		$name = trim($name);

		if ($namespace === '' || $name === '') {
			throw new InvalidArgumentException(
				sprintf(
					'UUID v%d requires a namespace and name.',
					$version
				)
			);
		}

		$namespace = self::UUID_NAMESPACE[strtolower($namespace)] ?? $namespace;

		if (!self::isValid($namespace)) {
			throw new InvalidArgumentException(sprintf(
				'Invalid namespace UUID for UUID v%d.',
				$version
			));
		}

		$namespace = hex2bin(str_replace('-', '', $namespace));

		if ($namespace === false) {
			throw new InvalidArgumentException(
				'Failed to decode namespace UUID.'
			);
		}

		$hash = $version === 3
			? md5($namespace . $name)
			: sha1($namespace . $name);

		$timeHi = (hexdec(substr($hash, 12, 4)) & 0x0fff)
			| ($version << 12);

		$clockSeq = (hexdec(substr($hash, 16, 4)) & 0x3fff)
			| 0x8000;

		return sprintf(
			'%s-%s-%04x-%04x-%s',
			substr($hash, 0, 8),
			substr($hash, 8, 4),
			$timeHi,
			$clockSeq,
			substr($hash, 20, 12)
		);
	}

	/**
	 * Parse timestamp to milliseconds.
	 *
	 * @param int $version
	 * @param int $timestamp
	 * @param int $clockSkewMinutes
	 * 
	 * @return int
	 */
	private static function parseTimestampMs(
		int $version, 
		int $timestamp, 
		int $clockSkewMinutes = 5
	): int
	{
		if ($timestamp < 0) {
			throw new InvalidArgumentException(
				sprintf('Invalid timestamp for UUID v%d. Cannot be negative.', $version)
			);
		}

		$length = strlen((string) $timestamp);

		$ts = match (true) {
			// seconds → ms
			$length <= 10 => $timestamp * 1000,

			// milliseconds
			$length <= 13 => $timestamp,

			// microseconds → ms
			$length <= 16 => (int) floor($timestamp / 1000),

			default => throw new InvalidArgumentException(
				sprintf('Invalid UUID v1 timestamp format.', $version)
			)
		};

		 if ($version === 1) {
			$now = (int) (microtime(true) * 1000);

			$clockSkewMinutes = max(0, min($clockSkewMinutes, 60));
			$maxFuture = $now + ($clockSkewMinutes * 60 * 1000);

			if ($ts > $maxFuture) {
				throw new InvalidArgumentException(
					'Timestamp too far in future for UUID v1.'
				);
			}
		}

		return $ts;
	}

	/**
	 * Validate UUID V1 and V7 Timestamp.
	 *
	 * @param string $uuid
	 * @param int $version
	 * @param int|null $past
	 * @param int|null $future
	 * 
	 * @return bool
	 */
	private static function isValidV1V7Timestamp(string $uuid, int $version, ?int $past, ?int $future): bool
	{
		if (
			($past === null && $future === null)
			|| ($version !== 7 && $version !== 1)
		) {
			return true;
		}

		$now = (int) (microtime(true) * 1000);
		$hex = str_replace('-', '', $uuid);

		if($version === 7){
			$timestamp = hexdec(substr($hex, 0, 12));
		}else{
			// extract v1 timestamp (60-bit value)
			$timeLow = hexdec(substr($hex, 0, 8));
			$timeMid = hexdec(substr($hex, 8, 4));
			$timeHi  = hexdec(substr($hex, 12, 4)) & 0x0fff;

			$uuidTime = ($timeHi << 48) | ($timeMid << 32) | $timeLow;

			// convert to unix ms
			$timestamp = (int) floor(
				($uuidTime - 0x01B21DD213814000) / 10000
			);
		}

		if (
			($past !== null && $timestamp < ($now - max(0, $past)))
			|| ($future !== null && $timestamp > ($now + max(0, $future)))
		) {
			return false;
		}

		return true;
	}
}