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
namespace Luminova\Functions;

use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Storages\FileManager;
use \Luminova\Base\BaseConfig;

class Func
{
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
		if($text  === ''){
			return  $text;
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
		$text = preg_replace('/@(\w+)(?![@.]|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}\b)/', '<a href="@$1">@$1</a>', $text);
			
		// Replace email addresses
		$text = preg_replace('/\b([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4})\b/', '<a href="mailto:$1" title="$1">$1</a>', $text);

		// Replace phone numbers
		$text = preg_replace('/\b((\+?\d{11,13})|(\d{11,13}))\b/', '<a href="tel:$1">$1</a>', $text);

		// Replace hashtags
		$text = preg_replace('/#(\w+)/', '<a href="#$1">#$1</a>', $text);

		$text = nl2br($text);

		return $text;
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
	 * @example Examples:
 	 * - `Func::random(16, 'password')` - Generates a secure password of 16 characters.
 	 * - `Func::random(8, 'alphabet', true)` - Generates an 8-character string in uppercase letters.
 	 * - `Func::random(32, 'hex')` - Generates a 32-character hexadecimal string.
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
	 * Generate a random BIGINT within a specified range.
	 * 
	 * UNSIGNED BIGINT: 0 to 18,446,744,073,709,551,615 (20 digits):
	 * $min = 0
	 * $max = 18446744073709551615
	 * 
	 * SIGNED BIGINT: -9,223,372,036,854,775,808 to 9,223,372,036,854,775,807 (19 digits):
	 * $min = -9223372036854775808
	 * $max = 9223372036854775807
	 *
	 * @param int|null $min The minimum value (default: 0).
	 * @param int|null $max The maximum value (default: 18446744073709551615).
	 *
	 * @return string Return a string representation of the generated BIGINT.
	 */
	public static function bigInteger(?string $min = null, ?string $max = null): string 
	{
		$min ??= '0';
		$max ??= '18446744073709551615';
		do {
			$random = bcadd(
				$min,
				bcmul(
					bcadd(bcsub($max, $min), '1'),
					bcdiv((string) mt_rand(0, mt_getrandmax()), (string) mt_getrandmax(), 8),
					0
				),
				0
			);
		} while (bccomp($random, $min) < 0 || bccomp($random, $max) > 0);
	
		return $random;
	}
	
	/** 
	 * Generate product EAN13 id.
	 * 
	 * @param int $country start prefix country code.
	 * @param int $length maximum length.
	 * 
	 * @return string Product ean code.
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
	 * @return string The generated UPC ID.
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
		return match($version) {
			1, 2 => self::uuid1Or2(),
			3, 5 => self::uuid3Or5($namespace, $name, $version),
			/**
			 * Generates a version 4 UUID.
			 *
			 * @return string The generated UUID string.
			 */
			default => (function(): string {
				$data = random_bytes(16);
				$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
				$data[8] = chr(ord($data[8]) & 0x3f | 0x80);

				return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
			})(),
		};
    }

	/**
     * Generates a version 1 or version 2 UUID.
     *
     * @return string The generated UUID string.
     */
	private static function uuid1Or2(): string 
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
     * Generates a version 3 or version 5 UUID.
     *
     * @param string|null $namespace The namespace for the UUID.
     * @param string|null $name The name for the UUID.
     * @param int $version The version of the UUID to generate (3 or 5).
	 * 
     * @return string Return the generated UUID string.
     * @throws InvalidArgumentException If the namespace or name is not provided or if the namespace is invalid.
     */
	private static function uuid3Or5(string|null $namespace, string|null $name, int $version = 3): string 
	{
		if (!$namespace || !$name) {
			throw new InvalidArgumentException("Namespace and name must be provided for version {$version} UUID");
		}
	
		if (!self::isUuid($namespace, $version)) {
			throw new InvalidArgumentException("Invalid namespace UUID provided for version {$version} UUID");
		}
	
		$namespaceBinary = hex2bin(str_replace(['-', '{', '}'], '', $namespace));
		$hash = ($version === 3) ? md5($namespaceBinary . $name) : sha1($namespaceBinary . $name);
	
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
	 * @param bool $allow_idn Set to true to allow internationalized domains (default: false).
	 * @param bool $http_only Weather to support urls with `http` and `https` scheme (default: false).
	 *
	 * @return bool Returns true if valid URL, false otherwise.
	 */
	public static function isUrl(string $url, bool $allow_idn = false, bool $http_only = false): bool
	{
		if(!$url || filter_var($url, FILTER_VALIDATE_URL) === false){
			return false;
		}

		if(!$http_only){
			return true;
		}

		if ($allow_idn && ($param = parse_url($url))) {
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
     * Determines if a given string is likely to be Base64-encoded.
     *
     * @param string $data The string to check for Base64 encoding.
     *
     * @return bool Returns true if the string is likely to be Base64-encoded, false otherwise.
     */
    public static function isBase64Encoded(string $data): bool
    {
        return $data 
			? (bool) preg_match('/^[a-zA-Z0-9\/+\r\n]+={0,2}$/', $data) && strlen($data) % 4 === 0
			: false;
    }

	/**
     * Determines if the content string is likely a binary based on the presence of non-printable characters.
     * 
	 * @param string $data The string to check for binary.
	 * 
     * @return bool Return true if it's a binary, false otherwise.
     */
    public static function isBinary(string $data): bool
    {
        return $data 
			? (bool) preg_match('/[^\x20-\x7E\t\r\n]/', $data)
			: false;
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
	 * Determine if password strength matches the basic strength recommendation.
	 * 
	 * @param string $password password to check.
	 * @param int $complexity maximum complexity pass count max(4) means password must contain (numbers, uppercase, lowercase and pecial characters).
	 * @param int $min minimum allowed password length (default: 6).
	 * @param int $max maximum allowed password length (default: 50).
	 * 
	 * @return bool Return trues if passed otherwise false.
	 */
	public static function strength(string $password, int $complexity = 4, int $min = 6, int $max = 50): bool 
	{
		if(!$password){
			return false;
		}

		$length = strlen($password);

		if ($length < $min || $length > $max) {
			return false;
		}

		$patterns = [
			'/\d/',          // Contains numbers
			'/[A-Z]/',       // Contains uppercase letters
			'/[a-z]/',       // Contains lowercase letters
			'/[^a-zA-Z\d]/', // Contains special characters
		];

		$passed = 0;

		foreach ($patterns as $pattern) {
			if (preg_match($pattern, $password)) {
				$passed++;
			}
		}

		return ($passed >= min($complexity, 4));
	}

	/**
	 * Strictly sanitizes user input to protect against invalid characters and ensure it conforms to the expected type.
 	 *
	 * @param string $value The input string value to be sanitized.
	 * @param string $type The expected data type (e.g., 'int', 'email', 'username').
	 * @param string|null $replacement The symbol to replace disallowed characters or null to throw and exception (default: '').
	 *
	 * @return string|null Return the sanitized string or null if input doesn't match 
	 * 			nor support replacing like `email` `url` `username` or `password`.
	 * @throws InvalidArgumentException If the input contains invalid characters, or HTML tags, and no replacement is provided.
	 * 
	 * Available types:
	 * - 'int'       : Only numeric characters (0-9) are allowed.
	 * - 'numeric'   : Numeric characters, including negative numbers and decimals.
	 * - 'key'       : Alphanumeric characters, underscores, and hyphens.
	 * - 'password'  : Alphanumeric characters, and special characters (@, *, !, _, -).
	 * - 'username'  : Alphanumeric characters, hyphen, underscore, and dot.
	 * - 'email'     : Alphanumeric characters and characters allowed in email addresses.
	 * - 'url'       : Valid URL characters (alphanumeric, ?, #, &, +, =, . , : , /, -).
	 * - 'money'     : Numeric characters, including decimal and negative values.
	 * - 'double'    : Floating point numbers (numeric and decimal points).
	 * - 'alphabet'  : Only alphabetic characters (a-z, A-Z).
	 * - 'phone'     : Numeric characters, plus sign, and hyphen (e.g., phone numbers).
	 * - 'name'      : Unicode characters, spaces, and common name symbols (e.g., apostrophe).
	 * - 'timezone'  : Alphanumeric characters, hyphen, slash, and colon (e.g., timezone names).
	 * - 'time'      : Alphanumeric characters and colon (e.g., time format).
	 * - 'date'      : Alphanumeric characters, hyphen, slash, comma, and space (e.g., date format).
	 * - 'uuid'      : A valid UUID format (e.g., 8-4-4-4-12 hexadecimal characters).
	 * - 'default'   : Removes HTML tags.
	 * 
	 * > **Note:** 
	 * > - HTML tags (including their content) are completely removed for the 'default' type.
	 * > - This method ensures secure handling of input to prevent invalid characters or unsafe content.
	 */
	public static function strictType(
		string $value,
		string $type = 'default',
		string|null $replacement = ''
	): ?string {

		if ($value === '') {
			return $value;
		}

		$pattern = self::getStrictPatterns($type);
		$html = '/<[^>]*>.*?<\/[^>]*>/s';

		if ($replacement === null) {
			if (!preg_match($pattern['validate'], $value) || ($type !== 'default' && preg_match($html, $value))
			) {
				throw new InvalidArgumentException(
					"String does not match the required format for type: $type."
				);
			}

			return $value;
		}

		if($type === 'int' && is_int($value) && (int) $value >= 0){
			return $value;
		}

		if(($type === 'numeric' || $type === 'digit') && is_numeric($value)){
			return $value;
		}

		if($pattern['replace'] === false){
			return preg_match($pattern['validate'], $value) ? $value : null;
		}
	
		// Replace all HTML tags first
		$value = ($type === 'default') 
			? $value 
			: preg_replace($html, $replacement, $value);

		if($value !== null){
			$format = preg_replace($pattern['replace'], $replacement, $value);
			return trim($format);
		}

		return null;
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
	 * Truncate an input with the specified length and adds an ellipsis at the end if the text is longer than the specified length.
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
			return mb_substr($text, 0, $length, $encoding) . '...';
		}

		return $text;
	}

	/** 
	 * Base64 encode string for URL passing.
	 * 
	 * @param string $input String to encode.
	 * 
	 * @return string Return base64 encoded string.
	 */
	public static function base64UrlEncode(string $input): string 
	{
		return str_replace(['+', '/', '='], ['.', '_', '-'], base64_encode($input));
	}

	/** 
	 * Base64 decode URL base64 encoded string.
	 * 
	 * @param string $input Encoded string to decode
	 * 
	 * @return string Return base64 decoded string.
	 */
	public static function base64UrlDecode(string $input): string
	{
		return base64_decode(str_replace(['.', '_', '-'], ['+', '/', '='], $input));
	}

	/**
	 * Mask email address.
	 *
	 * @param string $email Email address to mask.
	 * @param string $masker  Mask character (default is "*").
	 * 
	 * @return string Return masked email address.
	 */
	public static function maskEmail(string $email, string $masker = '*'): string 
	{
		if ($email === '') {
			return '';
		}

		$parts = explode("@", $email);
		$name = implode('@', array_slice($parts, 0, -1));
		$length = floor(strlen($name) / 2);

		return substr($name, 0, (int) $length) . str_repeat($masker, (int) $length) . "@" . end($parts);
	}

	/**
	 * Mask a string by position.
	 *
	 * @param string $string  String to mask.
	 * @param string $masker  Mask character (default is "*").
	 * @param string $position  The position of the string to mask ("center", "left", or "right").
	 * 
	 * @return string Return masked string.
	 */
	public static function mask(string $string, string $masker = '*', string $position = 'center'): string 
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
			'int' => [
				'validate' => "/^[0-9]+$/",
				'replace' => "/[^0-9]+/"
			],
			'digit', 'numeric' => [
				'validate' => "/^-?[0-9]+(\.[0-9]+)?$/",
				'replace' => "/[^-0-9.]+/"
			],
			'key' => [
				'validate' => "/^[a-zA-Z0-9_-]+$/",
				'replace' => "/[^a-zA-Z0-9_-]+/"
			],
			'password' => [
				'validate' => "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#^&!*_.$)(-])[A-Za-z\d@#^&!*_.$)(-]{8,128}$/",
				'replace' => false
			],
			'username' => [
				'validate' => "/^[a-zA-Z0-9_.-]{3,30}$/", // Minimum 3, maximum 30 characters
				'replace' => false
			],
			'email' => [
				'validate' => "/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9-]+\.[a-zA-Z]{2,}$/",
				'replace' => false
			],
			'url' => [
				'validate' => "/^([a-z][a-z0-9+.-]*:\/\/)?([a-z0-9-]+\.)+[a-z]{2,}(:[0-9]{1,5})?(\/[a-zA-Z0-9?#&+=.:\/_-]*)?$/i",
				'replace' => false 
			],
			'money' => [
				'validate' => "/^-?[0-9]+(\.[0-9]{1,2})?$/",
				'replace' => "/[^0-9.-]+/"
			],
			'double' => [
				'validate' => "/^-?[0-9]+(\.[0-9]+)?$/",
				'replace' => "/[^0-9.-]+/"
			],
			'alphabet' => [
				'validate' => "/^[a-zA-Z]+$/",
				'replace' => "/[^a-zA-Z]+/"
			],
			'phone' => [
				'validate' => "/^\+?[0-9-]+$/", // Allow international format with `+`
				'replace' => "/[^0-9-+]+/"
			],
			'name' => [
				'validate' => "/^[\p{L}0-9\s''_.-]+$/u",
				'replace' => "/[^\p{L}0-9\s''_.-]+/u"
			],
			'timezone' => [
				'validate' => "/^[a-zA-Z0-9\/,_:+ -]+$/",
				'replace' => "/[^a-zA-Z0-9\/,_:+ -]+/"
			],
			'time' => [
				'validate' => "/^[0-9]{1,2}:[0-9]{2}(:[0-9]{2})?$/",
				'replace' => "/[^0-9:]+/"
			],
			'date' => [
				'validate' => "/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?$/",
				'replace' => "/[^0-9T:-]+/"
			],
			'uuid' => [
				'validate' => "/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/",
				'replace' => "/[^0-9a-fA-F-]+/"
			],
			default => [
				'validate' => "/^(?!.*<[^>]+>).+$/", // Match anything that doesn't contain HTML tags
				'replace' => "/<[^>]*>.*?<\/[^>]*>/s", // Remove HTML tags with their content
			]
		};
	}

	/**
     * Converts a hexadecimal string into its binary representation.
     *
     * @param string      $hexStr      The input string containing hexadecimal data.
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
			
			FileManager::mkdir($destination);

			do {
				$filename = uniqid('bin_', true);
				$filePath = "{$destination}{$filename}";
			} while (file_exists($filePath));

			return FileManager::write(
				"{$filePath}." . self::getBinaryExtension($binary, $filePath), 
				$binary
			);
		}

		return FileManager::write($destination, $binary);
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
		if(FileManager::write($destination, $binaryData)){
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mimeType = finfo_file($finfo, $destination);
			finfo_close($finfo);
			unlink($destination);

			$extension = BaseConfig::getExtension($mimeType);

			if ($extension) {
				return $extension;
			}
		}

		$magicNumbers = [
			"\x89PNG\r\n\x1A\n" => 'png',
			"\xFF\xD8\xFF" => 'jpg',
			"\x25\x50\x44\x46" => 'pdf',
			"\x50\x4B\x03\x04" => 'zip',
			"\x49\x44\x33" => 'mp3',
			"\x47\x49\x46\x38" => 'gif',
			"\xD0\xCF\x11\xE0" => 'doc', 
			"\x50\x4B\x03\x04" => 'docx', 
			"\x50\x4B\x07\x08" => 'xlsx',
			"\x52\x49\x46\x46" => 'wav',
			"\x00\x00\x01\xBA" => 'mpg',
			"\x00\x00\x01\xB3" => 'mpg',
			"\x1A\x45\xDF\xA3" => 'mkv'
		];

		foreach ($magicNumbers as $signature => $ext) {
			if (strncmp($binaryData, $signature, strlen($signature)) === 0) {
				return $ext;
			}
		}

		return 'bin';
	}
}