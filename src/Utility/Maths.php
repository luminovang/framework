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

use \NumberFormatter;
use \Luminova\Exceptions\InvalidArgumentException;

/**
 * Class Maths
 *
 * Provides a set of static utility methods for common mathematical operations.
 */
final class Maths
{
    /**
     * Array of units for byte conversion.
     * 
     * @var array $units
     */
    private static array $units = [
        ['B', 'B'],
        ['KB', 'K'],
        ['MB', 'M'],
        ['GB', 'G'],
        ['TB', 'T'],
        ['PB', 'P'],
        ['EB', 'E'],
        ['ZB', 'Z'],
        ['YB', 'Y'],
    ];

    /**
     * Array of units powers for byte conversion.
     * 
     * @var array<string,int> $powers
     */
    private static array $powers = [
        'B'  => 0,
        'K'  => 1, 'KB' => 1,
        'M'  => 2, 'MB' => 2,
        'G'  => 3, 'GB' => 3,
        'T'  => 4, 'TB' => 4,
        'P'  => 5, 'PB' => 5,
        'E'  => 6, 'EB' => 6,
        'Z'  => 7, 'ZB' => 7,
        'Y'  => 8, 'YB' => 8,
    ];

    /**
     * Array of crypto currency length.
     * 
     * @var array<string,int> $decimals
     */
    private static array $cryptos = [
        'BTC' => 8, 
        'ETH' => 18,
        'LTC' => 8,
        'XRP' => 6,
        'DOGE' => 8
    ];

    /**
     * Radius of the Earth in different units
     * 
     * @var array<string,float> $radius
    */
    private static array $radius = [
        'km'  => 6371, 
        'm'   => 6_371_000, 
        'mi'  => 3959,
        'nmi' => 3440.065,
        'yd'  => 6_959_000,
        'ft'  => 20_921_000,
        'cm'  => 637_100_000,
    ];

    /**
     * Time units to corresponding number of milliseconds.
     * 
     * @var array $timeUnits 
     */
    private static array $timeUnits = [
        'ms'  => 1,
        's'   => 1_000,
        'min' => 60_000,
        'h'   => 3_600_000,
        'd'   => 86_400_000,
        'w'   => 604_800_000,
        'mo'  => 2_629_746_000,
        'y'   => 31_556_952_000,
    ];

    /**
     * Time units to full names.
     * 
     * @var array $timeUnitNames 
     */
    private static array $timeUnitNames = [
        'ms'  => 'millisecond',
        's'   => 'second',
        'min' => 'minute',
        'h'   => 'hour',
        'd'   => 'day',
        'w'   => 'week',
        'mo'  => 'month',
        'y'   => 'year'
    ];

    /**
     * Convert a byte value to a human-readable format.
     *
     * Automatically scales bytes to the most appropriate unit (B, KB, MB, GB, TB, PB, EB, ZB, YB)
     * and can optionally append the unit name in Unix or human-readable form.
     * Handles negative values and very large numbers safely.
     *
     * @param float|int $bytes The byte value to convert.
     * @param int $decimals Number of decimal places (default 2).
     * @param bool $withName Append unit name (default false).
     * @param bool $unixName Use Unix-style units (B, K, M, etc.) if true (default false).
     * @param bool $trimZeros Remove trailing zeros after the decimal (default false).
     *
     * @return string Return formatted value with optional unit name.
     */
    public static function toUnit(
        float|int $bytes, 
        int $decimals = 2,  
        bool $withName = false,
        bool $unixName = false,
        bool $trimZeros = false
    ): string
    {
        if ($bytes === 0) {
            if(!$withName){
                return '0';
            }
            
            return $unixName ? '0B' : '0 B';
        }
        
        $negative = $bytes < 0;
        $bytes = abs($bytes);

        $index = 0;
        $maxIndex = count(self::$units) - 1;

        while ($bytes >= 1024 && $index < $maxIndex) {
            $bytes /= 1024;
            $index++;
        }

        $value = number_format($bytes, max(0, $decimals), '.', '');

        if ($trimZeros && strpos($value, '.') !== false) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        if ($negative) {
            $value = '-' . $value;
        }

        if(!$withName){
            return $value;
        }

        if($unixName){
            return $value . self::$units[$index][1];
        }

        return $value . ' ' . self::$units[$index][0];
    }

