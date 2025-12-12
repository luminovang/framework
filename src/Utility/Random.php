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

use \ValueError;
use \OverflowException;
use Luminova\Exceptions\InvalidArgumentException;

final Class Random
{
    /**
     * Maximum signed 8-bit integer value.
     */
    public const INT8_MAX = 127;

    /**
     * Maximum unsigned 8-bit integer value.
     */
    public const UINT8_MAX = 255;

    /**
     * Minimum signed 16-bit integer value.
     */
    public const INT16_MIN = -32768;

    /**
     * Maximum signed 16-bit integer value.
     */
    public const INT16_MAX = 32767;

    /**
     * Maximum unsigned 16-bit integer value.
     */
    public const UINT16_MAX = 65535;

    /**
     * Minimum signed 32-bit integer value.
     */
    public const INT32_MIN = -2147483648;

    /**
     * Maximum signed 32-bit integer value.
     */
    public const INT32_MAX = 2147483647;

    /**
     * Minimum unsigned 32-bit integer value.
     */
    public const UINT32_MIN = 0;

    /**
     * Maximum unsigned 32-bit integer value.
     */
    public const UINT32_MAX = 4294967295;

    /**
     * Minimum signed 64-bit integer value.
     */
    public const INT64_MIN = '-9223372036854775808';

    /**
     * Maximum signed 64-bit integer value.
     *
     * Uses the platform's maximum PHP integer value.
     */
    public const INT64_MAX = PHP_INT_MAX;

    /**
     * Minimum unsigned 64-bit integer value.
     */
    public const UINT64_MIN = '0';

    /**
     * Maximum unsigned 64-bit integer value.
     *
     * Stored as a string because the value exceeds PHP_INT_MAX.
     */
    public const UINT64_MAX = '18446744073709551615';

    /**
     * MAX_UNSIGNED_INT_64BIT
     * 
     * @var string UINT64_SIZE
     */
    private const UINT64_SIZE = '18446744073709551616';

    /**
     * MAX_UNSIGNED_INT_32BIT
     * 
     * @var string UINT32_SIZE
     */
    private const UINT32_SIZE = '4294967296';

    /**
	 * Integer characters values (0-9).
	 * 
	 * @var string RAND_INTEGERS
	 */
	private const RAND_INTEGERS = '0123456789';

    /**
     * Generate random characters from alphabet letters only.
     */
    public const TYPE_ALPHABET = 'alphabet';

    /**
     * Generate random characters from numeric digits only.
     */
    public const TYPE_INTEGER = 'integer';

    /**
     * Generate random characters from special characters only.
     */
    public const TYPE_CHARACTER = 'character';

    /**
     * Generate random characters using letters and numbers.
     */
    public const TYPE_ALPHANUMERIC = 'alphanumeric';

    /**
     * Generate random characters suitable for passwords.
     *
     * Includes letters, numbers, and password-safe special characters.
     */
    public const TYPE_PASSWORD = 'password';

    /**
     * Generate cryptographically secure random bytes.
     */
    public const TYPE_BYTES = 'bytes';

    /**
     * Generate random hexadecimal characters.
     *
     * Each byte is represented as two hexadecimal characters.
     */
    public const TYPE_HEX = 'hex';

    /**
     * Generate random characters using all available characters.
     *
     * Includes letters, numbers, and all special characters.
     */
    public const TYPE_DEFAULT = 'default';

	/**
	 * Uppercase alphabet format (A-Z).
	 * 
	 * @var string RAND_UPPER_ALPHABET
	 */
	private const RAND_UPPER_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

	/**
	 * Mixed alphabet format (a-zA-Z).
	 * 
	 * @var string RAND_MIXED_ALPHABET
	 */
	private const RAND_MIXED_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	/**
	 * Special characters format.
	 * 
	 * @var string RAND_SPECIAL_CHARS
	 */
	private const RAND_SPECIAL_CHARS = '%#*^,?+$`;"{}][|\/:=)(@!.-';

	/**
	 * Password supported special characters format.
	 * 
	 * @var string RAND_PASSWORD_CHARS
	 */
	private const RAND_PASSWORD_CHARS = '%#^_-@!$&*+=|~?<>[]{}()';

	/**
	 * Last generated random bytes timestamp.
	 * 
	 * @var int $lastRandomTimestamp 
	 */
	private static int $lastRandomTimestamp = 0;

    /**
     * Last random integer
     *
     * @var int $lastRandomInteger
     */
    private static int $lastRandomInteger = 0;

	/**
	 * Last generated random bytes value.
	 * 
	 * @var string $lastRandomBytes
	 */
	private static string $lastRandomBytes = '';

    /**
     * Generate cryptographically secure random bytes.
     *
     * When monotonic mode is enabled, bytes are generated with timestamp tracking
     * and incremented within the same millisecond to reduce collision risk.
     *
     * @param int $length Number of bytes to generate.
     * @param bool $monotonic Whether to enable monotonic byte generation (default: false).
     * @param int|null $timestamp The monotonic generation timestamp return by reference.
     *
     * @return string Returns raw bytes value.
     */
    public static function bytes(
        int $length = 10,
        bool $monotonic = false,
        ?int &$timestamp = null
    ): string 
    {
        $timestamp = null;

        if (!$monotonic) {
            return random_bytes($length);
        }

        $timestamp = (int) floor(microtime(true) * 1000);
        $bytes = random_bytes($length);

        if ($timestamp === self::$lastRandomTimestamp) {
            $bytes = self::incrementBytes(self::$lastRandomBytes);
        }

        self::$lastRandomTimestamp = $timestamp;
        self::$lastRandomBytes = $bytes;

        return $bytes;
    }

    /**
     * Generate cryptographically secure random hexadecimal characters.
     *
     * When monotonic mode is enabled, generated bytes are incremented within
     * the same millisecond to reduce collision risk and includes the generation timestamp.
     *
     * @param int $length Number of random bytes to generate.
     * @param bool $monotonic Whether to enable monotonic byte generation (default: false).
     * @param int|null $timestamp The monotonic generation timestamp return by reference.
     *
     * @return string Returns a hexadecimal string  value .
     */
    public static function hex(
        int $length = 10,
        bool $monotonic = false,
        ?int &$timestamp = null
    ): string 
    {
        $timestamp = null;
        $bytes = self::bytes($length, $monotonic, $timestamp);

        return bin2hex($bytes);
    }

    /**
     * Generate cryptographically secure random binary data.
     *
     * When monotonic mode is enabled, generated bytes are incremented within
     * the same millisecond to reduce collision risk and includes the generation timestamp.
     *
     * @param int $length Number of random bytes to generate.
     * @param bool $monotonic Whether to enable monotonic byte generation (default: false).
     * @param int|null $timestamp The monotonic generation timestamp return by reference.
     *
     * @return string Returns raw binary data.
     */
    public static function binary(
        int $length = 10,
        bool $monotonic = false,
        ?int &$timestamp = null
    ): string 
    {
        $timestamp = null;
        return self::bytes($length, $monotonic, $timestamp);
    }

    /**
     * Generate a cryptographically secure random integer.
     *
     * When monotonic mode is enabled, generated values are guaranteed to increase
     * within the same millisecond and include the generation timestamp.
     *
     * @param int $min Minimum possible value (default: `Random::UINT32_MIN`).
     * @param int $max Maximum possible value (default: `Random::UINT32_MAX`).
     * @param bool $monotonic Whether to enable monotonic generation (default: false).
     * @param int|null $timestamp The monotonic generation timestamp return by reference.
     *
     * @return int Returns an integer value.
     * @throws ValueError If max value exceeds PHP_INT_MAX
     * 
     * @example - Examples:
     * ```php
     * // Generates a random, 32-bit unsigned integer.
     * $int = Random::integer(Random::UINT32_MIN, Random::UINT32_MAX);
     * ```
     */
    public static function integer(
        string|int $min = self::UINT32_MIN,
        string|int $max = self::UINT32_MAX,
        bool $monotonic = false,
        ?int &$timestamp = null
    ): int 
    {
        self::assertInteger($min, $max);

        $min = (int) $min;
        $max = (int) $max;

        $timestamp = null;
        $value = random_int($min, $max);

        if (!$monotonic) {
            return $value;
        }

        $timestamp = (int) floor(microtime(true) * 1000);

        if ($timestamp === self::$lastRandomTimestamp) {
            if (self::$lastRandomInteger >= $max) {
                throw new OverflowException(
                    'Unable to generate a monotonic integer within the specified range.'
                );
            }

            $value = (self::$lastRandomInteger < $max)
                ? self::$lastRandomInteger + 1
                : $value;
        }

        self::$lastRandomTimestamp = $timestamp;
        self::$lastRandomInteger = $value;

        return $value;
    }

    /**
     * Generates a random 64-bit integer within the specified range.
     *
     * Unsigned 64-bit range:
     * 0 to 18,446,744,073,709,551,615
     *
     * Signed 64-bit range:
     * -9,223,372,036,854,775,808 to 9,223,372,036,854,775,807
     *
     * Values are returned as strings to preserve the full 64-bit range.
     *
     * @param string|null $min The minimum value (default: `self::UINT64_MIN`).
     * @param string|null $max The maximum value (default: `self::UINT64_MAX`).
     *
     * @return string Returns the generated random 64-bit integer as a string.
     * 
     * @see self::unsignedBigInteger()
     * @see self::signedBigInteger()
     */
    public static function bigInteger(?string $min = null, ?string $max = null): string 
    {
        static $gmp = null;
        $assert = ($max !== null || $max !== null);

        $min ??= self::UINT64_MIN;
        $max ??= self::UINT64_MAX;

        if($assert){
            self::assertBigint($min, $max);
        }

        $gmp ??= extension_loaded('gmp');

        return $gmp 
            ? self::bigIntegerGmp($min, $max) 
            : self::bigIntegerBc($min, $max);
    }

    /**
     * Generates a random unsigned 64-bit integer.
     *
     * @return string Returns a random unsigned 64-bit integer as a string.
     */
    public static function unsignedBigInteger(): string 
    {
        return self::bigInteger(self::UINT64_MIN, self::UINT64_MAX);
    }

    /**
     * Generates a random signed 64-bit integer.
     *
     * @return string Returns a random signed 64-bit integer as a string.
     */
    public static function signedBigInteger(): string 
    {
        return self::bigInteger(self::INT64_MIN, self::INT64_MAX);
    }

    /**
     * Generates a cryptographically secure random string.
     *
     * Supports character-based output, raw binary bytes, and hexadecimal output.
     * Character-based output is generated using `random_int()`, while byte-based
     * output uses `random_bytes()`.
     *
     * Supported types:
     * - `character` - Letters, numbers, and special characters.
     * - `alphanumeric` - Uppercase/lowercase letters and numbers.
     * - `alphabet` - Uppercase/lowercase letters only.
     * - `password` - Letters, numbers, and password-safe special characters.
     * - `bytes` - Raw binary random bytes.
     * - `hex` - Random bytes encoded as hexadecimal characters.
     * - `int`|`integer` - Numeric characters (`0-9`) only.
     * - `default`  - Includes letters, numbers, and special characters.
     *
     * For `hex`, `$length` specifies the number of characters in the final
     * hexadecimal string, not the number of random bytes.
     *
     * @param int $length Length of the generated output.
     * @param string|'character'|'alphanumeric'|'alphabet'|'password'|'bytes'|'hex'|'int'|'integer'|'default' $type Output type: `integer`, or `Random::TYPE_*`.
     * @param bool $uppercase Whether alphabetic characters should be uppercase.
     *
     * @return string Returns the generated random string.
     *
     * @example - Examples:
     * ```php
     * Random::generate(16, Random::TYPE_PASSWORD);        // 16-character password.
     * Random::generate(8,  Random::TYPE_ALPHABET, true);   // 8 uppercase letters.
     * Random::generate(32, Random::TYPE_HEX);              // 32 hexadecimal characters.
     * ```
     */
	public static function generate(
		int $length = 10,
		string $type = self::TYPE_INTEGER,
		bool $uppercase = false
	): string 
	{
		if ($length < 1) {
			return '';
		}

		$type = strtolower($type);

		if ($type === self::TYPE_BYTES || $type === self::TYPE_HEX) {
			$bytes = self::bytes((int) ceil($length / 2), true);

			return ($type === self::TYPE_HEX)
				? substr(bin2hex($bytes), 0, $length)
				: substr($bytes, 0, $length);
		}

		$alphabets = $uppercase 
            ? self::RAND_UPPER_ALPHABET 
            : self::RAND_MIXED_ALPHABET;

		$pool = match ($type) {
			self::TYPE_ALPHABET     => $alphabets,
			self::TYPE_INTEGER      => self::RAND_INTEGERS,
			self::TYPE_CHARACTER    => self::RAND_SPECIAL_CHARS,
			self::TYPE_ALPHANUMERIC => $alphabets . self::RAND_INTEGERS,
			self::TYPE_PASSWORD     => $alphabets . self::RAND_INTEGERS . self::RAND_PASSWORD_CHARS,
			default                 => $alphabets . self::RAND_INTEGERS . self::RAND_SPECIAL_CHARS,
		};

		$len = strlen($pool);
		$out = '';

		for ($i = 0; $i < $length; $i++) {
			$out .= $pool[random_int(0, $len - 1)];
		}

		return $out;
	}

    /**
     * Generate a random password.
     *
     * @param integer $length The max length.
     * 
     * @return string Return generated password.
     */
    public static function password(int $length = 8): string 
	{
		return self::generate($length, self::TYPE_PASSWORD);
	}
	
	/** 
	 * Generate product EAN13 id.
	 * 
	 * @param int $country start prefix country code.
	 * @param int $length maximum length.
	 * 
	 * @return string Return the generated product ean code.
	 */
	public static function ean(int $country = 615, int $length = 13): string 
	{
		return self::upc($country, $length);
	}

	/**
	 * Generate a product UPC ID.
	 *
	 * @param int $prefix Start prefix number.
	 * @param int $length Maximum length.
	 * 
	 * @return string Return the generated UPC ID.
	 */
	public static function upc(int $prefix = 0, int $length = 12): string 
	{
		$length -= strlen((string)$prefix) + 1;
		$randomPart = self::generate($length);
		
		$code = $prefix . str_pad($randomPart, $length, '0', STR_PAD_LEFT);
		
		$sum = 0;
		$weightFlag = true;
		
		for ($i = strlen($code) - 1; $i >= 0; $i--) {
			$digit = (int)$code[$i];
			$sum += $weightFlag ? $digit * 3 : $digit;
			$weightFlag = !$weightFlag;
		}
		
		$checksumDigit = (10 - ($sum % 10)) % 10;
		
		return $code . $checksumDigit;
	}

	/**
	 * Increment 80-bit binary value.
	 *
	 * Treats binary string as big-endian counter.
	 * Rolls over if maximum value is reached.
	 *
	 * @param string $bytes 10-byte binary string.
	 *
	 * @return string Incremented 10-byte binary string.
	 */
	private static function incrementBytes(string $bytes): string
	{
		$len = strlen($bytes);

		for ($i = $len - 1; $i >= 0; $i--) {
			$val = ord($bytes[$i]) + 1;

			if ($val > 255) {
				$bytes[$i] = chr(0);
			} else {
				$bytes[$i] = chr($val);
				return $bytes;
			}
		}

		return random_bytes(10);
	}

     /**
     * Generate big in using BC math.
     *
     * @param string $min
     * @param string $max
     * 
     * @return string
     */
    private static function bigIntegerBc(string $min, string $max): string
    {
        $range = bcadd(bcsub($max, $min), '1');
        $maxUint64 = bcadd(self::UINT64_MAX, '1');

        if(bccomp($range, $maxUint64) === 0){
            return bcadd(
                self::bytesToDecimal(random_bytes(8)),
                $min
            );
        }

        $limit = bcmul(
            bcdiv($maxUint64, $range, 0),
            $range
        );

        do {
            $random = self::bytesToDecimal(random_bytes(8));
        } while (bccomp($random, $limit) >= 0);

        return bcadd(bcmod($random, $range), $min);
    }

    /**
     * Convert bytes to decimal.
     *
     * @param string $bytes
     * @return string
     */
    private static function bytesToDecimal(string $bytes): string
    {
        $value = '0';

        foreach (unpack('C*', $bytes) as $byte) {
            $value = bcmul($value, '256');
            $value = bcadd($value, (string) $byte);
        }

        return $value;
    }

    /**
     * Generate big in using GMP.
     *
     * @param string $min
     * @param string $max
     * 
     * @return string
     */
    private static function bigIntegerGmp(string $min, string $max): string
    {
        $minG = gmp_init($min, 10);
        $maxG = gmp_init($max, 10);
        $range = gmp_add(gmp_sub($maxG, $minG), 1);
        $maxUint64 = gmp_init(self::UINT64_SIZE, 10);

        if (gmp_cmp($range, $maxUint64) === 0) {
            return gmp_strval(
                gmp_add(self::bytesToGmp64(), $minG),
                10
            );
        }

        $limit = gmp_mul(
            gmp_div($maxUint64, $range),
            $range
        );

        do {
            $random = self::bytesToGmp64();
        } while (gmp_cmp($random, $limit) >= 0);

        return gmp_strval(
            gmp_add(gmp_mod($random, $range), $minG),
            10
        );
    }

    /**
     * Generate random GMP bytes.
     *
     * @return \GMP
     */
    private static function bytesToGmp64(): \GMP
    {
        $bytes = random_bytes(8);
        $hi = unpack('N', substr($bytes, 0, 4))[1];
        $lo = unpack('N', substr($bytes, 4, 4))[1];

        return gmp_add(
            gmp_mul($hi, self::UINT32_SIZE),
            $lo
        );
    }

    /**
     * Assert bigint min/max values.
     *
     * @param string $min
     * @param string $max
     * 
     * @return void
     */
    private static function assertBigint(
        string $min,
        string $max
    ): void 
    {
        if (!ctype_digit(ltrim($min, '-'))) {
            throw new InvalidArgumentException(
                "Big integer minimum '{$min}' must be a valid integer string."
            );
        }

        if (!ctype_digit(ltrim($max, '-'))) {
            throw new InvalidArgumentException(
                "Big integer maximum '{$max}' must be a valid integer string."
            );
        }

        self::assertMaxValue($min, $max);
    }

    /**
     * Assert integer min and max values.
     *
     * @param string|int $min
     * @param string|int $max
     * 
     * @return void
     */
    private static function assertInteger(string|int $min, string|int $max): void 
    {
        if (!is_numeric($min) || !is_numeric($max)) {
            throw new InvalidArgumentException(
                'Minimum and maximum values must be numeric.'
            );
        }

        self::assertMaxValue($min, $max);

        if ((int) $max > self::INT64_MAX) {
            throw new ValueError('Maximum integer value cannot exceed PHP_INT_MAX.');
        }
    }

    /**
     * Assert min exceed max value.
     *
     * @param string|int $min
     * @param string|int $max
     * 
     * @return void
     */
    private static function assertMaxValue(string|int $min, string|int $max): void 
    {
        if (bccomp($min, $max) > 0) {
            throw new InvalidArgumentException(
                "Minimum value '{$min}' cannot be greater than maximum value '{$max}'."
            );
        }
    }
}