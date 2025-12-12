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
use \Luminova\Utility\MIME;
use \Luminova\Storage\Filesystem;
use function \Luminova\Funcs\root;
use \Luminova\Exceptions\{RuntimeException, InvalidArgumentException};

class Helpers
{
	/**
	 * Only numeric digits (0–9).
	 * @var SANITIZE_INT
	 */
	public const SANITIZE_INT = 'int';

	/**
	 * Numeric value including negative and decimal numbers.
	 * @var SANITIZE_NUMERIC
	 */
	public const SANITIZE_NUMERIC = 'numeric';

	/**
	 * Alphanumeric, underscore and hyphen.
	 * @var SANITIZE_KEY
	 */
	public const SANITIZE_KEY = 'key';

	/**
	 * Strong password format (uppercase, lowercase, number, special char).
	 * @var SANITIZE_PASSWORD
	 */
	public const SANITIZE_PASSWORD = 'password';

	/**
	 * Username (3–30 chars): letters, digits, underscore, dot, hyphen.
	 * @var SANITIZE_USERNAME
	 */
	public const SANITIZE_USERNAME = 'username';

	/**
	 * Email address format.
	 * @var SANITIZE_EMAIL
	 */
	public const SANITIZE_EMAIL = 'email';

	/**
	 * URL format.
	 * @var SANITIZE_URL
	 */
	public const SANITIZE_URL = 'url';

	/**
	 * Money value: numeric with optional decimal and negative.
	 * @var SANITIZE_MONEY
	 */
	public const SANITIZE_MONEY = 'money';

	/**
	 * Floating point number.
	 * @var SANITIZE_DOUBLE
	 */
	public const SANITIZE_DOUBLE = 'double';

	/**
	 * Alphabetic characters only (A–Z, a–z).
	 * @var SANITIZE_ALPHABET
	 */
	public const SANITIZE_ALPHABET = 'alphabet';

	/**
	 * Phone number: digits, plus sign and hyphen.
	 * @var SANITIZE_PHONE
	 */
	public const SANITIZE_PHONE = 'phone';

	/**
	 * Human name: Unicode letters, digits, spaces and common symbols.
	 * @var SANITIZE_NAME
	 */
	public const SANITIZE_NAME = 'name';

	/**
	 * Timezone identifier (e.g. Africa/Lagos, UTC+1).
	 * @var SANITIZE_TIMEZONE
	 */
	public const SANITIZE_TIMEZONE = 'timezone';

	/**
	 * Time format (HH:MM or HH:MM:SS).
	 * @var SANITIZE_TIME
	 */
	public const SANITIZE_TIME = 'time';

	/**
	 * Date or datetime format (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS).
	 * @var SANITIZE_DATE
	 */
	public const SANITIZE_DATE = 'date';

	/**
	 * UUID format (8-4-4-4-12 hexadecimal).
	 * @var SANITIZE_UUID
	 */
	public const SANITIZE_UUID = 'uuid';

	/**
	 * Default sanitizer: strips HTML tags, allows all other characters.
	 * @var SANITIZE_DEFAULT
	 */
	public const SANITIZE_DEFAULT = 'default';

	/**
	 * Binary magic numbers.
	 * 
	 * @var array<string,string> $magicNumbers
	 */
	private static array $magicNumbers = [
		"\x89PNG\r\n\x1A\n" => 'png',
		"\xFF\xD8\xFF" 	    => 'jpg',
		"\x25\x50\x44\x46"  => 'pdf',
		"\x50\x4B\x03\x04"  => 'zip',
		"\x49\x44\x33" 	    => 'mp3',
		"\x47\x49\x46\x38"  => 'gif',
		"\xD0\xCF\x11\xE0"  => 'doc', 
		"\x50\x4B\x03\x04"  => 'docx', 
		"\x50\x4B\x07\x08"  => 'xlsx',
		"\x52\x49\x46\x46"  => 'wav',
		"\x00\x00\x01\xBA"  => 'mpg',
		"\x00\x00\x01\xB3"  => 'mpg',
		"\x1A\x45\xDF\xA3"  => 'mkv'
	];

