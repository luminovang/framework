<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Logger;

use \Luminova\Exceptions\InvalidArgumentException;

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
    public const PHP       = 'php_error';

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
     * List of critical log levels.
     * 
     * @var array<string,true> $critical
     */
    private static array $critical = [
        self::EMERGENCY => true,
        self::ALERT     => true,
        self::EXCEPTION  => true,
        self::CRITICAL  => true,
    ];

    /**
     * Checks if the specified log level is valid and exists.
     *
     * @param string $level The log level to check (e.g., 'error', 'info', 'debug').
     * 
     * @return bool Return true if the log level exists, false otherwise.
     */
    public static function has(string $level): bool
    {
        return isset(self::LEVELS[$level]);
    }

    /**
     * Asserts that the given log level is valid.
     *
     * This function checks if the provided log level exists in the predefined set of log levels.
     * If the level is invalid, it throws an InvalidArgumentException with a detailed error message.
     *
     * @param string $level The log level to validate.
     * @param string|null $function Optional. The name of the calling function for context in the error message.
     *
     * @return void
     * @throws InvalidArgumentException If the provided log level is not valid.
     */
    public static function assert(string $level, ?string $function = null): void
    {
        if (!isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException(sprintf(
                'Invalid log level "%s" in %s. Supported levels: %s. See https://luminova.ng/docs/0.0.0/logging/levels',
                $level,
                $function ? "\"$function\"(...)" : 'the given context',
                implode(', ', self::LEVELS)
            ));
        }
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
     * @param string $level The log level to check.
     * 
     * @return bool Returns true if the level is critical, otherwise false.
     */
    public static function isCritical(string $level): bool
    {
        return isset(self::$critical[$level]);
    }
}