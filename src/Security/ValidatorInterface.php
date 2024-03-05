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

interface ValidatorInterface 
{

    /**
     * Validate entries
     * @param array $input array input to validate it fields
     * @param array $rules Optional passed rules as array
     * @return boolean true if the rule passed else false
    */
    public function validateEntries(array $input, array $rules = []): bool;

    /**
     * Validate fields 
     * @param string $ruleName The name of the rule to validate
     * @param string $value The value to validate
     * @param string $rule The rule line
     * @param string $param additional validation parameters
     * @return boolean true if the rule passed else false
    */
    public function validateField(string $ruleName, string $value, string $rule, ?string $param = null): bool;

    /**
     * Gets validation error
     * @return array validation error message
    */
    public function getErrors(): array;

    /**
     * Get validation error messages
     * @param string $field messages input field name
     * @return string Error message
    */
    public function getError(string $field): string;

    /**
     * Add validation error message
     * 
     * @param string $field input field name
     * @param string $ruleName Rule name
     * @param string $message Error message
     * 
     * @return void 
    */
    public function addError(string $field, string $ruleName, string $message = 'Validation failed for %s.'): void;

    /**
     * Set rules array array with optional messages
     * @param array $rules validation rules
     * @param array $message optional pass response message for validation
     * @return self InputValidator instance 
    */
    public function setRules(array $rules, array $messages = []): self;

   /**
     * Add single rule with optional message
     * @param string $field validation rule input field name
     * @param array $messages optional pass response message for rule validation
     * @return self InputValidator instance 
    */
    public function addRule(string $field, string $rules, array $messages = []): self;

    /**
     * Set array list rule messages
     * @param array $messages messages to set
     * @return self InputValidator instance 
    */
    public function setMessages(array $messages): self;

     /**
     * Set a single validation rule messages
     * @param string $field messages input field name
     * @param array $messages messages to set
     * @return self InputValidator instance 
    */
    public function addMessage(string $field, array $messages): self;

    /**
     * Get validation error messages
     * @param int $indexField field index
     * @param int $indexErrors error index
     * 
     * @return string Error message
    */
    public function getErrorLine(int $indexField = 0, int $indexErrors = 0): string;
}
