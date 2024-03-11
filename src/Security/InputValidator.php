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

use \Luminova\Security\ValidatorInterface;

class InputValidator implements ValidatorInterface
{
    /**
     * @var array $errors validated errors messages
    */
    private array $errors = [];

     /**
     * @var array $validationRules validation rules
    */
    public array $validationRules = [];

    /**
     * @var array $errorMessages validation error messages
    */
    public array $errorMessages = [];

    /**
     * Validate entries
     * @param array $input array input to validate it fields
     * @param array $rules Optional passed rules as array
     * 
     * @return self Use $validate->isPassed() method to check the validity of
    */
    public function validate(array $input, array $rules = []): self
    {
        $this->validateEntries($input, $rules);
    
        return $this;
    }

    /**
     * Validate entries
     * @param array $input array input to validate it fields
     * @param array $rules Optional passed rules as array
     * 
     * @return boolean true if the rule passed else false
    */
    public function validateEntries(array $input, array $rules = []): bool
    {
        if ($rules === []) {
            $rules = $this->validationRules;
        }
    
        if ($rules === [] || ($rules === [] && $input === [])) {
            return true;
        }

        $this->errors = [];
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
                            if (is_callable($ruleParam) && !$ruleParam($fieldValue, $field)) {
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
                        case 'is_list':
                            if (!is_list($fieldValue, true)) {
                                $this->addError($field, $ruleName, $fieldValue);
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
                            if (!$this->validateField($ruleName, $fieldValue, $rulePart, $ruleParam)) {
                                $this->addError($field, $ruleName, $fieldValue);
                            }
                        break;
                    }
                }
            }else{
                $this->addError($field, '*', 'Form input field [' . $field . '] is missing', null);
            }
        }

        return $this->errors === [];
    }

    /**
     * Validate fields 
     * 
     * @param string $ruleName The name of the rule to validate
     * @param mixed $value The value to validate
     * @param string $rule The rule line
     * @param string $param additional validation parameters
     * 
     * @return boolean true if the rule passed else false
    */
    public function validateField(string $ruleName, mixed $value, string $rule, mixed $param = null): bool
    {
        return match ($ruleName) {
            'max_length', 'max' => strlen($value) <= (int) $param,
            'min_length', 'min' => strlen($value) >= (int) $param,
            'exact_length', 'length' => strlen($value) == (int) $param,
            'integer' => match ($param) {
                'positive' => filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value > 0,
                'negative' => filter_var($value, FILTER_VALIDATE_INT) !== false && (int) $value < 0,
                default => filter_var($value, FILTER_VALIDATE_INT) !== false,
            },
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'alphanumeric' => preg_match("/[^A-Za-z0-9]/", $value) !== false,
            'alphabet' => preg_match("/^[A-Za-z]+$/", $value) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'uuid' => func()->is_uuid($value), //$version = (int) $param;
            'ip' =>  func()->ip()->isValid($value, (int) $param),
            'phone' => func()->is_phone($value),
            'decimal' => preg_match('/^-?\d+(\.\d+)?$/', $value) === 1,
            'binary' => ctype_print($value) && !preg_match('/[^\x20-\x7E\t\r\n]/', $value),
            'hexadecimal' => ctype_xdigit($value),
            'array' => is_array(json_decode($value, true)) || is_array($value),
            'json' => (json_decode($value) && json_last_error() == JSON_ERROR_NONE),
            'path' => match ($param) {
                'true' => is_string($value) && is_readable($value),
                default => is_string($value) && preg_match("#^[a-zA-Z]:[\\\/]{1,2}#", $value)
            },
            'scheme' => strpos($value, rtrim($param, '://')) === 0,
            default => true,
        };
    }

    /**
     * Gets validation error
     * @return array validation error message
    */
    public function getErrors(): array
    {
        return $this->errors??[];
    }

    /**
     * Get validation error messages
     * 
     * @param string $field messages input field name
     * 
     * @return string Error message
    */
    public function getError(string $field): string
    {
        return $this->errors[$field][0]??'';
    }

    /**
     * Get validation error filed
     * 
     * @param string $field messages input field name
     * 
     * @return string Error field
    */
    public function getErrorField(string $field): string
    {
        return $this->errors[$field]['field']??'';
    }

    /**
     * Get validation error messages
     * 
     * @param int $fieldIndex field index
     * @param int $errorsIndex error index
     * 
     * @return string Error message
    */
    public function getErrorLine(int $fieldIndex = 0, int $errorsIndex = 0): string
    {
        $errors = $this->getCurrentErrorInfo($fieldIndex);

        if($errors === []){
            return '';
        }
        
        $errorKey = array_keys($errors)[$errorsIndex] ?? null;

        // Retrieve the error message based on the indices
        $errorMessage = $errors[$errorKey] ?? '';

        return $errorMessage;
    }

    /**
     * Get validation error information
     * 
     * @param int $fieldIndex field index
     * 
     * @return array Error information
    */
    public function getCurrentErrorInfo(int $fieldIndex = 0): array
    {
        $errors = $this->errors;
        // Get the keys of the provided indices
        $fieldKey = array_keys($errors)[$fieldIndex] ?? null;

        if($fieldKey === null){
            return [];
        }

        // Retrieve the error message based on the indices
        $errorInfos = $errors[$fieldKey] ?? [];

        // Remove the parent key from the error array
        unset($errors[$fieldKey]);

        return $errorInfos;
    }

    /**
     * Get validation current error field
     * 
     * @param string $prefix prefix
     * 
     * @return string $errorField
    */
    public function getCurrentErrorField(string $prefix = ''): string
    {
        $errors = $this->getCurrentErrorInfo();

        $errorField = $errors['field'] ?? '';

        return $prefix . $errorField;
    }

     /**
     * Get validation error messages
     * @param int $indexField field index
     * @param int $indexErrors error index
     * 
     * @deprecated This method will be removed in a future release use getErrorLine instead
     * @return string Error message
    */
    public function getErrorByIndices(int $indexField = 0, int $indexErrors = 0): string 
    {
        return $this->getErrorLine($indexField, $indexErrors);
    }

    /**
     * Add validation error message
     * 
     * @param string $field input field name
     * @param string $ruleName Rule name
     * @param mixed $value Filed valu
     * 
     * @return void 
    */
    public function addError(string $field, string $ruleName, mixed $value = null): void
    {
        $message = $this->errorMessages[$field][$ruleName] ?? null;

        if($message === null){
            $message = 'Validation failed for "' . $field . '" while validating [' . $ruleName . '].';
        }else{
            $message = static::replaceMessage($message, [
                $field, $ruleName, $value
            ]);
        }

        $this->errors[$field][] = $message;
        $this->errors[$field]['field'] = $field;
    }

    /**
     * Set rules array array with optional messages
     * @param array $rules validation rules
     * @param array $message optional pass response message for validation
     * @return self InputValidator instance 
    */
    public function setRules(array $rules, array $messages = []): self
    {
        $this->validationRules = $rules;
        if($messages !== []){
            $this->errorMessages = $messages;
        }

        return $this;
    }

   /**
     * Add single rule with optional message
     * @param string $field validation rule input field name
     * @param array $messages optional pass response message for rule validation
     * @return self InputValidator instance 
    */
    public function addRule(string $field, string $rules, array $messages = []): self
    {
        $this->validationRules[$field] = $rules;
        if(!empty($message)){
            $this->errorMessages[$field] = $messages;
        }

        return $this;
    }

    /**
     * Set array list rule messages
     * @param array $messages messages to set
     * @return self InputValidator instance 
    */
    public function setMessages(array $messages): self
    {
        $this->errorMessages = $messages;

        return $this;
    }

    /**
     * Set a single validation rule messages
     * @param string $field messages input field name
     * @param array $messages messages to set
     * @return self InputValidator instance 
    */
    public function addMessage(string $field, array $messages): self
    {
        $this->errorMessages[$field] = $messages;

        return $this;
    }

    /**
     * Check if validation passed
     * 
     * @return boolean true if the rule passed else false
    */
    public function isPassed(): bool
    {
        return $this->errors === [];
    }

     /**
     * Translate placeholders
     * 
     * @param string $message message to be translated
     * @param array $placeholders array 
     * 
     * @return string 
    */
    private static function replaceMessage(string $message, array $placeholders = []): string 
    {
        if($placeholders === []){
            return $message;
        }

        $replaced = str_replace(['[field]', '[rule]', '[value]'], $placeholders, $message);

        return $replaced;
    }
}