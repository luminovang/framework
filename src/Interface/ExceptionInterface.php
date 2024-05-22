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

use \Throwable;

interface ExceptionInterface extends Throwable
{
    /***
     * Constructor for BaseException.
     *
     * @param string message   The exception message (default: 'Database error').
     * @param int $code  The exception code (default: 500).
     * @param Throwable $previous  The previous exception if applicable (default: null).
    */
    public function __construct(string $message, int $code = 0, Throwable $previous = null);

    /**
     * Get a string representation of the exception.
     *
     * @return string A formatted error message.
    */
    public function __toString(): string;

    /**
     * Logs an exception message
     *
     * @param string $level Exception log level.
     * 
     * @return void
    */
    public function log(string $level = 'exception'): void;


    /**
     * Handle exception gracefully based on environment and exception error code.
     * 
     * @throws self Throws an exception if on development or fatal, otherwise log the exception.
    */
    public function handle(): void;

    /**
     * Create and handle a exception gracefully.
     *
     * @param string $message he exception message.
     * @param int $code The exception code (default: 500).
     * @param Throwable $previous  The previous exception if applicable (default: null).
     * 
     * @return void 
     * @throws static Exception
    */
    public static function throwException(string $message, int $code = 0, Throwable $previous = null): void;
}