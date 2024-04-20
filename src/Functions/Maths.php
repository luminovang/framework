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

class Maths
{
    /**
     * Array of units for byte conversion.
     * 
     * @var array $units
     */
    private static array $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

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
    
        while ($bytes >= 1024 && $index < count(static::$units) - 1) {
            $bytes /= 1024;
            $index++;
        }
    
        $formatted = number_format($bytes, 2);
        $result = $add_name ? $formatted . ' ' . static::$units[$index] : $formatted;
    
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
     * Calculate the average from individual ratings.
     *
     * @param int ...$ratings Individual ratings.
     *  - @example average(10, 20, 30, 40, 50) - return 30 as the average.
     * 
     * @return float|null The average rating, or null if no ratings are provided.
    */
    public static function average(int ...$numbers): ?float 
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
	 * @param mixed $number Amount you want to format.
	 * @param bool $fractional Whether to format fractional numbers.
	 * 
	 * @return string Formatted currency string.
	*/
	public static function money(mixed $number, bool $fractional = true): string 
	{
		if (!is_numeric($number)) {
			return $number;
		}

		$decimals = ($fractional) ? 2 : 0;

		return number_format((float) $number, $decimals, '.', ',');
	}

	/**
	 * Format a number with optional rounding.
	 *
	 * @param float|int|string $number The number you want to format.
	 * @param int|null $decimalPlaces The number of decimal places (null for no rounding).
	 * 
	 * @return string The formatted number.
	 */
	public static function fixed($number, ?int $decimals = null): string 
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
     * @param int $rate The discount rate (percentage) as an integer.
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
     * @param float|int $rate The interest rate as a percentage (float or int).
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