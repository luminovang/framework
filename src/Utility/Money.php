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
use Luminova\Exceptions\InvalidArgumentException;

final class Money
{
    /**
     * Convert a monetary amount to the smallest currency unit.
     *
     * For example, `10.99` becomes `1099` when the divisor is `100`.
     *
     * @param string|float|int $amount The monetary amount.
     * @param int $divisor The number of minor units in one major unit.
     *
     * @return int The amount in the smallest currency unit.
     *
     * @throws InvalidArgumentException If the amount is not numeric or the divisor is invalid.
     */
    public static function toCents(
        string|float|int $amount,
        int $divisor = 100
    ): int
    {
        self::assert($amount);
        self::divisor($divisor);

        return (int) round((float) $amount * $divisor);
    }

    /**
     * Convert an amount from the smallest currency unit to its major unit.
     *
     * For example, `1099` becomes `10.99` when the divisor is `100`.
     *
     * @param string|int $cents The amount in the smallest currency unit.
     * @param int $divisor The number of minor units in one major unit.
     *
     * @return string The formatted monetary amount.
     *
     * @throws InvalidArgumentException If the amount is not numeric or the divisor is invalid.
     */
    public static function fromCents(
        string|int $cents,
        int $divisor = 100
    ): string
    {
        self::assert($cents, 'cents');
        self::divisor($divisor);

        return number_format((int) $cents / $divisor, 2, '.', '');
    }

    /**
     * Format a monetary amount for display.
     *
     * This method formats a numeric value using grouped thousands and a fixed
     * number of decimal places. Optionally, a currency symbol or prefix can be
     * added before the amount, and/or a suffix after it.
     *
     * @param string|int|float $amount The monetary amount to format.
     * @param int $decimals Number of decimal places to display.
     * @param string|null $prefix Optional prefix (for example, `$` or `€`).
     * @param string|null $suffix Optional suffix (for example, ` USD`).
     *
     * @return string The formatted monetary amount.
     * @throws InvalidArgumentException If the amount is not numeric.
     */
    public static function format(
        string|int|float $amount,
        int $decimals = 2,
        ?string $prefix = null,
        ?string $suffix = null
    ): string
    {
        self::assert($amount);
        $formatted = number_format((float) $amount, $decimals, '.', ',');

        if ($prefix === null && $suffix === null) {
            return $formatted;
        }

        return ($prefix ?? '') 
            . $formatted 
            . ($suffix ?? '');
    }

    /**
     * Format an amount stored as the smallest currency unit (for example, cents)
     * into a human-readable monetary value.
     *
     * @param string|int $cents The amount in the smallest currency unit.
     * @param int $decimals Number of decimal places to display.
     * @param int $divisor The value used to convert the smallest currency unit
     *                     into its major unit (for example, `100` for cents).
     * @param string|null $prefix Optional text to prepend.
     * @param string|null $suffix Optional text to append.
     *
     * @return string The formatted monetary amount.
     *
     * @throws InvalidArgumentException If the amount is not numeric or the divisor is less than 1.
     */
    public static function formatCents(
        string|int $cents,
        int $decimals = 2,
        int $divisor = 100,
        ?string $prefix = null,
        ?string $suffix = null
    ): string
    {
        self::assert($cents, 'cents');
        self::divisor($divisor);

        return self::format((int) $cents / $divisor, $decimals, $prefix, $suffix);
    }

    /**
     * Format a monetary value as a localized currency string.
     *
     * Uses the application locale as the default formatting locale when none
     * is provided. The currency code controls the currency symbol, decimal
     * precision, and other currency-specific formatting rules.
     *
     * @param string|int|float $amount The amount to format.
     * @param string $code The ISO 4217 currency code (for example, USD).
     * @param string|null $locale Optional locale identifier to use for formatting.
     *
     * @return string|null The formatted currency string, or null on failure.
     * @throws InvalidArgumentException If the amount is not numeric.
     */
    public static function currency(
        string|int|float $amount,
        string $code = 'USD',
        ?string $locale = null
    ): ?string
    {
        self::assert($amount);
        $locale ??= env('app.locale', 'en-US');

        $currency = (new NumberFormatter($locale, NumberFormatter::CURRENCY))
            ->formatCurrency((float) $amount, $code);

        if($currency === false){
            return null;
        }

        return $currency;
    }

    /**
     * Calculate a percentage discount from an amount.
     *
     * Applies a percentage reduction to the given amount.
     * For example, a 10% discount on 100 returns 90.
     *
     * @param string|float|int $amount The original amount.
     * @param string|float|int $percentage The discount percentage.
     * @param int|null $precision Optional decimal precision. Null disables rounding.
     *
     * @return float The amount after applying the discount.
     *
     * @throws InvalidArgumentException If inputs are non-numeric, the percentage is negative,
     *                                  or the precision is invalid.
     */
    public static function discount(
        string|float|int $amount,
        string|float|int $percentage,
        ?int $precision = null
    ): float
    {
        self::assert($amount);
        self::divisor($percentage, 'percentage', 0);

        return self::converter(
            $amount,
            $percentage,
            $precision,
            'subtraction'
        );
    }

