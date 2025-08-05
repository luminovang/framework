<?php
/**
 * Luminova Framework Exception interface.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \Luminova\Exceptions\AppException;
use \Throwable;

interface ExceptionInterface
{
    /**
     * Checks if the current exception's code matches a given code or any in an array.
     *
     * Compares the provided code(s) against the exception's string code (if available) 
     * or its numeric code as a fallback.
     *
     * @param string|int|array<int,string|int> $code The code or list of codes to compare against.
     *
     * @return bool Returns `true` if a match is found, otherwise `false`.
     */
    public function isCode(string|array|int $code): bool;

    /**
     * Set the exception code.
     *
     * @param string|int $code The exception code as a string or integer (e.g, `Luminova\Exceptions\ErrorCode::*`).
     * 
     * @return static Return the current exception instance.
     */
    public function setCode(string|int $code): self;

    /**
     * Set the file where the error occurred.
     * 
     * @param string $file The file path where the error occurred.
     * 
     * @return static Return the current exception instance.
     */
    public function setFile(string $file): self;

    /**
     * Set the line number where the error occurred.
     * 
     * @param int $line The line number of the error.
     * 
     * @return static Return the current exception instance.
     */
    public function setLine(int $line): self;

    /**
     * Get a formatted message.
     * 
     * This method returns a filtered exception message, ensuring messages doesn't contain 
     * any sensitive information like private server paths.
     * 
     * @return string Return the filtered exception message.
     */
    public function getDescription(): string;

    /**
     * Get an exception error name.
     * 
     * This method returns a humanized exception name based on the exception code.
     * 
     * @return string Return the exception name.
     */
    public function getName(): string;

    /**
     * Get the last debug backtrace from the exception or shared error context.
     *
     * Checks the exception trace first, then the shared variable `__ERROR_DEBUG_BACKTRACE__`
     * if the trace is unavailable. Return an empty array if no backtrace exists.
     * 
     * @return array Return the debug backtrace, or an empty array if not available.
     */
    public function getBacktrace(): array;

    /**
     * Get the string or integer error code for this exception.
     *
     * First returns the string error code (`strCode`) if set; otherwise returns the numeric error code.
     *
     * @return string|int Return the error code as a string if available, otherwise return the numeric error code.
     */
    public function getErrorCode(): string|int;

    /**
     * Returns the raw error message when the object is printed or cast to string.
     * 
     * Triggered automatically by `echo`, `print`, or string casting.
     * 
     * @return string Return the raw error message.
     */
    public function __toString(): string;

    /**
     * Returns a formatted error message with code, file, and line details.
     * 
     * Format: `Exception: (code) message in file/path/foo.php on line N`.
     * 
     * @return string Return the formatted error message with code, file, and line number.
     */
    public function toString(): string;

    /**
     * Logs the exception message to the configured logger.
     * 
     * Uses `App\Config\Logger`. If asynchronous logging is enabled, logs use Fiber for async processing.
     * In production, if `logger.mail.logs` or `logger.remote.logs` is set, logs are sent via email or to a remote server.
     *
     * @param string $dispatch A log level, email address, or remote URL to send the error to (default: 'exception').
     * 
     * @return void
     * 
     * **Log Levels:**
     * 
     * - emergency — Emergency error that needs immediate attention.
     * - alert — Alert message. 
     * - critical — Critical issue that may cause the app to fail. 
     * - error — Standard error.
     * - warning — Warning message.
     * - notice — Notice for later review.
     * - info — Informational message.
     * - debug — Debugging message.
     * - exception — Exception message.
     * - php_error — PHP-related error.
     * - metrics — Performance metrics, typically for production APIs.
     */
    public function log(string $dispatch = 'exception'): void;

    /**
     * Handles exceptions safely depending on the application environment and error type.
     *
     * This method ensures that exceptions are processed appropriately:
     * - In CLI mode: either re-throws the exception (if enabled) or shows a CLI-friendly error detail.
     * - In production: logs the error, shows a user-friendly page for fatal errors, and prevents leaks of sensitive information.
     * - In development: re-throws the exception so it can be displayed directly.
     *
     * The handler also prevents recursive exception handling and ensures consistent shutdown behavior.
     *
     * @return void
     * @throws AppException<\T,Throwable> Re-throws the exception in non-production environments 
     *                   or when explicitly configured for CLI.
     */
    public function handle(): void;

    /**
     * Get the file and line of a specific call depth where the method was called.
     *
     * This method inspects the call stack to determine the file and line number
     * from which the current method was invoked. It wraps debug_backtrace to return the file and line number
     * of the caller at the requested depth.
     *
     * @param int $depth The depth in the call stack (0 = the call to this method itself).
     *              Use `1` to get the immediate caller.
     * @param int $options Options passed to debug_backtrace (default: `DEBUG_BACKTRACE_IGNORE_ARGS`).
     * 
     * @return array<int,mixed> Returns an array containing:
     *              - `string|null`: The file of the caller (default: `null`).
     *              - `int`: The line number in the file (default: 1).
     */
    public static function trace(int $depth, int $options = DEBUG_BACKTRACE_IGNORE_ARGS): array;

    /**
     * Creates and handles an exception gracefully.
     *
     * @param string $message The exception message.
     * @param string|int $code The exception code (default: 0).
     * @param Throwable|null $previous The previous exception, if available (default: null).
     * 
     * @return never
     * @throws AppException<static> Throws the exception from the called class.
     */
    public static function throwException(string $message, string|int $code = 0, ?Throwable $previous = null): void;
    
    /**
     * Rethrow or handle an exception as a specified exception class.
     *
     * If the given Throwable is already an instance of `Luminova\Exceptions\AppException`, it will be handled directly.
     * Otherwise, a new exception of the specified class (or the current class if not provided) will be created with the
     * same message, code, and previous exception, then handled.
     *
     * @param Throwable $e The original exception to rethrow or handle.
     * @param class-string<ExceptionInterface>|null $abstract The new exception class to throw as (e.g., `Luminova\Exceptions\RuntimeException`). Defaults to the current class if null.
     * 
     * @return never
     * @throws Throwable Throws the exception from the called class.
     * @example - Example:
     * ```
     * use Luminova\Exceptions\LogicException;
     * 
     * try{
     *      throw new Error('Error message.');
     * }catch(Throwable $e){
     *      LogicException::throwAs($e);
     * }
     * ```
     *  @example - Example:
     * ```
     * use Luminova\Exceptions\LogicException;
     * use Luminova\Exceptions\RuntimeException;
     * 
     * try{
     *      throw new Error('Error message.');
     * }catch(LogicException $e){
     *      if($e->isCode(200))
     *          LogicException::throwAs($e, RuntimeException::class);
     * }
     * ```
     */
    public static function throwAs(Throwable $e, ?string $abstract = null): void;
}