<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Core;

use \Luminova\Time\Time;
use \Luminova\Time\CronInterval;
use \Luminova\Base\BaseCommand;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\InvalidArgumentException;

abstract class CoreCronTasks
{
    /**
     * Array to store cron job configurations.
     *
     * @var array $controllers
     */
    protected static array $controllers = [];

    /**
     * Path to store cron job configuration lock files.
     *
     * @var string|null $path
     */
    protected static ?string $path = null;

    /**
     * The filename to store cron job configuration lock files.
     *
     * @var string|null $filename
     */
    protected static ?string $filename = null;

    /**
     * Application timezone string.
     *
     * @var string|null
     */
    protected static ?string $timezone = null;

    /**
     * Supported Log levels.
     * 
     * @var array $logLevels
     */
    private static $logLevels = [
        'emergency',
        'alert',
        'critical',
        'error',
        'warning',
        'notice',
        'info',
        'debug',
        'exception',
        'php_errors'
    ];

    /**
     * Constructs the Cron instance with optional configuration settings.
     * 
     * To override the default cron `$path`, set the property before invoking the parent constructor.
     * 
     * @example In your cron configuration class:
     * ```php
     * class Cron extends Luminova\Core\CoreCronTasks
     * {
     *     public function __construct()
     *     {
     *         self::$path = 'path/to/cron/';
     *         parent::__construct();
     *     }
     * }
     * ```
     */
    public function __construct()
    {
        self::$path ??= root('/writeable/cron/');
        self::$filename ??='schedules.json';
        self::$timezone ??= env('app.timezone', date_default_timezone_get());
        make_dir(self::getPath());
    }

    /**
     * To define scheduled cron jobs.
     * Called method `service` withing the `schedule` method, each service should have its own configurations.
     * 
     * @return void
     */
    abstract protected function schedule(): void;

    /**
     * specific the service controller for the cron job.
     *
     * @param class-string<BaseCommand> $controller The controller class and method.
     * 
     * @return self Return cron class instance.
     */
    protected function service(string $controller): self
    {
        self::$controllers[] = [
            'controller' => $controller
        ];

        return $this;
    }

    /**
     * Set the callback for the cron execution completion.
     *
     * @param callable $onComplete The callback function to execute on completion.
     * 
     * @return self Return cron class instance.
     */
    protected function onComplete(callable $onComplete): self
    {
        self::$controllers[self::getId()]['onComplete'] = $onComplete;

        return $this;
    }

    /**
     * Set a callback for the cron execution failure.
     *
     * @param callable $onError The callback function to execute on failure.
     * 
     * @return self Return cron class instance.
     */
    protected function onFailure(callable $onError): self
    {
        self::$controllers[self::getId()]['onFailure'] = $onError;

        return $this;
    }

    /**
     * Specify the URL to ping on cron execution completion.
     *
     * @param string $url The URL to ping on completion.
     * 
     * @return self Return cron class instance.
     */
    protected function pingOnComplete(string $url): self
    {
        self::$controllers[self::getId()]['pingOnComplete'] = $url;

        return $this;
    }

    /**
     * Specify the URL to ping on cron execution failure.
     *
     * @param string $url The URL to ping on failure.
     * 
     * @return self Return cron class instance.
     */
    protected function pingOnFailure(string $url): self
    {
        self::$controllers[self::getId()]['pingOnFailure'] = $url;

        return $this;
    }