    /**
     * Convert time milliseconds to a human-readable  time unit.
     *
     * Automatically chooses the most appropriate time unit:
     * milliseconds (ms), seconds (s), minutes (min), hours (h), days (d), weeks (w).
     *
     * @param float|int $milliseconds The time in milliseconds
     * @param int $decimals Number of decimal places (default 2).
     * @param bool $withName Include unit name (e.g., "s" or "seconds").
     * @param bool $withFullName Use full name (e.g., "seconds" instead of "s").
     * @param bool $trimZeros Trim unnecessary decimal zeros (default true).
     * 
     * @return string Return formatted time string.
     */
    public static function toTimeUnit(
        float|int $milliseconds,
        int $decimals = 2,
        bool $withName = false,
        bool $withFullName = false,
        bool $trimZeros = true
    ): string 
    {
        if ($milliseconds === 0) {
            if(!$withName){
                return '0';
            }

            return $withFullName ? '0 millisecond' : '0 ms';
        }

        $negative = $milliseconds < 0;
        $ms = abs($milliseconds);
        $unit = 'ms';

        foreach (self::$timeUnits as $name => $threshold) {
            if ($ms >= $threshold) {
                $unit = $name;
                continue;
            }

            break;
        }

        $value = $ms / self::$timeUnits[$unit];
        $value = round($value, $decimals);

        if ($trimZeros) {
            $value = rtrim(rtrim((string) $value, '0'), '.');
        }

        if ($negative) {
            $value = '-' . $value;
        }

        if ($withName) {
            $name = $withFullName ? self::$timeUnitNames[$unit] : $unit;

            if ($withFullName && abs($value) != 1) {
                $name .= 's';
            }

            return $value . ' ' . $name;
        }

        return (string)$value;
    }

    /**
     * Convert a size string into bytes.
     *
     * Supports Unix and human formats:
     * - (M, MB), (G, GB), -1 (unlimited)
     * 
     * @param string $units The string representation of the byte size (e.g., `1KB`, `2MB`, `1.5GB`).
     * 
     * @return int Return the size in bytes.
     */
    public static function toBytes(string $size): int
    {
        $size = strtoupper(trim($size));

        if ($size === '-1') {
            return PHP_INT_MAX;
        }

        if (!preg_match('/^([\d.]+)\s*(B|K|KB|M|MB|G|GB|T|TB|P|PB|E|EB|Z|ZB|Y|YB)?$/', $size, $m)) {
            return 0;
        }

        $value = (float) $m[1];
        $unit  = $m[2] ?? 'B';

        return (int) ($value * (1024 ** self::$powers[$unit]));
    }

    /**
     * Calculate the average of a giving numbers.
     *
     * @param int|float ...$numbers Input arguments integers or float values to calculate the average.
     * 
     * @return float|null Return the average of the passed numbers.
     * @example - Example:
     * ```php
     * Maths::average(10, 20, 30, 40, 50) // return 30 as the average.
     * ```
     */
    public static function average(int|float ...$numbers): ?float 
    {
        if ($numbers === []) {
            return null;
        }
        
        $total = array_sum($numbers);
        return $total / count($numbers);
    }

    /**
	 * Calculate the average rating based on the number of reviews and total rating points.
	 *
	 * @param int $reviews Total number of reviews.
	 * @param float $rating Total sum of rating points.
	 * @param bool $round Whether to round the average to 2 decimal places.
	 * 
	 * @return float Return the average rating.
     * 
     * @example - The average rating is: 8.50:
     * ```php
     * Math::rating(5, 42.5, true)
     * ```
	 */
	public static function rating(int $reviews = 0, float $rating = 0, bool $round = false): float 
	{
		if ($reviews === 0) {
			return 0.0; 
		}

		$average = $rating / $reviews;

		return $round ? round($average, 2) : $average;
	}

