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
use \Luminova\Logger\Logger;
use \Luminova\Http\Network\IP;
use function \Luminova\Funcs\is_empty;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Utility\{Luhn, Math, Helpers};
use \Luminova\Components\Object\LazyObject;
use \Luminova\Interface\{RequestInterface, LazyObjectInterface, InputValidationInterface};

/**
 * Built-in validation rules for Luminova Input Validator.
 *
 * Rules are defined as a pipe-separated string:
 *
 * ```php
 * 'field' => 'required|min(3)|max(20)|email'
 * ```
 *
 * Format:
 * - Plain rule: `required`
 * - Rule with arguments: `min(3)`, `email([], true)`
 *
 * Arguments inside `()` are auto-cast to their expected types.
 *
 * @see https://luminova.ng/docs/0.0.0/security/validation-rules
 * @see Rule
 *
 * **Available Rules:**
 *
 * Basic:
 * ```
 * none, required, alphanumeric, alphabet, numeric, boolean,
 * hexadecimal, string, array, json, url, phone, email
 * ```
 *
 * Range / Length:
 * ```
 * between, max, min, size, limit, length, fixed,
 * maxlength, minlength, maxsize, minsize, maxlimit, minlimit
 * ```
 *
 * Type / Format:
 * ```
 * float, integer, decimal, binary, scheme, latitude, longitude,
 * username, is_value, phone, email, uuid, ip, path
 * ```
 *
 * Comparison:
 * ```
 * match, equals, not_equal
 * ```
 *
 * Array:
 * ```
 * in_array, is_list, keys_exists
 * ```
 *
 * Utility:
 * ```
 * callback, default
 * ```
 *
 * **PHPStan Types:**
 *
 * @phpstan-type RuleName =
 *     'none'|'required'|'alphanumeric'|'alphabet'|'numeric'|'boolean'|
 *     'hexadecimal'|'string'|'array'|'json'|'url'|'phone'|'email'|
 *     'between'|'max'|'min'|'size'|'limit'|'length'|'fixed'|'luhn'|
 *     'maxlength'|'minlength'|'maxsize'|'minsize'|'maxlimit'|'minlimit'|
 *     'float'|'integer'|'decimal'|'binary'|'scheme'|'latitude'|'longitude'|
 *     'username'|'name'|'is_value'|'uuid'|'ip'|'path'|
 *     'match'|'equals'|'not_equal'|
 *     'in_array'|'is_list'|'keys_exists'|
 *     'callback'|'default'
 *
 * @phpstan-type RuleExpression =
 *     'between(int,int)'|
 *     'max(int|float)'|'min(int|float)'|
 *     'size(int)'|'limit(int|float)'|
 *     'length(int)'|'fixed(int|float)'|
 *     'maxlength(int)'|'minlength(int)'|
 *     'maxsize(int)'|'minsize(int)'|
 *     'maxlimit(int|float)'|'minlimit(int|float)'|
 *
 *     'float(string)'|'integer(string)'|'decimal(string)'|
 *     'binary(bool,bool)'|
 *     'scheme(string)'|
 *     'latitude(bool,int)'|'longitude(bool,int)'|
 *     'name(bool,int,int)'|'username(bool,?array)'|
 *     'is_value(string)'|
 *     'phone(int,int)'|
 *     'email(array,bool)'|
 *     'uuid(int)'|'ip(int)'|
 *     'path(string)'|
 *
 *     'match(string)'|
 *     'equals(string)'|'not_equal(string)'|
 *
 *     'in_array(array)'|
 *     'is_list(int)'|
 *     'keys_exists(array)'|
 *
 *     'callback(callable)'|
 *     'default(mixed)'
 *
 * @phpstan-type InputRules = RuleName|RuleExpression|string
 */
