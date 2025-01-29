<?php 
/**
 * Luminova Framework Logger class.
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
use \Luminova\Logger\LoggerTrait;
use \App\Config\Logger as LoggerConfig;
use \Luminova\Functions\Func;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\InvalidArgumentException;

class Logger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * PSR logger interface.
     * 
     * @var LoggerInterface|null $logger
     */
    protected static ?LoggerInterface $logger = null;

    /**
     * Initialize logger instance.
     */
    public function __construct(){}

    /**
     * Get shared instance of  your application PSR logger class.
     * 
     * If no logger was specified in `App\Config\Logger->getLogger()` method, default NovaLogger will be returned.
     * 
     * @return LoggerInterface|NovaLogger Return instance of app logger class in-use.
     */
    public static function getLogger(): LoggerInterface
    {
        if(!self::$logger instanceof LoggerInterface){
            self::$logger = (new LoggerConfig())->getLogger() ?? new NovaLogger();
        }

        return self::$logger;
    }

    /**
     * Log a message at a specified log level.
     *
     * @param string $level The log level (e.g., "emergency," "error," "info").
     * @param string $message The log message.
     * @param array $context Additional context data (optional).
     *
     * @return void
     * @throws RuntimeException If logger does not implement PSR LoggerInterface.
     */
    public function log($level, $message, array $context = []): void
    {
        self::write($level, $message, $context);
    }

    /**
     * Dispatches a log message to a specified destination (file, email, or remote server) asynchronously.
     * 
     * The destination is determined by the provided parameter (`$to`) which can be a log level, email address, 
     * URL, or null. Email and remote logging are handled asynchronously. By default, in development, logs 
     * are written to a file unless an explicit email address or URL is specified.
     * 
     * In production, if no destination is provided, the method checks for default email or remote URL 
     * configurations in the environment file (`logger.mail.logs` or `logger.remote.logs`).
     * 
     * @param string|null $to The destination for the log message (log level, email address, URL, or NULL).
     * @param string $message The message to be logged.
     * @param array $context Optional additional data to provide context for the log.
     *
     * @return void
     * @throws InvalidArgumentException If the provided destination is invalid (not a valid log level, 
     *                                  email address, or URL).
     * @throws RuntimeException If email or remote logging is attempted with an invalid logger class or 
     *                           the logger does not implement the PSR LoggerInterface.
     */
    public static function dispatch(string|null $to, string $message, array $context = []): void 
    {
        if(trim($message) === ''){
            return;
        }

        $isFile = ($to && LogLevel::has($to));
        
        if($isFile && !LogLevel::isCritical($to)){
            self::write($to, $message, $context);
            return;
        }

        $level = $isFile ? $to : LogLevel::ALERT;
        $to = self::getLogDestination($to ?? '');
        
        if ($to && LogLevel::has($to)) {
            self::write($to, $message, $context);
            return;
        }

        $valid = true;

        if ($to && Func::isEmail($to)) {
            self::assertInterface('Email dispatch');
            self::getLogger()->setLevel($level)->mail($to, $message, $context); 
        } elseif($to && Func::isUrl($to)) {
            self::assertInterface('Remote dispatch');
            self::getLogger()->setLevel($level)->remote($to, $message, $context);
        }else{
            $valid = false;
        }

        self::getLogger()->setLevel(LogLevel::ALERT);

        if(!$valid){
            throw new InvalidArgumentException(sprintf(
                'Invalid destination "%s" provided. A valid log level, URL, or email address is required. %s%s',
                $to,
                'For auto-dispatch logging, ensure you specify a valid email address using `logger.mail.logs` ',
                'or a remote URL using `logger.remote.logs` in your environment configuration file.'
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
     * @param array $context Additional context data to be included in the log message (optional).
     *
     * @return void
     *
     * @throws InvalidArgumentException If the provided URL is invalid.
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
     * @param array $context Additional context data to be included in the log message (optional).
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
            ? (env('logger.mail.logs', false) ?: (env('logger.remote.logs', false) ?: $to))
            : $to;
    }

    /**
     * Write log a message at a specified log level.
     *
     * @param string $level The log level (e.g., "emergency," "error," "info").
     * @param string $message The log message.
     * @param array $context Additional context data (optional).
     *
     * @return void
     * @throws RuntimeException If logger does not implement LoggerInterface.
     */
    private static function write(string $level, string $message, array $context = []): void
    {
        self::assertPsrLogger();
        self::getLogger()->log($level, $message, $context);
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