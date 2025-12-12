<?php
/**
 * Luminova Framework file stream.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Storage;

use \Throwable;
use \Stringable;
use \Luminova\Exceptions\RuntimeException;
use \Luminova\Exceptions\InvalidArgumentException;

class Stream implements Stringable
{
    /**
     * Patterns for readable and writable stream modes.
     * 
     * @var array<string,string> MODES
     */
    protected const MODES = [
        'readable' => '/r|r\+|rb\+|w\+|wb\+|a\+|ab\+|x\+|xb\+|c\+|cb\+/', 
        'writable' => '/w|wb|w\+|wb\+|a|ab|a\+|ab\+|r\+|rb\+|x|xb|x\+|xb\+|c|cb|c\+|cb\+/'
    ];

    /**
     * The underlying file resource handle, or null when closed.
     *
     * @var resource|null $resource
     */
    protected mixed $resource = null;

    /**
     * Optional stream context resource created via stream_context_create().
     *
     * @var resource|null $context
     */
    protected mixed $context = null;

    /**
     * Object-level read-only mode
     * 
     * @var bool $isReadonly
     */
    protected bool $isReadonly = false;

    /**
     * Immutable read-only mode
     * 
     * @var bool $isReadonlyImmutable
     */
    protected bool $isReadonlyImmutable = false;

    /**
     * Whether this instance owns (and is therefore responsible for closing) the handle.
     * False when the handle was injected via Stream::fromResource().
     *
     * @var bool $isOwnsHandler
     */
    protected bool $isOwnsHandler = true;

    /**
     * Whitelist of allowed dynamic stream functions.
     *
     * This controls which PHP functions can be called via {@see self::__call()} on the underlying stream resource.
     * 
     * Usage:
     *  - `null`  → use default allowed functions (safe defaults, e.g., `fread`, `fwrite`, etc.)
     *  - `[]`    → deny all dynamic function calls.
     *  - `[...]` → explicitly allow only the listed functions.
     *
     * @var \string-callable[]|null $allowedDynamicFunctions
     * 
     * > **Note:**
     * > Only functions that accept a stream resource as the first argument should be used here.
     */
    protected ?array $allowedDynamicFunctions = null;

    /**
     * Expected resource type (default: "stream").
     * 
     * @var string $allowedResourceType
     */
    protected string $allowedResourceType = 'stream';

    /**
     * Creates a new Stream instance and, optionally, opens the underlying file immediately.
     *
     * Set `$autoOpen` to `false` and call `setContext()` before `open()` when a custom stream
     * context is required, because the context is passed to fopen() at open time.
     *
     * @param string $filename The filename or stream URI (e.g. "php://temp", "compress.zlib://file.gz").
     * @param string $mode fopen()-compatible mode string. Defaults to 'c+b'
     *                 (read/write, create if absent, binary-safe, no truncation).
     * @param bool $autoOpen When true (default) the file is opened in the constructor.
     *                         Pass false to defer opening until open() is called explicitly.
     *
     * @throws RuntimeException If $autoOpen is true and the file cannot be opened.
     * 
     * @see self::from() - Create from string, resource, or file.
     * @see self::fromString() - Create from a string.
     * @see self::fromResource() - Create from a stream resource.
     *
     * @example - Deferred open with custom context:
     * ```php
     * $opts = stream_context_create(['http' => ['method' => 'GET']]);
     * $stream  = new Stream('http://example.com/file', 'rb', autoOpen: false);
     * 
     * $stream->setContext($opts)->open();
     * ```
     */
    public function __construct(
        protected string $filename,
        protected string $mode = 'c+b',
        protected bool $autoOpen = true
    ) 
    {
        if ($this->autoOpen) {
            $this->open();
        }
    }

    /**
     * Create a stream instance from a resource, file path, or raw string content.
     *
     * This method detects the type of the given source and delegates creation:
     *
     * - resource → wraps the existing stream
     * - string (file path) → opens a file stream using the given mode
     * - string (non-file) → creates an in-memory stream from content
     *
     * @param mixed $source Stream resource, file path, or raw string content.
     * @param string $mode File open mode (used only when source is a file path).
     * @param bool $readonly Weather to mark stream as read-only object (default: `false`).
     *
     * @return static Return new instance of stream class. 
     * @throws RuntimeException If the source type is not supported.
     * 
     * @see self::fromString()
     * @see self::fromResource()
     */
    public static function from(mixed $source, string $mode = 'wb+', bool $readonly = false): static
    {
        if (is_resource($source)) {
            return self::fromResource($source, $readonly);
        }

        if (!is_string($source)) {
            throw new RuntimeException(
                'Stream::from accepts file path, resource, or string content.'
            );
        }

        if (self::isFopenLike($source)) {
            $instance = static::newStatic($source, $mode, autoOpen: true);
            $instance->isReadonly = $readonly;

            return $instance;
        }

        return self::fromString($source, $mode);
    }

    /**
     * Wraps an already-open stream resource in a Stream instance without taking ownership.
     *
     * The returned instance will never call fclose() on the resource; lifetime
     * management stays with the caller.
     *
     * @param resource $resource A valid, open stream resource.
     * @param string $filename Optional filename to associate with the resource
     *                           (used for error messages only; does not affect I/O).
     * @param bool $readonly Weather to mark stream as read-only object (default: `false`).
     *
     * @return self A new Stream instance wrapping the supplied resource.
     * @throws InvalidArgumentException If $resource is not a valid stream resource.
     * @see self::from()
     * @see self::fromString()
     *
     * @example - Wrapping an existing handle:
     * ```php
     * $fp     = fopen('/tmp/shared.log', 'a+b');
     * $stream = Stream::fromResource($fp, '/tmp/shared.log');
     *
     * $stream->lock(LOCK_EX);
     * $stream->write("entry\n");
     * $stream->unlock();
     * // fclose($fp) is still the caller's responsibility.
     * ```
     */
    public static function fromResource(mixed $resource, string $filename = '', bool $readonly = false): self
    {
        self::assertNewResource($resource);

        $instance = static::newStatic($filename, autoOpen: false)
            ->setResource($resource);

        $instance->isReadonly = $readonly;

        return $instance;
    }

    /**
     * Create a read-write Stream from a plain string.
     *
     * The string is written into a `php://temp` stream opened with the given
     * mode, the pointer is rewound to position 0, and the stream is returned
     * ready for both reading and writing.
     *
     * @param string $content The raw string content to seed the stream with.
     * @param string $mode The `fopen()` mode used to open the stream (default: `'wb+'`).
     * @param bool $readonly Weather to mark stream as read-only object (default: `false`).
     *
     * @return static A new Stream instance containing the given content.
     * @throws RuntimeException If the content looks like a filesystem path, or if
     *                          the temporary stream cannot be created.
     * 
     * @see self::from()
     * @see self::fromResource()
     */
    public static function fromString(string $content, string $mode = 'wb+', bool $readonly = false): static
    {
        if (self::isFopenLike($content)) {
            throw new RuntimeException(
                'Stream::fromString does not accept file paths.'
            );
        }

        $instance = static::newStatic('php://temp', $mode, autoOpen: true);

        if ($content) {
            $instance->write($content);

            if ($instance->isSeekable()) {
                try{
                    $instance->rewind();
                } catch(Throwable){}
            }
        }

        $instance->isReadonly = $readonly;

        return $instance;
    }

    /**
     * Set whether the stream operates in blocking mode.
     *
     * @param bool $blocking True for blocking, false for non-blocking.
     *
     * @return bool True on success, false on failure.
     * @throws RuntimeException If the stream is closed or operation fails.
     *
     * @example - Make reads/writes block until completion:
     * ```php
     * $stream->setBlocking(true);
     * ```
     */
    public function setBlocking(bool $blocking): bool
    {
        $this->assertResource();

        $result = stream_set_blocking($this->resource, $blocking);

        if ($result === false) {
            throw new RuntimeException('Failed to set stream blocking mode.');
        }

        return $result;
    }

    /**
     * Mark the stream as read-only or writable.
     *
     * When set to read-only, all write operations (write, truncate, etc.)
     * will be blocked at the API level.
     * 
     * If $immutable is true, the stream becomes permanently read-only and cannot
     * be changed back to writable.
     *
     * @param bool $readonly True to make the stream read-only.
     * @param bool $immutable True to enforce permanent read-only mode.
     *
     * @return self Return instance of stream class.
     */
    public function setReadOnly(bool $readonly, bool $immutable = false): self
    {
        $this->isReadonly = $readonly;

        if ($this->isReadonlyImmutable || $immutable) {
            $this->isReadonlyImmutable = true;
            $this->isReadonly = true; 
        }

        return $this;
    }

    /**
     * Set the read/write timeout for the stream.
     *
     * @param int $seconds Timeout seconds.
     * @param int $microseconds Timeout microseconds.
     *
     * @return bool True on success, false on failure.
     * @throws RuntimeException If the stream is closed or operation fails.
     *
     * @example - Example:
     * ```php
     * $stream->setTimeout(2, 500000); // 2.5 seconds timeout
     * ```
     */
    public function setTimeout(int $seconds, int $microseconds = 0): bool
    {
        $this->assertResource();
        $result = stream_set_timeout($this->resource, $seconds, $microseconds);

        if ($result === false) {
            throw new RuntimeException('Failed to set stream timeout.');
        }

        return $result;
    }

    /**
     * Attaches a stream context to be used the next time open() is called.
     *
     * Must be called before open() (or before construction when $autoOpen is true).
     * Setting the context after the stream is already open has no effect on the
     * current handle.
     *
     * @param resource $context A context resource created by stream_context_create().
     *
     * @return self Return instance of stream class.
     * @throws RuntimeException If the stream is already open.
     *
     * @example - HTTP context:
     * ```php
     * $ctx = stream_context_create(['http' => ['timeout' => 5]]);
     * $stream = new Stream('http://example.com/data', 'rb', autoOpen: false);
     * $stream->setContext($ctx)->open();
     * ```
     */
    public function setContext(mixed $context): self
    {
        if ($this->resource !== null) {
            throw new RuntimeException(
                'Cannot change context after the stream is already open. Close the stream first.'
            );
        }

        $this->context = $context;
        return $this;
    }

    /**
     * Set the underlying stream resource for the wrapper.
     *
     * This replaces the current stream resource with a new one. The provided
     * resource **must be a valid PHP stream** (`get_resource_type() === 'stream'`).
     * If a previous resource exists, it will be closed first. After setting,
     * the wrapper does not “own” the resource, meaning it will not close it
     * automatically unless explicitly told.
     *
     * @param mixed $resource A valid PHP stream resource to wrap.
     * @param string $filename Optional filename for the stream.
     *
     * @return self Returns the current instance for chaining.
     *
     * @throws InvalidArgumentException If the provided resource is not a valid stream.
     */
    public function setResource(mixed $resource, string $filename = ''): self
    {
        self::assertNewResource($resource, $this->allowedResourceType);

        if ($this->resource) {
            $this->close();
        }

        $this->filename = $filename;
        $this->resource = $resource;
        $this->isOwnsHandler = false;

        return $this;
    }

    /**
     * Set the allowed stream resource type for this instance.
     *
     * This defines the expected resource type for any stream assigned
     * to this wrapper. Only valid PHP stream types are accepted.
     *
     * @param string $expected The expected resource type (e.g., "stream").
     *
     * @return self Returns instance stream class.
     * @throws InvalidArgumentException If the provided type is empty or invalid.
     */
    public function setAllowedResourceType(string $expected): self 
    {
        $expected = trim($expected);

        if ($expected === '') {
            throw new InvalidArgumentException('Allowed type cannot be empty.');
        }

        $this->allowedResourceType = $expected;
        return $this;
    }

    /**
     * Returns the PHP resource type string of the underlying handle, or null if closed.
     *
     * For a normal file or pipe this returns 'stream'. Use isOpen() to check
     * whether the stream is usable.
     *
     * @return string|null Resource type string, or null when the handle is closed.
     *
     * @example - Inspect resource type:
     * ```php
     * $stream = new Stream('php://memory', 'r+b');
     * echo $stream->getType(); // "stream"
     * 
     * $stream->close();
     * var_dump($stream->getType()); // NULL
     * ```
     */
    public function getType(): ?string
    {
        if($this->resource === null){
            return null;
        }

        if(is_resource($this->resource)){
            return get_resource_type($this->resource);
        }

        return gettype($this->resource);
    }

    /**
     * Get the internal resource identifier.
     *
     * Returns the unique ID assigned by PHP to the underlying stream resource,
     * or null if the stream is not initialized.
     *
     * @return int|null Resource ID or null if no resource is available.
     */
    public function getId(): ?int
    {
        return $this->resource ? get_resource_id($this->resource) : null;
    }

    /**
     * Returns the underlying stream resource.
     *
     * @return resource|null Returns the stream resource, or null if closed.
     */
    public function getResource(): mixed
    {
        return $this->resource;
    }

    /**
     * Returns true when the underlying file handle is open and valid.
     *
     * @return bool Return true if stream is open, otherwise false.
     *
     * @example - Guard before I/O:
     * ```php
     * if ($stream->isOpen()) {
     *     echo $stream->read();
     * }
     * ```
     */
    public function isOpen(): bool
    {
        return $this->resource && is_resource($this->resource);
    }

    /**
     * Check if the stream resource matches a specific type.
     *
     * Compares the actual type of the underlying stream resource with the
     * given type string.
     *
     * @param string $type The expected resource type (e.g., "stream", "file").
     *
     * @return bool True if the resource type matches, false otherwise.
     */
    public function isType(string $type): bool
    {
        return $this->getType() === $type;
    }

    /**
     * Check whether the stream is read-only.
     *
     * Returns true if the stream is marked as read-only, meaning write operations
     * (such as write, truncate, or overwrite) are not allowed.
     *
     * @return bool True if the stream is read-only, false otherwise.
     */
    public function isReadOnly(): bool
    {
        return $this->isReadonly;
    }

    /**
     * Returns true when the stream supports random-access seeking.
     *
     * Non-seekable streams (network sockets, pipes, php://stdin) return false.
     * Calling seek() on a non-seekable stream returns -1 without throwing.
     *
     * @return bool `true` if the stream is seekable, `false` if it is not or
     *              if the stream has been detached.
     *
     * @example - Conditional seek:
     * ```php
     * if ($stream->isSeekable()) {
     *     $stream->seek(0, SEEK_END);
     *     $size = $stream->tell();
     *     $stream->rewind();
     * }
     * ```
     */
    public function isSeekable(): bool
    {
        return (bool) ($this->metadata('seekable') ?? false);
    }

    /**
     * Returns true when the stream mode allows reading.
     *
     * Detection is based on the mode string: any mode containing 'r' or '+' is
     * considered readable. Modes like 'w', 'a', 'x', and 'c' (without '+') are
     * write-only and return false.
     *
     * @return bool `true` if the stream is readable, `false` otherwise.
     *
     * @example - Guard a read:
     * ```php
     * $stream = new Stream('/tmp/out.txt', 'wb');
     * if (!$stream->isReadable()) {
     *     throw new \LogicException('Stream is write-only.');
     * }
     * ```
     */
    public function isReadable(): bool
    {
        return $this->isReWr('readable');
    }

    /**
     * Returns true when the stream mode allows writing.
     *
     * Any mode other than plain 'r' (without '+') is considered writable. This
     * includes 'r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+', and their
     * binary ('b') variants.
     *
     * @return bool `true` if the stream is writable, `false` otherwise.
     *
     * @example - Guard a write:
     * ```php
     * $stream = new Stream('/tmp/data.txt', 'rb');
     * if (!$stream->isWritable()) {
     *     throw new \LogicException('Stream is read-only.');
     * }
     * ```
     */
    public function isWritable(): bool
    {
        return $this->isReWr('writable');
    }

    /**
     * Returns true when another process (or a non-cooperative thread) currently
     * holds an exclusive lock on the stream file.
     *
     * The check uses a non-blocking LOCK_EX attempt. If the lock is successfully
     * acquired it is immediately released and false is returned (not locked by
     * others). If the attempt fails, another holder exists and true is returned.
     *
     * Note: flock() is per-process on Linux. If *this process* already holds the
     * lock, the probe will succeed (re-entrant), so isLocked() will return false
     * even though the file is locked — it only detects *external* locks.
     *
     * @return bool True if an external lock is detected, false otherwise.
     *
     * @example - Poll before acquiring:
     * ```php
     * if ($stream->isLocked()) {
     *     throw new RuntimeException('File is locked by another process.');
     * }
     * $stream->lock(LOCK_EX);
     * ```
     */
    public function isLocked(): bool
    {
        if (!$this->resource) {
            return false;
        }

        if (flock($this->resource, LOCK_EX | LOCK_NB)) {
            flock($this->resource, LOCK_UN);
            return false;
        }

        return true;
    }

    /**
     * Check whether the entire stream content is valid JSON.
     *
     * If the stream is seekable, rewinds to the beginning before validating.
     * Returns false for empty streams, unreadable resources, or invalid JSON.
     * 
     * @param int $depth Maximum nesting depth of the structure being decoded (default: 512).
     *
     * @return bool Return true if the stream contains valid JSON, false otherwise.
     *
     * @example - Example:
     * ```php
     * if ($stream->isJsonString()) {
     *     echo "Valid JSON detected!";
     * }
     * ```
     */
    public function isJsonString(int $depth = 512): bool
    {
        try {
            $buffer = $this->buffer();
            $buffer = ltrim($buffer);

            if ($buffer === '') {
                return false;
            }

            $first = $buffer[0] ?? '';

            if ($first !== '{' && $first !== '[') {
                return false;
            }

            return json_validate($buffer, $depth);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Opens (or re-opens) the stream using the configured path and mode.
     *
     * If the stream is already open, close() is called first to prevent
     * handle leaks before the new handle is created.
     *
     * @param string|null $mode fopen() mode override. When null the mode passed
     *                          to the constructor (default 'c+b') is used.
     *
     * @return bool Returns true on success (the handle is a valid stream resource).
     * @throws RuntimeException If the file cannot be opened.
     *
     * @example - Re-open in read-only mode:
     * ```php
     * $stream = new Stream('/var/log/app.log', 'c+b');
     * // ... write some data ...
     * $stream->close();
     * $stream->open('rb');  // re-open read-only
     * echo $stream->read();
     * ```
     */
    public function open(?string $mode = null): bool
    {
        $this->assertFopenTarget($this->filename);

        if ($this->resource !== null) {
            $this->close();
        }

        $mode ??= $this->mode;
        $this->mode = $mode;

        $this->resource = fopen($this->filename, $this->mode, false, $this->context);

        if ($this->resource === false) {
            $this->resource = null;
            throw new RuntimeException(sprintf('Failed to open stream: %s', $this->filename));
        }

        $this->isOwnsHandler = true;
        return is_resource($this->resource);
    }

    /**
     * Closes the stream and releases the file handle.
     *
     * Only closes the handle when this instance owns it (i.e. not injected via
     * {@see fromResource()}). After close(), all I/O methods will throw a RuntimeException
     * until open() is called again.
     *
     * @return void
     *
     * @example - Explicit close:
     * ```php
     * $stream = new Stream('/tmp/data.bin');
     * $stream->write('hello');
     * $stream->close();
     * // $stream->read(); // would throw RuntimeException
     * ```
     */
    public function close(): void
    {
        if ($this->isOwnsHandler && $this->isOpen()) {
            fclose($this->resource);
        }

        $this->resource = null;
    }

    /**
     * Detach the underlying PHP resource from this stream object.
     *
     * Once detached the stream is in an unusable state (equivalent to a
     * closed stream), but the caller receives the raw resource and takes
     * full responsibility for closing it.
     *
     * @return resource|null The detached resource, or `null` if the stream
     *                        has already been detached or closed.
     */
    public function detach(): mixed
    {
        if (!isset($this->resource)) {
            return null;
        }

        $res = $this->resource;
        $this->resource = null;
        return $res;
    }

    /**
     * Retrieves stream metadata, optionally filtered to a single key.
     *
     * Common keys:
     * 'seekable', 'mode', 'uri', 'eof', 'wrapper_type', 'stream_type',
     * 'timed_out', 'blocked', 'unread_bytes'.
     *
     * @param string|null $key Metadata key to retrieve. Pass null to get the full array.
     *
     * @return mixed Full metadata array when $key is null; scalar value for a specific key;
     *               null if the key does not exist or the handle is closed;
     *               empty array when $key is null and the handle is closed.
     *
     * @example - Check wrapper type:
     * ```php
     * $stream = new Stream('php://temp', 'r+b');
     * echo $stream->metadata('stream_type'); // "TEMP"
     * print_r($stream->metadata());          // full array
     * ```
     */
    public function metadata(?string $key = null): mixed
    {
        $meta = [];

        if ($this->resource) {
           $meta = stream_get_meta_data($this->resource);
        }

        return ($key === null) ? $meta : ($meta[$key] ?? null);
    }

    /**
     * Returns low-level file statistics for the open handle via fstat().
     *
     * **Example return keys:**
     * 'dev', 'ino', 'mode', 'nlink', 'uid', 'gid', 'rdev', 'size', 'atime', 
     * 'mtime', 'ctime', 'blksize', 'blocks'.
     * 
     * @param string|null $key Optional fstat result key (e.g, `mtime`).
     *
     * @return array<string,mixed>|mixed Return fstat result: 
     *                      - An associative fstat array if `$key` is null, or empty array on failure.
     *                      - fstat key value if `$key` is set, or null on failure.
     *
     * @example - Check modification time:
     * ```php
     * $stat  = $stream->stat();
     * $mtime = $stat['mtime'] ?? 0;
     * echo 'Last modified: ' . date('Y-m-d H:i:s', $mtime);
     * ```
     */
    public function stat(?string $key = null): mixed
    {
        $stat = [];

        if ($this->resource) {
            $stat = fstat($this->resource) ?: [];
        }

        if($key === null){
            return $stat;
        } 

        return $stat[$key] ?? null;
    }

    /**
     * Calculate the byte size of the stream contents.
     *
     * For regular files, uses `fstat()` (O(1)).
     * For other streams (e.g., php://temp), falls back to seeking to the end
     * and calling `ftell()`. The original stream position is restored afterwards.
     * Returns -1 if the size cannot be determined (non-seekable stream without `fstat` support).
     *
     * @return int File size in bytes, or -1 if unavailable.
     *
     * @example - Guard large reads:
     * ```php
     * $size = $stream->size();
     * if ($size > 10 * 1024 * 1024) {
     *     throw new \OverflowException('File exceeds 10 MiB limit.');
     * }
     * $data = $stream->read();
     * ```
     */
    public function size(): int
    {
        $this->assertResource();

        $size = $this->stat('size');

        if ($size !== null) {
            return (int) $size;
        }

        $uri = $this->metadata('uri') ?: $this->filename;
        
        if ($uri && is_file((string) $uri)) {
           clearstatcache(true, $uri);
        }

        if (!$this->isSeekable()) {
            return $this->__size($uri);
        }

        $pos = ftell($this->resource);
        if ($pos === false) {
            return $this->__size($uri);
        }

        if (fseek($this->resource, 0, SEEK_END) !== 0) {
            return $this->__size($uri);
        }

        $size = ftell($this->resource);
        fseek($this->resource, $pos, SEEK_SET);

        if($size === false){
            return $this->__size($uri);
        }

        return (int) $size;
    }

    /**
     * Moves the file pointer to the given byte offset.
     *
     * Returns 0 on success, -1 on failure or when the stream is not seekable
     * (matching fseek() semantics).
     * 
     * @param int $offset Byte position to seek to. 
     *          Use negative values with SEEK_END to seek from the end of the file.
     * @param int $whence One of the `SEEK_*` constants (default: `SEEK_SET`).
     *                     - `SEEK_SET` — absolute position from the start.
     *                     - `SEEK_CUR` — offset relative to the current position.
     *                     - `SEEK_END` — offset relative to the end of the stream.
     *
     * @return void.
     * @throws RuntimeException If the stream has been detached, is not seekable, or fails.
     *
     * @example - Append without truncation:
     * ```php
     * $stream->lock(LOCK_EX);
     * $stream->seek(0, SEEK_END);
     * $stream->write("new line\n");
     * $stream->flush();
     * $stream->unlock();
     * ```
     *
     * @example - Read the last 256 bytes:
     * ```php
     * $stream->seek(-256, SEEK_END);
     * $tail = $stream->read(256);
     * ```
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->assertResource();

        if (!$this->isSeekable()) {
            throw new RuntimeException('Error, stream is not seekable.');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException(sprintf(
                'Failed to seek to offset %d (whence: %d) in the stream.',
                $offset,
                $whence
            ));
        }
    }

    /**
     * Returns the current byte offset of the file pointer.
     *
     * @return int Current position in bytes.
     * @throws RuntimeException If the stream has been detached or if `ftell()` fails.
     * 
     * @example - Save and restore position:
     * ```php
     * $saved = $stream->tell();
     * $stream->seek(0);
     * $header = $stream->read(8);
     * $stream->seek($saved);
     * ```
     */
    public function tell(): int
    {
        $this->assertResource();

        $position = ftell($this->resource);

        if ($position === false) {
            throw new RuntimeException(
                'Unable to determine the current position of the stream pointer.'
            );
        }

        return $position;
    }

    /**
     * Rewinds the file pointer to the beginning of the stream (offset 0).
     *
     * @return void
     * @throws RuntimeException If the stream has been detached, is not seekable,
     *                          or if the rewind operation fails.
     *
     * @example - Read from the beginning after writing:
     * ```php
     * $stream->write('hello');
     * $stream->rewind();
     * echo $stream->read(); // "hello"
     * ```
     */
    public function rewind(): void
    {
        $this->assertResource();

        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable.');
        }

        if (!rewind($this->resource)) {
            throw new RuntimeException(
                'Failed to rewind the stream pointer to the beginning.'
            );
        }
    }

    /**
     * Returns true when the file pointer is at the end of the stream.
     *
     * @return bool Return true if EOF or no resource, otherwise false.
     *
     * @example - Read until EOF:
     * ```php
     * $stream->rewind();
     * while (!$stream->eof()) {
     *     echo $stream->readLine();
     * }
     * ```
     */
    public function eof(): bool
    {
        if(!$this->resource){
            return true;
        }

        return feof($this->resource);
    }

    /**
     * Acquire an advisory file lock with optional retry support.
     *
     * This method attempts to acquire a shared (read) or exclusive (write) lock
     * on the underlying stream resource using flock().
     *
     * By default, the call blocks until the lock is acquired. When LOCK_NB is
     * included in $flags, the call becomes non-blocking and will fail immediately
     * if the lock cannot be obtained.
     *
     * Retry logic applies only to blocking mode. When enabled, the method will
     * retry acquiring the lock up to the specified number of attempts, with an
     * optional delay between attempts.
     *
     * @param int $flags The lock options:
     *                   - LOCK_SH: Shared/read lock
     *                   - LOCK_EX: Exclusive/write lock
     *                   - LOCK_NB: Optional; enables non-blocking mode
     * @param int $retries Number of retry attempts in blocking mode (default: 1).
     * @param int $delay Delay in microseconds between retries (default: 0).
     * @param int|null &$wouldBlock Set by flock() in non-blocking mode if the lock would block.
     *
     * @return bool True if the lock was acquired.
     *
     * @throws InvalidArgumentException If neither LOCK_EX nor LOCK_SH is specified.
     * @throws RuntimeException If the lock cannot be acquired:
     *                          - immediately in non-blocking mode
     *                          - after all retries in blocking mode
     *
     * @see self::tryLock() Non-blocking, non-throwing alternative
     * @see self::unlock() Release the acquired lock
     *
     * @example Blocking exclusive lock:
     * ```php
     * $stream->lock(LOCK_EX);
     * $stream->seek(0, SEEK_END);
     * $stream->write("appended line\n");
     * $stream->flush();
     * $stream->unlock();
     * ```
     *
     * @example Non-blocking shared lock:
     * ```php
     * $wouldBlock = 0;
     * try {
     *     $stream->lock(LOCK_SH | LOCK_NB, 1, 0, $wouldBlock);
     *     echo "Lock acquired!";
     * } catch (RuntimeException $e) {
     *     if ($wouldBlock) {
     *         echo "Another process holds an exclusive lock.";
     *     }
     * }
     * ```
     */
    public function lock(
        int $flags,  
        int $retries = 1,
        int $delay = 0,
        ?int &$wouldBlock = null
    ): bool 
    {
        $this->assertResource(); 

        $locks = $flags & (LOCK_EX | LOCK_SH);
        if (!$locks) {
            throw new InvalidArgumentException('Must specify either LOCK_EX or LOCK_SH.');
        }

        $isNonBlocking = ($flags & LOCK_NB) !== 0;
        $label = ($locks === LOCK_EX) ? 'exclusive' : 'shared';
        $attempts = 0;

        do {
            if (flock($this->resource, $locks | ($isNonBlocking ? LOCK_NB : 0), $wouldBlock)) {
                return true;
            }

            if ($isNonBlocking) {
                throw new RuntimeException(sprintf(
                    'Unable to acquire %s lock on: %s',
                    $label,
                    $this->filename
                ));
            }

            if ($delay > 0) {
                usleep($delay);
            }
        } while (++$attempts <= $retries);

        throw new RuntimeException(sprintf(
            'Unable to acquire %s lock on: %s after %d attempts',
            $label,
            $this->filename,
            $retries
        ));
    }

    /**
     * Releases any advisory lock held on the stream.
     *
     * Equivalent to flock($handle, LOCK_UN). Safe to call when no lock is held.
     *
     * @return bool Return true on success, false on failure.
     *
     * @example - Always unlock in a finally block:
     * ```php
     * $stream->lock(LOCK_EX);
     * try {
     *     $stream->write($data);
     *     $stream->flush();
     * } finally {
     *     $stream->unlock();
     * }
     * ```
     */
    public function unlock(): bool
    {
        return $this->resource 
            && flock($this->resource, LOCK_UN);
    }

    /**
     * Reads from the stream.
     *
     * When $length is -1 (the default) the entire remaining content from the
     * current pointer position to EOF is returned using stream_get_contents().
     * When $length is positive, exactly $length bytes are read using fread().
     *
     * @param int $length Number of bytes to read, or -1 to read until EOF.
     *
     * @return string The data read from the stream.
     * @throws RuntimeException If the handle is closed or the read fails.
     * 
     * @see self::buffer() To read all content.
     *
     * @example - Read whole file under a shared lock:
     * ```php
     * $stream->lock(LOCK_SH);
     * $stream->rewind();
     * $content = $stream->read();
     * $stream->unlock();
     * ```
     *
     * @example - Read first 512 bytes:
     * ```php
     * $stream->rewind();
     * $header = $stream->read(512);
     * ```
     */
    public function read(int $length = -1): string
    {
        $this->assertResource();
        $this->assertReadable();

        $data = ($length === -1)
            ? stream_get_contents($this->resource)
            : fread($this->resource, $length);

        if ($data === false) {
            throw new RuntimeException('Read failed to retrieve contents.');
        }

        return $data;
    }

    /**
     * Read the entire stream content, always starting from the beginning.
     *
     * If the stream is seekable the pointer is rewound to position 0 before
     * reading, so previously consumed content is included in the result.
     * For non-seekable streams only the remaining (unread) bytes are returned.
     *
     * @return string The complete stream content (or remaining content for
     *                non-seekable streams).
     * @throws RuntimeException If the stream cannot be read.
     * @see self::read() To read specific length.
     */
    public function buffer(): string
    {
        if ($this->isSeekable()) {
            rewind($this->resource);
        }

        return $this->read(-1);
    }

    /**
     * Output stream contents directly to the client.
     *
     * Reads data from the current stream position and writes it to the output
     * buffer in chunks, making it suitable for large files and streaming
     * responses without excessive memory usage.
     *
     * If $length is -1, the entire remaining stream is sent until EOF.
     * Otherwise, only the specified number of bytes will be sent.
     *
     * The method automatically flushes output buffers and stops execution
     * if the client disconnects.
     *
     * @param int $length Number of bytes to send, or -1 for all.
     * @param int $chunkSize Number of bytes per read (default: `8192`).
     * @param float|int $delay Optional delay between chunk in seconds (default: 0).
     *
     * @return int Return total number of bytes sent to the output.
     * @throws RuntimeException If reading from the stream fails.
     */
    public function send(int $length = -1, int $chunkSize = 8192, float|int $delay = 0): int
    {
        $this->assertResource();
        $this->assertReadable();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $total = 0;
        $delay = is_float($delay) ? (int) round($delay * 1_000_000) : $delay;
        $delay = max(0, $delay);

        while (($length !== 0) && !feof($this->resource)) {
            $read = ($length < 0) ? $chunkSize : min($chunkSize, $length);
            $chunk = fread($this->resource, $read);

            if ($chunk === false) {
                throw new RuntimeException('Failed to read from stream.');
            }

            $len = strlen($chunk);
            if ($len === 0) {
                break;
            }

            echo $chunk;

            $total += $len;

            if ($length > 0) {
                $length -= $len;
            }

            flush();

            if (connection_aborted()) {
                break;
            }

            if($delay > 0){
                usleep($delay);
            }
        }

        return $total;
    }

    /**
     * Reads one line from the stream (up to and including the newline character).
     *
     * Wraps fgets(). Returns an empty string at EOF. When $length is provided,
     * reading stops after $length - 1 bytes even if no newline was encountered,
     * matching fgets() semantics.
     *
     * @param int|null $length Maximum number of bytes to read (including the newline).
     *                         Pass null to use PHP's internal default buffer.
     *
     * @return string The line read, including the trailing newline if present,
     *                or an empty string at EOF.
     * @throws RuntimeException If the handle is closed or fgets() fails.
     *
     * @example - Process a log file line by line:
     * ```php
     * $stream->lock(LOCK_SH);
     * $stream->rewind();
     * while (!$stream->eof()) {
     *     $line = $stream->readLine();
     *     if ($line === '') break;
     *     echo trim($line) . PHP_EOL;
     * }
     * $stream->unlock();
     * ```
     */
    public function readLine(?int $length = null): string
    {
        $this->assertResource();
        $line = fgets($this->resource, $length);

        if ($line === false) {
            if (feof($this->resource)) {
                return '';
            }

            throw new RuntimeException('Read line failed.');
        }

        return $line;
    }

    /**
     * Writes data to the stream at the current (or specified) byte offset.
     *
     * When $offset is non-zero the pointer is first moved to that position via
     * seek() before writing. Note that seek() is a no-op on non-seekable streams
     * and $offset is silently ignored in that case. The method does NOT acquire
     * a lock — callers should lock/unlock around write operations as needed.
     *
     * @param string $data The data to write.
     * @param int $offset Byte offset to seek to before writing. 0 (default) writes
     *                       at the current pointer position without seeking.
     *
     * @return int Number of bytes written.
     * @throws RuntimeException If the handle is closed or write() fails.
     *
     * @example - Append under an exclusive lock:
     * ```php
     * $stream->lock(LOCK_EX);
     * $stream->seek(0, SEEK_END);
     * $bytes = $stream->write("new entry\n");
     * $stream->flush();
     * $stream->unlock();
     * ```
     */
    public function write(string $data): int
    {
        $this->assertResource();
        $this->assertWritable();

        $written = fwrite($this->resource, $data);

        if ($written === false) {
            throw new RuntimeException('Failed to write data.');
        }

        return $written;
    }

    /**
     * Writes data to the stream at the current (or specified) byte offset.
     *
     * When $offset is non-zero the pointer is first moved to that position via
     * seek() before writing. Note that seek() is a no-op on non-seekable streams
     * and $offset is silently ignored in that case. The method does NOT acquire
     * a lock — callers should lock/unlock around write operations as needed.
     *
     * @param string $data The data to write.
     * @param int $offset Byte offset to seek to before writing. 0 (default) writes
     *                       at the current pointer position without seeking.
     *
     * @return int Number of bytes written.
     * @throws RuntimeException If the handle is closed or write() fails.
     * 
     * @example - Overwrite at a specific offset:
     * ```php
     * $stream->lock(LOCK_EX);
     * $bytes = $stream->writeAt('PATCHED', offset: 16);
     * $stream->flush();
     * $stream->unlock();
     * ```
     */
    public function writeAt(string $data, int $offset): int
    {
        $this->seek($offset);
        return $this->write($data);
    }

    /**
     * Overwrites the entire stream content with $data.
     *
     * Acquires an exclusive lock, rewinds, truncates to zero, writes $data,
     * flushes, and releases the lock — all atomically from the caller's perspective.
     * This is the safe, idiomatic way to replace a file's contents in one call.
     *
     * @param string $data The new content to write.
     *
     * @return int Number of bytes written.
     * @throws RuntimeException If the handle is closed, the lock cannot be acquired,
     *                          or the write fails.
     *
     * @example - Persist updated configuration:
     * ```php
     * $stream = new Stream('/etc/myapp/config.json', 'c+b');
     * $stream->overwrite(json_encode($config, JSON_PRETTY_PRINT));
     * $stream->close();
     * ```
     */
    public function overwrite(string $data): int
    {
        $this->lock(LOCK_EX);

        try{
            if ($this->isSeekable()) {
                rewind($this->resource);
            }

            $this->truncate(0);
            $written = $this->write($data);

            $this->flush();
            return $written;
        } finally {
            $this->unlock();
        }
    }

    /**
     * Performs a search-and-replace on the entire stream content.
     *
     * Reads the file under a shared lock, applies str_replace(), and writes the
     * result back via contents() (which acquires an exclusive lock internally).
     *
     * @param array|string $search The value(s) to search for.
     * @param array|string $replace The replacement value(s).
     *
     * @return int Number of bytes written after replacement.
     * @throws RuntimeException If the handle is closed, a lock cannot be acquired,
     *                          or any I/O operation fails.
     *
     * @example - Rotate log level token:
     * ```php
     * $stream = new Stream('/etc/myapp/config.ini', 'c+b');
     * $stream->replace('level=debug', 'level=info');
     * $stream->close();
     * ```
     */
    public function replace(array|string $search, array|string $replace): int
    {
        $this->lock(LOCK_SH);
        try{
            if ($this->isSeekable()) {
                rewind($this->resource);
            }

            $content = $this->read();
        } finally {
            $this->unlock();
        }

        return $this->overwrite(str_replace(
            $search, 
            $replace, 
            $content
        ));
    }

    /**
     * Check if the stream contains the given string.
     *
     * @param string $needle The string to search for.
     * @param bool $rewind Whether to rewind before searching (default: true).
     *
     * @return bool True if found, false otherwise.
     */
    public function contains(string $needle, bool $rewind = true): bool
    {
        $this->assertResource();
        $this->assertReadable();

        $needle = trim($needle);

        if ($needle === '') {
            return true;
        }

        $position = $this->tell();

        if ($rewind) {
            $this->rewind();
        }

        while (!$this->eof()) {
            if (str_contains($this->read(4096), $needle)) {
                $this->seek($position);
                return true;
            }
        }

        $this->seek($position);
        return false;
    }

    /**
     * Search for occurrences of a string in the stream.
     *
     * Returns an array of matches with line number and byte position:
     *
     * [
     *     ['line' => 1, 'position' => 15],
     *     ['line' => 3, 'position' => 42],
     * ]
     *
     * @param string $needle The string to search for.
     * @param bool $rewind Whether to rewind before searching.
     * @param bool $firstOnly Stop after the first occurrence match is found.
     *
     * @return array<int,array{line:int,position:int}> Return found content and position.
     */
    public function search(string $needle, bool $rewind = true, bool $firstOnly = false): array
    {
        $this->assertResource();
        $this->assertReadable();

        $needle = trim($needle);

        if ($needle === '') {
            return [];
        }

        $originalPos = $this->tell();

        if ($rewind) {
            $this->rewind();
        }

        $results = [];
        $buffer = '';
        $offset = 0;
        $line = 1;

        $needleLen = strlen($needle);

        while (!$this->eof()) {
            $chunk = $this->read(4096);
            $data = $buffer . $chunk;

            $pos = 0;
            while (($found = strpos($data, $needle, $pos)) !== false) {
                $absolutePos = $offset - strlen($buffer) + $found;

                $lineCount = substr_count(substr($data, 0, $found), "\n");
                $matchLine = $line + $lineCount;

                $results[] = [
                    'line' => $matchLine,
                    'position' => $absolutePos,
                ];

                if ($firstOnly) {
                    $this->seek($originalPos);
                    return $results;
                }

                $pos = $found + 1;
            }

            $line += substr_count($chunk, "\n");
            $buffer = substr($data, -($needleLen - 1));

            $offset += strlen($chunk);
        }

        $this->seek($originalPos);

        return $results;
    }

    /**
     * Truncates the stream to the given size.
     *
     * If $size is smaller than the file's current size, the extra data is
     * discarded. If $size is larger, the file is extended with null bytes.
     * The file pointer is NOT moved after truncation — call rewind() or seek()
     * as required.
     *
     * @param int $size Target size in bytes. Defaults to 0 (empty the file).
     *
     * @return bool True on success, false on failure.
     * @throws RuntimeException If the handle is closed.
     *
     * @example - Clear and re-fill a temp file:
     * ```php
     * $stream->lock(LOCK_EX);
     * $stream->truncate(0);
     * $stream->rewind();
     * $stream->write($freshData);
     * $stream->flush();
     * $stream->unlock();
     * ```
     */
    public function truncate(int $size = 0): bool
    {
        $this->assertResource();
        $this->assertWritable();

        return ftruncate($this->resource, $size);
    }

    /**
     * Flushes the output buffers of the stream to the underlying storage device.
     *
     * Should be called after a sequence of write() calls and before unlock()
     * to ensure data is durably persisted before releasing the lock.
     *
     * @return bool True on success, false on failure.
     * @throws RuntimeException If the handle is closed.
     *
     * @example - Flush after writing:
     * ```php
     * $stream->lock(LOCK_EX);
     * $stream->write($data);
     * $stream->flush();
     * $stream->unlock();
     * ```
     */
    public function flush(): bool
    {
        $this->assertResource();
        return fflush($this->resource);
    }

    /**
     * Copies data from this stream into another stream.
     *
     * Starts copying from the current pointer of the source. Use {@see rewind()} 
     * to start from the beginning. The destination pointer ends at the end of written data.
     *
     * @param self $destination Target stream to copy into.
     * @param int $length Maximum bytes to copy. Default -1 copies until EOF.
     * @param int $offset Byte offset in the source stream. Default 0 uses current position.
     *
     * @return int Number of bytes copied.
     * @throws RuntimeException If the source or destination is closed or copy fails.
     *
     * @example - Copy full stream to temp buffer:
     * ```php
     * $source = new Stream('/var/data/report.csv', 'rb');
     * $buffer = new Stream('php://temp', 'r+b');
     *
     * $source->rewind();
     * $bytesCopied = $source->copy($buffer);
     *
     * $buffer->rewind();
     * echo $buffer->read();
     *
     * $source->close();
     * $buffer->close();
     * ```
     *
     * @example - Copy first 4 KiB only:
     * ```php
     * $source->rewind();
     * $source->copy($dest, length: 4096);
     * ```
     */
    public function copy(self $destination, int $length = -1, int $offset = 0): int
    {
        $this->assertResource();
        $destination->assertResource();
        $destination->assertWritable('Destination ');

        $copied = stream_copy_to_stream(
            $this->resource,
            $destination->getResource(),
            ($length === -1) ? -1 : $length,
            $offset
        );

        if ($copied === false) {
            throw new RuntimeException(sprintf(
                'Failed to copy stream from "%s" to destination.',
                $this->filename ?? '[unknown]'
            ));
        }

        return $copied;
    }

    /**
     * Generate a hash checksum of the entire stream content.
     *
     * The stream pointer will be rewound before reading if the stream is seekable.
     * For non-seekable streams, hashing is performed from the current position.
     *
     * @param string $algo Hash algorithm (e.g. "xxh3", "sha256", "md5").
     * @param bool $binary Whether to return raw binary output.
     *
     * @return string Return generated hash string.
     * @throws RuntimeException If the stream is invalid, not open, or hashing fails.
     *
     * @example - Example:
     * ```php
     * $hash = $stream->checksum('sha256');
     * 
     * // Binary hash
     * $binaryHash = $stream->checksum('xxh3', true);
     * ```
     */
    public function checksum(string $algo = 'xxh3', bool $binary = false): string
    {
        static $algos = null;
        $algos ??= hash_algos();

        if (!in_array($algo, $algos, true)) {
            throw new RuntimeException(sprintf(
                'Unsupported hash algorithm: %s',
                $algo
            ));
        }

        $hash = hash($algo, $this->buffer(), $binary);

        if ($hash === false) {
            throw new RuntimeException('Failed to generate checksum.');
        }

        return $hash;
    }

    /**
     * Attempt to acquire a non-blocking advisory file lock.
     *
     * This is a fail-fast wrapper around {@see self::lock()} that always operates
     * in non-blocking mode. It will attempt to acquire the lock once and return
     * immediately.
     *
     * Unlike {@see self::lock()}, this method does not throw an exception on failure.
     * Instead, it returns false if the lock cannot be obtained.
     *
     * @param int $flags Lock type: LOCK_SH (shared/read) or LOCK_EX (exclusive/write).
     *                   LOCK_NB is automatically applied and does not need to be passed.
     * @param int $retries Ignored in non-blocking mode (kept for signature consistency).
     * @param int $delay Ignored in non-blocking mode (kept for signature consistency).
     * @param int|null &$wouldBlock Set to 1 if the lock could not be acquired because it would block.
     *
     * @return bool True if the lock was acquired, false otherwise.
     *
     * @throws InvalidArgumentException If neither LOCK_EX nor LOCK_SH is specified.
     *
     * @example - Fail-fast exclusive lock:
     * ```php
     * $wouldBlock = 0;
     * if (!$stream->tryLock(LOCK_EX, 1, 0, $wouldBlock)) {
     *     if ($wouldBlock) {
     *         echo "Another process holds the lock; skipping write.";
     *     }
     * } else {
     *     $stream->write($data);
     *     $stream->flush();
     *     $stream->unlock();
     * }
     * ```
     */
    public function tryLock(
        int $flags,  
        int $retries = 1,
        int $delay = 0,
        ?int &$wouldBlock = null
    ): bool 
    {
        try {
            return $this->lock($flags, $retries, $delay, $wouldBlock);
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Returns the full stream contents as a string, rewinding first.
     *
     * This method rewinds before reading so callers always
     * get the complete content regardless of the current pointer position.
     *
     * @return string Stream contents, or empty string on failure (safe for exception-free contexts).
     *
     * @example - Dump stream contents:
     * ```php
     * $stream->write('Hello, World!');
     * echo $stream->toString(); // "Hello, World!"
     * ```
     */
    public function toString(): string
    {
        try {
            return $this->buffer();
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * Decode the stream content into a JSON object (`stdClass`).
     *
     * Returns null if the buffer is empty, invalid JSON, or not an object at the root.
     * 
     * @param int $depth Maximum nesting depth of the structure being decoded (default: 512).
     * @param int $flags Optional decoding flags (default: JSON_BIGINT_AS_STRING).
     *
     * @return object|null Decoded object, or null on failure.
     *
     * @example - Example:
     * ```php
     * $obj = $stream->toObject();
     * if ($obj) {
     *     echo $obj->name;
     * }
     * ```
     */
    public function toObject(int $depth = 512, int $flags = JSON_BIGINT_AS_STRING): ?object
    {
        try {
            $buffer = $this->buffer();
            $buffer = ltrim($buffer);

            if (($buffer === '' || ($buffer[0] ?? '') !== '{') || !json_validate($buffer, $depth)) {
                return null;
            }

            $decoded = json_decode($buffer, false, $depth, $flags | JSON_THROW_ON_ERROR);
            return is_object($decoded) ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Decode the stream content into a JSON associative array.
     *
     * Returns an empty array for empty streams. Returns the raw content wrapped
     * in an array if JSON validation fails.
     * 
     * @param int $depth Maximum nesting depth of the structure being decoded (default: 512).
     * @param int $flags Optional decoding flags (default: JSON_BIGINT_AS_STRING).
     *
     * @return array Decoded array, or array with raw content on invalid JSON.
     *
     * @example - Example:
     * ```php
     * $arr = $stream->toArray();
     * print_r($arr);
     * ```
     */
    public function toArray(int $depth = 512, int $flags = JSON_BIGINT_AS_STRING): array
    {
        $buffer = $this->buffer();
        $buffer = ltrim($buffer);

        if ($buffer === '') {
            return [];
        }

        $first = $buffer[0] ?? '';

        if (($first !== '{' && $first !== '[') || !json_validate($buffer, $depth)) {
            return [];
        }

        try {
            return (array) json_decode($buffer, true, $depth, $flags | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Ensure the stream resource is valid.
     *
     * @throws RuntimeException If the resource is closed or invalid.
     * @see self::assertResourceType()
     * @see self::assertReadable()
     * @see self::assertWriteable()
     * 
     * @example - Custom method using assert:
     * ```php
     * public function hash(): string
     * {
     *     $this->assertResource();
     *     $this->rewind();
     *
     *     return hash('xxh3', $this->read());
     * }
     * ```
     * > Use {@see self::isOpen()} when you need a non-throwing check.
     */
    protected function assertResource(): void
    {
        if (is_resource($this->resource)) {
            return;
        }

        throw new RuntimeException(
            'Cannot operate on a closed or invalid stream resource.'
        );
    }

    /**
     * Ensure the resource type matches the expected type.
     *
     * @throws RuntimeException If the resource type does not match.
     * Use {@see self::isType()} when you need a non-throwing check.
     */
    protected function assertResourceType(): void
    {
        if ($this->isType($this->allowedResourceType)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Invalid resource type: expected "%s", got "%s".',
            $this->allowedResourceType,
            $this->getType()
        ));
    }

    /**
     * Ensure the stream is readable.
     *
     * Throws an exception if the underlying resource cannot be read.
     *
     * @param string|null $prefix Optional message prefix for context.
     *
     * @throws RuntimeException If the stream is not readable.
     * > Use {@see self::isReadable()} when you need a non-throwing check.
     */
    protected function assertReadable(?string $prefix = null): void
    {
        if ($this->isReadable()) {
            return;
        }

        throw new RuntimeException(sprintf(
            '%sstream is not readable.',
            $prefix ?? 'Error: '
        ));
    }

    /**
     * Ensure the stream is writable.
     *
     * This checks both the internal read-only flag and the underlying
     * resource capability.
     *
     * @param string|null $prefix Optional message prefix for context.
     *
     * @throws RuntimeException If the stream is read-only or not writable.
     * > Use {@see self::isWritable()} or {@see self::isReadOnly()} when you need a non-throwing check.
     */
    protected function assertWritable(?string $prefix = null): void
    {
        $prefix ??= 'Cannot write: ';

        if ($this->isReadOnly()) {
            throw new RuntimeException(sprintf(
                '%sstream is read-only.',
                $prefix
            ));
        }

        if ($this->isWritable()) {
            return;
        }

        throw new RuntimeException(sprintf(
            '%sstream is not writable.',
            $prefix
        ));
    }

    /**
     * Validate that the given resource is a valid PHP stream.
     *
     * This static helper ensures that the provided resource is a PHP stream
     * (i.e., `get_resource_type() === 'stream'`). It is intended for use
     * before assigning or wrapping a new stream resource.
     *
     * @param mixed $resource The resource to validate.
     * @param string $expected The expected resource type (default: 'stream').
     *
     * @throws InvalidArgumentException If the resource is not a valid stream.
     */
    protected static function assertNewResource(mixed $resource, string $expected = 'stream'): void
    {
        if (
            !is_resource($resource) 
            || get_resource_type($resource) !== $expected
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid stream resource provided; expected: %s resource, got: %s %s.',
                $expected,
                is_resource($resource) 
                    ? get_resource_type($resource)
                    : gettype($resource)
            ));
        }
    }

    /**
     * Validate that the given is a valid fopen() target.
     * 
     * Allow:
     *  URL, File or registered stream wrapper.
     *
     * @param string $filename The filename to validate.
     * 
     * @throws RuntimeException If not a valid target fopen.
     */
    protected function assertFopenTarget(string $filename): void
    {
        if ($filename === '') {
            throw new RuntimeException('Path cannot be empty.');
        }

        if (is_file($filename)) {
            if (is_readable($filename) || is_writable($filename)) {
                return;
            }

            throw new RuntimeException("File exists but is not readable or writable: {$filename}");
        }

        if($this->isUrl($filename, true)){
            return;
        }

        $this->isFile($filename, true);
    }

    /**
     * Override to create and return new stream instance.
     * 
     * This should be based on sub class constructor signature that align with parent constructor signature.
     *
     * @param string $filename The path to open stream.
     * @param string $mode File open mode (used only when source is a file path).
     * @param bool $autoOpen When true the file is opened immediately.
     * 
     * @return static Return new instance of stream class. 
     */
    protected static function newStatic(
        string $filename,
        string $mode = 'c+b',
        bool $autoOpen = true
    ): static 
    {
        return new static($filename, $mode, $autoOpen);
    }

    /**
     * Determine whether a string represents an existing filesystem path.
     *
     * @param string $input The filename to evaluate.
     * @param bool $throw Weather to throw exception if failed.
     *
     * @return bool Return `true` if the input resolves to an existing file =, otherwise `false`.
     * @throws RuntimeException If failed and `$throw` is `true`.
     */
    protected function isFile(string $input, bool $throw): bool
    {
        if (is_dir($input)) {
            if ($throw) {
                throw new RuntimeException("Path points to a directory, not a file: {$input}");
            }

            return false;
        }

        $dir = dirname($input);
        $valid = is_dir($dir) && is_writable($dir);

        if (!$valid && $throw) {
            throw new RuntimeException("Cannot create file; parent directory missing or not writable: {$dir}");
        }

        return $valid;
    }

    /**
     * Check if fopen target is a valid URL.
     * 
     * Checks for scheme: (`http`, `https`, `ftp` or `stream_get_wrappers`).
     * And also consider if `allow_url_fopen` is disabled or not.
     *
     * @param string $value The input string to validate.
     * @param bool $throw Weather to throw exception if failed.
     *
     * @return bool True if valid URL, false otherwise.
     * @throws RuntimeException If failed and `$throw` is `true`.
     */
    protected function isUrl(string $path, bool $throw): bool
    {
        $scheme = parse_url($path, PHP_URL_SCHEME);

        if ($scheme === null) {
            return false;
        }

        $scheme = strtolower($scheme);
        static $wrappers = null;

        $wrappers ??= stream_get_wrappers();

        if (!in_array($scheme, $wrappers, true)) {
            if ($throw) {
                throw new RuntimeException("Stream wrapper '{$scheme}' is not registered.");
            }
            return false;
        }

        if (in_array($scheme, ['http', 'https', 'ftp'], true)) {
            if (!filter_var($path, FILTER_VALIDATE_URL)) {
                if ($throw) {
                    throw new RuntimeException("Invalid URL: {$path}");
                }
                return false;
            }

            if (!ini_get('allow_url_fopen')) {
                if ($throw) {
                    throw new RuntimeException("allow_url_fopen is disabled; cannot open URL: {$path}");
                }
                return false;
            }
        }

        return true;
    }

    /**
     * Loosely detect if a string could be a valid fopen target.
     *
     * Matches:
     * - Local paths (absolute or relative)
     * - Filenames with extensions
     * - URLs (http, https, ftp)
     *
     * @param string $target The target fopen filename.
     * @return bool Return true if valid, otherwise false.
     */
    public static function isFopenLike(string $target): bool
    {
        $target = trim($target);

        if ($target === '') {
            return false;
        }

        return (
            preg_match('#^[a-z][a-z0-9+.-]*://[^\s]+$#i', $target)
            || preg_match('#^(?:[a-zA-Z]:)?[\\/](?:[\w\-.]+[\\/])*[\w\-.]+$#', $target)
            || preg_match('#^[\w\-.]+\.\w{1,5}$#', $target)
        );
    }
    
    /**
     * Converts the stream to a string by returning its full contents.
     *
     * Rewinds the stream before reading when the stream is seekable, so the
     * complete content is always returned.
     *
     * @return string Stream contents, or empty string on failure.
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Dynamically calls a callable function on the underlying stream resource.
     *
     * The function must be callable and expects the stream resource as its first argument.
     * Any additional arguments are passed as subsequent parameters.
     *
     * @param string $fn Name of the callable function (e.g., "stream_get_contents").
     * @param array $arguments Additional arguments to pass to the function.
     *
     * @return mixed The result of the function call.
     *
     * @throws RuntimeException If the stream is closed, invalid, or the function is not callable.
     *
     * @example - Examples:
     * ```php
     * // Read 1024 bytes from current position using native function
     * $data = $stream->fread(1024);
     *
     * // Get metadata
     * $meta = $stream->stream_get_meta_data();
     * ```
     */
    public function __call(string $fn, array $arguments): mixed
    {
        if($this->allowedDynamicFunctions === null){
            $this->allowedDynamicFunctions = [
                'fread', 'fwrite', 'fseek', 'ftell', 'fstat',
                'rewind', 'feof', 'fgetc', 'fgets', 'fflush',
                'ftruncate', 'stream_get_contents'
            ];
        }

        if ($this->allowedDynamicFunctions === [] || !in_array($fn, $this->allowedDynamicFunctions, true)) {
            throw new RuntimeException(sprintf(
                'Function "%s" is not allowed.',
                $fn
            ));
        }

        $this->assertResource();

        if (!is_callable($fn)) {
            throw new RuntimeException(sprintf(
                'Cannot call "%s": function is not callable or unavailable.',
                $fn
            ));
        }

        return $fn($this->resource, ...$arguments);
    }

    /**
     * Stream instances cannot be cloned.
     *
     * Cloning an owned handle would result in two objects both attempting to
     * fclose() the same resource, causing a double-free. 
     * 
     * Use {@see self::fromResource()} to share a handle across multiple Stream objects.
     *
     * @return never
     * @throws RuntimeException Always.
     */
    private function __clone()
    {
        throw new RuntimeException(
            'Stream instances cannot be cloned. Use Stream::fromResource() to share a handle.'
        );
    }

    /**
     * Closes the stream handle when the object is garbage-collected.
     *
     * Ensures resources are always released even when close() is not called
     * explicitly. Only closes handles that are owned by this instance.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Check if stream is readable or writeable.
     *
     * @param string $context The context (e.g, `readable` or `writable`).
     * 
     * @return bool Return true.
     */
    private function isReWr(string $context): bool
    {
        $mode = $this->metadata('mode') ?? $this->mode;

        if (!$mode) {
            return false;
        }

        $mode = str_replace(['b', 't'], '', $mode);
        $first = $mode[0] ?? '';


        if (str_contains($mode, '+')) {
            return true;
        }

        return match ($context) {
            'readable' => $first === 'r',
            'writable' => strpbrk($first, 'waxc') !== false,
            default => (bool) preg_match(self::MODES[$context], $mode),
        };
    }

    /**
     * Resolve stream size from file.
     *
     * @param string|null $filename
     * 
     * @return int Return file size.
     */
    private function __size(?string $filename): int
    {
        if (!$filename) {
            return -1;
        }

        $size = @filesize($filename);
        return ($size !== false) ? (int) $size : -1;
    }
}