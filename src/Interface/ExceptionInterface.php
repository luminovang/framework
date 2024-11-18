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

use \Luminova\Exceptions\AppException;
use \Throwable;

interface ExceptionInterface
{
    /**
     * Constructor for the exception.
     *
     * @param string $message The error message for the exception.
     * @param string|int $code The exception code (default: 0).
     * @param Throwable|null $previous The previous exception, if available (default: null).
     */
    public function __construct(string $message, string|int $code = 0, ?Throwable $previous = null);

    /**
     * Sets the exception code.
     *
     * @param string|int $code The string or integer representation of the exception code.
     * @return self Returns the current instance for method chaining.
     */
    public function setCode(string|int $code): self;

    /**
     * Sets the file where the error occurred.
     * 
     * @param string $file The file where the error occurred.
     * @return self Returns the current instance for method chaining.
     */
    public function setFile(string $file): self;

    /**
     * Sets the line number where the error occurred.
     * 
     * @param int $line The line number of the error.
     * @return self Returns the current instance for method chaining.
     */
    public function setLine(int $line): self;

    /**
     * Retrieves the filtered exception message without the file path.
     * 
     * @return string The filtered exception message.
     */
    public function getFilteredMessage(): string;

    /**
     * Gets the name of the exception.
     * 
     * @return string The name of the thrown exception.
     */
    public function getName(): string;

    /**
     * Retrieves the last debug backtrace from the exception or shared error context.
     *
     * This method checks for the stored debug backtrace in the shared variable `__ERROR_DEBUG_BACKTRACE__`
     * if the exception trace is unavailable. Returns an empty array if not set.
     * 
     * @return array The debug backtrace or an empty array if not available.
     */
    public function getBacktrace(): array;

    /**
     * Gets a string representation of the exception.
     *
     * @return string Return a formatted error message representing the exception.
     */
    public function __toString(): string;

    /**
     * Logs the exception message to a specified log file.
     * Based on your `App\Config\Logger`, if asynchronous logging is enabled, all log will use Fiber for asynchronous logging.
     * If on production, `logger.mail.logs` or `logger.remote.logs` is set, the log will be redirected to email or remote server.
     *
     * @param string $level The log level to use (default: 'exception').
     * 
     * @return void
     * 
     * Log Levels:
     * 
     * - emergency - Log emergency error that need attention.
     * - alert - Log alert message. 
     * - critical - Log critical issue that may cause app not to work properly. 
     * - error - Log minor error.
     * - warning - Log a warning message.
     * - notice - Log a notice to attend later.
     * - info - Log an information.
     * - debug - Log for debugging purpose.
     * - exception - Log an exception message.
     * - php_errors - Log any php related error.
     * - metrics - Log performance metrics, specifically for api in production level.
     */
    public function log(string $level = 'exception'): void;

    /**
     * Handles the exception gracefully based on the environment and error code.
     *
     * @return void
     * @throws AppException<\T> If in a development environment or if the exception is fatal, the exception is thrown; otherwise, it is logged.
     */
    public function handle(): void;

    /**
     * Creates and handles an exception gracefully.
     *
     * @param string $message The exception message.
     * @param string|int $code The exception code (default: 0).
     * @param Throwable|null $previous The previous exception, if available (default: null).
     * 
     * @return void
     * @throws AppException<\T> Throws the exception from the called class.
     */
    public static function throwException(string $message, string|int $code = 0, ?Throwable $previous = null): void;
}