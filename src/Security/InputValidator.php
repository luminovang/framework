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
use \JsonException;

final class InputValidator implements ValidationInterface
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

                foreach ($ruleParts as $rulePart) {
                    $ruleName = preg_replace("/\s*\([^)]*\)/", '', $rulePart);
                    $ruleParam = str_replace([$ruleName . '(', ')'], '', $rulePart);
                    
                    switch ($ruleName) {
                        case 'none':
                            return true;
                        case 'required':
                            if (is_empty($fieldValue)) {
                                $this->addError($field, $ruleName, $fieldValue);
                            }
                        break;
                        case 'callback':
                            if ($ruleParam !== '' && is_callable($ruleParam) && !$ruleParam($fieldValue, $field)) {
                                $this->addError($field, $ruleName, $fieldValue);
                            }
                        break;
                        case 'match':
                            if (!preg_match('/' . $ruleParam . '/', $fieldValue)) {
                                $this->addError($field, $ruleName, $fieldValue);
                            }
                        break;
                        case 'equals':
                            if ($fieldValue !== $input[$ruleParam]) {
                                $this->addError($field, $ruleName, $fieldValue);
                            }
                        break;
                        case 'in_array':
                            if (!empty($ruleParam)) {
                                $matches = list_to_array($ruleParam);
                                if (!in_array($fieldValue, $matches)) {
                                    $this->addError($field, $ruleName, $fieldValue);
                                }
                            }
                        break;
                        case 'keys_exist':
                            if (!empty($ruleParam)) {
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
                            $defaultValue = $ruleParam;
                            if (is_empty($fieldValue)) {
                                $defaultValue = "";
                            } elseif (strtolower($ruleParam) == 'null') {
                                $defaultValue = null;
                            }
                            $input[$field] = $defaultValue;
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

        if(!empty($message)){
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
     * Validate fields 
     * @param string $ruleName The name of the rule to validate
     * @param string $value The value to validate
     * @param string $rule The rule line
     * @param string $param additional validation parameters
     * @return boolean true if the rule passed else false
    */
    private static function validation(string $ruleName, mixed $value, string $rule, mixed $param = null): bool
    {
        try{
            return match ($ruleName) {
                'max_length', 'max' => mb_strlen($value) <= (int) $param,
                'min_length', 'min' => mb_strlen($value) >= (int) $param,
                'exact_length', 'length' => mb_strlen($value) == (int) $param,
                'string' => is_string($value),
                'integer' => match ($param) {
                    'positive' => filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0,
                    'negative' => filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value < 0,
                    default => filter_var($value, FILTER_VALIDATE_INT) !== false,
                },
                'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'alphanumeric' => preg_match("/[^A-Za-z0-9]/", $value) !== false,
                'alphabet' => preg_match("/^[A-Za-z]+$/", $value) !== false,
                'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
                'uuid' => func()->isUuid($value), //$version = (int) $param;
                'ip' =>  func()->ip()->isValid($value, (int) $param),
                'phone' => func()->isPhone($value),
                'decimal' => preg_match('/^-?\d+(\.\d+)?$/', $value) === 1,
                'binary' => ctype_print($value) && !preg_match('/[^\x20-\x7E\t\r\n]/', $value),
                'hexadecimal' => ctype_xdigit($value),
                'array' => is_array(json_decode($value, true, 512, JSON_THROW_ON_ERROR)) || is_array($value),
                'json' => (json_decode($value, null, 512, JSON_THROW_ON_ERROR) && json_last_error() === JSON_ERROR_NONE),
                'path' => match ($param) {
                    'true' => is_string($value) && is_readable($value),
                    default => is_string($value) && preg_match("#^[a-zA-Z]:[\\\/]{1,2}#", $value)
                },
                'scheme' => str_starts_with($value, rtrim($param, '://')),
                default => true,
            };
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * Add validation error message
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
     * Translate placeholders
     * 
     * @param string $message message to be translated
     * @param array $placeholders array 
     * 
     * @return string 
    */
    private static function replace(string $message, array $placeholders = []): string 
    {
        if($placeholders === []){
            return $message;
        }

        return str_replace(['{field}', '{rule}', '{value}'], $placeholders, $message);
    }
}
