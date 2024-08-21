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

interface ExceptionInterface
{
    /**
     * Constructor for the exception.
     *
     * @param string $message The exception error message.
     * @param string|int $code The exception code (default: 0).
     * @param Throwable|null $previous The previous exception, if available (default: null).
     */
    public function __construct(string $message, string|int $code = 0, ?Throwable $previous = null);

    /**
     * Set the exception code as a string.
     *
     * @param string|int $code The string representation of the exception code.
     * 
     * @return self Returns the instance of the exception.
     */
    public function setCode(string|int $code): self;

    /**
     * Sets the file where the error occurred.
     * 
     * @param string $file The file where the error occurred.
     * 
     * @return self Returns the instance of the exception.
     */
    public function setFile(string $file): self;

    /**
     * Sets the line number where the error occurred.
     * 
     * @param int $line The line number where the error occurred.
     * 
     * @return self Returns the instance of the exception.
     */
    public function setLine(int $line): self;

    /**
     * Gets filtered error message without the file.
     * 
     * @return string Return the filtered error message.
     */
    public function getFilteredMessage(): string;

    /**
     * Get debug trace.
     * If no custom trace was set, then the default trace is returned.
     * 
     * @return array Returns the debug tracer for the exception thrown.
     */
    public function getDebugTrace(): array;

    /**
     * Get a string representation of the exception.
     *
     * @return string A formatted error message that represents the exception.
     */
    public function __toString(): string;

    /**
     * Logs the exception message to a file.
     *
     * @param string $level The log level to use (default: 'exception').
     * 
     * @return void
     */
    public function log(string $level = 'exception'): void;
    
    /**
     * Handles the exception gracefully based on the environment and error code.
     * 
     * @return void
     * @throws self If in a development environment or if the exception is fatal, the exception is thrown, otherwise, it is logged.
     */
    public function handle(): void;

    /**
     * Create and handle an exception gracefully.
     *
     * @param string $message The exception message.
     * @param string|int $code The exception code (default: 0).
     * @param Throwable|null $previous The previous exception, if available (default: null).
     * 
     * @return void
     * @throws static Throws the exception from the called class.
     */
    public static function throwException(string $message, string|int $code = 0, ?Throwable $previous = null): void;
}