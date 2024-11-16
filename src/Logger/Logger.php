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
use \App\Config\Preference;
use \Luminova\Functions\Func;
use \Luminova\Email\Mailer;
use \Luminova\Http\Network;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\InvalidArgumentException;
use \Throwable;
use \Fiber;

class Logger implements LoggerInterface
{
    /**
     * @var LoggerInterface $logger
     */
    protected static ?LoggerInterface $logger = null;

    /**
     * Initialize logger instance 
     */
    public function __construct(){}

    /**
     * Get shared instance of PSR logger class.
     * 
     * @return LoggerInterface Return instance of logger class inuse.
     */
    public static function getLogger(): LoggerInterface
    {
        if(static::$logger instanceof LoggerInterface){
            return static::$logger;
        }

        return static::$logger = (new Preference())->getLogger() ?? new NovaLogger();
    }
    
    /**
     * Log an emergency message.
     *
     * @param string $message The emergency message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public function emergency($message, array $context = [])
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Log an alert message.
     *
     * @param string $message The alert message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public function alert($message, array $context = [])
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Log a critical message.
     *
     * @param string $message The critical message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public function critical($message, array $context = [])
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message The error message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public function error($message, array $context = [])
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message The warning message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public function warning($message, array $context = [])
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log a notice message.
     *
     * @param string $message The notice message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public function notice($message, array $context = [])
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message The info message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public function info($message, array $context = [])
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message The debug message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public function debug($message, array $context = [])
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log an exception message.
     *
     * @param string $message The EXCEPTION message to log.
     * @param array $context Additional context data (optional).
     */
    public function exception($message, array $context = []): void
    {
        $this->log(LogLevel::EXCEPTION, $message, $context);
    }

    /**
     * Log an php message.
     *
     * @param string $message The php message to log.
     * @param array $context Additional context data (optional).
     */
    public function php($message, array $context = []): void
    {
        $this->log(LogLevel::PHP, $message, $context);
    }

    /**
     * Log an performance metric.
     *
     * @param string $message The php message to log.
     * @param array $context Additional context data (optional).
     */
    public function metrics($message, array $context = []): void
    {
        $this->log(LogLevel::METRICS, $message, $context);
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

        throw new InvalidArgumentException('Logger must implement Psr\Log\LoggerInterface');
    }

