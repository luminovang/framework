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

use \Luminova\Time\Time;

trait StringTrait
{
	public const INT = "int";
	public const CHAR = "char";
	public const STR = "str";
	public const SALT = "salt";
	public const SID = "sid";
	public const UUI = "uui";
	public const PASS = "pass";
	
	private const DEFAULT_RULES = [
		"[br/]" =>  "&#13;&#10;",
		"\r\n"      => "\n",
		"\n\r"      => "\n",
		"\r"      => "\n",
		"\n"      => "\n",
		"<br/>"    => "\n",
		"<b>"    => "**",
		"</b>"    => "**",
		"<img "    => "<data-img",
		"</img>"    => "</data-img>",
		"<script>"    => "",
		"</script>"    => "",
		"<style>"    => "",
		"</style>"    => "",
		"alert("    => "data-alert(",
		"onclick("    => "data-onclick(",
		"onload("    => "data-onload(",
		"javascript:"    => "data-javascript:",
		"<a "    => "<data-a ",
		"</a>"    => "</data-a>",
	];


	/**
	 * Format input to person name
	 * 
	 * @param string $input input string
	 * @param string $replacement replacement unwanted string
	 * 
	 * @return string $format
	*/
	public static function toName(string $input, string $replacement = ''): string
	{
		$format = preg_replace("/[^a-zA-Z0-9-_. ]+/", $replacement, $input);

		return $format;
	}

	/**
	 * Check if variable matches any of the array values
	 * 
	 * @param string $needle input string
	 * @param array $haystack The array to search in
	 * 
	 * @return bool true or false
	*/
	public static function matchIn(string $needle, array $haystack): bool 
	{
		foreach ($haystack as $item) {
			if (stripos($needle, $item) !== false) {
			  return true;
			}
		}
		return false;
	}

	/**
	 * Check if variable matches any of the array values
	 * 
	 * @param string $needle input string
	 * @param array $haystack The array to search in
	 * 
	 * @deprecated this method is deprecated and will be removed in future use matchIn() method instead
	 * @return bool true or false
	*/
  	public static function isNameBanned(mixed $nameToCheck, array $bannedNames): bool 
	{
      	return self::matchIn($nameToCheck, $bannedNames);
  	}

