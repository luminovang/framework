<?php
/**
 * Luminova Framework, Rule-based input validation.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security;

use \Throwable;
use \Luminova\Utility\IP;
use \Luminova\Common\{Helpers, Maths};
use function \Luminova\Funcs\is_empty;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Interface\{HttpRequestInterface, LazyObjectInterface, InputValidationInterface};

/**
 * Built-in validation rules for Luminova's Input Validator.
 *
 * Each rule can be combined in a validation string like:
 * ```php
 * 'field' => 'required|min(3)|max(20)|email'
 * ```
 *
 * Rules without parameters are basic type or presence checks.  
 * Rules with parameters take arguments in parentheses, which are automatically cast to the correct data types.
 *
 * @see https://luminova.ng/docs/0.0.0/security/validation-rules
 *
 * **Available Rules**
 *
 * **Basic Rules (no parameters):**
 * ```
 * none, required, alphanumeric, alphabet, numeric, boolean, 
 * hexadecimal, string, array, json, url, email
 * ```
 *
 * **Range and Length Rules:**
 * ```
 * between(int: offset, int: limit), max(float|int: size), min(float|int: size), 
 * size(int: count), limit(int|float: max), length(int: length), fixed(float|int: length),
 * maxlength(int: length), minlength(int: length), maxsize(int: count), minsize(int: count),
 * maxlimit(float|int: offset), minlimit(float|int: limit)
 * ```
 *
 * **Type & Format Rules:**
 * ```
 * float(string: type), integer(string: type), decimal(string: type), binary(bool: strict, bool: allowPrintable),
 * scheme(string: protocol), latitude(bool: strict, int: precision), longitude(bool: strict, int: precision), 
 * username(bool: allowUppercase, ?array: reservedUsernames), is_value(string: value),
 * phone(int: min, int: max), uuid(int: version), ip(int: version), path(string: access)
 * ```
 *
 * **Comparison & Match Rules:**
 * ```
 * match(string: pattern), equals(string: field), not_equal(string: field)
 * ```
 *
 * **Array & Object Rules:**
 * ```
 * in_array(array: values), is_list(int: min), keys_exists(array: keys)
 * ```
 *
 * **Custom & Utility Rules:**
 * ```
 * callback(callable: fn), default(mixed: value)
 * ```
 */
final class Validation implements InputValidationInterface, LazyObjectInterface
{
    /**
     * Validation rules for input fields.
     * 
     * Keys are the field names, and values are the rules applied (as strings).
     * Multiple rules can be combined with pipe `|` separators.
     * 
     * @var array<string,string> $rules
     * 
     * @example - Example:
     * ```php
     * $input->rules = [
     *     'username' => 'required|alphanumeric|max(20)',
     *     'email'    => 'required|email',
     * ];
     * ```
     */
    public array $rules = [];

    /**
     * Custom validation error messages.
     * 
     * Keys are field names, and values are arrays mapping rule names to messages.
     * Placeholders like {field}, {value}, {rule} can be used for dynamic messages.
     * 
     * @var array<string,array<string,string>> $messages
     * 
     * @example - Example:
     * ```php
     * $input->messages = [
     *     'username' => [
     *         'required'    => 'Username is required',
     *         'alphanumeric'=> 'Username must be alphanumeric',
     *         'max'         => 'Username cannot exceed 20 characters',
     *     ],
     *     'email' => [
     *         'required' => 'Email is required',
     *         'email'    => 'Invalid email address',
     *     ],
     * ];
     * ```
     */
    public array $messages = [];

    /**
     * Input data to validate.
     * 
     * @var array<string,mixed> $body
     */
    private array $body = [];

    /**
     * Validated errors messages.
     * 
     * @var array<string,array> $failures
     */
    private array $failures = [];

    /**
     * Check for common invalid patterns.
     * 
     * @var array $invalidUsernamePatterns
     */
    private static array $invalidUsernamePatterns = [
        '/^[0-9]+$/' => 'Username cannot be all numbers',
        '/^\./' => 'Username cannot start with a dot',
        '/\.$/' => 'Username cannot end with a dot',
        '/^[-]/' => 'Username cannot start with a hyphen',
        '/[-]$/' => 'Username cannot end with a hyphen',
        '/--/' => 'Username cannot contain consecutive hyphens',
        '/__/' => 'Username cannot contain consecutive underscores',
        '/\.\./' => 'Username cannot contain consecutive dots'
    ];