	/**
     * Generates a UUID string of the specified version.
     *
     * @param int $version The version of the UUID to generate (1, 2, 3, 4, or 5).
     * @param string|null $namespace The namespace for versions 3 and 5.
     * @param string|null $name The name for versions 3 and 5.
	 * 
     * @return string Return the generated UUID string.
     * @throws InvalidArgumentException If the namespace or name is not provided for versions 3 or 5.
     */
	public static function uuid(int $version = 4, ?string $namespace = null, ?string $name = null): string
    {
		return ($version <= 2) 
			? self::uuidV1V2()
			: self::uuidV3V4V5($namespace, $name, $version);
    }

	/**
	 * Truncate a string with the specified length.
	 * 
	 * This method truncates and adds an ellipsis at the end if the text is longer than the specified length.
	 *
	 * @param string $text The string to truncate.
	 * @param int $length The length to display before truncating.
	 * @param string $encoding Text encoding type.
	 * 
	 * @return string Return the truncated string.
	 */
	public static function truncate(string $text, int $length = 10, string $encoding = 'UTF-8'): string
	{
		if($text === ''){
			return $text;
		}

		if (mb_strlen($text, $encoding) > $length) {
			return mb_substr($text, 0, $length, $encoding);
		}

		return $text;
	}

	/**
	 * Mask a string by position.
	 *
	 * @param string $string  String to mask.
	 * @param string $masker  Mask character (default is "*").
	 * @param string $position  The position of the string to mask ("center", "left", or "right").
	 * 
	 * @return string Return masked string.
	 * @see maskEmail() - To mask an email address.
	 */
	public static function mask(
		string $string, 
		string $masker = '*', 
		string $position = 'center'
	): string 
	{
		if ($string === '') {
			return '';
		}

		$length = strlen($string);
		$visibleCount = (int) round($length / 4);

		if ($position === 'right') {
			return substr($string, 0, ($visibleCount * -1)) . str_repeat($masker, $visibleCount);
		}

		$hiddenCount = (int) ($length - ($visibleCount * 2));

		if ($position === 'left') {
			return str_repeat($masker, $visibleCount) . substr($string, $visibleCount, $hiddenCount) . substr($string, ($visibleCount * -1), $visibleCount);
		}

		return substr($string, 0, $visibleCount) . str_repeat($masker, $hiddenCount) . substr($string, $visibleCount * -1, $visibleCount);
	}

	/**
	 * Mask email address.
	 *
	 * @param string $email Email address to mask.
	 * @param string $masker  Mask character (default is "*").
	 * 
	 * @return string Return masked email address.
	 * @see mask() - To mask any string.
	 */
	public static function maskEmail(string $email, string $masker = '*'): string 
	{
		if ($email === '') {
			return '';
		}

		$parts = explode("@", $email);
		$name = implode('@', array_slice($parts, 0, -1));
		$length = floor(strlen($name) / 2);

		return substr($name, 0, (int) $length)
			 . str_repeat($masker, (int) $length) 
			 . "@" 
			 . (array_last($parts) ?? '');
	}

