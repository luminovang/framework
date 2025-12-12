<?php
/**
 * Luminova Framework FileTail.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Command;

use \Throwable;
use \Luminova\Luminova;
use \Luminova\Exceptions\{RuntimeException, InvalidArgumentException};
use function \inotify_init;
use function \inotify_read;
use function \inotify_add_watch;

class FileTail
{
    /**
     * The file handler resource used for reading the file.
     * 
     * @var resource|false $handler
     */
    private mixed $handler = null;

    /**
     * @var int|false $watcher
     */
    private bool|int $watcher = false;

    /**
     * @var resource|false $notifier
     */
    private mixed $notifier = null;

    /**
     * Read buffers.
     * 
     * @var string $buffer
     */
    private string $buffer = '';

    /**
     * The timestamp used for filtering lines that are older than this time.
     *
     * @var int|null $since
     */
    private ?int $since = null;

    /**
     * The log level used for filtering lines that do not match this level.
     *
     * @var string|null $level
     */
    private ?string $level = null;

    /** 
     * The string used for filtering lines that do not contain this substring.
     *
     * @var string|null $grep
     */
    private ?string $grep = null;

    /** 
     * The string used for filtering lines that contain this substring.
     *
     * @var string|null $exclude
     */
    private ?string $exclude = null;

    /**
     * Length of chunk line to read.
     * 
     * @var int $length
     */
    protected int $length = 8192;

    /**
     * An array of errors that have occurred during the execution of the FileTail.
     *
     * @var array $errors
     */
    private array $errors = [];

    /**
     * Callback function that is called when the file is opened.
     *
     * @var (callable(string $filename, string $mode): void)|null $onOpen
     */
    private mixed $onOpen = null;

    /**
     * Callback function that is called when the file is rotated (moved or deleted).
     *
     * @var (callable(string $filename): void)|null $onRotate
     */
    private mixed $onRotate = null;

    /**
     * Callback function that is called when a new line is output.
     *
     * @var (callable(string $line): void)|null $onOutput
     */
    private mixed $onOutput = null;

    /**
     * Callback function that is called when an error occurs.
     *
     * @var (callable(string $message, string $status): void)|null $onError
     */
    private mixed $onError = null;

    /**
     * CLI environment. 
     * 
     * @var bool|null $isCommand
     */
    private static ?bool $isCommand = null;

    /**
     * Create a new FileTail instance.
     * 
     * @param string $filename Path to the file to watch
     * @param bool $isInotify Whether to use inotify (Linux only) or fallback to polling.
     * @param bool $json Whether to pretty print JSON lines.
     * @param string|null $grep Optional string to filter lines that contain it.
     * @param string|null $exclude Optional string to filter out lines that contain it.
     * @param string|null $level Optional log level to filter (e.g. "ERROR", "INFO").
     * @param string|null $since Optional time filter (e.g. "10s", "5m", "2h", or any strtotime format).
     * 
     * @throws RuntimeException If `$isInotify` is true and inotify extension is not loaded.
     * @throws InvalidArgumentException If the $filename is an empty string.
     * 
     * @example - Example Usage:
     * 
     * ```php
     * use Luminova\Storage\FileTail;
     * 
     * $tail = new FileTail('/var/log/app.log', true, false, 'ERROR', null, 'ERROR', '1h');
     * 
     * $tail->onOpen(function(string $filename, string $mode): void {
     *     echo "Started tailing {$filename} using {$mode} mode." . PHP_EOL;
     * });
     * 
     * $tail->onRotate(function(string $filename): void {
     *     echo "File rotated: {$filename}" . PHP_EOL;
     * });
     * 
     * $tail->onOutput(function(string $line): void {
     *     echo "New line: {$line}" . PHP_EOL;
     * });
     * 
     * $tail->onError(function(string $message, string $status): void {
     *     echo "Error ({$status}): {$message}" . PHP_EOL;
     * });
     * 
     * $tail->run();
     * ```
     */
    public function __construct(
        private string $filename,
        private bool $isInotify = false,
        private bool $json = false,
        ?string $grep = null,
        ?string $exclude = null,
        ?string $level = null,
        ?string $since = null
    ) 
    {
        if (!$this->filename) {
            throw new InvalidArgumentException("Filename cannot be empty: {$this->filename}");
        }

        if ($this->isInotify && !extension_loaded('inotify')) {
            throw new RuntimeException('inotify extension is not available.');
        }

        $this->since = $this->parseSince($since);
        $this->level = $level ? strtoupper(trim($level)) : null;
        $this->grep = $grep ? trim($grep) : null;
        $this->exclude = $exclude ? trim($exclude) : null;

        self::$isCommand ??= Luminova::isCommand();
    }

    /** 
     * Register a callback for when the file is opened.
     * 
     * @param (callable(string $filename, string $mode):void) $callback The callback function to handle the event.
     * 
     * @return static Returns the FileTail instance.
     */
    public function onOpen(callable $callback): self
    {
        $this->onOpen = $callback;
        return $this;
    }

    /** 
     * Register a callback for when the inotify file is rotated (moved or deleted).
     * 
     * @param (callable(string $filename):void) $callback The callback function to handle the event.
     * 
     * @return static Returns the FileTail instance.
     */
    public function onRotate(callable $callback): self
    {
        $this->onRotate = $callback;
        return $this;
    }

    /** 
     * Register a callback for when a new plain-text line is output.
     * 
     * @param (callable(string $line):void) $callback The callback function to handle the event.
     * 
     * @return static Returns the FileTail instance.
     */
    public function onOutput(callable $callback): self
    {
        $this->onOutput = $callback;
        return $this;
    }

    /** 
     * Register a callback for when an error occurs.
     * 
     * @param (callable(string $message, string $status):void) $callback The callback function to handle the event. 
     * 
     * @return static Returns the FileTail instance.
     */
    public function onError(callable $callback): self
    {
        $this->onError = $callback;
        return $this;
    }

    /**
     * Close all handler and notifier
     */
    public function __destruct()
    {
        $this->close(true);
    }

    /** 
     * Get the list of errors that have occurred.
     * 
     * @return array An array of error information, where each error is 
     *      an associative array with 'status', 'state', and 'message' keys.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** 
     * Get a specific error message by index.
     * 
     * @param int $index The index of the error to retrieve. 
     *      Use negative indices to count from the end (e.g. -1 for the last error).
     * 
     * @return string|null The error message if found, or null if the index is out of bounds.
     */
    public function getError(int $index = -1): ?string
    {
        if ($this->errors === []) {
            return null;
        }

        if ($index < 0) {
            $index = count($this->errors) + $index; 
        }

        if (!isset($this->errors[$index])) {
            return null;
        }

        return $this->errors[$index]['message'] ?? null;
    }

    /** 
     * Clear all recorded errors from the internal error list.
     * 
     * @return void
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /** 
     * Check if any errors have occurred.
     * 
     * @return bool Returns true if there are errors, false otherwise.
     */
    public function hasError(): bool
    {
        return $this->errors !== [];
    }

    /** 
     * Start tailing the file. 
     * 
     * This method will block and continuously watch for new lines until the process is terminated.
     * 
     * @param float|int $wait The number of time in seconds to wait before next line (default: 200_000).
     * @param int $retry Number of retry attempt while waiting for file to be available 
     *              or readable (default: 0 (no-limit)).
     * 
     * @return bool Returns true on successful execution, 
     *      or false if an error occurred (errors can be retrieved with getErrors()).
     */
    public function run(float|int $wait = 200_000, int $retry = 0): bool
    {
        $wait = is_float($wait) ? (int) round($wait * 1_000_000) : $wait;
        $wait = max(0, $wait);
        $this->clearErrors();

        if (!$this->open($this->isInotify, $retry)) {
            return false;
        }

        $this->clearErrors();

        return $this->isInotify 
            ? $this->runInotify($wait) 
            : $this->runPolling($wait);
    }

    /**
     * Register error to list or invoke error handler.
     * 
     * This method adds an error to the internal error list if `onError` is not defined,
     * or triggers onError callback if defined
     * 
     * If `onError` is not set, in CLI, it will send error to `STDERR` while also add to list.
     * 
     * @param string $status A short identifier string representing the error status or type.
     * @param string $message The error message describing issue.
     * 
     * @return void
     */
    private function addError(string $status, string $message): void
    {
        if ($this->onError) {
            ($this->onError)($message, $status);
            return;
        }

        $this->errors[] = [
            'status' => $status,
            'message' => $message
        ];

        if(self::$isCommand){
            fwrite(STDERR, sprintf(
                '[%s] %s%s',
                $status,
                $message,
                PHP_EOL
            ));
        }
    }

    /**
     * Parse the "since" option into a timestamp. 
     * 
     * Supports formats like "10s", "5m", "2h", or any strtotime-compatible string.
     * 
     * @param string|null $since The input string representing the time filter.
     * 
     * @return int|null The parsed timestamp or null if parsing fails.
     */
    protected function parseSince(?string $since): ?int
    {
        if (!$since) {
            return null;
        }

        if (preg_match('/^(\d+)([smh])$/i', $since, $m)) {
            $value = (int) $m[1];
            $unit  = strtolower($m[2]);

            return match ($unit) {
                's' => time() - $value,
                'm' => time() - ($value * 60),
                'h' => time() - ($value * 3600),
                default => null,
            };
        }

        return strtotime($since) ?: null;
    }

    /**
     * Open the file for reading and seek to the end.
     *
     * @param bool $isInotify Whether inotify mode is being used (for callback context).
     * @param int $retry Number of times to attempt check for file if not exist or readable yet.
     * @param bool $isStart Whether this is the initial open (true) or a reopen after rotation (false). 
     *          This affects the onOpen callback.
     * 
     * @return bool Returns true on success, false on failure (errors will be added to the error list).
     */
    protected function open(bool $isInotify, int $retry = 0, bool $isStart = true): bool
    {
        $attempt = 0;
        $this->buffer = '';
        $maxReadableRetry = ($retry > 0) ? $retry : 5;

        while (true) {
            $ctx = null;

            if (!$this->check($ctx)) {
                ++$attempt;

                if($ctx === 'file.readable'){
                    if($attempt > $maxReadableRetry){
                        return false;
                    }
                }

                if ($attempt > $retry && $retry > 0) {
                    return false;
                }

                usleep(500_000);
                continue;
            }

            $this->handler = fopen($this->filename, 'r');

            if (!$this->handler) {
                $this->addError('file.open', "Unable to open file: {$this->filename}");

                if (++$attempt > $retry && $retry > 0) {
                    return false;
                }

                usleep(500_000);
                continue;
            }

            $attempt = 0;
            $this->clearErrors();
            fseek($this->handler, 0, SEEK_END);

            if($isStart){
                if ($this->onOpen) {
                    ($this->onOpen)($this->filename, $isInotify ? 'inotify' : 'polling');
                }
            }

            return true;
        }
    }

    /**
     * Close file handler and optionally close notifier and remove watch.
     *
     * @param boolean $all Whether to close notifier and remove watch.
     * 
     * @return void
     */
    protected function close(bool $all = false): void 
    {
        if (is_resource($this->handler)) {
            fclose($this->handler);
        }

        $this->handler = null;

        if(!$all){
            return;
        }
        
        if($this->isInotify){
            if(is_resource($this->notifier)){
                if($this->watcher !== false){
                    inotify_rm_watch($this->notifier, $this->watcher);
                }

                fclose($this->notifier);
            }
        }

        $this->watcher = false;
        $this->notifier = null;
        $this->errors = [];
    }

    /**
     * Check if file is available and is readable.
     *
     * @param string|null &$status The error status type passed by reference.
     * 
     * @return bool Return true if file is available and readable, otherwise false.
     */
    protected function check(?string &$status = null): bool
    {
        if (!is_file($this->filename)) {
            $status = 'file';
            $this->addError($status, "File not found: {$this->filename}");

            return false;
        }

        if (!is_readable($this->filename)) {
            $status = 'file.readable';
            $this->addError($status, "Cannot read file: {$this->filename}");
            return false;
        }

        return true;
    }

    /**
     * Continuously read from the file and process lines according to the filters.
     * 
     * This method is used in polling mode to read new lines as they are added.
     * It handles buffering to ensure that lines are processed correctly even if they arrive in chunks.
     * 
     * @return void
     */
    protected function stream(): void
    {
        if(!$this->handler){
            return;
        }

        while (true) {
            $chunk = fread($this->handler, $this->length);

            if ($chunk === '' || $chunk === false) {
                break;
            }

            $this->buffer .= $chunk;

            while (($pos = strpos($this->buffer, "\n")) !== false) {
                $line = substr($this->buffer, 0, $pos);
                $this->buffer = substr($this->buffer, $pos + 1);

                $this->line($line);
            }
        }
    }

    /**
     * Process a single line of input, applying filters and outputting if it matches.
     * 
     * @param string $line The line of text to process.
     * 
     * @return void
     */
    protected function line(string $line): void
    {
        $line = trim($line);

        if ($line === '') {
            return;
        }

        if ($this->grep && !str_contains($line, $this->grep)) {
            return;
        }

        if ($this->exclude && str_contains($line, $this->exclude)) {
            return;
        }

        $json = null;
        if ($line[0] === '{' || $line[0] === '[') {
            try{
                $json = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($json)) {
                    $json = null;
                }
            } catch(Throwable){
                $json = null;
            }
        }

        if ($this->level) {
            if ($json) {
                $level = strtoupper($json['level'] ?? '');
                if ($level !== $this->level) {
                    return;
                }
            } else {
                if (!str_contains(strtoupper($line), $this->level)) {
                    return;
                }
            }
        }

        if ($this->since) {
            $ts = null;

            if ($json && isset($json['timestamp'])) {
                $ts = strtotime($json['timestamp']);
            } elseif (preg_match('/\b\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $line, $m)) {
                $ts = strtotime($m[0]);
            }

            if ($ts && $ts < $this->since) {
                return;
            }
        }

        if ($this->json && $json) {
            $line = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if ($this->onOutput) {
            ($this->onOutput)($line);
            return;
        }

        if(self::$isCommand){
            fwrite(STDOUT, $line . PHP_EOL);
            return;
        }

        echo $line . PHP_EOL;
    }

    /**
     * Run the polling loop to watch for new lines in the file.
     * 
     * This method continuously checks the file for new data and processes it.
     * It also handles file rotation by checking if the file size has decreased.
     * 
     * @param int $microseconds The number of microseconds to sleep between entries.
     * 
     * @return bool Returns true on successful execution, false if an error occurs 
     *      (errors will be added to the error list).
     */
    protected function runPolling(int $microseconds): bool
    {
        while (true) {
            if (!$this->check()) {
                if($microseconds > 0){
                    usleep($microseconds);
                }
                continue;
            }

            clearstatcache(true, $this->filename);
            $size = filesize($this->filename);

            if ($size === false) {
                continue;
            }

            $position = ftell($this->handler);

            if ($size < $position) {
                if($this->onRotate){
                    ($this->onRotate)($this->filename);
                }

                $this->close();

                if (!$this->open(false, 5, false)) {
                    return false;
                }

                continue;
            }

            if ($position < $size) {
                $this->stream();
            }

            if($microseconds > 0){
                usleep($microseconds);
            }
        }

        return true;
    }

    /**
     * Run the inotify loop to watch for file changes.
     * 
     * This method uses the inotify extension to efficiently watch for modifications to the file.
     * It handles file rotation by listening for IN_MOVE_SELF and IN_DELETE_SELF events.
     * 
     * @param int $microseconds The number of microseconds to sleep between entries.
     * 
     * @return bool Returns true on successful execution, false if an error occurs 
     *      (errors will be added to the error list).
     */
    protected function runInotify(int $microseconds): bool
    {
        $this->notifier = inotify_init();

        if (!$this->notifier) {
            $this->addError('inotify.init', "Failed to initialize inotify.");
            return false;
        }

        stream_set_blocking($this->notifier, true);

        $dir  = dirname($this->filename);
        $file = basename($this->filename);

        $this->watcher = inotify_add_watch(
            $this->notifier,
            $dir,
            \IN_MODIFY | \IN_CREATE | \IN_MOVED_TO | \IN_DELETE | \IN_MOVE_SELF
        );

        if ($this->watcher === false) {
            $this->addError('inotify.watch', "Failed to attach watch.");
            return false;
        }

        while (true) {
            $events = inotify_read($this->notifier);

            if (!$events) {
                continue;
            }

            foreach ($events as $event) {
                $name = $event['name'] ?? '';
                $mask = $event['mask'];

                if ($name !== $file && $name !== $this->filename) {
                    continue;
                }

                if ($mask & \IN_MODIFY) {
                    $this->stream();
                    continue;
                }

                if ($mask & (\IN_CREATE | \IN_MOVED_TO)) {

                    if ($this->onRotate) {
                        ($this->onRotate)($this->filename);
                    }

                    $this->close();

                    if (!$this->open(true, 5, false)) {
                        return false;
                    }

                    continue;
                }

                if ($mask & (\IN_DELETE | \IN_MOVE_SELF)) {
                    $this->close();
                    continue;
                }
            }

            if ($microseconds > 0) {
                usleep($microseconds);
            }
        }

        return true;
    }
}