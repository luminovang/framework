<?php
/**
 * Luminova Framework Exception interface.
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
    /**
     * Constructor for BaseException.
     *
     * @param string message  The exception error message.
     * @param int $code  The exception code (default: 0).
     * @param Throwable|null $previous  The previous exception if applicable (default: null).
    */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null);

    /**
     * Get the exception code string.
     *
     * @return string|null Return exception code string, otherwise null if not string exception code.
    */
    public function getCodeString(): ?string;

    /**
     * Set an exception code string.
     *
     * @param string $code The exception string code.
     * 
     * @return self Return exception class instance.
    */
    public function setCodeString(string $code): self;

    /**
     * Get a string representation of the exception.
     *
     * @return string A formatted error message.
    */
    public function __toString(): string;

    /**
     * Logs an exception message to file.
     *
     * @param string $level The log level to use (default: `exception`).
     * 
     * @return void
    */
    public function log(string $level = 'exception'): void;
    
    /**
     * Handle exception gracefully based on environment and exception error code.
     * 
     * @return void 
     * @throws self Throws an exception if on development or fatal, otherwise log the exception.
    */
    public function handle(): void;

    /**
     * Create and handle a exception gracefully.
     *
     * @param string $message The exception message.
     * @param int|string $code The exception code (default: 0).
     * @param Throwable|null $previous  The previous exception if applicable (default: null).
     * 
     * @return void 
     * @throws static Throws the exception from the called class. 
    */
    public static function throwException(string $message, int|string $code = 0, ?Throwable $previous = null): void;
}