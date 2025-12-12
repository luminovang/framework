<?php 
declare(strict_types=1);
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Components\Future;

use \Throwable;
use Luminova\Luminova;
use Luminova\Logger\Logger;
use Luminova\Interface\Awaitable;
use Luminova\Command\{Worker, Terminal};
use function Luminova\Funcs\{root, temp_dir, is_platform};
use Luminova\Exceptions\{LuminovaException, RuntimeException, InvalidArgumentException};

final class ProcessFuture implements Awaitable
{
    /**
     * @var bool $suspended
     */
    private bool $suspended  = false;

    /**
     * @var bool $terminated
     */
    private bool $terminated = false;

    /**
     * @var bool $completed
     */
    private bool $completed  = false;

    /**
     * @var bool $started
     */
    private bool $started  = false;

    /**
     * @var bool $isAwaitable
     */
    private bool $isAwaitable = false;

    /**
     * @var bool $isResult
     */
    private bool $isResult = true;

    /**
     * @var bool
     */
    private bool $failed = false;

    /**
     * @var array $response
     */
    private array $response = [];

    /**
     * @var array $command
     */
    private array $command = [];

    /**
     * @var string|null $file
     */
    private ?string $file = null;

    /**
     * @var string|null $path
     */
    private ?string $path = null;

    /**
     * @var string|null $pidPipe
     */
    private ?string $pidPipe = null;

    /**
     * @var (callable(array $args): mixed) $cliHandler
     */
    protected mixed $cliHandler = null;

    /**
     * @var array $cliHandlerArgs
     */
    protected array $cliHandlerArgs = [];

    /**
     * File handler
     *
     * @var resource|null $fp
     */
    private mixed $fp = null;

    /**
     * CLI mode.
     *
     * @var bool|null
     */
    private static ?bool $isCommand = null;

    /**
     * Initialize a Future for a given PID.
     *
     * @param int|null $pid The PID of the asynchronous task, or null if not started.
     */
    public function __construct(private ?int $pid = null)
    {
        self::$isCommand ??= Luminova::isCommand();

        if(self::$isCommand){
            return;
        }

        if ($this->isPid()) {
            $this->createDirectory(); 
            $this->started = true;
            $this->file = "{$this->path}tr-{$this->pid}.txt";
        }
    }

