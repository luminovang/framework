<?php
/**
 * Luminova Framework, Fluent rule builder for input validation.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security;

use Luminova\Exceptions\InvalidArgumentException;

/**
 * @see Luminova\Security\Validation
 */
final class Rule
{
    /**
     * Initialize a new Rule instance.
     */
    private function __construct(){}

    /**
     * Build a validation rule definition.
     *
     * This method creates a structured rule array used by the validation engine.
     * It supports passing optional arguments and a custom error message.
     *
     * If no error message is provided, a default message is generated using:
     * "The {field} is invalid ({rule})."
     *
     * @param string $rule Rule name (e.g. required, minLength, notEqual)
     * @param array|null $argument Optional rule arguments (e.g. [5], ['a', 'b'])
     * @param string|null $error Custom validation error message
     *
     * @return array{0: string, 1: array|null, 2: string} Return an array that resolve to rule.
     * @throws InvalidArgumentException If invalid rule name.
     *
     * @example - Example:
     * ```php
     * Rule::for('minLength', [6], 'Must be at least 6 characters.')
     * Rule::for('required', null)
     * ```
     */
    public static function for(string $rule, ?array $argument, ?string $error = null): array
    {
        if(!self::isRule($rule)){
            throw new InvalidArgumentException(sprintf(
                'Invalid unsupported rule name: %s.',
                $rule,
            ));
        }

        return [$rule, $argument, $error ?? "The {field} is invalid ({$rule})."];
    }

    /**
     * Check if a validation rule exists.
     *
     * This method does not perform validation. It only verifies whether
     * the given rule name is supported by the validator.
     *
     * @param string $name Rule name.
     * 
     * @return bool True if rule exists, false otherwise.
     */
    public static function isRule(string $name): bool 
    {
        return match ($name) {
            'required', 'callback', 'match', 'regex',
            'equals', 'not_equal', 'is_value', 'is_list',
            'in_array', 'username', 'key_exists', 'keys_exists',
            'default', 'fallback', 'nullable', 'name',
            'string', 'between', 'lat', 'latitude',
            'lng', 'longitude', 'latlng', 'phone',
            'uuid', 'ip', 'numeric', 'integer', 'luhn',
            'digit', 'float', 'email', 'alphanumeric',
            'alphabet', 'url', 'decimal', 'binary',
            'boolean', 'hexadecimal', 'array', 'json',
            'scheme', 'length', 'minlength', 'maxlength',
            'limit', 'minlimit', 'maxlimit',
            'size', 'minsize', 'maxsize',
            'min', 'max', 'fixed' => true,
            default => false
        };
    }

