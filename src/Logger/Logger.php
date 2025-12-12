<?php 
/**
 * Luminova Framework static logger class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Logger;

use \Throwable;
use \Luminova\Luminova;
use \Luminova\Http\Request;
use \Psr\Log\LoggerInterface;
use \Luminova\Utility\Helpers;
use \App\Config\Logger as Config;
use \Luminova\Logger\{NovaLogger, LogLevel};
use \Luminova\Exceptions\{RuntimeException, InvalidArgumentException};

/**
 * Static logger methods for system and application events.
 *
 * @method static void emergency(string $message, array $context = []) Logs a critical system failure (highest severity).
 * @method static void alert(string $message, array $context = []) Logs an alert requiring immediate action.
 * @method static void critical(string $message, array $context = []) Logs a serious condition requiring prompt attention.
 * @method static void error(string $message, array $context = []) Logs a runtime error that affects execution.
 * @method static void warning(string $message, array $context = []) Logs a potential problem or risk.
 * @method static void notice(string $message, array $context = []) Logs a normal but noteworthy event.
 * @method static void info(string $message, array $context = []) Logs general informational messages.
 * @method static void debug(string $message, array $context = []) Logs developer-focused debugging information.
 * @method static void phpError(string $message, array $context = []) Logs a PHP runtime error.
 * @method static void php(string $message, array $context = []) Alias for `phpError`, logs PHP-related issues.
 */
final class Logger
{
    /**
     * Telegram bot token.
     * 
     * @var string|null $telegramToken
     */
    private static ?string $telegramToken = null;

    /**
     * Logger class;
     *
     * @var LoggerInterface|NovaLogger|null $logger
     */
    private static ?LoggerInterface $logger = null;

    /**
     * Prevent initializing logger class.
     */
    private function __construct(){}

    /**
     * Support for other custom log levels.
     *
     * @param string $method The log level as method name to call (e.g., `$logger->error(...)`, `$logger->info(...)`).
     * @param array{0:string,1:array{0:string,1:array<string|int,mixed>}} $arguments Argument holding the log message and optional context.
     * 
     * @return void 
     * @throws InvalidArgumentException If an invalid logger method-level is called.
     * @throws RuntimeException If logger does not implement PSR LoggerInterface.
     */
    public function __call(string $method, array $arguments = [])
    {
        self::log($method, ...$arguments);
    }

    /**
     * Static logger helper.
     *
     * @param string $method The log level as method name to call (e.g., `Logger::error(...)`, `Logger::info(...)`).
     * @param array{0:string,1:array{0:string,1:array<string|int,mixed>}} $arguments Argument holding the log message and optional context.
     *
     * @return void
     * @throws InvalidArgumentException If an invalid logger method-level is called.
     * @throws RuntimeException If logger does not implement PSR LoggerInterface.
     */
    public static function __callStatic(string $method, array $arguments)
    {
        self::log($method, ...$arguments);
    }

    /**
     * Return the active PSR-compliant logger instance.
     *
     * Falls back to the default `NovaLogger` if no custom logger is configured in
     * `App\Kernel->getLogger()`.
     *
     * @return LoggerInterface|NovaLogger Return the active logger instance.
     */
    public static function logger(): LoggerInterface
    {
        if (!self::$logger instanceof LoggerInterface) {
            self::$logger = Luminova::kernel()->getLogger() ?? new NovaLogger();
        }

        return self::$logger;
    }

    /**
     * Write log a message at a specified log level.
     *
     * @param string $level The log level (e.g., `LogLevel::INFO`, `emergency`).
     * @param string $message The log message.
     * @param array<string|int,mixed> $context Additional context data (optional).
     *
     * @return void
     * @throws RuntimeException If logger does not implement PSR LoggerInterface.
     */
    public static function log(string $level, string $message, array $context = []): void
    {
        self::assertPsrLogger();
       
        self::logger()->log(
            ($level === 'phpError') ? LogLevel::PHP : $level, 
            $message, 
            $context + self::getAutoContext()
        );
    }

