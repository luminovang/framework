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
use Luminova\Utility\Mime;
use Luminova\Storage\Filesystem;
use function Luminova\Funcs\root;
use Luminova\Exceptions\{ErrorCode, RuntimeException, InvalidArgumentException};

/**
 * Class Converter
 *
 * Provides a set of static utility methods for common mathematical operations.
 */
final class Converter
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
	 * Binary magic numbers.
	 * 
	 * @var array<string,string> $magicNumbers
	 */
	private static array $magicNumbers = [
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
     * @param string $size The string representation of the byte size (e.g., `1KB`, `2MB`, `1.5GB`).
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
     * Converter::rating(5, 42.5, true)
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
    public static function currency(
        float $number, 
        string $code = 'USD', 
        ?string $locale = null
    ): string|bool
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
        return self::converter(
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
        return self::converter(
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
        return self::converter(
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
        return self::converter(
            $of, 
            $rate, 
            $precision
        );
    }

    /**
     * Convert a distance value between supported units.
     *
     * Supports millimeters (`MM`), centimeters (`CM`), meters (`M`),
     * kilometers (`KM`), inches (`IN`), feet (`FT`), yards (`YD`),
     * and miles (`MI`).
     *
     * @param float|int $value Distance value to convert.
     * @param string $from Source distance unit.
     * @param string $to Target distance unit.
     * @param int $precision Number of decimal places (default: 2).
     *
     * @return float Return the converted distance.
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
        $units = [
            'MM' => 0.001,
            'CM' => 0.01,
            'M'  => 1,
            'KM' => 1000,
            'IN' => 0.0254,
            'FT' => 0.3048,
            'YD' => 0.9144,
            'MI' => 1609.344,
        ];

        $from = strtoupper($from);
        $to = strtoupper($to);

        if (!isset($units[$from], $units[$to])) {
            throw new InvalidArgumentException("Unsupported distance unit.");
        }

        $meters = $value * $units[$from];
        $result = $meters / $units[$to];

        return round($result, max(0, $precision));
    }

    
    /**
     * Convert a weight value between supported units.
     *
     * Supports milligrams (`MG`), grams (`G`), kilograms (`KG`),
     * tonnes (`T`), ounces (`OZ`), pounds (`LB`), and stones (`ST`).
     *
     * @param float|int $value Weight value to convert.
     * @param string $from Source weight unit.
     * @param string $to Target weight unit.
     * @param int $precision Number of decimal places (default: 2).
     *
     * @return float Return the converted weight.
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
        $units = [
            'MG' => 0.001,
            'G'  => 1,
            'KG' => 1000,
            'T'  => 1000000,
            'OZ' => 28.349523125,
            'LB' => 453.59237,
            'ST' => 6350.29318,
        ];

        $from = strtoupper($from);
        $to = strtoupper($to);

        if (!isset($units[$from], $units[$to])) {
            throw new InvalidArgumentException("Unsupported weight unit.");
        }

        $grams = $value * $units[$from];
        $result = $grams / $units[$to];

        return round($result, max(0, $precision));
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
    public static function coordinates(
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
     * Convert a temperature value between supported units.
     *
     * Supports Celsius (`C`), Fahrenheit (`F`), and Kelvin (`K`).
     *
     * @param float|int $value Temperature value to convert.
     * @param string $from Source temperature unit.
     * @param string $to Target temperature unit.
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
            default => throw new InvalidArgumentException("Unsupported temperature unit: {$from}")
        };

        // Convert from Celsius
        $result = match ($to) {
            'C' => $celsius,
            'F' => ($celsius * 9 / 5) + 32,
            'K' => $celsius + 273.15,
            default => throw new InvalidArgumentException("Unsupported temperature unit: {$to}")
        };

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
			$destination = root($destination);
			
			Filesystem::mkdir($destination);

			do {
				$filename = 'bin_' . bin2hex(random_bytes(3));
				$filePath = "{$destination}{$filename}";
			} while (file_exists($filePath));

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

		foreach (self::$magicNumbers as $signature => $ext) {
			if (strncmp($binaryData, $signature, strlen($signature)) === 0) {
				return $ext;
			}
		}

		return 'bin';
	}
}