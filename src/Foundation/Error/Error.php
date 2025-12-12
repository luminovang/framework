<?php
declare(strict_types=1);
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
use \App\Kernel;
use Luminova\Runtime;
use Luminova\Luminova;
use Luminova\Config\Env;
use Luminova\Http\Header;
use Luminova\Utility\Text;
use Luminova\Logger\Logger;
use Luminova\Routing\Router;
use Luminova\Debugger\Tracer;
use Luminova\Command\Terminal;
use Luminova\Exceptions\ErrorCode;
use function Luminova\Funcs\response;
use Luminova\Exceptions\{ErrorException, RuntimeException, LuminovaException};

final class Error
{
    /**
     * @var string PRETTIFY_PATTERN
     */
    private const PRETTIFY_PATTERN = '~<(link)>(https?://[^<\s]+|mailto:[^<\s]+)</\1>|<highlight(?:\s+color="([^"]*)")?>(.*?)</highlight>~is';

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
        $error = [
            'type'     => $severity,
            'message'  => $message,
            'file'     => $file,
            'line'     => $line,
        ];

        Runtime::setLastError($error);

        if (!(error_reporting() & $severity)) {
            return false;
        }

        self::shutdown($error);
        return true;
    }

    /**
     * Handles the final shutdown phase of the application.
     *
     * This method is automatically invoked by the registered PHP shutdown handler
     * and may also be called manually with optional shutdown error information.
     *
     * When no error is supplied, it invokes the application `onShutdown()` lifecycle
     * hook to allow final cleanup and resource management.
     *
     * When an error is detected, it invokes `onErrorShutdown()` unless shutdown is
     * forced. The application can either allow Luminova to continue its default
     * error handling or take full control of the shutdown process.
     *
     * Shutdown flow:
     * - Prevents recursive shutdown handling.
     * - Retrieves the last PHP error using `error_get_last()` when no error is supplied.
     * - Invokes `onShutdown()` during normal shutdown.
     * - Invokes `onErrorShutdown()` when shutdown is caused by an error, unless forced.
     * - Allows the application to take over error shutdown handling.
     * - Displays fatal errors or prints error information when appropriate.
     * - Logs errors when required.
     * - Closes open logger streams after framework error handling.
     *
     * @param array{
     *     type:int,
     *     force?:bool,
     *     message:string,
     *     file:string,
     *     line:int
     * }|null $error Optional shutdown error information. When `null`, the last PHP
     *                error is retrieved using `error_get_last()`.
     *
     * @return void
     * @internal Used by the core register_shutdown_function handler.
     */
    public static function shutdown(?array $error = null): void
    {
        $error ??= error_get_last();

        if($error !== null){
            Runtime::setLastError($error);
        }

        if (self::$handlingShutdown) {
            return;
        }

        self::$handlingShutdown = true;

        // Handle normal application shutdown.
        if ($error === null || !isset($error['type'], $error['message'])) {
            self::shouldShutdown();
            return;
        }

        // Allow the application to intercept error shutdown.
        if (!($error['force'] ?? false) && !self::shouldShutdown($error)) {
            return;
        }

        self::$handlingShutdown = true;

        $isProduction = Runtime::isProduction();
        $isDisplay = (bool) Runtime::tryGetIniValue('display_errors');
        $isFatal = ErrorCode::isFatal($error['type']);
        $isCommand = Runtime::isCommand();

        $code = self::findCode($error['message'], $error['type']);
        $name = ErrorCode::getName($code);

        try {
            if (!$isProduction && $isDisplay && $isFatal) {
                throw new ErrorException(
                    $error['message'],
                    $code,
                    $error['type'],
                    $error['file'],
                    $error['line']
                );
            }

            if (
                (!$isFatal && ($isProduction || !$isDisplay))
                || ($isCommand && Env::get('throw.cli.exceptions', false))
            ) {
                return;
            }

            if ($isFatal || $isCommand) {
                self::display(new ErrorException(
                    $error['message'],
                    $code,
                    $error['type'],
                    $error['file'],
                    $error['line']
                ));

                return;
            }

            self::printLine(
                $error['message'],
                $name,
                Luminova::toDisplayPath($error['file']),
                $error['line'],
                (string) $code,
                isCommand: $isCommand
            );
        } catch (ErrorException $e) {
            throw $e;
        } catch (Throwable) {
            Luminova::terminate(500, sprintf(
                '[%s (%s)] %s File: %s Line: %d.',
                $name,
                (string) $code,
                $error['message'],
                $error['file'],
                $error['line']
            ));
        } finally {
            self::$handlingShutdown = false;

            if (!$isDisplay || $isProduction) {
                self::tryLog(
                    ErrorCode::getLevel($error['type']),
                    sprintf(
                        '[%s] %s in %s:%d (code: %s).',
                        $name,
                        $error['message'],
                        $error['file'],
                        $error['line'],
                        (string) $code
                    )
                );
            }

            Logger::close();
        }

        exit(0);
    }

    /**
     * Display the appropriate system error page.
     *
     * Resolves and renders the error view based on the current environment,
     * request type, and application boot state.
     *
     * @param Throwable|LuminovaException<Throwable> $error The error or message to display.
     * @param int              $status HTTP status code for the response.
     *
     * @return never
     */
    public static function display(Throwable $error, int $status = 500): never
    {
        $display = Runtime::tryGetIniValue('display_errors');
        $startup = Runtime::tryGetIniValue('display_startup_errors');

        Runtime::trySetIniValue('display_errors', '0');
        Runtime::trySetIniValue('display_startup_errors', '0');

        try{
            // Set the PHP error backtrace when tracing is enabled.
            if (Runtime::getConstantValue('SHOW_DEBUG_BACKTRACE')) {
                Runtime::setLastBacktrace(debug_backtrace(), true);
            }

            if(Runtime::isCommand()){
                Terminal::init();
                Terminal::exception($error);
                exit(1);
            }

            if(Router::isApiRequest(true)){
                response()->json(self::getApiErrorResponse($error));
                exit(1);
            }

            $isProduction = Runtime::isProduction();

            if(!$isProduction){
                $template = __DIR__ . '/Templates/errors.php';

                if (@is_file($template)) {
                    Header::sendNoCacheHeaders($status);
                    Runtime::cleanOutputBuffers('all');

                    include_once $template;
                    exit(1);
                }

                $message = ($error instanceof LuminovaException) 
                    ? $error->getDescription() : $error->getMessage();
            } else{
                $message = 'Something went wrong, check your server error logs for more details.';
            }

            Luminova::terminate(
                $status,
                $message,
                retry: 60
            );
        } finally {
            if($display !== false){
                Runtime::trySetIniValue('display_errors', $display);
            }

            if($startup !== false){
                Runtime::trySetIniValue('display_startup_errors', $startup);
            }
        }

        exit(1);
    }

    /**
     * Handles uncaught exceptions registered.
     *
     * Converts standard exceptions into a framework runtime exception when
     * necessary, preserves the original exception location, and delegates
     * exception handling to the framework exception handler.
     *
     * @param Throwable $e The uncaught exception or error.
     *
     * @return void
     * @internal
     * @see set_exception_handler()
     */
    public static function exceptions(Throwable $e): void
    {
        $previous = $e->getPrevious() ?? $e;

        if (!class_exists(LuminovaException::class)) {
            throw new \ErrorException(
                $e->getMessage(),
                (int) $e->getCode(),
                E_ERROR,
                $previous->getFile(),
                $previous->getLine(),
                $previous
            );
        }

        if (!$e instanceof LuminovaException) {
            $e = new RuntimeException(
                $e->getMessage(),
                $e->getCode(),
                $previous
            );
        }

        $e->setFile($previous->getFile())
            ->setLine($previous->getLine())
            ->handle();
    }

    /**
     * Throws an ErrorException with the given message, code, and optional file and line.
     *
     * If the file or line is not provided, the caller's location is resolved from the stack trace.
     *
     * @param string $message Error message.
     * @param string|int $code Error code (default: `ErrorCode::USER_NOTICE`).
     * @param string|null $file Optional file where the error occurred.
     * @param int|null $line Optional line where the error occurred.
     *
     * @return never
     * @throws ErrorException Always throws an ErrorException.
     */
    public static function trigger(
        string $message,
        string|int $code = ErrorCode::USER_NOTICE,
        ?string $file = null,
        ?int $line = null
    ): never 
    {
        if ($file === null || $line === null) {
            [$traceFile, $traceLine] = Tracer::trace(2);

            $file ??= $traceFile;
            $line ??= $traceLine;
        }

        throw new ErrorException(
            message: $message,
            code: $code,
            file: $file,
            line: $line
        );
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
    public static function findCode(
        string $message, 
        string|int $severity = ErrorCode::ERROR
    ): string|int
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

        return Runtime::LastErrorCode($severity);
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

        if (Runtime::isCommand()) {
            echo $error;
            exit(STATUS_ERROR);
        }

        Luminova::terminate(500, $error, retry: $retryAfter);
        exit(STATUS_ERROR);
    }

    /**
     * Converts special placeholders (<link> and <highlight>) into styled output.
     *
     * - Web contexts:
     *   - <link>URL</link> → HTML anchor element.
     *   - <highlight color="red">Text</highlight> → HTML span element.
     *
     * - CLI contexts:
     *   - <link>URL</link> → Underlined or clickable URL (ANSI when supported).
     *   - <highlight>Text</highlight> → Yellow highlighted text (ANSI when supported).
     *
     * Only valid HTTP(S) and mailto URLs are converted into links.
     *
     * @param string $message The message containing <link> or <highlight> placeholders.
     * @param bool   $newTab  Whether to open links in a new browser tab (default: true).
     *
     * @return string Returns the formatted message with placeholders replaced by styled output.
     *
     * @example - Example:
     * ```php
     * echo Error::prettify(
     *     'See <link>https://luminova.ng/</link> and <highlight color="red">important</highlight>'
     * );
     *
     * // Web output:
     * // See <a class="error-link" href="https://luminova.ng/" target="_blank" rel="noopener">https://luminova.ng/</a>
     * // and <span class="error-highlight" style="color:red">important</span>
     * ```
     */
    public static function prettify(
        string $message, 
        bool $newTab = true
    ): string
    {
        if ($message === '') {
            return '';
        }

        $isCommand = Runtime::isCommand();
        $isAnsi = false;
        $isColor = false;

        if ($isCommand) {
            Terminal::init();

            $isAnsi = Terminal::isAnsiSupported();
            $isColor = $isAnsi && Terminal::isColorSupported();
        }

        return preg_replace_callback(
            self::PRETTIFY_PATTERN,
            static function (array $matches) use ($newTab, $isCommand, $isAnsi, $isColor): string {
                // Handle links.
                if (!empty($matches[1]) && !empty($matches[2])) {
                    $url = $matches[2];

                    $isValidUrl = filter_var($url, FILTER_VALIDATE_URL) !== false
                        && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?: ''), 
                        ['http', 'https'], true);

                    $isMailto = str_starts_with(strtolower($url), 'mailto:')
                        && filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL) !== false;

                    if (!$isValidUrl && !$isMailto) {
                        return htmlspecialchars($matches[0], ENT_QUOTES, 'UTF-8');
                    }

                    $href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

                    if ($isCommand) {
                        if (!$isAnsi) {
                            return $href;
                        }

                        if (!$isColor) {
                            return "\033]8;;{$href}\033\\{$href}\033]8;;\033\\";
                        }

                        return "\033[4m{$href}\033[0m";
                    }

                    $target = $newTab ? ' target="_blank"' : '';

                    return "<a class=\"error-link\" href=\"{$href}\"{$target} rel=\"noopener\">{$href}</a>";
                }

                // Handle highlights.
                $text = htmlspecialchars($matches[4] ?? '', ENT_QUOTES, 'UTF-8');

                if ($isCommand) {
                    return $isColor ? "\033[33m{$text}\033[0m" : $text;
                }

                $color = $matches[3] ?? null;

                // Allow CSS color names, hexadecimal, RGB, HSL and modern color functions.
                if (
                    $color === null
                    || !preg_match(
                        '/^(?:[a-z]+|#[a-f0-9]{3,8}|(?:rgb|rgba|hsl|hsla)\([0-9.%\s,\/]+\))$/i',
                        $color
                    )
                ) {
                    $color = 'yellow';
                }

                return "<span class=\"error-highlight\" style=\"color:{$color}\">{$text}</span>";
            },
            $message
        );
    }

    /**
     * Dispatches the appropriate application shutdown lifecycle hook.
     *
     * During normal shutdown, invokes `onShutdown()` to allow the application
     * to perform final cleanup.
     *
     * During error shutdown, invokes `onErrorShutdown()` and uses its return
     * value to determine whether Luminova should continue its default error
     * handling.
     *
     * If the application hook throws an exception, Luminova continues with
     * its default shutdown handling.
     *
     * @param array{
     *     type:int,
     *     force?:bool,
     *     message:string,
     *     file:string,
     *     line:int
     * }|null $error Optional shutdown error information. A null value indicates
     *                normal application shutdown.
     *
     * @return bool Returns `true` to allow Luminova to continue its shutdown
     *              handling, or `false` when the application takes over.
     *
     * @internal
     */
    private static function shouldShutdown(?array $error = null): bool
    {
        try {
            /**
             * @var \Luminova\Foundation\Core\Application $app
             */
            $app = Kernel::resolve(Kernel::SERVICE_APPLICATION, shared: true);

            if ($error === null) {
                $app->trigger('onShutdown');

                return true;
            }

            return $app->trigger('onErrorShutdown', $error) !== false;
        } catch (Throwable $e) {
            if ($error === null) {
                return true;
            }

            throw $e;
        }  finally {
            self::$handlingShutdown = false;
        }
    }

    /**
     * Prints a formatted error message to the command line or browser output.
     *
     * Includes the error name, optional error code, message, file path, and line number.
     * Command-line output is written to STDERR, while browser output is formatted as HTML.
     *
     * @param string $message The error message to display.
     * @param string $name The name or type of the error.
     * @param string $file The file where the error occurred.
     * @param int $line The line number where the error occurred.
     * @param string|null $code (Optional) The error code to include in the output.
     *
     * @return void
     */
    private static function printLine(
        string $message,
        string $name,
        string $file,
        int $line,
        ?string $code = null,
        bool $isCommand = false
    ): void 
    {
        $name = ucfirst(strtolower($name));
        $label = ($code === null) 
            ? $name 
            : sprintf('%s (code: %s)', $name, $code);

        if ($isCommand) {
            $message = sprintf(
                '%s: %s in %s on line %d' . PHP_EOL,
                $label,
                $message,
                $file,
                $line
            );

            fwrite(
                STDERR,
                preg_replace('/<br\s*\/?>/i', PHP_EOL, strip_tags($message)) ?? $message
            );

            return;
        }

        printf(
            '<b>%s</b>: %s in %s on line %d<br>' . PHP_EOL,
            htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($file, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $line
        );
    }

    /**
     * Attempts to log a message using the framework logger or PHP error log.
     *
     * Uses the internal logger when boot initialization has started.
     * Falls back to a level-specific log file, then PHP's default error log
     * if the log directory or file cannot be accessed.
     *
     * @param string $level   The log severity level.
     * @param string $message The message to log.
     *
     * @return bool Returns true if the message was successfully logged.
     */
    public static function tryLog(
        string $level, 
        string $message, 
        array $context = []
    ): bool
    {
        $path = APP_BASE_PATH . '/writeable/logs/';

        if (defined('BOOT_WARMUP_STARTED')) {
            if (Logger::tryDispatch($level, $message, $context)) {
                return true;
            }

            $path = Luminova::root('/writeable/logs/');
        }

        $formatted = sprintf(
            "[%s] [%s] PHP %s%s%s",
            date(DATE_ATOM),
            strtoupper($level),
            $message,
            PHP_EOL,
            ($context !== []) 
                ? sprintf(' % [context] ', (string) json_encode(
                    $context, 
                    JSON_UNESCAPED_SLASHES 
                        | JSON_UNESCAPED_UNICODE 
                        | JSON_BIGINT_AS_STRING
                 ))
                : ''
        );

        if (
            !is_dir($path)
            && !@mkdir($path, 0775, true)
            && !is_dir($path)
        ) {
            return error_log($formatted);
        }

        if (error_log($formatted, 3, rtrim($path, '/\\') . "/{$level}.log")) {
            return true;
        }

        return error_log($formatted);
    }

    /**
     * Builds a standardized API error response.
     *
     * Luminova exceptions use their descriptive name and description.
     * Other exceptions fall back to their class name and exception message.
     * Detailed error information is only included outside production.
     *
     * @param Throwable|LuminovaException $error The exception or error being handled.
     *
     * @return array<string, mixed> Returns the formatted API error response.
     */
    public static function getApiErrorResponse(Throwable $error): array
    {
        $isLuminova = $error instanceof LuminovaException;

        $body = [
            'code'    => $error->getCode(),
            'title'   => $isLuminova 
                ? $error->getName() 
                : ErrorCode::getName($error->getCode()),
            'message' => 'Something went wrong, please check your server error logs for more details.',
        ];

        if (!defined('IS_ENV_REGISTERED')) {
            return $body;
        }

        if (PRODUCTION) {
            return $body;
        }

        $body['message'] = $isLuminova 
            ? $error->getDescription() : $error->getMessage();
        $body['file'] = $error->getFile();
        $body['line'] = $error->getLine();

        if (SHOW_DEBUG_BACKTRACE) {
            $body['trace'] = $isLuminova
                ? $error->getBacktrace()
                : $error->getTrace();
        }
    
        $body['environment'] = [
            'php_version' => PHP_VERSION,
            'version'     => Luminova::VERSION
        ];

        return $body;
    }
}