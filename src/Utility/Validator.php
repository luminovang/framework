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

final class Validator
{
	/**
	 * Validate an email address with optional IDN support and domain rejection.
	 *
	 * @param string $email Email address to validate.
	 * @param array $rejectDomains List of domains to reject (e.g. ['example.com']).
	 * @param bool $allowIdn Allow internationalized domains (default: false).
	 *
	 * @return bool Returns true if valid email address, false otherwise.
	 */
	public static function isEmail(
		string $email,
		array $rejectDomains = [],
		bool $allowIdn = false
	): bool
	{
		if ($email === '' || !str_contains($email, '@')) {
			return false;
		}

		[$local, $domain] = explode('@', $email, 2);

		if ($local === '' || $domain === '') {
			return false;
		}

		if ($allowIdn && function_exists('idn_to_ascii')) {
			$domain = idn_to_ascii($domain) ?: '';
		}

		if ($domain === '') {
			return false;
		}

		if ($rejectDomains) {
			$domainLower = strtolower($domain);

			foreach ($rejectDomains as $reject) {
				$reject = strtolower($reject);
				if (
					$domainLower === $reject ||
					str_ends_with($domainLower, '.' . $reject)
				) {
					return false;
				}
			}
		}

		$email = $local . '@' . $domain;

		if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
			return true;
		}

