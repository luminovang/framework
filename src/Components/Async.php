<?php
/**
 * Luminova Framework non-blocking Fiber asynchronous execution.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components;

use \Fiber;
use \Closure;
use \PhpToken;
use \Throwable;
use \Luminova\Luminova;
use \Luminova\Utility\Serializer;
use \Luminova\Interface\Awaitable;
use \Opis\Closure\Serializer as OpisSerializer;
use \Luminova\Components\Future\{ProcessFuture, FiberFuture};
use \Luminova\Exceptions\{RuntimeException, InvalidArgumentException};

final class Async
{
    /**
     * Executed tasks result.
     * 
     * @var array<string,mixed> $result
     */
    protected array $result = [];

    /**
     * Flag indicating task is running.
     * 
     * @var bool $isRunning
     */
    private bool $isRunning = false;

    /**
     * PHP tokens
     * 
     * @var int[] $phpTokens
     */
    private static array $phpTokens = [
        T_VARIABLE,
        T_FUNCTION,
        T_CLASS,
        T_ECHO,
        T_IF,
        T_FOREACH,
        T_WHILE,
        T_RETURN,
        T_STRING,
        T_NEW,
        T_NS_SEPARATOR,
    ];

    /**
     * Initializes a new Async instance with an optional array of tasks.
     *
     * @param array<string|int,Fiber|(callable():mixed)> $tasks An optional array of tasks (Fiber or callable) to initialize the queue with.
     *
     * @throws RuntimeException If PHP Fiber is not supported.
     *
     * @example - With callable tasks.
     * 
     * ```php
     * $async = new Async([
     *     function () { return 'Task 1 completed'; },
     *     function () { return 'Task 2 completed'; }
     * ]);
     * $async->run();
     * ```
     *
     * @example - With Fiber tasks.
     * 
     * ```php
     * $async = new Async([
     *     new Fiber(fn() => 'Task 1 completed'),
     *     new Fiber(fn() => 'Task 2 completed')
     * ]);
     * $async->run();
     * ```
     */
    public function __construct(protected array $tasks = [])
    {
        $this->isRunning = false;
        $this->toKeyString();
    }

    /**
     * Gets the current list of tasks.
     *
     * @return array<string,Fiber|callable> Return an array of current tasks.
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get executed task result.
     *
     * @return array<string,mixed> Return array of completed task results.
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * Checks if there are any tasks in the queue.
     *
     * @return bool Return true if there are tasks, false otherwise.
     */
    public function isEmpty(): bool
    {
        return $this->tasks === [];
    }

    /**
     * Suspends the current fiber and yields a value.
     *
     * @param mixed $value The value to yield.
     * 
     * @return mixed Return the value yielded by the fiber or the immediate value if fibers are not supported.
     * @throws RuntimeException If PHP Fiber is not supported.
     */
    public static function next(mixed $value = null): mixed
    {
        return Fiber::suspend($value);
    }

    /**
     * Pauses the execution of the current fiber for a specified duration.
     * 
     * @param float|int $seconds The duration to pause, in seconds (e.g., 1 for one second, 0.5 for half a second).
     * @throws RuntimeException If PHP Fiber is not supported on the system.
     */
    public static function sleep(float|int $seconds = 0.5): void
    {
        $stop = microtime(true) + (float) $seconds;

        while (microtime(true) < $stop) {
            Fiber::suspend();
        }
    }

    /**
     * Adds a fiber or callable to the task queue for later execution.
     *
     * @param Fiber|(callable():mixed) $task The task to add to queue (e.g, `fiber` or `callable`).
     * 
     * @return string Return the unique task ID for reference.
     */
    public function enqueue(Fiber|callable $task): string
    {
        $id = uniqid('task_', true);
        $this->tasks[$id] = ($task instanceof Fiber) ? $task : new Fiber($task);

        return $id;
    }

    /**
     * Removes a task from the task queue by its ID or index.
     *
     * @param string|int $id The task ID or index to remove.
     * 
     * @return bool Returns true if the task was removed, false otherwise.
     */
    public function dequeue(string|int $id): bool
    {
        $id = is_int($id) ? 'task_' . $id : $id;

        if (isset($this->tasks[$id])) {
            unset($this->tasks[$id]);
            return true;
        }

        return false;
    }

    /**
     * Reindexes the tasks array to remove any gaps in the array keys.
     *
     * @return bool Returns true if the reindexed, otherwise false.
     */
    public function reindex(): bool
    {
        if($this->tasks === []){
            return false;
        }

        $this->tasks = array_filter($this->tasks, fn($task) => $task !== null);
        return true;
    }

    /**
     * Prioritizes a specific task by moving it to the front of the queue.
     *
     * @param string|int $id The id or index of the task to prioritize.
     * 
     * @return bool Return true if the task was prioritized, false otherwise.
     */
    public function prioritize(string|int $id): bool
    {
        $id = is_int($id) ? 'task_' . $id : $id;

        if (array_key_exists($id, $this->tasks)) {
            $fiber = $this->tasks[$id];
            unset($this->tasks[$id]);
    
            $this->tasks = [$id => $fiber] + $this->tasks; 
            return true;
        }

        return false;
    }

    /**
     * Clears all tasks from the task queue.
     * 
     * @return true Always return true.
     */
    public function clear(): bool
    {
        $this->tasks = [];
        return true;
    }

    /**
     * Runs all enqueued tasks asynchronously with option controls over the execution.
     *
     * @param (callable(mixed $result, string $id):void)|null $callback Optional callback to execute with each result.
     *                      Callback signature: `function(mixed $result, string $id): void{}`.
     * @param int $delay The number of seconds to wait after checking for completed task (default: 5000).
     * 
     * @return void
     * @throws RuntimeException If the method is called while another task execution (`run` or `until`) is in progress.
     * 
     * @example - Example Usage.
     * 
     * ```php
     * use Luminova\Http\Network;
     * use Luminova\Http\Message\Response;
     * 
     * $results = [];
     * 
     * $async->enqueue(fn() => Network::get('https://example.com');
     * $async->enqueue(fn() => Network::get('https://another.com'));
     * 
     * $async->run(function(Response $response, string $id) use(&$results) {
     *      $results[$id] = $response->getContents();
     * });
     * ```
     */
    public function run(?callable $callback = null, int $delay = 5000): void
    {
        if ($this->isRunning) {
            throw new RuntimeException('This is already running. Wait for it to finish before calling Async::run().');
        }

        $this->execute($callback, $delay);
    }

    /**
     * Executes all deferred tasks sequentially until completion.
     * 
     * @param (callable(mixed $result, string $id):void)|null $callback Callback to execute after each task completes, receiving the result and index.
     *                      Callback signature: `function(mixed $result, string $id): void{}`.
     *
     * @return void
     * @throws RuntimeException If the method is called while another task execution (`run` or `until`) is in progress.
     * @throws Exception If any error occurs.
     * 
     * @example - Usage example:
     * 
     * ```php
     * use Luminova\Http\Network;
     * 
     * $async->enqueue(fn() => (new Network)->get('https://example.com')->getContents());
     * $async->enqueue(fn() => (new Network)->get('https://another.com')->getContents());
     * 
     * $async->until(function(mixed $result, string $id){
     *      var_dump($result);
     * });
     * ```
     */
    public function until(?callable $callback = null): void
    {
        if ($this->isRunning) {
            throw new RuntimeException('This is already running. Wait for it to finish before calling Async::until().');
        }

        $this->execute($callback);
    }

    /**
     * Creates a new instance of the Async class, optionally initializing with an array of tasks.
     *
     * @param array<string|int,Fiber|(callable():mixed)> $tasks An optional array of tasks (Fiber or callable) to initialize with.
     * 
     * @return self Returns a new instance of the Async class.
     * @throws RuntimeException If PHP Fiber is not supported.
     *
     * @example - With callable tasks.
     * 
     * ```php
     * $async = Async::task([
     *     function () { return 'Task 1 completed'; },
     *     function () { return 'Task 2 completed'; }
     * ]);
     * 
     * $async->run();
     * var_dump($async->getResult())
     *```
     *
     * @example - With Fiber tasks and callback.
     * 
     * ```php
     * $async = Async::task([
     *     new Fiber(fn() => 'Task 1 completed'),
     *     new Fiber(fn() => 'Task 2 completed')
     * ]);
     * 
     * $async->run(function(mixed $result){
     *      echo $result;
     * });
     * ```
     */
    public static function task(array $tasks = []): self 
    {
        return new self($tasks);
    }

    /**
     * Wrap a task in an awaitable Future for asynchronous execution.
     *
     * Converts a given task into a `Future` without running it immediately. 
     * The returned awaitable can be passed to `await()` for controlled execution 
     * and retrieving the result.
     *
     * Supported task types:
     * - `Awaitable`: returned as-is.
     * - `Fiber` or `callable`: wrapped in a `FiberFuture`.
     * - `callable` with `$detachProcess = true`: executed in a detached background worker (`background()`), returning a `ProcessFuture`.
     * - `int` (PID): wrapped in a `ProcessFuture`.
     *
     * @param Awaitable|Fiber|callable|int $task The task to execute asynchronously.
     * @param bool $detachProcess Whether to run a callable in a detached background process.
     * 
     * @return Awaitable Returns an awaitable representing the task.
     * @throws InvalidArgumentException If the task type is unsupported.
     *
     * @example - Fiber Async:
     * ```php
     * $future = Async::async(fn() => doSomething());
     * $result = Async::await($future);
     * echo $result;
     * ```
     *
     * @example - Detached process (Background)
     * ```php
     * $future = Async::async(fn() => doHeavyTask(), detachProcess: true);
     * $result = Async::await($future);
     * ```
     * @see self::background() For Detached process future.
     */
    public static function async(Awaitable|Fiber|callable|int $task, bool $detachProcess = false): Awaitable
    {
        if ($task instanceof Awaitable) {
            return $task;
        }

        $isCallable = is_callable($task);

        if ($isCallable && $detachProcess) {
            return self::background($task, lazyRun: true);
        }

        if ($isCallable || $task instanceof Fiber) {
            return new FiberFuture($task);
        }

        if (is_int($task) && $task > 0) {
            return new ProcessFuture($task);
        }

        throw new InvalidArgumentException(sprintf(
            'Unsupported task type: %s. Expected Awaitable, Fiber, callable, or int.',
            get_debug_type($task)
        ));
    }

    /**
     * Wait for a task to complete and return its result.
     *
     * Supports Awaitable, Fiber, or callable tasks. Blocks until the task finishes,
     * optionally adding a delay between ticks and enforcing a maximum wait time.
     * Throws if the task does not complete in time or if the task itself fails.
     *
     * @param Awaitable|Fiber|callable $task The task to await.
     * @param int $timeout Maximum time to wait in seconds (0 = no limit).
     * @param float $delay Delay in seconds between execution ticks (default 0.1).
     *
     * @return mixed The value returned by the task upon completion.
     *
     * @throws RuntimeException If the task does not complete within $timeout seconds.
     * @throws Throwable If the underlying task throws an exception.
     * @see self::async() - To create future object.
     *
     * @example - Example:
     * ```php
     * $future = Async::async(fn() => doSomething());
     * 
     * $result = Async::await($future);
     * echo $result;
     * ```
     *
     * @example - With delay:
     * ```php
     * Async::await(Async::async(fn() => doWork()), delay: 0.01);
     * ```
     *
     * @example - With timeout:
     * ```php
     * try {
     *     Async::await(Async::async(fn() => slowTask()), timeout: 2.5);
     * } catch (RuntimeException $e) {
     *     echo "Task timed out!";
     * }
     * ```
     *
     * @example - Waiting on multiple tasks:
     * ```php
     * $tasks = [
     *     Async::async(fn() => fetch('https://a.com')),
     *     Async::async(fn() => fetch('https://b.com')),
     * ];
     * 
     * $results = [];
     * foreach ($tasks as $task) {
     *     $results[] = Async::await($task);
     * }
     * ```
     */
    public static function await(Awaitable|Fiber|callable $task, int $timeout = 0, float $delay = 0.1): mixed
    {
        if($task instanceof Awaitable){
            return $task->await($timeout, $delay);
        }

        if(!$task instanceof Fiber){
            $task = new Fiber($task);
        }

        $delay = max(1_000, (int) ($delay * 1_000_000));
        $start = microtime(true);

        if (!$task->isStarted()) {
            $task->start();
        }

        while (!$task->isTerminated()) {
            if ($task->isSuspended()) {
                $task->resume();
            }

            if ($task->isTerminated()) {
                break;
            }

            if ($timeout > 0 && (microtime(true) - $start) >= $timeout) {
                throw new RuntimeException(
                    sprintf('Task did not complete within %.3f seconds.', $timeout)
                );
            }

            usleep($delay);
            Fiber::suspend();
        }

        return $task->getReturn();
    }

    /**
     * Executes a given callback function after a specified timeout in milliseconds.
     *
     * This method runs the callback asynchronously using a Fiber and handles any
     * errors that occur during execution by throwing a `RuntimeException`.
     *
     * @param (callable():void) $callback The callback function to execute after the timeout.
     * @param int $milliseconds The delay before the callback is executed, in milliseconds.
     *
     * @throws RuntimeException Throws if an error occurs during execution.
     * 
     * @example - Usage Example:
     * 
     * ```php
     * echo "Start\n";
     * 
     * Async::setTimeout(function () {
     *    echo "Timeout executed at: " . date('H:i:s') . "\n";
     * }, 2000);
     * 
     * echo "End\n";
     * ```
     */
    public static function setTimeout(callable $callback, int $milliseconds): void
    {
        $seconds = $milliseconds / 1000;
    
        $fiber = new Fiber(function () use ($callback, $seconds) {
            Fiber::suspend();
            usleep($seconds * 1_000_000); 
            try{
                $callback();
            }catch(Throwable $e){
                throw new RuntimeException(
                    'Failure while executing callback: ' . $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }
        });
    
        $fiber->start();
        usleep($milliseconds * 1_000);
        $fiber->resume();
    }

    /**
     * Creates a Fiber to execute a given callback after a specified timeout.
     *
     * The returned Fiber can be manually managed to provide fine-grained control
     * over its execution.
     *
     * @param (callable(mixed $result):void) $callback The callback function to execute after the timeout.
     * @param int $milliseconds The delay before the callback is executed, in milliseconds.
     *
     * @return Fiber The Fiber instance managing the timeout execution.
     * @throws RuntimeException Throws if an error occurs during execution.
     * 
     * @example - Usage Example:
     * ```php
     * echo "Start\n";
     * $fiber = Async::timeout(function () {
     *     echo "Timeout executed in fiber at: " . date('H:i:s') . "\n";
     * }, 2000);
     * 
     * usleep(1000_000); // Simulate 1 second of work
     * 
     * // Resume the fiber to allow the callback to execute
     * $fiber->resume();
     * echo "End\n";
     * ```
     * 
     * @example - Another Example:
     * 
     * ```php
     * $fibers = [];
     * $fibers[] = Async::timeout(function (int $value) {
     *     echo "Task 1 of {$value} executed after 1 second\n";
     * }, 1000);
     * 
     * $fibers[] = Async::timeout(function () {
     *     echo "Task 2 executed after 2 seconds\n";
     * }, 2000);
     * 
     * foreach ($fibers as $idx => $fiber) {
     *     $fiber->resume($idx);
     * }
     * ```
     */
    public static function timeout(callable $callback, int $milliseconds): Fiber
    {
        $fiber = new Fiber(function () use ($callback, $milliseconds) {
            $value = Fiber::suspend();
            usleep($milliseconds * 1_000);
            try{
                $callback($value);
            }catch(Throwable $e){
                throw new RuntimeException(
                    'Failure while executing callback: ' . $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }
        });

        $fiber->start();
        return $fiber;
    }

    /**
     * Run a PHP task asynchronously in a detached CLI worker.
     *
     * Executes a task in the background without blocking the current request.
     * The task may be provided as raw PHP code, a callable, or a closure.
     * When called from CLI, the task executes immediately and returns
     * a resolved Future.
     *
     * Worker context:
     * - Passed arguments are available as `$arguments`
     *   or via `__get_worker_argv()`.
     *
     * Task rules:
     * - Raw PHP code must be valid PHP and MUST NOT include PHP tags
     *   (`<?php`, `<?`, `?>`).
     * - Callables may be functions, static methods
     *   (`[Class::class, 'method']`), or closures.
     * - Closures are serialized using Opis\Closure if available or Luminova serializer.
     *
     * Execution model:
     * - By default, the task starts immediately in the background.
     * - If `$lazyRun` is enabled, execution is deferred until `start()` or `await()` is called.
     * - Task is run immediately if already in CLI mode.
     *
     * @param callable|string $task Task to execute:
     *   - string: raw PHP code (no PHP tags)
     *   - callable: function or `[Class::class, 'method']`
     *   - closure: supported via Opis\Closure or Luminova
     * @param array<string,mixed> $arguments Optional key-value arguments to passed worker task.
     * @param bool $lazyRun Whether execution should be deferred until `start()` or `await()` is invoked.
     * @param bool $noOutput Whether to suppress response/output capture from the background task (default: false).
     * @param string|null $phpPath Optional PHP CLI binary path, auto-detected when null
     *
     * @return ProcessFuture<Awaitable> A Future representing the background task
     * @throws RuntimeException If the task cannot be prepared or executed
     *
     * @example Run raw PHP asynchronously:
     * ```php
     * $future = Async::background(<<<'PHP'
     *     sleep(2);
     *     file_put_contents($arguments['path'], "Job done\n", FILE_APPEND);
     * PHP, ['path' => root('/writeable/test/', 'test_bg.txt')]);
     * ```
     *
     * @example Execute a static class method:
     * ```php
     * $future = Async::background([MyJob::class, 'handle']);
     * ```
     *
     * @example Execute an anonymous closure:
     * ```php
     * $future = Async::background(
     *     fn(array $args) => Logger::debug($args['message']),
     *     arguments: ['message' => 'Log background message']
     * );
     * ```
     *
     * @example Wait for completion:
     * ```php
     * $result = Async::await($future);
     * ```
     */
    public static function background(
        callable|string $task,
        array $arguments = [],
        bool $lazyRun = false,
        bool $noOutput = false,
        ?string $phpPath = null
    ): Awaitable
    {
        if(Luminova::isCommand()){
            $isCallable = is_callable($task);
            $task = $isCallable ? $task : self::buildBgTaskFrom($task);

            ob_start();

            try {
                $response = $isCallable 
                    ? $task($arguments) 
                    : (static function(string $_, array $arguments): mixed {
                        return eval($_);
                    })($task, $arguments);
            } catch (Throwable $e) {
                $response = $e;
            }

            if($noOutput){
                ob_end_clean();
                $response = null;
                return ProcessFuture::fromValue([])
                    ->noOutput($noOutput);
            }

            $output = ob_get_clean() ?: null;

            return ProcessFuture::fromValue([
                'response' => $response,
                'output'   => $output,
            ]);
        }
        
        $handler = 'none';
        $arguments['__worker_task__'] = base64_encode(self::buildBgTaskFrom($task, $handler));
        $arguments['__worker_handler__'] = $handler;
        $arguments['__worker_no_output__'] = $noOutput;

        $future = (new ProcessFuture(null))
            ->noOutput($noOutput)
            ->build(
                ['arguments' => base64_encode(serialize($arguments))],
                $phpPath
            );

        if(!$lazyRun){
            $future->start();
        }

        return $future;
    }

    /**
     * Build a background task into executable PHP code or an existing worker ID.
     *
     * Accepts a callable, raw PHP code, or an existing worker reference (`wid:*`).
     * Callables must be named functions or static class methods.
     * Anonymous closures are intentionally rejected.
     *
     * @param callable|string $task
     * @param string &$handler
     *
     * @return string Return normalized PHP code.
     * @throws RuntimeException When the task is invalid or unsupported
     */
    private static function buildBgTaskFrom(callable|string $task, ?string &$handler = null): string
    {
        if ($task instanceof Closure) {
            static $isOpis = null;

            $isOpis ??= class_exists(OpisSerializer::class);
            $handler = 'opis.closure';

            if ($isOpis) {
                return OpisSerializer::serialize($task);
            }

            $handler = 'closure';
            return Serializer::serialize($task);
        }

        if (is_callable($task)) {
            if (is_array($task)) {
                $handler = 'class';
                return serialize($task);
            }

            $pattern = '/^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*(?:::[A-Za-z_][A-Za-z0-9_]*)?$/';

            if (preg_match($pattern, $task)) {
                $handler = 'callable';
                return $task;
            }
        }

        $task = trim($task);

        if ($task === '') {
            throw new RuntimeException('Background process PHP task is empty.');
        }

        if (str_contains($task, '<?')) {
            throw new RuntimeException('Background process PHP opening tags are not allowed.');
        }

        if (str_ends_with($task, '?>')) {
            throw new RuntimeException(
                'Background process PHP closing tags are not allowed in worker code.'
            );
        }

        if(!self::isValidPhp($task)){
            throw new RuntimeException(
                'Background process task does not appear to be valid PHP code.'
            );
        }

        $handler = 'raw';
        return $task;
    }

    /**
     * Determine whether a string contains valid, intentional PHP code.
     *
     * This method does NOT execute the code.
     * It tokenizes the input using PHP's lexer and checks for real PHP constructs.
     *
     * @param string $code The PHP code to analyze.
     * @return bool Return true if likely a php code, otherwise false.
     */
    private static function isValidPhp(string $code): bool
    {
        if ($code === '') {
            return false;
        }

        try {
            foreach (PhpToken::tokenize("<?php\n" . $code) as $token) {
                if (
                    !($token instanceof PhpToken) || 
                    $token->is(T_OPEN_TAG) ||
                    $token->isIgnorable()
                ) {
                    continue;
                }

                if ($token->is(self::$phpTokens)) {
                    return true;
                }
            }
        } catch (Throwable) {}

        return false;
    }

    /**
     * Executes all tasks in the queue, either with or without a delay between iterations.
     *
     * @param (callable(mixed $result, string $id):void)|null $callback Optional callback function 
     *              to be called after each task completes.
     * @param int|null $delay Optional delay in microseconds between checking task states.
     *                        If null, tasks are executed immediately without delays.
     *
     * @return void
     */
    private function execute(?callable $callback = null, ?int $delay = null): void
    {
        $this->isRunning = true;
        $this->result[uniqid('task_', true)] = Fiber::getCurrent();

        while (!$this->isEmpty()) {
            foreach ($this->tasks as $id => $task) {
                if($task === null){
                    $this->dequeue($id);
                    continue;
                }

                $finished = false;

                if($delay === null){
                    $this->result[$id] = self::await($task);
                    $finished = true;
                }else{
                    $task = ($task instanceof Fiber) ? $task : new Fiber($task);

                    if (!$task->isStarted()) {
                        $task->start();
                    } elseif ($task->isSuspended()) {
                        $task->resume();
                    }

                    if ($task->isTerminated()) {
                        $this->result[$id] = $task->getReturn();
                        $finished = true;
                    }    
                }

                if($finished){
                    $this->dequeue($id);

                    if ($callback !== null) {
                        $callback($this->result[$id], $id);
                    }
                }
            }

            $this->reindex();

            if($delay !== null){
                usleep($delay);
            }
        }

        $this->isRunning = false;
    }

    /**
     * Converts numeric keys to prefixed string keys and ensures all values are Fiber instances.
     * 
     * @return void
     */
    private function toKeyString(): void
    {
        if ($this->tasks === [] || !array_is_list($this->tasks)) {
            return;
        }

        $tasks = [];

        foreach ($this->tasks as $key => $task) {
            $tasks["task_{$key}"] = ($task instanceof Fiber)
                ? $task 
                : new Fiber($task);
        }

        $this->tasks = $tasks;
    }
}