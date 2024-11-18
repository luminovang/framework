<?php 
/**
 * Luminova Framework
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
     * Initialize logger instance 
     */
    public function __construct(){}

    /**
     * Get shared instance of PSR logger class.
     * 
     * @return LoggerInterface|NovaLogger Return instance of logger class inuse.
     */
    public static function getLogger(): LoggerInterface
    {
        if(static::$logger instanceof LoggerInterface){
            return static::$logger;
        }

        return static::$logger = (new LoggerConfig())->getLogger() ?? new NovaLogger();
    }

    /**
     * Log a message at a specified log level.
     *
     * @param string $level The log level (e.g., "emergency," "error," "info").
     * @param string $message The log message.
     * @param array $context Additional context data (optional).
     *
     * @return void
     * @throws InvalidArgumentException If logger does not implement LoggerInterface.
     */
    public function log($level, $message, array $context = []): void
    {
        if (static::getLogger() instanceof LoggerInterface) {
            static::getLogger()->log($level, $message, $context);
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Logger: %s must implement Psr\Log\LoggerInterface', 
            static::getLogger()::class
        ));
    }

    /**
     * Sends a log message to a specified destination, either asynchronously or synchronously.
     * The method validates the logging destination and routes the log based on its type 
     * (log level, email address, or URL). Email and network logging are performed asynchronously by default.
     *
     * @param string $to The destination for the log (log level, email address, or URL).
     * @param string $message The message to log.
     * @param array $context Additional context data (optional).
     *
     * @return void
     * @throws InvalidArgumentException If an invalid logging destination is provided.
     * @throws RuntimeException If called logging for network or mail destination for non-novalogger class.
     */
    public function dispatch(
        string $to, 
        string $message, 
        array $context = []
    ): void {

        if(!$to || trim($message) === ''){
            return;
        }

        $level = $to;
        $to = PRODUCTION 
            ? (env('logger.mail.logs', false) ?: (env('logger.remote.logs', false) ?: $to))
            : $to;

        if (LogLevel::has($to)) {
            $this->log($to, $message, $context);
            return;
        }

        if(!static::getLogger() instanceof NovaLogger){
            throw new RuntimeException(sprintf(
                'Email or Remote network logging requires %s, you logger interface: %s is not supported.', 
                NovaLogger::class,
                static::getLogger()::class
            ), RuntimeException::NOT_SUPPORTED);
        }

        $level = LogLevel::has($level) 
            ? $level 
            : LogLevel::ALERT;

        if (Func::isEmail($to)) {
            static::getLogger()->setLevel($level)->mail($to, $message, $context);
            return;
        } 
        
        if(Func::isUrl($to)) {
            static::getLogger()->setLevel($level)->remote($to, $message, $context);
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid logger destination: %s was provided. A valid log level, URL, or an email address is required.', 
            $to
        ));
    }
}