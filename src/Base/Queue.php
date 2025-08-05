<?php
/**
 * Luminova Framework background task queue and execution.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base;

use \Closure;
use \Throwable;
use \DateTimeInterface;
use \Luminova\Time\Time;
use \Luminova\Models\Task;
use \Luminova\Logger\Logger;
use \Opis\Closure\Serializer;
use \Luminova\Command\Terminal;
use \Luminova\Database\{Connection, Helpers\Alter};
use \Luminova\Interface\{
    DatabaseInterface, 
    QueueableInterface, 
    InvokableInterface, 
    LazyObjectInterface,
    TaskWorkerInterface
};
use \Luminova\Exceptions\{
    ErrorCode,
    InvalidArgumentException, 
    BadMethodCallException, 
    DatabaseException, 
    FileException, 
    RuntimeException
};
use function \Luminova\Funcs\{root, make_dir};

abstract class Queue
{
    /**
     * Task is created but has not yet started.
     *
     * @var string PENDING
     */
    public const PENDING = 'pending';

    /**
     * Task is temporarily paused and can be resumed.
     *
     * @var string PAUSED
     */
    public const PAUSED = 'paused';

    /**
     * Task is currently in progress.
     *
     * @var string RUNNING
     */
    public const RUNNING = 'running';

    /**
     * Task has failed during execution.
     *
     * @var string FAILED
     */
    public const FAILED = 'failed';

    /**
     * Task has completed successfully.
     *
     * @var string COMPLETED
     */
    public const COMPLETED = 'completed';

    /**
     * Match all tasks regardless of their current status.
     *
     * @var string ALL
     */
    public const ALL = 'all';

    /**
     * Mark tasks that failed due to an exception.
     *
     * @var string EXCEPTION
     */
    protected const EXCEPTION = 'exception';

    /**
     * Match tasks that can be paused (e.g., running, failed).
     *
     * @var string PAUSEABLE
     */
    protected const PAUSEABLE = 'pauseable';

    /**
     * Match tasks that are ready for execution.
     *
     * @var string EXECUTABLE
     */
    protected const EXECUTABLE = 'executable';

    /**
     * Match tasks that run indefinitely without expiration.
     *
     * @var string FOREVER
     */
    public const FOREVER = 'forever';

    /**
     * List of all valid task statuses.
     * 
     * @var array<int,string> STATUS_NAMES
     */
    protected const STATUS_NAMES = [
        self::PENDING, 
        self::PAUSED,
        self::RUNNING, 
        self::FAILED, 
        self::COMPLETED
    ];
   
    /**
     * Database connection instance used for task operations.
     * 
     * @var Connection|null $db
     */
    protected ?Connection $db = null;

    /**
     * Database driver instance.
     * 
     * @var DatabaseInterface|null $stmt
     */
    private static ?DatabaseInterface $stmt = null;

    /**
     * Task completion callback.
     * 
     * @var Closure|null $onComplete
     * @internal Used internally when executing task in novakit to monitor process.
     */
    public ?Closure $onComplete = null;

    /**
     * Task error callback.
     * 
     * This callback will execute if any error occur while executing task.
     * 
     * @var Closure|null $onError
     * 
     * @example - Callback Signature:
     * ```php
     * $this->onError = function(int $id, string $status, Throwable $e){
     *      // Handle error
     * };
     * ```
     */
    protected ?Closure $onError = null;

    /**
     * Lazy loaded terminal instance.
     * 
     * @var Terminal<LazyObjectInterface>|null $term
     */
    protected ?LazyObjectInterface $term = null;

    /**
     * A static instances by class.
     *
     * @var array<class-string,static> $instances
     */
    private static array $instances = [];

    /**
     * Holds staged tasks.
     *
     * @var array<string,mixed> $queues
     */
    private array $queues = [];

    /**
     * Storage driver to use (e.g., "database", "file", etc.).
     * 
     * @var string $storage
     */
    protected string $storage = 'database';

    /**
     * Task database table name used for persistence.
     * 
     * @var string $table
     */
    protected string $table = '`task_queue`';

    /**
     * A unique identifier for this task queue group.
     *
     * This value is used to identify and manage tasks, especially when multiple
     * queue classes share the same database table. By setting a different value,
     * you can group and execute tasks that belong to a specific class or type.
     * 
     * > Note: The group name should not exceed 150 characters.
     *
     * @var string|null $group
     */
    protected ?string $group = null;

    /**
     * Automatically delete tasks after successful completion.
     * 
     * When true, completed tasks and their output will be removed immediately after execution.
     * These tasks will no longer appear in task listings.
     * 
     * @var bool $deleteOnCompletion
     */
    protected bool $deleteOnCompletion = false;

    /**
     * Flag to enable or disable support for Opis\Closure serialized handlers.
     *
     * When set to true, the system will allow serialized handlers and deserialized using `Opis\Closure\Serializer`. 
     * This enables storing closures (anonymous functions) as strings and restoring them later.
     * If false, only standard callable formats (e.g., Class@method or function names) are allowed.
     *
     * @var bool $supportOpisClosure
     */
    protected bool $supportOpisClosure = false;

    /**
     * File path used to signal background workers to stop.
     * 
     * When this file is present, active workers will shut down gracefully.
     * 
     * @var string|null $stopSignalFile
     * 
     * @note To restart the worker, run: `php novakit task:init` or manually delete the file.
     */
    protected ?string $stopSignalFile = null;

    /**
     * Enable event logging for task execution monitoring.
     * 
     * Set to false to disable logging. This flag is used when running:
     * `php novakit task:listen`.
     * 
     * @var bool $eventLogging
     */
    public bool $eventLogging = false;

    /**
     * Current execution mode (e.g., "default", "cli").
     * 
     * @internal This is set internally when running tasks with `mode=cli` in novakit.
     * 
     * @var string $mode
     */
    public string $mode = 'default';

    /**
     * Flag to set return type of tasks for methods (`info`, `get` or `list`).
     * 
     * If `true` instance of `Luminova\Models\Task` will be returned.
     * If `false` an array of task(s) will be returned
     * 
     * @var string $returnAsTaskModel
     * @internal This will always set to false when executing task in `novakit`.
     */
    public bool $returnAsTaskModel = true;

    /**
     * List of task handlers that should be ignored or skipped.
     * 
     * @var array $ignores
     */
    protected array $ignores = [];

    /**
     * Execution result
     * 
     * @var array $result
     */
    private array $result = [];

    /**
     * File lock handler
     * 
     * @var mixed $flockHandle
     */
    private mixed $flockHandle = null;

    /**
     * Worker is currently running.
     * 
     * When SIGTERM or SIGINT is sent stop worker.
     * 
     * @var bool $running
     */
    protected bool $running = true;

    /**
     * Initialize a new task queue instance.
     *
     * By default, the task system uses `App\Task\TaskQueue` as the base queue class.
     * You can extend this class to create and manage custom task queues per domain or module.
     *
     * @example - CLI Usage:
     * 
     * ```bash
     * php novakit task:queue         # Loads and enqueues tasks from tasks() method
     * php novakit task:enqueue -t App\Utils\Worker@clean
     * ```
     *
     * @example - Programmatic Usage:
     * 
     * ```php
     * $queue = new \App\Task\TaskQueue();
     * $queue->enqueue('App\Utils\Worker@clean', ['temp'], '+10 minutes');
     * 
     * // Static access
     * \App\Task\TaskQueue::enqueue('App\Utils\Worker@clean', ['temp'], '+10 minutes');
     * ```
     * > **Note:**  
     * > This constructor initializes the internal database connection.  
     * > If you're extending this class and overriding `__construct()`, you must either call `parent::__construct()`  
     * > or use the `onCreate()` method for custom initialization logic.
     */
    public function __construct()
    {
        $this->db = Connection::getInstance();
        $this->onCreate();
    }

    /**
     * Handle static method calls and route them to an instance.
     *
     * @param string $method The static method name.
     * @param array<int,mixed> $arguments Optional arguments to pass.
     *
     * @return mixed Return method call response.
     * @throws BadMethodCallException If the method does not exist.
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $instance = self::getInstance();

         if (!method_exists($instance, $method)) {
            throw new BadMethodCallException(sprintf(
                'Call to undefined static method %s::%s()',
                static::class,
                $method
            ));
        }

        return $instance->{$method}(...$arguments);
    }

    /**
     * Hook method for custom initialization logic during construction.
     *
     * This method is called automatically after the database connection is initialized.
     * It can be overridden by child classes to perform setup logic without needing
     * to override `__construct()` directly.
     *
     * @return void
     *
     * @example - Example:
     * ```php
     * use Luminova\Base\Queue;
     * 
     * class UserTaskQueue extends Queue
     * {
     *     protected function onCreate(): void
     *     {
     *         $this->ignores = ['App\Tasks\DebugTask@run'];
     *     }
     * }
     * ```
     */
    protected function onCreate(): void {}

    /**
    * Get the singleton instance of the called class.
    *
    * This method ensures that only one instance of the class is created
    * and reused throughout the application. Useful for lightweight
    * service classes or static facades that require shared state.
    *
    * @return static Returns the singleton instance of the class.
    */
    public static function getInstance(): static
    {
        $class = static::class;

        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }

        return self::$instances[$class];
    }

    /**
     * Attach a terminal instance for use within the queue system.
     *
     * This makes command arguments and options available inside the queue class.
     *
     * @param Terminal|LazyObjectInterface $term Terminal instance that provides command arguments/options.
     *
     * @return static Returns the instance of the class.
     */
    public function setTerminal(LazyObjectInterface|Terminal $term): self
    {
        $this->term = $term;
        return $this;
    }

    /**
     * Check if the database connection is active and ready.
     *
     * @return bool Return true if connected; otherwise false.
     */
    protected final function isConnected(): bool 
    {
        return ($this->db instanceof Connection) && 
            ($this->db->database() instanceof DatabaseInterface) && 
            $this->db->database()->isConnected();
    }

    /**
     * Check if the task database table is initialized and exists.
     *
     * @return bool Returns true if the table exists and the statement executed successfully.
     * @throws DatabaseException If the database is not connected.
     */
    public function isInitialized(): bool
    {
        $stmt = $this->database();
        $sql = "SELECT 1 FROM ";
        $sql .= Alter::getTableExists($stmt->getDriver());

        $stmt->prepare($sql)
            ->bind(':tableName', $this->table)
            ->execute();
        
        return $stmt->ok() && !empty($stmt->fetch(RETURN_NEXT, FETCH_COLUMN));
    }

    /**
     * Checks if the given handler string is a serialized Opis\Closure.
     *
     * This method verifies whether the provided handler string is a serialized
     * instance of Opis\Closure\Serializer. It performs the following checks:
     * 
     * - Returns false if Opis closure support is disabled or the handler is empty.
     * - Returns false if the Opis\Closure\Serializer class is not available.
     * - Checks if the handler starts with a serialized format marker matching
     *   either object (`O:`) or custom-serialized (`C:`) Opis closures.
     *
     * @param string $handler The serialized string to check.
     * 
     * @return bool Return true if the string appears to be an Opis closure, false otherwise.
     */
    public function isOpisClosure(string $handler): bool
    {
        if (!$this->supportOpisClosure || !$handler) {
            return false;
        }
    
        return self::isClosure($handler);
    }

    /**
     * Get the results of executed tasks.
     *
     * When task is executed in non-cli mode, this method will return all collected task results including status, handler, and response.
     *
     * @return array Returns an associative array of task results.
     */
    public function getResult(): array 
    {
        return $this->result;
    }

    /**
     * Returns the task group name.
     *
     * If a custom group name is set in the $group property, it will return that.
     * Otherwise, it falls back to using the short class name (without the namespace).
     *
     * @return string Return the group name used for this task queue.
     * 
     * > The group name is also used internally for identifying or grouping tasks when multiple task classes share the same queue system storage.
     */
    public function getGroup(): string
    {
        if (!$this->group) {
            $this->group = substr(strrchr(static::class, '\\') ?: static::class, 1);
        }

        return $this->group;
    }

    /**
     * Get all staged tasks, combining those defined in the subclass (`tasks()`)
     * and those explicitly queued via the `stage()` method.
     *
     * This method is used to collect all pending tasks before they are persisted
     * using the `task:queue` command. Tasks returned from `tasks()` may be defined
     * in lifecycle methods like `__construct()` or `onCreate()`.
     *
     * @return array<int,array> Returns a merged array of all staged tasks.
     */
    public function getStagedTasks(): array
    {
        return array_merge($this->tasks() ?: [], $this->queues);
    }

    /**
     * Get the resolved directory and full file path for a specific worker context.
     *
     * Supported contexts:
     * - 'event'  : Path to the task event log file.
     * - 'signal' : Path to the stop/resume signal file.
     * - 'flock'  : Path to the task lock file for preventing duplicate workers.
     *
     * @param string $context The context to resolve. One of: 'event', 'signal', 'flock'.
     * 
     * @return array|null Returns an array:
     *                    - [0] string: Directory path
     *                    - [1] string: Full file path (directory + filename)
     *                    Or null if context is invalid or required config is missing.
     *
     * @example - Example:
     * ```php
     * [$path, $file] = $this->getPathInfo('event')  ?? [null, null];
     * [$path, $file] = $this->getPathInfo('signal') ?? [null, null];
     * [$path, $file] = $this->getPathInfo('flock')  ?? [null, null];
     * ```
     */
    public function getPathInfo(string $context): ?array
    {
        $id = str_replace('\\', '_', static::class);
        $path = root('writeable/temp/worker/');

        switch ($context) {
            case 'event':
                if (!$this->eventLogging) {
                    return null;
                }

                $path .= 'events/';
                return [$path, "{$path}{$id}task.log"];

            case 'signal':
                if (!$this->stopSignalFile) {
                    return null;
                }

                $info = pathinfo($this->stopSignalFile);
                $path = str_starts_with(ltrim($info['dirname'], TRIM_DS), 'writeable/')
                    ? root($info['dirname'])
                    : $info['dirname'];

                return [$path, "{$path}{$info['basename']}"];

            case 'flock':
                $path .= 'locks/';
                return [$path, "{$path}task_{$id}.lock"];

            default:
                return null;
        }
    }

    /**
     * Define tasks to preload into the queue when `php novakit task:queue` is executed.
     *
     * This method should return an array of task(s) definitions. Each task specifies the handler 
     * and optional metadata such as arguments, priority, schedule, and forever. These tasks 
     * will be queued with an initial status of `pending`.
     *
     * @return array|null Returns an array of task definitions to enqueue, or null to skip.
     * 
     * Each task must be an associative array with the following keys:
     * - `handler`  (string, required): A valid callable, e.g. `Class@method`, `Class::method`, or `function_name`.
     * - `arguments` (array, optional): Arguments to pass to the handler.
     * - `schedule`  (DateTimeInterface|string|int|null, optional): Optional. Delay task executing (e.g, `DateTime`, Unix  timestamp, or relative time).
     * - `priority`  (int, optional): Task priority (0 = highest, 100 = lowest). Defaults to 0.
     * - `forever` (int, optional) – Interval in minutes between repeats for forever tasks (e.g, 1440 once/day).
     *
     * @example - Example:
     * 
     * ```php
     * namespace App\Tasks;
     * 
     * use Luminova\Base\Queue;
     * 
     * class TaskQueue extends Queue
     * {
     *      public function tasks(): ?array 
     *      {
     *          return [
     *              [
     *                  'handler'  => 'App\Services\Mailer@sendWelcomeEmail',
     *                  'arguments' => ['user@example.com']
     *              ],
     *              [
     *                  'handler'  => 'App\Utils\Cleanup::purgeTempFiles',
     *                  'schedule' => '+5 minutes',
     *                  'priority' => 5,
     *                  'forever'   => 1440
     *              ],
     *          ];
     *      }
     * }
     * ```
     * @see batchEnqueue()
     * @see enqueue()
     * @see stage()
     */
    abstract protected function tasks(): ?array;

    /**
     * Get the active database connection or throw if it's not available.
     *
     * @return DatabaseInterface Return database connection object.
     * @throws DatabaseException If the database is not connected.
     */
    protected final function database(): DatabaseInterface
    {
        if($this->isConnected()){
            if(!self::$stmt instanceof DatabaseInterface){
                self::$stmt = $this->db->database();
            }

            return self::$stmt;
        }

        throw new DatabaseException(
            'Error: Database connection failed.',
            ErrorCode::CONNECTION_DENIED
        );
    }

    /**
     * Close the active database connection, if connected.
     *
     * This method safely disconnects from the database and clears the local connection reference.
     *
     * @return bool Return true if connection was closed, false otherwise.
     */
    public function close(): bool
    {
        if($this->isConnected()){
            $this->db->disconnect();
        }

         if(self::$stmt instanceof DatabaseInterface){
            self::$stmt->close();
        }

        $this->db = null;
        self::$stmt = null;
        return !$this->isConnected();
    }

    /**
     * Gracefully stop task execution when a termination signal is received.
     *
     * This method sets the `$running` flag to false, allowing the worker to exit cleanly. 
     * If a signal is provided, it logs the signal and process ID.
     *
     * @param int|null $signal Optional POSIX signal number (e.g. SIGTERM, SIGINT).
     *
     * @return void
     */
    public function shutdown(?int $signal = null): void
    {
        if(!$this->running){
            return;
        }

        $sql = "UPDATE {$this->table} SET `status` = 'pending', `outputs` = NULL WHERE `status` = 'running'";
        $sql .= " AND group_name = '" . $this->getGroup() . "'";
        $this->running = false;

        $this->database()->query($sql);
        $this->close();
        $this->unlock();

        if ($signal !== null) {
            $pid = getmypid();
            $info = $pid ? " (PID: $pid)" : '';
            echo "Received signal: {$signal}{$info}\n";
        }
    }

    /**
     * Initialize the task queue task queue system.
     *
     * Creates the queue table if it does not already exist. This table stores 
     * task metadata including priority, status, handler information, and timestamps.
     *
     * If the table already exists, no changes will be made.
     *
     * @return bool Returns true if the table was created successfully or already exists.
     * @throws DatabaseException If the database connection is denied.
     */
    public function init(): bool
    {
        $handler = $this->supportOpisClosure
            ? ' MEDIUMTEXT NOT NULL'
            : ' VARCHAR(255) NOT NULL';

        $result = $this->database()->exec("CREATE TABLE IF NOT EXISTS {$this->table} (
            `id` INT AUTO_INCREMENT PRIMARY KEY, 
            `priority` TINYINT(3) NOT NULL DEFAULT 0,
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `retries` TINYINT(3) NOT NULL DEFAULT 0,
            `auto_delete` TINYINT(1) NOT NULL DEFAULT 0,
            `forever` INT UNSIGNED DEFAULT NULL,
            `status` ENUM('pending', 'running', 'failed', 'completed', 'paused') DEFAULT 'pending',
            `group_name` VARCHAR(150) NOT NULL,
            `handler` {$handler},
            `arguments` TEXT COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`arguments`)),
            `signature` CHAR(32) NOT NULL,
            `outputs` LONGTEXT DEFAULT NULL,
            `scheduled_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_task_group_signature` (`group_name`, `signature`),
            KEY `idx_group_status` (`group_name`, `status`),
            KEY `idx_group_forever_status` (`group_name`, `forever`, `status`),
            KEY `idx_group_scheduled` (`group_name`, `scheduled_at`)
        ) DEFAULT CHARSET=utf8mb4") > 0;

        $this->clean();
        return $result;
    }

    /**
     * Destroy the task queue system.
     *
     * Drops the table used for storing queued tasks. This also resets internal 
     * task states if the operation succeeds.
     *
     * @return bool Returns true if the table was dropped.
     * @throws DatabaseException If the database is not connected or an error occurs during drop.
     */
    public function deinit(): bool
    {
        $result = $this->database()
            ->exec(Alter::getDropTable($this->database()->getDriver(), $this->table)) > 0;

        if($result){
            $this->running = false;
            $this->clean();
        }

        return $result;
    }

    /**
     * Queue a new task for background execution.
     *
     * @param string|class-string<QueueableInterface>|class-string<InvokableInterface> $handler The task handler reference (e.g., function name, `Class@method`, or `Class::method`).
     * @param array $arguments Optional arguments to pass to the task on execution.
     * @param DateTimeInterface|string|int|null $schedule Optional delay task execution time (default: null). 
     *                          Accepts `DateTime`, Unix  timestamp, or relative time. 
     *                          If null, the task will run immediately.
     * @param int $priority Optional task priority (0 = highest, 100 = lowest). Default is 0.
     * @param int|null $forever Interval in minutes between repeated executions for a forever task (default: null).
     *                  (e.g, `1440` once per day).
     * @param int $retries The number of times to retry failed task (default: `0` no retry).
     * @param int $deleteOnComplete If true delete task once completed (default: `false`).
     *
     * @return int Returns the inserted task ID on success, or 0 if failed or ignored.
     * @throws DatabaseException If a database error occurs.
     * @throws InvalidArgumentException If an invalid forever interval is provided.
     *
     * @example - Example:
     * ```php
     * $queue->enqueue(
     *     handler:   App\Utils\Mailer::class . '@send',
     *     arguments: ['user@example.com', 'welcome-template'],
     *     schedule:  '+5 minutes',
     *     priority:   1
     * );
     * ```
     * 
     * @example - Forever task example:
     * ```php
     * $queue->enqueue(
     *     handler:  'App\Utils\Cleaner::purgeTemp',
     *     schedule: '+5 minutes',
     *     priority: 10,
     *     forever:  720   // Run forever, every 12 hours
     * );
     * ```
     * 
     * @see tasks()
     * @see stage()
     * @see batchEnqueue()
     */
    public function enqueue(
        string $handler, 
        array $arguments = [], 
        DateTimeInterface|string|int|null $schedule = null,
        int $priority = 0,
        ?int $forever = null,
        int $retries = 0,
        bool $deleteOnComplete = false
    ): int
    {
        if (!$handler || ($this->ignores && in_array($handler, $this->ignores, true))) {
            return -0;
        }

        $forever = $this->getForever($forever);
        $schedule = $this->toDateTime($schedule);
        $jsonArgs = json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;

        $stmt = $this->database()->prepare($this->getInsertSql())
            ->bind(':handler', $handler)
            ->bind(':arguments', $jsonArgs)
            ->bind(':forever', $forever)
            ->bind(':priority', max(0, min(100, $priority)))
            ->bind(':signature', md5("{$handler}{$jsonArgs}"))
            ->bind(':retries', max(0, (int) $retries))
            ->bind(':auto_delete', (int) $deleteOnComplete)
            ->bind(':scheduled', $schedule);
        $stmt->execute();

        return $stmt->ok() ? (int) $stmt->getLastInsertId() : 0;
    }

    /**
     * Stage a task for queuing, to be pushed later using `php novakit task:queue`.
     *
     * This method does not immediately add the task into the queue system storage. Instead, it stores the task 
     * in an internal queue list which is later processed when the `task:queue` command is executed.
     * Typically used within subclasses of `Queue` (e.g., in `__construct`, `onCreate`, or `tasks()`).
     *
     * @param string|class-string<QueueableInterface>|class-string<InvokableInterface> $handler The task handler reference (e.g., function name, `Class@method`, or `Class::method`).
     * @param array<int,mixed> $arguments Optional arguments to pass to the task on execution.
     * @param DateTimeInterface|string|int|null $schedule Optional delay time for task execution.
     *                          Accepts `DateTime`, Unix timestamp, or relative time string (e.g., `+5 minutes`). 
     *                          If null, the task is scheduled to run immediately.
     * @param int $priority Optional priority for the task (e.g, 0 = highest, 100 = lowest) (default: `0`).
     * @param int|null $forever Optional interval in minutes for repeated execution (e.g., `1440` = run once per day). 
     *                      If set, the task is marked as a "forever" task.
     * @param int $retries Optional number of times to retry the task if it fails (default: `0` no retry).
     * @param int $deleteOnComplete If true delete task once completed (default: `false`).
     *
     * @return static Returns the current instance of class.
     *
     * @example - Queue a one-time task:
     * ```php
     * protected function onCreate(): void 
     * {
     *      $this->stage(
     *          handler: App\Tasks\NotifyAdmin::class . '@dispatch',
     *          arguments: ['User 42 registered'],
     *          schedule: '+2 minutes'
     *      );
     * }
     * ```
     *
     * @example - Queue a repeating task:
     * ```php
     * protected function onCreate(): void 
     * {
     *      $this->stage(
     *          handler: 'App\Tasks\Jobs\SystemTasks::run',
     *          arguments: ['API_STATUS'],
     *          forever: 1440, // Run once every 24 hours
     *          priority: 3
     *      )->stage(
     *          handler: 'App\Tasks\Jobs\SystemTasks::run',
     *          arguments: ['CLEAR_CACHES'],
     *          forever: 1440,
     *          priority: 5
     *      );
     * }
     * ```
     * 
     * @see tasks()
     * @see enqueue()
     * @see batchEnqueue()
     */
    protected final function stage(
        string $handler, 
        array $arguments = [], 
        DateTimeInterface|string|int|null $schedule = null,
        int $priority = 0,
        ?int $forever = null,
        int $retries = 0,
        bool $deleteOnComplete = false
    ): self
    {
        $this->queues[] = [
            'handler'   => $handler,
            'arguments' => $arguments,
            'priority'  => $priority,
            'schedule'  => $schedule,
            'forever'   => $forever,
            'retries'   => $retries,
            'auto_delete' => $deleteOnComplete
        ];

        return $this;
    }

    /**
     * Queues multiple tasks for background execution in a batch.
     *
     * Each task must define a `handler`, and can optionally define:
     *
     * - `arguments` (array) – Arguments to pass to the handler.
     * - `schedule` (DateTimeInterface|string|int|null) – Optional. Delay task execution (e.g, `DateTime`, Unix  timestamp, or relative time).
     * - `priority` (int) – Priority of execution (0 = highest). Default is 0.
     * - `forever` (int|null) – Interval in minutes between repeats for forever tasks (e.g, 1440 once/day).
     * - `retries` (int|null) - The number of times to retry failed task.
     *
     * Tasks whose handlers match those in `$this->ignores` will be skipped.
     *
     * @param array $tasks Array of task definitions (each an associative array).
     *
     * @return int Return the number of tasks successfully inserted.
     * @throws DatabaseException If a database error occurs during insertion.
     *
     * @example - Example:
     * ```php
     * $queue->batchEnqueue([
     *     ['handler' => 'App\Utils\Mailer@send', 'arguments' => ['user@example.com']],
     *     ['handler' => 'App\Utils\Webhook::send', 'priority' => 10, 'schedule' => '+5 minutes'],
     *     ['handler' => 'App\Utils\Cleaner::purgeTemp', 'forever' => 1440]
     * ]);
     * ```
     * 
     * @example - Task Model Example:
     * ```php
     * $queue->batchEnqueue([
     *     [new Task(['handler' => 'App\Utils\Mailer@send', 'arguments' => ['user@example.com']])],
     *     [new Task(['handler' => 'App\Utils\Webhook::send', 'priority' => 10, 'schedule' => '+5 minutes']),
     *     [new Task(['handler' => 'App\Utils\Cleaner::purgeTemp', 'forever' => 1440])
     * ]);
     * ```
     * @see tasks()
     * @see enqueue()
     * @see stage()
     */
    public function batchEnqueue(array $tasks): int
    {
        if ($tasks === []) {
            return 0;
        }

        $inserted = 0;
        $db = $this->database();

        $db->beginTransaction();

        try{
            foreach ($tasks as $task) {
                if ($this->enqueue(...$this->getTaskFrom($task)) > 0) {
                    $inserted++;
                    usleep(5_000);
                }
            }
        }catch(Throwable $e){
            if($db->inTransaction()){
                $db->rollback();
            }

            throw $e;
        }

        if($db->inTransaction()){
            if($inserted > 0){
                return $db->commit() ? $inserted : 0;
            }

            $db->rollback();
        }

        return $inserted;
    }

    /**
     * Retrieve task information by task ID.
     *
     * @param int $id The task's unique ID.
     *
     * @return Task|array<string,mixed>|null Returns the task data as an associative array, or null if not found.
     * @throws DatabaseException If the database connection fails or a query error occurs.
     */
    public function get(int $id): Task|array|null
    {
        $stmt = $this->database()
            ->prepare("SELECT * FROM {$this->table} WHERE id = :id AND group_name = :group_name LIMIT 1")
            ->bind(':id', $id)
            ->bind(':group_name', $this->getGroup());
            $stmt->execute();

        if(!$stmt->ok()){
            return null;
        }

        if($this->returnAsTaskModel){
            return $stmt->fetchObject(Task::class) ?: null;
        }

        return $stmt->fetchNext(FETCH_ASSOC) ?: null;
    }

    /**
     * Count the number of tasks matching a given status.
     *
     * @param string $status Task status to count (e.g., 'pending', 'failed', 'all').
     *                       Use `Queue::ALL` to count all tasks.
     *
     * @return int Number of matching tasks.
     * @throws RuntimeException If the given status is invalid.
     * @throws DatabaseException If the database connection fails or a query error occurs.
     */
    public function count(string $status = self::ALL): int
    {
        $filtered = in_array($status, [self::ALL, self::EXECUTABLE, self::FOREVER], true);

        if (!$filtered) {
            $this->assertStatus($status);
        }

        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $sql .= $this->getListSql($status);

        if (!$filtered) {
            $stmt = $this->database()
                ->prepare($sql)
                ->bind(':status', $status);
            $stmt->execute();
        }else{
            $stmt = $this->database()->query($sql);
        }

        return $stmt->ok() ? $stmt->getCount() : 0;
    }

    /**
     * Update the status of a task (and optionally its output).
     *
     * @param int $id The task ID.
     * @param string $newStatus The new status to update.
     *
     * @return bool Return true if task was updated.
     * @throws RuntimeException If the status name is invalid.
     * @throws DatabaseException If the database is not connected or an error occur.
     */
    public function status(int $id, string $newStatus): bool
    {
        return $this->update($id, $newStatus);
    }

    /**
     * Delete a task by ID.
     *
     * @param int $id The task ID to delete.
     *
     * @return bool Return true if the task was deleted.
     * @throws DatabaseException If the database is not connected or an error occur.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->database()
            ->prepare("DELETE FROM {$this->table}  WHERE id = :id AND group_name = :group_name")
            ->bind(':id', $id)
            ->bind(':group_name', $this->getGroup());

        $stmt->execute();
        return $stmt->ok() && $stmt->rowCount() > 0;
    }

    /**
     * Delete all tasks with a given status.
     *
     * @param string $status Status to match. Use `all` to delete everything.
     *
     * @return int Return the number of tasks deleted.
     * @throws RuntimeException If the status name is invalid.
     * @throws DatabaseException If the database is not connected or an error occur.
     */
    public function purge(string $status = self::ALL): int
    {
        $sql = "DELETE FROM {$this->table}";
        $sql .= " WHERE `group_name` = '" . $this->getGroup() . "'";

        if ($status !== self::ALL) {
            if($status === self::FOREVER){
                $sql .= " AND `forever` IS NOT NULL";
            }else{
                $this->assertStatus($status);

                $sql .= " AND `status` = :status";
            }
        }

        $stmt = $this->database()->prepare($sql);
        
        if($status !== self::ALL && $status !== self::FOREVER){
            $stmt->bind(':status', $status);
        }

        $stmt->execute();
        return $stmt->ok() ? $stmt->rowCount() : 0;
    }

    /**
     * Reset a failed task back to 'pending'.
     *
     * @param int $id The task ID to mark as retry.
     *
     * @return bool Return true if the task was updated.
     * @throws DatabaseException If the database is not connected or an error occur.
     */
    public function retry(int $id): bool
    {
        return $this->update($id, self::PENDING, self::FAILED);
    }

    /**
     * Resume a paused task by setting its status to 'pending'.
     *
     * @param int $id The task ID.
     *
     * @return bool Return true if task was successfully resumed, otherwise false.
     * @throws DatabaseException If the database is not connected or an error occur.
     */
    public function resume(int $id): bool
    {
        return $this->update($id, self::PENDING, self::PAUSED);
    }

    /**
     * Pause a task by setting its status to 'paused', only if currently pending or failed.
     *
     * @param int $id The task ID.
     *
     * @return bool Return true if the task was paused.
     * @throws DatabaseException If the database is not connected or an error occur.
     */
    public function pause(int $id): bool
    {
        return $this->update($id, self::PAUSED, self::PAUSEABLE);
    }

    /**
     * Get a list of tasks from the database, optionally filtered by status, offset, and limit.
     *
     * @param string $status Task status to filter by. Use `all` to fetch all tasks.
     * @param int|null $limit Maximum number of tasks to retrieve. Null for no limit.
     * @param int $offset The limit offset to start from (default: 0).
     * @param bool $withTotal Whether to include total task count with result (default: false).
     *
     * @return array<int,array>|null Returns an array of tasks or null if no tasks found.
     *              Return structure if `$countOver` is true `['total' => int, 'count' => int, 'tasks' => array|Task]`.
     * @throws DatabaseException If the database connection fails or a query error occurs.
     */
    public function list(
        string $status = self::ALL,
        ?int $limit = null,
        int $offset = 0,
        bool $withTotal = false
    ): ?array 
    {
        $filtered = in_array($status, [self::ALL, self::EXECUTABLE, self::FOREVER], true);
        $countColumn = '';

        if (!$filtered) {
            $this->assertStatus($status);
        }

        $db = $this->database();

        if($withTotal && $this->supportsWindowFunctions($db)){
            $countColumn = ', COUNT(*) OVER() AS totalTasks';
        }

        $sql = "SELECT *{$countColumn} FROM {$this->table}";
        $sql .= $this->getListSql($status);
        $sql .= " ORDER BY priority ASC, scheduled_at ASC, id ASC";

        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }

        if($filtered){
            $stmt = $db->query($sql);
        }else{
            $stmt = $db->prepare($sql);
            $stmt->bind(':status', $status);
            $stmt->execute();
        }

        if(!$stmt->ok()){
            return null;
        }

        $result = $stmt->fetchAll(FETCH_ASSOC);

        if(!$result){
            return null;
        }

        if(!$withTotal){
            return $this->returnAsTaskModel 
                ? array_map(static fn(array $item) => new Task($item), $result)
                : $result;
        }

        if($countColumn === ''){
            return [
                'total' => $this->count($status),
                'count' => count($result),
                'tasks' => $this->returnAsTaskModel 
                    ? array_map(static fn(array $item) => new Task($item), $result)
                    : $result
            ];
        }

        return [
            'total' => (int) $result[0]['totalTasks'] ?? 0,
            'count' => count($result),
            'tasks' => array_map(function(array $item): Task|array {
                if($this->returnAsTaskModel){
                    return new Task($item);
                }

                unset($item['totalTasks']);
                return $item;
            }, $result)
        ];
    }

    /**
     * Exports the list of tasks with the given status to a file.
     *
     * The exported file will contain an array of task definitions, including:
     * - priority
     * - forever
     * - handler
     * - arguments
     * - schedule
     * - status
     *
     * If no file path is provided, null or empty, the method will defaults to `writeable/exports/TaskHandlerClass.txt` in the `writeable` directory.
     *
     * @param string $status Task status to filter by (e.g., ALL, PENDING, FAILED).
     * @param string|null $path Optional path to write the exported file.
     * @param string|null $metadata Optional output variable for export metadata info.
     *
     * @return bool Return true if export was successful, otherwise false.
     * @throws FileException If the directory cannot be created or written to.
     */
    public function export(string $status = self::ALL, ?string &$path = null, ?string &$metadata = null): bool
    {
        $tasks = $this->list($status);

        if (empty($tasks)) {
            return false;
        }

        $filename = str_replace('\\', '_', static::class) . '.txt';

        if (!$path) {
            $path = root('writeable/exports/', $filename);
        }

        $info = pathinfo($path);
        $dir = $info['dirname'] ?? '';
        $file = $info['basename'] ?? '';

        if (!$dir || $file === '' || $file === '.') {
            $file = $filename;
            $dir = $dir ?: root('writeable/exports/');
        }

        if (!make_dir($dir)) {
            throw new FileException(sprintf(
                'Failed to create directory "%s" for exporting tasks.', $dir
            ));
        }

        if (!is_writable($dir)) {
            throw new FileException(sprintf(
                'Export directory "%s" is not writable.', $dir
            ));
        }

        $path = rtrim($dir, TRIM_DS) . DIRECTORY_SEPARATOR . $file;

        $metadata = sprintf(
            "\nTask Handler: %s\nTotal Exported: %d\nExported On: %s\n",
            static::class,
            count($tasks),
            date('Y-m-d H:i:s')
        );

        $lines = [
            "<?php",
            "",
            "/**" . $metadata . "*/",
            "",
            "return ["
        ];

        foreach ($tasks as $task) {
            $arguments = $task['arguments'];

            if ($arguments) {
                $decoded = json_decode($arguments, true);
                $arguments = is_array($decoded) ? $decoded : $arguments;
            }

            $lines[] = "    [";
            $lines[] = "        'priority'     => {$task['priority']},";
            $lines[] = "        'forever'      => " . var_export($task['forever'], true) . ",";
            $lines[] = "        'auto_delete'  => " . ((bool) ($task['auto_delete'] ?? false)) . ",";
            $lines[] = "        'retries'      => " . var_export($task['retries'], true) . ",";
            $lines[] = "        'handler'      => " . var_export($task['handler'], true) . ",";
            $lines[] = "        'arguments'    => " . var_export($arguments, true) . ",";
            $lines[] = "        'schedule'     => " . var_export($task['scheduled_at'], true) . ",";
            $lines[] = "        'status'       => " . var_export($task['status'], true) . ",";
            $lines[] = "    ],";
        }
        

        $lines[] = "];";

        return file_put_contents($path, implode("\n", $lines)) !== false;
    }

    /**
     * Acquire an exclusive lock to prevent multiple concurrent task queue instances.
     *
     * Creates an flock file. If another process already holds the lock, this method returns false. 
     * The lock is determined by file-based advisory locking using `flock`.
     *
     * @param int|null $permission Optional. Directory permission mode (e.g, 0775).
     *                             If null use the default application directory permission from `App\Config\Files`.
     *
     * @return bool Return true if the lock was successfully acquired; false if already locked or on failure.
     * @throws RuntimeException If failed to open lock file.
     */
    public function lock(?int $permission = 0775): bool
    {
        $info = $this->getPathInfo('flock');

        if(!$info){
            return false;
        }

        if (!make_dir($info[0], $permission)) {
            return false;
        }

        $fp = fopen($info[1], 'c+');
        if (!$fp) {
            throw new RuntimeException(sprintf(
                'Failed to open lock file "%s": %s',
                $info[1],
                error_get_last()['message'] ?? 'Unknown error'
            ));
        }

        $isLocked = flock($fp, LOCK_EX | LOCK_NB);
        if ($isLocked) {
            $this->flockHandle = $fp;
            return true;
        }

        fclose($fp);
        return $isLocked;
    }

    /**
     * Release the lock by deleting the lock file.
     *
     * @return bool Return true if lock file was deleted.
     */
    public function unlock(): bool
    {
        $unlocked = false;

        if ($this->flockHandle !== null && is_resource($this->flockHandle)) {
            $unlocked = flock($this->flockHandle, LOCK_UN);
            fclose($this->flockHandle);
        }

        $info = $this->getPathInfo('flock');
        $this->flockHandle = null;

        if(!$info){
            return $unlocked;
        }

        return (file_exists($info[1]) && @unlink($info[1])) || $unlocked;
    }

    /**
     * Check if the task is currently locked.
     *
     * @return bool Return true if another instance is holding the lock.
     */
    public function isLocked(): bool
    {
        if($this->flockHandle !== null && is_resource($this->flockHandle)){
            return true;
        }

        $info = $this->getPathInfo('flock');

        if (!$info || !file_exists($info[1])) {
            return false;
        }

        $fp = fopen($info[1], 'c+');
        if (!$fp) {
            return false;
        }

        $isAvailable = flock($fp, LOCK_EX | LOCK_NB);

        if ($isAvailable) {
            flock($fp, LOCK_UN);
        }

        fclose($fp);
        return !$isAvailable;
    }

    /**
     * Start the task worker loop and continuously process executable tasks in (FIFO) order.
     *
     * This method runs in a loop until stopped by one of the following:
     * - A stop signal (SIGTERM, SIGINT) is detected.
     * - A stop signal file is detected.
     * - The maximum number of idle cycles is reached (no new tasks).
     *
     * The worker checks for new tasks at each cycle and executes them if available.
     * Adaptive sleep delay is applied based on the number of completed tasks per cycle.
     *
     * @param int $sleep Base sleep time (in microseconds) between task cycles.
     * @param int|null $limit Optional. Maximum number of tasks to fetch per cycle. Null for no limit.
     * @param int $maxIdle Maximum consecutive idle cycles allowed before automatic shutdown.
     *
     * @return void
     * @throws RuntimeException If an unrecoverable error occurs during execution.
     * @throws DatabaseException If the database is not connected or a query fails.
     */
    public final function run(int $sleep = 100, ?int $limit = null, int $maxIdle = 10): void
    {
        $this->setup();
        $idleCount = 0;

        while ($this->running) {
            if ($this->stopSignalFile) {
                $file = $this->getPathInfo('signal')[1] ?? null;

                if ($file && file_exists($file)) {
                    $this->shutdown();
                    echo "Worker stopped by signal file: {$file}\n";
                    break;
                }
            }

            $tasks = $this->list(self::EXECUTABLE, $limit);

            if (empty($tasks)) {
                $idleCount++;
                if ($idleCount >= $maxIdle) {
                    $this->shutdown();
                    echo "No new tasks after $maxIdle idle checks. Worker exiting.\n";
                    break;
                }

               sleep(1);
               continue;
            }

            $idleCount = 0; 
            $total = count($tasks);
            $completed = 0;

            foreach ($tasks as $task) {
                if(!$this->running){
                    continue;
                }

                $this->logEvent((int) $task['id'], $task['handler'], null, true);

                $response = $this->handle($task);
                $completed++;

                if ($this->compute($response, $task['handler']) && $total > 1) {
                    usleep(1000);
                }
            }

            if(!$this->running){
                break;
            }

            if ($sleep > 0 && $total > 1) {
                $ratio = $completed / $total;
                $scale = 1.0 - $ratio; 
                $delay = (int) ($sleep * ($scale * $total));

                $sleep = min(1_000_000, max(50_000, $delay));
            }

            usleep($sleep);
        }
    }

    /**
     * Checks if the task handler is a serialized Opis\Closure.
     * 
     * @param string $handler The handler to check
     * 
     * @return bool Return true if the handler appears to be an Opis closure, false otherwise.
     */
    public static function isClosure(string $handler): bool
    {
        if (!$handler || !class_exists(Serializer::class)) {
            return false;
        }

        if(str_contains($handler, '{')){
            return true;
        }

        $length = strlen(Serializer::class);

        return str_starts_with($handler, 'O:' . $length . ':"' . Serializer::class . '"')
            || str_starts_with($handler, 'C:' . $length . ':"' . Serializer::class . '"');
        
        return false;
    }

    /**
     * Update task status and output, with optional status filter check.
     *
     * @param int $id Task ID.
     * @param string $status New status to set.
     * @param string|null $filter Only update where current status matches this.
     *                            Special case: 'pauseable' allows 'pending' and 'failed'.
     * @param string|null $outputs Optional task output JSON string (e.g, `{'response': ..., 'output': ...}`).
     *
     * @return bool Return true if task was updated success, otherwise false.
     * @throws RuntimeException If status or filter is invalid.
     */
    protected final function update(
        int $id, 
        string $status, 
        ?string $filter = null, 
        ?string $outputs = null
    ): bool
    {
        $this->assertStatus($status, true);

        $sql = "UPDATE {$this->table} SET status = :status, outputs = :outputs";

        if($status === self::COMPLETED || $status === self::FAILED){
            $sql .= ', attempts = attempts + 1';
        }elseif($status === self::PENDING){
            $sql .= ', attempts = 0';
        }

        $sql .= ' WHERE id = :id';
        $sql .= " AND group_name = '" . $this->getGroup() . "'";
        $isFilter = $filter !== null 
            && $filter !== self::PAUSEABLE 
            && $filter !== self::FOREVER;

        if($filter !== null){
            if($filter === self::PAUSEABLE){
                $sql .= " AND status IN ('pending', 'failed')";
            }elseif($filter === self::FOREVER){
                $sql .= " AND forever IS NOT NULL";
            }else{
                $this->assertStatus($status, true, true);

                $sql .= " AND status = :filters";
            }
        }

        $stmt = $this->database()
            ->prepare($sql)
            ->bind(':status', $status)
            ->bind(':outputs', $outputs)
            ->bind(':id', $id);

        if($isFilter){
            $stmt->bind(':filters', $filter);
        }

        $stmt->execute();
        return $stmt->ok() && $stmt->rowCount() > 0;
    }

    /**
     * Releases the task lock and deletes the stop signal file if it exists.
     *
     * This method is used to clean up any active stop signals that prevent
     * the task worker from executing. It first unlocks the process, then
     * checks if a stop signal file is defined and removes it from the filesystem.
     *
     * @return void
     */
    protected final function clean(): void 
    {
        $this->unlock();

        if ($this->stopSignalFile) {
            $file = $this->getPathInfo('signal')[1] ?? null;

            if ($file && file_exists($file)) {
                @unlink($file);
            }
        }
    }

    /**
     * Export a result as a string, scalar, or null depending on type.
     * 
     * This method can be override to implement how task response should be exported before saving to database.
     *
     * - Scalars (int, float, bool, string): returned as-is
     * - Null: returned as null
     * - Resources: returned as a string with type info
     * - Arrays/Objects: JSON-encoded
     * - Fallback: var_export representation
     * 
     * 
     * @param string|int|float|bool|null $result The value to export.
     * @return string The string representation of the result.
     */
    protected function exportResult(mixed $result): string|int|float|bool|null
    {
        return match (true) {
            ($result === null),
            ($result === '') => $result,
            is_scalar($result) => $result,
            is_resource($result) => sprintf('resource (%s)', get_resource_type($result)),
            is_array($result), is_object($result) =>
                json_encode($result, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            default => var_export($result, true),
        };
    }

    /**
     * Resolve a handler string into an actual PHP callable.
     *
     * Supported formats:
     * - "Class@method" → Instantiates Class and calls method.
     * - "Class::method" → Static method call.
     * - "Class" → Instantiates the class if it exists.
     * - Function name → Returns the function if it exists.
     * - Serialized Opis\Closure\Serializer → Deserialized and returned.
     *
     * @param string $handler The handler string to resolve.
     * @param bool   $isInvokable Whether the resolved instance is invokable.
     * @param bool   $isQueueable Whether the instance supports queuing.
     * @param bool   $isWorker Whether the instance is a worker.
     * 
     * @return callable|null Return a valid callable or null if not resolvable.
     */
    protected function getCallable(
        string $handler, 
        bool &$isInvokable = false, 
        bool &$isQueueable = false, 
        bool &$isWorker = false,
        bool &$isAutoDelete = false
    ): mixed 
    {
        $method = null;
        $instance = null;
        $isObject = false;

        if (str_contains($handler, '@')) {
            $isObject = true;
            [$class, $method] = explode('@', $handler, 2);
            $instance = new $class();
        }elseif (class_exists($handler)) {
            $isObject = true;
            $instance = new $handler();
        }elseif (function_exists($handler)) {
            $instance = $handler;
        }elseif ($this->isOpisClosure($handler)) {
            try {
                $isObject = true;
                $instance = Serializer::unserialize($handler);
            } catch (Throwable) {}
        }elseif (str_contains($handler, '::')) {
            [$instance, $method] = explode('::', $handler, 2);
        }

        if($instance === null){
            return null;
        }

        if($isObject && $instance){
            $isInvokable = ($instance instanceof InvokableInterface);
            $isQueueable = ($instance instanceof QueueableInterface);
            $isAutoDelete = $isQueueable && $instance->deleteOnCompletion();

            if($this->term && ($instance instanceof TaskWorkerInterface)){
                $isWorker = true;
                $instance = $instance->setTerminal($this->term);
            }
        }

        return ($method === null) 
            ? $instance 
            : [$instance, $method];
    }

    /**
     * Normalize datetime.
     * 
     * @param mixed $value The execution datetime.
     * 
     * @return string Return mysql datetime string.
     */
    protected final function toDateTime(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if ($value instanceof DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            if (is_numeric($value)) {
                return (new Time())->setTimestamp((int) $value)->format('Y-m-d H:i:s');
            }

            if (is_string($value)) {
                return (new Time($value))->format('Y-m-d H:i:s');
            }
        } catch (Throwable) {}

        return null;
    }

    /**
     * Validates the `forever` value used in interval-based task execution.
     *
     * @param mixed $forever The value to check.
     * 
     * @return int|null Returns a valid integer value in minutes or null if not set.
     * @throws InvalidArgumentException If the value is invalid or below the minimum threshold.
     */
    protected final function getForever(mixed $forever): ?int
    {
        if ($forever === null) {
            return null;
        }

        if (!is_numeric($forever) || (int) $forever < 5) {
            throw new InvalidArgumentException(sprintf(
                "Invalid forever value: [%s]. Must be a number >= 5 minutes or null.", 
                var_export($forever, true)
            ));
        }

        $minutes = (int) $forever;

        if ($minutes <= 0) {
            throw new InvalidArgumentException('Forever interval must be a positive integer in minutes.');
        }

        return $minutes;
    }

    /**
     * Execute a single task from the queue and return the result.
     *
     * Handles errors and sets appropriate status.
     *
     * @param array $task Task data from the queue to execute.
     *
     * @return array{id:int,status:string,output|string,autoDelete:bool} Return an array with [taskId, status, output].
     */
    private function handle(array $task): array
    {
        $id = (int) $task['id'];
        $handler = $task['handler'];
        $taskStatus = $task['status'];
        $arguments  = json_decode($task['arguments'] ?? '[]', true) ?: [];
        $output  = '';
        $status  = self::COMPLETED;
        $result = null;
        $isAutoDelete = false;

        try {
            $isInvokable = false;
            $isQueueable = false;
            $isWorker = false;
            
            $callable = $this->getCallable(
                $handler, 
                $isInvokable, 
                $isQueueable, 
                $isWorker,
                $isAutoDelete
            );
    
            if (!$isInvokable && !is_callable($callable)) {
                return [
                    $id, self::EXCEPTION, 
                    sprintf(
                        "Invalid task handler: '%s' is not callable or does not implement %s or %s.",
                        $this->isOpisClosure($handler) ? Serializer::class . '@anonymous' : $handler,
                        InvokableInterface::class,
                        QueueableInterface::class
                    ),
                    null,
                    false
                ];
            }

            ob_start();
            $hasStarted = ($taskStatus === self::RUNNING) ? true : $this->status($id, self::RUNNING);

            if($hasStarted){
                $isAutoDelete = empty($task['forever']) && (
                    $task['auto_delete'] === 1 || 
                    $isAutoDelete || 
                    $this->deleteOnCompletion
                );

                $arguments = $arguments ?  array_values($arguments) : [];
                $result = $callable(...$arguments);
                
                if ($result !== null) {
                    $result = $this->exportResult($result);
                }
            }else{
                $output = "Task #$id could not be started (previous status: $taskStatus). Marked as failed.\n";
                $status = self::FAILED;
            }

            $output .= ob_get_clean() ?: '';

        } catch (Throwable $e) {
            $output = $e->getMessage();
            $status = self::FAILED;
        }

        return [$id, $status, $output, $result, $isAutoDelete];
    }

    /**
     * Validates the given status name against allowed status values.
     *
     * @param string $status The status name to validate.
     * @param bool $isStrict Whether to enforce strict validation rules.
     * @param bool $isFilter Whether to add filter text.
     *
     * @throws RuntimeException If the status is invalid.
     */
    private function assertStatus(
        string $status, 
        bool $isStrict = false,
        bool $isFilter = false
    ): void
    {
        $allowed = self::STATUS_NAMES;

        if (!$isStrict && !$isFilter) {
            $allowed[] = self::ALL;
        }

        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException(sprintf(
                'Invalid%s status name [%s]. Allowed values: %s.',
                $isFilter ? ' filter' : '',
                $status,
                implode(', ', $allowed)
            ), ErrorCode::INVALID_ARGUMENTS);
        }
    }

    /**
     * Log the status of a task execution to the event log file.
     *
     * @param int $id The task ID.
     * @param string $handler Task handler (function or method).
     * @param string|null $status Task status (e.g., 'running', 'completed', 'failed').
     * @param bool $isStarting Whether the log is for starting the task (true) or after execution (false).
     *
     * @return void
     */
    private function logEvent(int $id, string $handler, ?string $status = null, bool $isStarting = false): void
    {
        $handler = $this->isOpisClosure($handler) ? Serializer::class . '@anonymous' : $handler; 

        if(!$isStarting && $this->mode === 'cli' && ($this->onComplete instanceof Closure)){
            ($this->onComplete)($id, $handler, $status);
        }

        if (!$this->eventLogging || $this->mode !== 'cli') {
            return;
        }

        $info = $this->getPathInfo('event');

        if($info === null){
            return;
        }

        if (make_dir($info[0])) {
            $timestamp = date('Y-m-d H:i:s');
            $line = $isStarting
                ? sprintf("[%s] Starting Task #%d: %s\n", $timestamp, $id, $handler)
                : sprintf("[%s] Finished Task #%d: %s => %s\n", $timestamp, $id, $handler, strtoupper($status ?? 'unknown'));

            file_put_contents($info[1], $line, FILE_APPEND);
        }
    }

    /**
     * Logs task failure or delegates to a custom error handler.
     *
     * @param int $id The task identifier.
     * @param mixed $output Task output or error message.
     * @param string $status Task status at the time of failure.
     * 
     * @return void
     */
    private function logError(int $id, mixed $output, string $status): void 
    {
        if ($status === self::COMPLETED || !is_string($output)) {
            return;
        }

        if ($this->onError instanceof Closure) {
            ($this->onError)($id, $status, new RuntimeException($output));
            return;
        }

        Logger::notice(sprintf(
            'Task #%d failed. Status: %s. Output: %s',
            $id,
            $status,
            $output
        ));
    }

    /**
     * Prepare the task runner environment before entering the worker loop.
     *
     * This method sets up internal flags, clears previous event logs, and registers signal handlers
     * (SIGTERM, SIGINT) if running in CLI mode with PCNTL support. These handlers allow graceful shutdown
     * when termination signals are received.
     *
     * @return void
     */
    private function setup(): void
    {
        if(
            $this->mode === 'cli' && 
            function_exists('pcntl_signal') && 
            function_exists('pcntl_async_signals')
        ){
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn(int $signal) => $this->shutdown($signal));
            pcntl_signal(SIGINT, fn(int $signal) => $this->shutdown($signal));
        }

        $this->running = true;
        $this->result = [];

        if($this->mode === 'cli' && $this->eventLogging){
            @unlink($this->getPathInfo('event')[1]);
        }
    }

    /**
     * Processes and finalizes a task based on its status and output.
     *
     * Stores the result, handles errors, logs events, and updates or deletes the task.
     * Throws an exception if the task fails in non-CLI mode.
     *
     * @param array $response An array of executed task metadata.
     * @param string $handler Handler that processed the task.
     *
     * @return bool Return true if task succeeded or was handled; false if failed in CLI mode.
     * @throws RuntimeException If the task failed and not running in CLI mode.
     */
    private function compute(array $response, string $handler): mixed 
    {
        [$id, $status, $output, $result, $autoDelete] = $response;

        if ($this->mode !== 'cli') {
            $this->result[$id] = [
                'status'   => $status,
                'id'       => $id,
                'output'   => $output,
                'response' => $result
            ];
        }

        $this->logError($id, $output, $status);

        if ($status === self::EXCEPTION) {
            if ($this->mode === 'cli') {
                echo "Exception in task #$id: $output\n";

                $this->logEvent($id, $handler, $status);
                return false;
            }

            throw new RuntimeException("Task #$id failed: $output");
        }

        if($autoDelete && $status === self::COMPLETED){
            $this->delete($id);
        }else{
            $this->update($id, $status, null, json_encode([
                'response' => $result,
                'output' => $output,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $this->logEvent($id, $handler, $status);

        return true;
    }

    /**
     * Checks if the connected database supports SQL window functions.
     *
     * This method inspects the database driver and version to determine if window
     * functions like `ROW_NUMBER()` or `RANK()` can be used in queries.
     *
     * @return bool Return true if window functions are supported, false otherwise.
     */
    private function supportsWindowFunctions(DatabaseInterface $db): bool
    {
        $driver = null;
        $version = '';
        
        try{
            $driver = $db->getDriver();
            $version = $db->getVersion();
        }catch(Throwable){
            return false;
        }

        $versionNumber = $this->parseVersionNumber($version);
        return match ($driver) {
            'mysql', 'mysqli' => $this->isMariaDb($version)
                ? version_compare($versionNumber, '10.2.0', '>=')
                : version_compare($versionNumber, '8.0.0', '>='),

            'pgsql'   => version_compare($versionNumber, '8.4.0', '>='),
            'sqlite'  => version_compare($versionNumber, '3.25.0', '>='),
            'sqlsrv', 'dblib', 'oci' => true,
            'cubrid'  => version_compare($versionNumber, '11.0.0', '>='),
            default   => false,
        };
    }

    /**
     * Extracts task details from Task instance or array.
     *
     * @param Task|array $task An array or instance of task model.
     * 
     * @return array{0:string|null,1:array,2:int,3:string|null,4:int|null,5:int}
     */
    private function getTaskFrom(Task|array $task): array
    {
        if ($task instanceof Task) {
            $handler  = $task->handler ?? null;
            $arguments = (array) ($task->arguments ?? []);
            $priority  = (int) $task->priority;
            $schedule  = $task->scheduled_at ?? null;
            $forever   = $task->forever ?? null;
            $retries   = (int) ($task->retries ?? 0);
            $autoDelete = (bool) ($task->auto_delete ?? 0);
        } else {
            $handler  = $task['handler'] ?? null;
            $arguments = (array) ($task['arguments'] ?? []);
            $priority  = (int) ($task['priority'] ?? 0);
            $schedule  = $task['scheduled_at'] ?? null;
            $forever   = $task['forever'] ?? null;
            $retries   = (int) ($task['retries'] ?? 0);
            $autoDelete   = (bool) ($task['auto_delete'] ?? 0);
        }

        return [$handler, $arguments, $schedule, $priority, $forever, $retries, $autoDelete];
    }

    /**
     * Extract version  number.
     * 
     * @var string $version
     * 
     * @return string Return version. number only.
     */
    private function parseVersionNumber(?string $version): string
    {
        if ($version && preg_match('/(\d+\.\d+\.\d+)/', $version, $match)) {
            return $match[1];
        }

        return '0.0.0';
    }

    /**
     * Check database is mariadb from version.
     * 
     * @var string $version
     * 
     * @return bool Return true if mariadb.
     */
    private function isMariaDb(?string $version): bool
    {
        return $version && stripos($version, 'mariadb') !== false;
    }

    /**
     * Returns the SQL selection query.
     *
     * @return string Return the SQL statement for selecting task.
     */
    private function getListSql(string $status): string 
    {
        $group = $this->getGroup();

        if ($status === self::ALL) {
            return " WHERE group_name = '{$group}'";
        }

        if ($status === self::FOREVER) {
            return " WHERE forever IS NOT NULL AND group_name = '{$group}'";
        }

        if ($status === self::EXECUTABLE) {
            return " WHERE (
                status = 'pending'
                OR (
                    forever IS NOT NULL 
                    AND status IN ('pending', 'failed', 'completed')
                    AND (
                        updated_at IS NULL 
                        OR (forever > 0 AND updated_at <= (NOW() - INTERVAL forever MINUTE))
                    )
                )
                OR (
                    status = 'failed'
                    AND retries > 0 
                    AND retries >= attempts
                )
            )
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            AND group_name = '{$group}'";
        }

        return " WHERE status = :status AND group_name = '{$group}'";
    }

    /**
     * Returns the SQL insert query used for adding a new task to the queue.
     *
     * @return string Return the SQL statement for inserting or updating a task.
     */
    private function getInsertSql(): string 
    {
        $group = $this->getGroup();
        return "INSERT INTO {$this->table} 
            (
                `handler`, `arguments`, `status`, `forever`, `retries`,
                `priority`, `signature`, `group_name`, `scheduled_at`, `auto_delete`
            ) 
            VALUES (
                :handler, :arguments, 'pending', :forever, :retries,
                :priority, :signature, '{$group}', :scheduled, :auto_delete
            )
            ON DUPLICATE KEY UPDATE 
                `status` = 'pending',
                `outputs` = NULL,
                `attempts` = 0,
                `updated_at` = CURRENT_TIMESTAMP";
    }
}