	/**
	 * Generate a random string or value.
	 *
	 * @param int $length The length of the random value to generate.
	 * @param string $type The type of random value to generate (e.g., character, alphabet, int, password, bytes, hex).
	 * @param bool $uppercase Whether to convert non-numeric values to uppercase (default: false).
	 * 
	 * @return string Return the generated randomized value.
	 * 
	 * **Supported Types:**
	 * 
	 *   - `character` - Includes special characters like `%#*^,?+$`;"{}][|\/:=)(@!.-`.
	 * 	 - `alphanumeric` - Contains only alphanumeric characters.
 	 *   - `alphabet` - Contains only alphabetical characters (both uppercase and lowercase).
 	 *   - `password` - Combines letters, numbers, and an expanded set of special characters (`%#^_-@!$&*+=|~?<>[]{}()`).
 	 *   - `bytes` - Returns a raw binary string of the specified length.
 	 *   - `hex` - Returns a hexadecimal representation of random bytes.
 	 *   - `int|integer` - Contains only numeric characters (0-9).
	 *
	 * @example - Examples:
 	 * - `Helpers::random(16, 'password')` - Generates a secure password of 16 characters.
 	 * - `Helpers::random(8, 'alphabet', true)` - Generates an 8-character string in uppercase letters.
 	 * - `Helpers::random(32, 'hex')` - Generates a 32-character hexadecimal string.
	 */
	public static function random(int $length = 10, string $type = 'int', bool $uppercase = false): string 
	{
		if ($type === 'bytes' || $type === 'hex') {
			$key = random_bytes((int) ceil($length / 2));
			return ($type === 'hex') ? bin2hex($key) : substr($key, 0, $length);
		}

		$alphabets = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$integers = '0123456789';
		$special = '%#*^,?+$`;"{}][|\/:=)(@!.-';
		
		$char = match ($type) {
			'character' => $special,
			'alphabet' => $alphabets,
			'alphanumeric' => $alphabets . $integers,
			'password' => $alphabets . $integers . '%#^_-@!$&*+=|~?<>[]{}()',
			'int', 'integer' => $integers,
			default => $alphabets . $integers . $special
		};

		$key = '';
		$len = strlen($char);
		for ($i = 0; $i < $length; $i++) {
			$key .= $char[random_int(0, $len - 1)];
		}

		return ($uppercase && $type !== 'int') ? strtoupper($key) : $key;
	}
	
	/** 
	 * Generate product EAN13 id.
	 * 
	 * @param int $country start prefix country code.
	 * @param int $length maximum length.
	 * 
	 * @return string Return the generated product ean code.
	 */
	public static function ean(int $country = 615, int $length = 13): string 
	{
		return self::upc($country, $length);
	}

	/**
	 * Generate a product UPC ID.
	 *
	 * @param int $prefix Start prefix number.
	 * @param int $length Maximum length.
	 * 
	 * @return string Return the generated UPC ID.
	 */
	public static function upc(int $prefix = 0, int $length = 12): string 
	{
		$length -= strlen((string)$prefix) + 1;
		$randomPart = self::random($length);
		
		$code = $prefix . str_pad($randomPart, $length, '0', STR_PAD_LEFT);
		
		$sum = 0;
		$weightFlag = true;
		
		for ($i = strlen($code) - 1; $i >= 0; $i--) {
			$digit = (int)$code[$i];
			$sum += $weightFlag ? $digit * 3 : $digit;
			$weightFlag = !$weightFlag;
		}
		
		$checksumDigit = (10 - ($sum % 10)) % 10;
		
		return $code . $checksumDigit;
	}
	
	/**
     * Validates a UUID string against a specific version.
     *
     * @param string $uuid The UUID string to check.
     * @param int $version The UUID version to check (default: 4).
	 * 
     * @return bool Return true if the UUID is valid, false otherwise.
     */
    public static function isUuid(string $uuid, int $version = 4): bool 
    {
		if(!$uuid){
			return false;
		}

        $pattern = ($version === 4)
            ? '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i'
            : '/^\{?[0-9a-fA-F]{8}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{4}\-[0-9a-fA-F]{12}\}?$/';

        return (bool) preg_match($pattern, $uuid);
    }

	/**
	 * Checks if string is a valid email address, with optional support for internationalized domains.
	 * 
	 * @param string $email Email address to validate.
	 * @param bool $allow_idn Set to true to allow internationalized domains (default: false).
	 * 
	 * @return bool Returns true if valid email address, false otherwise.
	 */
	public static function isEmail(string $email, bool $allow_idn = false): bool
	{
		if(!$email){
			return false;
		}

		if(filter_var($email, FILTER_VALIDATE_EMAIL) !== false){
			return true;
		}

		if($allow_idn){
			[$local, $domain] = explode('@', $email, 2);
			$domain = idn_to_ascii($domain);

			return $domain 
				? (bool) preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/", "$local@$domain")
				: false;
		}

		return false;
	}

