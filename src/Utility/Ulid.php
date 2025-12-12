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

use Luminova\Utility\{Random, Encoder};

final Class Ulid
{
	/**
	 * Generate a ULID (Universally Lexicographically Sortable Identifier).
	 *
	 * ULID is composed of:
	 * - 48-bit timestamp (milliseconds since Unix epoch)
	 * - 80-bit cryptographically secure randomness
	 *
	 * Encoding:
	 * - Crockford Base32 (default, URL-safe, sortable)
	 *
	 * @return string Return 26-character ULID uppercase string.
	 *
	 * Notes:
	 * - Output is always 26 characters when using Crockford Base32.
	 */
	public static function generate(): string
	{
		$random = Random::bytes();

		$timestamp = $random['timestamp'];
		$parts = '';

		for ($i = 0; $i < 10; $i++) {
			$parts = Encoder::BASE32_CROCKFORD[$timestamp & 0x1F] . $parts;
			$timestamp >>= 5;
		}

		return $parts . substr(Encoder::base32Encoder(
			$random['bytes'], 
			Encoder::BASE32_CROCKFORD
		), 0, 16);
	}

    /**
	 * Validate ULID (Crockford Base32).
	 *
	 * Optionally validates timestamp range (heuristic check only).
	 *
	 * @param string $ulid ULID string.
	 * @param bool $withTimestamp Whether to validate timestamp portion.
	 *
	 * @return bool True if valid ULID, false otherwise.
	 */
	public static function isValid(string $ulid, bool $withTimestamp = false): bool
	{
		$ulid = strtoupper(trim($ulid));

		if ($ulid === '' || strlen($ulid) !== 26) {
			return false;
		}

		if (!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid)) {
			return false;
		}

		if (!$withTimestamp) {
			return true;
		}

		$timestamp = self::decodeUlidTime(substr($ulid, 0, 10));

		if ($timestamp === null) {
			return false;
		}

		// range check (not strict RFC validation)
		$now = (int) (microtime(true) * 1000);
		$maxFuture = $now + (100 * 365 * 24 * 60 * 60 * 1000);

		return $timestamp >= 0 && $timestamp <= $maxFuture;
	}

    /**
	 * Decode ULID timestamp (first 10 chars).
	 *
	 * @param string $time
	 * 
	 * @return int|null
	 */
	private static function decodeUlidTime(string $time): ?int
	{
		static $map = null;

		if ($map === null) {
			$map = array_flip(str_split(Encoder::BASE32_CROCKFORD));
		}

		$value = 0;

		for ($i = 0; $i < 10; $i++) {
			if (!isset($map[$time[$i]])) {
				return null;
			}

			$value = ($value * 32) + $map[$time[$i]];
		}

		return $value;
	}
}