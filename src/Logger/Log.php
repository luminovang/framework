<?php 
/**
 * Luminova Framework static logger helper class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Logger;

use \Luminova\Logger\LogLevel;
use \Luminova\Logger\Logger;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\InvalidArgumentException;

final class Log
{
    /**
     * Static logger helper.
     *
     * @param string $method The log level as method name to call (e.g., `Log::error(...)`, `Log::info(...)`).
     * @param array{0:string,1:array} $arguments Argument holding the log message and optional context.
     *
     * @return void
     * @throws InvalidArgumentException If an invalid logger method-level is called.
     * @throws RuntimeException If logger does not implement PSR LoggerInterface.
     */
    public static function __callStatic(string $method, array $arguments)
    {
        Logger::getLogger()->log($method, ...$arguments);
    }

    /**
     * Log an emergency message.
     *
     * @param string $message The emergency message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function emergency(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Log an alert message.
     *
     * @param string $message The alert message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function alert(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::ALERT, $message, $context);
    }

    /**
     * Log a critical message.
     *
     * @param string $message The critical message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function critical(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message The error message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function error(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::ERROR, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message The warning message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function warning(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log a notice message.
     *
     * @param string $message The notice message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function notice(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message The info message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function info(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message The debug message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function debug(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log an exception message.
     *
     * @param string $message The EXCEPTION message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function exception(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::EXCEPTION, $message, $context);
    }

    /**
     * Log an php message.
     *
     * @param string $message The php message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function php(string $message, array $context = []): void
    {
        Logger::dispatch(LogLevel::PHP, $message, $context);
    }
}