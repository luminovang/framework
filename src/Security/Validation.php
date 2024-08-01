<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Security;

use \Luminova\Interface\ValidationInterface;
use \Exception;
use \JsonException;

final class Validation implements ValidationInterface
{
    /**
     * @var array<string,array> $failures validated errors messages.
    */
    private array $failures = [];

    /**
     * @var array<string,string> $rules validation rules.
    */
    public array $rules = [];

    /**
     * @var array<string,array> $messages validation error messages.
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
            if(isset($input[$field])){
                $fieldValue = $input[$field] ?? null;
                $ruleParts = explode('|', $rule);

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
                        case 'in_array':
                            if ($ruleParam !== '') {
                                $matches = list_to_array($ruleParam);
                                if (!in_array($fieldValue, $matches)) {
                                    $this->addError($field, $ruleName, $fieldValue);
                                }
                            }
                        break;
                        case 'keys_exist':
                            if ($ruleParam !== '') {
                                $matches = list_to_array($ruleParam);
                                if (is_array($fieldValue)) {
                                    $intersection = array_intersect($matches, $fieldValue);
                                    $exist = count($intersection) === count($fieldValue);
                                } else {
                                    $exist = list_in_array($fieldValue, $matches);
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
                            if (!static::validation($ruleName, $fieldValue, $rulePart, $ruleParam)) {
                                $this->addError($field, $ruleName, $fieldValue);
                            }
                        break;
                    }
                }
            }else{
                $this->addError($field, '*', 'Form input field [' . $field . '] is missing');
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
    public function getError(string|int $fieldIndex = 0, string $type = 'message'): string
    {
        $errors = $this->getErrorField($fieldIndex);

        if($errors === []){
            return '';
        }

        return $errors[0][$type] ?? '';
    }

     /**
     * {@inheritdoc}
    */
    public function getErrorFieldLine(string $prefix = ''): string
    {
        return $prefix . $this->getError(0, 'field');
    }

    /**
     * {@inheritdoc}
    */
    public function getErrorLine(string|int $fieldIndex = 0, int $errorIndex = 0): string
    {
        $errors = $this->getErrorField($fieldIndex);

        if($errors === []){
            return '';
        }
        
        $error = $errors[$errorIndex] ?? null;

        if($error === null){
            return '';
        }

        return $errors['message'] ?? '';
    }

    /**
     * {@inheritdoc}
    */
    public function getErrorField(string|int $fieldIndex = 0): array
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

        // Remove the parent key from the error array
        unset($this->failures[$field]);

        return $infos;
    }

    /**
     * {@inheritdoc}
    */
    public function setRules(array $rules, array $messages = []): self
    {
        $this->rules = $rules;

        if($messages !== []){
            $this->messages = $messages;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function addRule(string $field, string $rules, array $messages = []): self
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
     * @param string $rule The rule line.
     * @param string $param additional validation parameters.
     * 
     * @return boolean Return true if the rule passed else false.
    */
    private static function validation(string $ruleName, mixed $value, string $rule, mixed $param = null): bool
    {
        try {
            return match ($ruleName) {
                'max_length', 'max' => is_string($value) && mb_strlen($value) <= (int) $param,
                'min_length', 'min' => is_string($value) && mb_strlen($value) >= (int) $param,
                'exact_length', 'length' => is_string($value) && mb_strlen($value) === (int) $param,
                'string' => is_string($value),
                'integer' => self::validateInteger($value, $param),
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'alphanumeric' => ctype_alnum($value),
                'alphabet' => ctype_alpha($value),
                'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
                'decimal' => filter_var($value, FILTER_VALIDATE_FLOAT) !== false && str_contains((string) $value, '.'),
                'binary' => ctype_print($value) && !preg_match('/[^\x20-\x7E\t\r\n]/', $value),
                'hexadecimal' => ctype_xdigit($value),
                'array' => is_array($value) || is_array(json_decode($value, true, 512, JSON_THROW_ON_ERROR)),
                'json' => is_json($value),
                'path', 'scheme' => self::validatePath($ruleName, $value, $param),
                default => self::validateOthers($ruleName, $value, $param)
            };
        } catch (Exception|JsonException) {
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
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return false;
        }

        if ($param === 'positive') {
            return (int) $value > 0;
        }

        if ($param === 'negative') {
            return (int) $value < 0;
        }

        return true;
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
            if($param === 'true' || $param === 'readable'){
                return is_readable($value);
            }

            if($param === 'writable'){
                return is_writable($value);
            }

            return preg_match('#^(?:[a-zA-Z]:[\\\/]|/|\\\\)[\\w\\s\\-_.\\/\\\\]+$#i', $value);
        }

        if($param === ''){
            return preg_match('#^[a-z][a-z\d+.-]*://#i', $value);
        }

        return str_starts_with($value, rtrim($param, '://') . '://');
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
        if($name === 'uuid' || $name === 'ip' || $name === 'phone'){
            static $func = null;
            $func ??= func();

            if($name === 'phone'){
                return $func->isPhone((string) $value, ($param === '') ? 10 : (int) $param);
            }

            if($name === 'uuid'){
                return $func->isUuid((string) $value, ($param === '') ? 4 : (int) $param);
            }

            if($name === 'ip'){
                return $func->ip()->isValid((string) $value, ($param === '') ? 0 : (int) $param);
            }
        }

       return true;
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
        if ($value === null || $value === '' || $value === []) {
            return true;
        }

        return is_empty($value);
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

        if($message === null){
            $message = "Validation failed for field: '{$field}', while validating [{$ruleName}].";
        }else{
            $message = static::replace($message, [
                $field, $ruleName, $value
            ]);
        }

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
        if($placeholders === []){
            return $message;
        }

        return str_replace(['{field}', '{rule}', '{value}'], $placeholders, $message);
    }
}