    /**
     * Set the log path for the cron job execution response.
     *
     * @param string $level The log level to use while logging execution response.
     * 
     * **Supported Log levels:**
     * [emergency, alert, critical, error, warning, notice, info, debug, exception, php_errors]
     * 
     * @return self Return cron class instance.
     * @throws InvalidArgumentException If invalid or unsupported log level is provided.
     */
    protected function log(string $level): self
    {
        if (!in_array($level, self::$logLevels, true)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid or unsupported log level: "%s" provided in %s(string $level). Supported log levels are: [%s]',
                $level,
                __FUNCTION__,
                implode(', ', self::$logLevels)
            ));
        }

        self::$controllers[self::getId()]['log'] = $level;

        return $this;
    }

    /**
     * Set the output filepath for execution messages and errors that may occur during executions.
     *
     * @param string $filepath The output filepath (e.g, `/path/to/writable/cron/err.txt`).
     * 
     * @return self Return cron class instance.
     * @throws InvalidArgumentException If invalid or unsupported file path is provided.
     * 
     * > **Note:** Latest execution outputs overrides the previous outputs.
     */
    protected function output(string $filepath): self
    {
        if (preg_match('/^(?!.*[<>:"|?*])(?!.*\/\/)[a-zA-Z0-9_\/\.\-]+$/', $filepath) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid or unsupported file path: "%s" provided in %s(). A valid file path is required.',
                $filepath,
                __FUNCTION__
            ));
        }     

        self::$controllers[self::getId()]['output'] = $filepath;

        return $this;
    }

    /**
     * Set a description for the cron service.
     *
     * @param string $description The description of the cron job.
     * 
     * @return self Return cron class instance.
     */
    protected function description(string $description): self
    {
        self::$controllers[self::getId()]['description'] = $description;

        return $this;
    }

    /**
     * Create and store the cron jobs in file.
     *
     * @param bool $force Whether to force the creation (default: false).
     * 
     * @return bool Return true if the cron jobs were successfully created and written to a file, false otherwise.
     * @internal
     */
    public final function create(bool $force = false): bool
    {
        if(!$force && file_exists(self::getPath() . self::$filename)){
            return true;
        }

        $this->schedule();
        $now = Time::now(self::$timezone)->getTimestamp();
        $commands = [];
        
        foreach(self::$controllers as $command){
            $commands[$command['controller']] = [
                'description' => $command['description'] ?? '',
                'controller' => $command['controller'],
                'lastExecutionDate' => $now,
                'lastRunCompleted' => true,
                'completed' => 0,
                'failures' => 0,
                'retries' => 0,
                'log' => $command['log'] ?? null,
                'output' => $command['output'] ?? null,
                'onFailure' => isset($command['onFailure']),
                'onComplete' => isset($command['onComplete']),
                'pingOnComplete' => $command['pingOnComplete'] ?? null,
                'pingOnFailure' => $command['pingOnFailure'] ?? null,
                'interval' => $command['interval']
            ];
        }

        return write_content(self::getPath() . self::$filename, json_encode($commands, JSON_PRETTY_PRINT));
    }

    /**
     * To execute cron task at a specific seconds.
     *
     * @param int $seconds Interval in seconds.
     * 
     * @return self Return cron class instance.
     */
    protected final function seconds(int $seconds = 5): self
    {
        return $this->setInterval('PT', $seconds, 'S');
    }

    /**
     * To execute cron task at a specific minutes.
     *
     * @param int $minutes Interval in minutes.
     * 
     * @return self Return cron class instance.
     */
    protected final function minutes(int $minutes = 1): self
    {
        return $this->setInterval('PT', $minutes, 'M');
    }

    /**
     * To execute cron task at a specific hour(s).
     *
     * @param int $hours Interval in hours.
     * 
     * @return self Return cron class instance.
     */
    protected final function hours(int $hours = 1): self
    {
        return $this->setInterval('PT', $hours, 'H');
    }

    /**
     * To execute cron task at a specific day.
     *
     * @param int $days Interval in days.
     * 
     * @return self Return cron class instance.
     */
    protected final function days(int $days = 1): self
    {
        return $this->setInterval('P', $days, 'D');
    }

    /**
     * To execute cron task at a specific week.
     *
     * @param int $weeks Interval in weeks.
     * 
     * @return self Return cron class instance.
     */
    protected final function weeks(int $weeks = 1): self
    {
        return $this->setInterval('P', $weeks, 'W');
    }

    /**
     * To execute cron task in specific month(s).
     *
     * @param int $months Interval in months.
     * 
     * @return self Return cron class instance.
     */
    protected final function months(int $months = 1): self
    {
        return $this->setInterval('P', $months, 'M');
    }

    /**
     * To execute cron task in specific year(s).
     *
     * @param int $years Interval in years.
     * 
     * @return self Return cron class instance.
     */
    protected final function years(int $years = 1): self
    {
        return $this->setInterval('P', $years, 'Y');
    }

    /**
     * To execute cron task in using the cron timestamp expression.
     *
     * @param string $expression The cron expression.
     *
     * 
     * @return self Return cron class instance. 
     * @throws RuntimeException If invalid expression is provided.
     */
    protected function cronTime(string $expression): self
    {
        $intervals = CronInterval::convert($expression, self::$timezone);

        if(is_string($intervals)){
            return $this->setInterval($intervals);
        }

        [$format, $interval, $unit] = $intervals;

        return $this->setInterval($format, $interval, $unit);
    }

    /**
     * Updates cron tasks to lock file.
     *
     * @param array $tasks An array of tasks to be locked.
     * 
     * @return bool Returns true on success, false on failure.
     * @internal
     */
    public final function update(array $tasks): bool
    {
        return write_content(self::getPath() . self::$filename, json_encode($tasks, JSON_PRETTY_PRINT));
    }

    /**
     * Retrieves tasks from cron class.
     *
     * @param string|null $controller Optional controller name to fetch specific tasks.
     * 
     * @return array Returns an array of tasks. If no controller is specified, returns all tasks.
     * @internal
     */
    public final function getTask(?string $controller = null): array
    {
        $this->schedule();

        if($controller === null){
            return self::$controllers;
        }

        return self::$controllers[$controller] ?? [];
    }

    /**
     * Retrieves tasks from lock file.
     *
     * @return array|bool Returns an array of tasks if the file exists and is readable, false otherwise.
     * @internal
     */
    public final static function getTaskFromFile(): array|bool
    {
        if(file_exists($file = self::getPath() . self::$filename)){
            $content = get_content($file);
            if($content !== false){
                return json_decode($content, true);
            }
        }

        return false;
    }

    /**
     * Set cron task execution interval.
     *
     * @param string $format Interval format.
     * @param int $value Interval value.
     * @param string $unit Interval unit.
     * 
     * @return self Return cron class instance. 
     * @internal
     */
    private function setInterval(string $format, ?int $value = null, ?string $unit = null): self
    {
        if($value !== null && $unit !== null){
            $value = max(1, $value);
            $format = "{$format}{$value}{$unit}";
        }
      
        self::$controllers[self::getId()]['interval'] = [
            'timezone' => self::$timezone,
            'format' => $format
        ];

        return $this;
    }

    /**
     * Get the cron task lock directory.
     * 
     * @return string Return the cron tasks directory.
     */
    private static function getPath(): string
    {
        return rtrim(self::$path, TRIM_DS) . DIRECTORY_SEPARATOR;
    }

    /**
     * Get the last task service index id.
     * 
     * @return int Return the index id of last added task.
     */
    protected static final function getId(): int
    {
        return (self::$controllers === []) 
            ? 0 
            : (array_key_last(self::$controllers) ?? 0);
    }
}