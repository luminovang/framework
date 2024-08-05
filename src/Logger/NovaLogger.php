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
use \Psr\Log\AbstractLogger;
use \Luminova\Time\Time;
use \Luminova\Exceptions\FileException;

class NovaLogger extends AbstractLogger
{
    /**
     * @var string|null $path log path.
    */
    protected ?string $path = null;

    /**
     * Error log levels.
     * 
     * @var array<string,string> $levels
    */
    protected static array $levels = [
        'emergency' => LogLevel::EMERGENCY,
        'alert' => LogLevel::ALERT,
        'critical' => LogLevel::CRITICAL,
        'error' => LogLevel::ERROR,
        'warning' => LogLevel::WARNING,
        'notice' => LogLevel::NOTICE,
        'info' => LogLevel::INFO,
        'debug' => LogLevel::DEBUG,
        'exception' => LogLevel::EXCEPTION,
        'php_errors' => LogLevel::PHP,
    ];

    /**
     * Initialize NovaLogger
     * 
     * @param string $extension log file dot file extension
    */
    public function __construct(protected string $extension = '.log')
    {
        $this->path ??= root('/writeable/logs/');
    }

    /**
     * Log a message at the given level.
     *
     * @param string $level The log level (e.g., "emergency," "error," "info").
     * @param string $message The log message.
     * @param array $context Optional additional context to include in log.
     *
     * @return void
     * @throws FileException — If unable to write log to file.
     */
    public function log($level, $message, array $context = [])
    {
        static $time = null;
        
        if(make_dir($this->path)){
            $level = static::$levels[$level] ?? LogLevel::INFO;
            $filepath = $this->path . "{$level}{$this->extension}";
            $time ??= Time::now();
            $now = $time->format('Y-m-d\TH:i:sP');

            $message = "[{$level}] [{$now}]: {$message}";
            
            if ($context !== []) {
                $message .= ' Context: ' . print_r($context, true);
            }

            write_content($filepath, $message . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
