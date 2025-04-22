<?php
/**
 * Luminova Framework process executor.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Utils;

use \Luminova\Storages\Stream;
use \Luminova\Command\Terminal;
use \Psr\Http\Message\StreamInterface;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\BadMethodCallException;
use \Luminova\Exceptions\InvalidArgumentException;
use \Closure;
use \Fiber;
use \FiberError;
use \Exception;
use \Generator;

class Process
{
    /**
     * Flag indicating whether fiber is supported.
     * 
     * @var bool $isFiberSupported
     */
    private static bool $isFiberSupported = false;

    /**
     * The process open flag.
     * 
     * @var bool $open
     */
    private bool $open = false;

    /**
     * The running process.
     * 
     * @var mixed $process
     */
    private mixed $process = null;

    /**
     * Process response.
     * 
     * @var mixed $response
     */
    private mixed $response = null;

    /**
     * Process waiting status.
     * 
     * @var bool $isComplete
     */
    private bool $isComplete = false;

    /**
     * @var float|null $startTime
     */
    private ?float $startTime = null;

    /**
     * @var array $metadata
     */
    private array $metadata = [];

    /**
     * Options for proc_open command executor.
     * 
     * @var array $options
     */
    private array $options = [
        'suppress_errors'   => true, 
        'bypass_shell'      => true
    ];

    /**
     * Supported options for proc_open command executor.
     * 
     * @var array $supportedOptions
     */
    private static array $supportedOptions = [
        'blocking_pipes', 
        'create_process_group', 
        'create_new_console', 
        'detach_process', 
        'use_pty', 
        'inherit_environment', 
        'redirect_stderr'
    ];

    /**
     * Descriptors for proc_open command executor.
     * 
     * @var array $options
     */
    private array $descriptors = [];

    /**
     * proc_open pipes.
     * 
     * @var array $pipes
     */
    private array $pipes = [];

    /**
     * Instance of fiber to resume.
     * 
     * @var Fiber|null $fiber
     */
    private ?Fiber $fiber = null;

    /**
     * Mode for popen command executor.
     * 
     * @var string $mode
     */
    private ?string $mode = null;

    /**
     * Command execution with popen.
     * 
     * @var string EXECUTOR_POPEN
     */
    public const EXECUTOR_POPEN = 'popen';

    /**
     * Command execution with exec.
     * 
     * @var string EXECUTOR_EXEC
     */
    public const EXECUTOR_EXEC = 'exec';

    /**
     * Command execution with shell_exec.
     * 
     * @var string EXECUTOR_SHELL
     */
    public const EXECUTOR_SHELL = 'shell_exec';

    /**
     * Command execution with proc_open.
     * 
     * @var string EXECUTOR_PROC_OPEN
     */
    public const EXECUTOR_PROC_OPEN = 'proc_open';

    /**
     * Callback execution flag.
     * 
     * @var string EXECUTOR_CALLBACK
     */
    public const EXECUTOR_CALLBACK = 'callback';

    /**
     * Stream execution flag.
     * 
     * @var string EXECUTOR_STREAM
     */
    public const EXECUTOR_STREAM = 'stream';

    /**
     * Process constructor.
     *
     * @param StreamInterface|Stream|Closure|resource|callable|array|string $input Command string, callable, or stream to be executed.
     * @param string $executor The process executor type (e.g, `Process::EXECUTOR_*`). 
     *              Can be one of the defined executor constants.
     * @param string|null $cwd Optional working directory (default: null).
     * @param array<string>|null $env Optional environment variables (default: null).
     */
    public function __construct(
        private mixed $input,
        private string $executor = self::EXECUTOR_PROC_OPEN,
        private ?string $cwd = null,
        private ?array $env = null
    ) {
        if (
            $this->cwd === null && 
            (defined('ZEND_THREAD_SAFE') || DIRECTORY_SEPARATOR === '\\')
        ) {
            $this->setWorkingDirectory(getcwd());
        }

        self::$isFiberSupported = (PHP_VERSION_ID >= 80100 && class_exists('Fiber'));
    }

    /**
     * Process serialization is not supported.
     * 
     * @throws BadMethodCallException Throws when attempt to serialize process.
     */
    public function __sleep(): array
    {
        throw new BadMethodCallException(sprintf('Cannot serialize %s', __CLASS__));
    }

    /**
     * Process deserialization is not supported.
     * 
     * @throws BadMethodCallException Throws when attempt to unserialize process.
     */
    public function __wakeup(): void
    {
        throw new BadMethodCallException(sprintf('Cannot unserialize %s', __CLASS__));
    }

    /**
     * Cleanup on destruct object.
     * 
     * @return void
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Cleanup on clone object.
     * 
     * @return void
     */
    public function __clone(): void
    {
        $this->cleanup();
    }

    /**
     * Runs the process based on the provided input and executor.
     *
     * @return void
     * @throws RuntimeException throws if the process fails to run or any error occurs.
     * @throws BadMethodCallException If trying to called while the process is running.
     * @throws InvalidArgumentException If an invalid command argument is specified.
     */
    public function run(): void
    {
        $this->assertStarted(__FUNCTION__);
        $this->isComplete = false;

        if(self::$isFiberSupported){
            try{
                $this->fiber = new Fiber([$this, 'runExecutor']);
                $this->fiber->start();

                if ($this->fiber->isTerminated()) {
                    $this->fiber = null;
                }
                return;
            }catch(Exception|FiberError){
                self::$isFiberSupported = false;
            }
        }

        $this->runExecutor();
    }

    /**
     * Waits for the process response to complete and collects output.
     *
     * @param int $timeout Maximum time to wait in seconds (default: 0 for no timeout).
     * @throws RuntimeException If the process has not been started.
     * 
     * @return void
     */
    public function wait(int $timeout = 0): void
    {
        $this->startTime = microtime(true);
        if($this->waitForFibers()){
            if (!$this->open) {
                self::onError('Process not started. You need to call run() first.');
            }

            match ($this->executor) {
                self::EXECUTOR_POPEN => $this->handlePopen($timeout),
                self::EXECUTOR_EXEC => $this->handleExec(),
                self::EXECUTOR_PROC_OPEN => $this->handleProcOpen($timeout),
                self::EXECUTOR_CALLBACK, self::EXECUTOR_SHELL => $this->handleCallbackOrShell($timeout),
                self::EXECUTOR_STREAM => $this->handleStream($timeout),
                default => $this->cleanup()
            };
        }
    }

    /**
     * Creates a Process instance with a callback.
     *
     * @param callable|Closure $callable The callable to be executed.
     * 
     * @return self Return new static process instance.
     */
    public static function withCallback(callable $callable): self
    {
        return new self($callable, self::EXECUTOR_CALLBACK);
    }

    /**
     * Creates a Process instance with a stream.
     *
     * @param StreamInterface|Stream|resource $stream The stream resource to read from.
     * @return self Return new static process instance.
     */
    public static function withStream(mixed $stream): self
    {
        return new self($stream, self::EXECUTOR_STREAM);
    }

    /**
     * Creates a Process instance with a command.
     *
     * @param array|string $command The command to be executed.
     * @param string $executor The executor method to use (default: Process::EXECUTOR_POPEN).
     * @param string|null $cwd Optional working directory (default: null).
     * @param array<string>|null $envs Optional environment variables (default: null).
     * 
     * @return self Return new static process instance.
     */
    public static function withCommand(
        array|string $command, 
        string $executor = self::EXECUTOR_POPEN,
        ?string $cwd = null,
        ?array $envs = null
    ): self
    {
        return new self($command, $executor, $cwd, $envs);
    }
    
    /**
     * Checks if the process has completed execution.
     *
     * @return bool True if the process has finished; otherwise, false.
     */
    public function isComplete(): bool
    {
        return $this->isComplete;
    }

    /**
     * Checks if the process has completed execution.
     *
     * @return bool True if the process has finished; otherwise, false.
     */
    public function isRunning(): bool
    {
        return $this->open;
    }

    /**
     * Determines if PTY (Pseudo-Terminal) is supported on the current system.
     * 
     * @return bool Return true if PTY is supported, false otherwise.
     */
    public static function isPtySupported(): bool
    {
        return Terminal::isPtySupported();
    }

    /**
     * Checks if the current system supports TTY (Teletypewriter).
     *
     * @return bool Return true if TTY is supported, false otherwise.
     */
    public static function isTtySupported(): bool
    {
        return Terminal::isTtySupported();
    }

    /**
     * Retrieves the output response from the executed process.
     *
     * @return mixed The response output, which can be of any type (e.g., string, array).
     *               Returns null if the process has not yet run or if no response is available.
     */
    public function getResponse(): mixed
    {
        return $this->response;
    }

    /**
     * Returns the Pid (process identifier), if applicable.
     *
     * @return int|null The process id if running, null otherwise
     */
    public function getPid(): ?int
    {
        return ($this->open && $this->process) ? $this->metadata['pid'] : null;
    }

    /**
     * Returns the Pid (process identifier), if applicable.
     *
     * @return array|false The process id if running, null otherwise
     */
    public function getInfo(): array|bool
    {
        return ($this->open && $this->process) ? $this->metadata : false;
    }

    /**
     * Retrieve the process start time in seconds.
     * 
     * @return float Return the process start time.
     * @throws RuntimeException Throws if process is not started
     */
    public function getStartTime(): float
    {
        if (!$this->open) {
            self::onError("Process not started yet, You need to call run() first before you can get the start time.");
        }

        return $this->startTime;
    }

    /**
     * Gets the working directory.
     * 
     * @return string Return the working directory.
     */
    public function getWorkingDirectory(): ?string
    {
        if ($this->cwd === null) {
            return getcwd() ?: null;
        }

        return $this->cwd;
    }

    /**
     * Sanitize, replace and retrieve command.
     * 
     * @return string Return command string to be executed.
     * @throws InvalidArgumentException Throws if an error occurs.
     */
    public function getCommand(): string 
    {
        if (is_array($this->input)) {
            return implode(' ', array_map(Terminal::escape(...), $this->input));
        }

        return Terminal::replace($this->input, $this->env ?? [], true);
    }

    /**
     * Sets the current working directory.
     *
     * @param string $cwd The current working directory.
     * 
     * @return self Return the instance of process class.
     * @throws BadMethodCallException If trying to set working directory while the process is running.
     */
    public function setWorkingDirectory(string $cwd): self
    {
        $this->assertStarted(__FUNCTION__);
        $this->cwd = $cwd;
        if(
            self::EXECUTOR_PROC_OPEN !== $this->executor &&
            $this->cwd !== getcwd() . DIRECTORY_SEPARATOR
        ){
            chdir($this->cwd);
        }

        return $this;
    }

    /**
     * Sets the environment variables.
     *
     * @param array<string,mixed> $envs The environment variables.
     * 
     * @return self Return the instance of process class.
     * @throws BadMethodCallException If trying to set environment variables while the process is running.
     */
    public function setEnvironment(array $envs): self
    {
        $this->assertStarted(__FUNCTION__);
        $this->env = $envs;

        return $this;
    }

    /**
     * Sets the mode for opens process file pointer.
     *
     * @param string $mode The mode to use.
     * 
     * @return self Return the instance of process class.
     * @throws BadMethodCallException If trying to set mode while the process is running.
     */
    public function setMode(string $mode): self
    {
        $this->assertStarted(__FUNCTION__);
        $this->mode = $mode;

        return $this;
    }

    /**
     * Sets the descriptors specifications.
     *
     * @param array<int,array> $descriptors The descriptors specifications.
     * 
     * @return self Return the instance of process class.
     * @throws BadMethodCallException If trying to set descriptors while the process is running.
     */
    public function setDescriptors(array $descriptors): self
    {
        $this->assertStarted(__FUNCTION__);
        $this->descriptors = $descriptors;

        return $this;
    }

     /**
     * Set the process options before execution.
     *
     * This method allows setting specific options for the process, such as blocking pipes or
     * creating a new process group. Options cannot be modified once the process is running.
     *
     * @param array $options An associative array of options to set.
     * 
     * Supported options include:
     * 
     * - `blocking_pipes`: Controls whether the pipes should block during I/O.
     * - `create_process_group`: Determines if the process should be run in a new group.
     * - `create_new_console`: Launches the process in a new console window (Windows only).
     * - `bypass_shell`: Bypass the shell when launching the process (Linux/Windows).
     * - `detach_process`: Detaches the process, allowing it to run independently (Linux/Windows).
     * - `use_pty`: Use a pseudo-terminal (PTY) for the process (Unix systems only).
     * - `inherit_environment`: Inherit the environment variables from the parent process.
     * - `redirect_stderr`: Redirect stderr to stdout (combine output streams).
     * - `suppress_errors`: Suppress PHP errors or warnings during the process execution.
     * 
     * @return self Return the instance of process class.
     * @throws RuntimeException If an invalid option is provided.
     * @throws BadMethodCallException If trying to set options while the process is running.
     */
    public function setOptions(array $options): self
    {
        $this->assertStarted(__FUNCTION__);
        $defaultOptions = $this->options;

        foreach ($options as $key => $value) {
            if (!in_array($key, self::$supportedOptions)) {
                $this->options = $defaultOptions;
                self::onError(sprintf(
                    'Invalid option "%s". Supported options are: "%s".', 
                    $key, 
                    implode('", "', self::$supportedOptions)
                ));
            }

            $this->options[$key] = $value;
        }

        return $this;
    }

    /**
     * Gets the descriptors for the process.
     *
     * @return array Return the descriptors for the process based on the operating system.
     */
    public function getDescriptors(): array
    {
        if($this->descriptors !== []){
            return $this->descriptors;
        }

        return [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'], 
            2 => ['pipe', 'w'],
        ];
    }

    /**
     * Throws bad method call exception.
     * 
     * @param string $func The called function name.
     * 
     * @return void
     * @throws BadMethodCallException Throws an exception if called while process is running.
     */
    private function assertStarted(string $func): void  
    {
        if ($this->open) {
            throw new BadMethodCallException(
                sprintf('Calling method "%s()",  while the process is running is not allowed', $func)
            );
        }
    }

    /**
     * Checks if the waiting time has exceeded the timeout.
     *
     * @param int $timeout The maximum time to wait for the process to complete, in seconds.
     * 
     * @return bool Return true if the process is still waiting, false if it timed out.
     */
    private function isWaiting(int $timeout): bool
    {
        if ($timeout > 0 && (microtime(true) - $this->startTime) >= $timeout) {
            $this->cleanup($timeout);
            return false;
        }

        if ($this->process instanceof Generator) {
            array_merge_result($this->response, $this->waitForGenerator(), false);
        }

        return true;
    }

    /**
     * Waits for output from a generator process.
     *
     * @return mixed Return the current output from the generator.
     */
    private function waitForGenerator(): mixed
    {
        $output = $this->process->current(); 
        $this->process->next();

        if (!$this->process->valid()) {
            $this->isComplete = true;
        }

        return $output;
    }

     /**
     * Wait for all fibers to complete and resume suspended fibers.
     * 
     * @return bool Return true when all fibers are completed.
     */
    private function waitForFibers(): bool 
    {
        if(self::$isFiberSupported){
            if ($this->fiber instanceof Fiber && !$this->isComplete){
                if ($this->fiber->isSuspended()) {
                    try{
                        $this->fiber->resume();
                    }catch(Exception|FiberError $e){
                        throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                }

                if($this->fiber->isTerminated()) {
                    $this->fiber = null;
                }
            }
        }

        if (!$this->process) {
            $this->cleanup();
            self::onError(sprintf("Failed to run process with %s.", $this->executor));
            return false;
        }

        $this->open = true;
        return true;
    }

    /**
     * Cleans up the process and its resources.
     * 
     * @param int|null $timeout Execution timeout (default: null).
     * 
     * @return void
     * @throws RuntimeException Throws if the process timeout is reached.
     */
    private function cleanup(?int $timeout = null): void
    {
        if ($this->process && is_resource($this->process)) {
            if($this->executor === self::EXECUTOR_PROC_OPEN){
                @proc_close($this->process);
            }else{
                @pclose($this->process); 
            }
        }

        $this->process = null;
        $this->response = null;
        $this->isComplete = true; 
        $this->open = false;
        $this->startTime = null;

        if($timeout !== null){
            self::onError(
                sprintf("The process has timed out after %d seconds.", $timeout),
                RuntimeException::TIMEOUT_ERROR
            );            
        }
    }

    /**
     * Executes the process based on the specified executor.
     *
     * @return void
     * @throws RuntimeException If an invalid execution method is specified.
     * @throws InvalidArgumentException If an invalid command argument is specified.
     */
    private function runExecutor(): void
    {
        if (self::$isFiberSupported && Fiber::getCurrent()) {
            Fiber::suspend();
        }

        $this->pipes = [];
        
        if (!in_array($this->executor, [self::EXECUTOR_CALLBACK, self::EXECUTOR_STREAM])) {
            if(!function_exists($this->executor)){
                self::onError(sprintf('The Process executor: %s is not available on your PHP environment', $this->executor));
            }

            $this->input = $this->getCommand();
        }

        $this->process = match($this->executor){
            self::EXECUTOR_POPEN => popen($this->input, $this->mode ?? 'r'),
            self::EXECUTOR_SHELL => shell_exec($this->input),
            self::EXECUTOR_CALLBACK => ($this->input)(),
            self::EXECUTOR_PROC_OPEN => proc_open(
                $this->input, 
                $this->getDescriptors(), 
                $this->pipes,
                $this->cwd,
                $this->env,
                $this->options
            ),
            self::EXECUTOR_STREAM => (function(){
                if (
                    $this->input instanceof StreamInterface ||
                    $this->input instanceof Stream || 
                    is_resource($this->input)
                 ) {
                    return $this->input;
                }

                self::onError('Invalid stream resource provided.');
                return false;
            })(),
            default => (function() {
                self::onError('Invalid execution method specified.');
                return false;
            })()
        };
    }

    /**
     * Handles process responses when using popen.
     *
     * @param int $timeout The maximum time to wait for the process to complete, in seconds.
     * 
     * @return void 
     */
    private function handlePopen(int $timeout): void
    {
        if ($this->process) {
            stream_set_blocking($this->process, false);
            $output = '';

            while (!feof($this->process) && !$this->isComplete) {
                if (!$this->isWaiting($timeout)) {
                    break; 
                }

                $output .= fgets($this->process);
                usleep(10000);
            }

            $this->isComplete = true;
            if (pclose($this->process) !== -1) {
                array_merge_result($this->response, $output, false);
            }
        }
    }

    /**
     * Handles process responses when using exec.
     * 
     * @return void 
     */
    private function handleExec(): void
    {
        $this->isComplete = true;
        array_merge_result($this->response, implode("\n", $this->process), false);
    }

    /**
     * Handles process responses when using proc_open.
     *
     * @param int $timeout The maximum time to wait for the process to complete, in seconds.
     * 
     * @return void 
     */
    private function handleProcOpen(int $timeout): void
    {
        if (is_resource($this->process)) {
            do {
                if (!$this->isWaiting($timeout)) {
                    $this->metadata['exitcode'] = STATUS_SUCCESS;
                    $this->metadata['running'] = false;
                    break;
                }
                usleep(10000); 
                $this->metadata = proc_get_status($this->process);
            } while ($this->metadata['running'] && !$this->isComplete);

            $output = stream_get_contents($this->pipes[1]); 
            $error = stream_get_contents($this->pipes[2]);


            fclose($this->pipes[0]);
            fclose($this->pipes[1]);
            fclose($this->pipes[2]);
            $this->isComplete = true; 

            if ($this->metadata['exitcode'] === STATUS_SUCCESS && $output !== false) {
                array_merge_result($this->response, $output, false);
            }

            if ($error) {
                array_merge_result($this->response, "Error: {$error}", false);
            }
        }
    }

    /**
     * Handles process responses when using callback or shell commands.
     *
     * @param int $timeout The maximum time to wait for the process to complete, in seconds.
     * 
     * @return void 
     */
    private function handleCallbackOrShell(int $timeout): void
    {
        while (!$this->response && !$this->isComplete) {
            if (!$this->isWaiting($timeout)) {
                break;
            }
            usleep(10000);
        }

        $this->isComplete = true;
        array_merge_result($this->response, $this->process, false);
    }

    /**
     * Handles process responses when using streams.
     *
     * @param int $timeout The maximum time to wait for the process to complete, in seconds.
     * 
     * @return void 
     */
    private function handleStream(int $timeout): void
    {
        if ($this->input instanceof StreamInterface || $this->input instanceof Stream) {
            $this->readStream($timeout);
            return;
        } 
        
        if (is_resource($this->input)) {
            while (!$this->response && !$this->isComplete) {
                if (!$this->isWaiting($timeout)) {
                    break;
                }
                usleep(1000);
            }

            $output = stream_get_contents($this->process);
            $this->isComplete = true;
            if ($output) {
                array_merge_result($this->response, $output, false);
            }
        }
    }

     /**
     * Reads data from the stream until EOF is reached.
     * 
     * @return void
     */
    private function readStream(int $timeout): void
    {
        while (!$this->process->eof() && !$this->isComplete) {
            if (!$this->isWaiting($timeout)) {
                break;
            }

            $chunk = $this->process->read(1024);
            
            if ($chunk !== false) {
                array_merge_result($this->response, $chunk, false);
            }

            usleep(1000);
        }
        
        $this->isComplete = true;
    }

    /**
     * Throws an exception if error occurs.
     * 
     * @param string $message The error message.
     * @param int $code The exception code.
     * 
     * @return never
     * @throws RuntimeException Throws an exception message.
     */
    private static function onError(
        string $message, 
        int $code = RuntimeException::PROCESS_ERROR
    ): void 
    {
        throw new RuntimeException(
            $message,
            $code
        );
    }
}