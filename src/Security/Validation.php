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
namespace Luminova\Security;

use \Luminova\Interface\ValidationInterface;
use \Luminova\Interface\LazyInterface;
use \Luminova\Functions\Func;
use \Luminova\Functions\IP;
use \Throwable;

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

                switch ($ruleName) {
                    case 'none':
                    case 'nullable':
                        return true;
                    case 'required':
                        if ($this->isEmpty($fieldValue)) {
                            $this->addError($field, $ruleName, $fieldValue);
                        }
                    break;
                    case 'callback':
                        if ($ruleParam !== '' && is_callable($ruleParam) && !$ruleParam($fieldValue, $field)) {
                            $this->addError($field, $ruleName, $fieldValue);
                        }
                    break;
                    case 'match':
                        if ($ruleParam !== '' && !preg_match('/' . $ruleParam . '/', $fieldValue)) {
                            $this->addError($field, $ruleName, $fieldValue);
                        }
                    break;
                    case 'equals':
                        if ($fieldValue !== $input[$ruleParam]) {
                            $this->addError($field, $ruleName, $fieldValue);
                        }
                    break;
                    case 'is_value':
                        if ($fieldValue !== $ruleParam) {
                            $this->addError($field, $ruleName, $fieldValue);
                        }
                    break;
                    case 'not_equal':
                        if ($fieldValue === $input[$ruleParam]) {
                            $this->addError($field, $ruleName, $fieldValue);
                        }
                    break;
                    case 'in_array':
                        if ($ruleParam !== '') {
                            $matches = list_to_array($ruleParam);
                            $inArray = ($matches === false) ? false : in_array($fieldValue, $matches);
                            if (!$inArray) {
                                $this->addError($field, $ruleName, $fieldValue);
                            }
                        }
                    break;
                    case 'keys_exist':
                    case 'keys_exists':
                        if ($ruleParam !== '') {
                            $matches = list_to_array($ruleParam);
                            $exist = false;

                            if($matches !== false) {
                                if (is_array($fieldValue)) {
                                    $intersection = array_intersect($matches, $fieldValue);
                                    $exist = count($intersection) === count($fieldValue);
                                } else {
                                    $exist = list_in_array($fieldValue, $matches);
                                }
                            }

                            if (!$exist) {
                                $this->addError($field, $ruleName, $fieldValue);
                            }
                        }
                    break;
                    case 'fallback':
                        if ($this->isEmpty($fieldValue)) {
                            $input[$field] = (strtolower($ruleParam) === 'null') ? null : $ruleParam;
                        }
                    break;
                    default:
                        if (!self::validation($ruleName, $fieldValue, $ruleParam)) {
                            $this->addError($field, $ruleName, $fieldValue);
                        }
                    break;
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
     * Validate fields.
     * 
     * @param string $ruleName The name of the rule to validate.
     * @param string $value The value to validate.
     * @param string $param additional validation parameters.
     * 
     * @return boolean Return true if the rule passed else false.
     */
    private static function validation(string $ruleName, mixed $value, mixed $param): bool
    {
        try {
            return match ($ruleName) {
                'max_length', 'max' => mb_strlen((string) $value) <= (int) $param,
                'min_length', 'min' => mb_strlen((string) $value) >= (int) $param,
                'exact_length', 'length' => mb_strlen((string) $value) === (int) $param,
                'string' => is_string($value),
                'integer' => self::validateInteger($value, $param),
                'float' => self::validateFloat($value, $param),
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'alphanumeric' => is_string($value) && ctype_alnum($value),
                'alphabet' => is_string($value) && ctype_alpha($value),
                'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
                'decimal' => self::validateFloat($value, $param, true),
                'binary' => ctype_print($value) && !preg_match('/[^\x20-\x7E\t\r\n]/', $value),
                'hexadecimal' => ctype_xdigit($value),
                'array' => is_array($value) || is_array(json_decode($value, true, 512, JSON_THROW_ON_ERROR)),
                'json' => is_string($value) && json_validate($value),
                'path', 'scheme' => self::validatePath($ruleName, $value, $param),
                default => self::validateOthers($ruleName, $value, $param)
            };
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Validates if the given value is an integer and optionally checks if it meets specific conditions.
     *
     * @param mixed $value The value to be validated.
     * @param mixed $param The condition to check for the integer value. Accepts 'positive', 'negative', or any other string to validate the integer without additional conditions.
     *
     * @return bool Return true if the value is a valid integer and meets the condition (if provided); `false` otherwise.
     */
    private static function validateInteger(mixed $value, mixed $param = 'none'): bool
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
     * @param mixed $param The condition to check for the file path. Accepts 'true' to check if the path is readable or any other string to validate the path format.
     *
     * @return bool Returns true if the value passed false otherwise.
     */
    private static function validatePath(string $rule, mixed $value, mixed $param = ''): bool
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
     * @return Return true if the value is not empty, false otherwise.
     */
    private function isEmpty(mixed $value): bool 
    {
        return ($value === null || $value === '' || $value === []) 
            ? true 
            : is_empty($value);
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
        
        return [
            $name,
            ($param === '') ? '' : trim($param)
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