    /**
     * Logs performance metric data.
     *
     * @param string $message The profiling data to log.
     * @param array<string|int,mixed> $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function metrics(string $data, array $context = []): void
    {
        self::log(LogLevel::METRICS, $data, $context);
    }

    /**
     * Logs an exception with additional context.
     *
     * @param Throwable|string $message The exception message to log.
     * @param array<string|int,mixed> $context Additional context data (optional).
     * 
     * @return void 
     */
    public static function exception(Throwable|string $message, array $context = []): void
    {
        self::log(
            LogLevel::EXCEPTION, 
            $message instanceof Throwable ? $message->getMessage() : $message, 
            $context
        );
    }

    /**
     * Generates a log entry with an ISO 8601 timestamp (including microseconds).
     * 
     * Useful for logging multiple messages that share the same severity level, especially in loops or batch operations. 
     * Instead of logging each entry separately, you can construct multiple log entries and log them all at once for better efficiency.
     *
     * @param string $level The log severity level (e.g., 'INFO', 'ERROR').
     * @param string $message The main log message.
     * @param array<string,mixed> $context Optional contextual data for the log entry.
     *
     * @return string Return the formatted log entry in plain text, ending with a newline.
     */
    public static function entry(
        string $level, 
        string $message, 
        array $context = [],
    ): string
    {
        if(self::logger() instanceof NovaLogger){
            return self::$logger->message($level, $message, $context) . PHP_EOL;
        }

        return NovaLogger::formatMessage($level, $message, '', $context) . PHP_EOL;
    }

    /**
     * Sets a debug trace to the active logger, if tracing is supported.
     *
     * This method forwards the given trace data to the internal logger
     * only when the logger instance supports tracing (NovaLogger).
     * If tracing is unavailable or unsupported, the call is safely ignored.
     *
     * @param array $trace Debug trace data (stack trace, context, metadata).
     *
     * @return bool Returns true when the trace was accepted by the logger,
     *              false when tracing is not supported or no logger is available.
     */
    public static function tracer(array $trace): bool
    {
        if(self::logger() instanceof NovaLogger){
            self::$logger->setTracer($trace);
            return true;
        }

        return false;
    }

    /**
     * Dispatch a log message to a local or remote destination.
     *
     * The destination is resolved from `$to`, which may be a log level, email
     * address, URL, Telegram chat ID, or `null` to detected from `env`.
     *
     * - If `$to` is a log level, the message is written locally unless the level
     *   is marked as dispatchable.
     * - If `$to` is null, the method falls back to configured remote destinations
     *   (`logger.mail.logs`, `logger.remote.logs`, or Telegram settings).
     * - Email, remote URL, and Telegram dispatches are sent asynchronously.
     *
     * If no valid remote destination is resolved, the message is logged locally.
     * An exception is thrown only when an explicitly invalid destination is
     * provided.
     *
     * @param string|int|null $to Log destination: (e.g, log level, email address, URL, Telegram chat ID or null).
     * @param string $message The log message.
     * @param array<string|int,mixed> $context Additional context data for the log entry.
     *
     * @return void
     * @throws InvalidArgumentException When `$to` is provided but does not resolve to a valid destination.
     * @throws RuntimeException When a dispatch logger is misconfigured or 
     *         does not implement the required logger interface.
     */
    public static function dispatch(string|int|null $to, string $message, array $context = []): void 
    {
        if(trim($message) === ''){
            return;
        }

        $level = LogLevel::CRITICAL;

        if ($to !== null && LogLevel::has($to)) {
            if (!self::isDispatchable($to)) {
                self::log($to, $message, $context);
                return;
            }

            $level = $to;
        }

        $to = self::getRemoteDestination($to ?? LogLevel::ERROR);
        
        if (LogLevel::has($to)) {
            self::log($to, $message, $context);
            return;
        }

        $isValidDestination = false;

        if ($to) {
            $isValidDestination = true;

            if(self::isTelegramChatId($to)) {
                self::assertInterface('Telegram dispatch');
                self::logger()
                    ->setLevel($level)
                    ->telegram($to, self::getTelegramToken(), $message, $context + self::getAutoContext());
            } elseif (Helpers::isEmail($to)) {
                self::assertInterface('Email dispatch');
                self::logger()
                    ->setLevel($level)
                    ->mail($to, $message, $context + self::getAutoContext()); 
            } elseif(Helpers::isUrl($to)) {
                self::assertInterface('Remote dispatch');
                self::logger()
                    ->setLevel($level)
                    ->remote($to, $message, $context + self::getAutoContext());
            } else{
                $isValidDestination = false;
            }
        }
        
        if(!$isValidDestination){
            throw new InvalidArgumentException(sprintf(
                'Invalid log destination "%s". Expected a log level, email, URL, or Telegram chat ID. ' .
                'Configure "logger.mail.logs", "logger.remote.logs", or Telegram bot credentials in your environment.',
                (string) $to
            ));
        }

        self::logger()->setLevel(LogLevel::DEBUG);
    }

