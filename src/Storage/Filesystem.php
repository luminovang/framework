<?php
/**
 * Luminova Framework Filesystem helper.
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
use \SplFileInfo;
use \SplFileObject;
use Luminova\Runtime;
use Luminova\Luminova;
use \FilesystemIterator;
use \App\Config\Security;
use \OutOfBoundsException;
use Luminova\Logger\Logger;
use \UnexpectedValueException;
use \RecursiveIteratorIterator;
use Luminova\Utility\Validator;
use \RecursiveDirectoryIterator;
use \Psr\Http\Message\StreamInterface;
use Luminova\Exceptions\InvalidArgumentException;
use Luminova\Exceptions\{FileException, RuntimeException, BadMethodCallException};

/**
 * Filesystem abstraction class.
 *
 * Provides read-only access to file properties and application paths.
 *
 * File properties:
 * @property-read string $filename   The file name.
 * @property-read string $basename   The file basename.
 * @property-read string $pathname   The full file path including the file name.
 * @property-read string $path       The directory path containing the file.
 * @property-read string $extension  The file extension.
 * @property-read int    $size       The file size in bytes.
 * @property-read string $realPath   The full resolved path.
 * @property-read string $fileInfo   The an SplFileInfo object for the file.
 *
 * Application paths:
 * @property-read string $system      Path to the system framework codes.
 * @property-read string $plugins     Path to the system third-party plugins.
 * @property-read string $library     Path to the libraries and third-party modules.
 * @property-read string $services    Path to serialized shared services.
 * @property-read string $controllers Path to application controllers.
 * @property-read string $modules     Path to HMVC modules.
 * @property-read string $app         Path to application files.
 * @property-read string $writeable   Path to writeable files.
 * @property-read string $logs        Path to error logs.
 * @property-read string $caches      Path to application caches.
 * @property-read string $public      Path to public document root.
 * @property-read string $assets      Path to public assets directory.
 * @property-read string $views       Path to view templates.
 * @property-read string $routes      Path to application routes.
 * @property-read string $languages   Path to language modules.
 */
class Filesystem implements Stringable
{
    /**
     * File binary mode constant.
     * 
     * @var int FILE_BINARY
     */
    public const FILE_BINARY = 2691;

    /**
     * File Info Object.
     * 
     * @var SplFileInfo|null $info
     */
    private ?SplFileInfo $info = null;

    /**
     * Application directory paths.
     * 
     * @var array<string,string> PATHS 
     */
    private const PATHS = [
        'app'         => 'app/',
        'routes'      => 'routes/',
        'system'      => 'system/',
        'public'      => 'public/',
        'writeable'   => 'writeable/',
        'modules'     => 'app/Modules/',
        'logs'        => 'writeable/log/',
        'assets'      => 'public/assets/',
        'languages'   => 'app/Languages/',
        'plugins'     => 'system/plugins/',
        'library'     => 'libraries/libs/',
        'views'       => 'resources/Views/',
        'controllers' => 'app/Controllers/',
        'caches'      => 'writeable/caches/',
        'services'    => 'writeable/services/',
    ];

    /**
     * SplFileInfo properties.
     * 
     * @var array<string,true> PROPERTIES
     */
    private const PROPERTIES = [
        'filename'  => true,
        'pathname'  => true,
        'path'      => true,
        'params'    => true,
        'inode'     => true,
        'group'     => true,
        'extension' => true,
        'realPath'  => true,
        'size'      => true,
        'basename'  => true,
        'owner'     => true,
        'mTime'     => true,
        'cTime'     => true,
        'type'      => true,
    ];

    /**
     * Initialize the filesystem object with an optional filename.
     *
     * If a filename is provided, initializes the associated file information
     * using {@see self::setFilename()}.
     *
     * @param string|null $filename Optional file or directory path.
     *
     * @throws FileException If the provided filename is empty or invalid.
     *
     * @see self::setFilename() To change file or directory path.
     */
    public function __construct(private ?string $filename = null)
    {
        if ($filename !== null) {
            $this->setFilename($filename);
        }
    }

    /**
     * Create a Filesystem instance for the specified file or directory.
     *
     * Initializes the instance with the given path and loads its file information.
     *
     * @param string $filename The file or directory path.
     *
     * @return static A new Filesystem instance.
     * @throws FileException If the filename is empty.
     * 
     * @see self::setFilename() To change file or directory path.
     */
    public static function info(string $filename): static
    {
        return new static(self::parseFilename($filename));
    }

    /**
     * Set or update the file for this Filesystem instance.
     *
     * Loads file information using `SplFileInfo` for later property access.
     *
     * @param string $filename The path to the file.
     * 
     * @return self Returns the instance of Filesystem.
     * @throws FileException If the filename is empty.
     */
    public function setFilename(string $filename): self
    {
        $this->filename = self::parseFilename(
            $filename
        );

        $this->info = new SplFileInfo(
            $this->filename
        );

        return $this;
    }

    /**
     * Check if the file exists.
     * 
     * This method determine whether the file path refers to 
     * an existing file, directory, or symbolic link.
     *
     * @return bool True if the path exists as a file, directory, or symbolic link, otherwise false.
     */
    public function exists(): bool
    {
        return $this->info instanceof SplFileInfo
            && (
                $this->info->isFile()
                || $this->info->isDir()
                || $this->info->isLink()
            );
    }

    /**
     * Change the permissions of a file or directory.
     *
     * @param string $location The file or directory path.
     * @param int $permission The permissions to apply, typically an octal mode such as `0755` or `0644`.
     *
     * @return bool True if the permissions were changed successfully, otherwise false.
     * @throws FileException If failed to change permissions.
     * 
     * @example - Examples:
     * ```php
     * Filesystem::setPermission("/path/to/somefile", 755);   // decimal; probably incorrect
     * Filesystem::setPermission("/path/to/somefile", 0755);  // octal; correct value of mode
     * ```
     */
    public static function setPermission(string $location, int $permission): bool
    {
        Runtime::clearLastError();

        if (@chmod($location, $permission)) {
            return true;
        }

        FileException::handlePermission(
            $location,
            Runtime::lastError()['message'] 
                ?? "Failed to change permissions to: {$permission}."
        );

        return false;
    }

    /**
     * Set the permissions of a file or directory.
     *
     * @param string $location The file or directory path.
     * @param int $permission The permissions to apply.
     *
     * @return bool True if the permissions were changed successfully, otherwise false.
     * @throws FileException If failed to change permissions.
     */
    public static function chmod(string $location, int $permission): bool
    {
        return self::setPermission($location, $permission);
    }

    /**
     * Converts Unix file permission code to its string representation.
     * 
     * @param int $permission The unix file permission (e.g, `0777`)
     * 
     * @return string Return permission string representation.
     */
    public static function permissions(int $permission): string
    {
        $symbolic = '';

        // Owner permissions
        $symbolic .= (($permission & 0x0100) !== 0) ? 'r' : '-';
        $symbolic .= (($permission & 0x0080) !== 0) ? 'w' : '-';
        $symbolic .= (($permission & 0x0040) !== 0) ?
            ((($permission & 0x0800) !== 0) ? 's' : 'x') :
            ((($permission & 0x0800) !== 0) ? 'S' : '-');

        // ©roup permissions
        $symbolic .= (($permission & 0x0020) !== 0) ? 'r' : '-';
        $symbolic .= (($permission & 0x0010) !== 0) ? 'w' : '-';
        $symbolic .= (($permission & 0x0008) !== 0) ?
            ((($permission & 0x0400) !== 0) ? 's' : 'x') :
            ((($permission & 0x0400) !== 0) ? 'S' : '-');

        // Other permissions
        $symbolic .= (($permission & 0x0004) !== 0) ? 'r' : '-';
        $symbolic .= (($permission & 0x0002) !== 0) ? 'w' : '-';
        $symbolic .= (($permission & 0x0001) !== 0) ?
            ((($permission & 0x0200) !== 0) ? 't' : 'x') :
            ((($permission & 0x0200) !== 0) ? 'T' : '-');

        return $symbolic;
    }

