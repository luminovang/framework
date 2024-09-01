<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Functions;

use \Luminova\Exceptions\InvalidArgumentException;

class Normalizer
{
    /**
     * Normalizes an array of headers by validating and optionally grouping them by their normalized names.
     *
     * This method processes the provided headers, ensuring that each header name is valid
     * and its corresponding value is normalized. If the `$withNames` parameter is true, 
     * it will return an associative array where headers are stored with their normalized 
     * names as keys, allowing for case-insensitive grouping of headers.
     *
     * @param array $headers The array of headers to normalize, where keys are header names
     *                       and values are their corresponding values.
     * @param bool $withNames Whether to group headers by their normalized names (default: false).
     *
     * @return array An associative array of normalized headers, or an array containing both
     *               normalized headers and their original names if `$withNames` is true.
     *
     * @throws InvalidArgumentException if any header name is invalid.
     */
    public static function normalizeHeaders(array $headers, bool $withNames = false): array
    {
        $headerNames = [];
        $defaultHeaders = [];

        foreach ($headers as $header => $value) {
            // Convert the header to a string to avoid issues with numeric array keys.
            $header = (string) $header;

            // Validate the header name.
            self::assertHeader($header);
            
            // Normalize the header value.
            $value = self::normalizeHeaderValue($value);
            
            if ($withNames) {
                $normalizedHeader = strtolower($header);
                if (isset($headerNames[$normalizedHeader])) {
                    // Merge values for headers with the same normalized name.
                    $header = $headerNames[$normalizedHeader];
                    $defaultHeaders[$header] = array_merge($defaultHeaders[$header], $value);
                } else {
                    // Store the original header name with its normalized form.
                    $headerNames[$normalizedHeader] = $header;
                    $defaultHeaders[$header] = $value;
                }
            } else {
                $defaultHeaders[$header] = $value;
            }
        }

        return $withNames ? ['headers' => $defaultHeaders, 'headerNames' => $headerNames] : $defaultHeaders;
    }

    /**
     * Normalizes a string of HTTP headers into an associative array.
     *
     * This method takes a raw header string, splits it into individual lines, 
     * and organizes the headers into a key-value array. If the header key 
     * appears multiple times, it can either store the values in an array or 
     * as a single string, based on the $arrayValue parameter.
     *
     * @param string $header The raw HTTP header string.
     * @param bool $lowercase Indicate whether to use a lowercased header names (default: false).
     * @param bool $arrayValue Determines whether to store multiple values for the same header key in an array (default: true).
     * 
     * @return array An associative array of normalized headers.
     * 
     * @throws InvalidArgumentException If the header format is invalid.
     */
    public static function normalizeStringHeaders(
        string $header, 
        bool $lowercase = false,
        bool $arrayValue = true
    ): array
    {
        $headers = [];
        $status = $lowercase ? 
            'x-response-protocol-status-phrase' : 
            'X-Response-Protocol-Status-Phrase';

        // Split the header string into individual lines
        foreach (explode("\r\n", $header) as $i => $line) {
            $line = trim($line);

            // Check for empty lines and skip them
            if ($line === '') {
                continue;
            }

            if ($i === 0) {
                // Treat the first line as the Status header
                $headers[$status] = $arrayValue ? [$line] : $line;
                continue;
            }

            // Split the line into key and value if it contains a colon
            if (str_contains($line, ': ')) {
                [$key, $value] = explode(': ', $line, 2);
                $key = trim($key);

                // Validate the header name.
                self::assertHeader($key);

                $value = trim($value);
                self::assertValue($value);
                $key = $lowercase ? strtolower($key) : $key;

                // Add value to the headers array
                if ($arrayValue) {
                    // Initialize the array if the key does not exist
                    $headers[$key] = ($headers[$key] ?? []);
                    $headers[$key][] = $value;
                } else {
                    $headers[$key] = $value;
                }
            }
        }

        return $headers;
    }

    /**
     * Normalizes the given header value(s) into an array of trimmed strings.
     *
     * @param mixed $value The header value, which can be a single value or an array of values.
     *
     * @return string[] An array of trimmed and validated header values.
     *
     * @throws InvalidArgumentException if the value is an empty array.
     */
    public static function normalizeHeaderValue($value): array
    {
        if (!is_array($value)) {
            return self::trimAndValidateHeaderValues([$value]);
        }

        if ($value === []) {
            throw new InvalidArgumentException('Header value cannot be an empty array.');
        }

        return self::trimAndValidateHeaderValues($value);
    }

    /**
     * Trims whitespace from each header value and validates them.
     *
     * Spaces and tabs should be excluded by parsers when extracting the field value from a header.
     * Reference: https://datatracker.ietf.org/doc/html/rfc7230#section-3.2.4
     *
     * @param mixed[] $values The header values to trim and validate.
     *
     * @return string[] An array of trimmed header values.
     *
     * @throws InvalidArgumentException if any value is non-scalar or null.
     */
    public static function trimAndValidateHeaderValues(array $values): array
    {
        return array_map(function ($value) {
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException(sprintf(
                    'Header value must be scalar or null; %s provided.',
                    is_object($value) ? get_class($value) : gettype($value)
                ));
            }

            $trimmed = trim((string) $value, " \t");
            self::assertValue($trimmed);

            return $trimmed;
        }, array_values($values));
    }

    /**
     * Validates that the provided header name is a valid string.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2
     *
     * @param mixed $header The header name to validate.
     *
     * @throws InvalidArgumentException if the header name is not a string or is invalid.
     */
    public static function assertHeader(mixed $header): void
    {
        if ($header === '' || !is_string($header)) {
            throw new InvalidArgumentException(sprintf(
                'Header name must be a string; %s provided.',
                is_object($header) ? get_class($header) : gettype($header)
            ));
        }

        if (!preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/D', $header)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid header name.', $header));
        }
    }

    /**
     * Validates that the provided field value adheres to the expected format.
     *
     * The regular expression intentionally does not support line folding (obs-fold),
     * as clients must not send requests with line folding.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2
     *
     * @param string $value The field value to validate.
     *
     * @throws InvalidArgumentException if the value is invalid.
     */
    public static function assertValue(string $value): void
    {
        if (!preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/D', $value)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid header value.', $value));
        }
    }
}