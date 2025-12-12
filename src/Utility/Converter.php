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
use Luminova\Luminova;
use Luminova\Config\Env;
use Luminova\Utility\Mime;
use Luminova\Storage\Filesystem;
use Luminova\Exceptions\{ErrorCode, RuntimeException, InvalidArgumentException};

/**
 * Class Converter
 *
 * Provides a set of static utility methods for common mathematical operations.
 */
final class Converter
{
    /**
     * @var array<string,float|int> UNIT_DISTANCE
     */
    private const UNIT_DISTANCE = [
        'MM'  => 0.001,
        'CM'  => 0.01,
        'M'   => 1,
        'KM'  => 1000,
        'IN'  => 0.0254,
        'FT'  => 0.3048,
        'YD'  => 0.9144,
        'MI'  => 1609.344,
        'NMI' => 1852,
    ];

    /**
     * @var array<string,float|int> UNIT_WEIGHT
     */
    private const UNIT_WEIGHT = [
        'MG' => 0.001,
        'G'  => 1,
        'KG' => 1000,
        'T'  => 1000000,
        'OZ' => 28.349523125,
        'LB' => 453.59237,
        'ST' => 6350.29318,
    ];

    /**
     * Array of units powers for byte conversion.
     * 
     * @var array<string,int> UNIT_POWERS
     */
    private const UNIT_POWERS = [
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
     * Array of units for byte conversion.
     * 
     * @var array<int,string[]> UNIT_MEASUREMENTS
     */
    private const UNIT_MEASUREMENTS = [
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
     * Array of crypto currency length.
     * 
     * @var array<string,int> CRYPTO_CURRENCIES
     */
    private const CRYPTO_CURRENCIES = [
        // 8 decimals (satoshi-style)
        'BTC'   => 8,
        'BCH'   => 8,
        'LTC'   => 8,
        'DOGE'  => 8,

        // 18 decimals (EVM native / ERC-20 default)
        'ETH'   => 18,
        'BNB'   => 18,
        'AVAX'  => 18,
        'MATIC' => 18,
        'POL'   => 18,
        'LINK'  => 18,
        'DAI'   => 18,

        // Other native precisions
        'XRP'   => 6,
        'ADA'   => 6,
        'TRX'   => 6,
        'ATOM'  => 6,
        'XLM'   => 7,
        'SOL'   => 9,
        'TON'   => 9,
        'DOT'   => 10,
        'XMR'   => 12,

        // Stable coins (ERC-20 / TRC-20 / Solana use 6)
        'USDT'  => 6,
        'USDC'  => 6,
    ];

    /**
     * Radius of the Earth in different units
     * 
     * @var array<string,float> EARTH_RADIUS
    */
    private const EARTH_RADIUS = [
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
     * @var array<string,int> TIME_UNITS 
     */
    private const TIME_UNITS = [
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
     * @var array<string,string> TIME_UNIT_NAMES 
     */
    private const TIME_UNIT_NAMES = [
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
	 * Binary magic numbers.
	 * 
	 * @var array<string,string> MAGIC_NUMBERS
	 */
	private const MAGIC_NUMBERS = [
		"\x89PNG\r\n\x1A\n" => 'png',
		"\xFF\xD8\xFF" 	    => 'jpg',
		"\x25\x50\x44\x46"  => 'pdf',
		//"\x50\x4B\x03\x04"  => 'zip',
		"\x49\x44\x33" 	    => 'mp3',
		"\x47\x49\x46\x38"  => 'gif',
		"\xD0\xCF\x11\xE0"  => 'doc', 
		"\x50\x4B\x03\x04"  => 'docx', 
		"\x50\x4B\x07\x08"  => 'xlsx',
		"\x52\x49\x46\x46"  => 'wav',
		"\x00\x00\x01\xBA"  => 'mpg',
		"\x00\x00\x01\xB3"  => 'mpg',
		"\x1A\x45\xDF\xA3"  => 'mkv'
	];

    /**
     * Converts a byte value to a human-readable size.
     *
     * Automatically scales the value to the most appropriate unit, from bytes
     * through yottabytes, using a base of 1024.
     *
     * @param float|int $bytes Byte value to convert.
     * @param int $decimals Number of decimal places (default: 2).
     * @param bool $withName Whether to include the unit name.
     * @param bool $unixName Whether to use Unix-style unit names such as `K`, `M`, and `G`.
     * @param bool $trimZeros Whether to remove trailing decimal zeros (default: false).
     *
     * @return string Returns the formatted byte value, optionally with its unit name.
     *
     * @example - Examples:
     * ```php
     * Converter::toUnit(1024);                            // "1.00"
     * Converter::toUnit(1536, 2, true);                  // "1.50 KB"
     * Converter::toUnit(1536, 2, true, true);            // "1.50K"
     * Converter::toUnit(1536, 2, true, false, true);     // "1.5 KB"
     * Converter::toUnit(1073741824, 2, true);             // "1.00 GB"
     * Converter::toUnit(-2048, 2, true);                 // "-2.00 KB"
     * ```
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
            if (!$withName) {
                return '0';
            }

            return $unixName ? '0B' : '0 B';
        }

        $negative = $bytes < 0;
        $bytes = abs($bytes);

        $index = 0;
        $maxIndex = count(self::UNIT_MEASUREMENTS) - 1;

        while ($bytes >= 1024 && $index < $maxIndex) {
            $bytes /= 1024;
            $index++;
        }

        $value = number_format($bytes, max(0, $decimals), '.', '');

        if ($trimZeros && str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        if ($negative) {
            $value = '-' . $value;
        }

        if (!$withName) {
            return $value;
        }

        $unit = self::UNIT_MEASUREMENTS[$index];

        return $unixName
            ? $value . $unit[1]
            : $value . ' ' . $unit[0];
    }

    /**
     * Converts milliseconds to a human-readable time unit.
     *
     * Automatically selects the largest appropriate unit, from milliseconds
     * through years.
     *
     * @param float|int $milliseconds Time value in milliseconds.
     * @param int $decimals Number of decimal places (default: 2).
     * @param bool $withName Whether to include the unit name.
     * @param bool $withFullName Whether to use the full unit name instead of its abbreviation.
     * @param bool $trimZeros Whether to remove trailing decimal zeros (default: true).
     *
     * @return string Returns the formatted time value.
     *
     * @example - Examples:
     * ```php
     * Converter::toTimeUnit(500);                    // "500"
     * Converter::toTimeUnit(1500);                   // "1.5"
     * Converter::toTimeUnit(1500, withName: true);   // "1.5ms"
     * Converter::toTimeUnit(60000, withName: true);  // "1min"
     * Converter::toTimeUnit(3600000, withName: true); // "1h"
     * Converter::toTimeUnit(86400000, withName: true, withFullName: true); // "1 day"
     * ```
     */
    public static function toTimeUnit(
        float|int $milliseconds,
        int $decimals = 2,
        bool $withName = false,
        bool $withFullName = false,
        bool $trimZeros = true
    ): string 
    {
        $negative = $milliseconds < 0;
        $milliseconds = abs($milliseconds);

        $unit = 'ms';

        foreach (self::TIME_UNITS as $name => $threshold) {
            if ($milliseconds < $threshold) {
                break;
            }

            $unit = $name;
        }

        $value = round(
            $milliseconds / self::TIME_UNITS[$unit],
            max(0, $decimals)
        );

        if ($trimZeros) {
            $value = rtrim(rtrim((string) $value, '0'), '.');
        }

        if ($negative && $value !== '0') {
            $value = '-' . $value;
        }

        if (!$withName) {
            return (string) $value;
        }

        if (!$withFullName) {
            return $value . $unit;
        }

        $name = self::TIME_UNIT_NAMES[$unit];

        if (abs((float) $value) != 1) {
            $name .= 's';
        }

        return $value . ' ' . $name;
    }

    /**
     * Converts a human-readable size into bytes.
     *
     * Supports binary units from bytes through yottabytes, including their
     * short forms such as `K`, `M`, and `G`. The special value `-1` represents
     * an unlimited size and returns `PHP_INT_MAX`.
     *
     * Supported units:
     * - `B` - Bytes
     * - `K`|`KB` - Kilobytes
     * - `M`|`MB` - Megabytes
     * - `G`|`GB` - Gigabytes
     * - `T`|`TB` - Terabytes
     * - `P`|`PB` - Petabytes
     * - `E`|`EB` - Exabytes
     * - `Z`|`ZB` - Zettabytes
     * - `Y`|`YB` - Yottabytes
     *
     * @param string $size Size to convert, such as `1KB`, `2MB`, or `1.5GB`.
     *
     * @return int Returns the size in bytes, or `0` for an invalid size.
     *
     * @example - Examples:
     * ```php
     * Converter::toBytes('1KB');   // 1024
     * Converter::toBytes('2MB');   // 2097152
     * Converter::toBytes('1.5GB'); // 1610612736
     * Converter::toBytes('10G');   // 10737418240
     * Converter::toBytes('-1');    // PHP_INT_MAX
     * Converter::toBytes('invalid'); // 0
     * ```
     */
    public static function toBytes(string $size): int
    {
        $size = strtoupper(trim($size));

        if ($size === '-1') {
            return PHP_INT_MAX;
        }

        if (!preg_match(
            '/^(\d+(?:\.\d+)?)\s*(B|K|KB|M|MB|G|GB|T|TB|P|PB|E|EB|Z|ZB|Y|YB)?$/i',
            $size,
            $matches
        )) {
            return 0;
        }

        $value = (float) $matches[1];
        $unit = $matches[2] ?? 'B';

        return (int) ($value * (1024 ** self::UNIT_POWERS[$unit]));
    }

    /**
     * Calculates the average rating from the total rating points and review count.
     *
     * @param int $reviews Number of reviews.
     * @param float $rating Total rating points.
     * @param bool $round Whether to round the result to 2 decimal places.
     *
     * @return float Returns the average rating.
     *
     * @example - Examples:
     * ```php
     * Converter::rating(5, 42.5);       // 8.5
     * Converter::rating(5, 42.5, true); // 8.5
     * ```
     */
    public static function rating(
        int $reviews = 0,
        float $rating = 0,
        bool $round = false
    ): float 
    {
        if ($reviews === 0) {
            return 0.0;
        }

        $average = $rating / $reviews;

        return $round ? round($average, 2) : $average;
    }

    /**
     * Formats a numeric amount with decimal places and thousands separators.
     *
     * @param mixed $amount Amount to format.
     * @param int $decimals Number of decimal places (default: 2).
     *
     * @return string Returns the formatted amount, or `0.00` for a non-numeric value.
     *
     * @example - Examples:
     * ```php
     * Converter::money(1234.5);       // "1,234.50"
     * Converter::money(1234.567, 3);  // "1,234.567"
     * Converter::money('invalid');    // "0.00"
     * ```
     */
    public static function money(
        mixed $amount,
        int $decimals = 2
    ): string 
    {
        if (!is_numeric($amount)) {
            return '0.00';
        }

        return number_format((float) $amount, $decimals, '.', ',');
    }

    /**
     * Formats a number as a localized currency string.
     *
     * Uses the application locale when no locale is explicitly provided.
     *
     * @param float $number Amount to format.
     * @param string $code ISO 4217 currency code (default: `USD`).
     * @param string|null $locale Locale to use for currency formatting.
     *
     * @return string|false Returns the formatted currency string, or `false` if formatting fails.
     *
     * @example - Examples:
     * ```php
     * Converter::currency(1234.5);                  // "$1,234.50"
     * Converter::currency(1234.5, 'EUR');           // "€1,234.50"
     * Converter::currency(1234.5, 'NGN', 'en-NG');  // "₦1,234.50"
     * ```
     */
    public static function currency(
        float $number,
        string $code = 'USD',
        ?string $locale = null
    ): string|false 
    {
        $locale ??= Env::get('app.locale', 'en-US');

        return (new NumberFormatter($locale, NumberFormatter::CURRENCY))
            ->formatCurrency($number, $code);
    }

    /**
     * Formats a cryptocurrency amount using the currency's on-chain precision.
     *
     * Formatting is done on the decimal string, not a float, so high-precision
     * assets such as ETH (18 decimals) are never corrupted by float rounding.
     * Rounding is half away from zero, matching number_format().
     *
     * @param string|float|int $amount Cryptocurrency amount to format.
     * @param string $currency The network currency code, e.g. `BTC`, `ETH`, `USDT`.
     * @param int|null $precision Override decimals (e.g. 2 for UI display).
     * @param bool $trimZeros Strip trailing zeros ("1.5" instead of "1.50000000").
     *
     * @return string|false Formatted amount, or `false` if not a finite number.
     *
     * @example - Examples:
     * ```php
     * Converter::crypto(0.12345678, 'BTC');                  // "0.12345678 BTC"
     * Converter::crypto(1.5, 'ETH');                         // "1.500000000000000000 ETH"
     * Converter::crypto(0.1, 'ETH');                         // "0.100000000000000000 ETH"
     * Converter::crypto(1.5, 'ETH', trimZeros: true);        // "1.5 ETH"
     * Converter::crypto(100, 'USDT');                        // "100.000000 USDT"
     * Converter::crypto(100, 'USDT', precision: 2);          // "100.00 USDT"
     * Converter::crypto(1e-8, 'BTC');                        // "0.00000001 BTC"
     * Converter::crypto('invalid', 'BTC');                   // false
     * ```
     */
    public static function crypto(
        string|float|int $amount,
        string $currency = 'BTC',
        ?int $precision = null,
        bool $trimZeros = false
    ): string|false 
    {
        $currency = strtoupper(trim($currency));

        $decimals = $precision ?? (self::CRYPTO_CURRENCIES[$currency] ?? 8);
        $decimals = max(0, min($decimals, 36));

        $formatted = self::decimal($amount, $decimals, $trimZeros);

        return ($formatted === false) 
            ? false 
            : $formatted . ' ' . $currency;
    }

    /**
     * Formats a numeric value to a fixed number of decimal places without
     * floating-point conversion.
     *
     * Supports integer, floating-point, and decimal string values, including
     * scientific notation. Decimal strings are processed directly to preserve
     * precision for large or high-precision values.
     *
     * Values with more decimal places than requested are rounded half away
     * from zero. Trailing zeros can optionally be removed.
     *
     * @param string|float|int $amount Numeric value to format.
     * @param int $decimals Number of decimal places.
     * @param bool $trimZeros Whether to remove trailing decimal zeros.
     *
     * @return string|false Returns the formatted decimal value, or `false`
     *     if the value is invalid or cannot be represented safely.
     *
     * @example - Examples:
     * ```php
     * Converter::decimal('123.4567', 2);           // "123.46"
     * Converter::decimal('123.4500', 4, false);    // "123.4500"
     * Converter::decimal('123.4500', 4, true);     // "123.45"
     * Converter::decimal('1.0E-8', 10);            // "0.00000001"
     * Converter::decimal('123456789.123456789', 9); // "123456789.123456789"
     * ```
     */
    public static function decimal(
        string|float|int $amount,
        int $decimals,
        bool $trimZeros = true
    ): string|false 
    {
        if ($decimals < 0) {
            return false;
        }

        $parsed = self::parseDecimal($amount);

        if ($parsed === false) {
            return false;
        }

        [$negative, $int, $frac] = $parsed;

        [$int, $frac] = self::roundDecimal($int, $frac, $decimals);

        $frac = $trimZeros
            ? rtrim($frac, '0')
            : str_pad($frac, $decimals, '0');

        $result = ($frac === '')
            ? $int
            : $int . '.' . $frac;

        if ($negative && $result !== '0') {
            $result = '-' . $result;
        }

        return $result;
    }

    /**
     * Calculates the discounted value after applying a percentage discount.
     *
     * @param string|float|int $value Original value.
     * @param string|float|int $rate Discount rate as a percentage.
     * @param int|null $precision Number of decimal places to round the result.
     *
     * @return float Returns the value after applying the discount.
     *
     * @throws InvalidArgumentException If an input is non-numeric, the rate is
     *     negative, or the precision is invalid.
     *
     * @example - Examples:
     * ```php
     * Converter::discount(100, 10);       // 90.0
     * Converter::discount(250, 15, 2);    // 212.5
     * Converter::discount('99.99', '5');  // 94.9905
     * ```
     */
    public static function discount(
        string|float|int $value,
        string|float|int $rate,
        ?int $precision = null
    ): float 
    {
        return self::converter($value, $rate, $precision, 'subtraction');
    }

    /**
     * Calculates the value after applying a percentage interest.
     *
     * @param string|float|int $value Original value.
     * @param string|float|int $rate Interest rate as a percentage.
     * @param int|null $precision Number of decimal places to round the result.
     *
     * @return float Returns the value after applying the interest.
     *
     * @throws InvalidArgumentException If an input is non-numeric, the rate is
     *     negative, or the precision is invalid.
     *
     * @example - Examples:
     * ```php
     * Converter::interest(100, 10);       // 110.0
     * Converter::interest(250, 15, 2);    // 287.5
     * Converter::interest('99.99', '5');  // 104.9895
     * ```
     */
    public static function interest(
        string|float|int $value,
        string|float|int $rate,
        ?int $precision = null
    ): float 
    {
        return self::converter($value, $rate, $precision, 'addition');
    }

    /**
     * Calculates a percentage of a given value.
     *
     * Alias of {@see self::rate()}.
     *
     * @param string|float|int $rate Percentage rate.
     * @param string|float|int $of Base value used to calculate the percentage.
     * @param int|null $precision Number of decimal places to round the result.
     *
     * @return float Returns the calculated percentage value.
     *
     * @throws InvalidArgumentException If an input is non-numeric, the rate is
     *     negative, or the precision is invalid.
     *
     * @example - Examples:
     * ```php
     * Converter::percentage(10, 200);       // 20.0
     * Converter::percentage(15, 250, 2);    // 37.5
     * Converter::percentage('5', '99.99');   // 4.9995
     * ```
     */
    public static function percentage(
        string|float|int $rate,
        string|float|int $of,
        ?int $precision = null
    ): float 
    {
        return self::converter($of, $rate, $precision);
    }

    /**
     * Calculates a percentage of a given value.
     *
     * Alias of {@see self::percentage()}.
     *
     * @param string|float|int $rate Percentage rate.
     * @param string|float|int $of Base value used to calculate the percentage.
     * @param int|null $precision Number of decimal places to round the result.
     *
     * @return float Returns the calculated percentage value.
     *
     * @throws InvalidArgumentException If an input is non-numeric, the rate is
     *     negative, or the precision is invalid.
     *
     * @example - Examples:
     * ```php
     * Converter::rate(10, 200);       // 20.0
     * Converter::rate(15, 250, 2);    // 37.5
     * Converter::rate('5', '99.99');   // 4.9995
     * ```
     */
    public static function rate(
        string|float|int $rate,
        string|float|int $of,
        ?int $precision = null
    ): float 
    {
        return self::converter($of, $rate, $precision);
    }

    /**
     * Converts a distance value between supported units.
     *
     * Supported units:
     * - `MM` - Millimeters
     * - `CM` - Centimeters
     * - `M`  - Meters
     * - `KM` - Kilometers
     * - `IN` - Inches
     * - `FT` - Feet
     * - `YD` - Yards
     * - `MI` - Miles
     * - `NMI` - Nautical miles
     *
     * @param float|int $value Distance value to convert.
     * @param string|'MM'|'CM'|'M'|'KM'|'IN'|'FT'|'YD'|'MI'|'NMI' $from Source distance unit.
     * @param string|'MM'|'CM'|'M'|'KM'|'IN'|'FT'|'YD'|'MI'|'NMI' $to Target distance unit.
     * @param int $precision Number of decimal places for the result (default: 2).
     *
     * @return float Returns the converted distance.
     *
     * @example - Examples:
     * ```php
     * Converter::distance(10, 'KM', 'MI'); // 6.21
     * Converter::distance(1000, 'M', 'KM'); // 1
     * Converter::distance(12, 'IN', 'CM'); // 30.48
     * ```
     */
    public static function distance(
        float|int $value,
        string $from,
        string $to,
        int $precision = 2
    ): float 
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if (!isset(self::UNIT_DISTANCE[$from])) {
            throw new InvalidArgumentException(
                "Unsupported source distance unit: {$from}."
            );
        }

        if (!isset(self::UNIT_DISTANCE[$to])) {
            throw new InvalidArgumentException(
                "Unsupported target distance unit: {$to}."
            );
        }

        if ($from === $to) {
            return (float) $value;
        }

        $meters = $value * self::UNIT_DISTANCE[$from];
        $result = $meters / self::UNIT_DISTANCE[$to];

        return round($result, max(0, $precision));
    }

    /**
     * Converts a weight value between supported units.
     *
     * Supported units:
     * - `MG` - Milligrams
     * - `G` - Grams
     * - `KG` - Kilograms
     * - `T` - Tonnes
     * - `OZ` - Ounces
     * - `LB` - Pounds
     * - `ST` - Stones
     *
     * @param float|int $value Weight value to convert.
     * @param string|'MG'|'G'|'KG'|'T'|'OZ'|'LB'|'ST' $from Source weight unit.
     * @param string|'MG'|'G'|'KG'|'T'|'OZ'|'LB'|'ST' $to Target weight unit.
     * @param int $precision Number of decimal places for the result (default: 2).
     *
     * @return float Returns the converted weight.
     *
     * @example - Examples:
     * ```php
     * Converter::weight(1, 'KG', 'LB'); // 2.2
     * Converter::weight(10, 'LB', 'KG'); // 4.54
     * Converter::weight(1000, 'G', 'KG'); // 1
     * ```
     */
    public static function weight(
        float|int $value,
        string $from,
        string $to,
        int $precision = 2
    ): float 
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if (!isset(self::UNIT_WEIGHT[$from])) {
            throw new InvalidArgumentException(
                "Unsupported source weight unit: {$from}."
            );
        }

        if (!isset(self::UNIT_WEIGHT[$to])) {
            throw new InvalidArgumentException(
                "Unsupported target weight unit: {$to}."
            );
        }

        if ($from === $to) {
            return (float) $value;
        }

        $grams = $value * self::UNIT_WEIGHT[$from];
        $result = $grams / self::UNIT_WEIGHT[$to];

        return round($result, max(0, $precision));
    }

    /**
     * Calculates the distance between two geographic coordinates.
     *
     * Uses the Haversine formula to calculate the great-circle distance between
     * two points on the Earth's surface.
     *
     * @param float|string $originLat Latitude of the origin point.
     * @param float|string $originLng Longitude of the origin point.
     * @param float|string $destLat Latitude of the destination point.
     * @param float|string $destLng Longitude of the destination point.
     * @param string|'km'|'m'|'mi'|'nmi'|'yd'|'ft'|'cm' $unit Distance unit.
     *
     * @return float Returns the distance between the two points in the requested unit.
     * @throws InvalidArgumentException If the unit or coordinates are invalid.
     *
     * @example - Examples:
     * ```php
     * Converter::coordinates(6.5244, 3.3792, 9.0765, 7.3986);          // 537.97
     * Converter::coordinates(6.5244, 3.3792, 9.0765, 7.3986, 'mi');   // 334.27
     * Converter::coordinates('6.5244', '3.3792', '9.0765', '7.3986', 'km'); // 537.97
     * ```
     */
    public static function coordinates(
        float|string $originLat,
        float|string $originLng,
        float|string $destLat,
        float|string $destLng,
        string $unit = 'km'
    ): float 
    {
        $radius = self::EARTH_RADIUS[$unit] ?? null;

        if ($radius === null) {
            throw new InvalidArgumentException(
                "Unsupported distance unit '{$unit}'"
            );
        }

        $lat1 = deg2rad((float) $originLat);
        $lng1 = deg2rad((float) $originLng);
        $lat2 = deg2rad((float) $destLat);
        $lng2 = deg2rad((float) $destLng);

        $deltaLat = $lat2 - $lat1;
        $deltaLng = $lng2 - $lng1;

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $radius * $c;
    }

    /**
     * Convert a temperature value between supported units.
     *
     * Supports Celsius (`C`), Fahrenheit (`F`), and Kelvin (`K`).
     *
     * @param float|int $value Temperature value to convert.
     * @param 'C'|'F'|'K' $from Source temperature unit.
     * @param 'C'|'F'|'K' $to Target temperature unit.
     * @param int $precision Number of decimal places (default: 2).
     *
     * @return float Return the converted temperature.
     *
     * @example - Examples:
     * ```php
     * Converter::temperature(100, 'C', 'F'); // 212
     * Converter::temperature(32, 'F', 'C');  // 0
     * Converter::temperature(273.15, 'K', 'C'); // 0
     * ```
     */
    public static function temperature(
        float|int $value,
        string $from,
        string $to,
        int $precision = 2
    ): float 
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        // Convert to Celsius
        $celsius = match ($from) {
            'C' => $value,
            'F' => ($value - 32) * 5 / 9,
            'K' => $value - 273.15,
            default => throw new InvalidArgumentException(
                "Unsupported temperature origin unit: {$from}"
            )
        };

        // Convert from Celsius
        $result = match ($to) {
            'C' => $celsius,
            'F' => ($celsius * 9 / 5) + 32,
            'K' => $celsius + 273.15,
            default => throw new InvalidArgumentException(
                "Unsupported temperature destination unit: {$to}"
            )
        };

        if($from === $to){
            return (float) $value;
        }

        return round($result, max(0, $precision));
    }

    /**
     * Converts a hexadecimal string into its binary representation.
     *
     * @param string $hexStr The input string containing hexadecimal data.
     * @param string|null $destination Optional. If specified, saves the binary data to a file.
     *                                 - If it's a `file path`, the binary data is saved directly.
     *                                 - If it's a `directory`, a unique filename is generated.
     *
     * @return string|bool Return the binary string if no destination is provided.
     *                     If a file is written, returns `true` on success, `false` on failure.
	 * @throws RuntimeException Throws if an invalid hex is encountered.
     */
	public static function hexToBinary(string $hexStr, ?string $destination = null): string|bool 
	{
		$binary = '';
		$lines = explode("\n", trim($hexStr));
		
		foreach ($lines as $line) {
			if (preg_match('/:\s*([0-9A-Fa-f\s]+)/', $line, $matches)) {
				$hex = trim(preg_replace('/[^0-9A-Fa-f]/', '', $matches[1]));

				if (!ctype_xdigit($hex)) {
					throw new RuntimeException("Invalid hexadecimal string: {$hex}", ErrorCode::INVALID);
				}				

				if (strlen($hex) % 2 !== 0) {
					$hex = '0' . $hex;
				}

				$bin = hex2bin($hex);
				if ($binary === false) {
					throw new RuntimeException('hexadecimal to binary conversion failed.');
				}

				$binary .= $bin;
			}
		}

		if (!$destination) {
			return $binary;
		}

        return self::writeBinary($binary, $destination);
    }

    /**
     * write converted binary to file.
     *
     * @param string $binary
     * @param string $destination
     * 
     * @return bool
     */
    private static function writeBinary(string $binary, string $destination): bool
    {
		if (
            str_ends_with($destination, DIRECTORY_SEPARATOR) 
            || !preg_match('/\.\w+$/', $destination)
        ) {
			$destination = Luminova::root($destination);
			
			Filesystem::mkdir($destination);

			do {
				$filename = 'bin_' . bin2hex(random_bytes(3));
				$filePath = "{$destination}{$filename}";
			} while (is_file($filePath));

			$destination = "{$filePath}." . self::getBinaryExtension($binary, $filePath);
		}

		return Filesystem::write($destination, $binary);
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
     * @param string|float|int $value The original value to adjust.
     * @param string|float|int $rate The percentage rate to apply.
     * @param int|null $precision Optional number of decimal places to round the result.
     * @param string|null $apply Type of adjustment: 'subtraction', 'addition', 
     *                  or null to get just the percentage.
     * @param bool $finite whether to check if value or rate is a legal finite number.
     *
     * @return float Returns the adjusted value or the raw percentage if $type is null.
     * @throws InvalidArgumentException If non-numeric inputs, negative rates, or invalid precision.
     */
    private static function converter(
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

    /**
     * Determines the file extension based on the binary data using MIME detection and magic numbers.
     *
     * @param string $binaryData  The raw binary data.
     * @param string $destination The temporary file location for MIME type detection.
     *
     * @return string Return the detected file extension (e.g., 'png', 'jpg', 'zip').
     *                Returns 'bin' if no known extension is found.
     */
	private static function getBinaryExtension(string $binaryData, string $destination): string 
	{
		$destination = "{$destination}-hex";
		$mime = Mime::guess($binaryData);

		if($mime === false && Filesystem::write($destination, $binaryData)){
			$mime = Mime::guess($destination);
			unlink($destination);
		}
		

		$extension = $mime ? (Mime::findExtension($mime) ?: false) : false;

		if ($extension) {
			return $extension;
		}

		foreach (self::MAGIC_NUMBERS as $signature => $ext) {
			if (strncmp($binaryData, $signature, strlen($signature)) === 0) {
				return $ext;
			}
		}

		return 'bin';
	}

    /**
     * Parses a numeric value into its sign, integer part, and fraction part.
     *
     * @return array{0:bool, 1:string, 2:string}|false
     */
    private static function parseDecimal(string|float|int $amount): array|false
    {
        if (is_float($amount)) {
            if (!is_finite($amount)) {
                return false;
            }

            $amount = var_export($amount, true);
        }

        $amount = trim((string) $amount);

        if (!preg_match(
            '/^([+-]?)(?=\d|\.\d)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/',
            $amount,
            $matches
        )) {
            return false;
        }

        $negative = $matches[1] === '-';
        $int      = $matches[2];
        $frac     = $matches[3] ?? '';
        $exp      = (int) ($matches[4] ?? 0);

        if (abs($exp) > 1000) {
            return false;
        }

        if ($exp !== 0) {
            [$int, $frac] = self::expandScientificNotation($int, $frac, $exp);
        }

        $int = ltrim($int, '0');

        if ($int === '') {
            $int = '0';
        }

        return [$negative, $int, $frac];
    }

    /**
     * Expands a decimal value written in scientific notation.
     *
     * @return array{0:string, 1:string}
     */
    private static function expandScientificNotation(
        string $int,
        string $frac,
        int $exp
    ): array 
    {
        $digits   = $int . $frac;
        $pointPos = strlen($int) + $exp;

        if ($pointPos <= 0) {
            return [
                '0',
                str_repeat('0', -$pointPos) . $digits,
            ];
        }

        if ($pointPos >= strlen($digits)) {
            return [
                $digits . str_repeat('0', $pointPos - strlen($digits)),
                '',
            ];
        }

        return [
            substr($digits, 0, $pointPos),
            substr($digits, $pointPos),
        ];
    }

    /**
     * Rounds a decimal value to the requested number of decimal places.
     *
     * @return array{0:string, 1:string}
     */
    private static function roundDecimal(
        string $int,
        string $frac,
        int $decimals
    ): array 
    {
        if (strlen($frac) <= $decimals) {
            return [$int, $frac];
        }

        $roundUp = $frac[$decimals] >= '5';
        $frac    = substr($frac, 0, $decimals);

        if (!$roundUp) {
            return [$int, $frac];
        }

        $digits = $int . $frac;
        $i      = strlen($digits) - 1;

        while ($i >= 0 && $digits[$i] === '9') {
            $digits[$i--] = '0';
        }

        if ($i >= 0) {
            $digits[$i] = (string) ((int) $digits[$i] + 1);
        } else {
            $digits = '1' . $digits;
        }

        $split = strlen($digits) - $decimals;

        return [
            substr($digits, 0, $split),
            substr($digits, $split),
        ];
    }
}