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
use \Luminova\Storages\FileManager;
use \Luminova\Storages\Archive;
use \Luminova\Exceptions\FileException;

class NovaLogger extends AbstractLogger
{
    /**
     * Default log path.
     * 
     * @var string|null $path
     */
    protected static ?string $path = null;

    /**
     * The maximum log size in bytes.
     * 
     * @var int|null $maxSize
     */
    protected static ?int $maxSize = null;

    /**
     * Flag indicating if backup should be created.
     * 
     * @var bool|null $createBackup
     */
    protected static ?bool $createBackup = null;

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
        'metrics' => LogLevel::METRICS,
    ];

    /**
     * Initialize NovaLogger
     * 
     * @param string $extension log file dot file extension
     */
    public function __construct(protected string $extension = '.log')
    {
        self::$path ??= root('/writeable/logs/');
        self::$maxSize ??= (int) env('logger.max.size', 0);
        self::$createBackup ??= env('logger.create.backup', false);
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
        if(make_dir(self::$path)){
            $level = self::$levels[$level] ?? LogLevel::INFO;
            $path = self::$path . "{$level}{$this->extension}";
            $message = self::message($level, $message, $context);

            if(FileManager::write($path, $message . PHP_EOL, FILE_APPEND|LOCK_EX)){
                $this->backup($path, $level);
            }
        }
    }

    /**
     * Checks if the specified log level exists.
     *
     * @param string $level The log level to check (e.g., 'error', 'info', 'debug').
     * 
     * @return bool Returns true if the log level exists, false otherwise.
     */
    public static function has(string $level): bool 
    {
        return isset(self::$levels[$level]);
    }

    /**
     * Clears the contents of the specified log file for a given log level.
     * 
     * @param string $level The log level whose log file should be cleared (e.g., 'info', 'error').
     * 
     * @return bool Returns true on success, or false on failure if the log file cannot be cleared.
     */
    public function clear(string $level): bool 
    {
        $path = self::$path . "{$level}{$this->extension}";
        return FileManager::write($path, '', LOCK_EX);
    }

    /**
     * Formats a log message with the given level, message, and optional context.
     *
     * @param string $level   The log level (e.g., 'INFO', 'ERROR').
     * @param string $message The primary log message.
     * @param array  $context Optional associative array providing context data.
     *
     * @return string Return the formatted log message.
     */
    public static function message(string $level, string $message, array $context = []): string
    {
        $now = Time::now()->format('Y-m-d\TH:i:s.uP');

        $message = "[{$level}] [{$now}]: {$message}";
        
        if ($context !== []) {
            $message .= ' Context: ' . print_r($context, true);
        }

        return $message;
    }

    /**
     * Creates a backup of the log file if it exceeds a specified maximum size.
     *
     * @param string $filepath The path to the current log file.
     * @param string $level    The log level, used in the backup file's naming convention.
     *
     * @return void 
     */
    protected function backup(string $filepath, string $level): void 
    {
        if(self::$maxSize && FileManager::size($filepath) >= (int) self::$maxSize){
            if(self::$createBackup){
                $backup_time = Time::now()->format('Ymd_His');
                $backup = self::$path . 'backups' . DIRECTORY_SEPARATOR;
                
                if(make_dir($backup)){
                    $backup .= "{$level}_v" . str_replace('.', '_', APP_VERSION) . "_{$backup_time}.zip";

                    try{
                        if(Archive::zip($backup, $filepath)){
                           $this->clear($level);
                        }
                    }catch(FileException $e){
                        $message = self::message($level, 'Failed to create backup: ' . $e->getMessage());
                        FileManager::write($filepath, $message . PHP_EOL, FILE_APPEND|LOCK_EX);
                    }
                }
                return;
            }
            
            $this->clear($level);
        }
    }
}