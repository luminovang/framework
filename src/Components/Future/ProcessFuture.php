<?php 
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
use \Luminova\Command\Terminal;
use \Luminova\Interface\Awaitable;
use function \Luminova\Funcs\{root, is_platform};
use \Luminova\Exceptions\{RuntimeException, InvalidArgumentException};

final class ProcessFuture implements Awaitable
{
    private bool $suspended  = false;
    private bool $terminated = false;
    private bool $completed  = false;
    private bool $started  = false;
    private bool $noOutput = false;
    private bool $disabled = false;
    private mixed $result = null;
    private array $response = [];
    private array $command = [];
    private ?string $file = null;
    private ?string $path = null;
    private static ?string $dir = null;

    /**
     * Initialize a Future for a given PID.
     *
     * @param int|null $pid The PID of the asynchronous task, or null if not started.
     */
    public function __construct(private ?int $pid)
    {
        if ($this->pid !== null && $this->pid > 0) {
            $this->createDirectory(); 
            $this->started = true;
            $this->file =  "{$this->path}pid-{$this->pid}-response.txt";
        }
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
        if ($pid !== null && !is_int($pid)) {
            throw new InvalidArgumentException(sprintf(
                'Invalid PID: expected int or null, got %s.',
                get_debug_type($pid)
            ));
        }

        return new static($pid);
    }

    /**
     * Garbage-collect stale PID response files.
     *
     * Removes response files older than the given TTL, optionally skipping
     * a specific PID to avoid deleting a file that is actively being written.
     *
     * @param int $ttl Time-to-live in seconds.
     * @param int|null $ignore PID to exclude from garbage collection.
     *
     * @return void
     */
    public static function gc(int $ttl = 1800, ?int $ignore = null): void
    {
        $pattern = self::getTempPath() . 'pid-*-response.txt';

        foreach (glob($pattern) as $file) {
            if ($ignore !== null && str_contains($file, "pid-{$ignore}-")) {
                continue;
            }

            if (filemtime($file) + $ttl < time()) {
                @unlink($file);
            }
        }
    }

    /**
     * Write response and output for a ProcessFuture safely.
     *
     * Merges existing data if present, ensures arrays for response,
     * concatenates output, and uses file locking to avoid concurrent corruption.
     *
     * @param int $pid The process ID associated with the task.
     * @param mixed $response The response data to write (scalar or array).
     * @param string $output The output text to append.
     *
     * @return bool Return true if the write succeeded, false otherwise.
     */
    public static function write(int $pid, mixed $response, string $output): bool
    {
        $sink = self::getTempPath() . "pid-{$pid}-response.txt";
        $fp = @fopen($sink, 'c+');

        if (!$fp) {
            return false;
        }

        $success = false;

        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }

            $contents = stream_get_contents($fp);
            $existing = [];

            if ($contents !== false && $contents !== '') {
                $decoded = @unserialize($contents);

                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }

            $outputs = $existing['output'] ?? '';
            $responses = $existing['response'] ?? null;

            if ($output) {
                $outputs .= ($outputs !== '') ? "\n" : '';
                $outputs .= $output;
            }

            if ($response) {
                $responses ??= [];

                if (!is_array($responses)) {
                    $responses = [$responses];
                }

                $responses[] = $response;
            }

            rewind($fp);
            ftruncate($fp, 0);

            $success = fwrite($fp, serialize([
                'response' => $responses,
                'output'   => $outputs,
            ])) !== false;

