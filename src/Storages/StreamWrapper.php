<?php
/**
 * Luminova Framework asynchronous queue execution using fiber, process fork or default.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 * @see https://www.macs.hw.ac.uk/~hwloidl/docs/PHP/function.stream-wrapper-register.html
 */
namespace Luminova\Storages;

use \Luminova\Exceptions\FileException;
use function \Luminova\Funcs\make_dir;

class StreamWrapper 
{
    /**
     * The full path of the file or directory, excluding the scheme.
     * 
     * @var string $fullPath 
     */
    protected string $fullPath = '';

    /**
     * The URI scheme (e.g., "foo") for this stream wrapper.
     * 
     * @var string|null $scheme
     */
    protected ?string $scheme = null;

    /**
     * Registered schemes associated with the StreamWrapper.
     * 
     * @var array $schemes
     */
    private static array $schemes = [];

    /**
     * File or directory resource currently opened by this wrapper.
     * 
     * @var resource|null $resource
     */
    protected mixed $resource = null;

    /**
     * List of entries in the currently opened directory.
     * 
     * @var array|null $dirEntries 
     */
    private static ?array $dirEntries = null;

    /**
     * Index of the current position within the directory entries.
     * 
     * @var int $dirIndex
     */
    private static int $dirIndex = 0;

    /**
     * Stream context passed to this wrapper.
     * 
     * @var mixed|null $context
     */
    public $context = null;

    /**
     * Stream wrapper constructor.
    */
    public function __construct(){}


    /**
     * Registers this stream wrapper for a given scheme.
     *
     * @param string $scheme The scheme to register.
     * 
     * @return bool Return true on success, false on failure.
     */
    public static function register(string $scheme): bool
    {
        $removed = true;
        if (in_array($scheme, stream_get_wrappers())) {
            $removed = self::unregister($scheme);
        }

        if($removed && stream_wrapper_register($scheme, self::class)){
            self::$schemes[$scheme] = $scheme;
            self::$dirEntries = null;
            self::$dirIndex = 0;
            return true;
        }

        return false;
    }

    /**
     * Unregister this stream wrapper for a given scheme.
     *
     * @param string $scheme The scheme to unregister.
     * 
     * @return bool Return true on success, false on failure.
     */
    public static function unregister(string $scheme): bool
    {
        return stream_wrapper_unregister($scheme);
    }

    /**
     * Restores the stream wrapper for a previously registered scheme.
     *
     * @param string $scheme The scheme to restore.
     * 
     * @return bool Return true on success, false on failure.
     */
    public static function restore(string $scheme): bool
    {
        return stream_wrapper_restore($scheme);
    }

    /**
     * Opens a stream for reading or writing.
     *
     * @param string $path The path to the resource.
     * @param string $mode The mode in which to open the stream.
     * @param int $options Stream options.
     * @param string &$opened_path Output parameter to hold the opened path.
     * 
     * @return bool Return true on success, false on failure.
     */
    public function stream_open(string $path, string $mode, $options, &$opened_path): bool 
    {
        if (preg_match('#^(?<scheme>[^:]+)://(?<fullpath>.+)$#', $path, $matches)) {
            $this->scheme = $matches['scheme'];
            $this->fullPath = $matches['fullpath'];
        } 

        if (!isset(self::$schemes[$this->scheme])) {
            return false;
        }

        $directory = dirname($this->fullPath);
        if (!is_dir($directory) && !make_dir($directory, 0755)) {
            return false;
        }

        $this->resource = fopen($this->fullPath, $mode);
        if ($this->resource) {
            $opened_path = $this->fullPath; 
            return true; 
        }

        return false;
    }

    /**
     * Reads data from the open stream.
     *
     * @param int $count The number of bytes to read.
     * 
     * @return string The data read from the stream.
     * @throws FileException Throws if no resource is found.
     */
    public function stream_read(int $count): string 
    {
        $read = $this->call('fread', $count);
        return ($read === false) 
            ? ''
            : $read;
    }

