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
namespace Luminova\Common;

use \NumberFormatter;
use \Luminova\Exceptions\InvalidArgumentException;

final class Maths
{
    /**
     * Array of units for byte conversion.
     * 
     * @var array $units
     */
    private static array $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

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
        'km' => 6371,
        'mi' => 3959, 
        'nmi' => 3440.065,
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
        'w'   => 604_800_000
    ];

    /**
     * Time units to full names.
     * 
     * @var array $timeUnitNames 
     */
    private static array $timeUnitNames = [
        'ms' => 'millisecond',
        's'  => 'second',
        'min' => 'minute',
        'h'  => 'hour',
        'd'  => 'day',
        'w'  => 'week'
    ];

    /**
     * Converts bytes to the appropriate unit.
     *
     * @param int $bytes The number of bytes to convert.
     * @param bool $addName Whether to include the unit name in the result. Default is false.
     * 
     * @return string Return the converted value with optional unit name.
     */
    public static function toUnit(int $bytes, bool $addName = false): string
    {
        $index = 0;
    
        while ($bytes >= 1024 && $index < count(self::$units) - 1) {
            $bytes /= 1024;
            $index++;
        }
    
        $formatted = number_format($bytes, 2);

        return ($addName ? $formatted . ' ' . self::$units[$index] : $formatted);
    }

    /**
     * Converts a given time in milliseconds to a human-readable format with
     * appropriate time units (e.g., milliseconds, seconds, minutes, etc.).
     * 
     * @param float|int  $milliseconds The time duration in milliseconds to be converted.
     * @param bool $addName Whether to include the unit name in the output (default: false).
     * @param bool $fullName Whether to use the full name of the unit (e.g., 'seconds' instead of 's').
     * 
     * @return string Return the formatted time duration with up to two decimal precision.
     */
    public static function toTimeUnit(float|int $milliseconds, bool $addName = false, bool $fullName = false): string
    {
        if ($milliseconds < 1) {
            return self::timeName($milliseconds / self::$timeUnits['ms'], 'ms', $addName, $fullName);
        }

        foreach (self::$timeUnits as $unit => $threshold) {
            if ($milliseconds < $threshold * 1_000) {
                return self::timeName($milliseconds / $threshold, $unit, $addName, $fullName);
            }
        }
        
        return self::timeName($milliseconds / self::$timeUnits['w'], 'w', $addName, $fullName);
    }

    /**
     * Converts a unit name to bytes.
     *
     * @param string $units The string representation of the byte size (e.g., '1KB', '2MB').
     * 
     * @return int Return the size in bytes.
     */
    public static function toBytes(string $units): int
    {
        $units = strtoupper(trim($units));
        $unit = substr($units, -1);
        $value = (int) substr($units, 0, -1);

        switch ($unit) {
            case 'G':
                $value *= 1024;
            case 'M':
                $value *= 1024;
            case 'K':
                $value *= 1024;
            break;
            case 'B':
            default:
        }

        return $value;
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

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($number, $code);
    }

    /**
     * Format a number to it's cryptocurrency length.
     *
     * @param int|float|string $amount The amount to convert.
     * @param string $network The cryptocurrency code (e.g., 'BTC', 'ETH', 'LTC').
     * 
     * @return string|false Return the equivalent amount in cryptocurrency.
     */
    public static function crypto(int|float|string $amount, string $network = 'BTC'): string|bool
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
     * Calculate the distance between two points on the Earth's surface.
     * 
     * @param float|string $originLat The latitude of the origin point.
     * @param float|string $originLng The longitude of the origin point.
     * @param float|string $destLat The latitude of the destination point.
     * @param float|string $destLng The longitude of the destination point.
     * @param string $unit The unit of distance to be returned (default is 'km').
     * 
     * @return float|false Return the distance between the two points, or false on invalid input.
     * 
     * Supported units: 
     * 
     * - 'km' - Kilometers, 
     * - 'mi' - Miles, 
     * - 'nmi' - Nautical miles.
     * 
     * > If you are passing a string, make sure its a float string.
     */
    public static function distance(
        float|string $originLat, 
        float|string $originLng, 
        float|string $destLat, 
        float|string $destLng, 
        string $unit = 'km'
    ): float|bool 
    {
        if (
            !isset(self::$radius[$unit]) || 
            !is_float($originLat) || 
            !is_float($originLng) || 
            !is_float($destLat) || 
            !is_float($destLng)
        ) {
            return false;
        }

        $lat1 = deg2rad((float) $originLat);
        $lng1 = deg2rad((float) $originLng);
        $lat2 = deg2rad((float) $destLat);
        $lng2 = deg2rad((float) $destLng);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lng2 - $lng1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
            cos($lat1) * cos($lat2) *
            sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::$radius[$unit] * $c;
    }

	/**
	 * Format a number with optional rounding.
	 *
	 * @param float|int|string $number The number you want to format.
	 * @param int|null $decimals The number of decimal places (null for no rounding).
	 * 
	 * @return string Return the formatted and rounded number.
	 */
	public static function fixed(float|int|string $number, ?int $decimals = null): string 
	{
		$number = is_numeric($number) ? (float) $number : 0.0;
		
		if ($decimals !== null) {
			return number_format($number, $decimals, '.', '');
		}
		
		return (string) $number;
	}

    /**
     * Calculate the discounted amount.
     *
     * @param float|int|string $total The total amount you want to discount.
     * @param float|int|string $rate The discount rate (percentage) as an integer.
     * 
     * @return float Return the discounted amount.
     */
    public static function discount(float|int|string $total, float|int|string $rate = 0): float 
    {
        $total = is_numeric($total) ? (float) $total : 0.0;
        $rate = is_numeric($rate) ? (float) $rate : 0.0;

        return $total * (1 - ($rate / 100));
    }

    /**
     * Calculate the total amount after adding interest.
     *
     * @param float|int|string $total The amount to which interest will be added.
     * @param float|int|string $rate The interest rate as a percentage (float or int).
     * 
     * @return float Return the total amount with an interest rate.
     */
    public static function interest(float|int|string $total, float|int|string $rate = 0): float 
    {
        $total = is_numeric($total) ? (float) $total : 0.0;
        $rate = is_numeric($rate) ? (float) $rate : 0.0;

        return $total * (1 + ($rate / 100));
    }

    /**
     * Formats a time value with optional unit name.
     *
     * @param float|int $time  The time value to format.
     * @param string $unit The unit of time (e.g., 'ms', 's', 'min', 'h', 'd', 'w').
     * @param bool $name   Whether to include the unit name in the output.
     * @param bool $full   Whether to use the full name of the unit (e.g., 'seconds' instead of 's').
     *
     * @return string Return the formatted time string.
     */
    private static function timeName(float|int $time, string $unit, bool $name, bool $full): string 
    {
        $formatted = number_format((float) $time, 2);

        if (!$name && !$full) {
            return $formatted;
        }

        return "$formatted " . ($full 
            ? self::$timeUnitNames[$unit] . (($formatted > 1) ? 's' : '')
            : $unit
        );
    }

    /**
     * Checks if a value is a valid latitude.
     * 
     * Latitude must be between **-90** and **90** degrees.
     * 
     * @param float|string $lat The latitude value to check.
     * @param bool $strict When true, also checks the numeric format and decimal precision.
     * @param int $precision The maximum number of decimal places allowed when $strict is true (default: 6).
     * 
     * @return bool Returns true if the latitude is valid, otherwise false.
     * @throws InvalidArgumentException If precision is less than 0.
     */
    public static function isLat(float|string $lat, bool $strict = false, int $precision = 6): bool
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
     * @param float|string $lng The longitude value to check.
     * @param bool $strict When true, also checks the numeric format and decimal precision.
     * @param int $precision The maximum number of decimal places allowed when $strict is true (default: 6).
     * 
     * @return bool Returns true if the longitude is valid, otherwise false.
     * @throws InvalidArgumentException If precision is less than 0.
     */
    public static function isLng(float|string $lng, bool $strict = false, int $precision = 6): bool
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
     * @param float|string $lat Latitude value.
     * @param float|string $lng Longitude value.
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
        float|string $lat, 
        float|string $lng, 
        bool $strict = false, 
        int $precision = 6
    ): bool
    {
        return self::isLat($lat, $strict, $precision) &&
            self::isLng($lng, $strict, $precision);
    }
}