	/**
	 * Formats currency with decimal places and comma separation.
	 *
	 * @param mixed $amount Amount you want to format.
	 * @param int $decimals Decimals places.
	 * 
	 * @return string Return the formatted currency string.
	 */
	public static function money(mixed $amount, int $decimals = 2): string 
	{
		if (!is_numeric($amount)) {
			return $amount ?? '0.00';
		}

		return number_format((float) $amount, $decimals, '.', ',');
	}

    /**
     * Format a number as a currency string using your application local as the default currency locale.
     * 
     * @param float $number The number to format.
     * @param string $code The currency code (optional).
     * @param string|null $locale TOptional pass locale name to use in currency formatting.
     * 
     * @return string|false Return the formatted currency string, or false if unable to format.
     */
    public static function currency(float $number, string $code = 'USD', ?string $locale = null): string|bool
    {
        $locale ??= env('app.locale', 'en-US');

        return (new NumberFormatter($locale, NumberFormatter::CURRENCY))
            ->formatCurrency($number, $code);
    }

    /**
     * Format a number to it's cryptocurrency length.
     *
     * @param string|float|int $amount The amount to convert.
     * @param string $network The cryptocurrency code (e.g., 'BTC', 'ETH', 'LTC').
     * 
     * @return string|false Return the equivalent amount in cryptocurrency.
     */
    public static function crypto(string|float|int $amount, string $network = 'BTC'): string|bool
    {
        if (!is_numeric($amount)) {
			return false;
		}

        if ($network === 'USDT') {
			return self::money($amount);
		}

        return number_format((float) $amount, (self::$cryptos[$network] ?? 8), '.', '') . ' ' . $network;
    }

    /**
     * Calculate distance between two geographic coordinates.
     *
     * Uses the Haversine formula to determine the distance between two points on the Earth's surface.
     *
     * @param float|string $originLat The latitude of origin.
     * @param float|string $originLng The longitude of origin.
     * @param float|string $destLat The latitude of destination.
     * @param float|string $destLng The longitude of destination.
     * @param string $unit The distance unit (e.g, 'km', 'm', 'mi', 'nmi', 'yd', 'ft', 'cm').
     *
     * @return float Returns the distance in the requested unit distance between points.
     * @throws InvalidArgumentException On invalid unit or coordinates.
     */
    public static function distance(
        float|string $originLat,
        float|string $originLng,
        float|string $destLat,
        float|string $destLng,
        string $unit = 'km'
    ): float 
    {
        $unit = self::$radius[$unit] ?? null;

        if (!$unit) {
            throw new InvalidArgumentException("Unsupported unit '{$unit}'");
        }

        $lat1 = (float) $originLat;
        $lng1 = (float) $originLng;
        $lat2 = (float) $destLat;
        $lng2 = (float) $destLng;

        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        $deltaLat = $lat2 - $lat1;
        $deltaLng = $lng2 - $lng1;

        $a = sin($deltaLat / 2) ** 2 +
            cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $unit * $c;
    }

    /**
     * Format a number with optional decimal precision.
     *
     * @param string|float|int $number The value to format.
     * @param int|null $decimals Number of decimal places (null = no formatting).
     *
     * @return string Return the formatted and rounded numeric string.
     * @throws InvalidArgumentException if `$decimals` is less than zero.
     */
    public static function fixed(string|float|int $number, ?int $decimals = null): string
    {
        $number = (float) $number;

        if ($decimals === null) {
            return rtrim(rtrim(sprintf('%.14F', $number), '0'), '.');
        }

        if ($decimals < 0) {
            throw new InvalidArgumentException('Decimals must be zero or greater.');
        }

        return number_format($number, $decimals, '.', '');
    }

    /**
     * Calculate a percentage discount of a given value.
     *
     * This method calculates the discounted amount based on the given rate. 
     *
     * @param string|float|int $value The original total value.
     * @param string|float|int $rate  The discount rate as a percentage.
     * @param int|null $precision Optional decimal places to round the result.
     * 
     * @return float Returns the final value after applying the discount.
     * @throws InvalidArgumentException If non-numeric inputs, negative rates, or invalid precision.
     */
    public static function discount(
        string|float|int $value, 
        string|float|int $rate,
        ?int $precision = null
    ): float 
    {
        return self::calculateRate(
            $value, 
            $rate, 
            $precision,
            'subtraction'
        );
    }

