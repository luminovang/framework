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
     * Validate input data against defined rules.
     *
     * Uses rules defined via `$this->rules`, {@see setRules()}, or {@see addField()}.
     * Input data is resolved from the provided request or from previously set body data.
     *
     * Behavior:
     * - If no rules are defined, validation passes immediately.
     * - Rules may be defined as pipe-separated strings or structured arrays.
     * - Empty rules and special rules (`none`, `nullable`) are ignored.
     * - Validation failures are collected and reset on each call.
     *
     * @param RequestInterface|LazyObjectInterface|null $request
     *        Optional request instance used to resolve input data.
     *
     * @return bool True if validation passes (no errors), false otherwise.
     *
     * @throws RuntimeException If:
     * - A LazyObject does not resolve to a RequestInterface
     * - No request is provided and no input body has been set
     *
     * @see self:setRequest() set request object to read input from.
     * @see self::setBody() Set input data manually.
     * @see self::setRules() Set input validation rules.
     * @see self::setMessage() Set input validation rule error messages.
     *
     * @example - Basic usage:
     * ```php
     * $isValid = $input->validate($request);
     * ```
     *
     * @example - Controller usage:
     * ```php
     * $this->input->rules = [...];
     * $isValid = $this->input->validate($this->request);
     * ```
     *
     * @example - Validate from array:
     * ```php
     * $isValid = $input->setBody($data)->validate();
     * ```
     */
    public function validate(RequestInterface|LazyObjectInterface|null $request = null): bool;

    /**
     * Determine if validation passed.
     *
     * @return bool True if no validation errors exist, false otherwise.
     */
    public function isPassed(): bool;

    /**
     * Get all validation errors.
     *
     * @return array<string, array<int, array{message:string, rule:string, field:string}>>
     *         Errors grouped by field name. Returns an empty array if none exist.
     */
    public function getErrors(): array;

    /**
     * Get the first validation error message for a field.
     *
     * If an integer is provided, the field is resolved by its position in the
     * error list. If a string is provided, it is treated as the field name.
     *
     * @param string|int $field Field name or index (default: 0 = first field with error).
     *
     * @return string Error message, or an empty string if not found.
     */
    public function getErrorMessage(string|int $field = 0): string;

    /**
     * Set the input body to validate from array.
     *
     * When the body is passed by reference, any fields modified by `default` or `fallback` rules 
     * are written back to the original array.
     *
     * @param array<string,mixed> &$body The input data to validate passed by reference.
     *
     * @return static Return instance of validation object.
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
     * Set the HTTP request instance used for validation.
     *
     * This allows the validator to resolve input data from a request object
     * without passing it into {@see validate()} each time.
     *
     * If a request is already set, it will be replaced.
     *
     * @param RequestInterface|LazyObjectInterface $request HTTP request instance.
     *
     * @return static Return instance of validation object.
     *
     * @throws RuntimeException If a LazyObject cannot be resolved into a RequestInterface.
     * @see self::validate()
     */
    public function setRequest(RequestInterface|LazyObjectInterface $request): self;

    /**
     * Set validation rules for input fields.
     *
     * Each key represents a field name, and the value defines its validation rules.
     * Rules can be provided as:
     * - A pipe-separated string (e.g. "required|email")
     * - An array of rule definitions
     *
     * Rule definitions may be:
     * - A string rule (e.g. "required", "min(3)")
     * - An array returned by {@see Rule} static methods
     *
     * Both formats can be mixed across fields.
     *
     * @param array<string,string|array<int,string|array>> $rules Validation rules mapped by field name.
     *
     * @return static Return instance of validation object.
     *
     * @see \Luminova\Security\Rule
     *
     * @example - String rules
     * ```php
     * $input->setRules([
     *     'email' => 'required|email',
     *     'name'  => 'required',
     * ]);
     * ```
     *
     * @example - Fluent rule builder
     * ```php
     * use Luminova\Security\Rule;
     *
     * $input->setRules([
     *     'email' => [
     *         Rule::required(),
     *         Rule::email(),
     *     ],
     *     'name' => [
     *         Rule::required(),
     *     ],
     * ]);
     * ```
     */
    public function setRules(array $rules): self;

    /**
     * Set custom validation error messages.
     *
     * Messages are defined per field and per rule. When validation fails,
     * the message for the matching field/rule pair is used. If no custom
     * message is defined, a default message is returned.
     *
     * Supported placeholders:
     * - {field} → Field name
     * - {rule}  → Rule name
     * - {value} → Field value at time of validation
     *
     * @param array<string, array<string, string>> $messages Custom messages mapped by field and rule.
     *
     * @return static Return instance of validation object.
     *
     * @example - Example:
     * ```php
     * $input->setMessages([
     *     'email' => [
     *         'required' => 'Email is required.',
     *         'email'    => 'Invalid email address: {value}',
     *     ],
     *     'name' => [
     *         'required' => 'Name is required.',
     *     ],
     * ]);
     * ```
     */
    public function setMessages(array $messages): self;

    /**
     * Add a field with its validation rules and optional custom messages.
     *
     * This is a convenience method that combines {@see setRules()} and
     * {@see setMessages()} for a single field.
     *
     * Rules can be provided as:
     * - A pipe-separated string (e.g. "required|string|email")
     * - An array of rule definitions
     *
     * Messages are defined per rule using the rule name as the key.
     * If a message is not provided, the default message for the rule is used.
     *
     * @param string $field Field name (e.g. "name", "email").
     * @param string|array<int,string|array> $rules Validation rules.
     * @param array<string,string> $messages Optional custom messages per rule.
     *
     * @return static Return instance of the Validation class.
     *
     * @see \Luminova\Security\Rule
     *
     * @example - Example:
     * ```php
     * use Luminova\Security\Rule;
     *
     * $input->addField(
     *     'email',
     *     [Rule::required(), Rule::email()],
     *     [
     *         'required' => 'Email is required.',
     *         'email'    => 'Invalid email address.',
     *     ]
     * );
     * ```
     */
    public function addField(string $field, array|string $rules, array $messages = []): self;

    /**
     * Get a validation error message.
     *
     * Retrieves an error message by field name or by index. If no field is specified,
     * the first field with an error is used. If the field has multiple errors, the
     * message is selected by its index.
     *
     * @param string|int $field Field name or field index (default: 0 = first field with error).
     * @param int $error Error index within the field (default: 0 = first error).
     *
     * @return string The error message, or an empty string if not found.
     *
     * @example - Get first error (default)
     * ```php
     * $input->getError();
     * ```
     *
     * @example - Get first error for a specific field
     * ```php
     * $input->getError('email');
     * ```
     *
     * @example Get second error for a field
     * ```php
     * $input->getError('email', 1);
     * ```
     */
    public function getError(string|int $field = 0, int $error = 0): string;

    /**
     * Get validation errors for a field by name or index.
     *
     * If an integer is provided, the field is resolved by its position in the
     * error list. If a string is provided, it is treated as the field name.
     *
     * Once retrieved, the field’s errors are removed from the internal failure list.
     *
     * @param string|int $field Field name or index (default: 0 = first field with errors).
     *
     * @return array<int,array{message:string,rule:string,field:string}> List of error entries for the field.
     *
     * @example - Get first field errors:
     * ```php
     * $errors = $input->getFields();
     * ```
     *
     * @example - Get errors by field name:
     * ```php
     * $errors = $input->getFields('email');
     * ```
     */
    public function getFields(string|int $field = 0): array;

    /**
     * Get the name of a field with a validation error.
     *
     * If an integer is provided, the field is resolved by its position in the
     * error list. If a string is provided, it is treated as the field name.
     *
     * @param string|int $field Field name or index (default: 0 = first field with error).
     *
     * @return string Field name that has a validation error, or an empty string if not found.
     */
    public function getField(string|int $field = 0): string;

    /**
     * Validate a username against format, length, and reserved constraints.
     *
     * This method performs a strict validation check to ensure a username follows
     * acceptable system rules. It returns early on the first failure encountered.
     *
     * Validation behavior:
     * - Enforces length between 3 and 64 characters (UTF-8 safe).
     * - Allows only letters (any language), numbers, underscore (_), hyphen (-), and dot (.).
     * - Rejects any whitespace characters.
     * - Controls uppercase usage:
     *   - If $allowUppercase is false, any uppercase letter will fail validation.
     *   - If $allowUppercase is true, usernames cannot be entirely uppercase.
     * - Prevents use of reserved usernames (case-insensitive match).
     * - Applies pattern-based restrictions:
     *   - Cannot be all numbers.
     *   - Cannot start or end with dot (.) or hyphen (-).
     *   - Cannot contain consecutive dots (..), hyphens (--), or underscores (__).
     *
     * @param string $username The username to validate.
     * @param bool $allowUppercase Whether uppercase letters are allowed (default: true).
     * @param string[] $reservedUsernames List of reserved usernames (case-insensitive) (e.g. ['root', 'admin', 'system']).
     *
     * @return array{0: bool, 1: ?string} Validation result containing:
     *               - (bool) Whether the username is valid
     *               - (string|null) Error message if invalid
     *
     * @example - Example:
     * ```php
     * [$valid, $error] = Validation::isUsername('admin', true, ['admin']);
     * ```
     */
    public static function isUsername(
        string $username,
        bool $allowUppercase = true,
        array $reservedUsernames = []
    ): array;
}