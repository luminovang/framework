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

use \Throwable;
use Luminova\Exceptions\LuminovaException;

interface ExceptionInterface
{
    /**
     * Checks if the current exception's code matches a given code or any in an array.
     *
     * Compares the provided code(s) against the exception's string code (if available) 
     * or its numeric code as a fallback.
     *
     * @param array<int,string|int>|string|int $code The code or list of codes to compare against.
     *
     * @return bool Returns `true` if a match is found, otherwise `false`.
     */
    public function isCode(array|string|int $code): bool;

    /**
     * Set the exception code.
     *
     * @param string|int $code The exception code as a string or integer (e.g, `Luminova\Exceptions\ErrorCode::*`).
     * 
     * @return self Return the current exception instance.
     */
    public function setCode(string|int $code): self;

    /**
     * Set the file where the error occurred.
     * 
     * @param string $file The file path where the error occurred.
     * 
     * @return self Return the current exception instance.
     */
    public function setFile(string $file): self;

    /**
     * Set the line number where the error occurred.
     * 
     * @param int $line The line number of the error.
     * 
     * @return self Return the current exception instance.
     */
    public function setLine(int $line): self;

    /**
     * Get a sanitized error message.
     * 
     * This strips environment-specific and backtrace details, ensuring messages doesn't contain 
     * any sensitive information like server paths and debug details.
     *
     * This method cleans raw exception or error strings by removing:
     * - Application root paths to avoid exposing internal directory structure
     * - PHP file references with line numbers (e.g. "file.php:12", "file.php on line 12")
     * - Stack trace sections ("Stack trace: ...")
     * - PHP exception suffixes ("thrown in ...")
     * 
     * @return string Return a filtered exception message.
     * 
     * > The goal is to extract only the human-readable core error message
     * without debugging or system-level context.
     * 
     * @see self::getMessage()
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
     * Returns the parent exception string representation.
     * 
     * This is the native PHP formatted exception output including class name,
     * message, file, line, and stack trace.
     *
     * Triggered automatically when the object is cast to string (echo/print).
     *
     * @return string Full native exception string representation.
     * @see self::toString() for controlled message output.
     */
    public function __toString(): string;

    /**
     * Returns a simplified formatted exception string without stack trace.
     *
     * This provides a compact single-line representation containing:
     * class name, message, file, line, and error code.
     *
     * Format:
     * `Class: message in file.php:line N (code: N)`
     *
     * @return string Compact exception summary string.
     * > **Note:**
     * > This method is not triggered when exception is cast as string {@see self::__toString()}
     */
    public function toString(): string;

    /**
     * Logs the current exception through the configured logging pipeline.
     *
     * The log is processed by the application logger and routed based on the given dispatch key.
     * In production, logs may be stored locally, streamed, or forwarded to external systems
     * (e.g. email or remote logging endpoints) depending on configuration.
     *
     * If asynchronous logging is enabled, the operation may be executed in a background.
     *
     * @param string|null $dispatch Log level or dispatch for remote, email or telegram login (default: `null`).
     *                      If null decide based on error code.
     *
     * @return void
     *
     * Available log levels:
     *
     * - emergency:     System is unusable and requires immediate attention.
     * - alert:         Action must be taken immediately.
     * - critical:      Critical condition affecting application stability.
     * - error:         Runtime error that does not stop execution.
     * - warning:       Warning conditions.
     * - notice:        Normal but significant events.
     * - info:          Informational messages.
     * - debug:         Debug-level details for development.
     * - exception:     Exception reporting channel.
     * - php:           Native PHP errors.
     */
    public function log(?string $dispatch = null): void;

    /**
     * Handles current exception safely through the framework error pipeline instead of throwing it.
     *
     * Depending on environment and execution mode, it may:
     * - Be thrown (debug/development mode)
     * - Be logged silently (production)
     * - Render CLI or HTTP error views
     * - Terminate execution for fatal errors
     *
     * @return void
     * @throws LuminovaException<static> In debug environments only
     */
    public function handle(): void;

    /**
     * Retrieves the file and line number from the call stack at a given depth.
     *
     * Wraps `debug_backtrace()` and returns the origin of the call at the specified stack level.
     *
     * @param int $depth Call stack depth:
     *        - 0 = current method call
     *        - 1 = immediate caller
     *        - higher values trace further up the stack
     * @param int $options Flags passed to `debug_backtrace()`.
     *        Defaults to `DEBUG_BACKTRACE_IGNORE_ARGS` for performance.
     *
     * @return array{0:?string,1:int} Returns:
     *         - 0: File path of the caller (null if unavailable)
     *         - 1: Line number of the caller (1 if unavailable)
     * 
     * @deprecated Use Luminova\Debugger\Tracer::trace() instead.
     */
    public static function trace(int $depth, int $options = DEBUG_BACKTRACE_IGNORE_ARGS): array;

    /**
     * Throws a new exception of the current class with resolved trace context.
     *
     * If a previous exception is provided, its file and line are preserved.
     * Otherwise, the call stack is inspected to determine origin.
     *
     * @param string $message The exception message.
     * @param string|int $code The exception code (default: 0).
     * @param Throwable|null $previous Previous exception for chaining context.
     *
     * @return never
     * @throws LuminovaException<static> Throws the exception from the called class.
     */
    public static function throwException(
        string $message,
        string|int $code = 0,
        ?Throwable $previous = null
    ): void;

    /**
     * Handles an exception through the framework error pipeline instead of throwing it.
     *
     * The exception is enriched with trace context, then passed to the global error handler.
     * Depending on environment and execution mode, it may:
     * - Be thrown (debug/development mode)
     * - Be logged silently (production)
     * - Render CLI or HTTP error views
     * - Terminate execution for fatal errors
     *
     * @param string $message The exception message.
     * @param string|int $code The exception code (default: 0).
     * @param Throwable|null $previous Previous exception for context chaining.
     *
     * @return void
     * @throws LuminovaException<static> In debug environments only
     */
    public static function handleException(
        string $message,
        string|int $code = 0,
        ?Throwable $previous = null
    ): void;

    /**
     * Re-throws a Throwable as a different exception type while preserving context.
     *
     * Creates a new exception instance using the given class (or the current class if none provided),
     * copying the original message, code, and chaining the previous exception.
     *
     * The original file and line are preserved when available; otherwise, the call stack is used
     * to resolve the origin.
     *
     * @param Throwable $e Original exception to transform.
     * @param class-string<Throwable>|null $abstract Target exception class.
     *        Defaults to the calling class.
     *
     * @return never
     * @throws ExceptionInterface|Throwable Always throws the newly created exception instance.
     *
     * @example - Example calling class:
     * try {
     *     throw new Error('Error message');
     * } catch (Throwable $e) {
     *     LogicException::throwAs($e);
     * }
     *
     * @example - Example other class:
     * try {
     *     throw new Error('Error message');
     * } catch (LogicException $e) {
     *     if ($e->isCode(200)) {
     *         LogicException::throwAs($e, RuntimeException::class);
     *     }
     * }
     */
    public static function throwAs(Throwable $e, ?string $abstract = null): void;
}