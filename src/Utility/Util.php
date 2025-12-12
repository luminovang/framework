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

use \PhpToken;
use Luminova\Luminova;
use Luminova\Exceptions\InvalidArgumentException;

final class Util
{
	/**
	 * Only numeric digits (0–9).
	 * 
	 * @var string SANITIZE_INT
	 */
	public const SANITIZE_INT = 'int';

	/**
	 * Numeric value including negative and decimal numbers.
	 * 
	 * @var string SANITIZE_NUMERIC
	 */
	public const SANITIZE_NUMERIC = 'numeric';

	/**
	 * Alphanumeric, underscore and hyphen.
	 * 
	 * @var string SANITIZE_KEY
	 */
	public const SANITIZE_KEY = 'key';

	/**
	 * Strong password format (uppercase, lowercase, number, special char).
	 * 
	 * @var string SANITIZE_PASSWORD
	 */
	public const SANITIZE_PASSWORD = 'password';

	/**
	 * Username (3–30 chars): letters, digits, underscore, dot, hyphen.
	 * 
	 * @var string SANITIZE_USERNAME
	 */
	public const SANITIZE_USERNAME = 'username';

	/**
	 * Email address format.
	 * 
	 * @var string SANITIZE_EMAIL
	 */
	public const SANITIZE_EMAIL = 'email';

	/**
	 * URL format.
	 * 
	 * @var string SANITIZE_URL
	 */
	public const SANITIZE_URL = 'url';

	/**
	 * Money value: numeric with optional decimal and negative.
	 * 
	 * @var string SANITIZE_MONEY
	 */
	public const SANITIZE_MONEY = 'money';

	/**
	 * Floating point number.
	 * 
	 * @var string SANITIZE_DOUBLE
	 */
	public const SANITIZE_DOUBLE = 'double';

	/**
	 * Alphabetic characters only (A–Z, a–z).
	 * 
	 * @var string SANITIZE_ALPHABET
	 */
	public const SANITIZE_ALPHABET = 'alphabet';

	/**
	 * Phone number: digits, plus sign and hyphen.
	 * 
	 * @var string SANITIZE_PHONE
	 */
	public const SANITIZE_PHONE = 'phone';

	/**
	 * Human name: Unicode letters, digits, spaces and common symbols.
	 * 
	 * @var string SANITIZE_NAME
	 */
	public const SANITIZE_NAME = 'name';

	/**
	 * Timezone identifier (e.g. Africa/Lagos, UTC+1).
	 * 
	 * @var string SANITIZE_TIMEZONE
	 */
	public const SANITIZE_TIMEZONE = 'timezone';

	/**
	 * Time format (HH:MM or HH:MM:SS).
	 * 
	 * @var string SANITIZE_TIME
	 */
	public const SANITIZE_TIME = 'time';

	/**
	 * Date or datetime format (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).
	 * 
	 * @var string SANITIZE_DATE
	 */
	public const SANITIZE_DATE = 'date';

	/**
	 * UUID format (8-4-4-4-12 hexadecimal).
	 * 
	 * @var string SANITIZE_UUID
	 */
	public const SANITIZE_UUID = 'uuid';

	/**
	 * Default sanitizer: strips HTML tags, allows all other characters.
	 * @var string SANITIZE_DEFAULT
	 */
	public const SANITIZE_DEFAULT = 'default';