		return (bool) preg_match(
			'/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9\-]+(\.[a-zA-Z0-9\-]+)*\.[a-zA-Z]{2,}$/',
			$email
		);
	}

	/**
	 * Validates a URL with optional IDN (internationalized domain) support.
	 *
	 * @param string $url The URL to validate.
	 * @param bool $allowIdn Whether to allow internationalized domain names.
	 * @param bool $httpOnly Restrict to http/https schemes only.
	 *
	 * @return bool True if valid URL, otherwise false.
	 */
	public static function isUrl(string $url, bool $allowIdn = false, bool $httpOnly = false): bool
	{
		if ($url === '') {
			return false;
		}

		$parts = parse_url($url);

		if ($parts === false || empty($parts['host'])) {
			return false;
		}

		if ($httpOnly) {
			if (empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
				return false;
			}
		}

		$host = $parts['host'];

		if ($allowIdn) {
			if (function_exists('idn_to_ascii')) {
				$converted = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
				if ($converted === false) {
					return false;
				}
				$host = $converted;
			} else {
				return false;
			}
		}

		$normalized =
			($parts['scheme'] ?? 'http') . '://' 
			. $host
			. (isset($parts['port']) ? ':' . $parts['port'] : '')
			. ($parts['path'] ?? '')
			. (isset($parts['query']) ? '?' . $parts['query'] : '')
			. (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

		return filter_var($normalized, FILTER_VALIDATE_URL) !== false;
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
     * Validates whether the given value is a valid big integer within an optional range.
     *
     * This method performs a strict numeric validation for integer strings. 
     * It supports comparison against minimum and maximum boundaries using lexicographic comparison.
     *
     * The function is designed for BIGINT-safe validation (signed or unsigned),
     * making it suitable for database IDs, user identifiers, and large numeric keys.
     *
     * Default Range
     * - min: 0
     * - max: 18446744073709551615 (unsigned BIGINT)
     *
     * @param string|int $input The value to validate as a big integer.
     * @param string|null $min Minimum allowed value (inclusive). Defaults to 0.
     * @param string|null $max Maximum allowed value (inclusive). Defaults to unsigned BIGINT max.
     *
     * @return bool Returns true if the value is a valid big integer within range, false otherwise.
     */
    public static function isBigInteger(
        string|int $input, 
        ?string $min = null, 
        ?string $max = null
    ): bool
    {
        $input = self::normalizeBigInt((string) $input);

        if ($input === '') {
            return false;
        }

        $min ??= Random::BIGINT_UNSIGNED_MIN;
        $max ??= Random::BIGINT_UNSIGNED_MAX;

        $digits = strlen($input);
        $minDigits = strlen(ltrim($min, '+-0')) ?: 1;
        $maxDigits = strlen(ltrim($max, '+-0')) ?: 1;

        if ($digits > $maxDigits) {
            return false;
        }

        if ($digits < $minDigits) {
            return false;
        }

        return self::compareBigInt($input, $min) >= 0
            && self::compareBigInt($input, $max) <= 0;
    }

    /**
     * Checks if a value is a valid latitude.
     * 
     * Latitude must be between **-90** and **90** degrees.
     * 
     * @param string|float $lat The latitude value to check.
     * @param bool $strict When true, also checks the numeric format and decimal precision.
     * @param int $precision The maximum number of decimal places allowed when $strict is true (default: 6).
     * 
     * @return bool Returns true if the latitude is valid, otherwise false.
     */
    public static function isLat(string|float $lat, bool $strict = false, int $precision = 6): bool
    {
        if (!is_numeric($lat) && !is_string($lat)) {
            return false;
        }

        $lat = trim((string) $lat);

        if ($lat === '') {
            return false;
        }

        if ($strict) {
            $precision = max(0, $precision);
            // if ($precision < 0) {
            //    throw new InvalidArgumentException('Precision must be >= 0');
            // }

            $pattern = ($precision > 0)
                ? sprintf('/^[+-]?\d{1,2}(?:\.\d{1,%d})?$/', $precision)
                : '/^[+-]?\d{1,2}$/';

            if (!preg_match($pattern, $lat)) {
                return false;
            }
        } elseif (!is_numeric($lat)) {
            if (!preg_match('/^[+-]?\d+(?:\.\d+)?/u', $lat, $m)) {
                return false;
            }

            $lat = $m[0];
        }

        $lat = (float) $lat;

        return is_finite($lat) && $lat >= -90 && $lat <= 90;
    }

    /**
     * Checks if a value is a valid longitude.
     * 
     * Longitude must be between **-180** and **180** degrees.
     * 
     * @param string|float $lng The longitude value to check.
     * @param bool $strict When true, also checks the numeric format and decimal precision.
     * @param int $precision The maximum number of decimal places allowed when $strict is true (default: 6).
     * 
     * @return bool Returns true if the longitude is valid, otherwise false.
     */
    public static function isLng(string|float $lng, bool $strict = false, int $precision = 6): bool
    {
        if (!is_numeric($lng) && !is_string($lng)) {
            return false;
        }

        $lng = trim((string) $lng);

        if ($lng === '') {
            return false;
        }

        if ($strict) {
            $precision = max(0, $precision);
            // if ($precision < 0) {
            //    throw new InvalidArgumentException('Precision must be >= 0');
            // }

            $pattern = ($precision > 0)
                ? sprintf('/^[+-]?\d{1,3}(?:\.\d{1,%d})?$/', $precision)
                : '/^[+-]?\d{1,3}$/';

            if (!preg_match($pattern, $lng)) {
                return false;
            }
        }elseif(!is_numeric($lng)){
            if (!preg_match('/^[+-]?\d+(?:\.\d+)?/u', $lng, $m)) {
                return false;
            }

            $lng = $m[0];
        }

        $lng = (float) $lng;
        return is_finite($lng) && $lng >= -180 && $lng <= 180;
    }
   
    /**
     * Checks if both latitude and longitude values are valid.
     * 
     * - Latitude must be between **-90** and **90** degrees.  
     * - Longitude must be between **-180** and **180** degrees.  
     * 
     * When `$strict` is true, both values are also checked for numeric format
     * and decimal precision (based on `$precision`).
     * 
     * @param string|float $lat Latitude value.
     * @param string|float $lng Longitude value.
     * @param bool $strict When true, also checks numeric format and precision.
     * @param int $precision Maximum allowed decimal places when $strict is true (default: 6).
     * 
     * @return bool Returns true if both latitude and longitude are valid, otherwise false.
     * 
     * @example - Example:
     * ```php
     * Validator::isLatLng('12.971603', '77.594605');              // true
     * Validator::isLatLng('12.9716032', '77.5946052', true);      // true
     * Validator::isLatLng('12.97160321', '77.59460521', true, 6); // false (too many decimals)
     * ```
     */
    public static function isLatLng(
        string|float $lat, 
        string|float $lng, 
        bool $strict = false, 
        int $precision = 6
    ): bool
    {
        return self::isLat($lat, $strict, $precision) &&
            self::isLng($lng, $strict, $precision);
    }

    /**
     * Compare bigint.
     *
     * @param string $a
     * @param string $b
     * @return integer
     */
    private static function compareBigInt(string $a, string $b): int
    {
        $aNeg = $a[0] === '-';
        $bNeg = $b[0] === '-';

        $a = ltrim($a, '+-');
        $b = ltrim($b, '+-');

        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';

        if ($aNeg !== $bNeg) {
            return $aNeg ? -1 : 1;
        }

        $cmp = self::bigIntCompareAbs($a, $b);

        return $aNeg ? -$cmp : $cmp;
    }


    /**
     * Compare bigint ABS.
     *
     * @param string $a
     * @param string $b
     * 
     * @return int
     */
    private static function bigIntCompareAbs(string $a, string $b): int
    {
        $la = strlen($a);
        $lb = strlen($b);

        if ($la !== $lb) {
            return $la <=> $lb;
        }

        return $a <=> $b;
    }

    /**
     * Normalize bigint
     *
     * @param string $value
     * 
     * @return string
     */
    private static function normalizeBigInt(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $negative = false;

        if ($value[0] === '-') {
            $negative = true;
            $value = substr($value, 1);
        } elseif ($value[0] === '+') {
            $value = substr($value, 1);
        }

        if ($value === '' || !ctype_digit($value)) {
            return '';
        }

        $value = ltrim($value, '0');

        if ($value === '') {
            return '0';
        }

        return $negative ? '-' . $value : $value;
    }
}