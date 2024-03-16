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

use \Luminova\Functions\Escaper;
use \Luminova\Functions\IPAddress;
use \Luminova\Functions\Document;
use \Luminova\Functions\Files;
use \Luminova\Functions\TorDetector;
use Luminova\Exceptions\InvalidArgumentException;

trait FunctionTrait
{
    /**
     * @var array $instance method instances
    */
    private static array $instances = [];

    /**
     * Returns an instance of the IPAddress class.
     *
     * @return IPAddress
     * @throws RuntimeException
     */
    public static function ip(): IPAddress
    {
        return static::$instances['ip'] ??= new IPAddress();
    }

    /**
     * Returns an instance of the Document class.
     *
     * @return Document
     * @throws RuntimeException
     */
    public static function document(): Document
    {
        return static::$instances['document'] ??= new Document();
    }

    /**
     * Returns an instance of the Files class.
     *
     * @return Files
     * @throws RuntimeException
     */
    public static function files(): Files
    {
        return static::$instances['files'] ??= new Files();
    }

    /**
     * Returns an instance of the TorDetector class.
     *
     * @return TorDetector
     * @throws RuntimeException
     */
    public static function tor(): TorDetector
    {
        return static::$instances['tor'] ??= new TorDetector();
    }

    /**
     * Escapes a string or array of strings based on the specified context.
     *
     * @param string|array $input The string or array of strings to be escaped.
     * @param string $context The context in which the escaping should be performed.
     *                        Possible values: 'html', 'js', 'css', 'url', 'attr', 'raw'.
     * @param string|null $encoding The character encoding to use. Defaults to null.
     * 
     * @return mixed The escaped string or array of strings.
     * @throws InvalidArgumentException|Exception When an invalid escape context is provided.
     */
    public static function escape(string|array $input, string $context = 'html', ?string $encoding = null): mixed
    {
        if (is_array($input)) {
            array_walk_recursive($data, function (&$value) use ($context, $encoding) {
                $value = static::escape($value, $context, $encoding);
            });
        } elseif (is_string($input)) {
            $context = strtolower($context);
            if ($context === 'raw') {
                return $input;
            }

            if (!in_array($context, ['html', 'js', 'css', 'url', 'attr'], true)) {
                throw new InvalidArgumentException('Invalid escape context provided.');
            }

            $method = $context === 'attr' ? 'escapeHtmlAttr' : 'escape' . ucfirst($context);
            static $escaper;

            if (!$escaper || ($encoding && $escaper->getEncoding() !== $encoding)) {
                $escaper = new Escaper($encoding);
            }

            // Perform escaping
            $input = $escaper->{$method}($input);
        }

        return $input;
    }

    /**
     * Calculate the average from individual ratings.
     *
     * @param int ...$ratings Individual ratings.
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
	 * @return float The average rating.
	 */
	public static function averageRating(int $reviews = 0, float $rating = 0, bool $round = false): float 
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