    /**
     * Require that the field is not empty or null.
     * 
     * Rule name: `required`
     *
     * Fails when the value is an empty string, `null`, or otherwise
     * considered empty by the validator's internal `isEmpty` check.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function required(?string $error = null): array
    {
        return ['required', null, $error ?? 'The {field} field is required.'];
    }

    /**
     * Indicate that the field can be empty or null.
     * 
     * Rule name: `nullable`
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function nullable(): array
    {
        return ['nullable', null, null];
    }

    /**
     * Require that the value is a PHP string.
     * 
     * Rule name: `string`
     *
     * Fails when `is_string($value)` returns `false`.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function isString(?string $error = null): array
    {
        return ['string', null, $error ?? 'The {field} is not a valid required data type.'];
    }

    /**
     * Require that the value is numeric.
     * 
     * Rule name: `numeric`
     *
     * Delegates to PHP's `is_numeric()`, so integer strings and
     * float strings both pass.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function numeric(?string $error = null): array
    {
        return ['numeric', null, $error ?? 'The {field} must be a numeric value.'];
    }

    /**
     * Require that the value is a valid boolean representation.
     * 
     * Rule name: `boolean`
     *
     * Accepts `true`, `false`, `1`, `0`, `"true"`, `"false"`, `"yes"`,
     * `"no"`, etc., as determined by the validator.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function boolean(?string $error = null): array
    {
        return ['boolean', null, $error ?? 'The {field} must be a boolean (true, false, 1, or 0).'];
    }

    /**
     * Require that the value is an integer.
     * 
     * Rule name: `integer`
     *
     * An optional type qualifier constrains the integer further:
     * - `positive` — value must be greater than zero.
     * - `negative` — value must be less than zero.
     * - `unsigned` — value must be greater or equals to zero.
     * - `null`     — any valid integer.
     *
     * Values containing a decimal point (`.`) are always rejected.
     *
     * @param string|null $type Optional sub-type: `'positive'`, `'negative'`, `unsigned`, or `null` for any.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function integer(?string $type = 'unsigned', ?string $error = null): array
    {
        return ['integer', [$type ?? 'any'], $error ?? 'The {field} is not a valid integer values.'];
    }

    /**
     * Require that the value is a valid Luhn number.
     * 
     * Rule name: `luhn`
     *
     * Delegates to the Luhn checksum algorithm. The value must be a string
     * containing digits, and the checksum must validate according to the
     * Luhn formula.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function luhn(?string $error = null): array
    {
        return ['luhn', null, $error ?? 'The {field} is not a valid Luhn number.'];
    }

    /**
     * Require that the value consists of decimal digits only.
     * 
     * Rule name: `digit`
     *
     * Delegates to `ctype_digit()`. Floats, negative numbers, and
     * leading/trailing spaces will fail.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function digit(?string $error = null): array
    {
        return ['digit', null, $error ?? 'The {field} must contain digits only.'];
    }

    /**
     * Require that the value is a floating-point number.
     * 
     * Rule name: `float`
     *
     * An optional type qualifier constrains the float:
     * - `positive` — value must be greater than zero.
     * - `negative` — value must be less than zero.
     * - `unsigned` — value must be greater or equals to zero.
     * - `any`      — any float (default).
     *
     * Values containing scientific notation (e.g. `1e5`) are rejected.
     * Integer values without a decimal point are also rejected.
     *
     * @param string|null $type Optional sub-type: `'positive'`, `'negative'`, `unsigned`, or `null` for any.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function isFloat(?string $type = 'unsigned', ?string $error = null): array
    {
        return ['float', [$type ?? 'any'], $error ?? 'The {field} must be a valid floating-point number.'];
    }

    /**
     * Require that the value is a decimal number (must contain a decimal point).
     * 
     * Rule name: `decimal`
     *
     * Behaves like {@see self::isFloat()} but additionally requires the value
     * to contain a `.` character, ruling out plain integers.
     *
     * An optional type qualifier constrains the result:
     * - `positive` — value must be greater than zero.
     * - `negative` — value must be less than zero.
     * - `unsigned` — value must be greater or equals to zero.
     * - `null`     — any decimal (default).
     *
     * @param string|null $type Optional sub-type: `'positive'`, `'negative'`, `unsigned`, or `null` for any.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function decimal(?string $type = 'unsigned', ?string $error = null): array
    {
        return ['decimal', [$type ?? 'any'], $error ?? 'The {field} must be a valid decimal number.'];
    }

    /**
     * Require that the value is alphanumeric.
     * 
     * Rule name: `alphanumeric`
     *
     * Delegates to `ctype_alnum()`. The value must be a non-empty string
     * containing only ASCII letters (`A-Z`, `a-z`) and digits (`0-9`).
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function alphanumeric(?string $error = null): array
    {
        return ['alphanumeric', null, $error ?? 'The {field} must contain letters and numbers only.'];
    }

    /**
     * Require that the value contains alphabetic characters only.
     * 
     * Rule name: `alphabet`
     *
     * Delegates to `ctype_alpha()`. Digits, spaces, and special characters
     * will cause validation to fail.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function alphabet(?string $error = null): array
    {
        return ['alphabet', null, $error ?? 'The {field} must contain letters only.'];
    }

    /**
     * Require that the value is a valid e-mail address.
     * 
     * Rule name: `email`
     *
     * Optionally reject specific domains and/or allow internationalized
     * domain names (IDN).
     *
     * @param array $rejectDomains List of domain strings to reject (e.g. `['example.com']`).
     * @param bool $allowIdn Whether to accept IDN e-mail addresses (default `false`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function email(array $rejectDomains = [], bool $allowIdn = false, ?string $error = null): array
    {
        return ['email', [$rejectDomains, $allowIdn], $error ?? 'The "{value}" is not a valid email address.'];
    }

    /**
     * Require that the value is a valid name.
     * 
     * Rule name: `name`
     *
     * A valid name:
     * - Contains only letters, spaces, dots, apostrophes, or hyphens.
     * - Does not start or end with a space or punctuation.
     * - Has a length between the specified minimum and maximum.
     *
     * @param bool $forceFirstName If true, requires at least two words (first and last name).
     * @param int $min Minimum length of the name (default: 2).
     * @param int $max Maximum length of the name (default: 150).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function name(
        bool $forceFirstName = false,
        int $min = 2,
        int $max = 150,
        ?string $error = null
    ): array
    {
        return ['name', [$forceFirstName, $min, $max], $error ?? 'The {field} must be a valid name.'];
    }

    /**
     * Require that the value is a valid URL.
     * 
     * Rule name: `url`
     *
     * Delegates to PHP's `filter_var($value, FILTER_VALIDATE_URL)`.
     *
	 * @param bool $allowIdn Whether to allow internationalized domain names.
	 * @param bool $httpOnly Restrict to http/https schemes only.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function url(bool $allowIdn = false, bool $httpOnly = true, ?string $error = null): array
    {
        return ['url', [$allowIdn, $httpOnly], $error ?? 'The {field} must be a valid URL.'];
    }

    /**
     * Require that the value is a valid JSON string.
     * 
     * Rule name: `json`
     *
     * Delegates to PHP's `json_validate()`. The value must be a string.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function json(?string $error = null): array
    {
        return ['json', null, $error ?? 'The {field} must be a valid JSON string.'];
    }

    /**
     * Require that the value is an array or a JSON-encoded array string.
     * 
     * Rule name: `array`
     *
     * Passes when `is_array($value)` is `true`, or when the value is a
     * string that decodes to an array via `json_decode()`.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function isArray(?string $error = null): array
    {
        return ['array', null, $error ?? 'The {field} must be an array.'];
    }

    /**
     * Require that the value is a valid binary representation.
     * 
     * Rule name: `binary`
     *
     * Behavior is controlled by the two flags, matching the validator's
     *
     * - `$strict = true`  (default) — only characters `0` and `1` are accepted.
     * - `$strict = false`           — PHP-style binary literals (e.g. `0b1010`) are also accepted.
     * - `$allowPrintable = true`    — when non-strict, printable ASCII strings are accepted as a fallback.
     * - `$allowPrintable = false`   — printable ASCII fallback is disabled (default).
     *
     * @param bool $strict Whether to enforce strict binary-digit-only validation (default `true`).
     * @param bool $allowPrintable Whether to accept printable ASCII as a fallback when non-strict (default `false`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function binary(bool $strict = true, bool $allowPrintable = false, ?string $error = null): array
    {
        return ['binary', [$strict, $allowPrintable], $error ?? 'The {field} must be a valid binary value.'];
    }

    /**
     * Require that the value is a valid hexadecimal string.
     * 
     * Rule name: `hexadecimal`
     *
     * Delegates to `ctype_xdigit()`. The value must not be an array.
     *
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function hexadecimal(?string $error = null): array
    {
        return ['hexadecimal', null, $error ?? 'The {field} must be a valid hexadecimal string.'];
    }

    /**
     * Require that the value is greater than or equal to a minimum.
     * 
     * Rule name: `min`
     *
     * Universal rule — type is auto-detected:
     * - Numeric: the numeric value is compared.
     * - String:  the character length is compared.
     * - Array:   the element count is compared.
     *
     * @param float|int $value The minimum allowed value, length, or count.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function min(float|int $value, ?string $error = null): array
    {
        return ['min', [$value], $error ?? "The {field} must be at least {$value}."];
    }

    /**
     * Require that the value is less than or equal to a maximum.
     * 
     * Rule name: `max`
     *
     * Universal rule — type is auto-detected:
     * - Numeric: the numeric value is compared.
     * - String:  the character length is compared.
     * - Array:   the element count is compared.
     *
     * @param float|int $value The maximum allowed value, length, or count.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function max(float|int $value, ?string $error = null): array
    {
        return ['max', [$value], $error ?? "The {field} must not exceed {$value}."];
    }

    /**
     * Require that the value is exactly equal to the given number.
     * 
     * Rule name: `fixed`
     *
     * Universal rule — type is auto-detected:
     * - Numeric: the numeric value is compared.
     * - String:  the character length is compared.
     * - Array:   the element count is compared.
     *
     * @param float|int $value The exact value, length, or count the field must equal.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function fixed(float|int $value, ?string $error = null): array
    {
        return ['fixed', [$value], $error ?? "The {field} must be exactly {$value}."];
    }

    /**
     * Require that the value falls within a range (inclusive).
     * 
     * Rule name: `between`
     *
     * Works with both numbers and strings:
     * - Numeric values: the number is compared directly.
     * - Strings: the character length is compared.
     *
     * @param float|int $min The minimum value or character length (default `1`).
     * @param float|int $max The maximum value or character length (default `100`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function between(float|int $min = 1, float|int $max = 100, ?string $error = null): array
    {
        return ['between', [$min, $max], $error ?? "The {field} must be between {$min} and {$max}."];
    }

    /**
     * Require that the string length is exactly `$length` characters.
     * 
     * Rule name: `length`
     *
     * Type-specific: only applied to string values.
     * Maps to the `'fixed'` comparator internally.
     *
     * @param int $length The required exact character length.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function length(int $length, ?string $error = null): array
    {
        return ['length', [$length], $error ?? "The {field} must be exactly {$length} characters."];
    }

    /**
     * Require that the string length is at least `$length` characters.
     * 
     * Rule name: `minlength`
     *
     * Type-specific: only applied to string values.
     * Maps to the `'min'` comparator internally.
     *
     * @param int $length The minimum character length.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function minLength(int $length, ?string $error = null): array
    {
        return ['minlength', [$length], $error ?? "The {field} must be at least {$length} characters."];
    }

    /**
     * Require that the string length does not exceed `$length` characters.
     * 
     * Rule name: `maxlength`
     *
     * Type-specific: only applied to string values.
     * Maps to the `'max'` comparator internally.
     *
     * @param int $length The maximum character length.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function maxLength(int $length, ?string $error = null): array
    {
        return ['maxlength', [$length], $error ?? "The {field} must not exceed {$length} characters."];
    }

    /**
     * Require that the numeric value equals exactly `$value`.
     * 
     * Rule name: `limit`
     *
     * Type-specific: only applied to numeric values.
     * 
     * Similar to {@see self::fixed()} but operates in the `limit` context
     * used by the validator for numeric fields.
     *
     * @param float|int $value The required exact numeric value.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function limit(float|int $value, ?string $error = null): array
    {
        return ['limit', [$value], $error ?? "The {field} must equal {$value}."];
    }

    /**
     * Require that the numeric value is at least `$value`.
     * 
     * Rule name: `minlimit`
     *
     * Type-specific: only applied to numeric values.
     * Maps to the {@see self::min()} comparator internally.
     *
     * @param float|int $value The minimum numeric value.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function minLimit(float|int $value, ?string $error = null): array
    {
        return ['minlimit', [$value], $error ?? "The {field} must be at least {$value}."];
    }

    /**
     * Require that the numeric value does not exceed `$value`.
     * 
     * Rule name: `maxlimit`
     *
     * Type-specific: only applied to numeric values.
     * Maps to the {@see self::max()} comparator internally.
     *
     * @param float|int $value The maximum numeric value.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function maxLimit(float|int $value, ?string $error = null): array
    {
        return ['maxlimit', [$value], $error ?? "The {field} must not exceed {$value}."];
    }

    /**
     * Require that the array contains exactly `$count` elements.
     * 
     * Rule name: `size`
     *
     * Type-specific: only applied to array values.
     * Maps to the {@see self::fixed()} comparator internally.
     *
     * @param int $count The required exact element count.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function size(int $count, ?string $error = null): array
    {
        return ['size', [$count], $error ?? "The {field} must contain exactly {$count} items."];
    }

    /**
     * Require that the array contains at least `$count` elements.
     * 
     * Rule name: `minsize`
     *
     * Type-specific: only applied to array values.
     * Maps to the `'min'` comparator internally.
     *
     * @param int $count The minimum element count.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function minSize(int $count, ?string $error = null): array
    {
        return ['minsize', [$count], $error ?? "The {field} must contain at least {$count} items."];
    }

    /**
     * Require that the array does not exceed `$count` elements.
     * 
     * Rule name: `maxsize`
     *
     * Type-specific: only applied to array values.
     * Maps to the `'max'` comparator internally.
     *
     * @param int $count The maximum element count.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function maxSize(int $count, ?string $error = null): array
    {
        return ['maxsize', [$count], $error ?? "The {field} must not contain more than {$count} items."];
    }

    /**
     * Require that the value is a valid geographic latitude coordinate.
     * 
     * Rule name: `latitude`
     *
     * Accepts values in the range −90 to +90.
     *
     * @param bool $strict Whether to enable strict format checking (default `false`).
     * @param int $precision Maximum number of decimal digits to allow (default `6`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function latitude(bool $strict = false, int $precision = 6, ?string $error = null): array
    {
        return self::for(
            'latitude', 
            [$strict, $precision],
            $error ?? 'The {field} must be between -90 and 90.'
        );
    }

    /**
     * Short alias for {@see self::latitude()}.
     * 
     * Rule name: `lat`
     *
     * Useful for shorter rule expressions while retaining the same behavior.
     *
     * @param bool $strict Whether to enable strict format checking (default `false`).
     * @param int $precision Maximum number of decimal digits to allow (default `6`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function lat(bool $strict = false, int $precision = 6, ?string $error = null): array
    {
        return self::for(
            'lat', 
            [$strict, $precision], 
            $error ?? 'The {field} must be between -90 and 90.'
        );
    }

    /**
     * Require that the value is a valid geographic longitude coordinate.
     * 
     * Rule name: `longitude`
     *
     * Accepts values in the range −180 to +180. Delegates to `Math::isLng()`.
     *
     * @param bool $strict Whether to enable strict format checking (default `false`).
     * @param int $precision Maximum number of decimal digits to allow (default `6`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function longitude(bool $strict = false, int $precision = 6, ?string $error = null): array
    {
        return self::for(
            'longitude', 
            [$strict, $precision], 
            $error ?? 'The {field} must be between -180 and 180.'
        );
    }

    /**
     * Alias for {@see self::longitude()}.
     * 
     * Rule name: `lng`
     *
     * Useful for shorter rule expressions while retaining the same behavior.
     *
     * @param bool $strict Whether to enable strict format checking.
     * @param int $precision Maximum number of decimal digits to allow.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function lng(bool $strict = false, int $precision = 6, ?string $error = null): array
    {
        return self::for(
            'lng', 
            [$strict, $precision], 
            $error ?? 'The {field} must be between -180 and 180.'
        );
    }

    /**
     * Require that the value is a valid `latitude,longitude` coordinate pair.
     * 
     * Rule name: `latlng`
     *
     * The field value must be a comma-separated string (e.g. `"51.5074,-0.1278"`).
     * The lat and lng parts are split from the value, `$strict` and `$precision`
     * are applied to validation.
     *
     * @param bool $strict Whether to enable strict format checking (default `false`).
     * @param int $precision Maximum number of decimal digits to allow (default `6`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function latLng(bool $strict = false, int $precision = 6, ?string $error = null): array
    {
        return self::for(
            'latlng', 
            [$strict, $precision], 
            $error ?? 'The {field} must be a valid latitude,longitude pair.'
        );
    }

    /**
     * Require that the value is a valid IP address.
     * 
     * Rule name: `ip`
     *
     * Pass `4` for IPv4-only, `6` for IPv6-only,
     * or `0` to accept any version. When no argument is given the validator
     * defaults to `0` (any version).
     *
     * @param int $version IP version to validate: `4`, `6`, or `0` for any (default `0`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function ip(int $version = 0, ?string $error = null): array
    {
        return ['ip', [$version], $error ?? (($version !== 0)
            ? "The {field} must be a valid IPv{$version} address."
            : 'The {field} must be a valid IP address.')];
    }

    /**
     * Require that the value is a valid UUID string.
     * 
     * Rule name: `uuid`
     *
     * Validates the string format against the specified UUID version. 
     * When no argument is given the validator defaults to version `4`.
     *
     * @param int $version UUID version to validate (default `4`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function uuid(int $version = 4, ?string $error = null): array
    {
        return ['uuid', [$version], $error ?? "The {field} must be a valid UUID version {$version}."];
    }

    /**
     * Require that the value is a valid phone number.
     * 
     * Rule name: `phone`
     *
     * The digit count of the number (after stripping formatting characters) must fall between `$min`
     * and `$max` inclusive.
     *
     * @param int $min Minimum digit count (default `10`).
     * @param int $max Maximum digit count (default `15`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function phone(int $min = 10, int $max = 15, ?string $error = null): array
    {
        return [
            'phone', 
            [$min, $max], 
            $error ?? "The {field} must be a valid phone number, between {$min} and {$max} in length."
        ];
    }

    /**
     * Require that the value is a valid file-system path.
     * 
     * Rule name: `path`
     *
     * The optional `$access` argument controls what "valid" means:
     * - `null`     — the value must match a well-formed absolute path pattern (default).
     * - `readable` — the path must exist and be readable (`is_readable()`).
     * - `writable` — the path must exist and be writable (`is_writable()`).
     *
     * @param string|null $access Access mode:  `readable`,  or `writable`.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function path(?string $access = null, ?string $error = null): array
    {
        return ['path', [$access ?? 'absolute'], $error ?? 'The {field} must be a valid file-system path.'];
    }

    /**
     * Require that the value is a string with a valid URI scheme.
     * 
     * Rule name: `scheme`
     *
     * When `$protocol` is empty (default) any syntactically valid scheme is
     * accepted (e.g. `http://`, `ftp://`, `mailto:`).
     * When `$protocol` is provided (e.g. `'https'`) the value must begin
     * with exactly that scheme followed by `:`.
     *
     * @param string $protocol Expected scheme without `://` (e.g. `'https'`), or `null` for any.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function scheme(?string $protocol = null, ?string $error = null): array
    {
        return ['scheme', [$protocol ?? ''], $error ?? (($protocol !== null)
            ? "The {field} must begin with {$protocol}://"
            : 'The {field} must begin with a valid URI scheme.')];
    }

    /**
     * Require that the value equals the value of another field in the same request body.
     * 
     * Rule name: `equals`
     *
     * Useful for "confirm password" style checks where two fields must match.
     * Comparison is strict (`===`) after both values are normalized through `toValue()`.
     *
     * @param string $field The name of the other field to compare against.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function equals(string $field, ?string $error = null): array
    {
        return ['equals', [$field], $error ?? 'The {field} must match "' . self::formatField($field) . '" value.'];
    }

    /**
     * Require that the value does not equal the value of another field in the same request body.
     * 
     * Rule name: `not_equal`
     *
     * Comparison is strict (`!==`) after both values are normalized.
     *
     * @param string $field The name of the other field to compare against.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function notEqual(string $field, ?string $error = null): array
    {
        return [
            'not_equal', 
            [$field], 
            $error ?? 'The {field} must not match "' . self::formatField($field) . '" value.'
        ];
    }

    /**
     * Require that the value is exactly a specified literal value.
     * 
     * Rule name: `is_value`
     *
     * Unlike {@see self::equals()}, which compares against another field's
     * runtime value, this compares the field against a hard-coded literal
     * that is embedded directly in the rule expression.
     *
     * @param mixed $value The exact literal value the field must equal.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function isValue(mixed $value, ?string $error = null): array
    {
        return ['is_value', [$value], $error ?? 'The {field} does not equal the expected value.'];
    }

    /**
     * Require that the value matches a regular-expression pattern.
     * 
     * Rule name: `regex`
     *
     * Provide the pattern without enclosing delimiters; the validator
     * wraps it in `/…/` automatically before calling `preg_match()`.
     *
     * @param string $pattern The regex pattern without delimiters (e.g. `'^[a-z]+$'`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function regex(string $pattern, ?string $error = null): array
    {
        return ['regex', [$pattern], $error ?? 'The {field} format is invalid.'];
    }

    /**
     * Require that the value is present in a given list.
     * 
     * Rule name: `in_array`
     *
     * When `$strict` is `false` (default) the comparison is type-coercing (`==`).
     * When `$strict` is `true` strict type-safe comparison (`===`) is used.
     *
     * @param array $values The list of accepted values.
     * @param bool $strict Whether to use strict (`===`) comparison (default `false`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function inArray(array $values, bool $strict = false, ?string $error = null): array
    {
        return [
            'in_array', 
            [$values, $strict], 
            $error ?? 'The {field} must be one of: ' . self::toList($values) . '.'
        ];
    }

    /**
     * Require that the value is a comma-separated list with a minimum number of items.
     * 
     * Rule name: `is_list`
     *
     * The value must be a string. Items are split by `,`, trimmed, and empty
     * segments are discarded before the count is compared against `$min`.
     *
     * @param int $min Minimum number of non-empty list segments required (default `1`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function isList(int $min = 1, ?string $error = null): array
    {
        return ['is_list', [$min], $error ?? 'The {field} must be a valid list.'];
    }

    /**
     * Require that at least one of the given keys exists in the array value.
     * 
     * Rule name: `key_exists`
     *
     * Passes when the intersection of `$keys` and the field value's array keys
     * is non-empty. Fails if the field value is not an array, or if none of
     * the specified keys are present.
     *
     * @param array $keys List of keys of which at least one must be present.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function keyExists(array $keys, ?string $error = null): array
    {
        return [
            'key_exists', 
            [$keys], 
            $error ?? 'The {field} must contain at least one of keys: ' . self::toList($keys) . '.'
        ];
    }

    /**
     * Require that all specified keys exist in the array value.
     * 
     * Rule name: `keys_exists`
     *
     * When `$strict` is `false` (default) only a subset check is performed,
     * extra keys in the value are allowed.
     * When `$strict` is `true` the value must contain *exactly* `$keys` and
     * no additional keys (bidirectional diff check).
     *
     * @param array $keys The list of keys that must all be present.
     * @param bool $strict When `true`, no keys outside of `$keys` may exist (default `false`).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function keysExists(array $keys, bool $strict = false, ?string $error = null): array
    {
        return [
            'keys_exists', 
            [$keys, $strict], 
            ($error ?? 'The {field} must contain all keys: ' 
                . self::toList($keys) 
                . ($strict ? ' With no additional key.' : '') . '.')
        ];
    }

    /**
     * Require that the value is a valid username.
     * 
     * Rule name: `username`
     *
     * Validates:
     * - Length: 3–64 UTF-8 characters.
     * - Allowed characters: letters, numbers, `_`, `-`, `.` (`[\p{L}\p{N}_.-]`).
     * - No whitespace.
     * - No all-uppercase (even when `$allowUppercase` is `true`).
     * - No all-numbers string.
     * - No leading/trailing hyphens or dots.
     * - No consecutive hyphens (`--`), underscores (`__`), or dots (`..`).
     *
     * @param bool $allowUppercase Whether uppercase letters are permitted (default `true`).
     *                                 When `true`, all-uppercase strings are still rejected.
     *                                 When `false`, any uppercase letter causes failure.
     * @param array $reservedUsernames List of reserved names that must not be used (case-insensitive).
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function username(
        bool $allowUppercase = true, 
        array $reservedUsernames = [], 
        ?string $error = null
    ): array
    {
        return ['username', [$allowUppercase, $reservedUsernames], $error ?? 'The {field} is not a valid username.'];
    }

    /**
     * Validate the field value with a custom callable.
     * 
     * Rule name: `callback`
     *
     * The callable receives `($fieldValue, $fieldName)` and must return
     * a truthy value to indicate success or a falsy value for failure.
     *
     * Supported callable formats:
     * - Closure:              `fn($value, $field): bool`
     * - Plain function name:  `'myFunction'`
     * - Instance method:      `'MyClass@method'`
     * - Static method string: `'MyClass::method'`
     * - Static array:         `[MyClass::class, 'method']`
     *
     * @param (callable(mixed $value, string $field):bool)|array|string $fn The custom validation callback.
     * @param string|null $error Optional error message if validation failed.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function callback(callable|array|string $fn, ?string $error = null): array
    {
        return ['callback', [$fn], $error ?? 'The {field} did not pass validation.'];
    }

    /**
     * Assign a default value to the field when it is empty or missing.
     * 
     * Rule name: `default`
     *
     * When the field value is empty the validator replaces it with `$value`
     * in the validated body and on the request object (when available).
     * No validation error is recorded for this rule.
     *
     * @param mixed $value The default value to assign to the field.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function default(mixed $value): array
    {
        return ['default', [$value], null];
    }

    /**
     * Alias for {@see self::default()}.
     * 
     * Rule name: `fallback`
     *
     * Identical behavior, handled by the same `case 'fallback':` branch
     * in the validator. Use whichever name reads more naturally in context.
     *
     * @param mixed $value The fallback value to assign when the field is empty.
     *
     * @return array{0:rule,1:argument,2:error} Return an array that resolve to rule.
     */
    public static function fallback(mixed $value): array
    {
        return ['fallback', [$value], null];
    }

    /**
     * Format filed name.
     *
     * @param string $field
     * 
     * @return string
     */
    public static function formatField(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Convert array to list.
     *
     * @param array $values
     * 
     * @return string
     */
    private static function toList(array $values): string
    {
        if($values === []){
            return '';
        }

        return '[' . implode(', ', $values) . ']';
    }
}