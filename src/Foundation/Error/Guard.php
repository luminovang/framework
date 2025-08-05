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

use \App\Application;
use \Luminova\Luminova;
use \Luminova\Http\Header;
use \Luminova\Logger\Logger;
use \Luminova\Exceptions\ErrorCode;
use \Luminova\Foundation\Error\Message;
use \Luminova\Exceptions\ErrorException;

final class Guard
{
    /**
     * Shared memory to store the last error code.
     * 
     * @var string|int|null $code
     */
    private static string|int|null $code = null;

    /**
     * Shared memory to store the last debug backtrace.
     * 
     * @var array|null $backtrace
     */
    private static ?array $backtrace = null;

    /**
     * Register Luminova's global error and shutdown handling.
     * 
     * Hooks PHP's error and shutdown events so that:
     * - All errors are routed through {@see self::handle()}.
     * - Fatal errors on shutdown are processed by {@see self::shutdown()}.
     * 
     * @return void
     * @internal
     */
    public static function register(): void
    {
        set_error_handler([static::class, 'handle']);
        register_shutdown_function([static::class, 'shutdown']);
    }

    /**
     * Clears the shared last error code and back tracer.
     * 
     * @return void
     * @internal
     */
    public static function free(): void 
    {
        self::$code = null;
        self::$backtrace = null;
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
     * @param int    $severity The error severity level.
     * @param string $message  The error message.
     * @param string $file     The full path to the file where the error occurred.
     * @param int    $line     The line number of the error.
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
        $file = Luminova::filterPath($file);
        $name = ErrorCode::getName($code);
        
        if((bool) ini_get('display_errors') && !PRODUCTION){
            if (ErrorCode::isCritical($severity)) {
                throw new ErrorException($message, $code, $severity, $file, $line);
            }

            printf("<b>%s</b>: %s in %s on line %d<br>\n", 
                ucfirst(strtolower($name)), 
                $message, $file, $line
            );
            return true;
        }

        self::log(
            ErrorCode::getLevel($severity),
            sprintf('[%s (%s)] %s File: %s Line: %d.', $name, (string) $code, $message, $file, $line)
        );
        return true;
    }

    /**
     * Shutdown handler for fatal or last-minute errors.
     * 
     * Executes when the script terminates, checking for a final error from `error_get_last()`.  
     * 
     * Behavior:
     * - Passes error data to `Application::onShutdown()`, stopping if handled.
     * - Displays a fatal error page if needed (production or fatal).
     * - Logs the error if hidden from display or in production.
     *
     * @return void
     * @internal
     */
    public static function shutdown(): void 
    {
        if (($error = error_get_last()) === null || !isset($error['type'])) {
            return;
        }

        if(!Application::onShutdown($error)){
            return;
        }

        $isFatal = ErrorCode::isFatal($error['type']);
        $isDisplay = (bool) ini_get('display_errors');
        $code = self::findCode($error['message'], $error['type']);
        $name = ErrorCode::getName($code);

        if($isFatal || ($isFatal && PRODUCTION)){
            $isFatal = true;
            self::display(new Message(
                message: $error['message'], 
                code: $code,
                severity: (int) $error['type'],
                file: $error['file'],
                line: $error['line'],
                name: $name
            ));
        }elseif($isDisplay){
            printf(
                '[%s (%s)] %s File: %s Line: %d.', 
                $name, (string) $code,
                $error['message'],
                Luminova::filterPath($error['file']), 
                $error['line']
            );
        }

        if(!$isDisplay || PRODUCTION){
            self::log(ErrorCode::getLevel($error['type']), sprintf(
                '[%s (%s)] %s File: %s Line: %d.', 
                $name, (string) $code,
                $error['message'], $error['file'], $error['line']
            ));
        }
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
    ): void 
    {
        throw new ErrorException(message: $message, code: $code, file: $file, line: $line);
    }