final class Validation implements InputValidationInterface, LazyObjectInterface
{
    /**
     * Validation rules for input fields.
     * 
     * Keys are the field names, and values are the rules applied as:
     * - `string` - Multiple rules can be combined with pipe `|` separators.
     * - `array` - Each rule as an array entry using {@see Rule}
     * 
     * @var array<string,InputRules|array> $rules
     * 
     * @example - Example:
     * ```php
     * $input->rules = [
     *     'username' => 'required|alphanumeric|max(20)',
     *     'email'    => 'required|email([example.com], true)',
     * ];
     * ```
     * @example - Fluent Rules:
     * ```php
     * use Luminova\Security\Rule;
     * 
     * $input->rules = [
     *     'username' => [
     *          Rule::required(),
     *          Rule::alphanumeric(),
     *          Rule::max(20)
     *      ],
     *     'email' => [
     *          Rule::required(),
     *          Rule::email(['example.com'], true),
     *      ]
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
     * @var array<string,array<RuleName,string>> $messages
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
     * @var array<string,mixed>|null $body
     */
    private ?array $body = null;

    /**
     * Optional request instance for value mutation.
     *
     * @var RequestInterface|LazyObjectInterface|null $request
     */
    private RequestInterface|LazyObjectInterface|null $request = null;

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
        $this->body =& $body;
        $this->request = null;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequest(RequestInterface|LazyObjectInterface $request): self
    {
        if(!($request instanceof RequestInterface) && ($request instanceof LazyObject)){
            if (!$request->isLazyInstanceof(RequestInterface::class)) {
                throw new RuntimeException(
                    sprintf(
                        'Invalid request object. Expected RequestInterface, got %s.',
                        get_class($request->getLazyObject())
                    )
                );
            }
        }

        unset($this->body);

        $this->body = $request->getParsedBody(); 
        $this->request = $request;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(RequestInterface|LazyObjectInterface|null $request = null): bool
    {
        if ($this->rules === []) {
            return true;
        }

        if($request !== null){
            $this->setRequest($request);
        }

        if ($this->body === null) {
            throw new RuntimeException(sprintf(
                'Validation failed: no input data available. Provide data using %s::setBody(), %s::setRequest(), or pass a valid request instance.',
                self::class,
                self::class
            ));
        }

        $this->failures = [];
     
        foreach ($this->rules as $field => $rule) {

            if($rule === '' || $rule === []){
                continue;
            }

            $ruleParts = is_array($rule) ?  $rule : explode('|', $rule);

            if($ruleParts === []){
                continue;
            }

            $fieldValue = self::toValue($this->body[$field] ?? null);

            foreach ($ruleParts as $rulePart) {
                [$ruleName, $ruleParam, $error] = is_array($rulePart) 
                    ? $rulePart
                    : self::toRuleArguments($rulePart);

                if($ruleName === '' || $ruleName === 'none' || $ruleName === 'nullable'){
                    continue;
                }

                $arguments = $ruleParam ? self::toArguments($ruleParam) : null;
                $isValid = $this->isDataValid(
                    $ruleName, 
                    $field, 
                    $fieldValue, 
                    ($arguments === []) ? null : $arguments
                );

                if ($isValid) {
                    continue;
                }

                $this->addError($field, $ruleName, $fieldValue, $error);
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
    public function getFields(string|int $field = 0): array
    {
        if($this->failures === []){
            return [];
        }

        $field = is_int($field) ? 
            (array_keys($this->failures)[$field] ?? null) : 
            $field;

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
    public function getError(string|int $field = 0, int $error = 0): string
    {
        return $this->getFields($field)[$error]['message'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getField(string|int $field = 0): string
    {
        return $this->getFields($field)[0]['field'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorMessage(string|int $field = 0): string
    {
        return $this->getError($field);
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
    public function addField(string $field, array|string $rules, array $messages = []): self
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
    public static function isUsername(
        string $username, 
        bool $allowUppercase = true, 
        array $reservedUsernames = []
    ): array
    {
        $length = mb_strlen($username, 'UTF-8');

        if ($length > 64 || $length < 3) {
            return [false, ($length > 64) 
                ? 'Username must be 64 characters or less.'
                : 'Username must be at least 3 characters or more.'
            ];
        }

        if (!preg_match('/^[\p{L}\p{N}_\.-]+$/u', $username)) {
            return [false, 'Username can only contain letters, numbers, and (_ - .)'];
        }

        if (preg_match('/\p{Z}/u', $username)) {
            return [false, 'Username cannot contain whitespace.'];
        }

        if (!$allowUppercase && preg_match('/[A-Z]/', $username)) {
            return [false, 'Username cannot contain any uppercase letters.'];
        }

        if ($allowUppercase && mb_strtoupper($username, 'UTF-8') === $username) {
            return [false, 'Username cannot be all uppercase letters.'];
        }

        if ($reservedUsernames !== []) {
            $name = mb_strtolower($username, 'UTF-8');

            foreach($reservedUsernames as $reserved){
                if( mb_strtolower($reserved, 'UTF-8') === $name){
                    return [false, "The username '{$name}' is not allowed."];
                }
            }
        }

        foreach (self::$invalidUsernamePatterns as $pattern => $error) {
            if (preg_match($pattern, $username)) {
                return [false, $error];
            }
        }

        return [true, null];
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
     * @param array|string|null $value The argument string to parse.
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
    public static function toArguments(array|string|null $value, ?array $default = null): array
    {
        if ($value === null || $value === '') {
            return $default ?? [];
        }

        if(is_array($value)){
            return $value;
        }

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
     * Validate if a value exists in a given allowed list.
     *
     * If no values are provided, validation fails unless the input is empty.
     *
     * @param mixed $value Input value.
     * @param array|null $args [allowedValues, strictComparison]
     * @param bool $isEmpty Whether the value is empty.
     *
     * @return bool True if value exists in the allowed list.
     */
    private static function isInArray(mixed $value, ?array $args, bool $isEmpty): bool
    {
        if (!$args) {
            return !$isEmpty;
        }

        [$matches, $strict] = self::fillDefaults($args, [[], false]);

        if($matches === [] || !is_array($matches)){
            return false;
        }

        return in_array($value, $matches, $strict);
    }

    /**
     * Validate username and optionally store error message.
     *
     * @param string $field Field name.
     * @param mixed $value Username value.
     * @param array|null $args [allowUppercase, reservedUsernames]
     *
     * @return bool True if username is valid.
     */
    private function isValidUsername(
        string $field,
        mixed $value,
        ?array $args
    ): bool 
    {
        [$valid, $error] = self::isUsername(
            $value,
            ...self::fillDefaults($args, [true, []])
        );

        if (!$valid && $error) {
            $this->messages[$field]['username'] ??= $error;
        }

        return $valid;
    }

    /**
     * Check if at least one of the given keys exists in an array value.
     *
     * @param mixed $value Input array.
     * @param array|null $args List of keys to check.
     *
     * @return bool True if at least one key exists.
     */
    private static function isAnyKeyExists(mixed $value, ?array $args): bool
    {
        if (!$args || !is_array($value)) {
            return false;
        }

        $keys = $args[0] ?? [];

        if($keys === []){
            return false;
        }

        return !empty(array_intersect($keys, array_keys($value)));
    }

    /**
     * Validate that required keys exist in an array.
     *
     * @param mixed $value Input array.
     * @param array|null $args [keys, strictMode]
     *
     * @return bool True if all required keys exist.
     */
    private static function isKeysExists(mixed $value, ?array $args): bool
    {
        if (!$args || !is_array($value)) {
            return false;
        }

        [$keys, $strict] = self::fillDefaults($args, [[], false]);

        $fieldKeys = array_keys($value);

        if ($strict) {
            return empty(array_diff($keys, $fieldKeys))
                && empty(array_diff($fieldKeys, $keys));
        }

        return empty(array_diff($keys, $fieldKeys));
    }

    /**
     * Apply default value when input is empty.
     *
     * This method mutates internal body and optionally updates request object.
     *
     * @param string $field Field name.
     * @param mixed $value Input value.
     * @param array|null $args Default value configuration.
     *
     * @return bool Always returns true (no validation error).
     */
    private function isDefaultApplied(
        string $field,
        mixed $value,
        ?array $args
    ): bool 
    {
        if (!self::isEmpty($value)) {
            return true;
        }

        if (
            $this->request instanceof \Luminova\Http\Request 
            || $this->request instanceof LazyObject
        ) {
            $this->request->setField($field, $args[0] ?? '');
            return true;
        }

        $this->body[$field] = $args[0] ?? '';
        return true;
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
    private static function isCallbackResolved(callable|string|array $callable, mixed $value, string $field): bool
    {
        if(!$callable){
            throw new RuntimeException('Invalid callable rule callback provided.');
        }

        if(is_callable($callable)){
            return (bool) $callable($value, $field);
        }

        $class = null;
        $method = null;

        if (is_string($callable)) {
            if (str_contains($callable, '@')) {
                [$class, $method] = explode('@', $callable, 2);
                self::assertCallable($class, info: [$class]);

                $instance = new $class();
                self::assertCallable([$instance, $method], 'method', [$method, $class]);

                return (bool) $instance->{$method}($value, $field);
            }

            if (str_contains($callable, '::')) {
                [$class, $method] = explode('::', $callable, 2);
            }
        } elseif(is_array($callable) && count($callable) === 2) {
            [$class, $method] = $callable;
        }

        if($class && $method){
            self::assertCallable($class, info: [$class], isStatic: true);
            self::assertCallable([$class, $method], 'method', [$class], true);

            return (bool) $class::{$method}($value, $field);
        }

        return false;
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
     * Validate a single field against a given rule.
     *
     * Executes the specified validation rule using the provided value and arguments.
     * Returns `true` if validation fails, otherwise `false`.
     *
     * Some rules may mutate the input:
     * - `default` / `fallback` will assign a value when the field is empty.
     *
     * Custom rules:
     * - `callback` expects a callable and must return a boolean.
     *
     * Error handling:
     * - On failure, the caller is expected to register an error message.
     * - If an exception occurs during validation:
     *   - In development, it is rethrown.
     *   - In production, it is logged and treated as a validation failure.
     *
     * @param string $name Validation rule name (e.g. "required", "min", "equals").
     * @param string $field Field name being validated.
     * @param mixed  $value The field value.
     * @param array|null $arguments Rule arguments (e.g. [10, 20]).
     *
     * @return bool True if validation failed, false otherwise.
     */
    private function isDataValid(
        string $name, 
        string $field, 
        mixed $value,
        ?array $arguments = null
    ): bool 
    {
        $isEmpty = ($value === '' || $value === null);

        try{
            return match ($name) {
                'required' => !($isEmpty || self::isEmpty($value)),
                'string'   => is_string($value),
                'numeric'  => is_numeric($value),
                'boolean'  => self::isBoolean($value),
                'integer'  => self::isInteger($value, (string) ($arguments[0] ?? 'unsigned')),
                'float'    => self::isFloatNumber($value, (string) ($arguments[0] ?? 'unsigned')),
                'decimal'  => self::isFloatNumber($value, (string) ($arguments[0] ?? 'unsigned'), true),
                'digit'    => (is_string($value) || is_numeric($value)) && ctype_digit((string) $value),
                'luhn'     => (is_string($value) || is_numeric($value)) && Luhn::isValid((string) $value),
                'between'  => self::isBetween($value, ...self::fillDefaults($arguments, [1, 100])),
                'phone' => !$isEmpty && (is_string($value) || is_numeric($value))
                    && Helpers::isPhone((string) $value, ...self::fillDefaults($arguments, [10, 15])),
                'uuid'  => !$isEmpty && is_string($value) 
                    && Helpers::isUuid((string) $value, (int) ($arguments[0] ?? 4)),
                'email' => !$isEmpty && is_string($value) 
                    && Helpers::isEmail($value, ...self::fillDefaults($arguments, [[], false])),
                'url'   => !$isEmpty && is_string($value) 
                    && Helpers::isUrl($value, ...self::fillDefaults($arguments, [false, true])), 
                'username' => !$isEmpty 
                    && $this->isValidUsername($field, $value, $arguments),
                'name'      => !$isEmpty && is_string($value) 
                    && self::isName($value, ...self::fillDefaults($arguments, [false, 2, 150])),
                'is_value'  => ($value === ($arguments[0] ?? null)),
                'equals'    => !$isEmpty 
                    && ($value === self::toValue($this->body[$arguments[0] ?? null])),
                'not_equal' => !$isEmpty 
                    && ($value !== self::toValue($this->body[$arguments[0] ?? null])),
                'is_list'   => !$isEmpty 
                    && self::isCommaSeparated($value, $arguments[0] ?? 1),
                'in_array'    => self::isInArray($value, $arguments, $isEmpty),
                'key_exists'  => !$isEmpty && self::isAnyKeyExists($value, $arguments),
                'keys_exists' => !$isEmpty && self::isKeysExists($value, $arguments),
                'latlng'      => is_string($value) && Math::isLatLng(...array_merge(
                    explode(',', (string) $value, 2), 
                    self::fillDefaults($arguments, [false, 6])
                )),
                'lat', 'latitude'   => is_string($value) && 
                    Math::isLat((string) $value, ...self::fillDefaults($arguments, [false, 6])),
                'lng', 'longitude' => is_string($value) && 
                    Math::isLng((string) $value, ...self::fillDefaults($arguments, [false, 6])),
                'binary'       => self::isBinary($value, ...self::fillDefaults($arguments, [true, false])),
                'alphabet'     => is_string($value) && ctype_alpha($value),
                'alphanumeric' => is_string($value) && ctype_alnum($value),
                'hexadecimal'  => is_string($value) && ctype_xdigit((string) $value),
                'array' => is_array($value) 
                    || (is_string($value) && is_array(json_decode($value, true, 512, JSON_THROW_ON_ERROR))),
                'json'  => is_string($value) && json_validate($value),
                'ip'    => is_string($value) && 
                    IP::isValid((string) $value, (int) ($arguments[0] ?? 0)),
                'callback' =>  $arguments && self::isCallbackResolved(
                    $arguments[0],
                    $value,
                    $field
                ),
                'match', 'regex' => !$isEmpty && is_string($value) && $arguments
                    && preg_match('/' . preg_quote(trim($arguments[0], '/'), '/') . '/', $value),
                'path'   => self::isFilePath($value, (string) ($arguments[0] ?? '')),
                'scheme' => self::isProtocol($value, (string) ($arguments[0] ?? '')),
                'length', 'minlength', 'maxlength' => self::isLength($name, $value, 'string', $arguments),
                'limit', 'minlimit', 'maxlimit' => self::isLength($name, $value, 'numeric', $arguments),
                'size', 'minsize', 'maxsize' => self::isLength($name, $value, 'array', $arguments),
                'min', 'max', 'fixed', => self::isLength($name, $value, 'auto', $arguments),
                'default', 'fallback' => $this->isDefaultApplied($field, $value, $arguments),
                default => true
            };
        } catch (Throwable $e) {
            $error = sprintf(
                'Error while validating rule "%s": %s',
                $name,
                $e->getMessage()
            );

            if (!PRODUCTION) {
                throw new RuntimeException($error, $e->getCode(), $e);
            }

            Logger::error($error, [
                'rule' => $name,
                'field' => $field,
                'value' => $value,
                'arguments' => $arguments,
            ]);

            $this->messages[$field][$name] = sprintf(
                'Validation error on rule "%s".',
                $name
            );

            return false;
        }
    }

    /**
     * Validates whether a value is a valid integer and optionally checks its sign constraint.
     *
     * The method accepts numeric strings and integers, but rejects floats.
     *
     * Supported constraints:
     * - positive: value must be > 0
     * - negative: value must be < 0
     * - unsigned: value must be >= 0
     *
     * Any unknown constraint will bypass sign validation.
     *
     * @param mixed $value  The value to validate.
     * @param string $param Sign constraint (positive|negative|unsigned).
     *
     * @return bool True if value is a valid integer and satisfies the constraint.
     */
    private static function isInteger(mixed $value, string $param = 'unsigned'): bool
    {
        if (str_contains((string) $value, '.') || !is_numeric($value)) {
            return false;
        }

        $value = (int) $value;
        return match ($param) {
            'positive' => $value > 0,
            'negative' => $value < 0,
            'unsigned' => $value >= 0,
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
        return in_array($value, ['true', 'false', '1', '0', 'yes', 'no'], true);
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
     * Validates a value against a length or size rule (min, max, fixed).
     *
     * Supports multiple data types:
     * - string: character length
     * - array: element count
     * - numeric: digit count or numeric value depending on mode
     *
     * Some rule aliases (limit, length, size) are internally normalized to "fixed".
     *
     * Type mismatch will result in validation failure.
     * 
     * Internally resolves:
     * - min  => >= limit
     * - max  => <= limit
     * - fixed => == limit
     *
     * If validation fails in "auto" mode and the value is numeric,
     * the check is retried using string length comparison.
     *
     * @param string $rule Range rule (min|max|fixed or alias).
     * @param mixed $value Value to evaluate.
     * @param string $mode Evaluation mode (auto|string|numeric).
     * @param array|null $arguments The length, limit arguments.
     *
     * @return bool True if value satisfies the rule.
     */
    private static function isLength(
        string $rule, 
        mixed $value, 
        string $mode,
        ?array $arguments
    ): bool
    {
        $length = $arguments[0] ?? 1;

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
            return self::isLength($rule, $value, 'string', $length);
        }

        return $result;
    }

    /**
     * Validate if a string is a valid name.
     *
     * A valid name:
     * - Contains only letters, spaces, dots, apostrophes, or hyphens.
     * - Does not start or end with a space or punctuation.
     * - Has a length between the specified minimum and maximum.
     *
     * @param string $name The name to validate.
     * @param bool $forceFirstName If true, requires at least two words (first and last name).
     * @param int $min Minimum length of the name (default: 2).
     * @param int $max Maximum length of the name (default: 150).
     *
     * @return bool Returns true if the name is valid, false otherwise.
     */
    private static function isName(
        string $name,
        bool $forceFirstName = false,
        int $min = 2,
        int $max = 150
    ): bool 
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        $length = mb_strlen($name, 'UTF-8');

        if ($length < $min || $length > $max) {
            return false;
        }

        $pattern = $forceFirstName
            ? '/^[\p{L}]+(?:[ .\'-][\p{L}]+)+$/u'
            : '/^[\p{L}]+(?:[ .\'-][\p{L}]+)*$/u';

        return (bool) preg_match($pattern, $name);
    }

    /**
     * Get value count/length info for numeric, string, or array types.
     *
     * @param mixed $value Value to measure.
     * @param mixed $min Minimum constraint.
     * @param mixed $max Maximum constraint (optional).
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

        return match(true){
            ($mode === 'auto' || $mode === 'numeric') && is_numeric($value) => [
                to_numeric((string) $value, true), 
                to_numeric((string) $min, true), 
                to_numeric((string) $max, true), 
                ['minlimit'=>'min','maxlimit'=>'max', 'limit'=>'fixed'],
                'numeric'
            ],
            ($mode === 'auto' || $mode === 'string') && is_string($value) => [
                mb_strlen($value), 
                (int) $min, 
                (int) $max, 
                ['minlength'=>'min','maxlength'=>'max','length'=>'fixed'],
                'string'
            ],
            ($mode === 'auto' || $mode === 'array') && is_array($value) => [
                count($value), 
                (int) $min, 
                (int) $max, 
                ['minsize'=>'min','maxsize'=>'max','size'=>'fixed'],
                'array'
            ],
            default => [null, null, null, null, null]
        };
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
     * Validates whether a value is a float or decimal number and optionally enforces a sign constraint.
     *
     * The method accepts numeric strings representing floating-point numbers.
     * Integer values are rejected.
     *
     * Validation rules:
     * - In decimal mode: value must contain a decimal point and must not be a pure digit string.
     * - In non-decimal mode: scientific notation (e.g. 1e5) is rejected.
     *
     * Supported constraints:
     * - positive: value must be greater than 0
     * - negative: value must be less than 0
     *
     * Unknown constraints are ignored and no additional validation is applied.
     *
     * @param mixed $value Numeric value to validate.
     * @param string $param Sign constraint (positive|negative).
     * @param bool $isDecimal Whether strict decimal format validation is enforced.
     *
     * @return bool True if value is a valid float/decimal and satisfies the constraint (if applied).
     */
    private static function isFloatNumber(mixed $value, string $param = 'unsigned', bool $isDecimal = false): bool
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
            'unsigned' => $value >= 0,
            default => true,
        };
    }

    /**
     * Validates whether a value is a valid file path and optionally checks file accessibility.
     *
     * By default, this only validates the path format (not filesystem existence).
     *
     * Supported modes:
     * - readable: path must be readable (is_readable)
     * - writable: path must be writable (is_writable)
     * - empty/default: validates path format only
     *
     * @param mixed $value File path to validate.
     * @param string $param Validation mode (readable|writable|format).
     *
     * @return bool True if valid file path and passes the selected condition.
     */
    private static function isFilePath(mixed $value, string $param): bool
    {
        if(!is_string($value)){
            return false;
        }

        if($param === 'true' || $param === 'readable') {
            return is_readable($value);
        }

        if($param === 'writable') {
            return is_writable($value);
        }

        return (bool) preg_match('#^(?:[a-zA-Z]:[\\\/]|/|\\\\)[\\w\\s\\-_.\\/\\\\]+$#i', $value);
    }

    /**
     * Validates whether a value is a URL-like protocol string.
     *
     * Examples:
     * - https://
     * - ftp://
     * - mailto:
     *
     * If no parameter is provided, it checks for a valid protocol format.
     * If a parameter is provided, it validates against that specific protocol.
     *
     * @param mixed $value Protocol string to validate.
     * @param string $param Optional protocol name (e.g. https, ftp, mailto).
     *
     * @return bool True if the value matches the protocol rule.
     */
    private static function isProtocol(mixed $value, string $param = ''): bool
    {
        if(!is_string($value)){
            return false;
        }

        if($param === '') {
            return (bool) preg_match('#^[a-z][a-z\d+.-]*:(//)?#i', $value);
        }

        return str_starts_with($value, rtrim($param, ':// ') . ':');
    }

    /**
     * Check if input value or rule param is empty.
     * 
     * @param mixed $value The value to check.
     * 
     * @return bool Return true if the value is not empty, false otherwise.
     */
    private static function isEmpty(mixed $value): bool 
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
    private static function fillDefaults(?array $param, array $default): array
    {
        if($param === null){
            return $default;
        }

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
    private static function toRuleArguments(string $rule): array
    {
        if (!preg_match('/^(\w+)(?:\((.*)\))?$/', trim($rule), $matches)) {
            return ['', ''];
        }

        return [
            strtolower(trim($matches[1] ?? '')),
            trim($matches[2] ?? '')
        ];
    }

    /**
     * Add a validation error for a field.
     *
     * Uses a custom message if defined, otherwise falls back to a default message.
     * Placeholders in custom messages are replaced with: field, rule, value.
     *
     * @param string $field Field name.
     * @param string $ruleName Rule being validated.
     * @param mixed  $value Field value (optional).
     * @param string|null $message Default error message.
     */
    private function addError(
        string $field,
        string $ruleName,
        mixed $value = null,
        ?string $message = null
    ): void
    {
        $message ??= ($this->messages[$field][$ruleName] ?? null);
        $message = ($message === null) 
            ? self::defaultMessage($field, $ruleName) 
            : self::replace($message, [Rule::formatField($field), $ruleName, htmlspecialchars((string) $value)]);

        $this->failures[$field][] = [
            'message' => $message,
            'rule'    => $ruleName,
            'field'   => $field,
        ];
    }

    /**
     * Return the default error message for a rule.
     *
     * Used when no custom message exists for the field/rule pair.
     *
     * @param string $field Field name.
     * @param string $rule  Failed rule name.
     *
     * @return string
     */
    private function defaultMessage(string $field, string $rule): string
    {
        return match ($rule) {
            'required'   => "The {$field} field is required.",
            'email'      => "The {$field} must be a valid email address.",
            'phone'      => "The {$field} must be a valid phone number.",
            'username'   => "The {$field} must be a valid username.",
            'between'    => "The {$field} is out of the allowed range.",
            'length'     => "The {$field} has an invalid length.",
            'match'      => "The {$field} format is invalid.",
            'equals'     => "The {$field} does not match the required value.",
            'not_equal'  => "The {$field} must not match the given value.",
            default      => "The {$field} is invalid ({$rule}).",
        };
    }

    /**
     * Translate placeholders.
     * 
     * Supports placeholders: {field}, {rule}, {value}.
     * 
     * @param string $message message to be translated.
     * @param array $placeholders array.
     * 
     * @return string Return the translated message.
     */
    private static function replace(string $message, array $placeholders = []): string 
    {
        return str_replace(
            ['{field}', '{rule}', '{value}'], 
            $placeholders, 
            $message
        );
    }
}