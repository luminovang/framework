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

use \NumberFormatter;

class Maths
{
    /**
     * Array of units for byte conversion.
     * 
     * @var array $units
     */
    private static array $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    /**
     * Array of crypto currecny length.
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
     * Converts bytes to the appropriate unit.
     *
     * @param int $bytes The number of bytes to convert.
     * @param bool $add_name Whether to include the unit name in the result. Default is false.
     * 
     * @return string The converted value with optional unit name.
     */
    public static function toUnit(int $bytes, bool $add_name = false): string
    {
        $index = 0;
    
        while ($bytes >= 1024 && $index < count(self::$units) - 1) {
            $bytes /= 1024;
            $index++;
        }
    
        $formatted = number_format($bytes, 2);
        $result = $add_name ? $formatted . ' ' . self::$units[$index] : $formatted;
    
        return $result;
    }

    /**
     * Converts a unit name to bytes.
     *
     * @param string $units The string representation of the byte size (e.g., '1KB', '2MB').
     * 
     * @return int The size in bytes.
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
        }

        return $value;
    }

    /**
     * Calculate the average of a giving numbers.
     *
     * @param int|float ...$numbers Input arguments integers or float values to calculate the average
     *      - @example average(10, 20, 30, 40, 50) - return 30 as the average.
     * 
     * @return float|null The average of the passed numbers.
    */
    public static function average(int|float ...$numbers): ?float 
    {
        if (empty($numbers)) {
            return null;
        }
        
        $total = array_sum($numbers);
        $average = $total / count($numbers);
        
        return $average;
    }

    /**
	 * Calculate the average rating based on the number of reviews and total rating points.
	 *
	 * @param int $reviews Total number of reviews.
	 * @param float $rating Total sum of rating points.
	 * @param bool $round Whether to round the average to 2 decimal places.
     * 
     *  - @example averageRating(5, 42.5, true) The average rating is: 8.50
	 * 
	 * @return float The average rating.
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
	 * @return string Formatted currency string.
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
     * @return string|false The formatted currency string, or false if unable to format.
     */
    public static function currency(float $number, string $code = 'USD', ?string $locale = null): string|bool
    {
        $locale ??= env('app.locale', 'en-US');

        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($number, $code);
    }

    /**
     * Convert a number to cryptocurrency.
     *
     * @param int|float|string $amount The amount to convert.
     * @param string $network The cryptocurrency code (e.g., 'BTC', 'ETH', 'LTC').
     * 
     * @return string|false The equivalent amount in cryptocurrency.
    */
    public static function crypto(int|float|string $amount, string $network = 'BTC'): string|bool
    {
        if (!is_numeric($amount)) {
			return false;
		}

        if ($network === 'USDT') {
			return static::money($amount);
		}

        return number_format((float) $amount, (self::$cryptos[$network] ?? 8), '.', '') . ' ' . $network;
    }

    /**
     * Calculate the distance between two points on the Earth's surface.
     * 
     * @param float|string $olat The latitude of the origin point.
     * @param float|string $olon The longitude of the origin point.
     * @param float|string $dlat The latitude of the destination point.
     * @param float|string $dlon The longitude of the destination point.
     * @param string $unit The unit of distance to be returned (default is 'km').
     *                     Supported units: 'km', 'mi', 'nmi'.
     * 
     * @return float|false The distance between the two points, or false on invalid input.
     * 
     * > If you are passing a string, make sure its a float string.
     */
    public static function distance(float|string $olat, float|string $olon, float|string $dlat, float|string $dlon, string $unit = 'km'): float|bool 
    {
        if (!isset(self::$radius[$unit]) || !is_float($olat) || !is_float($olon) || !is_float($dlat) || !is_float($dlon)) {
            return false;
        }

        $lat1 = deg2rad((float) $olat);
        $lon1 = deg2rad((float) $olon);
        $lat2 = deg2rad((float) $dlat);
        $lon2 = deg2rad((float) $dlon);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

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
	 * @return string The formatted number.
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
     * @return float The discounted amount.
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
     * @return float The total amount after adding interest.
     */
    public static function interest(float|int|string $total, float|int|string $rate = 0): float 
    {
        $total = is_numeric($total) ? (float) $total : 0.0;
        $rate = is_numeric($rate) ? (float) $rate : 0.0;

        return $total * (1 + ($rate / 100));
    }
}