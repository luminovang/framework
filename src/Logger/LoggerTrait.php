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

use \Luminova\Logger\LogLevel;

trait LoggerTrait
{
    /**
     * Support for other custom log levels.
     *
     * @param string $message The emergency message to log.
     * @param array $context Additional context data (optional).
     * 
     * @return void 
     */
    public function __call(string $method, array $arguments = [])
    {
        $this->log($method, ...$arguments);
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
}