    /**
     * Sends a log message to a specified destination, either asynchronously or synchronously.
     * The method validates the logging destination and routes the log based on its type 
     * (log level, email address, or URL). Email and network logging are performed asynchronously by default.
     *
     * @param string $to The destination for the log (log level, email address, or URL).
     * @param string $message The message to log.
     * @param array $context Additional context data (optional).
     * @param bool $asynchronous Whether to execute file logging asynchronously (default: false).
     *
     * @return void
     * @throws InvalidArgumentException If an invalid logging destination is provided.
     */
    public function dispatch(
        string $to, 
        string $message, 
        array $context = [],
        bool $asynchronous = false
    ): void {

        if(!$to || trim($message) === ''){
            return;
        }

        if (NovaLogger::has($to)) {
            if ($asynchronous) {
                $fiber = new Fiber(function () use ($to, $message, $context) {
                    try {
                        $this->log($to, $message, $context);
                    } catch (Throwable $e) {
                        $this->_error(
                            'Logging', $e->getMessage(), 
                            $message, $context
                        );
                    }
                });
                $fiber->start();
                return;
            }

            $this->log($to, $message, $context);
            return;
        }

        if (Func::isEmail($to)) {
            $this->mailLog($to, $message, $context);
            return;
        } 
        
        if(Func::isUrl($to)) {
            $this->networkLog($to, $message, $context);
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid logger destination: %s was provided. A valid log level, URL, or an email address is required.', 
            $to
        ));
    }

    /**
     * Logs an email message asynchronously.
     * This method prepares and sends an error log via email using the configured Mailer.
     * If sending fails, it logs an error message.
     *
     * @param string $email The recipient email address.
     * @param string $message The message to log.
     * @param array $context Additional context data (optional).
     *
     * @return void
     * > Note: If error occurs during mailing log, file logger will be used instead.
     * > If exception occurs during mailing log, file logger with level `exception` be used.
     */
    public function mailLog(string $email, string $message, array $context = []): void 
    {
        if(!$email || !Func::isEmail($email)){
            $this->log(LogLevel::CRITICAL, sprintf('Invalid mail logger email address: %s', $email), [
                'originalMessage' => $message,
                'originalContext' => $context
            ]);
            return;
        }

        $body = NovaLogger::message(LogLevel::ERROR, $message, $context);
        $subject = sprintf('%s (v%.1f) Error Logged: %s', APP_NAME, APP_VERSION);

        $fiber = new Fiber(function () use ($email, $subject, $body, $message, $context) {
            try {
                if (!Mailer::to($email)->subject($subject)->body($body)->send()) {
                    $this->log(LogLevel::ERROR, "Failed to send email log: {$message}", $context);
                }
            } catch (AppException $e) {
                $this->_error(
                    'Mailer', $e->getMessage(), 
                    $message, $context
                );
            } catch (Throwable $fe) {
                $this->_error(
                    'Fiber', $fe->getMessage(), 
                    $message, $context
                );
            }
        });

        $fiber->start();
    }

    /**
     * Logs a URL message asynchronously.
     * This method sends an error log to a specified URL endpoint with details about the error.
     * If sending fails, it logs an error message.
     *
     * @param string $url_endpoint The URL to which the log should be sent.
     * @param string $message The message to log.
     * @param array $context Additional context data (optional).
     *
     * @return void
     * > Note: If error occurs during network log, file logger will be used instead.
     * > If exception occurs during network log, file logger with level `exception` be used.
     */
    public function networkLog(string $url_endpoint, string $message, array $context = []): void 
    {
        if(!$url_endpoint || !Func::isUrl($url_endpoint)){
            $this->log(LogLevel::CRITICAL, sprintf('Invalid network logger URL endpoint: %s', $url_endpoint), [
                'originalMessage' => $message,
                'originalContext' => $context
            ]);
            return;
        }

        $payload = [
            'title'    => sprintf('%s (v%.1f) Error Logged: %s', APP_NAME, APP_VERSION),
            'host'     => HOST_NAME,
            'details'  => NovaLogger::message(LogLevel::ERROR, $message),
            'context'  => $context,
            'version'  => APP_VERSION,
        ];

        $fiber = new Fiber(function () use ($url_endpoint, $payload, $message) {
            try {
                $response = (new Network())->post($url_endpoint, ['body' => $payload]);
                if ($response->getStatusCode() !== 200) {
                    $this->log(LogLevel::ERROR, 
                        sprintf(
                            'Failed to send error to remote server: %s | Response: %s', 
                            $payload['details'], 
                            $response->getContents()
                        ), 
                        $payload['context']
                    );
                }
            } catch (AppException $e) {
                $this->_error(
                    'Network', $e->getMessage(), 
                    $message, $payload['context']
                );
            } catch (Throwable $fe) {
                $this->_error(
                    'Unexpected', $fe->getMessage(), 
                    $message, $payload['context']
                );
            }
        });

        $fiber->start();
    }

    /**
     * Logs an exception error message with original log information.
     *
     * @param string $from     The source or context where the error originated.
     * @param string $error    The error message or description.
     * @param string $message  The original message that was being logged when the error occurred (optional).
     * @param array  $context  Additional contextual data related to the original log attempt (optional).
     *
     * @return void
     */
    private function _error(string $from, string $error, string $message = '', array $context = []): void
    {
        $this->log(LogLevel::EXCEPTION, sprintf('%s Exception: %s', $from, $error), 
        [
            'originalMessage' => $message,
            'originalContext' => $context
        ]);
    }
}