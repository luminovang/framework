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
namespace Luminova\Utils;

use \Fiber;
use \Exception;
use \Throwable;
use \Luminova\Utils\Promise\Promise;
use \Luminova\Interface\PromiseInterface;
use \Luminova\Exceptions\RuntimeException;

class Async
{
    /**
     * Executed tasks result.
     * 
     * @var array<string,mixed> $result
     */
    protected array $result = [];

    /**
     * Flag indicating whether fiber is supported.
     * 
     * @var bool $isFiberSupported
     */
    private static ?bool $isFiberSupported = null;

    /**
     * Flag indicating task is running.
     * 
     * @var bool $isRunning
     */
    private bool $isRunning = false;

    /**
     * Initializes a new Async instance with an optional array of tasks.
     *
     * @param array<string|int,Fiber|callable> $tasks An optional array of tasks (Fiber or callable) to initialize the queue with.
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
    public function __construct(protected array $tasks = []){
        self::isFiber();
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
        self::isFiber();
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
        self::isFiber();
        
        $stop = microtime(true) + (float) $seconds;
        while (microtime(true) < $stop) {
            Fiber::suspend();
        }
    }

    /**
     * Adds a fiber or callable to the task queue for later execution.
     *
     * @param Fiber|callable $task The task to add to queue (e.g, `fiber` or `callable`).
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
     * Creates a new instance of the Async class, optionally initializing with an array of tasks.
     *
     * @param array<string|int,Fiber|callable> $tasks An optional array of tasks (Fiber or callable) to initialize with.
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
     * Awaits the completion of a fiber or callable task.
     *
     * Executes the given `Fiber` or `callable` and waits for it to complete. 
     * Optionally delays between resume cycles and enforces a maximum wait time.
     *
     * @param Fiber|callable $task The task to await. If a callable is provided, it will be wrapped in a `Fiber`.
     *                              Callback signature: `function(): mixed { return someResult(); }`
     * @param float|int $delay Optional delay in seconds between resume cycles (default: 0 (no delay)).
     * @param float $maxWait Optional maximum time to wait in seconds (default: 0 (no max-wait)). 
     *                  If exceeded, a `RuntimeException` is thrown.
     * 
     * @return mixed Return the result returned by the task after completion.
     *
     * @throws RuntimeException If PHP Fibers are not supported or the task does not complete in time.
     * @throws Exception If the task itself throws an exception.
     *
     * @example - Basic usage with a callable:
     * 
     * ```php
     * $result = Async::await(fn() => doSomething());
     * echo $result;
     * ```
     *
     * @example - With delay between resume cycles:
     * 
     * Sleep to yield CPU (avoids busy loop).
     * 
     * ```php
     * $result = Async::await(fn() => doWork(), delay: 0.01); // 10ms delay
     * ```
     *
     * @example - With max wait time enforcement:
     * ```php
     * try {
     *     $result = Async::await(fn() => slowTask(), maxWait: 2.5); // max 2.5 seconds
     * } catch (RuntimeException $e) {
     *     echo "Task timed out!";
     * }
     * ```
     *
     * @example - Passing a pre-created Fiber:
     * ```php
     * $fiber = new Fiber(fn() => fetchData());
     * $data = Async::await($fiber);
     * ```
     * 
     * @example - Waiting on multiple network requests:
     * ```php
     * use \Luminova\Utils\Async;
     * use Luminova\Http\Network;
     * 
     * $tasks = [
     *     fn() => (new Network)->get('https://example.com'),
     *     fn() => (new Network)->get('https://another.com'),
     * ];
     * 
     * $results = [];
     * foreach ($tasks as $task) {
     *     $response = Async::await($task);
     *     $results[] = $response->getContents();
     * }
     * 
     * print_r($results);
     * ```
     */
    public static function await(Fiber|callable $task, float|int $delay = 0, float $maxWait = 0): mixed
    {
        self::isFiber();

        $task = ($task instanceof Fiber) ? $task : new Fiber($task);
        $start = microtime(true);

        if (!$task->isStarted()) {
            $task->start();
        }

        $sleep = ($delay > 0) ? (int)($delay * 1_000_000) : 0;

        while (!$task->isTerminated()) {
            if ($task->isSuspended()) {
                $task->resume();
            }

            if ($task->isTerminated()) {
                break;
            }

            if ($maxWait > 0 && (microtime(true) - $start) >= $maxWait) {
                throw new RuntimeException("Fiber did not complete within {$maxWait} seconds.");
            }

            if ($sleep > 0) {
                usleep($sleep);
            }

            Fiber::suspend();
        }

        return $task->getReturn();
    }

    /**
     * Awaits the completion of a fiber or callable and return promise that resolves to the result.
     *
     * @param Fiber|callable $task The task to await (e.g, `Fiber` or `callable`).
     *          Callback signature: `function(): mixed{ return fooRunAndReturnTaskResult(); }`.
     * 
     * @return PromiseInterface Return promise object that result to result, otherwise reject reason.
     * 
     * @example - Usage Example:
     * 
     * ```php
     * use Luminova\Http\Network;
     * 
     * $promise = Async::awaitPromise(fn() => (new Network)->get('https://example.com'));
     * $promise->then(function($result){
     *      print_r($results);
     * })->catch(function(Throwable $e){
     *      echo 'error: ' . $e->getMessage();
     * })->error(function(Throwable $e){
     *      echo 'error: ' . $e->getMessage();
     * })->finally(function(){
     *      echo 'Done';
     * });
     * ```
     */
    public static function awaitPromise(Fiber|callable $task): PromiseInterface
    {
        return new Promise(function (callable $resolve, callable $reject) use($task): void {
            try{
                $result = self::await($task);
                $resolve($result);
            }catch(Throwable $e){
                $reject($e);
            }
        });
    }

    /**
     * Executes a given callback function after a specified timeout in milliseconds.
     *
     * This method runs the callback asynchronously using a Fiber and handles any
     * errors that occur during execution by throwing a `RuntimeException`.
     *
     * @param callable $callback The callback function to execute after the timeout.
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
     * @param callable $callback The callback function to execute after the timeout.
     * @param int $milliseconds The delay before the callback is executed, in milliseconds.
     *
     * @return Fiber The Fiber instance managing the timeout execution.
     *
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
     * Runs all enqueued tasks asynchronously with option controls over the execution.
     *
     * @param callable|null $callback Optional callback to execute with each result.
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
     * $async->enqueue(fn() => (new Network)->get('https://example.com'));
     * $async->enqueue(fn() => (new Network)->get('https://another.com'));
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
     * @param callable|null $callback Callback to execute after each task completes, receiving the result and index.
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
     * Executes all tasks in the queue, either with or without a delay between iterations.
     *
     * @param callable|null $callback Optional callback function to be called after each task completes.
     *                                The callback receives the task result and its ID as parameters.
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
            $tasks['task_' . strval($key)] = ($task instanceof Fiber)
                ? $task 
                : new Fiber($task);
        }

        $this->tasks = $tasks;
    }

    /**
     * Check if PHP fiber is supported.
     * 
     * @throws RuntimeException If PHP Fiber is not supported.
     */
    private static function isFiber(): void
    {
        self::$isFiberSupported ??= (PHP_VERSION_ID >= 80100 && class_exists('Fiber'));
        
        if (!self::$isFiberSupported) {
            throw new RuntimeException(
                'PHP Fiber is not supported on your system. This class requires Fiber execution. ' .
                'Consider using the `Luminova\Utils\Queue` class as an alternative.'
            );
        }
    }
}