    /**
     * Calculate a percentage interest of a given value.
     *
     * This method calculates the total value after adding interest based on the given rate.
     *
     * @param string|float|int $value The original amount.
     * @param string|float|int $rate The interest rate as a percentage.
     * @param int|null $precision Optional decimal places to round the result.
     * 
     * @return float Returns the final value after applying the interest.
     * @throws InvalidArgumentException If non-numeric inputs, negative rates, or invalid precision.
     */
    public static function interest(
        string|float|int $value, 
        string|float|int $rate,
        ?int $precision = null
    ): float 
    {
        return self::calculateRate(
            $value, 
            $rate, 
            $precision,
            'addition'
        );
    }

    /**
     * Calculate a percentage of a given amount.
     * 
     * This method calculates the absolute value of a percentage from a base value.
     * 
     * Alias {@see self::rate()}
     *
     * @param string|float|int $rate The percentage rate to calculate.
     * @param string|float|int $of The base value from.
     * @param int|null $precision Optional decimal precision (null = no rounding).
     *
     * @return float Return the percentage amount of given base value.
     * @throws InvalidArgumentException If non-numeric inputs, negative rates, or invalid precision.
     */
    public static function percentage(
        string|float|int $rate,
        string|float|int $of,
        ?int $precision = null
    ): float 
    {
        return self::calculateRate(
            $of, 
            $rate, 
            $precision
        );
    }

    /**
     * Calculate the absolute value of a percentage from a base value.
     * 
     * Alias of: {@see self::percentage()}
     *
     * @param string|float|int $rate The percentage rate to calculate.
     * @param string|float|int $of The base value from.
     * @param int|null $precision Optional decimal places to round the result.
     *
     * @return float Return the calculated percentage value.
     * @throws InvalidArgumentException If non-numeric inputs, negative rates, or invalid precision.
     */
    public static function rate(
        string|float|int $rate,
        string|float|int $of,
        ?int $precision = null
    ): float 
    {
        return self::calculateRate(
            $of, 
            $rate, 
            $precision
        );
    }

    /**
     * Sum multiple numbers.
     *
     * @param float|int ...$numbers Numbers to add.
     * 
     * @return float|int Return the total sum of the numbers.
     */
    public static function add(float|int ...$numbers): float|int
    {
        return array_sum($numbers);
    }

    /**
     * Subtract one number from another.
     *
     * @param float|int $a Minuend.
     * @param float|int $b Subtrahend.
     * 
     * @return float|int Return the difference.
     */
    public static function subtract(float|int $a, float|int $b): float|int
    {
        return $a - $b;
    }

    /**
     * Multiply multiple numbers.
     *
     * @param float|int ...$numbers Numbers to multiply.
     * 
     * @return float|int Return product of the numbers.
     */
    public static function multiply(float|int ...$numbers): float|int
    {
        return array_product($numbers);
    }

    /**
     * Divide one number by another.
     *
     * Returns null if division by zero is attempted.
     *
     * @param float|int $a The dividend number.
     * @param float|int $b The divisor number.
     * 
     * @return float|null Return the quotient or null if $b is 0.
     */
    public static function divide(float|int $a, float|int $b): ?float
    {
        return ($b === 0) ? null : $a / $b;
    }

    /**
     * Clamp a value between a minimum and maximum.
     *
     * @param float|int $value Value to clamp.
     * @param float|int $min Minimum allowed value.
     * @param float|int $max Maximum allowed value.
     * 
     * @return float|int Return the clamped value.
     */
    public static function clamp(float|int $value, float|int $min, float|int $max): float|int
    {
        return max($min, min($max, $value));
    }