    /**
     * Writes data to the open stream.
     *
     * @param string $data The data to write.
     * @param int|null $length Optional length of data to write.
     * 
     * @return int The number of bytes written.
     * @throws FileException Throws if no resource is found.
     */
    public function stream_write(string $data, ?int $length = null): int 
    {
        $write = $this->call('fwrite', $data, $length);
        return ($write === false) 
            ? 0 
            : $write;
    }

    /**
     * Seek to a specific point in the stream.
     * 
     * @param int $offset The offset to seek to
     * @param int $whence SEEK_SET, SEEK_CUR, or SEEK_END.
     * 
     * @return bool
     * @throws FileException Throws if no resource is found.
     */
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool 
    {
        $seek = $this->call('fseek', $offset, $whence);
        return ($seek === false) 
            ? false 
            : $seek === 0;
    }

    /**
     * Get the current position in the stream.
     * 
     * @return int
     * @throws FileException Throws if no resource is found.
     */
    public function stream_tell(): int 
    {
        $tell = $this->call('ftell');
        return ($tell === false) 
            ? 0 
            : $tell;
    }

    /**
     * Close the stream.
     * 
     * @return void
     * @throws FileException Throws if no resource is found.
     */
    public function stream_close(): void 
    {
        $this->call('fclose');
    }

    /**
     * Check if end of file is reached.
     * 
     * @return bool Return true if end of file.
     * @throws FileException Throws if no resource is found.
     */
    public function stream_eof(): bool 
    {
        return $this->call('feof');
    }

    /**
     * Stat method to support file functions like `filesize`.
     * 
     * @return array Return file information.
     * @throws FileException Throws if no resource is found.
     */
    public function stream_stat(): array 
    {
        $stat = $this->call('fstat');
        return ($stat === false) 
            ? []
            : $stat;
    }

    /**
     * Flushes the output to the underlying storage, ensuring any buffered data is written.
     *
     * @return bool Return true on successful flush, false on failure.
     * @throws FileException Throws if no resource is found.
     */
    public function stream_flush(): bool 
    {
        return $this->call('fflush');
    }

    /**
     * Deletes a file at the specified path.
     *
     * @param string $path The path to the file.
     * 
     * @return bool Return true on success, false on failure.
     */
    public function unlink(string $path): bool 
    {
        if (!$this->setLocalContext($path)){
            return false;
        }

        if (file_exists($this->fullPath)) {
            return unlink($this->fullPath);
        }

        return false;
    }

    /**
     * Renames a file or directory.
     *
     * @param string $from The current path.
     * @param string $to The new path.
     * 
     * @return bool Return true on success, false on failure.
     */
    public function rename(string $from, string $to): bool
    {
        $external = $this->setExternalContext($to);

        if (!$this->setLocalContext($from) || !$external){
            return false;
        }

        return rename($this->fullPath, $external['fullpath']);
    }

    /**
     * Creates a directory at the specified path with given permissions.
     *
     * @param string $path The path where the directory will be created.
     * @param int $permissions The permissions for the new directory, default is 0777.
     * @param bool $recursive Whether to create directories recursively if needed, default is false.
     * 
     * @return bool Return true on success, false on failure.
     */
    public function mkdir(string $path, int $permissions = 0777, bool $recursive = false): bool
    {
        if (!$this->setLocalContext($path)){
            return false;
        }
  
        return mkdir($this->fullPath, $permissions, $recursive);
    }
    
    /**
     * Copies a file from a source path to a destination path.
     *
     * @param string $from The source file path to copy from.
     * @param string $to The destination file path to copy to.
     * 
     * @return bool Return true if the file was successfully copied, false otherwise.
     */
    public function copy(string $from, string $to): bool
    {
        $external = $this->setExternalContext($to);

        if (!$this->setLocalContext($from) || !$external){
            return false;
        }

        return copy($this->fullPath, $external['fullpath']);
    }

