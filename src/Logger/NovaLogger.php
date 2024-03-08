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

use \Psr\Log\LogLevel;
use \Psr\Log\AbstractLogger;
use \Luminova\Time\Time;

class NovaLogger extends AbstractLogger
{
    public const EXCEPTION = 'exception';
    public const PHP = 'php_errors';
    /**
     * @var string $path log path
    */
    private string $path = '';

    /**
     * @var string $extension log file dot file extension
    */
    private string $extension = '.log';

    /**
     * @var array $levels log levels
    */
    private array $levels = [
        'emergency' => LogLevel::EMERGENCY,
        'alert' => LogLevel::ALERT,
        'critical' => LogLevel::CRITICAL,
        'error' => LogLevel::ERROR,
        'warning' => LogLevel::WARNING,
        'notice' => LogLevel::NOTICE,
        'info' => LogLevel::INFO,
        'debug' => LogLevel::DEBUG,
        'exception' => self::EXCEPTION,
        'php_errors' => self::PHP,
    ];

    /**
     * Initialize NovaLogger
     * 
     * @param string $extension log file dot file extension
    */
    public function __construct(string $extension = '.log')
    {
        $this->path = path('logs');
        $this->extension = $extension;
    }

    /**
     * Log an exception message.
     *
     * @param string $message The EXCEPTION message to log.
     * @param array $context Additional context data (optional).
     */
    public function exception($message, array $context = []): void
    {
        $this->log(self::EXCEPTION, $message, $context);
    }

    /**
     * Log an php message.
     *
     * @param string $message The php message to log.
     * @param array $context Additional context data (optional).
     */
    public function php($message, array $context = []): void
    {
        $this->log(self::PHP, $message, $context);
    }

    /**
     * Log a message at the given level.
     *
     * @param string $level The log level.
     * @param string $message The log message.
     * @param array $context Additional context data (optional).
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $level = $this->levels[$level] ?? LogLevel::INFO;
        
        $filepath = $this->path . "{$level}{$this->extension}";

        path()->createDirectory($this->path);
        
        $time = Time::now()->format('Y-m-d\TH:i:sP');

        $log = "[{$level}] [{$time}]: {$message}";
        if ($context !== []) {
            $log .= " Context: " . print_r($context, true);
        }

        $log .= PHP_EOL;

        write_content($filepath, $log, FILE_APPEND);
    }
}