    /**
     * Convert permission string to Unix integer permission.
     * 
     * @param string $permission The permission string (e.g., `rw-r--r--`).
     * @param bool $decimal Whether to return permission as decimal or formatted octal string.
     * 
     * @return string|int|null Return the Unix integer permission, or null if the conversion failed.
     */
    public static function toUnixPermission(string $permission, bool $decimal = false): string|int|null
    {
        $permissions = [
            'r' => 4,
            'w' => 2,
            'x' => 1,
            '-' => 0,
        ];

        if (strlen($permission) !== 9) {
            return null;
        }

        $owner = 0;
        $group = 0;
        $others = 0;

        for ($i = 0; $i < 3; $i++) {
            $owner += $permissions[$permission[$i]];
        }
        for ($i = 3; $i < 6; $i++) {
            $group += $permissions[$permission[$i]];
        }
        for ($i = 6; $i < 9; $i++) {
            $others += $permissions[$permission[$i]];
        }

        $unixPermission = ($owner << 6) + ($group << 3) + $others;

        if($decimal){
            return $unixPermission;
        }

        return sprintf("%04o", $unixPermission);
    }

    /**
     * Read file content with optional length, offset, and stream context.
     *
     * Reads file content in chunks, allowing the caller to specify the maximum
     * number of bytes to read, the starting offset, and an optional delay
     * between chunks.
     *
     * @param string $filename The path to the file.
     * @param int $length The maximum number of bytes to read, or 0 to read until EOF.
     * @param int $offset The byte offset at which reading begins.
     * @param bool $useInclude Whether to search for the file in the include path.
     * @param resource|null $context An optional stream context created with `stream_context_create()`.
     * @param float|int $delay The delay between chunks in seconds or microseconds, depending on `uwait()`.
     *
     * @return string|false Returns the file content, or false on failure.
     * @throws FileException If an error occurs while opening or reading the file.
     */
    public static function contents(
        string $filename,
        int $length = 0,
        int $offset = 0,
        bool $useInclude = false,
        mixed $context = null,
        float|int $delay = 0
    ): string|bool 
    {
        if ($length < 0 || $filename === '' || !is_file($filename)) {
            return false;
        }

        try {
            $file = new SplFileObject($filename, 'rb', $useInclude, $context);

            if ($offset > 0 && $file->fseek($offset) !== 0) {
                return false;
            }

            $buffer = '';
            $remaining = ($length > 0) ? $length : PHP_INT_MAX;

            while (!$file->eof() && $remaining > 0) {
                $chunk = $file->fread(min(8192, $remaining));

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $buffer .= $chunk;
                $remaining -= strlen($chunk);

                if (
                    $remaining <= 0 
                    || connection_aborted() 
                    || connection_status() !== CONNECTION_NORMAL
                ) {
                    break;
                }

                if ($delay > 0) {
                    uwait($delay);
                }
            }

            return $buffer;
        } catch (Throwable $e) {
            FileException::handleReadFile(
                $filename, 
                $e->getMessage(), 
                $e
            );
            return false;
        }
    }

