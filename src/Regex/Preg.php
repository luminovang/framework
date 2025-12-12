<?php
declare(strict_types=1);
/**
 * Luminova Framework pipeline value transformation
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Regex;

use \Luminova\Exceptions\BadMethodCallException;

class Preg 
{
    public const DEFAULT_CAPTURE = 0;
    public const OFFSET_CAPTURE = PREG_OFFSET_CAPTURE;
    public const UNMATCHED_AS_NULL = PREG_UNMATCHED_AS_NULL;
    public const SET_ORDER = PREG_SET_ORDER;
    public const PATTERN_ORDER = PREG_PATTERN_ORDER;
    public const SPLIT_DELIM_CAPTURE = PREG_SPLIT_DELIM_CAPTURE;
    public const SPLIT_NO_EMPTY = PREG_SPLIT_NO_EMPTY;
    public const SPLIT_OFFSET_CAPTURE = PREG_SPLIT_OFFSET_CAPTURE;

    public const ERROR_NONE = PREG_NO_ERROR;
    public const ERROR_INTERNAL = PREG_INTERNAL_ERROR;
    public const ERROR_BACKTRACK_LIMIT = PREG_BACKTRACK_LIMIT_ERROR;
    public const ERROR_RECURSION_LIMIT = PREG_RECURSION_LIMIT_ERROR;
    public const ERROR_BAD_UTF8 = PREG_BAD_UTF8_ERROR;
    public const ERROR_BAD_UTF8_OFFSET = PREG_BAD_UTF8_OFFSET_ERROR;

    private const ERRORS = [
        PREG_INTERNAL_ERROR => 'Internal PCRE error%s',
        PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit exhausted%s',
        PREG_RECURSION_LIMIT_ERROR => 'Recursion limit exhausted%s',
        PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data%s',
        PREG_BAD_UTF8_OFFSET_ERROR => 'Offset did not correspond to the beginning of a valid UTF-8 code point%s',
    ];

    private static array $result = [
        'pattern' => null,
        'matches' => [],
        'count' => 0
    ];

    /**
     * Constructor to initialize the Preg instance with a regex pattern.
     * 
     * @param array|string $pattern The regex pattern(s) to be used for matching operations.
     * 
     * @example Example:
     * ```php
     * $preg = new Preg('/\d+/');
     * 
     * if($preg->isMatch('There are 3 cats')) {
     *     $matches = $preg->getMatches();
     *     $count = $preg->getCount();
     *     // $matches will contain the matched numbers and $count will be the number of matches found.
     * }
     * ```
     */
    public function __construct(private array|string $pattern) 
    {
        self::reset();
    }

    /**
     * Magic method to handle dynamic method calls for regex operations.
     * 
     * @param string $method The name of the method being called.
     * @param array $args The arguments passed to the method.
     * 
     * @return mixed Returns the result of the called method if it exists, otherwise throws an exception.
     * 
     * @throws BadMethodCallException If the called method does not exist in the Preg class.
     */
    public function __call(string $method, array $args): mixed 
    {
        if(!method_exists(self::class, $method)) {
            throw new BadMethodCallException("Method $method does not exist.");
        }

        return self::{$method}($this->pattern, ...$args);
    }

    /**
     * Get the matches from the last regex operation.
     * 
     * @return array Returns an array of matches or an empty array if no matches found 
     *      or if no regex operation performed yet.
     */
    public function getMatches(): array 
    {
        return self::$result['matches'] ?? [];
    }

    /**
     * Get the count of matches from the last regex operation.
     * 
     * @return int Returns the number of matches found or 0 if no matches found 
     *      or if no regex operation performed yet.
     */
    public function getCount(): int 
    {
        return self::$result['count'] ?? 0;
    }

    /**
     * Get the pattern used in the last regex operation.
     * 
     * @return array|string|null Returns the pattern used or null if no regex operation performed yet.
     */
    public function getPattern(): array|string|null
    {
        return $this->pattern ?? self::$result['pattern'] ?? null;
    }

    /**
     * Determine whether the subject matches the pattern.
     * 
     * @param string $pattern The regex pattern to match.
     * @param string $subject The string to test against the pattern.
     * 
     * @return bool Returns true if the pattern matches the subject, false otherwise.
     */
    public static function isMatch(string $pattern, string $subject): bool 
    {
        return self::match($pattern, $subject);
    }

    /**
     * Determine whether a valid regex pattern.
     * 
     * @param string $pattern The regex pattern to validate.
     * 
     * @return bool Returns true if the pattern is valid, false otherwise.
     */
    public static function isPattern(string $pattern): bool 
    {
        self::reset();

        if($pattern === '') {
            return false;
        }

        self::result('pattern', $pattern);
        return preg_match($pattern, '') !== false;
    }

    /**
     * Perform a regex match operation and capture results.
     * 
     * @param string $pattern The regex pattern to match.
     * @param string $subject The string to test against the pattern.
     * @param array|null $matches Optional variable to store matches.
     * @param int $flags Optional flags for matching behavior.
     * @param int $offset Optional offset in the subject string to start matching.
     * @param int &$count Variable to store the count of matches found.
     * 
     * @return bool Returns true if a match is found, false otherwise.
     */
    public static function match(
        string $pattern, 
        string $subject, 
        ?array &$matches = null,
        int $flags = self::DEFAULT_CAPTURE,
        int $offset = 0,
        int &$count = 0
    ): bool 
    {
        self::reset();
        $count = 0;

        if($pattern === '' || $subject === '') {
            return false;
        }

        $result = preg_match($pattern, $subject, $matches, $flags, $offset);

        self::result('pattern', $pattern);

        if($result === false) {
            return false;
        }
        
        self::result('matches', $matches);
        self::result('count', $result);
        $count = $result;
        
        return $result === 1;
    }

    /**
     * Perform global regex matching.
     * 
     * This method perform a regex match operation for all occurrences and capture results.
     * 
     * @param string $pattern The regex pattern to match.
     * @param string $subject The string to test against the pattern.
     * @param array|null $matches Optional variable to store matches.
     * @param int $flags Optional flags for matching behavior.
     * @param int $offset Optional offset in the subject string to start matching.
     * @param int &$count Variable to store the count of matches found.
     * 
     * @return bool Returns true if matches are found, false otherwise.
     */
    public static function matchAll(
        string $pattern, 
        string $subject, 
        ?array &$matches = null,
        int $flags = self::DEFAULT_CAPTURE,
        int $offset = 0,
        int &$count = 0
    ): bool 
    {
        self::reset();
        $count = 0;

        if($pattern === '' || $subject === '') {
            return false;
        }

        $result = preg_match_all($pattern, $subject, $matches, $flags, $offset);
        self::result('pattern', $pattern);

        if($result === false){
            return false;
        }

        self::result('matches', $matches);
        self::result('count', $result);
        $count = $result;

        return $result > 0;
    }

    /**
     * Replace matches using a regex pattern.
     * 
     * @param array|string $pattern The regex pattern(s) to search for.
     * @param array|string $replacement The replacement string(s).
     * @param array|string $subject The string or array of strings to perform the replacement on.
     * @param int $limit The maximum number of replacements to perform. Default is -1 (no limit).
     * @param int &$count Variable to store the count of replacements performed.
     * 
     * @return array|string|null Returns the modified string or array of strings after replacements.
     */
    public static function replace(
        array|string $pattern, 
        array|string $replacement, 
        array|string $subject,
        int $limit = -1,
        int &$count = 0
    ): array|string|null
    {
        self::reset();
        $count = 0;

        if(($pattern === '' || $subject === '') || ($pattern === [] || $subject === [])) {
            return null;
        }
    
        $result = preg_replace($pattern, $replacement, $subject, $limit, $count);
        
        self::result('pattern', $pattern);
        self::result('count', $count);
    
        if($result === null) {
            return null;
        }

        return $result;
    }

    /**
     * Perform a regex replacement operation using a callback function.
     * 
     * @param array|string $pattern The regex pattern(s) to search for.
     * @param callable $callback The callback function to generate the replacement string.
     * @param array|string $subject The string or array of strings to perform the replacement on.
     * @param int $limit The maximum number of replacements to perform. Default is -1 (no limit).
     * @param int &$count Variable to store the count of replacements performed.
     * @param int $flags Optional flags for replacement behavior.
     * 
     * @return array|string|null Returns the modified string or array of strings after replacements.
     */
    public static function replaceCallback(
        array|string $pattern, 
        callable $callback, 
        array|string $subject,
        int $limit = -1,
        int &$count = 0,
        int $flags = self::DEFAULT_CAPTURE
    ): array|string|null
    {
        self::reset();
        $count = 0;

        if(($pattern === '' || $subject === '') || ($pattern === [] || $subject === [])) {
            return null;
        }

        $result = preg_replace_callback($pattern, $callback, $subject, $limit, $count, $flags);

        self::result('pattern', $pattern);
        self::result('count', $count);

        if($result === null) {
            return null;
        }

        return $result;
    }

    /**
     * Perform a regex split operation.
     * 
     * @param string $pattern The regex pattern to split by.
     * @param string $subject The string to split.
     * @param int $limit The maximum number of splits to perform. Default is -1 (no limit).
     * @param int $flags Optional flags for splitting behavior.
     * 
     * @return array Returns an array of strings obtained by splitting the subject.
     */
    public static function split(string $pattern, string $subject, int $limit = -1, int $flags = 0): array 
    {
        self::reset();

        if($pattern === '' || $subject === '') {
            return [$subject];
        }

        $result = preg_split($pattern, $subject, $limit, $flags);

        if($result === false){
            return [];
        }

        self::result('pattern', $pattern);
        return $result;
    }

    /**
     * Quote regular expression characters in a string.
     * 
     * @param string $str The input string to quote.
     * @param string|null $delimiter Optional regex delimiter to also escape. Default is null (no additional escaping).
     * 
     * @return string Returns the quoted string with regex special characters escaped.
     */
    public static function quote(string $str, ?string $delimiter = null): string 
    {
        self::reset();

        if($str === '') {
            return '';
        }
        
        self::result('pattern', $delimiter ?? '');
        return preg_quote($str, $delimiter);
    }

    /**
     * Check if the last regex operation resulted in an error.
     * 
     * @return bool Returns true if there was an error, false otherwise.
     */
    public static function hasError(): bool 
    {
        return self::ERROR_NONE !== self::getErrorCode();
    }

    /**
     * Check if the given error code matches the last regex error code.
     * 
     * @param int $code The error code to check against the last regex error code.
     * 
     * @return bool Returns true if the given code matches the last regex error code, false otherwise.
     */
    public static function isErrorCode(int $code): bool 
    {
        if (
            defined('PREG_JIT_STACKLIMIT_ERROR') 
            && $code === PREG_JIT_STACKLIMIT_ERROR
        ) {
            return true;
        }

        return $code === self::getErrorCode();
    }

    /**
     * Get the error code from the last regex operation.
     * 
     * @return int Returns the error code of the last regex operation. Returns PREG_NO_ERROR (0) if no error occurred.
     */
    public static function getErrorCode(): int 
    {
        return preg_last_error();
    }

    /**
     * Get a human-readable error message for the last regex error.
     * 
     * @return string|null Returns a string describing the last regex error, or null if no error occurred.
     */
    public static function getErrorMessage(): ?string 
    {
        $code = self::getErrorCode();

        if ($code === self::ERROR_NONE) {
            return null;
        }

        $pattern = self::$result['pattern'] ?? '';

        if($pattern){
            $pattern = ' Pattern: (' . (is_array($pattern) ? implode(', ', $pattern) : $pattern) . ')';
        }

        if (
            defined('PREG_JIT_STACKLIMIT_ERROR') &&
            $code === PREG_JIT_STACKLIMIT_ERROR
        ) {
            return sprintf('JIT stack limit exhausted%s', $pattern);
        }

        return sprintf(
            self::ERRORS[$code] ?? 'Unknown PCRE error%s', 
            $pattern
        );
    }

    /**
     * Store the result of a regex operation in the static result array.
     * 
     * @param string $ctx The context key to store the value under (e.g., 'pattern', 'matches', 'count').
     * @param mixed $value The value to store in the result array for the given context.
     * 
     * @return void
     */
    private static function result(string $ctx, mixed $value): void 
    {
        self::$result[$ctx] = $value;
    }

    /**
     * Reset the static result array to its initial state.
     * 
     * @return void
     */
    private static function reset(): void
    {
        self::$result = [
            'pattern' => null,
            'matches' => [],
            'count'   => 0
        ];
    }
}