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

use Luminova\Exceptions\InvalidArgumentException;

/**
 * Class Math
 *
 * Provides a set of static utility methods for common mathematical operations.
 */
final class Math
{
    /**
     * Calculate the average of a giving numbers.
     *
     * @param int|float ...$numbers Input arguments integers or float values to calculate the average.
     * 
     * @return float|null Return the average of the passed numbers.
     * @example - Example:
     * ```php
     * Math::average(10, 20, 30, 40, 50) // return 30 as the average.
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
     * Round a number down (toward negative infinity) with optional precision.
     *
     * This behaves like PHP's native floor() when precision is 0, but allows
     * rounding to a fixed number of decimal places or to larger units when
     * using negative precision.
     *
     * @param float|int $value The numeric value to round down.
     * @param int $precision  Number of decimal digits to preserve.
     *                        - 0  = integer floor (default)
     *                        - >0 = decimal precision (e.g. 2 → 4.567 → 4.56)
     *                        - <0 = round down to tens, hundreds, etc. (e.g. -2 → 1234 → 1200)
     *
     * @return float Return the floored value rounded to the next lowest integer.
     *
     * @example - Basic usage:
     * ```php
     * Math::floor(4.7);        // 4.0
     * Math::floor(-4.7);       // -5.0
     * ```
     *
     * @example - With decimal precision:
     * ```php
     * Math::floor(4.567, 2);   // 4.56
     * Math::floor(9.999, 1);   // 9.9
     * ```
     *
     * @example - With negative precision:
     * ```php
     * Math::floor(1234, -2);   // 1200
     * Math::floor(987, -1);    // 980
     * ```
     *
     * @example - Edge cases:
     * ```php
     * Math::floor(5, 2);       // 5.0
     * Math::floor(0, 3);       // 0.0
     * ```
     */
    public static function floor(float|int $value, int $precision = 0): float
    {
        $factor = 10 ** $precision;

        if ($precision >= 0) {
            return floor($value * $factor) / $factor;
        }

        return floor($value / (10 ** -$precision)) * (10 ** -$precision);
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
	 * @param int|null $min The minimum value (default: `Random::BIGINT_UNSIGNED_MIN`).
	 * @param int|null $max The maximum value (default: `Random::BIGINT_UNSIGNED_MAX`).
	 *
	 * @return string Return a string representation of the generated BIGINT.
     * @deprecated Use Random::bigInteger() instead.
	 */
    public static function bigInteger(?string $min = null, ?string $max = null): string 
    {
        return Random::bigInteger($min, $max);
    }

    /**
     * Check if a number is prime.
     *
     * @param int $n Number to check.
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
}