	/**
	 * Format text before display or saving 
	 * By matching links, email, phone, hashtags and mentions with a link representation
	 * And replace multiple new lines
	 * 
	 * @param string $text
	 * @param string $target link target action
	 * @param string $blocked Replace blocked word with
	 * 
	 * @return string $text
	*/
	public static function sanitizeText(string $text, ?string $target = null, ?string $blocked = null): string 
	{
		$blockedContent = $blocked || '<i style="color:darkred;">⚠️ Content Blocked</i>';

		$targetTo = ($target !== null ? "target='{$target}'" : '');

		$text = preg_replace('/<([^>]+)>(.*?)<\/\1>|<([^>]+) \/>/', $blockedContent, $text); 

		// Replace website links
		$text = preg_replace('/(https?:\/\/(?:www\.)?\S+(?:\.(?:[a-z]+)))/i', '<a href="$1" ' . $targetTo . '>$1</a>', $text);

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
	 * Filter and sanitize text before saving to database 
	 * 
	 * @param string $text
	 * @param bool $all
	 * 
	 * @return string $text
	*/
	public static function filterText(string $text, bool $all = true): string 
	{
		$pattern = ($all ? '/<[^>]+>/' : '/<(?!\/?b(?=>|\s.*>))[^>]+>/');
		$text = preg_replace('/<([^>]+)>(.*?)<\/\1>|<([^>]+) \/>/', '⚠️', $text); 
		$text = preg_replace($pattern, '', $text) ;
		$text = htmlentities($text);

		return $text;
	}

	/**
	 * Generate a random value.
	 *
	 * @param int $length The length of the random value.
	 * @param string $type The type of random value (e.g., self::INT, self::CHAR, self::STR).
	 * @param bool $upper Whether to make the value uppercase if it's a string.
	 * 
	 * @return string The generated random value.
	 */
	public static function random(int $length = 10, string $type = 'int', bool $upper = false): string 
	{
		$char = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$int = '0123456789';
	
		$hash = match ($type) {
			'char' => $char,
			'str' => $int . $char,
			'salt' => $int . $char . '%#*^,?+$`;"{}][|\/:=)(@!.',
			'pass', 'password' => $int . $char . '%#^_-@!',
			'sid' => $int . $char . '-',
			default => $int
		};
	
		$strLength = strlen($hash);
		$key = '';
	
		for ($i = 0; $i < $length; $i++) {
			$key .= $hash[random_int(0, $strLength - 1)];
		}
	
		return $upper ? strtoupper($key) : $key;
	}	


	/** 
	 * Create a random integer based on minimum and maximum
	 * @param int $min number
	 * @param int $max number
	 * 
	 * @return string 
	*/
	public static function bigInteger(int $min, int $max): string 
	{
		$difference   = bcadd(bcsub($max,$min),1);
		$rand_percent = bcdiv(mt_rand(), mt_getrandmax(), 8);
		
		return bcadd($min, bcmul($difference, $rand_percent, 8), 0);
	}
	
	/** 
	 * Generate product EAN13 id
	 * 
	 * @param int $country start prefix country code
	 * @param int $length maximum length
	 * 
	 * @return string 
	*/
	public static function EAN(int $country = 615, int $length = 13): string 
	{
		return self::UPC($country, $length);
	}

	/**
	 * Generate a product UPC ID.
	 *
	 * @param int $prefix Start prefix number.
	 * @param int $length Maximum length.
	 * 
	 * @return string The generated UPC ID.
	 */
	public static function UPC(int $prefix = 0, int $length = 12): string 
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
	 * Converts a PHP timestamp to a social media-style time format (e.g., "2 hours ago").
	 *
	 * @param string|int $time The timestamp to convert.
	 * 
	 * @deprecated This method is deprecated and will be removed use Time::ago($time) instead
	 * @return string Time in a human-readable format.
	 */
	public static function timeSocial(string|int $time): string 
	{
		return Time::ago($time);
	}

	/**
	 * Check if a certain amount of minutes has passed since the given timestamp.
	 *
	 * @param int|string $timestamp Either a Unix timestamp or a string representing a date/time.
	 * @param int $minutes The number of minutes to check against.
	 *
	 * @deprecated This method is deprecated and will be removed use Time::passed($timestamp, int $minutes) instead
	 * @return bool True if the specified minutes have passed, false otherwise.
	 */
	public static function timeHasPassed(int|string $timestamp, int $minutes): bool 
	{
		return Time::passed($timestamp, $minutes);
	}

	/**
	 * Get the suffix for a given day (e.g., 1st, 2nd, 3rd, 4th).
	 *
	 * @param int $day The day for which to determine the suffix.
	 * 
	 * @deprecated This method is deprecated and will be removed use Time::daySuffix($day) instead
	 * @return string The day with its appropriate suffix.
	 */
	public static function daysSuffix(int $day): string 
	{
		return Time::daySuffix($day);
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
	public static function is_uuid( string $uuid ): bool 
	{
		$pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
    	return (bool) preg_match($pattern, $uuid);
	}

	/** 
	* Has uuid using sha256
	*
	* @param string $uuid 
	* @deprecated This method is deprecated and will be removed 

	* @return string
	*/
	public function uuidToKey(string $uuid): string 
	{
		return hash('sha256', $uuid);
	}

	/**
	 * Checks if string is a valid email address
	 * 
	 * @param string $email email address to validate
	 * 
	 * @return bool true or false
	 */
	public static function is_email(string $email): bool
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
	* @param mixed $phone phone address to validate
	*
	* @return bool true or false
	*/
	public static function is_phone(string|int $phone): bool 
	{
		// Remove any non-digit characters
		$phone = preg_replace('/\D/', '', $phone);
	
		// Check if the phone number is numeric and has a valid length
		if (is_numeric($phone) && (strlen($phone) >= 10 && strlen($phone) <= 15)) {
			return true;
		}

		return false;
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
	
		return match (strlen($phone)) {
			7 => preg_replace("/([0-9]{3})([0-9]{4})/", "$1-$2", $phone),
			10 => preg_replace("/([0-9]{3})([0-9]{3})([0-9]{4})/", "($1) $2-$3", $phone),
			default => $phone,
		};
	}
	

	/**
	 * Checks if string is a valid email address or phone number
	 * 
	 * @param string $input email address or phone number to validate
	 * 
	 * @deprecated this method is deprecated and will be removed in future 
	 * @return bool true or false
	 */
	public static function is_email_or_phone(string $input): bool
	{
		if (self::is_email($input) || self::is_phone($input)) {
			return true;
		}
		
		return false;
	}

	/** 
	* Checks if string is a valid phone number
	*
	* @param mixed $phone phone address to validate
	*
	* @deprecated this method is deprecated and will be removed in future use is_phone() instead
	* @return bool true or false
	*/
	public static function isPhoneNumber(mixed $phone): bool 
	{
		return self::is_phone($phone);
	}

	/** 
	 * Determine password strength, if it meet all rules
	 * @param string $password password to check
	 * @param int $minLength minimum allowed password length
	 * @param int $maxLength maximum allowed password length
	 * @param int $complexity maximum complexity pass count
	 * @return boolean 
	*/
	public static function strongPassword(string $password, int $minLength = 8, int $maxLength = 16, int $complexity=4): bool 
	{
	    $passed = 0;
	    if (strlen($password) < $minLength) {
			return false;
	    }
	    // Does string contain numbers?
	    if(preg_match("/\d/", $password) > 0) {
			$passed++;
	    }
		// Does string contain big letters?
	    if(preg_match("/[A-Z]/", $password) > 0) {
			$passed++;
	    }
		// Does string contain small letters?
	    if(preg_match("/[a-z]/", $password) > 0) {
			$passed++;
	    }
	    // Does string contain special characters?
	    if(preg_match("/[^a-zA-Z\d]/", $password) > 0) {
			$passed++;
	    }
		return ($passed >= ($complexity > 4 ? 4 : $complexity));
	}

	/** 
	* Hash password string to create a hash value
	*
	* @param string $password password string
	* @param int $cost 
	*
	* @return string 
	*/
	public static function hashPassword(string $password, int $cost = 12): string 
	{
		return password_hash($password, PASSWORD_BCRYPT, [
			'cost' => $cost
		]);
	}
	
	/** 
	* Verify a password hash and verify if it match
	*
	* @param string $password password string
	* @param string $hash password hash
	*
	* @return bool true or false
	*/
	public static function verifyPassword(string $password, string $hash): bool 
	{
		return password_verify($password, $hash);
	}


	/**
	 * Get a list of time hours in 12-hour format with 30-minute intervals.
	 *
	 * @deprecated This method is deprecated and will be removed, use Time::hours($interval) instead
	 * 
	 * @return array An array of time hours.
	 */
	public static function hoursRange(): array 
	{
		return Time::hours();
	}


	/**
	 * Get an array of dates for each day in a specific month.
	 *
	 * @param int $month The month (1-12).
	 * @param int $year The year.
	 * @param string $dateFormat The format for the returned dates (default is "d-M-Y").
	 * 
	 * @deprecated This method is deprecated and will be removed, use Time::days($month, $year, $format) instead
	 * @return array An array of dates within the specified month.
	 */
	public static function daysInMonth(int $month = 0, int $year = 0, string $dateFormat = "d-M-Y"): array 
	{
		return Time::days($month, $year, $dateFormat);
	}


	/**
	 * Sanitize user input to protect against cross-site scripting attacks.
	 *
	 * @param string $string The input string to be sanitized.
	 * @param string $type   The expected data type (e.g., 'int', 'email').
	 * @param string $symbol The symbol to replace disallowed characters with (optional).
	 *
	 * @return string The sanitized string.
	 */
	public static function sanitizeInput(string $string, string $type = "name", string $symbol = ""): string
	{
		$patterns = [
			'int' => "/[^0-9]+/",
			'digit' => "/[^-0-9.]+/",
			'key' => "/[^a-zA-Z0-9_-]/",
			'pass' => "/[^a-zA-Z0-9-@!*_]/",
			'username' => "/[^a-zA-Z0-9-_.]+/",
			'email' => "/[^a-zA-Z0-9-@.]+/",
			'url' => "/[^a-zA-Z0-9?&+=.:\/ -]+/",
			'money' => "/[^0-9.-]+/",
			'double' => "/[^0-9.]+/",
			'float' => "/[^0-9.]+/",
			'az' => "/[^a-zA-Z]+/",
			'tel' => "/[^0-9-+]+/",
			'text' => "/[^a-zA-Z0-9-_.,!}{;: ?@#%&]+/",
			'name' => "/[^a-zA-Z., ]+/",
			'timezone' => "/[^a-zA-Z0-9-\/,_:+ ]+/",
			'time' => "/[^a-zA-Z0-9-: ]+/",
			'date' => "/[^a-zA-Z0-9-:\/,_ ]+/",
			'escape' => null, 
			'default' => "/[^a-zA-Z0-9-@.,]+/",
		];
		
		$pattern = $patterns[$type] ?? $patterns['default'];

		if ($pattern === null) {
			return escape($string, 'html', 'UTF-8');
		}

		return preg_replace($pattern, $symbol, $string);
	}

	/**
	 * Convert string characters to HTML entities with optional encoding.
	 *
	 * @param string $str The input string to be converted.
	 * @param string $encode Encoding
	 * 
	 * @return string The formatted string with HTML entities.
	 */
	public static function toHtmlentities(string $str, string $encode = 'UTF-8'): string
	{
		return escape($str, 'html', $encode);
	}

	/**
	 * Remove subdomains from a URL.
	 * 
	 * @param string $url The input URL from which subdomains should be removed.
	 * 
	 * @return string The main domain extracted from the URL.
	 */
	public static function removeSubdomain(string $url): string
	{
		$host = strtolower(trim($url));
		$count = substr_count($host, '.');

		if ($count === 2) {
			if (strlen(explode('.', $host)[1]) > 3) {
				$host = explode('.', $host, 2)[1];
			}
		} elseif ($count > 2) {
			$host = self::removeSubdomain(explode('.', $host, 2)[1]);
		}

		return $host;
	}


	/**
	 * Remove main domain from a URL.
	 *
	 * @param string $url The input URL from which the domain should be extracted.
	 * @return string The extracted domain or an empty string if no domain is found.
	 */
	public static function removeMainDomain(string $url): string
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
	 * Convert a string to kebab case.
	 *
	 * @param string $string The input string to convert.
	 * 
	 * @return string The kebab-cased string.
	 */
	public static function toKebabCase(string $string): string
	{
		$string = preg_replace('/[^a-zA-Z0-9]+/', ' ', $string);
		$kebabCase = str_replace(' ', '-', $string);

		return strtolower($kebabCase);
	}

	/**
	 * Truncate a text string and add an ellipsis if it exceeds a specified length.
	 *
	 * This function truncates the input text to the specified length and adds an ellipsis
	 * at the end if the text is longer than the specified length.
	 *
	 * @param string $text The string to truncate.
	 * @param int $length The length to display before truncating.
	 * @param int $encoding Text encoding type
	 * @return string The truncated string.
	 */
	public static function truncate(string $text, int $length = 10, string $encoding = 'UTF-8'): string
	{
		$escapedText = htmlspecialchars($text, ENT_QUOTES, $encoding);

		// Check if the text length exceeds the specified length
		if (mb_strlen($escapedText, $encoding) > $length) {
			// Truncate the text and add an ellipsis
			return mb_substr($escapedText, 0, $length, $encoding) . '...';
		}

		// Return the original text if it doesn't need truncation
		return $escapedText;
	}

	/** 
	 * Base64 encode string for URL passing
	 * @param string $input String to encode
	 * @return string Base64 encoded string
	 */
	public static function base64_url_encode(string $input): string 
	{
		return str_replace(['+', '/', '='], ['.', '_', '-'], base64_encode($input));
	}

	/** 
	 * Base64 decode URL-encoded string
	 * @param string $input Encoded string to decode
	 * @return string Base64 decoded string
	 */
	public static function base64_url_decode(string $input): string
	{
		return base64_decode(str_replace(['.', '_', '-'], ['+', '/', '='], $input));
	}

	/**
	 * Strip unwanted characters from a string.
	 *
	 * @param string $string The input string to clean.
	 * @param array $rules An array of rules to replace.
	 * @param bool $textarea If true, strictly remove all markdown if displaying on a webpage, else format with new lines inside a textarea.
	 * @return string The cleaned text.
	 */
	public static function stripText(string $string, array $rules = [], bool $textarea = true): string {
		$dict = (empty($rules) ? self::DEFAULT_RULES : $rules);
		$string = htmlspecialchars_decode($string);
		$string = str_replace(array_keys($dict), array_values($dict), $string);
		
		if (!$textarea) {
			$string = preg_replace('/(http|https|ftp|ftps|tel)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/', '', $string);
			$string = preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\.\s])?).*$)@', '', $string);
			$string = preg_replace('|https?://www\.[a-z\.0-9]+|i', '', $string);
			$string = preg_replace('|https?://[a-z\.0-9]+|i', '', $string);
			$string = preg_replace('|www\.[a-z\.0-9]+|i', '', $string);
			$string = preg_replace('~[a-z]+://\S+~', '', $string);
			$string = preg_replace('/&([a-z])(acute|uml|circ|grave|ring|cedil|slash|tilde|caron|lig|quot|rsquo);/i', '\\1', $string);
		} else {
			$string = str_replace("\n", "&#13;&#10;", $string);
		}

		return $string;
	}


	/**
	 * Mask email address.
	 *
	 * @param string $email Email address to mask.
	 * @param string $with  Mask character (default is "*").
	 * @return string Masked email address or null.
	 */
	public static function maskEmail(string $email, string $with = "*"): string 
	{
		if (!empty($email)) {
			$parts = explode("@", $email);
			$name = implode('@', array_slice($parts, 0, -1));
			$length = floor(strlen($name) / 2);
			return substr($name, 0, $length) . str_repeat($with, $length) . "@" . end($parts);
		}
		return '';
	}

	/**
	 * Mask string by position.
	 *
	 * @param string $string    String to mask.
	 * @param string $with      Mask character (default is "*").
	 * @param string $position  The position of the string to mask ("center", "left", or "right").
	 * @return string           Masked string.
	 */
	public static function mask(string $string, string $with = "*", string $position = "center"): string {
		if (empty($string)) {
			return '';
		}

		$length = strlen($string);
		$visibleCount = (int) round($length / 4);
		$hiddenCount = $length - ($visibleCount * 2);

		if ($position === "left") {
			return str_repeat($with, $visibleCount) . substr($string, $visibleCount, $hiddenCount) . substr($string, ($visibleCount * -1), $visibleCount);
		} elseif ($position === "right") {
			return substr($string, 0, ($visibleCount * -1)) . str_repeat($with, $visibleCount);
		}

		return substr($string, 0, $visibleCount) . str_repeat($with, $hiddenCount) . substr($string, ($visibleCount * -1), $visibleCount);
	}
}