	/**
	 * Checks if the string is a valid URL, with optional support for internationalized domains.
	 *
	 * @param string $url The URL to validate.
	 * @param bool $allowIdn Set to true to allow internationalized domains (default: false).
	 * @param bool $http_only Whether to support urls with `http` and `https` scheme (default: false).
	 *
	 * @return bool Returns true if valid URL, false otherwise.
	 */
	public static function isUrl(string $url, bool $allowIdn = false, bool $http_only = false): bool
	{
		if(!$url || filter_var($url, FILTER_VALIDATE_URL) === false){
			return false;
		}

		if(!$http_only){
			return true;
		}

		if ($allowIdn && ($param = parse_url($url))) {
			$host = isset($param['host']) ? idn_to_ascii($param['host']) : null;
		
			if ($host) {
				$url = (isset($param['scheme']) ? $param['scheme'] . '://' : '')
					. $host
					. (isset($param['port']) ? ':' . $param['port'] : '')
					. (isset($param['path']) ? $param['path'] : '')
					. (isset($param['query']) ? '?' . $param['query'] : '')
					. (isset($param['fragment']) ? '#' . $param['fragment'] : '');
			}
		}
	
		$scheme = $http_only ? '(https?:\/\/)' : '([a-z][a-z0-9+.-]*:\/\/)';

		return (bool) preg_match(
			"/^{$scheme}?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(:[0-9]{1,5})?(\/[a-zA-Z0-9?#&+=.:\/_-]*)?$/i",
			$url
		);
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
     * Determines if the content is likely a binary based on the presence of non-printable characters.
     * 
	 * @param string|resource $data The string or resource to check for binary.
	 * 
     * @return bool Return true if it's a binary, false otherwise.
     */
	public static function isBinary(mixed $data): bool
	{
		if(is_resource($data)){
			$mode = stream_get_meta_data($data)['mode'] ?? null;

			if($mode){
				return str_contains($mode, 'b');
			}

			$data = stream_get_contents($data);
		}
	
		if (!$data || !is_string($data) || trim($data) === '') {
			return false;
		}

		if (strpos($data, "\x00") !== false) {
			return true;
		}

		return preg_match('/[^\x09\x0A\x0D\x20-\x7E]/', substr($data, 0, 512)) === 1;
	}

	/**
	 * Validates if the input is a valid phone number.
	 *
	 * @param string|int $phone The phone number to validate.
	 * @param int $min The minimum allowed length (default: 10).
	 * @param int $max The maximum allowed length (default: 15).
	 *
	 * @return bool Returns true if valid phone number, false otherwise.
	 */
	public static function isPhone(string|int $phone, int $min = 10, int $max = 15): bool
	{
		if (!$phone) {
			return false;
		}

		$phone = is_numeric($phone)
			? (string) $phone
			: preg_replace('/\D+/', '', $phone);

		$length = strlen($phone);

		return $length >= $min && $length <= $max;
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

		$phone = preg_replace("/[^0-9]/", '', $phone);
		$length = strlen($phone);
		$patterns =[
			7 => preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2", $phone),
			10 => preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "($1) $2-$3", $phone),
		];

		return $patterns[$length]??$phone;
	}