	/**
	 * Generate a deterministic human-readable identifier from any scalar value.
	 *
	 * The same input always produces the same identifier when using the same
	 * hashing algorithm. When the GMP extension is available, the hash is
	 * converted from hexadecimal to a compact Base-36 representation. Otherwise,
	 * the hexadecimal hash is used as a fallback.
	 *
	 * The resulting identifier can be truncated to a fixed length and optionally
	 * grouped using hyphens for better readability.
	 *
	 * @param string $from Value used to generate the identifier.
	 * @param int $length Maximum length of the formatted identifier, including separators (between: `4` and `64`).
	 * @param int $group Number of characters per group. Set to `0` or less to disable grouping.
	 * @param string $algo Hash algorithm supported by {@see hash()} (default: `sha256`).
	 *
	 * @return string Returns a deterministic formatted identifier.
	 * @throws InvalidArgumentException If the hash algorithm is unsupported or
	 *                                  the length is less than 1.
	 * 
	 * @see self::isIdentifier()
	 *
	 * @example - Generate an identifier
	 * ```php
	 * $id = Util::identifier(12345);
	 * // 2Y4E9-7QKJ8-W1P6R-V8H
	 * ```
	 *
	 * @example - Disable grouping
	 * ```php
	 * $id = Util::identifier('order-1001', 20, 0);
	 * // 2Y4E97QKJ8W1P6RV8H3
	 * ```
	 */
	public static function identifier(
		string $from,
		int $length = 23,
		int $group = 5,
		string $algo = 'sha256'
	): string
	{
		if ($length < 4 || $length > 64) {
			throw new InvalidArgumentException(
				'Identifier length must be between 4 and 64.'
			);
		}

		$hash = Luminova::hash($algo, trim($from), fallbackAlgo: null);

		$raw = function_exists('gmp_init')
			? strtoupper(gmp_strval(gmp_init($hash, 16), 36))
			: strtoupper($hash);

		$rawLength = $length;

		if ($group > 0) {
			$group = min($group, $length);
			$rawLength = max(1, $length - intdiv($length - 1, $group));
		}

		$raw = substr(str_pad($raw, $rawLength, '0'), 0, $rawLength);

		return ($group > 0)
			? implode('-', str_split($raw, $group))
			: $raw;
	}

	/**
	 * Verify that an identifier was generated from the given source value.
	 *
	 * This method regenerates the deterministic identifier using the same
	 * generation parameters and performs a timing-safe comparison against the
	 * provided identifier.
	 *
	 * It first validates the identifier format to quickly reject malformed
	 * values before performing the hash comparison.
	 *
	 * @param string $from Original value used when generating the identifier.
	 * @param string $identifier Identifier value to verify.
	 * @param int $length Maximum identifier length including formatting separators.
	 * @param int $group Number of characters per group when formatting the identifier.
	 * @param string $algo Hash algorithm used during generation.
	 *
	 * @return bool Returns true when the identifier matches the generated value,
	 *              otherwise false.
	 *
	 * @example - Example:
	 * ```php
	 * $id = Util::identifier('order-1001');
	 *
	 * Util::isIdentifier('order-1001', $id);
	 * // true
	 *
	 * Util::isIdentifier('order-1002', $id);
	 * // false
	 * ```
	 */
	public static function isIdentifier(
		string $from,
		string $identifier,
		int $length = 23,
		int $group = 5,
		string $algo = 'xxh128'
	): bool
	{
		$from = trim($from);

		if (!self::isIdentifierFormat($identifier)) {
			return false;
		}

		return hash_equals(
			self::identifier($from, $length, $group, $algo),
			strtoupper($identifier)
		);
	}

	/**
     * Convert a PHP array into a compact PHP array syntax string.
     *
     * Converts the array using `var_export()`, tokenizes the generated PHP code,
     * removes unnecessary whitespace, and transforms legacy `array()` syntax into
     * short array syntax (`[]`). Trailing commas before closing brackets are also
     * removed to produce a minimized representation.
     *
     * @param array $array Array to convert into a minimized string format.
     *
     * @return string|null Returns the minimized array syntax string, or null when
     *                     conversion fails.
     */
    public static function minifyArray(array $array): string
    {
        if ($array === []) {
            return '[]';
        }

        $tokens = PhpToken::tokenize('<?php ' . var_export($array, true));
        $string = '';

        $parent = [];
        $isFromParentArray = false;

        foreach ($tokens as $token) {
            $text = $token->text;
            $id = $token->id;

            if ($id === T_OPEN_TAG) {
                continue;
            }

            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $string .= $text;
                continue;
            }

            if ($id === T_WHITESPACE) {
                continue;
            }

            if ($id === T_ARRAY) {
                $isFromParentArray = true;
                continue;
            }

            if ($text === '(') {
                if ($isFromParentArray) {
                    $parent[] = true;
                    $string .= '[';
                    $isFromParentArray = false;
                } else {
                    $parent[] = false;
                    $string .= '(';
                }
                continue;
            }

            if ($text === ')') {
                $isParent = array_pop($parent) ?? false;
                $string .= $isParent ? ']' : ')';
                continue;
            }

            $string .= $text;
        }

