<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Logger;

use Luminova\Exceptions\InvalidArgumentException;

/**
 * Defines various logging levels used for categorizing log messages.
 */
final class LogLevel
{
    /**
     * Emergency level: System is unusable.
     * 
     * @var string EMERGENCY
     */
    public const EMERGENCY = 'emergency';

    /**
     * Alert level: Action must be taken immediately.
     * 
     * @var string ALERT
     */
    public const ALERT     = 'alert';

    /**
     * Critical level: Critical conditions, such as an application or service being down.
     * 
     * @var string CRITICAL
     */
    public const CRITICAL  = 'critical';

    /**
     * Error level: Runtime errors that require attention.
     * 
     * @var string ERROR
     */
    public const ERROR     = 'error';

    /**
     * Warning level: Warnings about potential issues that are not immediately problematic but may require attention.
     * 
     * @var string WARNING
     */
    public const WARNING   = 'warning';

    /**
     * Notice level: Normal but significant conditions that require attention.
     * 
     * @var string NOTICE
     */
    public const NOTICE    = 'notice';

    /**
     * Info level: Informational messages that highlight the progress of the application at a coarse-grained level.
     * 
     * @var string INFO
     */
    public const INFO      = 'info';

    /**
     * Debug level: Detailed information used for debugging and diagnosing issues.
     * 
     * @var string DEBUG
     */
    public const DEBUG     = 'debug';

    /**
     * Exception level: Exception messages or errors, useful for handling uncaught exceptions or error scenarios.
     * 
     * @var string EXCEPTION
     */
    public const EXCEPTION = 'exception';

    /**
     * PHP level: PHP errors, including parse errors, runtime errors, and warnings.
     * 
     * @var string PHP
     */
    public const PHP       = 'php';

    /**
     * PHP level: Performance metrics, specifically for api or production level.
     * 
     * @var string METRICS
     */
    public const METRICS       = 'metrics';

    /**
     * List of all valid log levels.
     * 
     * @var array<string,string>
     */
    public const LEVELS = [
        'emergency'     => self::EMERGENCY,
        'alert'         => self::ALERT,
        'critical'      => self::CRITICAL,
        'error'         => self::ERROR,
        'warning'       => self::WARNING,
        'notice'        => self::NOTICE,
        'info'          => self::INFO,
        'debug'         => self::DEBUG,
        'exception'     => self::EXCEPTION,
        'php_error'     => self::PHP,
        'php'           => self::PHP,
        'metrics'       => self::METRICS,
    ];

    /**
     * Maps RFC 5424 numeric levels to corresponding string log levels.
     *
     * @var array<int,string> RFC_5424_LEVELS
     */
    private const RFC_5424_LEVELS = [
        7 => self::DEBUG,
        6 => self::INFO,
        5 => self::NOTICE,
        4 => self::WARNING,
        3 => self::ERROR,
        2 => self::CRITICAL,
        1 => self::ALERT,
        0 => self::EMERGENCY,
    ];

    /**
     * List of critical log levels.
     * 
     * @var array<string,true> CRITICAL_LEVELS
     */
    private const CRITICAL_LEVELS = [
        self::EMERGENCY  => true,
        self::ALERT      => true,
        self::EXCEPTION  => true,
        self::CRITICAL   => true,
    ];

    /**
     * Checks if the given log level is valid.
     *
     * Supports both PSR-style string levels (e.g., 'error', 'info') 
     * and RFC 5424 numeric levels (0–7).
     *
     * @param string|int $level The log level to validate.
     * 
     * @return bool Returns true if the level exists, false otherwise.
     */
    public static function has(string|int $level): bool
    {
        return isset(self::LEVELS[$level]) 
            || isset(self::RFC_5424_LEVELS[$level]);
    }

    /**
     * Resolves the canonical value for a given log level.
     *
     * Maps a PSR-style string level or RFC 5424 numeric level to its internal representation.
     *
     * @param string|int $level The log level to parse.
     * 
     * @return string|null Return the canonical log level, or null if invalid.
     */
    public static function resolve(string|int $level): ?string
    {
        return self::LEVELS[$level] 
            ?? self::RFC_5424_LEVELS[$level] 
            ?? null;
    }

    /**
     * Asserts that the given log level is valid.
     *
     * Supports predefined levels or custom levels.
     * Custom levels must:
     * - Be 64 characters long
     * - Start with a letter
     * - Contain at least one digit
     * - Match pattern: letters followed by alphanumeric ending in digits
     *
     * Examples:
     *  - valid: foo8383, a1, log123
     *  - invalid: 2883, 737foo, foo (no number), foo_123 (invalid char)
     *
     * @param string|int $level
     * @param string|null $function
     * @param bool $allowCustom
     *
     * @throws InvalidArgumentException If the provided log level is not valid.
     */
    public static function assert(
        string|int $level,
        ?string $function = null,
        bool $allowCustom = false
    ): void 
    {
        if (self::has($level)) {
            return;
        }

        if ($allowCustom && self::isValid($level)) {
           return;
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid log level "%s" in %s. Allowed: %s | custom: %s',
            $level,
            $function ? "\"$function\"(...)" : 'the given context',
            implode(', ', self::LEVELS),
            '[a-zA-Z][a-zA-Z0-9] length: [1-64]'
        ));
    }

    /**
     * Determine whether a log level identifier is valid.
     *
     * A valid log level may be one of the predefined RFC 5424 numeric levels or
     * a custom level name that starts with a letter and contains only letters,
     * numbers, underscores (`_`), or hyphens (`-`). Custom level names must be
     * between 1 and 64 characters in length.
     *
     * @param string|int $level The log level name or RFC 5424 numeric level.
     *
     * @return bool Returns `true` if the log level is valid; otherwise, `false`.
     */
    public static function isValid(string|int $level): bool 
    {
        if(is_int($level) && isset(self::RFC_5424_LEVELS[$level])){
            return true;
        }

        $level = (string) $level;
        $len = strlen($level);

        if (
            $len >= 1 &&
            $len <= 64 &&
            preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $level)
        ) {
            return true;
        }

        return false;
    }

    /**
     * Checks if a given log level is considered critical.
     *
     * Critical levels represent severe issues that require 
     * immediate attention, such as system failures or 
     * security breaches.
     *
     * The following log levels are considered critical:
     * - `emergency`
     * - `alert`
     * - `critical`
     * - `exception`
     *
     * @param string|int $level The log level to check.
     * 
     * @return bool Returns true if the level is critical, otherwise false.
     */
    public static function isCritical(string|int $level): bool
    {
        $level = self::resolve($level);

        if($level === null){
            return false;
        }

        return isset(self::CRITICAL_LEVELS[$level]);
    }
}