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
use \Throwable;
use Luminova\Interface\Awaitable;
use Luminova\Components\Future\{ProcessFuture, FiberFuture};
use Luminova\Exceptions\{RuntimeException, InvalidArgumentException};

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
        $id = bin2hex(random_bytes(4));
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
     * @param float|int $delay The number of seconds to wait after checking for completed task (default: 0.05).
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
    public function run(?callable $callback = null, float|int $delay = 0.05): void
    {
        if ($this->isRunning) {
            throw new RuntimeException('Task already running. Wait for it to finish before calling Async::run().');
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
     * @throws Throwable If any error occurs.
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
            throw new RuntimeException(
                'Task already running. Wait for it to finish before calling Async::until().'
            );
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
     * Creates an awaitable task for asynchronous execution.
     *
     * Converts a supported task type into an `Awaitable` instance and starts
     * execution immediately. Existing awaitables are returned directly after
     * ensuring they have started.
     *
     * Supported task types:
     * - `Awaitable`: returned as-is.
     * - `Fiber`: wrapped in a `FiberFuture`.
     * - `callable`: wrapped in a `FiberFuture` or executed as a detached
     *   background worker when `$detach` is enabled.
     *
     * Detached execution:
     * - Runs the callable in a separate PHP worker process.
     * - Returns an awaitable future that can be resolved with `await()`.
     *
     * @param Awaitable|Fiber|(callable():mixed) $task Task to execute asynchronously.
     * @param bool $detach Whether callable tasks should execute in a detached
     *                     background worker process.
     *
     * @return Awaitable An awaitable task representing the execution result.
     *
     * @throws InvalidArgumentException If the provided task type is unsupported.
     *
     * @example Fiber or callable execution:
     * ```php
     * $future = Async::async(fn() => doSomething());
     *
     * $result = Async::await($future);
     * ```
     *
     * @example Detached background execution:
     * ```php
     * $future = Async::async(
     *     fn() => doHeavyTask(),
     *     detach: true
     * );
     *
     * $result = Async::await($future);
     * ```
     *
     * @see self::background() For creating detached process futures directly.
     */
    public static function async(Awaitable|Fiber|callable $task, bool $detach = false): Awaitable
    {
        if ($task instanceof Awaitable) {
            if (!$task->isStarted()) {
                $task->start();
            }

            return $task;
        }

        if (!$task instanceof Fiber && !is_callable($task)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported task type: %s. Expected Awaitable, Fiber, or callable.',
                get_debug_type($task)
            ));
        }

        if ($detach && !($task instanceof Fiber)) {
            return self::background(
                $task,
                awaitable: true
            );
        }

        $future = new FiberFuture($task);
        $future->start();

        return $future;
    }

    /**
     * Execute a task asynchronously in a detached PHP worker process.
     *
     * Supports raw PHP code, callables, and serializable closures. The task
     * receives `$arguments` and can run immediately or be delayed until started.
     *
     * @param (callable(array $args):mixed)|string $task Task to execute:
     *   - Raw PHP code without PHP tags.
     *   - Callable or static method callback.
     *   - Serializable closure.
     * @param array<string|int,mixed> $arguments Arguments passed to the worker.
     * @param bool $lazyRun Whether to delay execution until {@see Awaitable::start()} is called.
     * @param bool $awaitable Whether to capture output and return value for later.
     * @param string|null $phpPath Optional PHP CLI binary path.
     *
     * @return Awaitable Asynchronous process future.
     * @throws RuntimeException If the task cannot be prepared or started.
     *
     * @see ProcessFuture
     * 
     * @example - Execute raw PHP code in the background:
     * ```php
     * $future = Async::background(
     *     <<<'PHP'
     *     uwait(0.2);
     *     file_put_contents($arguments['file'], "Completed\n", FILE_APPEND);
     *     PHP,
     *     ['file' => '/tmp/background.log']
     * );
     * ```
     *
     * @example - Execute a static class method:
     * ```php
     * $future = Async::background(
     *     [MyJob::class, 'handle'],
     *     ['id' => 123],
     *     awaitable: true
     * );
     *
     * $future->start();
     * $future->getPid();
     * 
     * $result = $future->wait();
     * ```
     *
     * @example - Execute a closure:
     * ```php
     * $future = Async::background(
     *     function(array $arguments): void {
     *         Logger::debug($arguments['message']);
     *     },
     *     ['message' => 'Background task executed']
     * );
     * ```
     *
     * @example - Delay execution until required:
     * ```php
     * $future = Async::background(
     *     [MyJob::class, 'handle'],
     *     [123],
     *     lazyRun: true,
     * );
     *
     * $future->start();
     * $future->getPid();
     * 
     * $result = $future->wait();
     * ```
     *
     * @example - Wait for task completion:
     * ```php
     * $result = Async::await($future);
     * ```
     */
    public static function background(
        callable|string $task,
        array $arguments = [],
        bool $lazyRun = false,
        bool $awaitable = false,
        ?string $phpPath = null
    ): Awaitable
    {
        $future = (new ProcessFuture(pid: null))
            ->setAwaitable($awaitable)
            ->build(
                $task,
                $arguments,
                $phpPath
            );

        if(!$lazyRun){
            $future->start();
        }

        return $future;
    }

    /**
     * Wait for an asynchronous task to complete and return its result.
     *
     * Accepts an awaitable, fiber, or callable task and blocks execution until
     * the task finishes. Awaitable tasks use their own waiting implementation,
     * while fibers and callables are executed through a managed Fiber instance.
     *
     * The optional timeout limits the maximum waiting duration. A timeout value
     * of `0` disables the limit. The delay controls the polling interval while
     * waiting for task completion.
     *
     * @param Awaitable|Fiber|(callable():mixed) $task Task to wait for.
     *   - Awaitable: waited using its native await implementation.
     *   - Fiber: resumed until completion.
     *   - callable: converted into a Fiber automatically.
     * @param int $timeout Maximum wait time in seconds (`0` = unlimited).
     * @param float $delay Delay between completion checks in seconds.
     *
     * @return mixed The value returned by the completed task.
     * @throws RuntimeException If the task exceeds the configured timeout.
     * @throws Throwable If the task execution fails.
     *
     * @see self::async()       - Create an awaitable task without immediately waiting.
     * @see self::background()  - Create an awaitable detached background task.
     *
     * @example Await a future:
     * ```php
     * $future = Async::async(fn() => doSomething());
     *
     * $result = Async::await($future);
     * ```
     *
     * @example Await with timeout:
     * ```php
     * try {
     *     $result = Async::await(
     *         Async::async(fn() => slowTask()),
     *         timeout: 2.5
     *     );
     * } catch (RuntimeException $e) {
     *     echo 'Task timed out.';
     * }
     * ```
     *
     * @example Await a callable directly:
     * ```php
     * $result = Async::await(
     *     fn() => calculateValue()
     * );
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
    public static function await(Awaitable|Fiber|callable $task, int $timeout = 0, float|int $delay = 0.1): mixed
    {
        if($task instanceof Awaitable){
            if (!$task->isStarted()) {
                $task->start();
            }
            
            return $task->await($timeout, $delay);
        }

        if(!$task instanceof Fiber){
            $task = new Fiber($task);
        }

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
                throw new RuntimeException(sprintf(
                    'Task did not complete within %.3f seconds.', 
                    $timeout
                ));
            }

            uwait($delay);

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
     * @param float|int $seconds The delay before the callback is executed, in seconds.
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
    public static function setTimeout(callable $callback, float|int $seconds): void
    {
        $fiber = new Fiber(function () use ($callback, $seconds) {
            Fiber::suspend();
            uwait($seconds); 
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
        uwait($seconds); 

        $fiber->resume();
    }

    /**
     * Creates a Fiber to execute a given callback after a specified timeout.
     *
     * The returned Fiber can be manually managed to provide fine-grained control
     * over its execution.
     *
     * @param (callable(mixed $result):void) $callback The callback function to execute after the timeout.
     * @param float|int $seconds The delay before the callback is executed, in seconds.
     *
     * @return Fiber The Fiber instance managing the timeout execution.
     * @throws RuntimeException Throws if an error occurs during execution.
     * 
     * @example - Usage Example:
     * ```php
     * echo "Start\n";
     * $fiber = Async::timeout(function () {
     *     echo "Timeout executed in fiber at: " . date('H:i:s') . "\n";
     * }, 0.02);
     * 
     * uwait(1); // Simulate 1 second of work
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
     * }, 0.01);
     * 
     * $fibers[] = Async::timeout(function () {
     *     echo "Task 2 executed after 2 seconds\n";
     * }, 0.02);
     * 
     * foreach ($fibers as $idx => $fiber) {
     *     $fiber->resume($idx);
     * }
     * ```
     */
    public static function timeout(callable $callback, float|int $seconds): Fiber
    {
        $fiber = new Fiber(function () use ($callback, $seconds) {
            $value = Fiber::suspend();
            uwait($seconds); 
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
     * Executes all tasks in the queue, either with or without a delay between iterations.
     *
     * @param (callable(mixed $result, string $id):void)|null $callback Optional callback function 
     *              to be called after each task completes.
     * @param float|int|null $delay Optional delay in microseconds between checking task states.
     *                        If null, tasks are executed immediately without delays.
     *
     * @return void
     */
    private function execute(?callable $callback = null, float|int $delay = 0): void
    {
        $this->isRunning = true;
        $this->result[bin2hex(random_bytes(4))] = Fiber::getCurrent();

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
            uwait($delay ?? 0);
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