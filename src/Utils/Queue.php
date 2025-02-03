<?php
/**
 * Luminova Framework asynchronous queue execution using fiber, process fork or default.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils;

use \Luminova\Exceptions\RuntimeException;
use \Luminova\Logger\Logger;
use \Fiber;
use \Closure;
use \Countable;
use \FiberError;
use \Exception;
use function \pcntl_fork;
use function \pcntl_wexitstatus;
use function \pcntl_wifexited;
use function \pcntl_waitpid;

final class Queue implements Countable
{
    /**
     * Log errors and execution messages using log level `debug`.
     * 
     * @var int E_LOG
     */
    public const E_LOG = 1;

    /**
     * Display errors and messages directly to the output.
     * 
     * @var int E_OUTPUT
     */
    public const E_OUTPUT = 2;

    /**
     * Suppress all error and execution messages.
     * 
     * @var int E_SUPPRESS
     */
    public const E_SUPPRESS = 0;

    /**
     * Flag indicating whether fiber is supported.
     * 
     * @var bool $isFiberSupported
     */
    private static bool $isFiberSupported = false;

    /**
     * Flag indicating whether process fork is supported.
     * 
     * @var bool $isForkSupported
     */
    private static bool $isForkSupported = false;

    /**
     * Flag indicating task is running.
     * 
     * @var bool $isRunning
     */
    private bool $isRunning = false;

    /**
     * Flag indicating task is cancelled.
     * 
     * @var bool $isCancelled
     */
    private bool $isCancelled = false;

    /**
     * Flag indicating task is completed.
     * 
     * @var bool $isCompleted
     */
    private bool $isCompleted = false;

    /**
     * Response callback handler.
     * 
     * @var Closure|callable|null $callback
     */
    private mixed $callback = null;

    /**
     * Array of jobs fibers to resume.
     * 
     * @var Fiber[] $fibers
     */
    private array $fibers = [];

    /**
     * Array of PIDs forked processes.
     * 
     * @var array $pids
     */
    private array $pids = []; 

    /**
     * Current job index. 
     * 
     * @var int|null $index
     */
    private ?int $index = null;

    /**
     * The maximum time (in seconds) allowed for the execution.
     * 
     * @var int $timeout
     */
    private int $timeout = 0;

    /**
     * The execution start timestamp (in seconds).
     * 
     * @var float $startTime
     */
    private float $startTime = 0.0;

    /**
     * The execution completion timestamp (in seconds).
     * 
     * @var float $waited
     */
    private float $waited = 0.0;

    /**
     * Executed job results. 
     * 
     * @var mixed $result
     */
    private mixed $result = null;

    /**
     * Initializes asynchronous queue with optional jobs, output settings, and error reporting mode.
     *
     * @param array<int,Closure|callable|string> $jobs An array of jobs to execute. Each job can be a closure, callable, or string.
     * @param bool $output Whether to display the result of executed jobs if they return a string (default: false).
     * @param int $eReporting Determines how execution messages and errors are handled (default: `Queue::E_OUTPUT`).
     *
     * @example Job Queue Structure:
     * **Tasks with identifiers:**
     * Each job requires a `task` key for the job to execute, and an optional `id` as the job's identifier.
     * ```php
     * [
     *  ['task' => 'Job 1', 'id' => 1],
     *  ['task' => 'Job 2', 'id' => 'foo2']
     * ]
     * ```
     * **Tasks without identifiers:**
     * Jobs can also be passed as strings or callables.
     * ```php
     * [
     *  'Job 1',
     *  'Job 2'
     * ]
     * ```
     */
    public function __construct(
        private array $jobs = [], 
        private bool $output = false,
        private int $eReporting = self::E_OUTPUT
    )
    {
        self::$isFiberSupported = (PHP_VERSION_ID >= 80100 && class_exists('Fiber'));
        self::$isForkSupported = !self::$isFiberSupported && function_exists('pcntl_fork');
    }

    /**
     * Singleton method to run asynchronous queued tasks.
     *
     * @param array<int,Closure|callable|string> $jobs An array of jobs to execute. Each job can be a closure, callable, or string.
     * @param bool $output Whether to display the result of executed jobs if they return a string (default: false).
     * @param int $eReporting Determines how execution messages and errors are handled (default: `Queue::E_OUTPUT`).
     *
     * @return Queue Return new Queue instance.
     * @example Usage Example:
     * ```php
     * Queue::wait([
     *      request('https://example.com/foo'),
     *      request('https://example.com/bar')
     * ])->run();
     * ```
     */
    public static function wait(
        array $jobs, 
        bool $output = false,
        int $eReporting = self::E_OUTPUT
    ){
        return new self($jobs, $output, $eReporting);
    }

    /**
     * Push a new job queue, either closure, any callable or string to be executed.
     *
     * @param Closure|callable|string $item The item to enqueue.
     * @param string|int|null $id Optional job identifier (default: null).
     *
     * @return void
     */
    public function push(Closure|callable|string $item, string|int|null $id = null): void
    {
        $this->jobs[] = ($id === null)
            ? $item
            : ['task' => $item, 'id' => $id];
    }

    /**
     * Run the job queue asynchronously, executing each job's callback function.
     * If Fibers are supported, it will be used for asynchronous execution. 
     * If Fibers are not available, it attempts process forking (if supported) 
     * or executes jobs directly in a blocking manner.
     *
     * @param Closure|callable|null $callback Optional callback function executed after the queue finishes processing.
     * @param int $timeout Maximum time to wait for execution in seconds (default: 0 for no timeout).
     * 
     * @return void
     */
    public function run(?callable $callback = null, int $timeout = 0): void
    {
        if($this->isRunning){
            $this->report('Queue is already running. Wait for completion before calling Queue::run() or call Queue::cancel() to cancel all running jobs.');
            return;
        }

        if($this->jobs === []){
            $this->report('Queue has no job to execute.');
            return;
        }

        $this->report('Queue has started running.');

        $this->result = null;
        $this->timeout = $timeout;
        $this->waited = 0.0;
        $this->isRunning = true;
        $this->isCancelled = false;
        $this->isCompleted = false;
        $total = $this->count();
        $this->startTime = microtime(true);

        if(self::$isFiberSupported){
            try{
                $this->isCompleted = $this->executeWithFiber();
            }catch(Exception|FiberError $e){
                $this->report('Queue FiberError:' . $e->getMessage());
            }
        }else{
            foreach ($this->jobs as $idx => $job) {
                if ($this->isCancelled || $this->timeElapsed()) {
                    $this->isCompleted = true;
                    break;
                }

                $task = ($job instanceof Closure) 
                    ? $job 
                    : ($job['task'] ?? $job);
                $this->execute($task, $idx);
            }

            $this->reindex();
            $this->waitForAsync();
            $this->isCompleted = ($this->isCompleted || $this->jobs === []);
        }

        $this->isRunning = false;
        $this->waited = microtime(true) - $this->startTime;

        if (!$this->isCancelled && $this->isCompleted && $this->isCallable($callback)) {
            $callback($this->result);
            $total -= $this->count();
            $handler = self::$isFiberSupported 
                ? 'fibers' 
                : (self::$isForkSupported ? 'child processes' : 'queue');
            
            $this->report("All ({$total}) {$handler} was completed in {$this->waited} seconds.");
        }
    }

    /**
     * Registers a callback to handle the response of each executed task.
     * This method allows you to specify a callback function that will be called whenever a task completes.
     *
     * @param callable $callback The callback function to be invoked on task completion.
     *
     * @return void
     * @example Example Signature for Callback:
     * 
     * ```php
     * $queue->onResponse(function (mixed $result, mixed $previous, Luminova\Utils\Queue $queue) {
     *      echo "Task completed: " . var_dump($result) . "\n";
     *      echo "Current cumulative results: " . var_dump($previous) . "\n";
     * });
     * ```
     * 
     * > **Note:** The method should be invoked before calling the `Queue::run()` method. 
     */
    public function onResponse(callable $callback): void 
    {
        $this->callback = $callback;
    }

    /**
     * Manually cancel all running jobs.
     * Sets a flag that will stop the execution loop in the run method.
     *
     * @return bool Return true on success, false on failure or if no active running job to cancel.
     */
    public function cancel(): bool
    {
        if ($this->isRunning) {
            $this->isCancelled = true;
            $this->isRunning = false;
            $this->isCompleted = false;

            if(self::$isFiberSupported){
                $this->fibers = [];
                try{
                    Fiber::suspend();
                }catch(Exception|FiberError){}
            }

            if(self::$isForkSupported){
                foreach ($this->pids as $pid) {
                    posix_kill($pid, SIGTERM);
                    $this->report("Sent termination signal to PID: {$pid}");
                }
                $this->pids = [];
            }

            $this->report('Cancelling all running jobs...');
            return true;
        }
        
        $this->report('No running job to cancel.');
        return false;
    }

    /**
     * Get executed job result.
     *
     * @return array|string Return array of job result or string in a new line representing the result.
     */
    public function getResult(): array|string
    {
        return $this->result;
    }

    /**
     * Get execution start time in seconds.
     *
     * @return float Return the start execution time in seconds.
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }

    /**
     * Get execution waiting time.
     *
     * @return float Return the time waited for jobs to complete in seconds.
     */
    public function getWaitingTime(): float
    {
        return $this->waited;
    }

    /**
     * Check if queue is running any job.
     *
     * @return bool Return true if the queue is running, otherwise false.
     */
    public function isRunning(): bool
    {
        return $this->isRunning;
    }

    /**
     * Check if the queue is empty.
     *
     * @return bool Return true if the queue is empty, otherwise false.
     */
    public function isEmpty(): bool
    {
        return $this->jobs === [];
    }

    /**
     * Check if the queue has registered callable jobs.
     *
     * @return bool True if the queue has registered callable jobs.
     */
    public function hasQueue(): bool
    {
        return !$this->isEmpty() && array_filter(
            $this->jobs, 
            fn($job): bool => $this->isCallable(($job instanceof Closure) ? $job : ($job['task'] ?? $job))
        ) !== [];
    }

    /**
     * Remove an job from the queue.
     *
     * @param string|int $id The job identifier or string value to remove.
     *
     * @return bool Return true if the job was removed, false otherwise.
     */
    public function has(string|int $id): bool
    {
        if(isset($this->jobs[$id])){
            return true;
        }
        
        return array_search($id, $this->jobs) !== false;
    }

    /**
     * Get the size of the queue.
     *
     * @return int Return the size of the queue.
     */
    public function count(): int
    {
        return count($this->jobs);
    }

    /**
     * Get the size of the queue.
     *
     * @return int Return the size of the queue.
     * @ignore
     */
    public function size(): int
    {
        return $this->count();
    }

    /**
     * Delete the first task from the queue
     *
     * @return void
     */
    public function delete(): void
    {
        array_shift($this->jobs);
    }

    /**
     * Remove an job from the queue.
     *
     * @param string|int $id The job identifier or string value to remove.
     *
     * @return bool Return true if the job was removed, false otherwise.
     */
    public function remove(string|int $id): bool
    {
        if(isset($this->jobs[$id])){
            unset($this->jobs[$id]);
            return true;
        }
        
        $index = array_search($id, $this->jobs);

        if ($index !== false) {
            unset($this->jobs[$index]);
            return true;
        }

        return false;
    }

    /**
     * Reindexes the tasks array to remove any gaps in the array keys.
     *
     * This method is useful after tasks have been removed from the queue,
     * ensuring that the remaining tasks are indexed sequentially.
     *
     * @return bool Returns true if the reindexed, otherwise false.
     */
    public function reindex(): bool
    {
        if($this->jobs === []){
            return false;
        }

        $this->jobs = array_values($this->jobs);
        return true;
    }

    /**
     * Free all resources by clearing the queue
     *
     * @return void
     */
    public function free(): void
    {
        $this->cancel();
        $this->jobs = [];
        $this->fibers = [];
        $this->pids = [];
        $this->isRunning = false;
        $this->isCancelled = false;
        $this->isCompleted = false;
    }

    /**
     * Get the current job from queue and return a new instance.
     *
     * @param int $index Current job index.
     *
     * @return Queue Return new Queue instance.
     */
    public function getInstance(int $index): Queue
    {
        return self::getNewInstance([$this->jobs[$index]], $index);
    }

    /**
     * Get the current job from queue and return a new instance.
     * 
     * @return Queue Return new Queue instance.
     */
    public function current(): Queue
    {
        return $this->getInstance(0);
    }

    /**
     * Get the next job from queue and return a new instance.
     * 
     * @return Queue Return new Queue instance.
     */
    public function next(): Queue
    {
        return $this->getInstance(1);
    }

    /**
     * Get the last job from queue and return a new instance.
     * 
     * @return Queue Return new Queue instance.
     */
    public function last(): Queue
    {
        return $this->getInstance($this->count() -1);
    }

    /**
     * Get the identifier from new returned job queue instance.
     * 
     * @return string|int|null Return the current job queue identifier.
     */
    public function id(): string|int|null
    {
        if ($this->index === null) {
            return null;
        }

        return $this->jobs[$this->index]['id'] ?? $this->jobs[$this->index] ?? null;
    }

    /**
     * Return a new Queue instance.
     * 
     * @param array<int,Closure|callable|string> $job Jobs to initialize the new Queue instance.
     * @param int $index The job index.
     * 
     * @return Queue Return new Queue instance.
     */
    private static function getNewInstance(array $job, int $index): Queue
    {
        return (new self($job))->setIndex($index);
    }

     /**
     * Return a new Queue instance.
     * 
     * @param array<int,Closure|callable|string> $job Jobs to initialize the new Queue instance.
     * 
     * @return self Return new Queue instance.
     */
    private function setIndex(int $index): self
    {
        $this->index = $index;
        return $this;
    }

    /**
     * Run the job queue using fibers, managing the execution of each job.
     * 
     * @return bool Return true when all fibers are completed.
     */
    private function executeWithFiber(): bool
    {
        foreach ($this->jobs as $idx => $job) {
            if ($this->isCancelled || $this->timeElapsed()) {
                break;
            }

            $task = ($job instanceof Closure) 
                ? $job 
                : ($job['task'] ?? $job);
            $fiber = new Fiber([$this, 'call']);
            $fiber->start($task, $idx);

            if ($fiber->isTerminated()) {
                $this->remove($idx);
                $this->addResult($fiber->getReturn());
                continue;
            }
            
            $this->fibers[$idx] = $fiber;
        }

        $this->reindex();
        return $this->waitForFibers();
    }

    /**
     * Execute a specific job, suspending the fiber if needed, and return the result.
     *
     * @param mixed $job The job to execute, which could be a callable or value.
     * @param int $id The job identifier.
     *
     * @return mixed Return the result of the executed job.
     */
    private function call(mixed $job, int $id): mixed
    {
        $result = $this->isCallable($job) 
            ? $job($this) 
            : $job;
        
        $this->report("Job: {$id} is waiting for response.");
        do {
            if (self::$isFiberSupported && Fiber::getCurrent()) {
                $this->report("Suspending job: {$id} fiber execution.");
                Fiber::suspend();
            }else{
                usleep(1000);
            }
        }while (!($this->isCancelled || $result || $result === false || $result === null || $result === ''));

        return $result;
    }

    /**
     * Execute a task using fork process or directly if Fiber is not supported.
     *
     * @param mixed $job The job to execute.
     * @param int $id The job identifier.
     *
     * @return void
     */
    private function execute(mixed $job, int $id): void
    {
        if (self::$isForkSupported) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                $className = ($this->isCallable($job) && is_array($job)) 
                    ? $job[0]::class 
                    : null;
                $this->report('Queue could not for process for job: ' . $id . '.' . ($className ? ' Class: ' . $className : ''));
                return;
            }

            if ($pid !== 0) {
                $this->pids[$id] = [
                    'job' => $job,
                    'pid' => $pid
                ];
                return; 
            }

            $this->report("Job: {$id} process fork execution completed.");
            exit(0);
        }

        $this->addResult($this->call($job, $id));
        $this->remove($id);
        $this->report("Job: {$id} asynchronous execution completed.");
    }

    /**
     * Wait for all fibers to complete and resume suspended fibers.
     * 
     * @return bool Return true when all fibers are completed.
     */
    private function waitForFibers(): bool 
    {
        while ($this->fibers !== [] && !$this->isCancelled){
            if ($this->timeElapsed()) {
                break;
            }

            foreach ($this->fibers as $idx => $fiber) {
                if($fiber->isTerminated()) {
                    $this->report("Job: {$idx} fiber execution completed.");
                    $this->addResult($fiber->getReturn());
                    unset($this->fibers[$idx]);
                    $this->remove($idx);
                    continue;
                }

                if ($fiber->isSuspended()) {
                    $this->report("Resuming job: {$idx} fiber execution.");
                    try{
                        $fiber->resume();
                    }catch(Exception|FiberError $e){
                        throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                }
            }

            $this->reindex();
            usleep(1000);
        }

        return $this->fibers === [] && !$this->isCancelled;
    }

    /**
     * Waits for all asynchronous execution to complete either forked child processes or default.
     *
     * @return void
     */
    private function waitForAsync(): void
    {
        if (self::$isForkSupported) {
            $this->waitForProcesses();
            return;
        }

        do {
            if ($this->timeElapsed()) {
                $this->isCompleted = true;
                break;
            }

            usleep(1000);
        }while ($this->jobs !== [] && !$this->isCompleted && !$this->isCancelled);
        $this->isCompleted = true;
    }

    /**
     * Waits for all forked child processes to complete and reports their exit status.
     *
     * @return void
     */
    private function waitForProcesses(): void
    {
        $status = 0;
        $message = 'did not exit normally';
      
        foreach ($this->pids as $idx => $fork) {
            if ($this->isCancelled || $this->timeElapsed()) {
                $this->isCompleted = true;
                break;
            }

            while (!$this->isCancelled && pcntl_waitpid($fork['pid'], $status, WNOHANG) == 0) {
                usleep(10000); 
            }

            $this->addResult($this->call($fork['job'], $idx));
            $this->remove($idx);
            $message = pcntl_wifexited($status) 
                ? "exited with status: " . pcntl_wexitstatus($status)
                : $message;

            $this->report("Queue Child process with PID: {$fork['pid']}, {$message}");
        }

        $this->reindex();
        $this->isCompleted = true;
    }

    /**
     * Add result to response variable and invoke onResponse callback function.
     *
     * @param mixed $result The result from executed job.
     *
     * @return void
     */
    private function addResult(mixed $result): void
    {
        if (is_string($result)) {
            $result = trim($result);
            
            if($this->output){
                echo $result . PHP_EOL;
            }
        }

        if($this->callback !== null && is_callable($this->callback)){
            ($this->callback)($result, $this->result, $this);
        }

        array_merge_result($this->result, $result);
    }

    /**
     * Check if an input or queued job is closure or valid callable.
     *
     * @param mixed $input The input to check.
     * 
     * @return bool Return true if the input is a valid callable.
     */
    private function isCallable(mixed $input): bool
    {
        return $input !== null && (is_callable($input) || $input instanceof Closure);
    }

    /**
     * Check if the elapsed time has exceeded the specified timeout.
     * 
     * @return bool Returns true if the timeout has been exceeded, false otherwise.
     */
    private function timeElapsed(): bool 
    {
        $this->waited = microtime(true) - $this->startTime;

        if ($this->timeout > 0 && $this->waited >= $this->timeout) {
            $this->report("Queue execution timeout. Start time: {$this->startTime}, elapsed: {$this->waited} seconds.");
            return true;
        }

        return false;
    }

    /**
     * Reports a message based on the current error reporting configuration.
     * The message can be logged or printed to the output depending on the settings.
     *
     * @param string $message The message to report.
     * 
     * @return void
     */
    private function report(string $message): void 
    {
        if($this->eReporting === self::E_LOG){
            Logger::debug($message);
            return;
        }

        if(!PRODUCTION && $this->eReporting === self::E_OUTPUT){
            echo $message . PHP_EOL;
        }
    }
}