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

class Luhn
{
    /**
     * Calculate Luhn checksum total.
     *
     * Takes a numeric string, removes non-digit characters, then computes the Luhn sum
     * by processing digits from right to left, doubling every second digit and adjusting
     * values greater than 9.
     *
     * @param string $number Input number (may contain non-digit characters).
     *
     * @return int Luhn checksum total. Used to validate numbers or derive check digits.
     * @example - Example:
     * ```php
     * Luhn::sum("7992739871"); // 70
     * Luhn::sum("12345678");   // 70
     * Luhn::sum("799273987");  // 67
     * ```
     */
    public static function sum(string $number): int
    {
        $number = preg_replace('/\D/', '', $number);
        $len = strlen($number);

        $sum = 0;
        $double = false;

        for ($i = $len - 1; $i >= 0; $i--) {
            $digit = (int) $number[$i];

            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $double = !$double;
        }

        return $sum;
    }

    /**
     * Check if number is valid Luhn.
     * 
     * The number can include non-digit characters, which will be ignored.
     * 
     * @param string $number The number to validate.
     * 
     * @return bool Returns return true if valid Luhn numbers, otherwise false.
     * @example - Example:
     * ```php
     * Luhn::isValid("7992739871"); // true
     * Luhn::isValid("7992739872"); // false
     * Luhn::isValid("123456781"); // true (check digit is 1)
     * ```
     * > **Note:**
     * > This method does not check for the length of the number, 
     * > so it can be used for various types of Luhn numbers (e.g. credit cards, IMEI, etc.).
     */
    public static function isValid(string $number): bool
    {
        return self::sum($number) % 10 === 0;
    }

     /**
     * Get Luhn check digit.
     *
     * Calculates the correct check digit for a number without its final digit.
     * 
     * @param string $number The number to calculate the check digit for (without the check digit).
     * 
     * @return int Returns the correct check digit (0-9) that should be appended to the number to make it valid Luhn.
     * @example - Example:
     * ```php     
     * Luhn::digit("7992739871"); // 3
     * Luhn::digit("12345678");   // 1
     * Luhn::digit("799273987");  // 3
     * ```
     */
    public static function digit(string $number): int
    {
        return (10 - (self::sum($number . '0') % 10)) % 10;
    }

    /**
     * Append valid check digit to number.
     * 
     * This method takes a number without the check digit, calculates the correct check digit using the Luhn algorithm,
     * and appends the correct check digit to make the number Luhn valid.
     * 
     * @param string $number The base number to generate (without check digit).
     * 
     * @return string Returns the original number with the correct check digit appended to the end.
     * @example - Example:
     * ```php
     * Luhn::generate("7992739871"); // "79927398713"
     * Luhn::generate("12345678");   // "123456781"
     * Luhn::generate("799273987");  // "7992739873"
     * ```
     */
    public static function generate(string $number): string
    {
        return $number 
            . self::digit($number);
    }
}