            fflush($fp); 
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);

            if ($success && mt_rand(1, 100) <= 3) {
                self::gc(ignore: $pid);
            }
        }

        return $success;
    }

    /**
     * Get the PID of the Future’s process.
     *
     * @return int|null PID if started, or null if not started.
     */
    public function getPid(): ?int
    {
        return $this->pid;
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
     * Set the result of the ProcessFuture directly.
     *
     * Marks the Future as completed, bypassing the usual background file or process checks.
     *
     * @param array{response:mixed,output:string} $value The value produced by the background task.
     *
     * @return self Return new instance of process future.
     * @throws InvalidArgumentException If output-key is not a string.
     */
    public static function fromValue(array $value): self
    {
        $value += ['response' => null, 'output' => ''];

        if (!is_string($value['output'])) {
            throw new InvalidArgumentException('Output must be a string.');
        }

        $future = new self(getmypid() ?: null);
        $future->disable($value);

        return $future;
    }

    /**
     * Check if the Future has completed (successfully or with exception).
     * 
     * @return bool True if the Future is completed.
     */
    public function isComplete(): bool
    {
        return $this->completed;
    }

    /**
     * Check if the Future can be awaited or resumed.
     *
     * A Future is waitable if it is:
     *  - not completed
     *  - not suspended
     *  - not terminated
     *  - has a valid PID
     *
     * @return bool True if the Future can still run or be awaited.
     */
    public function isWaitable(): bool
    {
        return !$this->noOutput
            && $this->isManageable();
    }

    /**
     * Check if the response file for the ProcessFuture exists.
     *
     * @return bool True if the response file exists, false otherwise.
     */
    public function isFile(): bool
    {
        return !$this->noOutput 
            && $this->file !== null 
            && is_file($this->file);
    }

    /**
     * Check if the Future is currently suspended.
     */
    public function isSuspended(): bool
    {
        return $this->suspended;
    }

    /**
     * Check if the Future has terminated.
     */
    public function isTerminated(): bool
    {
        return $this->terminated;
    }

    /**
     * Check if the Future has started.
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Check if the Future is set to suppress output capture.
     * 
     * @return bool Returns true if output capture is disabled.
     */
    public function isNoOutput(): bool
    {
        return $this->noOutput;
    }

    /**
     * Set whether the Future should suppress output capture.
     * 
     * @param bool $silent True to disable output capture, false to enable it.
     * 
     * @return self Return instance of process future.
     */
    public function noOutput(bool $silent): self 
    {
        $this->noOutput = $silent;

        if($silent){
            $this->response = [];
            $this->result = null;
        }

        return $this;
    }

    /**
     * Start a Future for a background process or PID.
     *
     * This method starts the asynchronous task if it has a prepared command. Sets the
     * PID, creates a response file, and marks the Future as started.
     *
     * Windows:
     * - Attempts to read the PID from a pipe or temporary file after launching.
     * - Uses `popen()` to detach the process.
     *
     * Unix/Linux/macOS:
     * - Executes the command with `exec()` in the background.
     * - Captures PID from command output.
     *
     * @param string|null $cwd Optional working directory to execute the command in.
     *
     * @return bool Return true if the Future was successfully started, false otherwise.
     */
    public function start(?string $cwd = null): bool
    {
        if ($this->started) {
            return true;
        }

        if($this->disabled || $this->command === []){
            return false;
        }

        $this->pid = null;
        $mainCwd = null;

        if ($cwd) {
            $mainCwd = getcwd();
            chdir($cwd);
        }

        if (is_platform('windows')) {
            $pipe = $this->command['pidPipe'] ?? null;

            if ($pipe && is_string($pipe)) {
                $pipe = @fopen($pipe, 'r+') ?: null;
            }

            if ($handler = @popen($this->command['cmd'], 'r')) {
                pclose($handler);
            }

            $this->pid = $pipe ? Terminal::getWindowsAsyncPid($pipe) : null;

            if ($mainCwd) {
                chdir($mainCwd);
            }
        }else{
            $output = [];
            $code = 0;

            exec($this->command['cmd'], $output, $code);

            if ($mainCwd) {
                chdir($mainCwd);
            }

            if ($code === 0 && !empty($output) && is_numeric($output[0])) {
                $this->pid = (int) $output[0];
            }
        }

        if (!$this->noOutput && $this->pid){
            $this->createDirectory(); 
            $this->file = "{$this->path}pid-{$this->pid}-response.txt";
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
        if ($this->disabled || !$this->isManageable()) {
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
        if ($this->disabled || !$this->suspended || $this->pid === null || $this->pid <= 0) {
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
        if ($this->disabled || !$this->isManageable()) {
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
        if ($this->disabled || $this->pid === null || $this->pid <= 0) {
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
        if(!$this->isTickable()){
            return;
        }
  
        $fp = @fopen($this->file, 'r');

        if (!$fp) {
            return;
        }

        $this->response = [];

        try {
            if (flock($fp, LOCK_SH)) { 
                $contents = stream_get_contents($fp);

                if ($contents !== false && $contents !== '') {
                    $decoded = @unserialize($contents);

                    if (is_array($decoded)) {
                        $this->response = $decoded;
                    }
                }

                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }

        $this->tok();
    }

    /**
     * Get the main result of the Future.
     *
     * @return mixed Return the value produced by the task.
     * @throws RuntimeException If the Future is suspended, terminated, or not yet completed.
     */
    public function value(): mixed
    {
        $this->assert('value');
        return $this->result;
    }

    /**
     * Get the full response data of the Future.
     *
     * Returns the array containing both 'response' and 'output'.
     *
     * @return array{response:mixed,output:string} Return the response and output produced by the task.
     * @throws RuntimeException If the Future is suspended, terminated, or not yet completed.
     */
    public function response(): array
    {
        $this->assert('value');
        $response = $this->response;

        $this->response = [];
        return $response + [
            'response' => null, 
            'output' => ''
        ];
    }

    /**
     * Wait for the Future to complete and return its result.
     *
     * Blocks execution until the asynchronous task completes or the optional
     * timeout is reached. Throws if the Future is suspended, terminated, or
     * fails to produce a result within the allowed time.
     *
     * @param int $timeout Maximum time in seconds to wait (0 = no limit).
     * @param float $delay Poll interval in seconds between ticks (default 0.1s).
     *
     * @return mixed Return the value produced by the completed task.
     * @throws RuntimeException If the Future is suspended, terminated, already completed,
     *                          or fails to produce a result within the timeout.
     */
    public function await(int $timeout = 0, float $delay = 0.1): mixed
    {
        $this->start();
        $this->assert('await');

        if (!$this->isWaitable()) {
            throw new RuntimeException('This future is not waitable.');
        }

        if (Terminal::waitForProcess($this->pid, $timeout, $delay) === null) {
            throw new RuntimeException(
                "Future did not complete within {$timeout} seconds."
            );
        }

        $start = microtime(true);
        $grace = max(0.1, min(1.0, $delay * 5));
        $delay = max(1_000, (int) ($delay * 1_000_000));

        while ($this->result === null) {
            $this->tick();

            if ($this->suspended) {
                throw new RuntimeException('Future was suspended during await.');
            }

            if ($this->terminated) {
                throw new RuntimeException('Future was terminated during await.');
            }

            if ((microtime(true) - $start) >= $grace) {
                break;
            }

            usleep($delay);
        }

        if ($this->result === null) {
            throw new RuntimeException(
                'Future completed but produced no result.'
            );
        }

        $this->completed = true;
        return $this->value();
    }

    /**
     * Prepare a PHP CLI command to run asynchronously in the background.
     *
     * Converts arguments to shell-safe format, handles Windows pipes or files,
     * and constructs a full background command for Linux/macOS or Windows.
     *
     * @param array<string,mixed> $arguments Key-value arguments to pass to future worker.
     *                   Arrays are JSON-encoded automatically.
     * @param string|null $phpPath Path to PHP CLI binary (auto-detected if null).
     *
     * @return self Returns instance of Future with the prepared command.
     * @throws RuntimeException If PHP CLI cannot be located.
     */
    public function build(array $arguments, ?string $phpPath = null): self 
    {
        if($this->disabled){
            return $this;
        }

        $phpPath ??= Terminal::whichPhp(true);

        if (!$phpPath) {
            throw new RuntimeException('Unable to locate PHP CLI executable.');
        }

        $this->createDirectory();

        $args = [];
        $isWindows = is_platform('windows');

        foreach ($arguments as $key => $value) {
            $args[] = escapeshellarg($key . '=' . (
                is_array($value)
                    ? json_encode($value, JSON_UNESCAPED_UNICODE)
                    : (string) $value
            ));
        }

        if ($isWindows) {
            $wid = bin2hex(random_bytes(6));
            $pidPipe = "\\\\.\\pipe\\luminova_future_background_async_{$wid}";

            if ($pipe = @fopen($pidPipe, 'r+')) {
                $this->command['pidPipe'] = $pidPipe;
                fclose($pipe);
            } else {
                $pidPipe = "{$this->path}w_pid_{$wid}.txt";
                $this->command['pidPipe'] = $pidPipe;
            }
            
            $args[] = escapeshellarg("pid_pipe=$pidPipe");
        }

        $phpPath = escapeshellarg($phpPath);
        $phpFile = escapeshellarg(root('/bootstrap/', 'worker.php'));
        $command = "$phpPath -f $phpFile";

        if($args !== []){
            $command .= ' ' . implode(' ', $args);
        }

        if ($isWindows) {
            $this->command['cmd'] = "start /B $command";
            return $this;
        }

        $log = PRODUCTION ? '/dev/null' : root('/writeable/logs/', 'bg_worker.log');
        $command .= " > " . escapeshellarg($log) . " 2>&1 & echo $!";

        $this->command['cmd'] = $command;
        return $this;
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
        $value = $this->response['response'] ?? null;

        if ($value === null) {
            $value = $this->response['output'] ?? null;
        }

        $this->result = $value;

        @unlink($this->file);
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
            && $this->pid !== null
            && $this->pid > 0;
    }

    /**
     * Check if the Future can be ticked for response collection.
     *
     * A Future is tickable if it is manageable and has a response file.
     *
     * @return bool True if the Future can be ticked.
     */
    private function isTickable(): bool
    {
       return $this->isManageable() && $this->isFile();
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
        $this->path = self::getTempPath();

        if (is_dir($this->path)) {
            return;
        }

        if (@mkdir($this->path, 0777, true) && is_dir($this->path)) {
            return;
        }

        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'luminova';
        $worker = $base . DIRECTORY_SEPARATOR . 'worker';

        foreach ([$base, $worker] as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
                throw new RuntimeException(sprintf(
                    'Failed to create async worker directory: %s',
                    $dir
                ));
            }
        }
    }

    /**
     * Get the path to the temporary worker directory.
     *
     * On production systems, attempts to use the system temp directory if writable.
     * Otherwise, defaults to the application's `writeable/worker` directory.
     *
     * @return string Fully-qualified path to the worker directory.
     */
    private static function getTempPath(): string
    {
        if (self::$dir !== null) {
            return self::$dir;
        }

        if (PRODUCTION) {
            $baseTemp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

            if (is_writable($baseTemp)) {
                self::$dir = $baseTemp 
                    . DIRECTORY_SEPARATOR . 'luminova' 
                    . DIRECTORY_SEPARATOR . 'worker'
                    . DIRECTORY_SEPARATOR;
                return self::$dir;
            }
        }

        self::$dir = root('/writeable/worker');
        return self::$dir;
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
        $this->disabled = true;
        $this->suspended = false;
        $this->started = true;
        $this->command = [];
        $this->terminated = true;
        $this->result = $value['response'] 
            ?? $value['output'] 
            ?? null;

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
        if($this->disabled && $type === 'value'){
            return;
        }

        if ($this->suspended) {
            throw new RuntimeException(
                ($type === 'value') 
                    ? 'Cannot retrieve value from a suspended Future.'
                    : 'Cannot await a future that is suspended.'
            );
        }

        if ($this->terminated) {
            throw new RuntimeException(
                ($type === 'value') 
                    ? 'Cannot retrieve value from a terminated Future.'
                    : 'Cannot await a future that has been terminated.'
            );
        }

        if($type === 'value' && !$this->completed) {
           throw new RuntimeException('Future is not completed.');
        }
        
        if($type === 'await' && $this->completed){
            throw new RuntimeException('Cannot await a future that has already completed.');
        }
    }
}