    /**
     * {@inheritdoc}
     */
    public function setBody(array &$body): self
    {
        $this->body = &$body;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(HttpRequestInterface|LazyObjectInterface|null $request = null, ?array $rules = null): bool
    {
        $rules ??= $this->rules;
        $this->setRequestBody($request);
    
        if ($rules === [] || ($rules === [] && $this->body === [])) {
            return true;
        }

        $this->failures = [];
     
        foreach ($rules as $field => $rule) {
            $ruleParts = ($rule === '') ? [] : explode('|', $rule);

            if($ruleParts === []){
                continue;
            }

            $fieldValue = self::toValue($this->body[$field] ?? null);

            foreach ($ruleParts as $rulePart) {
                [$ruleName, $ruleParam] = self::parseRule($rulePart);

                if($ruleName === '' || $ruleName === 'none' || $ruleName === 'nullable'){
                    continue;
                }

                $this->doValidation(
                    $ruleName, 
                    $field, 
                    $fieldValue, 
                    $ruleParam,
                    ($ruleName === 'default' || $ruleName === 'fallback') ? $request : null
                );
            }
        }

        return $this->failures === [];
    }

    /**
     * {@inheritdoc}
     */
    public function getErrors(): array
    {
        return $this->failures;
    }

    /**
     * {@inheritdoc}
     */
    public function getFields(string|int $fieldIndex = 0): array
    {
        if($this->failures === []){
            return [];
        }

        $field = is_int($fieldIndex) ? 
            (array_keys($this->failures)[$fieldIndex] ?? null) : 
            $fieldIndex;

        if($field === null || $field === ''){
            return [];
        }

        $infos = $this->failures[$field] ?? [];

        if($infos === []){
            return [];
        }

        unset($this->failures[$field]);
        return $infos;
    }

    /**
     * {@inheritdoc}
     */
    public function getError(string|int $fieldIndex = 0, int $errorIndex = 0): string
    {
        return $this->getFields($fieldIndex)[$errorIndex]['message'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getField(string|int $fieldIndex = 0): string
    {
        return $this->getFields($fieldIndex)[0]['field'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorMessage(string|int $fieldIndex = 0): string
    {
        return $this->getError($fieldIndex);
    }

    /**
     * {@inheritdoc}
     */
    public function setRules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setMessages(array $messages): self
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addField(string $field, string $rules, array $messages = []): self
    {
        $this->rules[$field] = $rules;

        if($messages !== []){
            $this->messages[$field] = $messages;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isPassed(): bool
    {
        return $this->failures === [];
    }

    /**
     * {@inheritdoc}
     */
    public static function validateUsername(
        string $username, 
        bool $allowUppercase = true, 
        array $reservedUsernames = []
    ): array
    {
        $length = strlen($username);
        if ($length > 64 || $length < 3) {
            return [false, ($length > 64) 
                ? 'Username must be 64 characters or less.'
                : 'Username must be at least 3 characters or more.'
            ];
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            return [
                false, 
                'Username can only contain letters (a-z, A-Z), numbers (0-9), and the characters (_ - .)'
            ];
        }

        if(str_contains($username, ' ')){
            return [false, 'Username can not contain whitespace.'];
        }

        if (!$allowUppercase && preg_match('/[A-Z]/', $username)) {
            return [false, 'Username cannot contain any uppercase letters'];
        }

        if ($allowUppercase && ctype_upper($username)) {
            return [false, 'Username cannot be all uppercase letters.'];
        }

        if ($reservedUsernames !== [] && in_array(strtolower($username), $reservedUsernames, true)) {
            return [false, 'That username is reserved for system use. Please choose another.'];
        }

        foreach (self::$invalidUsernamePatterns as $pattern => $error) {
            if (preg_match($pattern, $username)) {
                return [false, $error];
            }
        }

        return [true, null];
    }

    /**
     * Do input validation and set error if any error was found.
     * 
     * @param string $ruleName The validation rule name.
     * @param string $field The input field name.
     * @param mixed $fieldValue The input value.
     * @param mixed $ruleParam The input validation rule params (e.g, `true,[]`).
     * @param HttpRequestInterface|LazyObjectInterface|null $request Http request object.
     * 
     * @return void
     */
    private function doValidation(
        string $ruleName, 
        string $field,
        mixed $fieldValue,
        string $ruleParam,
        HttpRequestInterface|LazyObjectInterface|null $request = null
    ): void 
    {
        $hasError = false;
        $isEmptyValue = ($fieldValue === '' || $fieldValue === null);
        
        switch ($ruleName) {
            case 'required':
                $hasError = ($isEmptyValue || $this->isEmpty($fieldValue));
            break;
            case 'callback':
                $hasError = ($ruleParam && !self::resolveCallable($ruleParam, $fieldValue, $field));
            break;
            case 'match':
                $hasError = $ruleParam && ($isEmptyValue || !preg_match('/' . $ruleParam . '/', $fieldValue));
            break;
            case 'equals':
                $hasError = ($fieldValue !== self::toValue($this->body[$ruleParam]));
            break;
            case 'is_value':
                $hasError = ($fieldValue !== self::toArguments($ruleParam, [[], null])[0]);
            break;
            case 'not_equal':
                $hasError = ($fieldValue === self::toValue($this->body[$ruleParam]));
            break;
            case 'is_list': 
                $hasError = !$isEmptyValue && !self::isCommaSeparated(
                    $fieldValue,
                    self::toArguments($ruleParam, [1])[0] ?? 1
                );
                break;
            case 'in_array':
                if ($ruleParam !== '') {
                    $hasError = $isEmptyValue;

                    if(!$isEmptyValue){
                        $hasError = true;
                        [$matches, $strict] = self::toArguments($ruleParam, [[], true]);

                        if($matches !== [] && is_array($matches)){
                            $hasError = !in_array($fieldValue, $matches, $strict);
                        }
                    }
                }
            break;
            case 'username':
                $hasError = $isEmptyValue;

                if(!$isEmptyValue){
                    [$isValid, $error] = self::validateUsername(
                        $fieldValue, 
                        ...self::toArguments($ruleParam, [true, []])
                    );

                    $hasError = !$isValid;

                    if ($hasError) {
                        $this->messages[$field][$ruleName] ??= $error;
                    }
                }
            break;
            case 'key_exists':
                $hasError = true;

                if($ruleParam !== '' && !$isEmptyValue){
                    if(is_array($fieldValue)){
                        [$keys,] = self::toArguments($ruleParam, [[], null]);
                        $hasError = $keys !== [] && empty(array_intersect($keys, array_keys($fieldValue)));
                    }
                }
            break;
            case 'keys_exists':
                $hasError = true;

                if($ruleParam !== '' && !$isEmptyValue){
                    if(is_array($fieldValue)){
                        [$keys, $strict] = self::toArguments($ruleParam, [[], false]);

                        if ($strict) {
                            $hasError = !empty(array_diff($keys, array_keys($fieldValue))) 
                                || !empty(array_diff(array_keys($fieldValue), $keys));
                        } else {
                            $hasError = !empty(array_diff($keys, array_keys($fieldValue)));
                        }
                    }
                }
            break;
            case 'default':
            case 'fallback':
                if ($isEmptyValue || $this->isEmpty($fieldValue)) {
                    $this->body[$field] = $ruleParam ? self::toArguments($ruleParam, []) : '';
                    
                    if($request instanceof HttpRequestInterface || $request instanceof LazyObjectInterface){
                        $request->setField($field, $this->body[$field]);
                    }
                }
            break;
            default:
                $hasError = !self::isValid($ruleName, $fieldValue, $ruleParam);
            break;
        }

        if ($hasError) {
            $this->addError($field, $ruleName, $fieldValue);
        }
    }

    /**
     * Resolve and execute a callable from various formats.
     *
     * Supported formats:
     * - Plain function name: 'myFunction'
     * - Class instance method: 'MyClass@method'
     * - Static class method: 'MyClass::method'
     * - Static array callable: [MyClass::class, 'method']
     *
     * This method validates the callable, instantiates classes when needed,
     * and executes the method or function with the provided `$value` and `$field`.
     *
     * @param (callable(mixed $value, string $field):bool)|string|array $callable The callable to resolve.
     * @param mixed $value The first argument passed to the callable.
     * @param string $field The second argument passed to the callable.
     * 
     * @return bool The boolean result of the callable execution.
     * @throws RuntimeException If the callable is invalid or does not exist.
     */
    private static function resolveCallable(string $callable, mixed $value, string $field): bool
    {
        [$target, $max] = self::toArguments($callable, [null, 2]);
        $params = [$value];

        if($max > 1){
            $params[] = $field;
        }

        if($target){
            if (is_callable($target)) {
                return (bool) $target(...$params);
            }

            if (is_string($target)) {
                if (str_contains($target, '@')) {
                    [$class, $method] = explode('@', $target, 2);
                    self::assertCallable($class, info: [$class]);

                    $instance = new $class();
                    self::assertCallable([$instance, $method], 'method', [$method, $class]);

                    return (bool) $instance->{$method}(...$params);
                }

                if (str_contains($target, '::')) {
                    [$class, $method] = explode('::', $target, 2);

                    self::assertCallable($class, info: [$class], isStatic: true);
                    self::assertCallable([$class, $method], 'method', [$class], true);

                    return (bool) $class::{$method}(...$params);
                }
            }

            if (is_array($target) && count($target) === 2) {
                [$class, $method] = $target;

                self::assertCallable($class, info: [$class], isStatic: true);
                self::assertCallable([$class, $method], 'method', [$class], true);

                return (bool) $class::{$method}(...$params);
            }
        }

        throw new RuntimeException('Invalid callable rule callback provided.');
    }

    /**
     * Assert that a class or method exists.
     *
     * Validates the existence of:
     * - Classes
     * - Instance methods
     * - Static methods
     *
     * Throws a RuntimeException if the target does not exist.
     *
     * @param array|string $value Either a class name (string) or [class, method] array.
     * @param string $context The type of assertion ('class' or 'method').
     * @param array $info Array of information to include in the exception message.
     * @param bool $isStatic Whether the method is static (ignored for class validation).
     *
     * @return void
     * @throws RuntimeException If the class or method does not exist.
     */
    private static function assertCallable(
        array|string $value,
        string $context = 'class',
        array $info = [],
        bool $isStatic = false
    ): void 
    {
        $passed = true;

        if ($context === 'class' && !class_exists($value)) {
            $passed = false;
        } elseif ($context === 'method' && !method_exists(...$value)) {
            $passed = false;
        }

        if (!$passed) {
            throw new RuntimeException(sprintf(
                ($context === 'class')
                    ? "Class %s does not exist."
                    : ($isStatic
                        ? "Static method %s::%s does not exist."
                        : "Class %s method %s does not exist."),
                ...$info
            ));
        }
    }

    /**
     * Validate a value against a specific rule.
     * 
     * @param string $ruleName The input validation rule name to execute.
     * @param string $value The input value to validate.
     * @param string $arguments The validation rule arguments.
     * 
     * @return boolean Return true if the rule passed else false.
     */
    private static function isValid(string $ruleName, mixed $value, ?string $arguments = null): bool
    {
        try {
            return match ($ruleName) {
                'string' => is_string($value),
                'between' => self::isBetween($value, ...self::toArguments((string) $arguments, [1, 100])),
                'lat', 'latitude' => !is_array($value) && 
                    Maths::isLat((string) $value, ...self::toArguments((string) $arguments, [false, 6])),
                'lng', 'longitude' => !is_array($value) && 
                        Maths::isLng((string) $value, ...self::toArguments((string) $arguments, [false, 6])),
                'latlng' => !is_array($value) && Maths::isLatLng(...array_merge(
                        explode(',', (string) $value, 2), 
                        self::toArguments((string) $arguments, [false, 6])
                    )),
                'phone' => !is_array($value) 
                    && Helpers::isPhone((string) $value, ...self::toArguments((string) $arguments, [10, 15])),
                'uuid'  => !is_array($value) 
                    && Helpers::isUuid((string) $value, ($arguments === '') ? 4 : (int) $arguments),
                'ip' => !is_array($value) && 
                    IP::isValid((string) $value, ($arguments === '') ? 0 : (int) $arguments),
                'numeric' => is_numeric($value),
                'integer' => self::validateInteger($value, (string) $arguments),
                'digit'  => !is_array($value) && ctype_digit((string) $value),
                'float' => self::validateFloat($value, $arguments),
                'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'alphanumeric' => is_string($value) && ctype_alnum($value),
                'alphabet' => is_string($value) && ctype_alpha($value),
                'url' => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false,
                'decimal' => self::validateFloat($value, (string) $arguments, true),
                'binary' => self::isBinary($value, ...self::toArguments((string) $arguments, [true, false])),
                'boolean' => self::isBoolean($value),
                'hexadecimal' => !is_array($value) && ctype_xdigit((string) $value),
                'array' => is_array($value) || (is_string($value) && is_array(json_decode($value, true, 512, JSON_THROW_ON_ERROR))),
                'json' => is_string($value) && json_validate($value),
                'path', 'scheme' => self::validatePath($ruleName, $value, (string) $arguments),
                'length', 'minlength', 'maxlength' => self::isLength($ruleName, $value, $arguments, 'string'),
                'limit', 'minlimit', 'maxlimit' => self::isLength($ruleName, $value, $arguments, 'numeric'),
                'size', 'minsize', 'maxsize' => self::isLength($ruleName, $value, $arguments, 'array'),
                'min', 'max', 'fixed', => self::isLength($ruleName, $value, $arguments),
                default => true
            };
        } catch (Throwable $e) {
            if($e instanceof RuntimeException){
                throw new RuntimeException(
                    sprintf('%s while validating rule "%s".', $e->getMessage(), $ruleName),
                    previous: $e
                );
            }
            return false;
        }
    }

    /**
     * Validates if the given value is an integer and optionally checks if it meets specific conditions.
     *
     * @param mixed $value The value to be validated.
     * @param string $param The condition to check for the integer value. Accepts 'positive', 'negative', or any other string to validate the integer without additional conditions.
     *
     * @return bool Return true if the value is a valid integer and meets the condition (if provided); `false` otherwise.
     */
    private static function validateInteger(mixed $value, string $param = 'none'): bool
    {
        if (str_contains((string) $value, '.') || !is_numeric($value)) {
            return false;
        }

        $value = (int) $value;

        return match ($param) {
            'positive' => $value > 0,
            'negative' => $value < 0,
            default    => true,
        };
    }

    /**
     * Check if a given value represents a boolean.
     *
     * This method validates if the provided value is a boolean type or a value 
     * that can logically be interpreted as boolean. It supports:
     * 
     * - Native booleans: true, false  
     * - Integers: 0, 1  
     * - Strings: "0", "1", "true", "false" (case-insensitive, trimmed)
     *
     * @param mixed $value The value to evaluate.
     * 
     * @return bool Returns true if the value is boolean or can be interpreted as one, false otherwise.
     */
    private static function isBoolean(mixed $value): bool
    {
        if ($value === 0 || $value === 1 || is_bool($value)) {
            return true;
        }

        if (!is_string($value) && !ctype_digit((string) $value)) {
            return false;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['true', 'false', '1', '0'], true);
    }

    /**
     * Check if a given value falls between a minimum and maximum range.
     *
     * Works with both numbers and strings:
     * - For numeric values: compares the number directly.
     * - For strings: compares the string length.
     *
     * @param mixed  $value The value to check.
     * @param float|int $min The minimum value or string length.
     * @param float|int $max The maximum value or string length.
     * @param string $mode Used internally to revalidate as string length.
     * 
     * @return bool Returns true if within range, false otherwise.
     */
    private static function isBetween(
        mixed $value, 
        float|int $min, 
        float|int $max,
        string $mode = 'auto'
    ): bool
    {
        [$count, $offset, $limit, , $type] = self::getCountInfo($value, $min, $max, $mode);

        if ($count === null) {
            return false;
        }

        $result = $count >= $offset && $count <= $limit;

        // Revalidate as string length if failed
        if($result === false && $mode === 'auto' && $type === 'numeric'){
            return self::isBetween($value, $min, $max, 'string');
        }

        return $result;
    }

    /**
     * Validate numeric, string, or array value against a length/size rule.
     *
     * Type-specific rules will fail if applied to the wrong type.
     * 'min' / 'max' / 'fixed' are universal.
     * 'limit', 'length', 'size' are mapped internally to 'fixed' per type.
     *
     * @param string $rule   Rule name
     * @param mixed  $value  Value to validate (numeric, string, array)
     * @param int|float $length Expected length/size/value
     * @param string $mode Used internally to revalidate as string length.
     * 
     * @return bool Returns true if passed, otherwise false.
     */
    private static function isLength(
        string $rule, 
        mixed $value, 
        mixed $length, 
        string $mode = 'auto'
    ): bool
    {
        [$count, $limit,, $aliases, $type] = self::getCountInfo($value, $length, mode: $mode);
       
        if($count === null || $aliases === null) {
            return false;
        }

        $rule = $aliases[$rule] ?? $rule;
        $result = match($rule) {
            'min',   => $count >= $limit,
            'max',   => $count <= $limit,
            'fixed'  => $count == $limit,
            default  => null
        };

        if($result === null){
            return false;
        }

        // Revalidate as string length if failed
        if($result === false && $mode === 'auto' && $type === 'numeric'){
            return self::isLength($rule, $value, $length, 'string');
        }

        return $result;
    }

    /**
     * Get value count/length info for numeric, string, or array types.
     *
     * @param mixed $value Value to measure.
     * @param mixed $min   Minimum constraint.
     * @param mixed $max   Maximum constraint (optional).
     * @param bool $strict Used internally to revalidate as string length.
     * 
     * @return array [count, min, max, aliases]
     */
    private static function getCountInfo(
        mixed $value, 
        mixed $min, 
        mixed $max = 100, 
        string $mode = 'auto'
    ): array
    {
        if($value === null){
            return [0, (int) $min, (int) $max, null, null];
        }

        if (($mode === 'auto' || $mode === 'numeric') && is_numeric($value)) {
            return [
                to_numeric((string) $value, true), 
                to_numeric((string) $min, true), 
                to_numeric((string) $max, true), 
                ['minlimit'=>'min','maxlimit'=>'max', 'limit'=>'fixed'],
                'numeric'
            ];
        }
        
        if (($mode === 'auto' || $mode === 'string') && is_string($value)) {
            return [
                mb_strlen($value), 
                (int) $min, 
                (int) $max, 
                ['minlength'=>'min','maxlength'=>'max','length'=>'fixed'],
                'string'
            ];

        }
        
        if (($mode === 'auto' || $mode === 'array') && is_array($value)) {
            return [
                count($value), 
                (int) $min, 
                (int) $max, 
                ['minsize'=>'min','maxsize'=>'max','size'=>'fixed'],
                'array'
            ];
        }

        return [null, null, null, null, null];
    }

    /**
     * Check if a value represents a binary number.
     *
     * Behavior:
     *  - $strict = true  : only pure binary digits are allowed (e.g. "1010", "0", "1").
     *  - $strict = false : PHP-style binary literals are allowed (e.g. "0b1010", "1010").
     *  - $allowPrintable : when true (and non-strict), accept printable ASCII strings as a fallback.
     * 
     * @param mixed $value  Value to check.
     * @param bool  $strict If true, only characters 0 and 1 are accepted.
     * @param bool  $allowPrintable If true and not strict, accept printable ASCII strings as fallback.
     * 
     * @return bool Returns true if value is binary according to the options, false otherwise.
     * 
     * @example - Examples:
     * ```php
     * Validation::isBinary('1010')               // true
     * Validation::isBinary('0b1010')             // true (when strict = false)
     * Validation::isBinary(1010)                 // true (numeric; treated as "1010")
     * Validation::isBinary('17700')              // false
     * Validation::isBinary('Hello', false, true) // true  (printable fallback)
     * Validation::isBinary("\x01\x00", false, true) // false (non-printable bytes rejected)
     * ```
     */
    private static function isBinary(
        mixed $value, 
        bool $strict = false, 
        bool $allowPrintable = false
    ): bool
    {
        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if ($strict) {
            return (bool) preg_match('/^[01]+$/', $value);
        }

        if (preg_match('/^(?:0b)?[01]+$/i', $value)) {
            return true;
        }

        if ($allowPrintable) {
            return ctype_print($value) && !preg_match('/[^\x20-\x7E\t\r\n]/', $value);
        }

        return false;
    }

    /**
     * Check if a string is a comma-separated list with a minimum number of items.
     * 
     * @param mixed $value The string to check.
     * @param int   $min   Minimum number of items required in the list (default: 1).
     * 
     * @return bool Returns true if the string meets the comma-separated and minimum item requirements.
     */
    private static function isCommaSeparated(mixed $value, int $min = 1): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $parts = array_map('trim', explode(',', $value));
        $parts = array_filter($parts, fn($part) => $part !== '');

        if (count($parts) < $min) {
            return false;
        }

        return true;
    }

    /**
     * Validates if the given value is an float or decimal number 
     * and optionally checks if it meets specific conditions.
     *
     * @param mixed $value The value to be validated.
     * @param mixed $param The condition to check for the integer value. 
     *              Accepts 'positive', 'negative', or any other string 
     *              to validate the integer without additional conditions.
     * @param bool $isDecimal Whether is decimal mode.
     *
     * @return bool Return true if the value is a valid integer 
     *      and meets the condition (if provided); `false` otherwise.
     */
    private static function validateFloat(mixed $value, mixed $param = 'none', bool $isDecimal = false): bool
    {
        if (!is_numeric($value) || is_int($value)) {
            return false;
        }

        $isInvalid = $isDecimal 
            ? !str_contains((string) $value, '.') || ctype_digit((string) $value)
            : str_contains((string) $value, 'e');

        if ($isInvalid) {
            return false;
        }

        $value = (float) $value;

        return match ($param) {
            'positive' => $value > 0,
            'negative' => $value < 0,
            default => true,
        };
    }

    /**
     * Validates if the given value is a valid file path based on the specified condition.
     *
     * @param mixed $value The value to be validated. Should be a string representing a file path.
     * @param string $param The condition to check for the file path. 
     *                 Accepts 'true' to check if the path is readable or any other string to validate the path format.
     * mailto://
     *
     * @return bool Returns true if the value passed false otherwise.
     */
    private static function validatePath(string $rule, mixed $value, string $param = ''): bool
    {
        if(!is_string($value)){
            return false;
        }

        if($rule === 'path'){
            return ($param === 'true' || $param === 'readable') 
                ? is_readable($value)
                : (($param === 'writable') 
                    ? is_writable($value) 
                    : (bool) preg_match('#^(?:[a-zA-Z]:[\\\/]|/|\\\\)[\\w\\s\\-_.\\/\\\\]+$#i', $value)
                );
        }

        return ($param === '') 
            ? (bool) preg_match('#^[a-z][a-z\d+.-]*:(//)?#i', $value)
            : str_starts_with($value, rtrim($param, ':// ') . ':');
    }

    /**
     * Check if input value or rule param is empty.
     * 
     * @param mixed $value The value to check.
     * 
     * @return bool Return true if the value is not empty, false otherwise.
     */
    private function isEmpty(mixed $value): bool 
    {
        if(is_string($value) || is_numeric($value)){
            return trim((string) $value) === '';
        }

        if(!$value || $value === [] || $value === (object)[]){
            return true;
        }

        return is_empty($value);
    }

    /**
     * Normalize input value before validation.
     *
     * - Trims strings and numeric values.
     * - Leaves other types (arrays, objects, null, bool) unchanged.
     *
     * @param mixed $value The input value.
     * 
     * @return mixed Return value.
     */
    private static function toValue(mixed $value): mixed 
    {
        if($value === null){
            return null;
        }

        if($value === ''){
            return '';
        }

        return (is_string($value) || is_numeric($value)) ? trim((string) $value) : $value;
    }

    /**
     * Convert a comma-separated string of arguments into a structured array of mixed data types.
     *
     * Supports automatic conversion of:
     * - Booleans: `true`, `false`
     * - Null values: `null`
     * - Numbers: `1`, `0.5`, `-10`
     * - Quoted strings: `'text'`, `"text"`
     * - Arrays: `[1, 2, 3]`, `['a', 'b']`
     * - Objects: `{key: "value", id: 1}`
     *
     * @param string $value   The argument string to parse.
     * @param ?array $default Optional array to append to the result.
     *                        If provided, it will be merged with the parsed values.
     *
     * @return array Returns a list of converted argument values, or the default array if input is empty.
     * @throws RuntimeException If an invalid json argument was provided.
     *
     * @example - Example:
     * ```php
     * Validation::toArguments('true, [user, root, system]');
     * // Returns: [true, ['user', 'root', 'system']]
     *
     * Validation::toArguments('false, {name: "Peter"}, null, 1, "etc"');
     * // Returns: [false, (object)['name' => 'Peter'], null, 1, 'etc']
     * ```
     */
    public static function toArguments(string $value, ?array $default = null): array
    {
        $value = trim($value);
        if ($value === '') {
            return $default ?? [];
        }

        $args = [];
        $current = '';
        $depth = 0;

        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $char = $value[$i];

            if ($char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ']' || $char === '}') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $args[] = self::toDatatype($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }
        
        if (trim($current) !== '') {
            $args[] = self::toDatatype($current);
        }

        return ($default === null) 
            ? $args 
            : self::fillDefaults($args, $default);
    }

    /**
     * Merge arrays by filling missing values from the default array.
     * 
     * If $param has fewer items than $default, it fills the remaining
     * positions with elements from $default starting where $param ends.
     * 
     * @param array $param The main array to complete.
     * @param array $default The default array providing fallback values.
     * 
     * @return array The completed array.
     */
    private static function fillDefaults(array $param, array $default): array
    {
        $paramCount = count($param);
        $defaultCount = count($default);

        if ($paramCount >= $defaultCount) {
            return $param;
        }

        return array_merge(
            $param,
            array_slice($default, $paramCount)
        );
    }

    /**
     * Load the request body from the HttpRequestInterface or LazyObjectInterface.
     *
     * @param HttpRequestInterface|LazyObjectInterface|null $request
     * 
     * @throws RuntimeException When no body is provided and no valid request is available.
     * @throws RuntimeException When the lazy object does not resolve to HttpRequestInterface.
     */
    private function setRequestBody(HttpRequestInterface|LazyObjectInterface|null $request): void 
    {
        if($this->body !== []){
            return;
        }

        if($request === null){
            throw new RuntimeException(
               'No request body found. Provide an array using setBody() or pass a valid request object.'
            );
        }

        if(!$request instanceof HttpRequestInterface && $request instanceof LazyObjectInterface){
            if (!$request->isLazyInstanceof(HttpRequestInterface::class)) {
                throw new RuntimeException(
                    sprintf(
                        'Invalid request object. Expected HttpRequestInterface, got %s.',
                        get_class($request->getLazyObject())
                    )
                );
            }
        }

        $this->body = $request->getBody();
    }

    /**
     * Get the number of decimal digits in a number.
     *
     * @param string|float|int $value The numeric value or string.
     * 
     * @return int Number of digits after the decimal point.
     */
    private static function getPrecision(string|float|int $value): int
    {
        $value = (string) $value;

        if (!str_contains($value, '.')) {
            return 0;
        }

        return strlen(substr(strrchr($value, '.'), 1));
    }

    /**
     * Converts a string representation into its corresponding PHP data type.
     *
     * Supports:
     * - Scalars: true, false, null, numbers, quoted strings.
     * - Arrays:  [a, b, c]
     * - Objects: {key: value}
     *
     * @param string $option The value to convert.
     * 
     * @return mixed Returns the converted PHP value.
     */
    private static function toDatatype(string $option): mixed
    {
        $option = trim($option);
        $scalar = self::toScalar($option);

        if ($scalar !== '__scalar_fallback__') {
            // if (is_float($scalar)) {
            //   $precision = self::getPrecision($option);
            //    return round($scalar, $precision);
            // }

            return $scalar;
        }

         if (json_validate($option)) {
            try {
                return json_decode($option, false, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable) {
                return self::toCollection($option);
            }
        }

        return self::toCollection($option);
    }

    /**
     * Converts a string representing a collection into a PHP array or object.
     *
     * This method handles:
     *  - **Array-style strings**: e.g., "[a, b, c]" → ['a', 'b', 'c']
     *  - **Object-style strings**: e.g., "{key: value}" → (object)['key' => 'value']
     *
     * If the string does not match array or object patterns, it returns the original string.
     * Nested arrays and objects are parsed recursively.
     *
     * @param string $option The string to convert into a collection.
     * 
     * @return mixed Returns an array, an object, or the original string.
     */
    private static function toCollection(string $option): mixed
    {
        if (str_starts_with($option, '[') && str_ends_with($option, ']')) {
            $array = trim($option, '[]');

            if ($array === '') {
                return [];
            }
            
            return array_map(static fn($v) => self::toDatatype($v), explode(',', $array));
        }

        if (str_starts_with($option, '{') && str_ends_with($option, '}')) {
            $json = preg_replace(
                '/([{,]\s*)([a-zA-Z_][\w]*)(\s*:)/',
                '$1"$2"$3',
                $option
            );

            try {
                return json_decode($json, false, 512, JSON_THROW_ON_ERROR) ?? (object)[];
            } catch (Throwable) {
                return (object)[];
            }
        }

        return $option;
    }

    /**
     * Convert a string into a scalar (boolean, null, numeric, or string) value.
     *
     * Handles:
     * - `true` → true
     * - `false` → false
     * - `null` → null
     * - Quoted strings → unquoted string
     * - Numeric values → int or float
     *
     * Returns a special fallback marker if no scalar match is found.
     *
     * @param string $value The input string to evaluate.
     * 
     * @return mixed Returns the converted scalar or a fallback marker.
     */
    private static function toScalar(string $value): mixed
    {
        $lower = strtolower($value);
        return match ($lower) {
            'true'  => true,
            'false' => false,
            'null'  => null,
            '', '""', "''" => '',
            default => match(true) {
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'")) => trim($value, "\"'"),
                is_numeric($value) => to_numeric($lower),
                default => '__scalar_fallback__',
            }
        };
    }

    /**
     * Parse a validation rule string into its name and parameter parts.
     *
     * This method extracts the rule name (e.g. "min", "match") and an optional parameter
     * enclosed in parentheses (e.g. "5" in "min(5)" or "(^[a-z]+$)" in "match((^[a-z]+$))").
     *
     * @param string $rule The validation rule string to parse.
     * 
     * @return array{0:string,1:string} Returns an array containing the rule name and parameter (if any).
     */
    private static function parseRule(string $rule): array
    {
        // if (preg_match('/^(\w+)(?:\(([^)]*)\))?$/', trim($rule), $matches)) {
        if (!preg_match('/^(\w+)(?:\((.*)\))?$/', trim($rule), $matches)) {
            return ['', ''];
        }

        return [
            strtolower(trim($matches[1] ?? '')),
            trim($matches[2] ?? '')
        ];
    }

    /**
     * Add validation error message.
     * 
     * @param string $field input field name.
     * @param string $ruleName Rule name.
     * @param mixed $value Filed value.
     * 
     * @return void 
     */
    private function addError(string $field, string $ruleName, mixed $value = null): void
    {
        $message = $this->messages[$field][$ruleName] ?? null;
        $message = ($message === null) 
            ? "Validation failed for field: '{$field}', while validating [{$ruleName}]."
            : self::replace($message, [$field, $ruleName, $value]);

        $this->failures[$field][] = [
            'message' => $message,
            'rule' => $ruleName,
            'field' => $field
        ];
    }

    /**
     * Translate placeholders.
     * 
     * @param string $message message to be translated.
     * @param array $placeholders array.
     * 
     * @return string Return the translated message.
     */
    private static function replace(string $message, array $placeholders = []): string 
    {
        return ($placeholders === []) 
            ? $message 
            : str_replace(['{field}', '{rule}', '{value}'], $placeholders, $message);
    }
}