<?php 
/**
 * Luminova Framework static logger class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Logger;

use \Psr\Log\LoggerInterface;
use \Luminova\Logger\LogLevel;
use \Luminova\Logger\NovaLogger;
use \App\Config\Logger as LoggerConfig;
use \Luminova\Functions\Func;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\InvalidArgumentException;
use \Throwable;

/**
 * Static logger class methods.
 *
 * @method static void emergency(string $message, array $context = [])  Logs a system emergency (highest severity).
 * @method static void alert(string $message, array $context = [])      Logs an alert that requires immediate action.
 * @method static void critical(string $message, array $context = [])   Logs a critical condition that requires prompt attention.
 * @method static void error(string $message, array $context = [])      Logs an error that prevents execution but does not require immediate shutdown.
 * @method static void warning(string $message, array $context = [])    Logs a warning about a potential issue.
 * @method static void notice(string $message, array $context = [])     Logs a normal but significant event.
 * @method static void info(string $message, array $context = [])       Logs general informational messages.
 * @method static void debug(string $message, array $context = [])      Logs debugging information for developers.
 * @method static void phpError(string $message, array $context = [])   Logs a PHP runtime error.
 * @method static void php(string $message, array $context = [])        Alias for `phpError`, logs PHP-related issues.
 */
final class Logger
{
    /**
     * PSR logger interface.
     * 
     * @var LoggerInterface|null $logger
     */
    private static ?LoggerInterface $logger = null;

    /**
     * Initialize logger instance.
     */
    public function __construct(){}

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
     * Get the shared instance of the application's PSR-compliant logger.
     *
     * If no logger is specified in `App\Config\Logger->getLogger()`, the default `NovaLogger` is used.
     *
     * @return LoggerInterface|NovaLogger Return the active logger instance.
     */
    public static function getLogger(): LoggerInterface
    {
        if(!self::$logger instanceof LoggerInterface){
            self::$logger = (new LoggerConfig())->getLogger() ?? new NovaLogger();
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
        self::getLogger()->log(
            ($level === 'phpError') ? LogLevel::PHP : $level, 
            $message, 
            $context
        );
    }

    /**
     * Logs performance and metric data.
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
     * Dispatches a log message to a specified destination (file, email, or remote server) asynchronously.
     * 
     * The destination is determined by the provided parameter (`$to`) which can be a log level, email address, 
     * URL, Telegram bot chat Id or null. Email and remote logging are handled asynchronously. By default, in development, logs 
     * are written to a file unless an explicit email address or URL is specified.
     * 
     * In production, if no destination is provided, the method checks for default email or remote URL 
     * configurations in the environment file (`logger.mail.logs` or `logger.remote.logs`).
     * 
     * @param string|int|null $to The destination for the log message (log level, email address, telegram bot chat Id, URL, or NULL).
     * @param string $message The message to be logged.
     * @param array<string|int,mixed> $context Optional additional data to provide context for the log.
     *
     * @return void
     * @throws InvalidArgumentException If the provided destination is invalid (not a valid log level, 
     *                                  email address, or URL).
     * @throws RuntimeException If email or remote logging is attempted with an invalid logger class or 
     *                           the logger does not implement the PSR LoggerInterface.
     */
    public static function dispatch(string|int|null $to, string $message, array $context = []): void 
    {
        if(trim($message) === ''){
            return;
        }

        $isFile = ($to && LogLevel::has($to));
        
        if($isFile && !LogLevel::isCritical($to)){
            self::log($to, $message, $context);
            return;
        }

        $level = $isFile ? $to : LogLevel::ALERT;
        $to = self::getLogDestination($to ?? '');
        
        if ($to && LogLevel::has($to)) {
            self::log($to, $message, $context);
            return;
        }

        $valid = true;

        if ($to && Func::isEmail($to)) {
            self::assertInterface('Email dispatch');
            self::getLogger()->setLevel($level)->mail($to, $message, $context); 
        } elseif($to && Func::isUrl($to)) {
            self::assertInterface('Remote dispatch');
            self::getLogger()->setLevel($level)->remote($to, $message, $context);
        } elseif($to && self::isTelegramChatId($to)) {
            self::assertInterface('Telegram dispatch');
            self::getLogger()->setLevel($level)->telegram($to, $message, $context);
        }else{
            $valid = false;
        }

        self::getLogger()->setLevel(LogLevel::ALERT);

        if(!$valid){
            throw new InvalidArgumentException(sprintf(
                'Invalid destination "%s" provided. A valid log level, URL, email address or telegram bot chat id is required. %s%s%s',
                $to,
                'For auto-dispatch logging, ensure you specify a valid email address using `logger.mail.logs` ',
                ', a remote URL using `logger.remote.logs` ',
                'or a telegram both chat ID `telegram.bot.chat.id` in your environment configuration file.'
            ));
        }
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
        if(!Func::isUrl($url)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid logger destination: "%s" was provided. A valid URL is required.', 
                $url
            ));
        }

        self::getLogger()->remote($url, $message, $context);
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

        if (!Func::isEmail($email)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid logger destination: "%s" was provided. A valid email address is required.', 
                $email
            ));
        }

        self::getLogger()->mail($email, $message, $context); 
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

        self::getLogger()->telegram($chatId, $message, $context); 
    }

    /**
     * Determines the appropriate log destination based on the environment and configuration.
     *
     * @param string $to The initial log destination.
     *
     * @return string The determined log destination. In production, it may be an email
     *                address, a remote URL, or the original input. In non-production
     *                environments, it returns the original input.
     */
    private static function getLogDestination(string $to): string
    {
       return PRODUCTION 
            ? (env('logger.mail.logs', false) 
                ?: (env('logger.remote.logs', false) 
                ?: (env('telegram.bot.chat.id', false) ?: $to))
             )
            : $to;
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
        return $chatId && preg_match('/^(-100\d{10,13}|\d{5,12})$/', (string) $chatId) === 1;
    }

    /**
     * Asserts that logger instance is a NovaLogger class.
     * 
     * @param string $prefix Error message prefix.
     * @return void
     */
    private static function assertInterface(string $prefix): void 
    {
        if(self::getLogger() instanceof NovaLogger){
            return;
        }

        throw new RuntimeException(sprintf(
            '%s logging requires %s, your provided logger interface: %s is not supported.', 
            $prefix.
            NovaLogger::class,
            self::$logger::class
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
        if(self::getLogger() instanceof LoggerInterface){
            return;
        }

        throw new RuntimeException(sprintf(
            'Invalid Logger Interface: "%s", Your logger class in configuration: "%s", must implement "%s".', 
            self::$logger::class,
            LoggerConfig::class,
            LoggerInterface::class,
        ), RuntimeException::NOT_SUPPORTED);
    }
}