        $string = preg_replace('/,(\s*])/m', '$1', $string);
        $string = trim($string);

        return $string;
    }

	/** 
	 * Formats a phone number as (xxx) xxx-xxxx or xxx-xxxx depending on the length.
	 *
	 * @param string $phone phone address to format
	 *
	 * @return string Return the formatted phone number.
	 */
	public static function formatPhone(string $phone): string 
	{
		if(!$phone){
			return '';
		}

		$phone = preg_replace('/\D+/', '', $phone);
	
		return match(strlen($phone)){
			7 => preg_replace('/(\d{3})(\d{4})/', "$1-$2", $phone),
			10 => preg_replace('/(\d{3})(\d{3})(\d{4})/', "($1) $2-$3", $phone),
			11 => preg_replace('/(\d{1})(\d{3})(\d{3})(\d{4})/', '$1-$2-$3-$4', $phone),
			default => $phone,
		};
	}

	/**
	 * Extract the registrable main domain from a URL or hostname.
	 *
	 * Removes subdomains and returns the base domain name, including the
	 * top-level domain. The input may be a full URL or a plain hostname.
	 *
	 * Examples:
	 * - `www.example.com`      → `example.com`
	 * - `api.dev.example.com`  → `example.com`
	 * - `example.co.uk`        → `example.co.uk`
	 *
	 * @param string $url The URL or hostname to extract the main domain from.
	 *
	 * @return string The extracted main domain, or the original value if parsing fails.
	 * @see self::subDomain()
	 */
	public static function mainDomain(string $url): string
	{
		$domain = parse_url(
			preg_match('~^(?:f|ht)tps?://~i', $url) 
				? $url
				: "http://{$url}"
			, PHP_URL_HOST);

		if($domain === false || $domain === null){
			return $url;
		}

		$count = substr_count($domain, '.');

		if ($count === 2) {
			$parts = explode('.', $domain);

			if (strlen($parts[1]) > 3) {
				$domain = explode('.', $domain, 2)[1];
			}
		} elseif ($count > 2) {
			$domain = self::mainDomain(explode('.', $domain, 2)[1]);
		}

		return $domain;
	}

	/**
	 * Extract the first-level subdomain from a URL or hostname.
	 *
	 * Returns only the first subdomain segment directly before the main domain.
	 * Multi-level subdomains are ignored, and `www` is treated as a non-subdomain.
	 *
	 * Examples:
	 * - `api.example.com`          → `api`
	 * - `api.dev.example.com`      → `dev`
	 * - `www.example.com`          → ``
	 * - `www.api.example.com`      → `api`
	 *
	 * @param string $url The URL or hostname to extract the subdomain from.
	 *
	 * @return string The first-level subdomain, or an empty string if none exists.
	 * @see self::mainDomain()
	 */
	public static function subDomain(string $url): string
	{
		$domain = '';

		if (str_contains($url, '.')) {
			$parts = explode('.', $url, 4);

			if (count($parts) >= 3) {
				$domain = ($parts[1] !== 'www') 
					? $parts[1] 
					: $parts[2];
			}
		}

		return $domain;
	}

	/**
	 * Strictly sanitizes or validates user input based on the specified type.
	 *
	 * This method can either **replace invalid characters** with a given replacement
	 * or **validate throw an exception** if the input contains disallowed characters (strict mode).
	 *
	 * **Supported types and their rules:**
	 * - 'int'       : Only digits (0-9)
	 * - 'numeric'   : Numbers, including negatives and decimals
	 * - 'key'       : Alphanumeric, underscore, hyphen
	 * - 'password'  : Complex password (letters, digits, special chars), strict validation
	 * - 'username'  : Alphanumeric, underscore, hyphen, dot, 3–30 chars
	 * - 'email'     : Standard email format
	 * - 'url'       : Valid URL characters, optional scheme and port
	 * - 'money'     : Decimal numbers with optional negative sign
	 * - 'double'    : Floating point numbers
	 * - 'alphabet'  : Letters only
	 * - 'phone'     : Numbers, plus, hyphen
	 * - 'name'      : Unicode letters, digits, spaces, apostrophes, underscore, dot, hyphen
	 * - 'timezone'  : Letters, digits, colon, slash, comma, underscore, space, hyphen
	 * - 'time'      : hh:mm or hh:mm:ss
	 * - 'date'      : yyyy-mm-dd or yyyy-mm-dd hh:mm:ss
	 * - 'uuid'      : Standard 8-4-4-4-12 hex UUID
	 * - 'default'   : Removes HTML tags entirely
	 *
	 * **Usage modes:**
	 * - **Replacement mode:** Provide a `$replacement` string to replace invalid characters.
	 * - **Validation mode:** Pass `$replacement = null` to throw an exception if input is invalid.
	 *
	 * @param string $value  Input string to sanitize.
	 * @param string $type Expected type e.g, `self::SANITIZE_*`, (default: `Util::SANITIZE_DEFAULT`).
	 * @param string|null $replacement Replacement for disallowed characters, 
     *          or null to enforce strict validation.
	 *
	 * @return string|null Returns sanitized string, or null if input cannot be sanitized in replacement mode.
	 * @throws InvalidArgumentException If input does not match expected format and `$replacement` is null.
	 * 
	 * @example - Examples:
	 * ```php
	 * // Safe replacement
	 * $clean = Util::sanitize('<b>Hello</b>', 'default'); // 'Hello'
	 * 
	 * // Strict validation
	 * $uuid = Util::sanitize('550e8400-e29b-41d4-a716-446655440000', 'uuid', null);
	 * 
	 * // Throws exception if invalid
	 * 
	 * $strictUuid = Util::sanitize('invalid-uuid', 'uuid', null); // InvalidArgumentException
	 * ```
	 *
	 * > **Notes:**
	 * > - HTML tags are fully removed in 'default' type.
	 * > - For some types like 'password', 'email', 'username', replacement is disabled and validation is strict.
	 * > - Trimming is applied to the result before returning.
	 */
	public static function sanitize(
		string $value,
		string $type = self::SANITIZE_DEFAULT,
		?string $replacement = ''
	): ?string 
	{
		$isDefault = $type === self::SANITIZE_DEFAULT;

		if ($isDefault && $value === '') {
			return $value;
		}

		$pattern = self::getSanitizerPattern($type);

		if($pattern === []){
			throw new InvalidArgumentException(
				"Sanitize type '{$type}' is not supported."
			);
		}

		$html = '/<[^>]*>.*?<\/[^>]*>/s';
		//$html = '/<[^>]*>/';

		// Validate only
		if ($replacement === null) {
			if (
				!preg_match($pattern['validate'], $value) || 
				(!$isDefault && preg_match($html, $value))
			) {
				throw new InvalidArgumentException(
					"String does not match the required format for type: {$type}."
				);
			}

			return $value;
		}

		if($pattern['replace'] === false){
			return preg_match($pattern['validate'], $value) ? $value : null;
		}

		if($type ===  self::SANITIZE_INT && ctype_digit((string)$value)) {
			return $value;
		}

		if($type ===  self::SANITIZE_NUMERIC && is_numeric($value)){
			return $value;
		}
	
		// Replace all HTML tags first
		if(!$isDefault){
			$value = preg_replace($html, $replacement, $value);

			if($value === null){
				return null;
			}
		}

		$value = preg_replace($pattern['replace'], $replacement, $value);

		if($value === null){
			return null;
		}

		return trim($value);
	}

	/**
	 * Check whether an identifier matches the expected format.
	 *
	 * This method only validates the structure of the identifier and does not
	 * verify that it was generated from a specific source value.
	 *
	 * It is useful for input validation before processing identifiers received
	 * from external sources.
	 *
	 * @param string $identifier Identifier value to validate.
	 * @param int $group Number of characters expected per group. Set to `0` or
	 *                    less when validating an ungrouped identifier.
	 *
	 * @return bool Returns true when the identifier format is valid, otherwise false.
	 */
	private static function isIdentifierFormat(
		string $identifier,
		int $group = 5
	): bool
	{
		if ($group <= 0) {
			return ctype_alnum($identifier);
		}

		return (bool) preg_match(
			'/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/',
			strtoupper($identifier)
		);
	}

	/**
	 * Retrieves validation and replacement patterns for various data types.
	 *
	 * @param string $type The type of data for which to retrieve patterns.
	 *
	 * @return array An associative array containing two keys:
	 *               'validate' - The regular expression for validating the input.
	 *               'replace'  - The regular expression for replacing invalid characters.
	 */
	private static function getSanitizerPattern(string $type): array 
	{
		return match($type){
			self::SANITIZE_INT => [
				'validate' => "/^[0-9]+$/",
				'replace'  => "/[^0-9]+/"
			],
			 self::SANITIZE_NUMERIC, 'digit' => [
				'validate' => "/^-?[0-9]+(\.[0-9]+)?$/",
				'replace'  => "/[^-0-9.]+/"
			],
			 self::SANITIZE_KEY => [
				'validate' => "/^[a-zA-Z0-9_-]+$/",
				'replace'  => "/[^a-zA-Z0-9_-]+/"
			],
			 self::SANITIZE_PASSWORD => [
				'validate' => "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#^&!*_.$)(-])[A-Za-z\d@#^&!*_.$)(-]{8,128}$/",
				'replace'  => false
			],
			self::SANITIZE_USERNAME => [
				'validate' => "/^[a-zA-Z0-9_.-]{3,30}$/", // Minimum 3, maximum 30 characters
				'replace'  => false
			],
			self::SANITIZE_EMAIL => [
				'validate' => "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/",
				'replace'  => false
			],
			self::SANITIZE_URL => [
				'validate' => "/^([a-z][a-z0-9+.-]*:\/\/)?([a-z0-9-]+\.)+[a-z]{2,}(:[0-9]{1,5})?(\/[a-zA-Z0-9?#&+=.:\/_-]*)?$/i",
				'replace' => false 
			],
			self::SANITIZE_MONEY => [
				'validate' => "/^-?[0-9]+(\.[0-9]{1,2})?$/",
				'replace'  => "/[^0-9.-]+/"
			],
			self::SANITIZE_DOUBLE => [
				'validate' => "/^-?[0-9]+(\.[0-9]+)?$/",
				'replace'  => "/[^0-9.-]+/"
			],
			self::SANITIZE_ALPHABET => [
				'validate' => "/^[a-zA-Z]+$/",
				'replace'  => "/[^a-zA-Z]+/"
			],
			self::SANITIZE_PHONE => [
				'validate' => "/^\+?[0-9-]+$/", // Allow international format with `+`
				'replace'  => "/[^0-9-+]+/"
			],
			self::SANITIZE_NAME => [
				'validate' => "/^[\p{L}0-9\s''_.-]+$/u",
				'replace'  => "/[^\p{L}0-9\s''_.-]+/u"
			],
			self::SANITIZE_TIMEZONE => [
				'validate' => "/^[a-zA-Z0-9\/,_:+ -]+$/",
				'replace'  => "/[^a-zA-Z0-9\/,_:+ -]+/"
			],
			self::SANITIZE_TIME => [
				'validate' => "/^[0-9]{1,2}:[0-9]{2}(:[0-9]{2})?$/",
				'replace'  => "/[^0-9:]+/"
			],
			self::SANITIZE_DATE => [
				'validate' => "/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/",
				'replace'  => "/[^0-9T:-]+/"
			],
			self::SANITIZE_UUID => [
				'validate' => "/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/",
				'replace'  => "/[^0-9a-fA-F-]+/"
			],
			self::SANITIZE_DEFAULT => [
				'validate' => "/^(?!.*<[^>]+>).+$/", // Match anything that doesn't contain HTML tags
				'replace'  => "/<[^>]*>.*?<\/[^>]*>/s", // Remove HTML tags with their content
			], 
			default => []
		};
	}
}