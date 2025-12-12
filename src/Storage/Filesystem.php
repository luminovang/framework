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
use \App\Config\Files;
use \Luminova\Luminova;
use \FilesystemIterator;
use \OutOfBoundsException;
use \Luminova\Utility\MIME;
use \Luminova\Logger\Logger;
use \Luminova\Utility\Helpers;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \Psr\Http\Message\StreamInterface;
use function \Luminova\Funcs\{root, is_platform};
use \Luminova\Exceptions\{FileException, RuntimeException, BadMethodCallException};

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
     * File Info Object.
     * 
     * @var SplFileInfo|null $info
     */
    private ?SplFileInfo $info = null;

    /**
     * Application directory paths.
     * 
     * @var array<string,string> $paths 
     */
    private static array $paths = [
        'system'      => 'system/',
        'plugins'     => 'system/plugins/',
        'library'     => 'libraries/libs/',
        'services'    => 'writeable/services/',
        'controllers' => 'app/Controllers/',
        'modules'     => 'app/Modules/',
        'app'         => 'app/',
        'writeable'   => 'writeable/',
        'logs'        => 'writeable/log/',
        'caches'      => 'writeable/caches/',
        'public'      => 'public/',
        'assets'      => 'public/assets/',
        'views'       => 'resources/Views/',
        'routes'      => 'routes/',
        'languages'   => 'app/Languages/',
    ];

    /**
     * Constructor for the Filesystem class.
     *
     * Initializes the file object if a filename is provided.
     *
     * @param string|null $filename Optional. The path to the file to initialize. 
     *                             If provided, the file info will be loaded.
     */
    public function __construct(private ?string $filename = null)
    {
        if ($this->filename) {
            $this->setFilename($this->filename);
        }
    }

    /**
     * Create a new Filesystem instance for the specified file.
     *
     * This static method provides a convenient way to initialize
     * a Filesystem object with file information loaded via `SplFileInfo`.
     *
     * @param string $filename The path to the file.
     * 
     * @return self Returns a new instance of the Filesystem class.
     */
    public static function info(string $filename): self
    {
        return new static($filename);
    }

    /**
     * Set or update the file for this Filesystem instance.
     *
     * Loads file information using `SplFileInfo` for later property access.
     *
     * @param string $filename The path to the file.
     * 
     * @return self Returns the instance of Filesystem.
     */
    public function setFilename(string $filename): self
    {
        $this->info = new SplFileInfo($filename);
        return $this;
    }

    /**
     * Check if the file exists (either file or directory).
     * 
     * @return bool Returns true if file or directory exists.
     */
    public function exists(): bool
    {
        return ($this->info instanceof SplFileInfo) && 
            ($this->info->isFile() || $this->info->isDir());
    }

    /**
     * Set permissions for a file or directory.
     *
     * @param string $location The path to the file or directory.
     * @param int $permission The permission to set (Unix file permissions e.g, `0777`).
     * 
     * @return bool True on success, false on failure.
     */
    public static function setPermission(string $location, int $permission): bool
    {
        error_clear_last();
        if (!@chmod($location, $permission)) {
            $error = error_get_last();
            FileException::handlePermission(
                $location, 
                ($error === null) 
                    ? 'Failed to set permission' 
                    : $error['message']
            );

            return false;
        }

        return true;
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

        // Group permissions
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
     * Read content of file, similar to PHP `file_get_contents`.
     * 
     * This method reads the content of a file, allowing you to specify options 
     * like length of data to read and the starting offset.
     * 
     * @param string $filename The path to the file to be read.
     * @param int $length The maximum number data to read in bytes (default: 0).
     * @param int $offset The starting position in the file to begin reading from (default: 0).
     * @param bool $useInclude If `true`, the file will be searched in the include path (default: false). 
     * @param resource|null $context A context resource created with `stream_context_create()` (default: null).
     * @param int $delay The delay between each chunk in microseconds (default:, 0 second).
     * 
     * @return string|false Returns the contents of the file as a string, or `false` on failure.
     * @throws FileException If an error occurs while opening or reading the file.
     */
    public static function contents(
        string $filename,
        int $length = 0,
        int $offset = 0,
        bool $useInclude = false,
        mixed $context = null,
        int $delay = 0
    ): string|bool
    {
        if ($filename === '' || !is_file($filename)) {
            return false;
        }

        try {
            $file = new SplFileObject($filename, 'r', $useInclude, $context);

            if ($offset > 0 && $file->fseek($offset) !== 0) {
                return false;
            }

            $buffer = '';
            $remaining = ($length > 0) ? $length : PHP_INT_MAX;

            while (!$file->eof() && $remaining > 0) {
                $chunkSize = min(8192, $remaining);
                $chunk = $file->fread($chunkSize);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $buffer .= $chunk;
                $remaining -= strlen($chunk);

                if ($delay > 0) {
                    usleep($delay);
                }
            }

            return $buffer;
        } catch (Throwable $e) {
            FileException::handleReadFile($filename, $e->getMessage(), $e);
            return false;
        }
    }

    /**
     * Streams a file resource to output in chunks.
     *
     * Automatically handles text or binary files, with optional non-blocking reads
     * and resumable positions. Large files are streamed in chunks to reduce memory usage.
     * The file stream is always closed after reading.
     *
     * @param resource|string $source   File stream or file path.
     * @param int|null $filesize    Optional known file size in bytes (auto-detected if null).
     * @param MIME|string|null $mime  Optional MIME type for content detection.
     * @param int $length           Maximum chunk size in bytes (default: 2MB).
     * @param int $delay            Delay in microseconds between chunk reads (default: 0).
     * @param int &$position        Starting position in the file; updated to final read position.
     * @param bool $nonBlocking     If true, read in non-blocking mode.
     *
     * @return bool Returns true if the file was successfully streamed; false on error.
     */
    public static function read(
        mixed $source, 
        ?int $filesize = null, 
        MIME|string|null $mime = null,
        int $length = (1 << 21),
        int $delay = 0,
        int &$position = 0,
        bool $nonBlocking = false
    ): bool 
    {
        if (is_string($source)) {
            if (!is_file($source) || !is_readable($source)) {
                return false;
            }

            $stream = fopen($source, 'rb');
        }

        if ($stream === false || !is_resource($stream)) {
            return false;
        }

        $filesize ??= self::size($stream);

        if ($filesize === null || $filesize <= 0 || $position >= $filesize) {
            return false;
        }

        if ($filesize <= 5 * 1024 * 1024 && $position === 0) {
            $result = fpassthru($stream);
            fclose($stream);

            if ($result === false || $result === 0) {
                $position = 0;
                return false;
            }

            $position = $filesize;
            return true;
        }

        $isBinary = ($mime instanceof MIME) 
            ? $mime->isBinary()
            : (new MIME($mime ?? $stream))->isBinary();

        $result = self::fileRead(
            $stream, 
            $filesize, 
            $length, 
            $delay, 
            $position, 
            $nonBlocking,
            $isBinary 
        );

        if (is_resource($stream)) {
            fclose($stream);
        }

        if ($result === -1) {
            $position = 0;
            return false;
        }

        $position = $result;
        return true;
    }

    /**
     * Write or append data to a file.
     *
     * This method supports writing both string content and stream resources.
     * It handles optional append mode, file locks, include path, and binary content detection.
     *
     * @param string $filename Path to the file to write.
     * @param resource|string $content The data to write, either as a string or a stream resource.
     * @param int $flags Optional file flags (e.g., FILE_APPEND, FILE_USE_INCLUDE_PATH, LOCK_EX).
     * @param int $retries Number of times to retry acquiring the lock in non-blocking mode.
     * @param int $delay Delay in microseconds between retries (default 50_000 = 50ms).
     * @param resource|null $context Optional stream context created via stream_context_create().
     * @param int &$bytes The number of bytes written, or 0 on error.
     *
     * @return bool Returns true if writing succeeds, false otherwise.
     * @throws FileException If the provided stream is invalid or writing fails.
     * 
     * @see self::writeFromStream() To write from stream.
     *
     * @example - Example:
     * ```php
     * // Write string
     * Filesystem::write('/path/to/file.txt', 'Hello World', FILE_APPEND | LOCK_EX);
     *
     * // Write from stream
     * $stream = fopen('php://memory', 'rb+');
     * fwrite($stream, 'Hello Stream');
     * rewind($stream);
     * Filesystem::write('/path/to/file.txt', $stream, FILE_APPEND | LOCK_EX);
     * ```
     */
    public static function write(
        string $filename, 
        mixed $content, 
        int $flags = 0,  
        int $retries = 5,
        int $delay = 50_000,
        mixed $context = null,
        int &$bytes = 0
    ): bool
    {
        if ($filename === '') {
            return false;
        }

        if (self::isResource($content, 'stream')) {
            return self::writeFromStream(
                $filename, 
                $content, 
                $flags, 
                $context, 
                bytes: $bytes
            );
        }

        $content = (string) $content;
        $bytes = 0;
   
        try {
            $include = ($flags & FILE_USE_INCLUDE_PATH) !== 0;
            $mode = (($flags & FILE_APPEND) !== 0) ? 'a' : 'w';
            $mode .= (Helpers::isBinary($content) ? 'b' : '');

            $file = new SplFileObject($filename, $mode, $include, $context);
            $lockFlags = ($flags & (LOCK_EX | LOCK_NB | LOCK_SH | LOCK_UN)) ?: 0;
      
            if ($lockFlags) {
                if(($flags & LOCK_EX) === 0){
                    if (!$file->flock($lockFlags)) {
                        throw new FileException("Unable to acquire lock on file: {$filename}");
                    }
                }else{
                    $attempts = 0;
                    $nonBlocking = ($flags & LOCK_NB) !== 0;

                    while (!@$file->flock($lockFlags)) {
                        if (!$nonBlocking || ++$attempts > $retries) {
                            throw new RuntimeException("Unable to acquire lock on file: {$filename}");
                        }
                        
                        usleep($delay);
                    }
                }

                try {
                    $length = $file->fwrite($content);
                } finally {
                    $file->flock(LOCK_UN);
                }
            } else {
                $length = $file->fwrite($content);
            }
        } catch (Throwable $e) {
            FileException::handleFile($filename, $e->getMessage(), $e);
            return false;
        }

        $bytes = $length ?: 0;
        return $length !== false;
    }

    /**
     * Create a directory if it does not exist.
     *
     * This method attempts to create the directory with the specified permissions.
     * Supports recursive creation of nested directories.
     *
     * @param string $path The directory path to create.
     * @param int $permissions Unix file permissions (default: 0777, fully accessible to all users).
     * @param bool $recursive Whether to create nested directories recursively (default: true).
     *
     * @return bool Returns true if the directory already exists or was successfully created, false otherwise.
     * @throws FileException If unable to create the directory due to filesystem errors.
     *
     * @example - Example:
     * ```php
     * Filesystem::mkdir('writeable/storages/images', 0755);
     * ```
     */
    public static function mkdir(string $path, int $permissions = 0777, bool $recursive = true): bool 
    {
        if($path === ''){
            return false;
        }

        if (is_dir($path)) {
            return true;
        }
    
        error_clear_last();

        if(@mkdir($path, $permissions, $recursive)){
            return true;
        }

        $error = error_get_last()['message'] ?? 'Could not create directory';

        try {
            Luminova::permission('rw', $path, true);
        } catch (Throwable $e) {
            $error .= ' ' . $e->getMessage();
        }

        FileException::handleDirectory(
            $path, 
            $error
        );
        
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
        return self::recursion($origin, $dest, false, $skipIfExists, $copied);
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
        return self::recursion($origin, $dest, skippable: $skipIfExists, counter: $moved);
    }

    /**
	 * Deletes files and folders recursively.
	 *
	 * @param string $location The directory or file to delete.
	 * @param bool $deleteBase Wether to remove the base directory once done (default is false).
     * @param int &$deleted Reference to store the number of deleted items.
     * 
	 * @return int Returns count of deleted files.
	 */
	public static function delete(string $location, bool $deleteBase = false, int &$deleted = 0): int 
	{
		if (!file_exists($location)) {
			return $deleted;
		}
		
		$files = is_dir($location) ? 
			glob(rtrim($location, TRIM_DS) . DIRECTORY_SEPARATOR . '*', GLOB_MARK) : 
			glob($location . '*');

		foreach ($files as $file) {
			if (is_dir($file)) {
				self::delete($file, true, $deleted);
			} elseif(@unlink($file)){
				$deleted++;
			}
		}

        if($deleteBase && is_dir($location) && @rmdir($location)){
            $deleted++;
        }

		return $deleted;
	}

    /**
     * Create a symbolic link (symlink) to a target file or directory.
     *
     * Supports both Unix-like systems and Windows. Automatically creates
     * the destination directory if it does not exist, and replaces any existing link.
     * 
     * Alias {@see self::symlink()}
     *
     * @param string $target The target file or directory to link from.
     * @param string $link The path where the symbolic link will be created.
     * 
     * @return bool Returns true if the symbolic link was successfully created, false otherwise.
     */
    public static function symbolic(string $target, string $link): bool
    {
        if (!file_exists($target)) {
            return self::symError("The symlink target does not exist: {$target}");
        }

        $linkDir = dirname($link);
        if (!file_exists($linkDir) && !self::mkdir($linkDir, 0755)) {
            return self::symError("Unable to create symlink destination directory: {$linkDir}");
        }

        if (file_exists($link) || is_link($link)) {
            @unlink($link);
        }

        error_clear_last();

        if (is_platform('windows')) {
            $mode = is_dir($target) ? 'D' : 'H';
            exec("mklink /{$mode} " . escapeshellarg($link) . ' ' . escapeshellarg($target), $output, $result);

            if ($result !== 0) {
                return self::symError('Windows symlink creation failed', $output);
            }

            return true;
        }
        
        if (!symlink($target, $link)) {
            $error = error_get_last();
            $message = $error['message'] ?? 'Unknown error occurred while creating symlink.';

            return self::symError("Symlink creation failed: {$message}");
        }

        return true;
    }

    /**
     * Create a symbolic link (symlink) to a target file or directory.
     * 
     * Alias Of {@see self::symbolic()}
     *
     * @param string $target The target file or directory to link from.
     * @param string $link The path where the symbolic link will be created.
     * 
     * @return bool Returns true if the symbolic link was successfully created, false otherwise.
     */
    public static function symlink(string $target, string $link): bool
    {
       return self::symbolic($target, $link);
    }

    /**
     * Determine the size in bytes of a file, directory, stream, or string.
     *
     * Supported sources:
     *  - File path: returns file size.
     *  - Directory path: returns cumulative size of all files recursively.
     *  - Stream resource: returns size using fstat (if available).
     *
     * @param string|resource $source File path, directory path, stream resource, or string.
     *
     * @return int Return the calculated size in bytes.
     * @throws FileException If the source is invalid or does not exist.
     */
    public static function size(mixed $source): int
    {
        if ($source instanceof StreamInterface) {
            return $source->getSize() ?? 0;
        }

        if (is_resource($source)) {
            $stat = fstat($source);

            if ($stat !== false && isset($stat['size'])) {
                return (int) $stat['size'];
            }

            $meta = stream_get_meta_data($source);
            if (!empty($meta['seekable'])) {
                $pos = ftell($source);
                fseek($source, 0, SEEK_END);
                $size = ftell($source);
                fseek($source, $pos, SEEK_SET);
                return (int) $size;
            }

            return 0; 
        }

        if (!is_string($source)) {
            throw new FileException(sprintf(
                'Invalid source type: %s. Expected string path, readable file, resource, or stream object.',
                is_object($source) ? get_class($source) : gettype($source)
            ));
        }

        if (is_file($source)) {
            return (int) filesize($source);
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
                    $size += $file->getSize();
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

        return ($type === null || get_resource_type($resource) === $type);
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
        $path = self::$paths[$name] ?? null;
        return $path ? root($path) : '';
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
     * Forward unknown method calls to SplFileInfo.
     */
    public function __call(string $method, array $args): mixed
    {
        if (($this->info instanceof SplFileInfo) && method_exists($this->info, $method)) {
            return $this->info->{$method}(...$args);
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
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
        if(isset(self::$paths[$name])){
            return true;
        }

        return ($this->info instanceof SplFileInfo) && in_array($name, [
            'filename', 'pathname', 'path', 'params', 'inode', 'group',
            'extension', 'realPath', 'size', 'basename', 'owner',
            'mTime', 'cTime', 'type'
        ], true);
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
     * Writes or appends data from a stream resource to a file.
     *
     * This method reads a stream in chunks and writes it to the target file.
     * Supports optional append mode, include path, and delays between chunks.
     *
     * @param string $filename Path to the target file.
     * @param resource $resource Stream resource containing the data to write.
     * @param int $flags Optional file flags (FILE_APPEND, FILE_USE_INCLUDE_PATH, etc.).
     * @param resource|null $context Optional stream context created via stream_context_create().
     * @param int $delay Optional delay between writing chunks in microseconds (default: 0).
     * @param int &$bytes The number of bytes written, or 0 on error.
     *
     * @return bool Returns true on successful write, false otherwise.
     * @throws FileException If the provided resource is not a valid stream or writing fails.
     * 
     * @see self::write() To write string contents or stream.
     *
     * @example - Example:
     * ```php
     * $stream = fopen('php://memory', 'rb+');
     * fwrite($stream, 'Hello World');
     * rewind($stream);
     * 
     * Filesystem::writeFromStream('/path/to/file.txt', $stream, FILE_APPEND, delay: 100);
     * ```
     */
    private static function writeFromStream(
        string $filename, 
        mixed $resource, 
        int $flags = 0, 
        mixed $context = null,
        int $delay = 0,
        int &$bytes = 0
    ): bool
    {
        if ($filename === '' || $resource === null) {
            return false;
        }

        if (!self::isResource($resource, 'stream')) {
            throw new FileException(sprintf(
                'Invalid stream provided, expected stream resource, received: %s.', 
                gettype($resource)
            ));
        }

        error_clear_last();
        try {
            $meta = stream_get_meta_data($resource);
            $mode = (($flags & FILE_APPEND) !== 0) ? 'a' : 'w';
            $mode .= (str_contains($meta['mode'] ?? '', 'b') || Helpers::isBinary($resource) ? 'b' : '');

            $include = ($flags & FILE_USE_INCLUDE_PATH) !== 0;
            $file = new SplFileObject($filename, $mode, $include, $context);

            if (ftell($resource) !== 0 && ($meta['seekable'] ?? false)) {
                rewind($resource);
            }

            $lockFlags = ($flags & (LOCK_EX | LOCK_NB | LOCK_SH | LOCK_UN)) ?: LOCK_EX;
            $isLocked = $file->flock($lockFlags);

            while (!feof($resource)) {
                $data = fread($resource, 8192);

                if ($data === false) {
                    throw new FileException("Error reading from stream: {$filename}");
                }

                $written = $file->fwrite($data);
                if ($written === false) {
                    throw new FileException("Error writing to file: {$filename}");
                }

                $bytes += $written;

                if($delay > 0){
                    usleep($delay);
                }
            }

            if($isLocked){
                $file->flock(LOCK_UN);
            }
        } catch (Throwable $e) {
            FileException::handleFile($filename, $e->getMessage(), $e);
            return false;
        } finally {
            fclose($resource);
        }

        if (($error = error_get_last()) !== null) {
            FileException::handleFile($filename, $error['message']);
            return false;
        }

        return true;
    }

    /**
     * @param string $message
     * 
     * @return bool 
     */
    private static function symError(string $message): bool
    {
        if(PRODUCTION){
            Logger::dispatch('alert', $message);
            return false;
        }

       throw new FileException($message);
    }

    /**
     * Reads a file (text or binary) in chunks with optional non-blocking mode, 
     * customizable delay, and resumable position.
     *
     * Streams the file content in fixed-size chunks, flushing output after each chunk.
     * Can resume reading from a given position and stops if the connection is closed.
     * Automatically handles text line endings when reading text files.
     *
     * @param resource $handler     The open file handle.
     * @param int $filesize         Total size of the file (optional, auto-detected if null).
     * @param int $length           Maximum chunk size in bytes (default: 2MB).
     * @param int $delay            Delay in microseconds between chunk reads (default: 0).
     * @param int $position         Starting position in the file (default: 0).
     * @param bool $nonBlocking     If true, read in non-blocking mode.
     * @param bool $isBinary        If true, read as binary; otherwise as text.
     *
     * @return int Returns the final read position, or -1 on error.
     */
    private static function fileRead(
        mixed $handler, 
        int $filesize, 
        int $length = (1 << 21), 
        int $delay = 0,
        int $position = 0,
        bool $nonBlocking = false,
        bool $isBinary = true
    ): int
    {
        if ($nonBlocking) {
            stream_set_blocking($handler, false);
        }

        if ($position > 0) {
            fseek($handler, $position);
        }

        while (!feof($handler) && $position < $filesize) {
            $chunk = fread($handler, min($length, $filesize - $position));

            if ($chunk === false) {
                break;
            }

            if ($nonBlocking && $chunk === '') {
                usleep($delay ?: 1);
                continue;
            }

            if($isBinary){
                echo $chunk;
                $position += strlen($chunk);
            }else{
                $last = strrpos($chunk, "\n") ?: mb_strlen($chunk);

                echo mb_substr($chunk, 0, $last);
                $position += $last;
            }

            flush();

            if ($delay > 0) {
                usleep($delay);
            }

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }

        fclose($handler);
        return $position;
    }

    /**
     * Recursively copy or move files and directories from a source to a destination.
     *
     * Handles single files, nested directories, and optionally skips existing files/directories.
     * Updates a reference counter for the number of items successfully copied/moved.
     *
     * @param string $origin The source file or directory path.
     * @param string $dest The destination path.
     * @param bool $isMove Whether to perform a move (true) or copy (false) operation.
     * @param bool $skippable Whether to skip items that already exist in the destination (default: false).
     * @param int &$counter Reference to store the number of successfully copied/moved items.
     *
     * @return bool Returns true if at least one file or directory was successfully copied/moved, false otherwise.
     * @throws RuntimeException If the source does not exist or is not readable.
     * @throws FileException If unable to create destination directories.
     */
    private static function recursion(
        string $origin,
        string $dest,
        bool $isMove = true,
        bool $skippable = false,
        int &$counter = 0
    ): bool 
    {
        if (!file_exists($origin)) {
            throw new RuntimeException("Source '{$origin}' does not exist.");
        }

        if (is_file($origin)) {
            if ($skippable && file_exists($dest)) {
                return false;
            }

            if (!self::mkdir(dirname($dest), Files::$dirPermissions)) {
                return false;
            }

            $success = $isMove ? rename($origin, $dest) : copy($origin, $dest);
            if ($success) {
                $counter++;
                return true;
            }

            return false;
        }

        if (!is_dir($origin) || !is_readable($origin)) {
            throw new RuntimeException("Source directory '{$origin}' is not readable.");
        }

        if (!self::mkdir($dest, Files::$dirPermissions)) {
            return false;
        }

        $dir = opendir($origin);
        if (!$dir) {
            return false;
        }

        try {
            while (($file = readdir($dir)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $srcFile = $origin . DIRECTORY_SEPARATOR . $file;
                $destFile = $dest . DIRECTORY_SEPARATOR . $file;

                if ($skippable && file_exists($destFile)) {
                    continue;
                }

                if (is_dir($srcFile)) {
                    self::recursion($srcFile, $destFile, $isMove, $skippable, $counter);
                    usleep(10000);
                    continue;
                }

                $success = $isMove 
                    ? rename($srcFile, $destFile) 
                    : copy($srcFile, $destFile);

                if ($success) {
                    $counter++;
                }

                usleep(10000);
            }
        } finally {
            closedir($dir);
        }

        if ($isMove && $counter > 0) {
            @rmdir($origin);
        }

        return $counter > 0;
    }
}