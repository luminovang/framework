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

use \Luminova\Interface\RequestInterface;
use \Luminova\Interface\LazyObjectInterface;

interface InputValidationInterface 
{
    /**
     * Validate input data against the applied rules.
     * 
     * This method validates incoming request data against validation rules defined via  
     * `$input->rules = [...]`, `$input->setRules()`, or `$input->addField()`.  
     * 
     * You must define rules before calling `validate()`, or pass them as the second parameter.
     * 
     * @param \Luminova\Http\Request<RequestInterface>|LazyObjectInterface<RequestInterface>|null $request The HTTP request instance containing input data to validate (default: `null`).
     * @param array<string,string>|null $rules Optional validation rules if not previously applied (default `null`).
     * 
     * @return bool Returns true if all validation rules pass, otherwise false.
     * @throws RuntimeException If passed object of `LazyObjectInterface` that does not contain `RequestInterface` object. When `$request` is null without setting input data from `setBody`.
     * 
     * @see setBody() - To set validate input data from array.
     * 
     * @example - Example:
     * ```php
     * $request = new Request();
     * 
     * $isValid = $input->validate($request, [...]);
     * ```
     * 
     * @example - Controller Example:
     * ```php
     * // /app/Controller/Http/*
     * 
     * $isValid = $this->input->validate($this->request, [...]);
     * ```
     * 
     * @example - Validate From Input Array:
     * ```php
     * // /app/Controller/Http/*
     * $body = [...];
     * $isValid = $this->input->setBody($body)->validate(rules: [...]);
     * ```
     */
    public function validate(RequestInterface|LazyObjectInterface|null $request = null, ?array $rules = null): bool;

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
     * Set the input body to validate from array.
     *
     * When the body is passed by reference, any fields modified by `default` or `fallback` rules 
     * are written back to the original array.
     *
     * @param array<string,mixed> &$body The input data to validate passed by reference.
     *
     * @return self Return instance of validation object.
     *
     * @example - Example:
     * ```php
     * $body = [
     *     'name' => 'Peter',
     *     'email' => 'peter@example.com'
     * ];
     *
     * // Normal usage
     * $isValid = $input->setBody($body)->validate();
     *
     * // Optional: pass by reference to allow body mutation
     * $isValid = $input->setBody($body)->validate();
     * ```
     */
    public function setBody(array &$body): self;

    /**
     * Set validation fields and rules.
     * 
     * @param array<int,string> $rules The array of validation rules 
     *                  (e.g, ['email' => 'string|email', 'name' => 'required']).
     * 
     * @return static Return instance of the Validation class.
     */
    public function setRules(array $rules): self;

    /**
     * Set validation error messages based on fields and rules.
     * 
     * @param array<string,array<string,string>> $messages An error messages for validation rules 
     *              (e.g, ['email' => ['string' => '...', 'email' => '...'], 'name' => ['required' => '...']]).
     * 
     * @return static Return instance of the Validation class.
     */
    public function setMessages(array $messages): self;

    /**
     * Add a field and rules with an optional error messages using the rule name as an array key for the message.
     * 
     * @param string $field The form input field name (e.g, `name`, `email`).
     * @param string $rules The input field validation rules (e.g, `required|string|email`).
     * @param array<string,string> $messages Optional validation error message for the each rule (e.g, ['required' => '...', 'string' => '...', 'email' => '...']).
     * 
     * @return static Return instance of the Validation class.
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
     * Retrieve validation errors for a specific field by name or index.
     * 
     * This method returns the validation errors for a given field, identified either by 
     * its array index or its field name.
     * 
     * - If `$fieldIndex` is an integer, the corresponding field name is resolved by position.
     * - If `$fieldIndex` is a string, the method fetches the error data for that field.
     * 
     * @param string|int $fieldIndex The field name or index position in error list (default: 0).
     * 
     * @return array<int,array> An array of validation error details for the specified field.
     * 
     * > **Note:**
     * >  Once retrieved, the field’s error data is removed from `$this->failures`.
     */
    public function getFields(string|int $fieldIndex = 0): array;

    /**
     * Get the first field that has validation error.
     * 
     * @param string|int $fieldIndex The input field by index number or input filed name.
     * 
     * @return string Return the field name causing the validation error.
     */
    public function getField(string|int $fieldIndex = 0): string;

    /**
     * Validate a username according to standard requirements.
     * 
     * @param string $username The username to validate.
     * @param bool $allowUppercase Whether to allow username to contain uppercase (default: `false`).
     * @param array $reservedUsernames Optional list if reserved usernames (e.g, `['root', 'admin', 'system']`).
     * 
     * @return array Validation result containing:
     *               - (bool) Whether the username is valid
     *               - (string|null) Error message if invalid
     */
    public static function validateUsername(
        string $username, 
        bool $allowUppercase = true,  
        array $reservedUsernames = []
    ): array;
}