    /**
     * Calculate a percentage increase on an amount.
     *
     * Applies a percentage increase to the given amount.
     * For example, a 10% increase on 100 returns 110.
     *
     * @param string|float|int $amount The original amount.
     * @param string|float|int $percentage The tax increase percentage.
     * @param int|null $precision Optional decimal precision. Null disables rounding.
     *
     * @return float The amount after applying the increase.
     *
     * @throws InvalidArgumentException If inputs are non-numeric, the percentage is negative,
     *                                  or the precision is invalid.
     */
    public static function increase(
        string|float|int $amount,
        string|float|int $percentage,
        ?int $precision = null
    ): float
    {
        self::assert($amount);
        self::divisor($percentage, 'percentage', 0);

        return self::converter(
            $amount,
            $percentage,
            $precision,
            'addition'
        );
    }

    /**
     * Add tax percentage to an amount.
     *
     * @param string|float|int $amount Original amount.
     * @param string|float|int $percentage Tax percentage.
     *
     * @return float Amount including tax.
     */
    public static function tax(
        string|float|int $amount,
        string|float|int $percentage,
        ?int $precision = null
    ): float
    {
        return self::increase(
            $amount,
            $percentage,
            $precision
        );
    }

    /**
     * Extract tax amount from a tax-inclusive value.
     *
     * Example:
     * Money::extract(108, 8)
     * returns:
     * [
     *   'amount' => 100,
     *   'tax' => 8
     * ]
     */
    public static function discounted(
        string|float|int $total,
        string|float|int $percentage,
        ?int $precision = null
    ): array
    {
        self::assert($total, 'total');
        self::assert($percentage, 'percentage');

        $base = (float) $total / (1 + ((float) $percentage / 100));
        $taxAmount = (float) $total - $base;

        return [
            'amount'    => self::round($base, $precision ?? 2),
            'discount'  => self::round($taxAmount, $precision ?? 2),
        ];
    }

    /**
     * Calculate a percentage amount from a base value.
     *
     * For example, calculating 10% of 200 returns 20.
     *
     * @param string|float|int $percentage The percentage rate to apply.
     * @param string|float|int $amount The base amount to calculate from.
     * @param int|null $precision Optional decimal precision. Null disables rounding.
     *
     * @return float The calculated percentage amount.
     *
     * @throws InvalidArgumentException If inputs are non-numeric, the rate is negative,
     *                                  or the precision is invalid.
     */
    public static function percentage(
        string|float|int $percentage,
        string|float|int $amount,
        ?int $precision = null
    ): float
    {
        self::divisor($percentage, 'percentage', 0);
        self::assert($amount, 'of');

        return self::converter($amount, $percentage, $precision);
    }

    /**
     * Round a monetary value using the specified precision and rounding mode.
     *
     * @param string|float|int $amount The amount to round.
     * @param int $precision Number of decimal places.
     * @param int $mode Rounding mode (PHP_ROUND_HALF_UP by default).
     *
     * @return float The rounded amount.
     * @throws InvalidArgumentException If inputs are non-numeric, or the precision is invalid.
     */
    public static function round(
        string|float|int $amount,
        int $precision = 2,
        int $mode = PHP_ROUND_HALF_UP
    ): float
    {
        self::assert($amount, 'amount');
        self::divisor($precision, 'precision', 0);

        return round((float) $amount, $precision, $mode);
    }

    /**
     * Calculate the total sum of monetary values.
     *
     * @param array<int, string|float|int> $amounts Amounts to add.
     * @param int|null $precision Optional decimal precision.
     *
     * @return float The total amount.
     */
    public static function sum(
        array $amounts,
        ?int $precision = null
    ): float
    {
        $total = 0.0;

        foreach ($amounts as $index => $amount) {
            self::assert($amount, "amounts[$index]");
            $total += (float) $amount;
        }

        if($precision === null){
            return $total;
        }

        self::divisor($precision, 'precision', 0);
        return round($total, $precision);
    }

    /**
     * Compare two monetary values.
     *
     * @return int Returns -1 if first is lower, 0 if equal, 1 if greater.
     */
    public static function compare(
        string|float|int $first,
        string|float|int $second,
        int $precision = 2
    ): int
    {
        self::assert($first, 'first');
        self::assert($second, 'second');

        $first = round((float) $first, $precision);
        $second = round((float) $second, $precision);

        return $first <=> $second;
    }

