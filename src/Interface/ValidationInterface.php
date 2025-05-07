<?php
/**
 * Luminova Framework Interface for validating input data against defined rules.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

interface ValidationInterface 
{
    /**
     * Validate input data against the defined rules.
     * 
     * @param array<string,mixed> $input The input request body to validate (e.g, `$this->request->getBody()`, `$_POST`, `$_GET`, `$_REQUEST`).
     * @param array<string,string> $rules Optional array of rules to override initialized rules (default `NULL`).
     * 
     * @return bool Returns true if all rules are passed, otherwise false.
     */
    public function validate(array $input, ?array $rules = null): bool;

    /**
     * Check if all validation rules passed.
     * 
     * @return bool Returns true if all validation rules are passed, otherwise false.
     */
    public function isPassed(): bool;

    /**
     * Get all validation error messages.
     * 
     * @return array<string,array> Return array of validation error messages, otherwise empty array.
     */
    public function getErrors(): array;

    /**
     * Get the error message for a specific input field or using field index position.
     * 
     * @param string|int $fieldIndex The input field by index position or the input filed name (default: 0).
     * 
     * @return string Return an error message for the specified field.
     */
    public function getErrorMessage(string|int $fieldIndex = 0): string;

    /**
     * Set validation fields and rules.
     * 
     * @param array<int,string> $rules The array of validation rules 
     *                  (e.g, ['email' => 'string|email', 'name' => 'required']).
     * 
     * @return self Return instance of the Validation class.
     */
    public function setRules(array $rules): self;

    /**
     * Set validation error messages based on fields and rules.
     * 
     * @param array<string,array<string,string>> $messages An error messages for validation rules 
     *              (e.g, ['email' => ['string' => '...', 'email' => '...'], 'name' => ['required' => '...']]).
     * 
     * @return self Return instance of the Validation class.
     */
    public function setMessages(array $messages): self;

    /**
     * Add a field and rules with an optional error messages using the rule name as an array key for the message.
     * 
     * @param string $field The form input field name (e.g, `name`, `email`).
     * @param string $rules The input field validation rules (e.g, `required|string|email`).
     * @param array<string,string> $messages Optional validation error message for the each rule (e.g, ['required' => '...', 'string' => '...', 'email' => '...']).
     * 
     * @return self Return instance of the Validation class.
     */
    public function addField(string $field, string $rules, array $messages = []): self;

    /**
     * Get the error message at the specified field and error indexes.
     * This will return the first input field that has error, and the first error rule.
     * 
     * @param string|int $fieldIndex The input field by index number or input filed name (default: 0).
     * @param int $errorIndex The error index for current field if has multiple rules (default: 0).
     * 
     * @return string Error message.
     */
    public function getError(string|int $indexField = 0, int $errorIndex = 0): string;

    /**
     * Get information about the validation error at the specified field index or field name.
     * When passed an integer index, it will return the first validation error.
     * When passed a string field name, it will return the validation error related to that field name.
     * 
     * @param string|int $fieldIndex The input field by index number or input filed name (default: 0).
     * 
     * @return array<int,array> Return array error information related to the field validation.
     */
    public function getErrorFields(string|int $fieldIndex = 0): array;

    /**
     * Get the first field that has validation error.
     * 
     * @param string|int $fieldIndex The input field by index number or input filed name.
     * 
     * @return string Return the field name causing the validation error.
     */
    public function getErrorField(string|int $fieldIndex = 0): string;
}