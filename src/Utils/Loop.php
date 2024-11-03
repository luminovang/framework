<?php
/**
 * Luminova Framework non-blocking asynchronous loop execution control using fibers.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils;

use \Fiber;
use \Luminova\Exceptions\RuntimeException;

class Loop
{
    /**
     * Executed results.
     * 
     * @var array $result
     */
    protected array $result = [];

    /**
     * Flag indicating whether fiber is supported.
     * 
     * @var bool $isFiberSupported
     */
    protected static ?bool $isFiberSupported = null;

    /**
     * Flag indicating task is running.
     * 
     * @var bool $isRunning
     */
    private bool $isRunning = false;

    /**
     * Initializes a new Loop instance with an optional array of tasks.
     *
     * @param array<int,Fiber|callable> $tasks An optional array of Fiber or callable tasks to initialize with.
     */
    public function __construct(protected array $tasks = []){}

    /**
     * Gets the current list of tasks.
     *
     * @return array<int,Fiber|callable> Return an array of current tasks.
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }

    /**
     * Get executed task result.
     *
     * @return array<int,mixed> Return array of completed task results.
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
    public function hasTasks(): bool
    {
        return $this->tasks !== [];
    }

    /**
     * Suspends the current fiber and yields a value.
     *
     * If fibers are supported, it suspends the current fiber and yields the given value.
     * If not, it simply returns the value immediately.
     *
     * @param mixed $value The value to yield.
     * 
     * @return mixed Return the value yielded by the fiber or the immediate value if fibers are not supported.
     */
    public function next(mixed $value = null): mixed
    {
        return self::isFiber() ? Fiber::suspend($value) : $value;
    }

    /**
     * Pauses the execution for a specified number of seconds.
     *
     * @param int|float $seconds The number of seconds to sleep.
     */
    public function sleep(int|float $seconds = 2): void
    {
        $stop = microtime(true) + (float) $seconds;

        while (microtime(true) < $stop) {
            $this->next();
        }
    }

    /**
     * Awaits the completion of a fiber or callable.
     *
     * If fibers are supported, it runs the fiber and waits for its completion.
     * If not, it executes the callable and waits for a short time before returning the result.
     *
     * @param Fiber|callable $callable The fiber or callable to await.
     * 
     * @return mixed Return the result of the fiber or callable.
     */
    public static function await(Fiber|callable $callable): mixed
    {
        if(self::isFiber()){
            $fiber = ($callable instanceof Fiber) ? $callable : new Fiber($callable);
            $fiber->start();

            while (!$fiber->isTerminated()) {
                $fiber->resume();
                Fiber::suspend();
            }

            return $fiber->getReturn();
        }

        $result = $callable();
        $interval = 10;
        for ($elapsed = 0; $elapsed < 100; $elapsed += $interval) {
            usleep($interval * 1000);
        }

        return $result;
    }

    /**
     * Adds a fiber or callable to the task queue for later execution.
     *
     * @param Fiber|callable $task The task to add to queue (e.g, `fiber` or `callable`).
     */
    public function enqueue(Fiber|callable $task): void
    {
        $this->tasks[] = self::isFiber() 
            ? (($task instanceof Fiber) 
                ? $task 
                : new Fiber($task)
             ) 
            : $task;
    }

    /**
     * Removes a task from the task queue by its index.
     *
     * @param int $index The index of the task to remove.
     * 
     * @return bool Return true if the task was removed, false otherwise.
     */
    public function dequeue(int $index): bool
    {
        if (isset($this->tasks[$index])) {
            unset($this->tasks[$index]);
            return true;
        }
        return false;
    }

    /**
     * Prioritizes a specific task by moving it to the front of the queue.
     *
     * @param int $index The index of the task to prioritize.
     * @return bool Return true if the task was prioritized, false otherwise.
     */
    public function prioritize(int $index): bool
    {
        if (isset($this->tasks[$index])) {
            $fiber = $this->tasks[$index];
            unset($this->tasks[$index]);
            array_unshift($this->tasks, $fiber);
            return true;
        }
        return false;
    }

    /**
     * Clears all tasks from the task queue.
     */
    public function clear(): void
    {
        $this->tasks = [];
    }

    /**
     * Runs all deferred tasks until completion.
     *
     * @param callable|null $callback Optional callback to execute with each result.
     * 
     * @example 
     * 
     * ```php
     * Loop::run(function(mixed $result, int $id){
     *  var_dump($result);
     * });
     * ```
     */
    public function run(?callable $callback = null): void
    {
        if($this->isRunning){
            throw new RuntimeException('This is already running, wait for it to finish before calling Loop::run().');
        }

        $this->isRunning = true;
        while ($this->hasTasks()) {
            foreach ($this->tasks as $idx => $fiber) {
                $this->execute($fiber, $idx, $callback);
            }
        }

        $this->isRunning = false;
    }

    /**
     * Executes a fiber and manages its lifecycle.
     *
     * @param Fiber|callable $task The task to execute.
     * @param int $id The index of the fiber in the tasks array.
     * @param callable|null $callback Optional callback to handle the result.
     */
    protected function execute(Fiber|callable $task, int $id, ?callable $callback = null): void
    {
        $result = null;
        if($task instanceof Fiber){
            if (!$task->isStarted()) {
                $result = $task->start($id);
            } elseif (!$task->isTerminated()) {
                $result = $task->resume();
            } else {
                $this->dequeue($id);
                $result = $task->getReturn();
            }
        }else{
            $result = $task();
            $this->dequeue($id);
        }

        array_merge_result($this->result, $result);

        if ($callback !== null) {
            $callback($result, $id);
        }
    }

    /**
     * Check if PHP fiber is supported.
     * 
     * @return bool Return true if PHP fiber is supported, false otherwise.
     */
    protected static function isFiber(): bool
    {
        return self::$isFiberSupported ??= (PHP_VERSION_ID >= 80100 && class_exists('Fiber'));
    }
}