    /**
     * Sends a log message to a remote server via HTTP POST.
     *
     * This function sends a log message to a specified remote server using HTTP POST.
     * The function validates the URL and ensures that the log message is not empty.
     * If the URL is invalid or the log message is empty, the function returns without performing any action.
     *
     * @param string $url The URL of the remote server to send the log message to.
     * @param string $message The log message to be sent.
     * @param array<string|int,mixed> $context Additional context data to be included in the log message (optional).
     *
     * @return void
     *
     * @throws InvalidArgumentException If the provided URL is invalid.
     * @throws RuntimeException         If the logger doesn't support remote logging functionality.
     */
    public static function remote(string $url, string $message, array $context = []): void 
    {
        if(!$url || trim($message) === ''){
            return;
        }

        self::assertInterface('Remote');
        
        if(!Helpers::isUrl($url)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid logger destination: "%s" was provided. A valid URL is required.', 
                $url
            ));
        }

        self::logger()->remote($url, $message, $context + self::getAutoContext());
    }

    /**
     * Sends a log message via email.
     *
     * This method validates the email address and message, then sends the log message
     * to the specified email address using the configured logger.
     *
     * @param string $email The email address to send the log message to.
     * @param string $message The log message to be sent.
     * @param array<string|int,mixed> $context Additional context data to be included in the log message (optional).
     *
     * @return void
     *
     * @throws InvalidArgumentException If the provided email address is invalid.
     * @throws RuntimeException         If the logger doesn't support email functionality.
     */
    public static function mail(string $email, string $message, array $context = []): void 
    {
        if(!$email || trim($message) === ''){
            return;
        }

        self::assertInterface('Email');

        if (!Helpers::isEmail($email)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid logger destination: "%s" was provided. A valid email address is required.', 
                $email
            ));
        }

        self::logger()->mail($email, $message, $context + self::getAutoContext()); 
    }

    /**
     * Sends a log message to a Telegram chat using the Telegram Bot API.
     *
     * @param string|int $chatId The chat ID to send the message to.
     * @param string $message The log message to send.
     * @param array<string|int,mixed> $context Additional context data to be included in the log message (optional).
     *
     * @return void
     * @throws InvalidArgumentException If the provided chat Id is invalid.
     * @throws RuntimeException         If the logger doesn't support telegram logging functionality.
     */
    public static function telegram(string|int $chatId, string $message, array $context = []): void 
    {
        if(!$chatId || trim($message) === ''){
            return;
        }

        self::assertInterface('Telegram');

        if (!self::isTelegramChatId($chatId)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid logger destination: "%s" was provided. A valid telegram both chat Id is required.', 
                $chatId
            ));
        }

        self::logger()->telegram($chatId, self::getTelegramToken(), $message, $context + self::getAutoContext()); 
    }

    /**
     * Determine whether a log entry should be dispatched.
     * 
     * @param string|int $to Log level name or numeric severity.
     *
     * @return bool Returns true if the log level should be dispatched,
     *              false otherwise.
     */
    private static function isDispatchable(string|int $to): bool
    {
        $dispatches = (array) env('logger.dispatch.levels', []);

        if (!$dispatches) {
            return LogLevel::isCritical($to);
        }

        return in_array($to, $dispatches, true);
    }

    /**
     * Determines the appropriate log destination based on the environment.
     * 
     * In production, prioritizes:
     * 1. Email log address (`logger.mail.logs`)
     * 2. Remote log URL (`logger.remote.logs`)
     * 3. Telegram chat ID (`logger.telegram.bot.chat.id`)
     * Falls back to the provided destination if none are set.
     *
     * @param string $to The fallback destination provided.
     *
     * @return string Return the resolved destination.
     */
    private static function getRemoteDestination(string $to): string
    {
        return env('logger.mail.logs')
            ?: env('logger.remote.logs')
            ?: env('logger.telegram.bot.chat.id')
            ?: $to;
    }

    /**
     * Automatically builds a context array from the request, based on configured
     * header and body field names in Config.
     *
     * This method helps enrich log entries by extracting an identifier (e.g., user ID,
     * API key, or username) from either a request header or body field, if configured.
     *
     * @return array Return an associative context array with extracted values, prefixed with `__`.
     */
    private static function getAutoContext(): array
    {
        $header = Config::$contextHeaderName ?? null;
        $field = Config::$contextFieldName ?? null;
        $context = [];
        
        if ($header || $field) {
            $request = Request::getInstance();

            if ($field) {
                $context["__{$field}"] = $request->input($field);
            }

            if ($header) {
                $context["__{$header}"] = $request->header->get($header) ?? $request->server->get($header);
            }
        }

        return $context;
    }

    /**
     * Get the telegram bot token.
     * 
     * @return string|null Return telegram token or null.
     */
    private static function getTelegramToken(): ?string
    {
        return self::$telegramToken ??= env('logger.telegram.bot.token');
    }

    /**
     * Validates if the given input is a valid Telegram chat ID.
     *
     * This method checks if the provided chat ID is in the correct format for Telegram
     * and if a Telegram bot token is set in the environment configuration.
     *
     * @param string|int $chatId The chat ID to validate. Can be a string or an integer.
     *
     * @return bool Returns true if the chat ID is valid and a Telegram bot token is set,
     *              false otherwise.
     */
    private static function isTelegramChatId(string|int $chatId): bool
    {
        if(!self::getTelegramToken()){
            return false;
        }

        return $chatId && preg_match('/^\d{5,12}$|^-100\d{10,13}$|^-\d{5,12}$/', (string) $chatId) === 1;
    }

    /**
     * Asserts that logger instance is a NovaLogger class.
     * 
     * @param string $prefix Error message prefix.
     * @return void
     */
    private static function assertInterface(string $prefix): void 
    {
        if(self::logger() instanceof NovaLogger){
            return;
        }

        throw new RuntimeException(sprintf(
            '%s logging requires %s, your provided logger interface: %s is not supported.', 
            $prefix.
            NovaLogger::class,
            get_class(self::$logger ?? '')
        ), RuntimeException::NOT_SUPPORTED);
    }

    /**
     * Asserts that the current logger instance implements the PSR LoggerInterface.
     *
     * This method checks if the logger obtained from getLogger() implements
     * the PSR LoggerInterface. If it doesn't, a RuntimeException is thrown.
     *
     * @throws RuntimeException If the logger does not implement LoggerInterface.
     *                          The exception includes details about the invalid logger class
     *                          and the required interface.
     * @return void
     */
    private static function assertPsrLogger(): void 
    {
        if(self::logger() instanceof LoggerInterface){
            return;
        }

        throw new RuntimeException(sprintf(
            'Invalid Logger Interface: "%s", Your logger class must implement "%s".', 
            get_class(self::$logger ?? ''),
            LoggerInterface::class,
        ), RuntimeException::NOT_SUPPORTED);
    }
}