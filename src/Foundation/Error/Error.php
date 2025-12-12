<?php
/**
 * Luminova Framework error handling system.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Foundation\Error;

use \Throwable;
use Luminova\Luminova;
use Luminova\Http\Header;
use Luminova\Utility\Text;
use Luminova\Logger\Logger;
use Luminova\Debugger\Tracer;
use Luminova\Logger\NovaLogger;
use function Luminova\Funcs\root;
use Luminova\Exceptions\ErrorCode;
use Luminova\Foundation\Error\Message;
use Luminova\Exceptions\{ErrorException, RuntimeException, LuminovaException};

final class Error
{
    /**
     * Handling shutdown flag.
     *
     * @var bool $handlingShutdown
     * > Prevents recursive execution
     */
    private static bool $handlingShutdown = false;

    /**
     * Register Luminova's global error and shutdown handling.
     * 
     * Hooks PHP's error and shutdown events so that:
     * - All errors are routed through {@see self::handle()}.
     * - Uncaught exceptions handler with {@see self::exceptions()}.
     * - Fatal errors on shutdown are processed by {@see self::shutdown()}.
     * 
     * @return void
     * @internal
     */
    public static function register(): void
    {
        set_error_handler([self::class, 'handle']);
        set_exception_handler([self::class, 'exceptions']);
        register_shutdown_function([self::class, 'shutdown']);
    }

    /**
     * Global error handler for recoverable PHP errors.
     * 
     * Invoked automatically for warnings, notices, deprecations, and other recoverable errors.
     * Skips suppressed errors based on the current `error_reporting()` level.  
     * 
     * Behavior:
     * - In non-production with `display_errors` enabled:  
     *   Throws critical errors or prints formatted error messages.  
     * - In production or with `display_errors` disabled:  
     *   Logs the error in a structured format.
     *
     * @param int $severity The error severity level.
     * @param string $message The error message.
     * @param string $file The full path to the file where the error occurred.
     * @param int $line The line number of the error.
     * 
     * @return bool Return true if handled, false if suppressed.
     * @internal
     */
    public static function handle(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $code = self::findCode($message, $severity);
        $file = Luminova::toDisplayPath($file);
        $name = ErrorCode::getName($code);
        
        if((bool) ini_get('display_errors') && !PRODUCTION){
            if (ErrorCode::isCritical($severity)) {
                throw new ErrorException($message, $code, $severity, $file, $line);
            }

            printf(
                "<b>%s</b>: %s in %s on line %d<br>\n", 
                ucfirst(strtolower($name)), 
                $message, $file, $line
            );
            return true;
        }

        self::tryLog(
            ErrorCode::getLevel($severity),
            sprintf(
                '[%s] %s in %s:%d (code: %s).', 
                $name, (string)
                $message, 
                $file, 
                $line,
                $code
            )
        );
        return true;
    }

    /**
     * Handles the final shutdown phase of the application.
     *
     * This method is automatically invoked by the registered shutdown handler and
     * may also be called manually to process a supplied error.
     *
     * It retrieves the last PHP error (when no error is provided), prevents
     * recursive execution, notifies the application through the `onShutdown()`
     * lifecycle event, displays a fatal error page when appropriate, logs the
     * error if required, and closes any open logger streams before terminating.
     *
     * Shutdown flow:
     * - Prevents recursive shutdown handling.
     * - Uses `error_get_last()` when no error is provided.
     * - Invokes the application `onShutdown()` event (unless forced).
     * - Displays a fatal error page or prints the error when appropriate.
     * - Logs the error when it is not displayed or when running in production.
     * - Closes all logger streams before exiting.
     *
     * @param array{
     *     type?:int,
     *     force?:bool,
     *     message:string,
     *     file:string,
     *     line:int
     * }|null $error Optional shutdown error information. When `null`, the last PHP
     * error is obtained using `error_get_last()`.
     *
     * @return void
     * @internal Used for core register_shutdown_function handler
     */
    public static function shutdown(?array $error = null): void
    {
        if (self::$handlingShutdown) {
            return;
        }

        self::$handlingShutdown = true;
        $error ??= error_get_last();

        if ($error === null || !isset($error['type'])) {
            self::$handlingShutdown = false;
            return;
        }

        $isForce = ($error['force'] ?? false);

        if(!$isForce){
            try{
                if(!Luminova::kernel('app', shared: true)->trigger('onShutdown', $error)){
                    self::$handlingShutdown = false;
                    return;
                }
            } catch(Throwable) {}
        }

        $isUp = defined('APP_BOOTED');
        $isDisplay = (bool) ini_get('display_errors');
        $isFatal = ErrorCode::isFatal($error['type']);

        $code = self::findCode($error['message'], $error['type']);
        $name = ErrorCode::getName($code);

        try{
            if($isFatal || ($isUp && $isFatal && PRODUCTION)){
                $isFatal = true;
                self::display(new Message(
                    message: $error['message'], 
                    code: $code,
                    severity: (int) $error['type'],
                    file: $error['file'],
                    line: $error['line'],
                    name: $name
                ));
            }elseif(!$isUp || $isDisplay){
                printf(
                    '[%s (%s)] %s File: %s Line: %d.', 
                    $name, (string) $code,
                    $error['message'],
                    Luminova::toDisplayPath($error['file']), 
                    $error['line']
                );
            }
        }catch(Throwable){
            if($isUp){
                Luminova::terminate(500, sprintf(
                    '[%s (%s)] %s File: %s Line: %d.', 
                    $name, (string) $code,
                    $error['message'], $error['file'], $error['line']
                ));
            }
        }finally{
            self::$handlingShutdown = false;

            if(!$isDisplay || ($isUp && PRODUCTION)){
                self::tryLog(ErrorCode::getLevel($error['type']), sprintf(
                    '[%s] %s in %s:%d (code: %s).', 
                    $name, 
                    $error['message'],
                    $error['file'], 
                    $error['line'],
                    (string) $code,
                ), $isUp);
            }

            if($isUp){
                NovaLogger::close();
            }
        }

        exit(0);
    }

    /**
     * Handle uncaught exceptions.
     * 
     * @param Throwable $e The exception object.
     * 
     * @return void
     * @internal
     */
    public static function exceptions(Throwable $e): void
    {
        if(!class_exists(RuntimeException::class)){
            throw new \RuntimeException(
                $e->getMessage(),
                $e->getCode(), 
                $e
            );
        }

        $previous = $e->getPrevious();

        if(!$e instanceof LuminovaException){
            $e = new RuntimeException(
                $e->getMessage(),
                $e->getCode(), 
                $previous
            );
        }

        [$file, $line] = ($previous instanceof Throwable) 
            ? [$previous->getFile(), $previous->getLine()]
            : Tracer::trace(2);

        if($file){
            $e->setFile($file)->setLine($line);
        }

        $e->handle();
    }

    /**
     * Throws an ErrorException with the given message, code, and optional file/line.
     *
     * @param string $message Error message.
     * @param string|int $code Error code (default: `ErrorCode::USER_NOTICE`).
     * @param string|null $file Optional file where the error occurred.
     * @param int|null $line Optional line where the error occurred.
     *
     * @return never
     * @throws ErrorException Always throw error when called.
     */
    public static function trigger(
        string $message,
        string|int $code = ErrorCode::USER_NOTICE,
        ?string $file = null,
        ?int $line = null
    ): never 
    {
        $e = new ErrorException(
            message: $message, 
            code: $code, 
            file: $file, 
            line: $line
        );

        if($file === null){
            [$file, $line] = Tracer::trace(2);
                
            if($file){
                $e->setFile($file)->setLine($line);
            }
        }

        throw $e;
    }

    /**
     * Reports a deprecation event using logging, exceptions, or PHP warnings.
     *
     * This method formats the deprecation message, optionally appends version metadata,
     * and dispatches it based on the selected mode.
     *
     * Modes:
     * - 0: Log deprecation as a notice (non-blocking, production-safe)
     * - 1: Terminate application  with 502 HTTP code(strict enforcement mode)
     * - 2: Throw an exception (strict enforcement mode)
     * - default: Trigger a PHP `E_USER_DEPRECATED` warning
     *
     * The message supports sprintf-style placeholders and optional version tagging.
     *
     * @param string $message Deprecation message (supports sprintf placeholders).
     * @param string $since Version when the feature was deprecated (e.g. "1.5.0").
     * @param array|null $arguments Values used to replace placeholders in the message.
     * @param int $mode Dispatch mode (0=log, 1=terminate, 2=exception, default=warning).
     *
     * @return bool True if the deprecation was logged or a warning was triggered.
     * @throws ErrorException When mode 1 (strict exception mode) is used.
     *
     * @example - Example simple deprecation warning:
     * ```php
     * Error::deprecate('Method foo() is deprecated. Use getFoo() instead.');
     * ```
     *
     * @example - Example formatted deprecation warning:
     * ```php
     * Error::deprecate(
     *     'Method %s() is deprecated. Use %s() instead.',
     *     '1.5.0',
     *     ['foo', 'getFoo']
     * );
     * ```
     */
    public static function deprecate(
        string $message,
        string $since = '',
        ?array $arguments = null,
        int $mode = 0
    ): bool
    {
        if ($since !== '') {
            $message .= " (since {$since})";
        }

        if ($arguments) {
            $message = vsprintf($message, $arguments);
        }

        if($mode === 0){
            return Logger::tryLog('notice', $message);
        }

        if($mode === 1){
            Luminova::terminate(502, $message);
            return true;
        }

        if($mode === 2){
            $e = new ErrorException($message, ErrorCode::DEPRECATED);
            [$file, $line] = Tracer::trace(2);

            if($file){
                $e->setFile($file)->setLine($line);
            }

            throw $e;
        }

        return trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * Sanitizes an error message by removing environment-specific and diagnostic details.
     *
     * Removes internal paths, stack trace output, PHP file locations, and exception
     * suffixes to keep only the readable error message.
     *
     * The sanitization removes:
     * - Application root paths
     * - PHP file references with line numbers
     * - Stack trace sections
     * - "thrown in" exception location suffixes
     *
     * @param string $message Raw error or exception message.
     *
     * @return string Cleaned error message.
     */
    public static function sanitizeExceptionMessage(string $message): string
    {
        if (defined('APP_ROOT') && APP_ROOT !== '') {
            $message = str_replace(APP_ROOT, '', $message);
        }

        // clear error file+line
        // $message = preg_replace(
        //    '#\b(?:in\s+)?[^\s]+\.php(?::\d+|\s+on\s+line\s+\d+)\b#i',
        //    '',
        //    $message
        //);

        // Remove stack trace section.
        $message = Text::before($message, 'Stack trace:');

        // Remove PHP file location suffix.
        $message = preg_replace(
            '#\s*(?:in\s+)?[^\s]+\.php(?::\d+|\s+on\s+line\s+\d+)\s*$#i',
            '',
            $message
        );

        // Remove exception location suffix.
        $message = Text::before($message, 'thrown in');

        return trim($message);
    }

    /**
     * Determines the most relevant exception code from an error message or severity.
     * 
     * - Extracts a numeric code from messages like: "Uncaught Exception: (123)".
     * - Maps "Call to undefined" errors to `ErrorCode::UNDEFINED`.
     * - Falls back to the current stored code or provided severity if it has a known name.
     *
     * @param string $message The error message to inspect.
     * @param string|int $severity Fallback severity/type code (default: `ErrorCode::ERROR`).
     * 
     * @return string|int Return the extracted or derived error code.
     * @internal
     */
    public static function findCode(string $message, string|int $severity = ErrorCode::ERROR): string|int
    {
        if (preg_match('/^Uncaught\s*\w+:\s*\((\d+)\)/', $message, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^Uncaught\s*\w+:\s*Call to undefined/i', $message)) {
            return ErrorCode::UNDEFINED;
        }

        if (ErrorCode::has($severity)) {
            return $severity;
        }

        return Tracer::getLastErrorCode() ?? $severity;
    }

    /**
     * Display a basic error message when no error handler is available.
     * 
     * @param int $retryAfter Number of seconds before the client should retry (default: 60).
     * 
     * @return void
     * @deprecated version
     */
    public static function notify(int $retryAfter = 60): void
    {
        $error = 'An error has prevented the application from running correctly.';

        if (Luminova::isCommand()) {
            echo $error;
            exit(STATUS_ERROR);
        }

        Luminova::terminate(500, $error, retry: $retryAfter);
        exit(STATUS_ERROR);
    }
    
    /**
     * Resolves the appropriate error view based on application state and context.
     *
     * @param Message|null $error The instance of Message or null if not available.
     * 
     * @return array{0:bool,1:string,2:string} 
     *         Returns an array containing:
     *         [0] bool   - Whether a tracer-specific view was selected.
     *         [1] string - The view file name to render.
     *         [2] string - The absolute path to the error views directory.
     */
    private static function errRoute(?Message $error): array
    {
        $path = APP_ROOT . 'app/Errors/Defaults/';

        if (defined('APP_BOOTED') && PRODUCTION) {
            if (env('logger.mail.logs')) {
                return [true, 'mailer.php', $path];
            } 
            
            if (env('logger.remote.logs')) {
                return [true, 'remote.php', $path];
            }
        }

        $view = match (true) {
            Luminova::isCommand() => 'cli.php',
            Luminova::isApiRequest(true) => 'api.php',
            ($error instanceof Message) => 'errors.php',
            default => 'info.php',
        };

        return [false, $view, $path];
    }

    /**
     * Gracefully log error messages.
     * 
     * Logs messages using the internal logger if the application is marked as up (APP_BOOTED),
     * or writes to a file-based fallback log if not.
     *
     * @param string $level The log level (e.g. error, warning, info).
     * @param string $message The log message to write.
     * 
     * @return void
     */
    private static function tryLog(string $level, string $message, ?bool $isUp = null): void 
    {
        $isUp ??= defined('APP_BOOTED');
        $path = null;

        if ($isUp ) {
            if(Logger::tryDispatch($level, $message)){
                return;
            }

            $path = root('/writeable/logs/');
        }

        $path ??= __DIR__ . '/../../../writeable/logs/';

        if (!is_dir($path) && !@mkdir($path, 0775, true)) {
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $filename = "{$path}{$level}.log";
        $formatted = sprintf(
            "[%s] [%s] PHP %s%s",
            date(DATE_ATOM),
            strtoupper($level),
            $message,
            PHP_EOL
        );

        if (error_log($formatted, 3, $filename) === false) {
            error_log($formatted);
        }
    }

    /**
     * Display system errors based on the given error.
     *
     * This method includes an appropriate error view based on the environment and request type.
     *
     * @param Message|null $error The instance of Message containing error information.
     * 
     * @return void
     */
    private static function display(?Message $error = null): void 
    {
        [$isTraceable, $view, $path] = self::errRoute($error);

        // Get tracer for php error if not available
        if($isTraceable || SHOW_DEBUG_BACKTRACE){
            Tracer::setBacktrace(debug_backtrace(), true);
        }

        if(is_file($path . $view)){
            Header::clearOutputBuffers('all');

            include_once $path . $view;
            exit(1);
        }

        // Give up and output basic issue message
        $error ??= 'An error has prevented the application from running correctly.';

        Luminova::terminate(
            500, 
            (string) $error, 
            retry: 60
        );
    }
}