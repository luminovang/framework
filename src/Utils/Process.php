<?php
/**
 * Luminova Framework process executor.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Utils;

use \Luminova\Storages\Stream;
use \Luminova\Command\Terminal;
use \Psr\Http\Message\StreamInterface;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\BadMethodCallException;
use \Luminova\Exceptions\InvalidArgumentException;
use \Closure;
use \Generator;

class Process
{
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
     * @param array<string>|null $envs Optional environment variables (default: null).
     */
    public function __construct(
        private mixed $input,
        private string $executor,
        private ?string $cwd = null,
        private ?array $env = null
    ) {
        if (
            $this->cwd === null && 
            (defined('ZEND_THREAD_SAFE') || DIRECTORY_SEPARATOR === '\\')
        ) {
            $this->setWorkingDirectory(getcwd());
        }
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
     */
    public function __destruct()
    {
        $this->cleanup();
    }

    /**
     * Cleanup on clone object.
     */
    public function __clone()
    {
        $this->cleanup();
    }

    /**
     * Runs the process based on the provided input and executor.
     *
     * @throws RuntimeException throws if the process fails to run or any error occurs.
     * @throws BadMethodCallException If trying to called while the process is running.
     * @throws InvalidArgumentException If an invalid command argument is specified.
     */
    public function run()
    {
        $this->assertStarted(__FUNCTION__);
        $this->isComplete = false;
        $this->runExecutor();

        if (!$this->process) {
            self::onError(sprintf("Failed to run process with %s.", $this->executor));
        }

        $this->open = true;
    }

    /**
     * Creates a Process instance with a callback.
     *
     * @param callable $callable The callable to be executed.
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
     * @param string[] $envs The environment variables.
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
     * Waits for the process to complete and collects output.
     *
     * @param int $timeout Maximum time to wait in seconds (default: 0 for no timeout).
     * @throws RuntimeException If the process has not been started.
     */
    public function wait(int $timeout = 0)
    {
        if (!$this->open) {
            self::onError('Process not started. You need to call run() first.');
        }

        if(self::EXECUTOR_PROC_OPEN === $this->executor){
            $this->metadata = proc_get_status($this->process);
        }

        $this->startTime = microtime(true);

        while (!$this->response || !$this->isComplete) {
            if ($this->process instanceof Generator) {
                $this->normalizeResponse($this->waitForGenerator());
            } elseif ($this->response) {
                $this->isComplete = true;
            }

            if ($timeout > 0 && (microtime(true) - $this->startTime) >= $timeout) {
                $this->cleanup(true);
                break;
            }

            usleep(1000);
        }
    }

    /**
     * Waits for output from a generator process.
     *
     * @return mixed Current output from the generator.
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
     * Reads data from the stream until EOF is reached.
     */
    private function readStream(): void
    {
        while (!$this->process->eof()) {
            $chunk = $this->process->read(1024);
            
            if ($chunk !== false) {
                $this->normalizeResponse($chunk);
            }
        }
        
        $this->isComplete = true;
    }

    /**
     * Adds response output to the internal response storage.
     *
     * @param mixed $response The response output to add.
     * @return void
     */
    private function normalizeResponse(mixed $response): void
    {
        if ($this->response === null) {
            $this->response = $response;
            return;
        }
        
        if (is_array($this->response)) {
            if (is_array($response)) {
                $this->response = array_merge($this->response, $response);
            } else {
                $this->response[] = $response;
            }
        } elseif (is_string($this->response)) {
            if (is_array($response)) {
                $this->response = array_merge([$this->response], $response);
            } else {
                $this->response = [$this->response, $response];
            }
        } else {
            $this->response = [$this->response];

            if (is_array($response)) {
                $this->response = array_merge($this->response, $response);
            } else {
                $this->response[] = $response;
            }
        }
    }

    /**
     * Cleans up the process and its resources.
     * 
     * @param bool $timeout Whether is cleanup for timeout (default: false).
     * @throws RuntimeException Throws if the process timeout is reached.
     */
    private function cleanup(bool $timeout = false): void
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

        if($timeout){
            self::onError(
                "Process not started. You need to call run() first.",
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
        if (!in_array($this->executor, [self::EXECUTOR_CALLBACK, self::EXECUTOR_STREAM])) {
            if(!function_exists($this->executor)){
                self::onError(sprintf('The Process executor: %s is not available on your PHP environment', $this->executor));
            }

            $this->input = $this->getCommand();
        }

        switch ($this->executor) {
            case self::EXECUTOR_POPEN:
                $this->process = popen($this->input, $this->mode ?? 'r');
                if ($this->process) {
                    stream_set_blocking($this->process, false);
                    $output = '';
            
                    while (!feof($this->process)) {
                        $output .= fgets($this->process);
                    }
            
                    if (pclose($this->process) !== -1) {
                        $this->normalizeResponse($output); 
                    }
                }
                break;

            case self::EXECUTOR_EXEC:
                $output = [];
                $returnVar = null;
                $this->process = exec($this->input, $output, $returnVar);
                if ($returnVar === STATUS_SUCCESS) {
                    $this->normalizeResponse(implode("\n", $output));
                }
                break;

            case self::EXECUTOR_SHELL:
                $this->process = shell_exec($this->input);
                if ($this->process !== false) {
                    $this->normalizeResponse($this->process);
                }
                break;
            case self::EXECUTOR_PROC_OPEN:
                $this->process = proc_open(
                    $this->input, 
                    $this->getDescriptors(), 
                    $pipes,
                    $this->cwd,
                    $this->env,
                    $this->options
                );
            
                if (is_resource($this->process)) {
                    fclose($pipes[0]);
            
                    $output = stream_get_contents($pipes[1]);
                    fclose($pipes[1]);
            
                    $error = stream_get_contents($pipes[2]);
                    fclose($pipes[2]);
            
                    if ($output !== false) {
                        $this->normalizeResponse($output);
                    }

                    if ($error !== false) {
                        $this->normalizeResponse("Error: " . $error);
                    }
                }
                break;

            case self::EXECUTOR_CALLBACK:
                $this->process = ($this->input)();
                if($this->process){
                    $this->normalizeResponse($this->process);
                }
                break;
            case self::EXECUTOR_STREAM:
                if ($this->input instanceof StreamInterface || $this->input instanceof Stream) {
                    $this->process = $this->input;
                    $this->readStream();
                } elseif (is_resource($this->input)) {
                    $this->process = $this->input; 
                    $output = stream_get_contents($this->process);

                    if ($output !== false) {
                        $this->normalizeResponse($output);
                    }
                } else {
                    self::onError("Invalid stream resource provided.");
                }
                break;
            default:
                self::onError("Invalid execution method specified.");
        }
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