    /**
     * Close handler
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Create a Future for an existing asynchronous task.
     *
     * @param int|null $pid The PID of the task, or null if not started.
     *
     * @return static Return new instance of ProcessFuture.
     * @throws InvalidArgumentException If $pid is not an integer or null.
     */
    public static function async(mixed $pid): static
    {
        if ($pid !== null && (!is_int($pid) || $pid < 1)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid PID: expected int or null, got %s.',
                get_debug_type($pid)
            ));
        }

        return new static($pid);
    }

    /**
     * Get the PID of the Future’s process.
     *
     * @return int|null PID if started, or null if not started.
     */
    public function getPid(): ?int
    {
        if($this->isPid()){
            return $this->pid;
        }

        if($this->pidPipe === null || !is_file($this->pidPipe)){
            return null;
        }

        return $this->pid = Terminal::readPidPipe($this->pidPipe);
    }

    /**
     * Get the filename for the Future's response and output..
     *
     * @return string|null The response file path, or null if not set.
     */
    public function getFile(): ?string
    {
        return $this->file;
    }

    /**
     * Get the directory path for the future's response and output.
     *
     * @return string|null The response path, or null if not set.
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * {@inheritDoc}
     */
    public function isCompleted(): bool
    {
        return $this->completed;
    }
    
    /**
     * {@inheritDoc}
     */
    public function isComplete(): bool
    {
        return $this->completed;
    }

    /**
     * {@inheritDoc}
     *
     * A Future is waitable if it is:
     *  - not completed
     *  - not suspended
     *  - not terminated
     *  - has a valid PID
     */
    public function isWaitable(): bool
    {
        return $this->isAwaitable
            && $this->isManageable();
    }

    /**
     * Check if the response file for the ProcessFuture exists.
     *
     * @return bool True if the response file exists, false otherwise.
     */
    public function isFile(): bool
    {
        return $this->isAwaitable 
            && $this->file !== null 
            && is_file($this->file);
    }

    /**
     * {@inheritDoc}
     */
    public function isSuspended(): bool
    {
        return $this->suspended;
    }

    /**
     * {@inheritDoc}
     */
    public function isTerminated(): bool
    {
        return $this->terminated;
    }

    /**
     * {@inheritDoc}
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * {@inheritDoc}
     */
    public function isRunning(): bool
    {
        return $this->started 
            && !$this->completed 
            && !$this->terminated 
            && !$this->suspended;
    }

    /**
     * {@inheritDoc}
     */
    public function isFailed(): bool
    {
        return $this->failed 
            || self::isError();
    }

    /**
     * {@inheritDoc}
     */
    public function isPending(): bool
    {
        return !$this->started;
    }

    /**
     * Enable or disable awaiting the Future result.
     *
     * Marks the Future as awaitable, allowing its result to be retrieved after
     * completion. When disabled, the Future can run without being awaited for a
     * response.
     *
     * @param bool $awaitable Whether the Future can be awaited.
     *
     * @return self Returns the current Future instance.
     */
    public function setAwaitable(bool $awaitable = true): self
    {
        $this->isAwaitable = $awaitable;
        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * Windows:
     * - Attempts to read the PID from a pipe or temporary file after launching.
     * - Uses `popen()` to detach the process.
     *
     * Unix/Linux/macOS:
     * - Executes the command with `exec()` in the background.
     * - Captures PID from command output.
     */
    public function start(): bool
    {
        if ($this->started) {
            return true;
        }

        if($this->completed){
            return false;
        }

        if($this->cliHandler){
            if(!is_callable($this->cliHandler)){
                return false;
            }

            $this->pid = $this->call();
            
            return true;
        }

        if($this->command === []){
            return false;
        }

        $this->pidPipe = $this->command['pid-pipe'] ?? null;
        $this->pid = Terminal::async(
            $this->command['cmd'],
            [
                'pid-pipe'   => $this->pidPipe,
                //'stdout'   => $this->command['stdout'] ?? null,
                'stderr'     => $this->command['stderr'] ?? null,
                'cwd'        => APP_ROOT
            ]
        );

        if ($this->isAwaitable && $this->pid){
            $this->createDirectory(); 
            $this->file = "{$this->path}tr-{$this->pid}.txt";
        }

        $this->started = true;
        $this->command = [];
        return true;
    }

    /**
     * Suspend execution of the Future.
     *
     * @return bool True if the process was suspended, false otherwise.
     */
    public function suspend(): bool
    {
        if (!$this->isManageable()) {
            return false;
        }

        if (is_platform('windows') || !function_exists('posix_kill')) {
            return false;
        }

        if (posix_kill($this->pid, SIGSTOP)) {
            $this->suspended = true;
            return true;
        }

        return false;
    }

    /**
     * Resume execution of a suspended Future.
     *
     * @return bool True if the process was resumed, false otherwise.
     */
    public function resume(): bool
    {
        if (
            $this->completed 
            || !$this->suspended 
            || !$this->isPid()
        ) {
            return false;
        }

        if (is_platform('windows') || !function_exists('posix_kill')) {
            return false;
        }

        if (posix_kill($this->pid, SIGCONT)) {
            $this->suspended = false;
            return true;
        }

        return false;
    }

    /**
     * Terminate the Future by requesting its process to exit.
     *
     * @return bool True if a termination signal was sent.
     */
    public function terminate(): bool
    {
        if (!$this->isManageable()) {
            return false;
        }

        return $this->cancel(false);
    }

    /**
     * Forcefully kill the Future process.
     *
     * @return bool True if a kill signal was sent.
     */
    public function kill(): bool
    {
        if ($this->completed || !$this->isPid()) {
            return false;
        }

        return $this->cancel(true);
    }

    /**
     * Remove any pending output produced by the Future.
     *
     * This clears the result file without affecting process execution.
     *
     * @return bool True if the output file was removed, false otherwise.
     */
    public function flush(): bool
    {
        return $this->isFile() && @unlink($this->file);
    }

    /**
     * Poll the ProcessFuture for completion and collect its result.
     *
     * Safely reads the response file using shared locks, updates the Future's
     * response and result, and marks it as completed. Subsequent calls
     * become no-ops. Cleans up the file if necessary.
     *
     * @return void
     */
    public function tick(): void
    {
        if (!$this->isReadable()) {
            return;
        }

        if (!$this->fp) {
            clearstatcache(true, $this->file);

            $this->fp = @fopen($this->file, 'r');

            if (!$this->fp) {
                return;
            }
        }

        try {
            if (!flock($this->fp, LOCK_SH)) {
                return;
            }

            rewind($this->fp);

            $contents = trim((string) stream_get_contents($this->fp));

            flock($this->fp, LOCK_UN);

            if ($contents !== '') {
                $decoded = @unserialize($contents);

                if (is_array($decoded)) {
                    $pid = (int) ($decoded['pid'] ?? 0);

                    if ($pid !== (int) $this->pid) {
                        $this->completed = true;
                        $this->isResult = false;
                        $this->failed = true;

                        return;
                    }

                    $this->response = $decoded;
                }
            }
        } finally {
            $this->tok();
        }
    }

    /**
     * {@inheritDoc}
     * 
     * @see self::output()
     */
    public function value(): mixed
    {
        $this->checkValue();

        return $this->getReturn();
    }

    /**
     * Get the complete response from the Future.
     *
     * Returns both the task result and any captured output as an associative array.
     *
     * @return array{pid:int,response?:mixed,output?:string,error?:array} The task response and captured output.
     * @throws RuntimeException If the Future is suspended, terminated, or has not completed.
     * 
     * @deprecated version
     */
    public function response(): array
    {
        $this->checkValue();

        return $this->response;
    }

    /**
     * Get the captured output.
     *
     * Returns the output captured while the task was executing.
     *
     * @return string|null The captured output, or null if no output was captured.
     *
     * @throws RuntimeException If the Future is suspended, terminated, or has not completed.
     */
    public function output(): ?string
    {
        $this->checkValue();
        return $this->response['output'] ?? null;
    }

    /**
     * Wait for the Future to complete and return its result.
     *
     * Blocks execution until the asynchronous task completes or the optional
     * timeout is reached. Throws if the Future is suspended, terminated, or
     * fails to produce a result within the allowed time.
     *
     * @param int $timeout Maximum time in seconds to wait (0 = no limit).
     * @param float|int $delay Poll interval in seconds between ticks (default 0.1s).
     *
     * @return Throwable|mixed Return the value produced by the completed task.
     * @throws RuntimeException If the Future is suspended, terminated, already completed,
     *                          or fails to produce a result within the timeout.
     */
    public function await(int $timeout = 0, float|int $delay = 0.1): mixed
    {
        $this->start();

        if($this->completed){
            return $this->value();
        }

        $this->assert('await');

        if (!$this->isWaitable()) {
            throw new RuntimeException(sprintf(
                'This future task%s is not waitable.',
                $this->isPid() ? " with PID #{$this->pid}" : ''
            ));
        }

        if (Terminal::waitForProcess($this->pid, $timeout, $delay) === null) {
            throw new RuntimeException(sprintf(
                'This future task%s is not waitable.',
                $this->isPid() ? " with PID #{$this->pid}" : ''
            ));
        }

        $this->response = [];

        $start = microtime(true);
        $grace = max(0.1, min(1.0, $delay * 5));

        while (!$this->isCompleted()) {
            $this->tick();

            if ($this->suspended) {
                throw new RuntimeException(
                    "Task #{$this->pid} was suspended during await."
                );
            }

            if ($this->terminated) {
                throw new RuntimeException(
                    "Task #{$this->pid} was terminated during await."
                );
            }

            if ($timeout > 0 && (microtime(true) - $start) >= ($timeout + $grace)) {
                throw new RuntimeException(
                    "Task #{$this->pid} did not complete within {$timeout} seconds."
                );
            }

            uwait($delay);
        }

        if (!$this->isResult) {
            throw new RuntimeException(
                "Task PID #{$this->pid} completed with no returned result."
            );
        }
        
        return $this->getReturn();
    }

    /**
     * Create a Future for asynchronously CLI execution in the background.
     *
     * Converts arguments to shell-safe format, handles Windows pipes or files,
     * and constructs a full background command for Linux/macOS or Windows.
     *
     * @param (callable(array $args): mixed)|string $task Task callable or PHP code to execute.
     * @param array $arguments Optional arguments to pass to worker handler.
     * @param string|null $phpPath Path to PHP CLI binary (auto-detected if null).
     *
     * @return self Returns instance of Future with the prepared command.
     * @throws RuntimeException If PHP CLI cannot be located.
     */
    public function build(callable|string $task, array $arguments, ?string $phpPath = null): self 
    {
        if($this->completed){
            return $this;
        }

        if(self::$isCommand){
            if(!is_callable($task)){
                $task = static fn(array $arguments): mixed => eval(
                    Worker::serialize($task)
                );
            }

            $this->cliHandler = $task;
            $this->cliHandlerArgs = $arguments;
            return $this;
        }

        $this->createDirectory();
        $this->pidPipe = null;

        if (is_platform('windows')) {
            $this->pidPipe = Terminal::createTempPipePath('luminova_async_background');
            
            if($this->pidPipe === null ){
                $id = bin2hex(random_bytes(6));
                $this->pidPipe = "{$this->path}.pid/t{$id}.txt";
            }
            
            $this->command['pid-pipe'] = $this->pidPipe;
        }

        $this->command['cmd'] = Worker::build(
            $task, 
            $arguments, 
            $phpPath, 
            $this->pidPipe,
            $this->isAwaitable,
        );

        if($this->isAwaitable){
            $this->command['stderr'] = root('/writeable/logs/', 'background.log');
        }

        return $this;
    }

    /**
     * Resolve response result.  
     *
     * @return Throwable|mixed
     */
    private function getReturn(): mixed
    {
        static $e = null;

        if(!self::isError()){
            return $this->response['response'] ?? null;
        }

        if($e !== null){
            return $e;
        }

        $error = (array) $this->response['error'];
        $file = $error['file'] ?? 'unknown';
        $line = (int) ($error['line'] ?? 0);

        return $e = self::exception(
            $error['class'] ?? null,
            $error['message'] ?? sprintf(
                "[PID #%d] Background task failed in %s:%d",
                $this->response['pid'] ?? $this->pid,
                $file,
                $line
            ),
            (int) ($error['code'] ?? 0),
            $file,
            $line
        );
    }

    /**
     * Check if task failed with response.
     *
     * @return bool
     */
    private function isError(): bool
    {
        return isset($this->response['error'])
            && (array) ($this->response['error'] ?? []) !== [];
    }

    /**
     * Build exception from response.
     *
     * @param string|null $class
     * @param string $message
     * @param int $code
     * @param string $file
     * @param int $line
     * 
     * @return Throwable
     */
    private static function exception(
        ?string $class,
        string $message,
        int $code,
        string $file,
        int $line
    ): Throwable
    {
        if (
            $class === null ||
            !class_exists($class) ||
            !is_a($class, Throwable::class, true)
        ) {
            $class = RuntimeException::class;
        }

        if (is_a($class, \ErrorException::class, true)) {
            return new $class($message, $code, filename: $file, line: $line);
        }

        $exception = new $class($message, $code);

        if ($exception instanceof LuminovaException) {
            $exception->setFile($file)->setLine($line);
        }

        return $exception;
    }

    /**
     * Set response data after tick found response file.
     * 
     * @return void
     */
    private function tok(): void
    {
        if ($this->response === []) {
            return;
        }

        $this->completed = true;
        $this->isResult = true;

        $this->flush();
        $this->close();
    }

    /**
     * Close handler.
     *
     * @return void
     */
    private function close(): void
    {
        if (is_resource($this->fp)) {
            fclose($this->fp);
            $this->fp = null;
        }
    }

    /**
     * Tick and assert value
     *
     * @return void
     */
    private function checkValue(): void 
    {
        if($this->completed){
            return;
        }

        $this->tick();
        $this->assert('value');
    }

    /**
     * Check if the Future can be managed (suspended, resumed, terminated).
     *
     * A Future is manageable if it is:
     *  - not completed
     *  - not suspended
     *  - not terminated
     *  - has a valid PID
     *
     * @return bool True if the Future can be managed.
     */
    private function isManageable(): bool
    {
        return !$this->completed
            && !$this->suspended
            && !$this->terminated
            && $this->isPid();
    }

    /**
     * Check if pid is valid.
     *
     * @return bool
     */
    private function isPid(): bool 
    {
        return $this->pid !== null 
            && $this->pid > 0;
    }

    /**
     * Check if the Future can be ticked for response collection.
     *
     * A Future is scannable if it is manageable and has a response file.
     *
     * @return bool True if the Future can be ticked.
     */
    private function isReadable(): bool
    {
       return $this->isManageable() 
            && $this->isFile();
    }

    /**
     * Execute the registered CLI handler.
     *
     * Runs the configured command handler, captures any buffered output, and returns
     * the current process ID (PID). Any exception thrown by the handler is captured
     * and treated as the task response.
     *
     * When output is enabled, the response and captured output are stored for later
     * retrieval. When output is disabled, exceptions are logged before returning.
     *
     * @return int|null The current process ID, or null if execution failed.
     */
    private function call(): ?int 
    {
        ob_start();
        $pid = getmypid();

        $isError = false;
        $response = ['pid' => $pid];

        try {
            if(!$this->isAwaitable){
                ($this->cliHandler)($this->cliHandlerArgs);
                $this->disable([]);

                return $pid;
            }

            $response['response'] = ($this->cliHandler)($this->cliHandlerArgs);
            $response['response'] = trim(ob_get_clean() ?: '');
        } catch (Throwable $e) {
            $isError = true;
            $response['error'] = [
                'class'   => get_class($e),
                'code'    => $e->getCode(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'message' => $e->getMessage()
            ];
        } finally {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $this->cliHandler = null;
            $this->cliHandlerArgs = [];
        }

        if($isError && !$this->isAwaitable){
            $message = $response['error']['message'];
            unset($response['error']['message']);

            Logger::tryLog(
                'exception',
                sprintf(
                    'Task PID #%d failed with exception: %s.',
                    $pid,
                    $message
                ),
                $response
            );
            
            $this->disable([]);
            return $pid;
        }

        $this->disable($response);

        return $pid;
    }

    /**
     * Ensure the async worker directory exists.
     *
     * Tries multiple fallback locations:
     * 1. Uses `getTempPath()` if available.
     * 2. Falls back to system temp directory under `luminova/worker`.
     * 3. Finally, falls back to `writeable/worker` in the application root.
     *
     * @throws RuntimeException If the directory cannot be created.
     */
    private function createDirectory(): void
    {
        $this->path ??= temp_dir('worker', fromLocal: !PRODUCTION);

        if (is_dir($this->path)) {
            return;
        }

        if (mkdir($this->path, 0755, true) && is_dir($this->path)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Failed to create async background temp directory: %s',
            $this->path
        ));
    }

    /**
     * Forcefully terminate the associated process.
     *
     * Uses `Terminal::killProcess()` to send a termination signal.
     * Marks the Future as terminated and cleans up the worker file.
     *
     * @param bool $force Whether to force termination (SIGKILL on Unix, /F on Windows).
     * 
     * @return bool Return true if the process was successfully terminated, false otherwise.
     */
    private function cancel(bool $force): bool
    {
        if (Terminal::killProcess($this->pid, $force)) {
            $this->terminated = true;
            $this->flush();
            return true;
        }

        return false;
    }

    /**
     * Disable the Future and set its response directly.
     *
     * Marks the Future as disabled, completed, and terminated.
     * Sets the response and result values accordingly.
     *
     * @param array{response:mixed,output:string} $value The value to set for the Future.
     *
     * @return void
     */
    private function disable(array $value): void
    {
        $this->response = $value;
        $this->completed = true;
        $this->suspended = false;
        $this->started = true;
        $this->command = [];
        $this->terminated = true;

        $this->flush();
    }

    /**
     * Assert that the Future is in a valid state for a specific operation.
     *
     * Throws a RuntimeException if the Future is suspended, terminated, 
     * or not completed when attempting to retrieve the value or await it.
     *
     * @param string $type Operation type: 'value' for retrieving value, 'await' for awaiting.
     *
     * @throws RuntimeException If the Future is in an invalid state.
     */
    private function assert(string $type): void
    {
        if($this->completed && $type === 'value'){
            return;
        }

        $error = match (true){
            $this->suspended => ($type === 'value') 
                ? 'Cannot retrieve value from a suspended Future.'
                : 'Cannot await a future that is suspended.',
            $this->terminated => ($type === 'value') 
                ? 'Cannot retrieve value from a terminated Future.'
                : 'Cannot await a future that has been terminated.',
            $type === 'value' && !$this->completed 
                => "Future PID #{$this->pid} is not completed.",
            $type === 'await' && $this->completed =>  'Cannot await a future that has already completed.',
            default => null
        };

        if($error === null){
            return;
        }

        throw new RuntimeException($error);
    }

    /**
     * Check if output capturing is suppressed .
     *
     * @return bool
     * @deprecated Use isAwaitable() instead
     */
    public function isNoOutput(): bool
    {
        return false;
    }

    /**
     * Suppress output capturing.
     * 
     * Set whether the Future should suppress output capture.
     * 
     * @param bool $suppress True to disable output capture, false to enable it.
     * 
     * @return self Return instance of process future.
     * @deprecated 
     */
    public function noOutput(bool $suppress = true): self 
    {
        return $this;
    }
}