    /**
     * Check whether an amount is zero.
     *
     * @param string|float|int $amount The amount to check.
     * @param int $precision Precision used for comparison.
     *
     * @return bool True if the amount is zero.
     */
    public static function isZero(
        string|float|int $amount,
        int $precision = 2
    ): bool
    {
        return self::compare($amount, 0, $precision) === 0;
    }

    /**
     * Split an amount into equal parts.
     *
     * @param string|float|int $amount The amount to split.
     * @param int $parts Number of parts.
     * @param int $precision Decimal precision.
     *
     * @return array<int,float> The split amounts.
     */
    public static function split(
        string|float|int $amount,
        int $parts,
        int $precision = 2
    ): array
    {
        self::assert($amount, 'amount');

        if ($parts < 1) {
            throw new InvalidArgumentException('Parts must be greater than zero.');
        }

        $unit = round((float) $amount / $parts, $precision);
        $result = array_fill(0, $parts, $unit);

        $difference = round(
            (float) $amount - array_sum($result),
            $precision
        );

        $result[0] += $difference;

        return $result;
    }

    /**
     * Allocate an amount according to ratios.
     *
     * @param string|float|int $amount The amount to allocate.
     * @param array<int, int|float> $ratios Allocation ratios.
     * @param int $precision Decimal precision.
     *
     * @return array<int, float> Allocated amounts.
     */
    public static function allocate(
        string|float|int $amount,
        array $ratios,
        int $precision = 2
    ): array
    {
        self::assert($amount, 'amount');

        if ($ratios === []) {
            throw new InvalidArgumentException('Ratios cannot be empty.');
        }

        $totalRatio = array_sum($ratios);

        if ($totalRatio <= 0) {
            throw new InvalidArgumentException('Ratios must be greater than zero.');
        }

        $result = [];

        foreach ($ratios as $ratio) {
            $result[] = round(
                ((float) $amount * $ratio) / $totalRatio,
                $precision
            );
        }

        $difference = round(
            (float) $amount - array_sum($result),
            $precision
        );

        $result[0] += $difference;

        return $result;
    }

    /**
     * Adjust a value by applying a percentage.
     *
     * Calculates a percentage amount from the given value and optionally applies
     * it as an increase or decrease.
     *
     * Supported operations:
     * - null: Return only the calculated percentage amount.
     * - addition: Add the percentage amount to the original value.
     * - subtraction: Remove the percentage amount from the original value.
     *
     * @param string|float|int $value The original value.
     * @param string|float|int $percentage The percentage to apply.
     * @param int|null $precision Optional decimal places to round the result.
     * @param string|null $operation Adjustment operation.
     * @param bool $finite Whether to require finite numeric values.
     *
     * @return float The calculated percentage or adjusted value.
     *
     * @throws InvalidArgumentException If values are invalid or percentage is negative.
     */
    private static function converter(
        string|float|int $value,
        string|float|int $percentage,
        ?int $precision = null,
        ?string $operation = null,
        bool $finite = false
    ): float
    {
        $value = (float) $value;
        $percentage = (float) $percentage;

        if ($finite && (!is_finite($value) || !is_finite($percentage))) {
            throw new InvalidArgumentException('Value and percentage must be finite numbers.');
        }

        $adjustment = ($value * $percentage) / 100;

        $result = match ($operation) {
            'subtraction' => $value - $adjustment,
            'addition'    => $value + $adjustment,
            default       => $adjustment,
        };

        if ($precision !== null) {
            self::divisor($precision, 'precision', 0);
            $result = round($result, $precision);
        }

        return $result;
    }

    /**
     * Assert that the given value is numeric.
     *
     * @param string|int|float $amount The value to validate.
     * @param string $name The parameter name used in error messages.
     *
     * @throws InvalidArgumentException If the value is not numeric.
     */
    private static function assert(
        string|int|float $amount,
        string $name = 'amount'
    ): void
    {
        if (!is_numeric($amount)) {
            throw new InvalidArgumentException(
                sprintf('%s must be a numeric value.', $name)
            );
        }
    }

    /**
     * Assert that a divisor meets the minimum allowed value.
     *
     * @param string|int|float $divisor The divisor to validate.
     * @param string $name The parameter name used in error messages.
     * @param int|float $min The minimum allowed divisor value.
     *
     * @throws InvalidArgumentException If the divisor is not numeric or below the minimum value.
     */
    private static function divisor(
        string|float|int $divisor,
        string $name = 'divisor',
        int|float $min = 1
    ): void
    {
        if (!is_numeric($divisor) || $divisor < $min) {
            throw new InvalidArgumentException(
                sprintf('%s must be a numeric value greater than or equal to %s.', $name, $min)
            );
        }
    }
}