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

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use Luminova\Logger\NovaLogger;

class LoggerAware implements LoggerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ?LoggerInterface $logger = null;

    /**
     * Set a logger instance on the object.
     *
     * @param LoggerInterface $logger The logger instance.
    */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

   /**
     * Log an emergency message.
     *
     * @param string $message The emergency message to log.
     * @param array $context Additional context data (optional).
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Log an alert message.
     *
     * @param string $message The alert message to log.
     * @param array $context Additional context data (optional).
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Log a critical message.
     *
     * @param string $message The critical message to log.
     * @param array $context Additional context data (optional).
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Log an error message.
     *
     * @param string $message The error message to log.
     * @param array $context Additional context data (optional).
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Log a warning message.
     *
     * @param string $message The warning message to log.
     * @param array $context Additional context data (optional).
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log a notice message.
     *
     * @param string $message The notice message to log.
     * @param array $context Additional context data (optional).
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Log an info message.
     *
     * @param string $message The info message to log.
     * @param array $context Additional context data (optional).
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a debug message.
     *
     * @param string $message The debug message to log.
     * @param array $context Additional context data (optional).
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log a message at a specified log level.
     *
     * @param string $level The log level (e.g., "emergency," "error," "info").
     * @param string $message The message to log.
     * @param array $context Additional context data (optional).
     */
    public function log($level, $message, array $context = []): void
    {
        $this->logger ??= new NovaLogger();

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, $message, $context);
        }
    }
}
