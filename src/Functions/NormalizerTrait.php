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

trait NormalizerTrait
{
	/**
	 * Format text before display by matching links, email, phone, hashtags and mentions with a link representation and replace multiple new lines.
	 * 
	 * @param string $text Text to be formatted
	 * @param string $target Link target attribute in HTML anchor name.
	 * 	-	@example [_blank, _self, _top, _window, _parent or frame name]
	 * @param bool $no_html Determines whether to remove all HTML tags or only allow certain tags like <p> by default, it's set to true.
	 * 
	 * @param string $blocked Replace blocked word with
	 * 
	 * @return string $text
	*/
	public static function normalize(
		string $text, 
		string $target = '_self', 
		?string $blocked = null, 
		bool $no_html = true
	): string 
	{
		if(empty( $text )){
			return  $text;
		}

		if($no_html){
			$text = preg_replace('/<([^>]+)>(.*?)<\/\1>|<([^>]+) \/>/', ($blocked ?? ''), $text); 
		}

		// Replace website links
		//$text = preg_replace('/(https?:\/\/(?:www\.)?\S+(?:\.(?:[a-z]+)))/i', '<a href="$1" ' . $target . '>$1</a>', $text);
		$text = preg_replace_callback('/(https?:\/\/(?:www\.)?(\S+(?:\.(?:[a-z]+))))/i', function($matches) use($target){
			$target = "target='{$target}'";
			$link = $matches[1];

			if (strpos($link, APP_HOSTNAME) === 0) {
				return '<a href="' . $link . '" ' . $target . '>' . $link . '</a>';
			} else {
				return '<a href="' . APP_URL . '?redirect=' . urlencode($link) . '" ' . $target . '>' . $link . '</a>';
			}
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
	 * @param bool $upper Whether to make the value uppercase if it's a string.
	 * 
	 * @return string The generated random value.
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
	 * Create a random integer based on minimum and maximum
	 * 
	 * @param int $min Minimun number
	 * @param int $max Maximun number
	 * 
	 * @return string String representation of big integer.
	*/
	public static function bigInteger(int $min, int $max): string 
	{
		$difference = bcadd(bcsub($max,$min),1);
		$rand_percent = bcdiv(mt_rand(), mt_getrandmax(), 8);
		
		return bcadd($min, bcmul($difference, $rand_percent, 8), 0);
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
		return static::upc($country, $length);
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
		$randomPart = static::random($length);
		
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
	* Generates uuid string version 4
	* @return string uuid
	*/

	public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
	
	/** 
	* Checks a valid uuid version 4
	* @param string $uuid 
	* @return bool true or false
	*/
	public static function isUuid( string $uuid ): bool 
	{
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    	return (bool) preg_match($pattern, $uuid);
	}

	/**
	 * Checks if string is a valid email address
	 * 
	 * @param string $email email address to validate
	 * 
	 * @return bool true or false
	 */
	public static function isEmail(string $email): bool
	{
		if(filter_var($email, FILTER_VALIDATE_EMAIL) !== false){
			return true;
		}

		if(preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9\._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/", $email)){
			return true;
		}

		return false;
	}

	/** 
	* Checks if string is a valid phone number
	*
	* @param string|int $phone phone address to validate
	* @param int $min Minimum allowed length.
	*
	* @return bool true or false
	*/
	public static function isPhone(string|int $phone, int $min = 10): bool 
	{
		$phone = is_int($phone) ? (string) $phone : $phone;
		$phone = preg_replace('/\D/', '', $phone);
		$length = strlen($phone);
		
		return ($length >= $min && $length <= 15);
	}

	/** 
	* Formats a phone number as (xxx) xxx-xxxx or xxx-xxxx depending on the length.
	*
	* @param mixed $phone phone address to format
	*
	* @return string 
	*/
	public static function formatPhone(string $phone): string 
	{
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
	 * @return bool return trues if passed otherwise false
	*/
	public static function strength(string $password, int $complexity = 4, int $min = 6, int $max = 50): bool 
	{
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
	 * Sanitize user input to protect against cross-site scripting attacks.
	 *
	 * @param string $string The input string to be sanitized.
	 * @param string $type   The expected data type (e.g., 'int', 'email').
	 * @param string $replacement The symbol to replace disallowed characters with (optional).
	 *
	 * @return string The sanitized string.
	*/
	public static function strictInput(string $string, string $type = 'name', string $replacement = ''): string
	{
		if(empty($string)){
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

		$format = preg_replace($pattern, $replacement, $string);
		$format = trim($format);

		return $format;
	}

	/**
	 * Remove subdomains from a URL and return the main domain name.
	 * 
	 * @param string $url The input URL from which subdomains should be removed.
	 * 
	 * @return string The main domain extracted from the URL.
	 */
	public static function maindomain(string $url): string
	{
		$host = strtolower(trim($url));
		$count = substr_count($host, '.');

		if ($count === 2) {
			$parts = explode('.', $host);

			if (strlen($parts[1]) > 3) {
				$host = explode('.', $host, 2)[1];
			}
		} elseif ($count > 2) {
			$host = static::maindomain(explode('.', $host, 2)[1]);
		}

		return $host;
	}

	/**
	 * Remove main domain from a URL and return only the subdomain name.
	 *
	 * @param string $url The input URL from which the domain should be extracted.
	 * 
	 * @return string The extracted domain or an empty string if no domain is found.
	 */
	public static function subdomain(string $url): string
	{
		$domain = '';

		if (strpos($url, '.') !== false) {
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
	 * @param int $encoding Text encoding type
	 * 
	 * @return string The truncated string.
	 */
	public static function truncate(string $text, int $length = 10, string $encoding = 'UTF-8'): string
	{
		if(empty($text)){
			return $text;
		}

		//$escapedText = htmlspecialchars($text, ENT_QUOTES, $encoding);

		if (mb_strlen($text, $encoding) > $length) {
			return mb_substr($text, 0, $length, $encoding) . '...';
		}

		return $text;
	}

	/** 
	 * Base64 encode string for URL passing.
	 * 
	 * @param string $input String to encode
	 * 
	 * @return string Base64 encoded string
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
	 * @return string Base64 decoded string.
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
	 * @return string Masked email address or null.
	 */
	public static function maskEmail(string $email, string $masker = '*'): string 
	{
		if (empty($email)) {
			return '';
		}

		$parts = explode("@", $email);
		$name = implode('@', array_slice($parts, 0, -1));
		$length = floor(strlen($name) / 2);

		return substr($name, 0, $length) . str_repeat($masker, $length) . "@" . end($parts);
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
		if (empty($string)) {
			return '';
		}

		$length = strlen($string);
		$visibleCount = (int) round($length / 4);

		if ($position === 'right') {
			return substr($string, 0, ($visibleCount * -1)) . str_repeat($masker, $visibleCount);
		}

		$hiddenCount = $length - ($visibleCount * 2);

		if ($position === 'left') {
			return str_repeat($masker, $visibleCount) . substr($string, $visibleCount, $hiddenCount) . substr($string, ($visibleCount * -1), $visibleCount);
		}

		return substr($string, 0, $visibleCount) . str_repeat($masker, $hiddenCount) . substr($string, ($visibleCount * -1), $visibleCount);
	}
}
