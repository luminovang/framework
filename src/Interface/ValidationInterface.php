<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */

namespace Luminova\Interface;

/**
 * Interface for validating input data against defined rules.
 */
interface ValidationInterface 
{
  /**
   * Validate input data against the defined rules.
   * 
   * @param array<string, mixed> $input Input data to validate.
   * @param array<int, mixed> $rules Optional array of rules to override default rules.
   * 
   * @return bool Returns true if all rules are passed, otherwise false.
   */
  public function validate(array $input, array $rules = []): bool;

  /**
   * Get validation error messages.
   * 
   * @return array Validation error messages.
   */
  public function getErrors(): array;

  /**
   * Get the error message for a specific field.
   * 
   * @param string $field Name of the field.
   * 
   * @return string Error message for the specified field.
   */
  public function getError(string $field): string;

  /**
   * Get the field causing the validation error.
   * 
   * @param string $field Name of the field.
   * 
   * @return string Field causing the validation error.
   */
  public function getErrorField(string $field): string;

  /**
   * Set validation rules with optional error messages.
   * 
   * @param array<int, string> $rules Validation rules.
   * @param array<string, mixed> $messages Optional error messages for validation rules.
   * 
   * @return self Instance of the Validation.
   */
  public function setRules(array $rules, array $messages = []): self;

  /**
   * Add a single rule with an optional error message.
   * 
   * @param string $field Field name.
   * @param string $rules Validation rules.
   * @param array<string, string> $messages Optional error message for the validation rule.
   * 
   * @return self Instance of the Validation.
   */
  public function addRule(string $field, string $rules, array $messages = []): self;

  /**
   * Get the error message at the specified field and error indexes.
   * 
   * @param int $indexField Field index.
   * @param int $indexErrors Error index.
   * 
   * @return string Error message.
   */
  public function getErrorLine(int $indexField = 0, int $indexErrors = 0): string;

  /**
   * Get information about the validation error at the specified field index.
   * 
   * @param int $fieldIndex Field index.
   * 
   * @return array Information about the validation error.
   */
  public function getErrorLineInfo(int $fieldIndex = 0): array;

  /**
   * Get the field causing the current validation error.
   * 
   * @param string $prefix Prefix to prepend to the error field.
   * 
   * @return string Field causing the validation error.
   */
  public function getErrorFieldLine(string $prefix = ''): string;

  /**
   * Check if validation has passed.
   * 
   * @return bool True if all rules passed, false otherwise.
   */
  public function isPassed(): bool;
}