    /**
     * Issues a deprecation warning or logs it in production.
     *
     * - In development: triggers a PHP `E_USER_DEPRECATED` warning.
     * - In production: logs the warning at `notice` level if `$force` is `false`.
     *
     * @param string $message Deprecation message (supports placeholders).
     * @param string $since Version when the deprecation started (e.g., "1.5.0").
     * @param array|null $arguments Optional values to replace placeholders in the message.
     * @param bool $force Force triggering warning even in production (default: false).
     *
     * @return bool Returns true if the message was logged or triggered.
     *
     * @example - Simple deprecation warning:
     * ```php
     * Guard::deprecate('Method foo() is deprecated. Use getFoo() instead.');
     * ```
     * 
     * @example - Formatted deprecation warning:
     * ```php
     * Guard::deprecate('Method %s() is deprecated. Use %s() instead.', '1.5.0', ['foo', 'getFoo']);
     * ```
     */
    public static function deprecate(
        string $message,
        string $since = '',
        ?array $arguments = null,
        bool $force = false
    ): bool 
    {
        if ($since !== '') {
            $message .= " (since {$since})";
        }

        $message = $arguments ? vsprintf($message, $arguments) : $message;

        if (!$force && PRODUCTION) {
            Logger::notice($message);
            return true;
        }

        return trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * Stores the last debug backtrace in the shared error context.
     * 
     * Accessible via `Guard::getBacktrace()`.  
     * Can either replace the current backtrace or prepend to it.
     * 
     * @param array $backtrace Array of backtrace information.
     * @param bool $push If true (default), prepends to the existing backtrace.
     * 
     * @return void
     */
    public static function setBacktrace(array $backtrace, bool $push = true): void 
    {
        if($push && !empty(self::$backtrace)){
            self::$backtrace = array_merge($backtrace, self::$backtrace);
            return;
        }

        self::$backtrace = $backtrace;
    }

    /**
     * Stores the last error code in the shared error context.
     * 
     * Accessible via `Guard::getCode()`.
     * 
     * @param string|int $code The last error code value.
     * 
     * @return void
     */
    public static function setLastErrorCode(string|int $code): void 
    {
        self::$code = $code;
    }

    /**
     * Retrieves the last stored error code or a default value.
     * 
     * @param string|int $default Default error code if none was stored (default: `E_ERROR`).
     * 
     * @return string|int Return the last stored error code, or the provided default.
     */
    public static function getCode(string|int $default = E_ERROR): string|int
    {
        return self::$code ?? $default;
    }

    /**
     * Retrieves the last debug backtrace from the shared error context.
     * 
     * This method accesses a shared memory `$trace` to retrieve
     * the stored debug backtrace. If the backtrace is not set, it returns an empty array.
     * 
     * @return array Return the debug backtrace or an empty array if not available.
     */
    public static function getBacktrace(): array 
    {
        return self::$backtrace ?? [];
    }

    /**
     * Sanitize error message.
     * 
     * This method sanitizes an error message by removing the application root path and trimming extraneous details.
     *
     * Strips out the `APP_ROOT` path to avoid exposing sensitive directory structures,  
     * and removes trailing phrases like "thrown in" for cleaner output.
     *
     * @param string $message The original error message.
     * 
     * @return string Returns the sanitized error message.
     */
    public static function sanitizeMessage(string $message): string
    {
        $message = str_replace(APP_ROOT, '', $message);
        preg_match('/^(.*?)(?:\s+thrown(?:\s+in)?)/i', $message, $matches);
   
        return trim($matches[1] ?? $message);
    }

    /**
     * Determines the most relevant exception code from an error message or severity.
     * 
     * - Extracts a numeric code from messages like: "Uncaught Exception: (123)".
     * - Maps "Call to undefined" errors to `ErrorCode::UNDEFINED`.
     * - Falls back to the current stored code or provided severity if it has a known name.
     *
     * @param string  $message  The error message to inspect.
     * @param string|int $severity Fallback severity/type code (default: `ErrorCode::ERROR`).
     * 
     * @return string|int Return the extracted or derived error code.
     * @internal
     */
    public static function findCode(string $message, string|int $severity = ErrorCode::ERROR): string|int
    {
        if (preg_match('/^Uncaught \w+:\s*\((\d+)\)/', $message, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^Uncaught \w+:\s*Call to undefined/i', $message)) {
            return ErrorCode::UNDEFINED;
        }

        if (ErrorCode::has($severity)) {
            return $severity;
        }

        return self::$code ?? $severity;
    }

    /**
     * Display a basic error message when no error handler is available.
     * 
     * @param int $retryAfter Number of seconds before the client should retry (default: 60).
     * 
     * @return void
     */
    public static function notify(int $retryAfter = 60): void
    {
        $error = 'An error has prevented the application from running correctly.';

        if (Luminova::isCommand()) {
            echo $error;
            return;
        }

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Retry-After: ' . $retryAfter);
        }

        printf('<html><head><title>Error Occurred</title></head><body><h1>Error Occurred</h1><p>%s</p></body></html>', $error);
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

        if (defined('IS_UP') && PRODUCTION) {
            if (env('logger.mail.logs')) {
                return [true, 'mailer.php', $path];
            } 
            
            if (env('logger.remote.logs')) {
                return [true, 'remote.php', $path];
            }
        }

        $view = match (true) {
            Luminova::isCommand() => 'cli.php',
            Luminova::isApiPrefix(true) => defined('IS_UP')
                ? env('app.api.prefix', 'api') . '.php'
                : 'api.php',
            default => ($error instanceof Message) ? 'errors.php' : 'info.php',
        };

        return [false, $view, $path];
    }

    /**
     * Gracefully log error messages.
     * 
     * Logs messages using the internal logger if the application is marked as up (IS_UP),
     * or writes to a file-based fallback log if not.
     *
     * @param string $level   The log level (e.g. error, warning, info).
     * @param string $message The log message to write.
     * 
     * @return void
     */
    private static function log(string $level, string $message): void 
    {
        if (defined('IS_UP')) {
            Logger::dispatch($level, $message);
            return;
        }

        $path = __DIR__ . '/../writeable/logs/';
        $filename = "{$path}{$level}.log";

        if (!is_dir($path) && !@mkdir($path, 0777, true) && !is_dir($path)) {
            return;
        }

        $formatted = sprintf('[%s] [%s] [Boot::Fallback] %s', 
            strtoupper($level),
            date('Y-m-d\TH:i:sP'),
            $message
        );

        if (@file_put_contents($filename, $formatted . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            @chmod($filename, 0666);
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
        $isCommand = $view === 'cli.php';

        // Get tracer for php error if not available
        if($isTraceable || SHOW_DEBUG_BACKTRACE){
            self::setBacktrace(debug_backtrace(), true);
        }
        
        if (!$isCommand) {
            Header::clearOutputBuffers();
        }

        if(file_exists($path . $view)){
            include_once $path . $view;
            return;
        }

        // Give up and output basic issue message
        self::notify($isCommand);
    }
}