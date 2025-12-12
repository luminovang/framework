<?php 
/**
 * Luminova Framework Remove SSH connection
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command;

use \Error;
use \Throwable;
use function \Luminova\Funcs\is_platform;

final class Result
{
    /**
     * Output split into lines.
     * 
     * @param array<int,string> $lines
     */
    private array $lines = [];

    /**
     * Default options for command execution.
     *
     * These options are used when executing commands via Terminal::run()
     * and can be overridden by passing a custom options array.
     * 
     * @var array{async:bool,cwd:?string,env:?array,options:?array,timeout:int,blocking:bool,throwOnError:bool,mergeStderr:bool}
     */
    public const DEFAULT_RUN_OPTIONS = [
        'async'        => false,
        'cwd'          => null,
        'env'          => null,
        'options'      => null,
        'timeout'      => 0,
        'blocking'     => true,
        'throwOnError' => false,
        'mergeStderr'  => true,
    ];

    /**
     * Command execution result container.
     *
     * Holds both synchronous and asynchronous execution data,
     * including exit code, output, parsed lines, process ID,
     * and execution metadata such as arguments and options.
     * 
     * @param int $exitCode Exit code of the executed command.
     * @param string $output Raw output from the command execution.
     * @param int|null $pid Process ID for async executions.
     * @param bool $hasError Whether command failed with error.
     * @param bool $async Whether the command was executed asynchronously.
     * @param array<string,mixed> $args Original command arguments.
     * @param array<string,mixed> $options Execution options used for this command.
     * @param array<string,mixed> $status The executed process status details.
     * 
     * @see Luminova\Command\Terminal::run() for how these values are populated.
     */
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
        public readonly ?int $pid,
        public readonly bool $hasError,
        private bool $async = false,
        private array $args = [],
        private array $options = [],
        private array $status = []
    ) {}

    /**
     * Determine if the command executed successfully.
     * 
     * @return bool True if exit code is 0, indicating success; false otherwise.
     */
    public function isSuccess(): bool
    {
        return !$this->hasError && $this->exitCode === 0;
    }

    /**
     * Check whether this result supports process tracking.
     *
     * A runnable result means the process was started asynchronously
     * and a valid PID exists.
     * 
     * @return bool True if this result is from an async execution with a valid PID; false otherwise.
     */
    public function isRunnable(): bool
    {
        return $this->async && $this->pid !== null;
    }

    /**
     * Check if the process is still running (async only).
     *
     * Uses OS-level process detection:
     * - Windows: tasklist lookup
     * - Unix: /proc filesystem check
     * 
     * @return bool True if the process is running; false if it has exited or if this result is not runnable.
     */
    public function isRunning(): bool
    {
        if (!$this->isRunnable()) {
            return false;
        }

        if (is_platform('windows')) {
            $out = shell_exec("tasklist /FI \"PID eq {$this->pid}\"");
            return str_contains((string) $out, (string) $this->pid);
        }

        return file_exists("/proc/{$this->pid}");
    }

    /**
     * Forcefully terminate the process if it is running.
     *
     * Uses:
     * - Windows: taskkill /F
     * - Unix: SIGKILL (9)
     * 
     * @return bool True if the process was successfully terminated or was not running; false if termination failed.
     */
    public function kill(): bool
    {
        if (!$this->isRunnable()) {
            return true;
        }

        if (is_platform('windows')) {
            shell_exec("taskkill /PID {$this->pid} /F");
            return true;
        }

        return posix_kill($this->pid, 9);
    }

    /**
     * Get information about executed process.
     * 
     * This also include start and end process execution time. 
     *
     * @return array{
     *      command: string, 
     *      pid: int, 
     *      running: bool, 
     *      signaled: bool, 
     *      stopped: bool, 
     *      exitcode: int, 
     *      termsig: int,
     *      stopsig: int, 
     *      start:float,
     *      end:?float
     * }
     */
    public function getStatus(): array 
    {
        return $this->status;
    }

    /**
     * Check whether this result represents an async execution.
     * 
     * @return bool True if the command was executed asynchronously; false otherwise.
     */
    public function isAsync(): bool
    {
        return $this->async;
    }

    /**
     * Get process ID if available.
     * 
     * @return int|null The PID of the process if this is an async execution; null otherwise.
     */
    public function getPid(): ?int
    {
        return $this->pid;
    }

    /**
     * Undocumented function
     *
     * @return Throwable|null
     */
    public function getError(): ?Throwable
    {
        if(!$this->hasError){
            return null;
        }

        return new Error($this->output, $this->exitCode);
    }

    /**
     * Get execution options used for this command.
     *
     * Returns default options when none were provided.
     * 
     * @return array{async:bool,cwd:?string,env:?array,options:?array,timeout:int,blocking:bool,throwOnError:bool,mergeStderr:bool} The options array used for this command execution.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get original command arguments.
     * 
     * @return array The array of arguments that were passed to the command execution.
     */
    public function getArguments(): array
    {
        return $this->args;
    }

    /**
     * Get process exit code.
     * 
     * @return int The exit code returned by the executed command.
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * Get raw command output.
     * 
     * @return string The full output string produced by the command execution.
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Get output lines.
     *
     * @return array<int, string> An array of output lines, split by newline characters.
     */
    public function getResult(): array
    {
        if($this->hasError){
            return [];
        }

        if($this->lines !== []){
            return $this->lines;
        }

        return $this->lines = ($this->output === '') ? [] : explode("\n", $this->output);
    }
}