    /**
     * Calculate the greatest common divisor (GCD) of two integers.
     *
     * @param int $a First number.
     * @param int $b Second number.
     * 
     * @return int Return the GCD of $a and $b.
     */
    public static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }
        return abs($a);
    }

    /**
     * Calculate the least common multiple (LCM) of two integers.
     *
     * @param int $a First number.
     * @param int $b Second number.
     * 
     * @return int Returns the LCM of $a and $b.
     */
    public static function lcm(int $a, int $b): int
    {
        return ($a === 0 || $b === 0) ? 0 : abs(intval($a * $b) / self::gcd($a, $b));
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
     * Check if a number is prime.
     *
     * @param int $n Number to check.
     * 
     * 
     * @return bool Returns true if $n is prime, false otherwise.
     */
    public static function isPrime(int $n): bool
    {
        if ($n < 2) return false;
        $sqrtN = (int) sqrt($n);
        for ($i = 2; $i <= $sqrtN; $i++) {
            if ($n % $i === 0) return false;
        }
        return true;
    }

    /**
     * Check if a number is even.
     *
     * @param int $number Number to check.
     * 
     * @return bool Return true if the number is even, false otherwise.
     */
    public static function isEven(int $number): bool
    {
        return $number % 2 === 0;
    }

    /**
     * Check if a number is odd.
     *
     * @param int $number Number to check.
     * 
     * @return bool Return true if the number is odd, false otherwise.
     */
    public static function isOdd(int $number): bool
    {
        return !self::isEven($number);
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
     * @throws InvalidArgumentException If precision is less than 0.
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
            if ($precision < 0) {
                throw new InvalidArgumentException('Precision must be >= 0');
            }

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
     * @throws InvalidArgumentException If precision is less than 0.
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
            if ($precision < 0) {
                throw new InvalidArgumentException('Precision must be >= 0');
            }

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
     * @throws InvalidArgumentException If precision is less than 0.
     * 
     * @example - Example:
     * ```php
     * Maths::isLatLng('12.971603', '77.594605');              // true
     * Maths::isLatLng('12.9716032', '77.5946052', true);      // true
     * Maths::isLatLng('12.97160321', '77.59460521', true, 6); // false (too many decimals)
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
     * Adjust a numeric value by a percentage (discount or interest).
     *
     * This method calculates a percentage of a given value and optionally
     * applies it to produce the final adjusted value. It supports both:
     *  - Subtraction (e.g., discount)
     *  - Addition (e.g., interest)
     *
     * Validation:
     *  - Ensures value and rate are numeric.
     *  - Rate cannot be negative.
     *  - Optional rounding with precision.
     *
     * @param string|float|int $valueThe original value to adjust.
     * @param string|float|int $rate The percentage rate to apply.
     * @param int|null $precision Optional number of decimal places to round the result.
     * @param string|null $type Type of adjustment: 'subtraction', 'addition', 
     *                  or null to get just the percentage.
     * @param bool $finite whether to check if value or rate is a legal finite number.
     *
     * @return float Returns the adjusted value or the raw percentage if $type is null.
     * @throws InvalidArgumentException If non-numeric inputs, negative rates, or invalid precision.
     */
    private static function calculateRate(
        string|float|int $value,
        string|float|int $rate,
        ?int $precision = null,
        ?string $apply = null,
        bool $finite = false
    ): float 
    {
        if (!is_numeric($value) || !is_numeric($rate)) {
            throw new InvalidArgumentException('Value and rate must be numeric.');
        }

        $value = (float) $value;
        $rate  = (float) $rate;

        if ($finite && (!is_finite($value) || !is_finite($rate))) {
            throw new InvalidArgumentException('Amount and percent must be finite numbers.');
        }

        if ($rate < 0) {
            throw new InvalidArgumentException('Percentage rate cannot be negative.');
        }

        $amount = ($value * $rate) / 100;
        $amount = match ($apply) {
            'subtraction' => $value - $amount,
            'addition'    => $value + $amount,
            default       => $amount
        };

        if ($precision !== null) {
            if ($precision < 0) {
                throw new InvalidArgumentException('Precision must be zero or greater.');
            }
            return round($amount, $precision);
        }

        return $amount;
    }
}