	/**
	 * Remove subdomains from a URL and return the main domain name.
	 * 
	 * @param string $url The input URL from which subdomains should be removed.
	 * 
	 * @return string Return the main domain extracted from the URL.
	 */
	public static function mainDomain(string $url): string
	{
		$count = substr_count($url, '.');

		if ($count === 2) {
			$parts = explode('.', $url);

			if (strlen($parts[1]) > 3) {
				$url = explode('.', $url, 2)[1];
			}
		} elseif ($count > 2) {
			$url = self::mainDomain(explode('.', $url, 2)[1]);
		}

		if (!preg_match('~^(?:f|ht)tps?://~i', $url)) {
            $url = 'http://' . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
   
		return  $host ?? $url;
	}

	/**
	 * Remove main domain from a URL and return only the first subdomain name.
	 *
	 * @param string $url The input URL from which the domain should be extracted.
	 * 
	 * @return string Return the extracted domain or an empty string if no domain is found.
	 * 
	 * > Note: `www` is considered as none subdomain.
	 * > And only the first level of subdomain will be returned if the url contains multiple levels of subdomain.
	 */
	public static function subdomain(string $url): string
	{
		$domain = '';

		if (str_contains($url, '.')) {
			$parts = explode('.', $url, 4);

			if (count($parts) >= 3) {
				$domain = ($parts[1] !== 'www') ? $parts[1] : $parts[2];
			}
		}

		return $domain;
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
	 * @param string $type Expected type e.g, `self::SANITIZE_*`, (default: `Helpers::SANITIZE_DEFAULT`).
	 * @param string|null $replacement Replacement for disallowed characters, or null to enforce strict validation.
	 *
	 * @return string|null Returns sanitized string, or null if input cannot be sanitized in replacement mode.
	 * @throws InvalidArgumentException If input does not match expected format and `$replacement` is null.
	 * 
	 * @example - Examples:
	 * ```php
	 * // Safe replacement
	 * $clean = Helpers::sanitize('<b>Hello</b>', 'default'); // 'Hello'
	 * 
	 * // Strict validation
	 * $uuid = Helpers::sanitize('550e8400-e29b-41d4-a716-446655440000', 'uuid', null);
	 * 
	 * // Throws exception if invalid
	 * 
	 * $strictUuid = Helpers::sanitize('invalid-uuid', 'uuid', null); // InvalidArgumentException
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

		$pattern = self::getStrictPatterns($type);

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
     * Converts a hexadecimal string into its binary representation.
     *
     * @param string $hexStr The input string containing hexadecimal data.
     * @param string|null $destination Optional. If specified, saves the binary data to a file.
     *                                 - If it's a `file path`, the binary data is saved directly.
     *                                 - If it's a `directory`, a unique filename is generated.
     *
     * @return string|bool Return the binary string if no destination is provided.
     *                     If a file is written, returns `true` on success, `false` on failure.
	 * @throws RuntimeException Throws if an invalid hex is encountered.
     */
	public static function hexToBinary(string $hexStr, ?string $destination = null): string|bool 
	{
		$binary = '';
		$lines = explode("\n", trim($hexStr));
		
		foreach ($lines as $line) {
			if (preg_match('/:\s*([0-9A-Fa-f\s]+)/', $line, $matches)) {
				$hex = trim(preg_replace('/[^0-9A-Fa-f]/', '', $matches[1]));

				if (!ctype_xdigit($hex)) {
					throw new RuntimeException("Invalid hexadecimal string: {$hex}", RuntimeException::INVALID);
				}				

				if (strlen($hex) % 2 !== 0) {
					$hex = '0' . $hex;
				}

				$bin = hex2bin($hex);
				if ($binary === false) {
					throw new RuntimeException('hexadecimal to binary conversion failed.');
				}

				$binary .= $bin;
			}
		}

		if (!$destination) {
			return $binary;
		}
	
		if (str_ends_with($destination, DIRECTORY_SEPARATOR) || !preg_match('/\.\w+$/', $destination)) {
			$destination = root($destination);
			
			Filesystem::mkdir($destination);

			do {
				$filename = uniqid('bin_', true);
				$filePath = "{$destination}{$filename}";
			} while (file_exists($filePath));

			return Filesystem::write(
				"{$filePath}." . self::getBinaryExtension($binary, $filePath), 
				$binary
			);
		}

		return Filesystem::write($destination, $binary);
	}

	/**
	 * Format text before display by matching links, email, phone, 
	 * hashtags and mentions with a link representation and replace multiple new lines.
	 * 
	 * @param string $text Text to be formatted
	 * @param string $target Link target attribute in HTML anchor name.
	 * 	-	@example [_blank, _self, _top, _window, _parent or frame name]
	 * @param string $blocked Replace blocked word with
	 * @param bool $noHtml Determines whether to remove all HTML tags or only allow certain tags like <p> by default, it's set to true.
	 * 
	 * @return string Return formatted text.
	 */
	public static function normalize(
		string $text, 
		string $target = '_self', 
		?string $blocked = null, 
		bool $noHtml = true
	): string 
	{
		if($text === ''){
			return $text;
		}

		if($noHtml){
			$text = preg_replace('/<([^>]+)>(.*?)<\/\1>|<([^>]+) \/>/', ($blocked ?? ''), $text); 
		}

		// Replace website links
		$text = preg_replace_callback('/(https?:\/\/(?:www\.)?(\S+(?:\.(?:[a-z]+))))/i', function($matches) use($target){
			$target = "target='{$target}'";
			$link = $matches[1];

			if (str_starts_with($link, APP_HOSTNAME)) {
				return '<a href="' . $link . '" ' . $target . '>' . $link . '</a>';
			}

			return '<a href="' . APP_URL . '?redirect=' . urlencode($link) . '" ' . $target . '>' . $link . '</a>';
		}, $text);

		// Replace mentions, excluding email-like patterns
		$text = preg_replace(
			'/@(\w+)(?![@.]|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}\b)/', '<a href="@$1">@$1</a>', 
			$text
		);
			
		// Replace email addresses
		$text = preg_replace(
			'/\b([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4})\b/', '<a href="mailto:$1" title="$1">$1</a>', 
			$text
		);

		// Replace phone numbers
		$text = preg_replace('/\b((\+?\d{11,13})|(\d{11,13}))\b/', '<a href="tel:$1">$1</a>', $text);

		// Replace hashtags
		$text = preg_replace('/#(\w+)/', '<a href="#$1">#$1</a>', $text);

		$text = nl2br($text);

		return $text;
	}

	/**
     * Generates a legacy version 1 or version 2 UUID.
     *
     * @return string The generated UUID string.
     */
	private static function uuidV1V2(): string 
	{
		$data = random_bytes(16);
		return sprintf(
			'%s-%s-%s-%s-%s', 
			bin2hex(substr($data, 0, 4)), 
			bin2hex(substr($data, 4, 2)), 
			bin2hex(chr((ord($data[6]) & 0x0f) | 0x10) . substr($data, 7, 1)), 
			bin2hex(chr((ord($data[8]) & 0x3f) | 0x80) . substr($data, 9, 1)), 
			bin2hex(substr($data, 10, 6))
		);
	}

	/**
     * Generates UUID version 3, 4 or 5.
     *
     * @param string|null $namespace The namespace for the UUID.
     * @param string|null $name The name for the UUID.
     * @param int $version The version of the UUID to generate (3 or 5).
	 * 
     * @return string Return the generated UUID string.
     * @throws InvalidArgumentException If the namespace or name is not provided or if the namespace is invalid.
     */
	private static function uuidV3V4V5(?string $namespace, ?string $name, int $version = 3): string 
	{
		if($version === 4){
			$data = random_bytes(16);
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80);

			return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		}

		if (!$namespace || !$name) {
			throw new InvalidArgumentException("Namespace and name must be provided for version {$version} UUID");
		}
	
		if (!self::isUuid($namespace, $version)) {
			throw new InvalidArgumentException("Invalid namespace UUID provided for version {$version} UUID");
		}
	
		$namespaceBinary = hex2bin(str_replace(['-', '{', '}'], '', $namespace));
		$hash = ($version === 3) 
			? md5($namespaceBinary . $name) 
			: sha1($namespaceBinary . $name);
	
		return sprintf('%08s-%04s-%04x-%02x%02x-%012s',
			substr($hash, 0, 8),
			substr($hash, 8, 4),
			(hexdec(substr($hash, 12, 4)) & 0x0fff) | (($version === 3) ? 0x3000 : 0x5000),
			(hexdec(substr($hash, 16, 2)) & 0x3f) | 0x80,
			hexdec(substr($hash, 18, 2)),
			substr($hash, 20, 12)
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
	private static function getStrictPatterns(string $type): array 
	{
		return match($type){
			 self::SANITIZE_INT => [
				'validate' => "/^[0-9]+$/",
				'replace' => "/[^0-9]+/"
			],
			 self::SANITIZE_NUMERIC, 'digit' => [
				'validate' => "/^-?[0-9]+(\.[0-9]+)?$/",
				'replace' => "/[^-0-9.]+/"
			],
			 self::SANITIZE_KEY => [
				'validate' => "/^[a-zA-Z0-9_-]+$/",
				'replace' => "/[^a-zA-Z0-9_-]+/"
			],
			 self::SANITIZE_PASSWORD => [
				'validate' => "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#^&!*_.$)(-])[A-Za-z\d@#^&!*_.$)(-]{8,128}$/",
				'replace' => false
			],
			self::SANITIZE_USERNAME => [
				'validate' => "/^[a-zA-Z0-9_.-]{3,30}$/", // Minimum 3, maximum 30 characters
				'replace' => false
			],
			self::SANITIZE_EMAIL => [
				'validate' => "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/",
				'replace' => false
			],
			self::SANITIZE_URL => [
				'validate' => "/^([a-z][a-z0-9+.-]*:\/\/)?([a-z0-9-]+\.)+[a-z]{2,}(:[0-9]{1,5})?(\/[a-zA-Z0-9?#&+=.:\/_-]*)?$/i",
				'replace' => false 
			],
			self::SANITIZE_MONEY => [
				'validate' => "/^-?[0-9]+(\.[0-9]{1,2})?$/",
				'replace' => "/[^0-9.-]+/"
			],
			self::SANITIZE_DOUBLE => [
				'validate' => "/^-?[0-9]+(\.[0-9]+)?$/",
				'replace' => "/[^0-9.-]+/"
			],
			self::SANITIZE_ALPHABET => [
				'validate' => "/^[a-zA-Z]+$/",
				'replace' => "/[^a-zA-Z]+/"
			],
			self::SANITIZE_PHONE => [
				'validate' => "/^\+?[0-9-]+$/", // Allow international format with `+`
				'replace' => "/[^0-9-+]+/"
			],
			self::SANITIZE_NAME => [
				'validate' => "/^[\p{L}0-9\s''_.-]+$/u",
				'replace' => "/[^\p{L}0-9\s''_.-]+/u"
			],
			self::SANITIZE_TIMEZONE => [
				'validate' => "/^[a-zA-Z0-9\/,_:+ -]+$/",
				'replace' => "/[^a-zA-Z0-9\/,_:+ -]+/"
			],
			self::SANITIZE_TIME => [
				'validate' => "/^[0-9]{1,2}:[0-9]{2}(:[0-9]{2})?$/",
				'replace' => "/[^0-9:]+/"
			],
			self::SANITIZE_DATE => [
				'validate' => "/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/",
				'replace' => "/[^0-9T:-]+/"
			],
			self::SANITIZE_UUID => [
				'validate' => "/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/",
				'replace' => "/[^0-9a-fA-F-]+/"
			],
			self::SANITIZE_DEFAULT => [
				'validate' => "/^(?!.*<[^>]+>).+$/", // Match anything that doesn't contain HTML tags
				'replace' => "/<[^>]*>.*?<\/[^>]*>/s", // Remove HTML tags with their content
			], 
			default => []
		};
	}
	
	/**
     * Determines the file extension based on the binary data using MIME detection and magic numbers.
     *
     * @param string $binaryData  The raw binary data.
     * @param string $destination The temporary file location for MIME type detection.
     *
     * @return string Return the detected file extension (e.g., 'png', 'jpg', 'zip').
     *                Returns 'bin' if no known extension is found.
     */
	private static function getBinaryExtension(string $binaryData, string $destination): string 
	{
		$destination = "{$destination}-hex";
		$mime = MIME::guess($binaryData);

		if($mime === false && Filesystem::write($destination, $binaryData)){
			$mime = MIME::guess($destination);
			unlink($destination);
		}
		

		$extension = $mime ? (MIME::findExtension($mime) ?: false) : false;

		if ($extension) {
			return $extension;
		}

		foreach (self::$magicNumbers as $signature => $ext) {
			if (strncmp($binaryData, $signature, strlen($signature)) === 0) {
				return $ext;
			}
		}

		return 'bin';
	}
}