    /**
     * Removes a directory at the specified path.
     *
     * @param string $path  The path of the directory to be removed.
     * 
     * @return bool Return true if the directory was successfully removed, false otherwise.
     */
    public function rmdir(string $path): bool
    {
        if (!$this->setLocalContext($path)){
            return false;
        }

        return rmdir($this->fullPath);
    }

    /**
     * Retrieves the status information of a directory.
     *
     * @param string $directory  The directory path to retrieve status information for.
     * 
     * @return array Return an array with directory status details, or an empty array if the operation fails.
     */
    public function url_stat(string $directory): array
    {
        if (!$this->setLocalContext($directory)){
            return false;
        }

        $stat = stat($this->fullPath);
        return ($stat === false) 
            ? []
            : $stat;
    }
   
    /**
     * Reads the next entry from the opened directory.
     *
     * @return string Return the name of the next entry.
     */
    public function dir_readdir(): string
    {
        if (is_array(self::$dirEntries) && isset(self::$dirEntries[self::$dirIndex])) {
            return self::$dirEntries[self::$dirIndex++];
        }

        return false;
    }

    /**
     * Opens a directory for reading.
     *
     * @param string $directory The path to the directory.
     * 
     * @return bool Return true on success, false on failure.
     */
    public function dir_opendir(string $directory): bool
    {
        if (!$this->setLocalContext($directory)){
            return false;
        }

        if (is_dir($this->fullPath)) {
            if(scandir($this->fullPath) !== false){
                self::$dirEntries = scandir($this->fullPath);
                self::$dirIndex = 0;
                return true;
            }
        }

        return false;
    }

    /**
     * Resets the directory read pointer to the beginning.
     *
     * @return true Always returns true.
     */
    public function dir_rewinddir(): bool
    {
        self::$dirIndex = 0;
        return true;
    }

    /**
     * Closes the directory handle.
     *
     * @return true Always returns true.
     */
    public function dir_closedir(): bool
    {
        self::$dirEntries = null;
        self::$dirIndex = 0;
        return true;
    }

    /**
     * Checks if the current resource is valid.
     *
     * @return bool Return true if a valid resource, false otherwise.
     */
    protected function isResource(): bool 
    {
        return $this->resource !== null && is_resource($this->resource);
    }

    /**
     * Calls a function on the open resource, if available.
     *
     * @param string $fn The function name to call.
     * @param mixed ...$arguments Arguments to pass to the function.
     * 
     * @return mixed Return the result of the function, or false on failure.
     * @throws FileException Throws if no resource is found.
     */
    protected function call(string $fn, mixed ...$arguments): mixed 
    {
        if(!$this->isResource()){
            throw new FileException("Operation '{$fn}' is not allowed because the resource is invalid or unavailable.", FileException::NOT_ALLOWED);
        }

        return $fn($this->resource, ...$arguments);
    }

    /**
     * Sets the context for local operations based on the path.
     *
     * @param string $path The path to parse and set.
     * 
     * @return bool Return true if context is successfully set.
     */
    protected function setLocalContext(string $path): bool
    {
        $matches = $this->setExternalContext($path);
        if ($matches) {
            $this->scheme = $matches['scheme'];
            $this->fullPath = $matches['fullpath'];

            if (!isset(self::$schemes[$this->scheme])) {
                return false;
            }

            return true;
        } 

        return false;
    }

    /**
     * Parses an external path for scheme and full path.
     *
     * @param string $path The path to parse.
     * 
     * @return array|false Return array with scheme and full path or false if invalid.
     */
    protected function setExternalContext(string $path): array
    {
        if (preg_match('#^(?<scheme>[^:]+)://(?<fullpath>.+)$#', $path, $matches)) {
            return $matches;
        } 

        return false;
    }
}