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

use \Luminova\Interface\ValidationInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Functions\Func;
use \Luminova\Functions\IP;
use \Throwable;
use function \Luminova\Funcs\{
    is_empty,
    list_to_array,
    list_in_array
};

final class Validation implements ValidationInterface, LazyInterface
{
    /**
     * Validated errors messages.
     * 
     * @var array<string,array> $failures
     */
    private array $failures = [];

    /**
     * Validation rules.
     * 
     * @var array<string,string> $rules
     */
    public array $rules = [];

    /**
     * Validation error messages.
     * 
     * @var array<string,array> $messages
     */
    public array $messages = [];

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
    public function validate(array $input, ?array $rules = null): bool
    {
        $rules ??= $this->rules;
    
        if ($rules === [] || ($rules === [] && $input === [])) {
            return true;
        }

        $this->failures = [];
     
        foreach ($rules as $field => $rule) {
            $fieldValue = $input[$field] ?? null;
            $ruleParts = ($rule === '') ? [] : explode('|', $rule);

            if($ruleParts === []){
                continue;
            }

            foreach ($ruleParts as $rulePart) {
                [$ruleName, $ruleParam] = self::parseRule($rulePart);

                if($ruleName === ''){
                    continue;
                }

                if($this->doValidation($ruleName, $field, $fieldValue, $ruleParam, $input)){
                    return true;
                }
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
    public function getError(string|int $fieldIndex = 0, int $errorIndex = 0): string
    {
        $errors = $this->getErrorFields($fieldIndex);
    
        if($errors === []){
            return '';
        }
        
        $errors = $errors[$errorIndex] ?? [];
        return $errors['message'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorMessage(string|int $fieldIndex = 0): string
    {
        return ($this->getErrorFields($fieldIndex)[0]['message'] ?? '');
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorField(string|int $fieldIndex = 0): string
    {
        return ($this->getErrorFields($fieldIndex)[0]['field'] ?? '');
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorFields(string|int $fieldIndex = 0): array
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
            return [false, 'Username can only contain letters (a-z, A-Z), numbers (0-9), and the characters (_ - .)'];
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
     * @param array<string,mixed> $input The request for body.
     * 
     * @return bool Return true if rule allow nullable or empty value, otherwise false.
     */
    private function doValidation(
        string $ruleName, 
        string $field,
        mixed $fieldValue,
        string $ruleParam,
        array $input
    ): bool 
    {
        $hasError = false;
        $isEmptyValue = ($fieldValue === '' || $fieldValue === null);
        
        switch ($ruleName) {
            case 'none':
            case 'nullable':
                return true;
            case 'required':
                $hasError = ($isEmptyValue || $this->isEmpty($fieldValue));
            break;
            case 'callback':
                $hasError = ($ruleParam && is_callable($ruleParam) && !$ruleParam($fieldValue, $field));
            break;
            case 'match':
                $hasError = $ruleParam && ($isEmptyValue || !preg_match('/' . $ruleParam . '/', $fieldValue));
            break;
            case 'equals':
                $hasError = ($fieldValue !== $input[$ruleParam]);
            break;
            case 'is_value':
                $hasError = ($fieldValue !== $ruleParam);
            break;
            case 'not_equal':
                $hasError = ($fieldValue === $input[$ruleParam]);
            break;
            case 'in_array':
                if ($ruleParam !== '') {
                    $hasError = $isEmptyValue;

                    if(!$isEmptyValue){
                        $matches = list_to_array($ruleParam);
                        $hasError = !(($matches === false) ? false : in_array($fieldValue, $matches));
                    }
                }
            break;
            case 'username':
                $hasError = $isEmptyValue;

                if(!$isEmptyValue){
                    [$valid, $error] = self::validateUsername($fieldValue, ...self::toArguments($ruleParam));

                    if (!$valid) {
                        $hasError = true;
                        $this->messages[$field][$ruleName] = $this->messages[$field][$ruleName] ?? $error;
                    }
                }
            break;
            case 'keys_exist':
            case 'keys_exists':
                if ($ruleParam !== '') {
                    $hasError = $isEmptyValue;

                    if(!$isEmptyValue){
                        $matches = list_to_array($ruleParam);

                        if($matches !== false) {
                            if (is_array($fieldValue)) {
                                $intersection = array_intersect($matches, $fieldValue);
                                $hasError = count($intersection) !== count($fieldValue);
                            } else {
                                $hasError = !list_in_array($fieldValue, $matches);
                            }
                        }
                    }
                }
            break;
            case 'fallback':
                if ($this->isEmpty($fieldValue)) {
                    $input[$field] = (strtolower($ruleParam) === 'null') ? null : $ruleParam;
                }
            break;
            default:
                $hasError = !self::validation($ruleName, $fieldValue, $ruleParam);
            break;
        }

        if ($hasError) {
            $this->addError($field, $ruleName, $fieldValue);
        }

        return false;
    }

    /**
     * Validate fields.
     * 
     * @param string $ruleName The input validation rule name to execute.
     * @param string $value The input value to validate.
     * @param string $expected The expected validation rule argument.
     * 
     * @return boolean Return true if the rule passed else false.
     */
    private static function validation(string $ruleName, mixed $value, ?string $expected = null): bool
    {
        try {
            return match ($ruleName) {
                'max_length', 'max' => mb_strlen((string) $value) <= (int) $expected,
                'min_length', 'min' => mb_strlen((string) $value) >= (int) $expected,
                'exact_length', 'length' => mb_strlen((string) $value) === (int) $expected,
                'string' => is_string($value),
                'numeric' => is_numeric($value),
                'integer' => self::validateInteger($value, $expected),
                'float' => self::validateFloat($value, $expected),
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'alphanumeric' => is_string($value) && ctype_alnum($value),
                'alphabet' => is_string($value) && ctype_alpha($value),
                'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
                'decimal' => self::validateFloat($value, $expected, true),
                'binary' => ctype_print($value) && !preg_match('/[^\x20-\x7E\t\r\n]/', $value),
                'hexadecimal' => ctype_xdigit($value),
                'array' => is_array($value) || is_array(json_decode($value, true, 512, JSON_THROW_ON_ERROR)),
                'json' => is_string($value) && json_validate($value),
                'path', 'scheme' => self::validatePath($ruleName, $value, $expected),
                default => self::validateOthers($ruleName, $value, $expected)
            };
        } catch (Throwable) {
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
            default => true,
        };
    }

    /**
     * Validates if the given value is an float or decimal number and optionally checks if it meets specific conditions.
     *
     * @param mixed $value The value to be validated.
     * @param mixed $param The condition to check for the integer value. Accepts 'positive', 'negative', or any other string to validate the integer without additional conditions.
     * @param bool $isDecimal Whether is decimal mode.
     *
     * @return bool Return true if the value is a valid integer and meets the condition (if provided); `false` otherwise.
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
            ? (bool) preg_match('#^[a-z][a-z\d+.-]*://#i', $value) 
            : str_starts_with($value, rtrim($param, '://') . '://');
    }

    /**
     * Validates uuid, ip address or phone number.
     *
     * @param string $name The rule name.
     * @param mixed $value The value to be validated.
     * @param string $value The rule parameter.
     *
     * @return bool Returns true if the value passed false otherwise.
     */
    private static function validateOthers(string $name, mixed $value, string $param): bool
    {
        return match($name){
            'uuid' => Func::isUuid((string) $value, ($param === '') ? 4 : (int) $param),
            'phone' => Func::isPhone((string) $value, ($param === '') ? 10 : (int) $param),
            'ip' => IP::isValid((string) $value, ($param === '') ? 0 : (int) $param),
            default => true
        };
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
        return ($value === null || $value === '' || $value === []) 
            ? true 
            : is_empty($value);
    }

    /**
     * Convert rule param to bool.
     * 
     * @param string $value The input rule param.
     * 
     * @return bool true or false.
     */
    private static function toBool(string $value): bool
    {
        return match(strtolower($value)){
            'true', '1' => true,
            default => false
        };
    }

    /**
     * Convert a string of arguments into a structured array.
     *
     * Example input: 'true, [user, root, system]'
     * Result: [true, ['user', 'root', 'system']]
     *
     * The string should be in the format:
     * - A boolean value (true/false/1/0) 
     * - Optionally followed by a comma and a list in square brackets
     *
     * @param string $value The argument string to convert.
     * @return array An array with:
     *               - (bool) The boolean value
     *               - (array) The list of items, empty if none provided
     */
    private static function toArguments(string $value): array
    {
        if(!$value){
            return [true, []];
        }

        if (str_contains($value, ',')) {
            [$bool, $options] = explode(',', $value, 2);

            $bool = self::toBool($bool);
            $options = trim($options);
            $items = [];

            if (str_starts_with($options, '[') && str_ends_with($options, ']')) {
                $options = trim($options, '[]');
                $items = array_filter(
                    array_map('trim', explode(',', $options)),
                    fn($v) => $v !== ''
                );
            }

            return [$bool, $items];
        }

        return [self::toBool($value), []];
    }

    /**
     * Parses a validation rule string to extract the rule name and optional parameter.
     * 
     * @param string $rule The validation rule string to be parsed.
     * 
     * @return array<int,string> Return an array of rule names and parameter if available.
     */
    private static function parseRule(string $rule): array
    {
        $name = '';
        $param = '';

        if (preg_match('/^(\w+)(?:\(([^)]*)\))?$/', $rule, $matches)) {
            $name = $matches[1] ?? '';
            $param = $matches[2] ?? '';
        }
        
        return [trim($name), trim($param)];
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