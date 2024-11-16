<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Functions;

use \Luminova\Exceptions\InvalidArgumentException;

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
	 * Generate a random string.
	 *
	 * @param int $length The length of the random value.
	 * @param string $type The type of random value (e.g., character, alphabet, int, password).
	 * @param bool $uppercase Whether to make the value uppercase if it's a string.
	 * 
	 * @return string Return the generated random value.
	 */
	public static function random(int $length = 10, string $type = 'int', bool $uppercase = false): string 
	{
		$alphabets = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$integers = '0123456789';
		$mapping = [
			'character' => '%#*^,?+$`;"{}][|\/:=)(@!.',
			'password' => '%#^_-@!'
		];
		$hash = $integers . $alphabets . ($mapping[$type] ?? '-');

		if($type === 'alphabet'){
			$hash = $alphabets;
		}

		if($type === 'int'){
			$hash = $integers;
		}

		$key = '';
		$strLength = strlen($hash);

		for ($i = 0; $i < $length; $i++) {
			$key .= $hash[random_int(0, $strLength - 1)];
		}

		if ($uppercase) {
			return strtoupper($key);
		}

		return $key;
	}

	/** 
	 * Create a random integer based on minimum and maximum.
	 * 
	 * @param int $min The minimum number.
	 * @param int $max The maximin number.
	 * 
	 * @return string String representation of big integer.
	 */
	public static function bigInteger(int $min, int $max): string 
	{
		$difference = (string) bcadd(bcsub((string) $max, (string) $min), '1');
		$rand_percent = (string) bcdiv((string) mt_rand(), (string) mt_getrandmax(), 8);

		return bcadd((string) $min, bcmul($difference, $rand_percent, 8), 0);
	}
	
	/** 
	 * Generate product EAN13 id
	 * 
	 * @param int $country start prefix country code
	 * @param int $length maximum length
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
		$time_low = bin2hex(substr($data, 0, 4));
		$time_mid = bin2hex(substr($data, 4, 2));
		$time_hi_and_version = bin2hex(chr((ord($data[6]) & 0x0f) | 0x10) . substr($data, 7, 1));
		$clock_seq = bin2hex(chr((ord($data[8]) & 0x3f) | 0x80) . substr($data, 9, 1));
		$node = bin2hex(substr($data, 10, 6));
	
		return sprintf('%s-%s-%s-%s-%s', $time_low, $time_mid, $time_hi_and_version, $clock_seq, $node);
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
	 * Checks if string is a valid email address
	 * 
	 * @param string $email email address to validate
	 * 
	 * @return bool Return true if valid email address, false otherwise.
	 */
	public static function isEmail(string $email): bool
	{
		if(!$email){
			return false;
		}

		if(filter_var($email, FILTER_VALIDATE_EMAIL) !== false){
			return true;
		}

		return (bool) preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/", $email);
	}

	/**
	 * Checks if the string is a valid URL.
	 *
	 * @param string $url URL to validate.
	 *
	 * @return bool Return true if valid url, false otherwise.
	 */
	public static function isUrl(string $url): bool
	{
		if(!$url){
			return false;
		}

		if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
			return true;
		}

		return (bool) preg_match("/^(https?:\/\/)?([a-z0-9-]+\.)+[a-z]{2,}(:[0-9]{1,5})?(\/[^\s]*)?$/i", $url);
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
	 * Checks if string is a valid phone number
	 *
	 * @param string|int $phone phone address to validate
	 * @param int $min Minimum allowed length.
	 *
	 * @return bool Return true if valid phone number, false otherwise.
	 */
	public static function isPhone(string|int $phone, int $min = 10): bool 
	{
		if(!$phone){
			return false;
		}

		$phone = is_int($phone) 
			? (string) $phone 
			: preg_replace('/\D/', '', $phone);
		$length = strlen($phone);
		
		return ($length >= $min && $length <= 15);
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
	 * @return bool Return trues if passed otherwise false
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
	 * @param string $string The input string to be sanitized.
	 * @param string $type The expected data type (e.g., 'int', 'email', 'username').
	 * @param string|null $replacement The symbol to replace disallowed characters or null to throw and exception (default: '').
	 *
	 * @return string Return the sanitized string.
	 * @throws InvalidArgumentException If the input does not match the expected type and no replacement is provided.
	 * 
	 * Available types:
	 * - 'int'       : Only numeric characters (0-9) are allowed.
	 * - 'digit'     : Numeric characters, including negative numbers and decimals.
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
	 */
	public static function strictType(
		string $string, 
		string $type = 'default', 
		string|null $replacement = ''
	): string
	{
		if($string === ''){
			return $string;
		}
		
		$patterns = [
			'int' => "/[^0-9]+/",
			'digit' => "/[^-0-9.]+/",
			'key' => "/[^a-zA-Z0-9_-]/",
			'password' => "/[^a-zA-Z0-9-@!*_]/",
			'username' => "/[^a-zA-Z0-9-_.]+/",
			'email' => "/[^a-zA-Z0-9-@-_.]+/",
			'url' => "/[^a-zA-Z0-9?#&+=.:\/ -]+/",
			'money' => "/[^0-9.-]+/",
			'double' => "/[^0-9.]+/",
			'alphabet' => "/[^a-zA-Z]+/",
			'phone' => "/[^0-9-+]+/",
			'name' => "/[^\p{L}0-9\s'’_.-]+/u",
			'timezone' => "/[^a-zA-Z0-9-\/,_:+ ]+/",
			'time' => "/[^a-zA-Z0-9-: ]+/",
			'date' => "/[^a-zA-Z0-9-:\/,_ ]+/",
			'uuid' => "/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/",
			'default' => "/<[^>]+>/",
		];
		
		$pattern = $patterns[$type] ?? $patterns['default'];
		if($replacement === null){
			if (!preg_match($pattern, $string)) {
				throw new InvalidArgumentException(
					"String does not match the required format for type: $type.",
					InvalidArgumentException::STRICT_NOTICE
				);
			}

			return $string;
		}

		$format = preg_replace($pattern, $replacement, $string);
		return trim($format);
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
	public static function base64_url_encode(string $input): string 
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
	public static function base64_url_decode(string $input): string
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
}