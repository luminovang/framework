<?php 
declare(strict_types=1);
/**
 * Luminova Framework Background process worker.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command;

use \Closure;
use \PhpToken;
use \Throwable;
use Luminova\Logger\Logger;
use Luminova\Command\Terminal;
use Luminova\Utility\Serializer;
use Luminova\Command\Consoles\Commands;
use Luminova\Exceptions\RuntimeException;
use function Luminova\Funcs\{root, temp_dir};
use \Opis\Closure\Serializer as OpisSerializer;

final class Worker
{
    /**
     * Temp directory
     *
     * @var string|null
     */
    private static ?string $dir = null;

    /**
     * parsed arguments.
     *
     * @var array
     */
    private static array $arguments = [];

     /**
     * PHP tokens
     * 
     * @var int[] PHP_TOKENS
     */
    private const PHP_TOKENS = [
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
     * Allowed callable function name and class pattern.
     * 
     * @var string CALLABLE_CLASS_PATTERN
     */
    private const CALLABLE_CLASS_PATTERN = '/^\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*(?:::[A-Za-z_][A-Za-z0-9_]*)?$/';

    /**
     * Build a background worker command.
     *
     * Creates the command arguments required to execute a task asynchronously through
     * the PHP worker process. The task and arguments are encoded for safe transfer
     * to the worker, and output handling options are included.
     *
     * @param callable|string $task Task callable or PHP code to execute.
     * @param array<string,mixed> $arguments Arguments passed to the worker task.
     * @param string|null $phpPath Optional PHP CLI executable path.
     * @param string|null $pidPipe Windows PID pipe file.
     * @param bool $awaitable Whether to task is waiting for response.
     *
     * @return string The PHP worker command and escaped arguments.
     *
     * @throws RuntimeException If the PHP CLI executable cannot be found.
     */
    public static function build(
        callable|string $task, 
        array $arguments, 
        ?string $phpPath = null,
        ?string $pidPipe = null,
        bool $awaitable = false
    ): string 
    {
        $phpScript = root(filename: 'novakit');

        if(!is_file($phpScript)){
            throw new RuntimeException(sprintf(
                'Luminova novakit CLI executable PHP file is missing in project root: %s.',
                $phpScript
            ));
        }

        $phpPath ??= (Terminal::whichPhp() ?? PHP_BINARY);

        if (!$phpPath) {
            throw new RuntimeException('Unable to locate PHP CLI executable.');
        }

        $args = '';
        $handler = 'none';
        $task = base64_encode(self::serialize($task, $handler));

        $arguments = Terminal::escapeArguments([
            'arguments' => base64_encode(serialize($arguments)),
            'handler'   => $handler,
            'task'      => $task,
            'awaitable' => (int) $awaitable,
            'log'       => 'background',
            'pid-pipe'  => (string) $pidPipe
        ]);

        if($arguments !== []){
            $args = ' ' . implode(' ', $arguments);
        }

        $phpPath = escapeshellarg($phpPath);
        $phpScript = escapeshellarg($phpScript);

        return "{$phpPath} -f {$phpScript} async {$args}";
    }
    
    /**
     * Prepare a task for background execution.
     *
     * Converts supported task types into a worker-compatible representation.
     * Supports closures, callable arrays, named functions, static methods, and raw
     * PHP code. Anonymous closures require a supported closure serializer.
     *
     * Raw PHP code is validated and normalized before being accepted. PHP opening
     * and closing tags are not allowed because the worker executes the code directly.
     *
     * @param callable|string $task Task callable or PHP code.
     * @param string|null &$handler Receives the worker handler type.
     *
     * @return string Serialized callable or validated PHP code.
     * @throws RuntimeException If the task type is unsupported or invalid.
     */
    public static function serialize(callable|string $task, ?string &$handler = null): string
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

        if (is_array($task)) {
            $handler = 'array';
            return serialize($task);
        }

        if (!is_string($task)) {
            throw new RuntimeException(sprintf(
                'Invalid handler not supported type: %s',
                get_debug_type($task)
            ));
        }

        $task = trim($task);

        if ($task !== '' && is_callable($task) && preg_match(self::CALLABLE_CLASS_PATTERN, $task)) {
            $handler = 'callable';
            return $task;
        }

        $result = match(true) {
            $task === '' => 'Worker task cannot be empty.',
            str_contains($task, '<?') => 'Worker code must not contain PHP opening tags.',
            str_ends_with($task, '?>') => 'Worker code must not contain a PHP closing tag.',
            !self::isPhpVanilla($task) => 'Invalid worker PHP code.',
            default => null
        };

        if($result === null){
            $handler = 'php';
            return $task;
        }
 
        throw new RuntimeException($result);
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
        $pattern = self::getTempPath() . 'tr-*.txt';
        $files = glob($pattern);

        if($files === false){
            return;
        }

        foreach ($files as $file) {
            if ($ignore !== null && str_contains($file, "pid-{$ignore}-")) {
                continue;
            }

            if (filemtime($file) + $ttl < time()) {
                @unlink($file);
            }
        }
    }

    /**
     * Start running background task.
     *
     * @param array $argv Command arguments from PHP global.
     * @param int $pid Child process PID.
     * 
     * @return int Return status exit code.
     * @throws RuntimeException If error while running.
     */
    public static function run(array $argv, int $pid): int 
    {
        if ($pid < 1) {
            return self::failed(
                $pid, 
                new RuntimeException('Failed to obtain child process PID.'),
                write: false
            );
        }
        
        self::decodeArguments($argv);

        if(!self::pipeWitePid($pid)){
            return self::failed(
                $pid, 
                new RuntimeException('Failed to write child process PID to pipe'),
                write: false,
                context: ['pid' => $pid]
            );
        }

        Terminal::init();
        return self::execute($pid);
    }

    /**
     * Get normalized task arguments.
     * 
     * @return array<string,mixed> Task arguments.
     */
    public static function getArguments(): array 
    {
        return self::$arguments['arguments'];
    }

    /**
     * Decode worker command arguments.
     * 
     * @param array $argv Global command arguments.
     *
     * @return void
     */
    private static function decodeArguments(array $argv): void
    {
        if(self::$arguments !== []){
            return;
        }

        foreach ($argv as $i => $arg) {
            if (
                $i === 0 
                || $arg === 'async' 
                || $arg === 'novakit'
            ) {
                continue;
            }

            $arg = ($arg[0] === '-') ? ltrim($arg, '-') : $arg;

            if($arg === 'h' || $arg === 'help'){
                Terminal::header();
                Terminal::helper(Commands::get('async'));
                exit(STATUS_SUCCESS);
            }

            if (!str_contains($arg, '=')) {
                self::$arguments['options'][] = $arg;
                continue;
            }

            [$k, $v] = explode('=', $arg, 2);

            if($k === 'arguments'){
                self::$arguments[$k] = (array) unserialize(base64_decode($v));
                continue;
            }

            self::$arguments[$k] = json_validate($v) 
                ? (json_decode($v, true) ?: []) 
                : $v;
        }
    }

    /**
     * Writes a process ID to the specified pipe.
     *
     * @param int|null $pid Process ID to write.
     *
     * @return bool Returns `true` when the PID was successfully written,
     *              otherwise `false`.
     */
    private static function pipeWitePid(int $pid): bool
    {
        if ($pid < 1) {
            return false;
        }

        if (!str_starts_with(PHP_OS, 'WIN')) {
            return true;
        }

        $file = self::getValue('pid-pipe');

        if (empty($file)) {
            return false;
        }

        $pipe = @fopen($file, 'wb');

        if ($pipe === false) {
            return false;
        }

        fwrite($pipe, $pid . PHP_EOL);
        fclose($pipe);

        return true;
    }

    /**
     * Get argument by keyname.
     *
     * @param string $name
     * @param mixed $default
     * 
     * @return mixed
     */
    private static function getValue(string $name, mixed $default = null): mixed 
    {
        return self::$arguments[$name] ?? $default;
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
    private static function write(
        int $pid, 
        mixed $response = null, 
        string $output = '',
        ?array $error = null
    ): bool
    {
        $sink = self::getTempPath() . "tr-{$pid}.txt";
        $fp = @fopen($sink, 'c+');

        if (!$fp) {
            return false;
        }

        $success = false;

        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }

            $payload = [
                'pid'       => $pid,
                'error'     => $error
            ];

            if(empty($error)){
                $payload = [
                    'pid'       => $pid,
                    'response'  => $response,
                    'output'    => $output,
                    'error'     => null
                ];
            }

            rewind($fp);
            ftruncate($fp, 0);

            $success = fwrite($fp, self::encode($pid, $payload)) !== false;

            fflush($fp); 
            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }

        return $success;
    }

    /**
     * Encode response.
     *
     * @param int $pid
     * @param array $payload
     * 
     * @return string
     */
    private static function encode(int $pid, array $payload): string 
    {
         try{
            return serialize($payload);
        } catch(Throwable){
            $res = $payload['response'] ?? null;

            if($res !== null && is_object($res)){
                $payload['response'] = (object) get_object_vars($res);
            } else{
                $t = get_debug_type($res ?? '');
                $payload = [
                    'pid'       => $pid,
                    'error'     => self::error(
                        $pid, 
                        new RuntimeException(
                            "Task #{$pid}, completed with unserializable response type: {$t}"
                        )
                    )
                ];
                unset($payload['error']['pid']);
            }

            return serialize($payload);
        }
    }

    /**
     * Handle failed task execution.
     *
     * @param int $pid
     * @param Throwable $e
     * @param bool $write
     * @param array $context
     * 
     * @return int Return 1
     */
    private static function failed(
        int $pid, 
        Throwable $e, 
        bool $write = false,
        array $context = []
    ): int
    {
        $isAwaitable = (bool) self::getValue('awaitable', false);

        if(!$isAwaitable){
            self::log($pid, $e, 'error', $context);
            return 1;
        }

        if($write && self::write($pid, error: self::error($pid, $e))){
            return 1;
        }

        try{
            self::log($pid, $e, $write ? null : 'error', $context);
        } finally {
            throw $e;
        }
    }

    /**
     * Log failed task
     *
     * @param int $pid
     * @param Throwable $e
     * @param string|null $level
     * @param array $context
     * 
     * @return bool
     */
    private static function log(
        int $pid,
        Throwable $e,
        ?string $level = null,
        array $context = []
    ): bool 
    {
        $level ??= (bool) self::getValue('log', 'background');
        $context += self::error($pid, $e);

       return  Logger::tryLog($level, $e->getMessage(), $context);
    }

    /**
     * Parse error information from exception.
     *
     * @param int $pid
     * @param Throwable $e
     * 
     * @return array{pid:int,class:string,code:int,file:string,line:int,message:string}
     */
    private static function error(int $pid, Throwable $e): array
    {
        $previous = $e->getPrevious() ?? $e;

        return [
            'pid'     => $pid,
            'class'   => get_class($e),
            'code'    => $e->getCode(),
            'file'    => $previous->getFile(),
            'line'    => $previous->getLine(),
            'message' => $e->getMessage()
        ];
    }

     /**
     * Executes a background child process task.
     *
     * Restores the serialized task handler, executes it with the provided
     * arguments, captures output, and optionally writes the execution result back
     * to the parent process.
     *
     * @param int $pid Parent process identifier used for response communication.
     *
     * @return int Returns `0` on success, or `1` on failure.
     */
    private static function execute(int $pid): int
    {
        $handler = self::getValue('handler');
        $payload = base64_decode(self::getValue('task', ''), true);
        $isAwaitable = (bool) self::getValue('awaitable', false);

        ob_start();

        try {
            if (!$handler || $payload === false) {
                throw new RuntimeException(
                    "No valid handler was found to execute background task PID #{$pid}."
                );
            }

            $task = match ($handler) {
                'closure'      => Serializer::unserialize($payload),
                'opis.closure' => OpisSerializer::unserialize($payload),
                'array'        => unserialize($payload),
                'php'          => static fn(array $arguments) => eval($payload),
                default        => null,
            };

            if (!is_callable($task)) {
                throw new RuntimeException(
                    "Task PID #{$pid} handler is not callable."
                );
            }

            $response = null;
            $error = null;

            try{
                if(!$isAwaitable){
                    $task(self::getArguments());
                    return 0;
                }

                $response = $task(self::getArguments());
            } catch(Throwable $e){
                $error = self::error($pid, $e);
            }

            $output = trim((string) ob_get_clean());

            if (self::write($pid, $response, $output, $error)) {
                return 0;
            }

            return self::failed(
                $pid, 
                new RuntimeException(sprintf(
                    'Task PID #%d failed to write child process response to: %s.',
                    $pid,
                    self::getTempPath()
                )),
                write: true,
                context: [
                    'pid'       => $pid,
                    'response'  => $response,
                    'output'    => $output,
                    'error'     => $error
                ]
            );
        } catch (Throwable $e) {
            $class = get_class($e);

            return self::failed(
                $pid, 
                new $class(
                    sprintf(
                        'Task PID #%d failed with exception: %s.',  $pid,
                        $e->getMessage()
                    ),
                    $e->getCode(),
                    previous: $e
                ),
                write: true
            );
        } finally {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            self::gc(ignore: $pid);
        }
    }

    /**
     * Determine whether a string contains valid, intentional PHP code.
     *
     * This method does NOT execute the code.
     * It tokenizes the input using PHP's lexer and checks for real PHP constructs.
     *
     * @param string $code The PHP code to analyze.
     * 
     * @return bool Return true if likely a php code, otherwise false.
     */
    private static function isPhpVanilla(string $code): bool
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

                if ($token->is(self::PHP_TOKENS)) {
                    return true;
                }
            }
        } catch (Throwable) {}

        return false;
    }

    /**
     * Get the path to the temporary worker directory.
     *
     * On production systems, attempts to use the system temp directory if writable.
     * Otherwise, defaults to the application's `writeable/temp/worker` directory.
     *
     * @return string Fully-qualified path to the worker directory.
     */
    private static function getTempPath(): string
    {
        if (self::$dir !== null) {
            return self::$dir;
        }

        return self::$dir = temp_dir('worker', fromLocal: !PRODUCTION);
    }
}