    /**
     * Stream file contents directly to output in chunks without loading the
     * entire file into memory.
     *
     * Supports reading from a file path or an existing stream, starting at a
     * specified byte offset, with optional delays between chunks and a read
     * timeout. The stream is closed after processing, including when supplied
     * as an existing resource.
     *
     * @param resource|string $source An open stream or readable file path.
     * @param int $offset The starting byte offset, defaults to `0`.
     * @param int $length Maximum number of bytes to read. A value of `0` reads
     *                    until EOF or the timeout expires.
     * @param float|int $delay Delay between chunks in seconds.
     * @param float|int $timeout Maximum read duration in seconds. Zero disables
     *                           the timeout.
     * @param bool $blocking Whether stream reads should use blocking mode.
     * @param int|null $filesize The expected file size in bytes, or null to detect it.
     *
     * @return int|false The number of bytes read, or false if reading cannot start.
     */
    public static function read(
        mixed $source,
        int $offset = 0,
        int $length = (1 << 21),
        float|int $delay = 0,
        float|int $timeout = 30,
        bool $blocking = true,
        ?int $filesize = null
    ): int|false 
    {
        if ($offset < 0 || $length < 0 || $timeout < 0 || $delay < 0) {
            return false;
        }

        $stream = $source;
        $closeStream = false;

        if (is_string($source)) {
            if ($source === '' || !is_file($source) || !is_readable($source)) {
                return false;
            }

            $stream = @fopen($source, 'rb');

            if ($stream === false) {
                return false;
            }

            $closeStream = true;
        }

        if (!is_resource($stream)) {
            return false;
        }

        try {
            $filesize ??= self::size($stream);

            if ($filesize === null || $filesize < 0 || $offset > $filesize) {
                return false;
            }

            if ($offset === $filesize) {
                return 0;
            }

            $length = min(
                max(0, $length > 0 ? $length : PHP_INT_MAX), 
                max(0, $filesize - $offset)
            );

            if ($length === 0) {
                return $offset;
            }

            $result = self::fileRead(
                $stream,
                $length,
                $delay,
                $offset,
                $blocking,
                $timeout
            );

            return ($result < 0) ? false : $result;
        } finally {
            if ($closeStream && is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * Writes or appends data to a file.
     *
     * Supports string content and readable stream resources, with optional append
     * mode, file locking, include path, binary mode, lock retries, and stream context.
     *
     * @param string $filename Path to the file to write.
     * @param SplFileObject|resource|string $content The string or readable stream resource to write.
     * @param int $flags Optional file flags, such as FILE_APPEND,
     * FILE_USE_INCLUDE_PATH, LOCK_EX, LOCK_NB, and FILE_BINARY.
     * @param int $retryLock Number of attempts to acquire a non-blocking lock.
     * @param float|int $delay Delay in seconds/microseconds between chunk.
     * @param resource|null $context Optional stream context created by
     * stream_context_create().
     * @param int &$bytes The number of bytes written, or 0 if writing fails.
     *
     * @return bool Returns true if at least one byte was written, otherwise false.
     *
     * @throws InvalidArgumentException If the content or stream context is invalid.
     * @throws FileException If the file cannot be opened, locked, or written.
     *
     * @see self::writeFromStream() To write directly from a stream resource.
     *
     * @example - Example:
     * ```php
     * // Write string.
     * Filesystem::write(
     *     '/path/to/file.txt',
     *     'Hello World',
     *     FILE_APPEND | LOCK_EX
     * );
     *
     * // Write from stream.
     * $stream = fopen('php://memory', 'rb+');
     * fwrite($stream, 'Hello Stream');
     * rewind($stream);
     *
     * Filesystem::write(
     *     '/path/to/file.txt',
     *     $stream,
     *     FILE_APPEND | LOCK_EX
     * );
     * ```
     */
    public static function write(
        string $filename,
        mixed $content,
        int $flags = 0,
        int $retryLock = 1,
        float|int $delay = 0,
        mixed $context = null,
        int &$bytes = 0
    ): bool 
    {
        $bytes = 0;

        if ($filename === '') {
            return false;
        }

        $isResource = is_resource($content);
        $isDataObject = $content instanceof SplFileObject;

        if ($isResource && get_resource_type($content) !== 'stream') {
            throw new InvalidArgumentException(sprintf(
                'Content must be a string or stream resource, %s given.',
                get_resource_type($content)
            ));
        }

        if (!$isResource && !$isDataObject && !is_string($content)) {
            throw new InvalidArgumentException(sprintf(
                'Content must be a string, stream resource or %s, %s given.',
                SplFileObject::class,
                get_debug_type($content)
            ));
        }

        if ($context !== null && !is_resource($context)) {
            throw new InvalidArgumentException(sprintf(
                'Context must be a stream resource or null, %s given.',
                get_debug_type($context)
            ));
        }

        try {
            $meta = null;

            if ($isResource) {
                $meta = stream_get_meta_data($content);

                if (($meta['seekable'] ?? false) && ftell($content) !== 0) {
                    rewind($content);
                }
            } elseif ($isDataObject && $content->ftell() !== 0) {
                $content->rewind();
            }

            $isBinary = ($flags & self::FILE_BINARY) !== 0
                || ($isResource && str_contains($meta['mode'] ?? '', 'b'))
                || (!$isDataObject && Validator::isBinary($content));

            $include = ($flags & FILE_USE_INCLUDE_PATH) !== 0;
            $append = ($flags & FILE_APPEND) !== 0;

            $mode = ($append ? 'a' : 'w') 
                . ($isBinary ? 'b' : '');

            $hasLock = ($flags & LOCK_EX) !== 0;
            $nonBlocking = ($flags & LOCK_NB) !== 0;

            $file = new SplFileObject(
                $filename,
                $mode,
                $include,
                $context
            );

            if (!$hasLock) {
                $bytes = self::sink(
                    $file,
                    $content,
                    delay: $delay,
                    filename: $filename
                );

                return $bytes > 0;
            }

            if ($nonBlocking) {
                self::waitForLock(
                    $file,
                    $retryLock
                );
            } else {
                self::lock($file);
            }

            try {
                $bytes = self::sink(
                    $file,
                    $content,
                    delay: $delay,
                    filename: $filename
                );

                return $bytes > 0;
            } finally {
                self::unlock($file);
            }
        } catch (Throwable $e) {
            FileException::handleFile(
                $filename,
                $e->getMessage(),
                $e
            );
        }

        return false;
    }

    /**
     * Writes all data from a source to a destination stream.
     *
     * Continues writing until the entire string or stream content has been
     * written, handling partial writes returned by fwrite().
     *
     * @param SplFileObject|resource $destination Writable file handler.
     * @param SplFileObject|resource|string $source Data source, either a
     *        SplFileObject, stream resource, or string.
     * @param int $chunkSize Maximum number of bytes to read per iteration.
     * @param float|int $delay Delay in seconds/microseconds between each chunk.
     * @param string|null $filename Optional destination filename used in
     *        exception messages.
     *
     * @return int Returns the total number of bytes written.
     *
     * @throws FileException If reading from the source or writing to the
     * target stream fails.
     */
    public static function sink(
        mixed $destination,
        mixed $source,
        int $chunkSize = 16_384,
        float|int $delay = 0,
        ?string $filename = null
    ): int 
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException(
                'Chunk size must be greater than zero.'
            );
        }

        $isFileObject = $destination instanceof SplFileObject;

        if (!$isFileObject && !is_resource($destination)) {
            throw new FileException(sprintf(
                'Invalid destination handler: %s.',
                get_debug_type($destination)
            ));
        }

        $total = 0;
        $isDataObject = $source instanceof SplFileObject;
        $isResource = is_resource($source);

        if ($isDataObject || $isResource) {
            while (true) {
                if (
                    ($isDataObject && $source->eof())
                    || ($isResource && feof($source))
                ) {
                    break;
                }

                $chunk = $isDataObject
                    ? $source->fread($chunkSize)
                    : fread($source, $chunkSize);

                if ($chunk === false) {
                    throw new FileException(sprintf(
                        'Error reading from stream: %s.',
                        $filename ?? get_debug_type($source)
                    ));
                }

                if ($chunk === '') {
                    break;
                }

                $total += self::writeChunk(
                    $chunk,
                    $destination,
                    $isFileObject,
                    $filename
                );

                if($delay > 0){
                    uwait($delay);
                }
            }

            return $total;
        }

        if (!is_string($source)) {
            throw new FileException(sprintf(
                'Invalid source type: %s.',
                get_debug_type($source)
            ));
        }

        $length = strlen($source);

        for ($offset = 0; $offset < $length; $offset += $chunkSize) {
            $total += self::writeChunk(
                substr($source, $offset, $chunkSize),
                $destination,
                $isFileObject,
                $filename
            );

            if($delay > 0){
                uwait($delay);
            }
        }

        return $total;
    }

    /**
     * Atomically writes data to a file.
     *
     * Writes string data or a readable stream to a temporary file in the target
     * directory, then atomically replaces the target file using rename().
     *
     * The temporary file is created in the same directory to ensure the rename
     * operation occurs on the same filesystem.
     *
     * @param string $filename Target file path.
     * @param SplFileObject|resource|string $content String data or a readable stream resource.
     * @param int|null $mode File permissions. Null preserves the target file permissions.
     * @param bool $acquireLock Whether to acquire an exclusive lock.
     *
     * @return int|false Returns the total number of bytes written on success, otherwise false.
     *
     * @throws InvalidArgumentException If the data or chunk size is invalid.
     * @throws FileException If the target directory, temporary file, lock, write,
     * rename, or permission operation fails.
     */
    public static function atomicWrite(
        string $filename,
        mixed $content,
        ?int $mode = null,
        bool $acquireLock = false
    ): int|false 
    {
        if ($filename === '') {
            throw new InvalidArgumentException(
                'Target filename cannot be empty.'
            );
        }

        $directory = dirname($filename);

        if (!is_dir($directory)) {
            throw new FileException(sprintf(
                'Directory does not exist: %s',
                $directory
            ));
        }

        if (!is_writable($directory)) {
            throw new FileException(sprintf(
                'Directory is not writable: %s',
                $directory
            ));
        }

        $lock = null;
        $lockFile = null;
        $temporary = null;
        $stream = null;

        try {
            if ($acquireLock) {
                $lockFile = $filename . '.lock';
                $lock = fopen($lockFile, 'c');

                if ($lock === false) {
                    throw new FileException(sprintf(
                        'Unable to open lock file: %s',
                        $lockFile
                    ));
                }

                if (self::tryLock($lock) === false) {
                    return false;
                }
            }

            $temporary = self::createTempName($directory, '.atomic_');

            if ($temporary === null) {
                throw new FileException(sprintf(
                    'Unable to create temporary file in: %s',
                    $directory
                ));
            }

            $stream = fopen($temporary, 'wb');

            if ($stream === false) {
                throw new FileException(sprintf(
                    'Unable to open temporary file: %s',
                    $temporary
                ));
            }

            $length = self::sink(
                $stream,
                $content,
                filename: $filename,
            );

            if (!fflush($stream)) {
                throw new FileException(
                    'Unable to flush temporary file.'
                );
            }

            if (function_exists('fsync') && !fsync($stream)) {
                throw new FileException(
                    'Unable to synchronize temporary file.'
                );
            }

            if (!fclose($stream)) {
                $stream = null;

                throw new FileException(
                    'Unable to close temporary file.'
                );
            }

            $stream = null;

            if ($mode === null && is_file($filename)) {
                $permissions = fileperms($filename);

                if ($permissions !== false) {
                    $mode = $permissions & 0777;
                }
            }

            if ($mode !== null && !chmod($temporary, $mode)) {
                throw new FileException(sprintf(
                    'Unable to set permissions on temporary file: %s',
                    $temporary
                ));
            }

            if (!rename($temporary, $filename)) {
                throw new FileException(sprintf(
                    'Unable to replace target file: %s',
                    $filename
                ));
            }

            $temporary = null;

            return $length;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($temporary !== null && is_file($temporary)) {
                @unlink($temporary);
            }

            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * Waits until an exclusive lock can be acquired.
     *
     * Attempts to acquire the lock without blocking and retries until the
     * maximum number of attempts is reached.
     *
     * @param SplFileObject|resource|string $file The file path or open file handler.
     * @param int $retries Maximum number of retry attempts.
     * @param float|int $delay Delay between attempts in seconds/microseconds.
     *
     * @return SplFileObject|resource Returns the locked file handler.
     *
     * @throws InvalidArgumentException If the file argument or retry options are invalid.
     * @throws FileException If the lock cannot be acquired.
     * 
     * @see self::lock()
     * @see self::tryLock()
     */
    public static function waitForLock(
        mixed $file,
        int $retries = 10,
        float|int $delay = 100_000
    ): mixed 
    {
        if ($retries < 1) {
            throw new InvalidArgumentException(
                'Lock retries must be greater than zero.'
            );
        }

        $attempt = 0;

        while (true) {
            try {
                return self::lock($file, false);
            } catch (FileException $e) {
                if (++$attempt >= $retries) {
                    throw $e;
                }

                if ($delay > 0) {
                    uwait($delay);
                }
            }
        }
    }

    /**
     * Acquires an exclusive lock on a file.
     *
     * @param SplFileObject|resource|string $file The file path or open file handler.
     * @param bool $blocking Whether to wait until the lock becomes available.
     *
     * @return SplFileObject|resource Returns the locked file handler.
     *
     * @throws InvalidArgumentException If the file argument is invalid.
     * @throws FileException If the file cannot be opened or locked.
     */
    public static function lock(
        mixed $file,
        bool $blocking = true
    ): mixed 
    {
        $isResource = is_resource($file);
        $isFileObject = $file instanceof SplFileObject;

        if (!$isResource && !$isFileObject && !is_string($file)) {
            throw new InvalidArgumentException(sprintf(
                'Lock expected a filename, stream resource or %s object, %s given.',
                SplFileObject::class,
                get_debug_type($file)
            ));
        }

        $handler = ($isResource || $isFileObject)
            ? $file
            : fopen($file, 'c');

        if ($handler === false) {
            throw new FileException(sprintf(
                'Unable to open file for locking: %s',
                ($isResource || $isFileObject)
                    ? get_debug_type($file)
                    : $file
            ));
        }

        $operation = LOCK_EX | ($blocking ? 0 : LOCK_NB);

        $locked = $isFileObject
            ? $handler->flock($operation)
            : flock($handler, $operation);

        if (!$locked) {
            if (is_string($file)) {
                fclose($handler);
            }

            throw new FileException(sprintf(
                'Unable to acquire exclusive lock: %s',
                ($isResource || $isFileObject)
                    ? get_debug_type($file)
                    : $file
            ));
        }

        return $handler;
    }

    /**
     * Releases an acquired lock on a file.
     *
     * @param SplFileObject|resource $handler An open file handler with an active lock.
     *
     * @return bool Returns true if the lock was released successfully, otherwise false.
     */
    public static function unlock(mixed $handler): bool
    {
        if (is_resource($handler)) {
            return flock($handler, LOCK_UN);
        }

        return ($handler instanceof SplFileObject)
            && $handler->flock(LOCK_UN);
    }

    /**
     * Attempts to acquire an exclusive lock on a file.
     *
     * Unlike {@see self::lock()}, this method returns false when the lock cannot
     * be acquired instead of throwing an exception.
     *
     * @param SplFileObject|resource|string $file The file path or open file handler.
     * @param bool $blocking Whether to wait until the lock becomes available.
     *
     * @return SplFileObject|resource|false Returns the locked file handler, or false if the lock cannot be acquired.
     */
    public static function tryLock(
        mixed $file,
        bool $blocking = true
    ): mixed 
    {
        try {
            return self::lock($file, $blocking);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Create a unique writable temporary directory.
     *
     * Uses the system temporary directory when available, then falls back to
     * application-local temporary storage. When `$localOnly` is `true`, only
     * application-local storage locations are considered.
     *
     * A prefix may be provided to make the directory name identifiable. When no
     * prefix is provided, a random name is generated. For system temporary
     * storage, the application name is used as the parent directory prefix.
     *
     * @param string|null $prefix    Optional directory name prefix.
     * @param bool        $localOnly Whether to use application-local storage only.
     *
     * @return string|null The created directory path with a trailing directory
     *                     separator, or `null` if creation fails.
     * 
     * @throws FileException In development if unable to create temp directory due to filesystem errors.
     *
     * @see self::createTempFile()
     */
    public static function createTempDir(
        ?string $prefix = null,
        bool $localOnly = false
    ): ?string 
    {
        static $base = null;

        $directory = null;
        $isSystem = false;

        $paths = [
            'sys'  => $localOnly ? null : sys_get_temp_dir(),
            'app'  => Luminova::root('/writeable/temp/'),
            'root' => Luminova::root('.tmp'),
        ];

        $hasPrefix = $prefix !== null && $prefix !== '';

        foreach ($paths as $source => $path) {
            if (
                $path === null
                || !is_readable($path)
                || !is_writable($path)
            ) {
                continue;
            }

            $directory = rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
            $isSystem = $source === 'sys';

            break;
        }

        if ($directory === null) {
            return null;
        }

        if ($isSystem) {
            $base ??= (defined('APP_NAME') 
                ? strtolower(substr(
                    trim(preg_replace('/[^a-zA-Z0-9_-]+/', '_', APP_NAME), '_-'),
                    0,
                    50
                )) : 'tmp');

            $directory .= '.' . $base . DIRECTORY_SEPARATOR;
        }

        $directory .= $hasPrefix 
            ? trim(substr(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $prefix), 0, 50), '_-')
            : bin2hex(random_bytes(6));

        $directory .= DIRECTORY_SEPARATOR;

        if (self::mkdir($directory, $isSystem ? 0700 : 0755, true)) {
            return $directory;
        }

        return null;
    }

    /**
     * Creates a writable temporary file.
     *
     * Creates a temporary file inside the application temporary directory or the
     * system temporary directory when available. The file name can be customized
     * using a prefix and extension.
     *
     * @param string|null $filename Optional filename prefix.
     * @param string|null $extension Optional file extension without a leading dot.
     * @param bool $localOnly Whether to use only the application writable temp
     *                        directory instead of the system temporary directory.
     *
     * @return string|null Returns the created temporary file path, or `null` if the
     *                     temporary directory or file cannot be created.
     * 
     * @throws FileException In development if unable to create temp file due to filesystem errors.
     * 
     * @see self::createTempDir()
     */
    public static function createTempFile(
        ?string $filename = null,
        ?string $extension = null,
        bool $localOnly = false
    ): ?string
    {
        $dir = self::createTempDir(localOnly: $localOnly);

        if ($dir === null) {
            return null;
        }

        $name = '';

        if ($filename !== null) {
            $extension ??= pathinfo($filename, PATHINFO_EXTENSION);

            $name = preg_replace('/\.[^.]+$/', '', $filename);
            $name = str_replace(['/', '\\'], '-', $name);
            $name = trim($name, '-') . '-';
        }

        $hasExtension = $extension !== null 
            && $extension !== '';

        if(!$hasExtension && ($file = tempnam($dir, $name)) !== false){
            return $file;
        }

        $length = ($filename === null) ? 8 : 4;
        $name .= bin2hex(random_bytes($length));

        if ($hasExtension) {
            $name .= '.' . ltrim($extension, '.');
        }

        $file = $dir . $name;

        return self::touch($file) ? $file : null;
    }

    /**
     * Create a file or update its access and modification timestamps.
     *
     * If both timestamps are null, an existing regular file has its timestamps
     * updated using `touch()`, while a non-existing file is created exclusively.
     * If either timestamp is provided, the native `touch()` behavior is used.
     *
     * @param string $file The file path.
     * @param int|null $mtime Optional modification timestamp.
     * @param int|null $atime Optional access timestamp.
     *
     * @return bool Returns true if the operation succeeds, otherwise false.
     */
    public static function touch(
        string $file,
        ?int $mtime = null,
        ?int $atime = null
    ): bool 
    {
        if ($mtime !== null || $atime !== null || is_file($file)) {
            return @touch($file, $mtime, $atime);
        }

        $handle = @fopen($file, 'x');

        if ($handle === false) {
            return false;
        }

        return fclose($handle);
    }

    /**
     * Create a unique temporary file name in the specified directory.
     *
     * Attempts to create a temporary file using PHP's native `tempnam()` function.
     * If the native temporary file creation fails, a unique file name is generated
     * manually and created using `touch()`.
     *
     * The generated file is created immediately to reserve the name and reduce the
     * possibility of another process using the same filename.
     *
     * @param string $directory Directory where the temporary file should be created.
     * @param string $prefix    Prefix to prepend to the generated filename.
     *
     * @return string|null The path to the created temporary file, or `null` if the
     *                     directory is not writable or the file cannot be created.
     */
    public static function createTempName(
        string $directory,
        string $prefix = 'tmp_'
    ): ?string
    {
        $directory = rtrim($directory, '/\\');

        if (
            $directory === '' ||
            !is_dir($directory) ||
            !is_writable($directory)
        ) {
            return null;
        }

        $prefix = trim($prefix);

        if ($prefix === '') {
            $prefix = 'tmp_';
        }

        $prefix = str_replace(['/', '\\'], '_', $prefix);
        $prefix = trim($prefix, '-') . '-';

        $file = tempnam($directory, $prefix);

        if ($file !== false) {
            if (dirname($file) === $directory) {
                return $file;
            }

            @unlink($file);
        }

        try {
            $length = (strlen($prefix) > 8) ? 4 : 8;

            $file = $directory
                . DIRECTORY_SEPARATOR
                . $prefix
                . bin2hex(random_bytes($length));

            return self::touch($file)
                ? $file
                : null;

        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Create a directory if it does not exist.
     *
     * Creates the specified directory using the requested permissions,
     * optionally creating any missing parent directories.
     *
     * @param string $path The directory path to create.
     * @param int $permissions The requested directory permissions (default: 0777),
     *                         subject to the system umask.
     * @param bool $recursive Whether to create missing parent directories (default: true).
     *
     * @return bool Returns true if the directory exists or was created successfully,
     *              or false if creation fails.
     * @throws FileException If the directory cannot be created.
     *
     * @example - Create a nested directory:
     * ```php
     * Filesystem::mkdir('writeable/storages/images', 0755);
     * ```
     */
    public static function mkdir(
        string $path,
        int $permissions = 0777,
        bool $recursive = true
    ): bool 
    {
        $path = rtrim($path, TRIM_DS) ?: DIRECTORY_SEPARATOR;

        if ($path === '') {
            return false;
        }

        if (is_dir($path)) {
            return true;
        }

        Runtime::clearLastError();

        if (@mkdir($path, $permissions, $recursive) || is_dir($path)) {
            return true;
        }

        $error = Runtime::lastError()['message']
            ?? 'Unable to create the directory.';

        $base = dirname($path);

        if (is_dir($base) && !is_writable($base)) {
            $error .= sprintf(
                " Parent directory '%s' is not writable.",
                $base
            );
        }

        FileException::handleDirectory($path, $error);

        return false;
    }

    /**
     * Recursively copy files and directories from a source to a destination.
     * 
     * Optionally skip files or directories that already exist in the destination.
     *
     * @param string $origin The source directory path.
     * @param string $dest The destination directory path.
     * @param bool $skipIfExists Wether to skip already existing files or directories (default: false).
     * @param int &$copied Reference to store the number of successfully copied items.
     *
     * @return bool Returns true if at least one file or directory was copied, false otherwise.
     * @throws RuntimeException If source directory does not exist or is not readable.
     * @throws FileException If unable to create destination directory due to filesystem errors.
     */
	public static function copy(
        string $origin, 
        string $dest, 
        bool $skipIfExists = false, 
        int &$copied = 0
    ): bool
	{
        return self::recursion(
            $origin, 
            $dest, 
            false, 
            $skipIfExists, 
            $copied
        );
	}

    /**
     * Recursively move files and directories from a source to a destination.
     * 
     * Skips files or directories that already exist in the destination.
     *
     * @param string $origin The source directory or file path.
     * @param string $dest The destination directory path.
     * @param bool $skipIfExists Wether to skip already existing files or directories (default: false).
     * @param int &$moved Reference to store the number of successfully moved items.
     *
     * @return bool Returns true if at least one file or directory was moved, false otherwise.
     * @throws RuntimeException If the source does not exist or is not readable.
     * @throws FileException If unable to create destination directory due to filesystem errors.
     */
    public static function move(
        string $origin, 
        string $dest, 
        bool $skipIfExists = false, 
        int &$moved = 0
    ): bool
    {
        return self::recursion(
            $origin, 
            $dest, 
            skippable: $skipIfExists, 
            counter: $moved
        );
    }

    /**
     * Delete a file or recursively delete a directory and its contents.
     *
     * Includes hidden files and directories. Symbolic links are deleted
     * without traversing their targets.
     *
     * @param string $location The file or directory path to delete.
     * @param bool $deleteBase Whether to delete the target directory itself.
     *
     * @return int Returns the number of successfully deleted files and directories.
     */
    public static function delete(
        string $location,
        bool $deleteBase = false
    ): int 
    {
        if (is_link($location) || is_file($location)) {
            return @unlink($location) ? 1 : 0;
        }

        if (!is_dir($location)) {
            return 0;
        }

        $location = rtrim($location, TRIM_DS);
        $deleted = 0;

        try {
            foreach (new FilesystemIterator($location, FilesystemIterator::SKIP_DOTS) as $item) {
                $path = $item->getPathname();

                if ($item->isLink() || $item->isFile()) {
                    $deleted += @unlink($path) ? 1 : 0;
                    continue;
                }

                if ($item->isDir()) {
                    $deleted += self::delete($path, true);
                }
            }
        } catch (UnexpectedValueException) {
            return $deleted;
        }

        if ($deleteBase && @rmdir($location)) {
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Create a symbolic link to a file or directory.
     *
     * Supports Unix-like systems and Windows. Creates the destination directory
     * if necessary and replaces an existing symbolic link, but never overwrites
     * a regular file or directory.
     *
     * Alias {@see self::symlink()}.
     *
     * @param string $target The path to the target file or directory.
     * @param string $link The path where the symbolic link will be created.
     * @param int $permissions The permissions for a newly created destination
     *                         directory (default: 0755).
     *
     * @return bool Returns true if the symbolic link was created successfully,
     *              or false if creation failed.
     *
     * @throws FileException If the target does not exist or the destination
     *                       directory cannot be created.
     */
    public static function symbolic(
        string $target,
        string $link,
        int $permissions = 0755
    ): bool 
    {
        if (!file_exists($target) && !is_link($target)) {
            throw new FileException("The symlink target does not exist: {$target}");
        }

        $linkDir = dirname($link);

        if (!is_dir($linkDir) && !self::mkdir($linkDir, $permissions)) {
            throw new FileException(
                "Unable to create symlink destination directory: {$linkDir}"
            );
        }

        if (is_link($link)) {
            if (!@unlink($link)) {
                return self::symError(
                    "Unable to remove the existing symbolic link: {$link}"
                );
            }
        } elseif (file_exists($link)) {
            return self::symError(
                "The symlink destination already exists and is not a symbolic link: {$link}"
            );
        }

        Runtime::clearLastError();

        if (symlink($target, $link) || self::mkLink($target, $link)) {
            return true;
        }

        $message = Runtime::lastError()['message']
            ?? 'Unknown error occurred while creating the symbolic link.';

        return self::symError("Symlink creation failed: {$message}");
    }

    /**
     * Create a symbolic link (symlink) to a target file or directory.
     * 
     * Alias Of {@see self::symbolic()}
     *
     * @param string $target The target file or directory to link from.
     * @param string $link The path where the symbolic link will be created.
     * @param int $permissions The permissions to set for the destination directory 
     *                          if it needs to be created (default: 0755).
     * 
     * @return bool Returns true if the symbolic link was successfully created, false otherwise.
     * @throws FileException If the target does not exist or destination directory cannot be created.
     */
    public static function symlink(string $target, string $link, int $permissions = 0755): bool
    {
       return self::symbolic($target, $link, $permissions);
    }

    /**
     * Get the last-modified Unix timestamp of a file source.
     *
     * Supports filesystem paths, PHP stream resources, SplFileObject instances,
     * and Stream or StreamInterface implementations. For stream objects, the
     * timestamp is obtained from stream metadata or the underlying file when
     * its URI identifies an accessible local file.
     *
     * Returns 0 when the modification time cannot be determined.
     *
     * @param StreamInterface|SplFileObject|Stream|resource|string $source The file source.
     *
     * @return int|null Returns the last-modified Unix timestamp, or null if unavailable.
     */
    public static function lastModified(mixed $source): ?int
    {
        try {
            if ($source instanceof Stream) {
                $mtime = $source->stat('mtime');

                return ($mtime === null || $mtime === false) 
                    ? null 
                    : (int) $mtime;
            }

            $uri = ($source instanceof StreamInterface) 
                ? $source->getMetadata('uri')
                : $source;

            if (is_string($uri)) {
                if ($uri === '' || !is_file($uri)) {
                    return null;
                }

                clearstatcache(true, $uri);

                $mtime = @filemtime($uri);

                return ($mtime === false) ? null : (int) $mtime;
            }

            $stat = ($source instanceof SplFileObject)
                ? $source->fstat()
                : (is_resource($source) ? @fstat($source) : null);

            if($stat === false || $stat === null || ($stat['mtime'] ?? null) === null){
                return null;
            }

            return (int) ($stat['mtime'] ?? 0);
        } catch (Throwable) {}

        return null;
    }

    /**
     * Determine the size in bytes of a file, directory, stream, or stream object.
     *
     * Returns the file size, the cumulative size of regular files within a directory,
     * or the size reported by the supplied stream. For seekable streams whose size
     * cannot be determined from metadata, attempts to calculate it by seeking to EOF
     * and restores the original stream position.
     *
     * @param StreamInterface|Stream|resource|string $source A filesystem path, PHP stream resource, or stream instance.
     *
     * @return int|null Returns the size in bytes, or null if the stream size cannot be determined.
     * @throws FileException If the source type is unsupported or the filesystem path does not exist.
     */
    public static function size(mixed $source): ?int
    {
        if ($source instanceof StreamInterface) {
            return $source->getSize() ?? 0;
        }

        if ($source instanceof Stream) {
            return $source->size();
        }

        if (is_resource($source)) {
            $stat = @fstat($source);

            if ($stat !== false && isset($stat['size'])) {
                return (int) $stat['size'];
            }

            $meta = stream_get_meta_data($source);

            if (!($meta['seekable'] ?? false)) {
                return null;
            }

            $position = @ftell($source);

            if ($position === false || @fseek($source, 0, SEEK_END) !== 0) {
                return null;
            }

            $size = @ftell($source);

            if (@fseek($source, $position, SEEK_SET) !== 0) {
                return null;
            }

            return ($size === false) ? null : $size;
        }

        if (!is_string($source)) {
            throw new FileException(sprintf(
                'Invalid source type: %s. Expected a filesystem path, stream resource, or stream object.',
                get_debug_type($source)
            ));
        }

        clearstatcache(true, $source);

        if (is_file($source)) {
            $size = @filesize($source);

            return ($size === false) ? null : $size;
        }

        if (is_dir($source)) {
            $size = 0;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $source,
                    FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $fileSize = $file->getSize();

                    if ($fileSize >= 0) {
                        $size += $fileSize;
                    }
                }
            }

            return $size;
        }

        throw new FileException("File does not exist: {$source}");
    }

    /**
     * Determine whether a string likely represents a filesystem path.
     *
     * This method checks for common path patterns and excludes URLs.
     *
     * @param string $value The string to evaluate.
     * @param bool $forFile If true, enforces that the path looks like a file (with extension).
     *
     * @return bool Returns true if the string appears to be a filesystem path.
     */
    public static function isLikelyFile(string $value, bool $forFile = false): bool
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            return false;
        }

        // Must look like a filesystem path
        if (!preg_match('#^(?:\.{1,2}[\\/]|[\\/]|[a-zA-Z]:[\\/])#', $value)) {
            return false;
        }

        if ($forFile) {
            $basename = basename($value);

            if(!$basename || preg_match('#[\\/]\s*$#', $value)){
                return false;
            }

            if (!preg_match('/^[^<>:"|?*\r\n]+\.[a-z0-9]{1,10}$/i', $basename)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether a value is a PHP resource, optionally enforcing its exact type.
     *
     * @param mixed $resource The value to check if resource.
     * @param string|null $type Expected resource type (e.g. 'stream', 'stream-context').
     *
     * @return bool Returns true if the value is a resource and matches the given type (if provided).
     */
    public static function isResource(mixed $resource, ?string $type = null): bool
    {
        if (!is_resource($resource)) {
            return false;
        }

        return $type === null 
            || get_resource_type($resource) === $type;
    }

    /**
     * Check whether a local file path is permitted, exists, and can be read.
     *
     * This rejects URLs and stream wrappers and performs a readability check
     * for normal filesystem paths. UNC paths are treated as existence-only.
     *
     * @param string $path File path (absolute or relative).
     *
     * @return bool Returns true if the file can be safely accessed for reading.
     */
    public static function isAccessible(string $path): bool
    {
        if (!self::isPathPermitted($path) || !is_file($path)) {
            return false;
        }

        return str_starts_with($path, '\\\\') || is_readable($path);
    }

    /**
     * Check if a local filesystem path is writable.
     *
     * Supports both files and directories:
     * - Files must be writable.
     * - Directories must allow file creation.
     *
     * Rejects URLs and stream wrappers such as http://, ftp://, phar://.
     *
     * @param string $path Absolute or relative filesystem path.
     *
     * @return bool Returns true if the path exists, is permitted, and writable.
     */
    public static function isWritable(string $path): bool
    {
        return self::isPathPermitted($path)
            && file_exists($path)
            && is_writable($path);
    }

    /**
     * Check if a local filesystem path is readable.
     *
     * Supports both files and directories. Rejects URLs and stream wrappers
     * such as http://, ftp://, phar:// to prevent unsafe access.
     *
     * @param string $path Absolute or relative filesystem path.
     *
     * @return bool Returns true if the path exists, is permitted, and readable.
     */
    public static function isReadable(string $path): bool
    {
        if (!self::isPathPermitted($path) || !file_exists($path)) {
            return false;
        }

        return is_readable($path);
    }

    /**
     * Verify if the file path follows the allowed format and is not a URL or PHP Archive (Phar).
     * 
     * This method will reject paths that use stream wrappers (e.g. http://, phar://, ftp://).
     *
     * @param string $path Path to evaluate.
     *
     * @return bool Returns true if the path refers to a local filesystem location.
     */
    public static function isPathPermitted(string $path): bool
    {
        return !preg_match('#^[a-z][a-z\d+.-]*://#i', $path);
    }

    /**
     * Determines whether a string looks like a directory path.
     *
     * This does not touch the filesystem. The check is based purely on
     * trailing separators and the absence of a file extension.
     *
     * @param string $path Path string to inspect.
     *
     * @return bool Return true if the value appears to represent a directory path.
     */
    public static function isPath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        return str_ends_with($path, '/')
            || str_ends_with($path, '\\')
            || pathinfo($path, PATHINFO_EXTENSION) === '';
    }

    /**
     * Determine whether a given path is an absolute path.
     *
     * Supports Unix-style paths, Windows drive paths, and UNC network paths.
     *
     * @param string $path The path string to evaluate.
     * 
     * @return bool Returns true if the path is absolute, false if it is relative.
     */
    public static function isAbsolutePath(string $path): bool
    {
        return (
            str_starts_with($path, DIRECTORY_SEPARATOR) ||
            preg_match('#^[A-Z]:\\\\#i', $path) ||
            str_starts_with($path, '\\\\')
        );
    }

    /**
     * Resolve and normalize application paths.
     *
     * Returns an absolute path resolved from the project root
     * using a predefined path key.
     *
     * Available keys:
     *  - system: Framework core files.
     *  - plugins: Third-party plugins.
     *  - library: Shared libraries and external modules.
     *  - services: Serialized shared service definitions.
     *  - controllers: Application controllers.
     *  - modules: HMVC application modules.
     *  - app: Application source files.
     *  - writeable: Writable runtime files.
     *  - logs: Application error logs.
     *  - caches: Cached application data.
     *  - public: Public document root.
     *  - assets: Public static assets.
     *  - views: View templates.
     *  - routes: Application route definitions.
     *  - languages: Language and localization files.
     *
     * @param string $name Path key name (e.g. "app", "public").
     *
     * @return string Returns an absolute normalized path, or an empty string if the key is undefined.
     */
    public static function path(string $name): string 
    {
        $path = self::PATHS[$name] ?? null;
        
        return ($path !== null) 
            ? Luminova::root($path) 
            : '';
    }

    /**
     * Normalize directory separators to the current operating system.
     *
     * This only replaces '/' and '\' with OS DIRECTORY_SEPARATOR.
     * It does not resolve paths or validate filesystem access.
     *
     * @param string $path File path to normalize.
     *
     * @return string Returns path with normalized directory separators.
     * @see self::resolvePath()
     */
    public static function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Resolve and validate a filesystem path with security controls.
     *
     * Features:
     * - Normalizes separators
     * - Resolves "." and ".."
     * - Enforces sandbox (base directory)
     * - Optionally blocks symlinks
     * - Supports Windows long paths (\\?\)
     * - Rejects URLs, streams, phar
     *
     * @param string $path Input path (relative or absolute)
     * @param string|null $baseDir Base directory sandbox (null = no sandbox)
     * @param bool $mustExist Wether to require path to exist (default: `false`).
     * @param bool $mustReadable Wether to require file readability (default: `false`).
     * @param bool $blockSymlinks Wether to reject symlink traversal (default: `true`).
     *
     * @return string Returns the resolved absolute path
     * @throws FileException If the path is invalid, failed or unsafe.
     */
    public static function resolvePath(
        string $path,
        ?string $baseDir = null,
        bool $mustExist = false,
        bool $mustReadable = false,
        bool $blockSymlinks = true
    ): string 
    {
        if ($path === '') {
            throw new FileException('Path cannot be empty.');
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            throw new FileException("Unsupported path scheme: {$path}");
        }

        $path = self::normalizePath($path);

        if (!self::isAbsolutePath($path)) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
        }

        $resolved = self::canonicalizePath($path);

        if ($baseDir !== null) {
            $baseDir = self::canonicalizePath($baseDir);

            if (
                !str_starts_with($resolved, rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) && 
                $resolved !== $baseDir
            ) {
                throw new FileException("Path escapes sandbox: {$resolved}");
            }
        }

        if ($mustExist && !file_exists($resolved)) {
            throw new FileException("Path does not exist: {$resolved}");
        }

        if ($blockSymlinks && file_exists($resolved)) {
            $real = realpath($resolved);

            if ($real !== false && $real !== $resolved) {
                throw new FileException("Symlink traversal detected: {$resolved}");
            }
        }

        if ($mustReadable && is_file($resolved) && !is_readable($resolved)) {
            throw new FileException("Path is not readable: {$resolved}");
        }

        return self::resolveWindowsLongPath($resolved);
    }

    /**
     * Proxy read-only access to SplFileInfo methods.
     *
     * @param string $property
     * @return mixed Return property value.
     * 
     * @example - Example:
     * ```php
     *  $file->name        -> getFilename()
     *  $file->path        -> getPathname()
     *  $file->extension  -> getExtension()
     *  $file->size        -> getSize()
     * ```
     */
    public function __get(string $property): mixed
    {
        $root = self::path($property);

        if($root){
            return $root;
        }

        if(!$this->info instanceof SplFileInfo){
            return null;
        }

        $method = 'get' . ucfirst($property);

        if(method_exists($this->info, $method)){
            return $this->info->{$method}();
        }

        throw new OutOfBoundsException(
            "Undefined property: {$property}"
        );
    }

    /**
     * Forward unknown method calls to the underlying SplFileInfo instance.
     *
     * @throws BadMethodCallException If the file information object is not initialized
     *                                or the requested method does not exist.
     */
    public function __call(string $method, array $args): mixed
    {
        if (!$this->info instanceof SplFileInfo) {
            throw new BadMethodCallException(
                "Cannot call {$method}(): file information is not initialized. "
                . 'Call setFilename() first.'
            );
        }

        if (!method_exists($this->info, $method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s() does not exist.', 
                SplFileInfo::class, 
                $method
            ));
        }

        return $this->info->{$method}(...$args);
    }

    /**
     * Check if filesystem property exists.
     * 
     * @param string $name property name.
     * 
     * @return bool Return true if property name exists.
     */
    public function __isset(string $name): bool
    {
        if(isset(self::PATHS[$name])){
            return true;
        }

        return ($this->info instanceof SplFileInfo) 
            && isset(self::PROPERTIES[$name]);
    }

    /**
     * Stringify path info.
     * 
     * @return string Return stringify path info.
     */
    public function __toString(): string 
    {
        return ($this->info instanceof SplFileInfo) 
            ? (string) $this->info
            : '';
    }

    /**
     * Validate and normalize a filename by removing surrounding whitespace.
     *
     * @param string $filename The filename to validate.
     *
     * @return string The trimmed filename.
     *
     * @throws FileException If the filename is empty.
     */
    private static function parseFilename(string $filename): string
    {
        $filename = trim($filename);

        if ($filename === '') {
            throw new FileException(
                'Filename must not be empty.'
            );
        }

        return $filename;
    }

    /**
     * Create a symbolic link on Windows using the `mklink` command.
     *
     * This method is a fallback for creating symbolic links on Windows.
     *
     * @param string $target The target file or directory to link from.
     * @param string $link The path where the symbolic link will be created.
     *
     * @return bool Returns true if the symbolic link was successfully created, false otherwise.
     */
    private static function mkLink(string $target, string $link): bool
    {
        if (!Runtime::isPlatform('windows')) {
            return false;
        }

        $output = null;
        $result = 1;
        $mode = is_dir($target) ? 'D' : 'H';

        exec(
            sprintf(
                'mklink /%s %s %s',
                $mode,
                escapeshellarg($link),
                escapeshellarg($target)
            ),
            $output,
            $result
        );

        return $result === 0;
    }

    /**
     * Writes chunk data to a stream.
     *
     * @param string $chunk
     * @param SplFileObject|resource $handler Writable file handler.
     * @param bool $isFileObject
     * @param string $filename Target filename used in exception messages.
     *
     * @return int Returns the total number of bytes written.
     *
     * @throws FileException If error while writing.
     */
    private static function writeChunk(
        string $chunk, 
        mixed $handler,
        bool $isFileObject,
        ?string $filename = null
    ): int 
    {
        $length = strlen($chunk);
        $offset = 0;
        $total = 0;

        while ($offset < $length) {
            $written = $isFileObject
                ? $handler->fwrite(substr($chunk, $offset, $length - $offset))
                : fwrite($handler, substr($chunk, $offset, $length - $offset));

            if ($written === false || $written === 0) {
                throw new FileException(sprintf(
                    "Error writing to: %s.",
                    $filename ?? get_debug_type($handler)
                ));
            }

            $offset += $written;
            $total += $written;
        }

        return $total;
    }

    /**
     * @param string $path
     * 
     * @return string 
     */
    private static function pathRoot(string $path): string
    {
        if (preg_match('#^[A-Z]:\\\\#i', $path, $m)) {
            return $m[0];
        }

        if (str_starts_with($path, '\\\\')) {
            return '\\\\';
        }

        return DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $path
     * 
     * @return string 
     */
    private static function canonicalizePath(string $path): string
    {
        $root = self::pathRoot($path);
        $segments = [];

        foreach (explode(DIRECTORY_SEPARATOR, substr($path, strlen($root))) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);
                continue;
            }

            $segments[] = $segment;
        }

        return $root . implode(DIRECTORY_SEPARATOR, $segments);
    }

    /**
     * @param string $path
     * 
     * @return string 
     */
    private static function resolveWindowsLongPath(string $path): string
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return $path;
        }

        if (str_starts_with($path, '\\\\?\\')) {
            return $path;
        }

        if (preg_match('#^[A-Z]:\\\\#i', $path)) {
            return '\\\\?\\' . $path;
        }

        if (str_starts_with($path, '\\\\')) {
            return '\\\\?\\UNC\\' . ltrim($path, '\\');
        }

        return $path;
    }

    /**
     * @param string $message
     * 
     * @return bool 
     */
    private static function symError(string $message): bool
    {
        if(PRODUCTION){
            Logger::tryLog('alert', $message);
            return false;
        }

        throw new FileException($message);
    }

    /**
     * Read and output a specified number of bytes from a stream.
     *
     * Reads up to the requested length, optionally waiting for data in
     * non-blocking mode. Stops when the requested length is reached, EOF occurs,
     * the connection is aborted, or the timeout expires.
     *
     * @param resource $handler The stream resource to read from.
     * @param int $length The maximum number of bytes to read.
     * @param float|int $delay The delay between reads, in seconds.
     * @param int $offset The starting byte offset.
     * @param bool $blocking Whether to use blocking stream reads.
     * @param float|int $timeout The maximum read duration in seconds. Zero disables the timeout.
     *
     * @return int The ending byte offset, or -1 on failure.
     */
    private static function fileRead(
        mixed $handler,
        int $length = 8192,
        float|int $delay = 0,
        int $offset = 0,
        bool $blocking = true,
        float|int $timeout = 30
    ): int 
    {
        $meta = stream_get_meta_data($handler);

        if ($offset > 0 || ($meta['seekable'] ?? false)) {
            if (!($meta['seekable'] ?? false) || fseek($handler, $offset) !== 0) {
                return -1;
            }
        }

        stream_set_blocking($handler, $blocking);

        $read = 0;
        $startedAt = microtime(true);

        while ($read < $length && !feof($handler)) {
            if ($timeout > 0 && microtime(true) - $startedAt >= $timeout) {
                break;
            }

            $chunk = fread($handler, min(8192, $length - $read));

            if ($chunk === false) {
                return -1;
            }

            if ($chunk === '') {
                if ($blocking) {
                    break;
                }

                if ($timeout > 0 && microtime(true) - $startedAt >= $timeout) {
                    break;
                }

                if ($delay > 0) {
                    $remaining = ($timeout > 0)
                        ? max(0, $timeout - (microtime(true) - $startedAt))
                        : $delay;

                    if ($remaining <= 0) {
                        break;
                    }

                    uwait(min($delay, $remaining));
                }

                continue;
            }

            $bytes = strlen($chunk);
            echo $chunk;

            $read += $bytes;

            flush();

            if (connection_aborted() || connection_status() !== CONNECTION_NORMAL) {
                break;
            }

            if ($read >= $length) {
                break;
            }

            if ($delay > 0) {
                $remaining = ($timeout > 0)
                    ? max(0, $timeout - (microtime(true) - $startedAt))
                    : $delay;

                if ($remaining <= 0) {
                    break;
                }

                uwait(min($delay, $remaining));
            }
        }

        return $read;
    }

    /**
     * Recursively copies or moves a file or directory to a destination.
     *
     * Supports individual files, nested directories, and symbolic links.
     * Existing destination entries can optionally be skipped.
     *
     * @param string $origin The source file or directory path.
     * @param string $dest The destination file or directory path.
     * @param bool $isMove Whether to move the source instead of copying it.
     * @param bool $skippable Whether to skip entries that already exist at the destination.
     * @param int &$counter Reference to the number of successfully copied or moved entries.
     *
     * @return bool Returns true if at least one entry was successfully copied or moved.
     *
     * @throws RuntimeException If the source does not exist, is unreadable, or the destination is invalid.
     * @throws FileException If a required destination directory cannot be created.
     */
    private static function recursion(
        string $origin,
        string $dest,
        bool $isMove = true,
        bool $skippable = false,
        int &$counter = 0
    ): bool 
    {
        $origin = rtrim($origin, TRIM_DS);
        $dest = rtrim($dest, TRIM_DS);

        if (!file_exists($origin) && !is_link($origin)) {
            throw new RuntimeException("Source '{$origin}' does not exist.");
        }

        if ($origin === $dest) {
            throw new RuntimeException('Source and destination must be different.');
        }

        if ($skippable && (file_exists($dest) || is_link($dest))) {
            return false;
        }

        if (is_link($origin)) {
            $target = readlink($origin);

            if ($target === false) {
                return false;
            }

            if (!self::mkdir(dirname($dest), Security::$dirPermissions)) {
                return false;
            }

            $success = $isMove
                ? rename($origin, $dest)
                : self::symbolic($target, $dest);

            if ($success) {
                $counter++;
            }

            return $success;
        }

        if (is_file($origin)) {
            if (!is_readable($origin)) {
                throw new RuntimeException("Source file '{$origin}' is not readable.");
            }

            if (!self::mkdir(dirname($dest), Security::$dirPermissions)) {
                return false;
            }

            $success = $isMove
                ? rename($origin, $dest)
                : copy($origin, $dest);

            if ($success) {
                $counter++;
            }

            return $success;
        }

        if (!is_dir($origin) || !is_readable($origin)) {
            throw new RuntimeException("Source directory '{$origin}' is not readable.");
        }

        $source = realpath($origin);
        $target = realpath($dest);

        if (
            $source !== false &&
            $target !== false &&
            str_starts_with(
                $target . DIRECTORY_SEPARATOR,
                rtrim($source, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            )
        ) {
            throw new RuntimeException('Destination cannot be inside the source directory.');
        }

        if (!self::mkdir($dest, Security::$dirPermissions)) {
            return false;
        }

        $dir = opendir($origin);

        if ($dir === false) {
            return false;
        }

        $success = false;

        try {
            while (($file = readdir($dir)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $srcFile = $origin . DIRECTORY_SEPARATOR . $file;
                $destFile = $dest . DIRECTORY_SEPARATOR . $file;

                if ($skippable && (file_exists($destFile) || is_link($destFile))) {
                    continue;
                }

                if (self::recursion(
                    $srcFile,
                    $destFile,
                    $isMove,
                    $skippable,
                    $counter
                )) {
                    $success = true;
                }
            }
        } finally {
            closedir($dir);
        }

        if ($isMove && $success) {
            @rmdir($origin);
        }

        return $success;
    }
}