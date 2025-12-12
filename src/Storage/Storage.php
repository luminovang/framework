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
namespace Luminova\Storage;

use \Exception;
use \Luminova\Http\File;
use \Luminova\Time\Time;
use \Luminova\Http\Downloader;
use function \Luminova\Funcs\configs;
use \Luminova\Exceptions\StorageException;
use \Luminova\Storage\Filesystem as FS;
use \Luminova\Storage\Adapters\Adapters;
use \League\Flysystem\{Filesystem, FileAttributes, DirectoryAttributes};

class Storage extends Adapters
{
    /**
     * The filesystem instance.
     * 
     * @var Filesystem|null $filesystem
     */
    private ?Filesystem $filesystem = null;

    /**
     * The configurations for different storage contexts.
     * 
     * @var array $configs
     */
    private static array $configs = [];

    /**
     * The configuration for the current adapter.
     * 
     * @var array $config
     */
    private array $config = [];

    /**
     * The current working directory.
     * 
     * @var string $directory
     */
    private string $directory = '';

    /**
     * Storage adapter name.
     * 
     * @var string $adapter;
     */
    private string $adapter = '';

    /**
     * Last write filename
     * 
     * @var string $filename;
     */
    private string $filename = '';

    /**
     * Constructs a new `Storage` instance using the specified storage adapter.
     *
     * This initializes the storage system with the given adapter and its configuration.
     *
     * Supported Adapters:
     * - `local`
     * - `ftp`
     * - `memory`
     * - `aws-s3`
     * - `aws-async-s3`
     * - `azure-blob`
     * - `google-cloud`
     * - `sftp-v3`
     * - `web-dev`
     * - `zip-archive`
     *
     * @param string $adapter The name of the storage adapter to use.
     */
    public function __construct(string $adapter)
    {
        $this->adapter = strtolower($adapter);
        $this->config = self::getConfigs($this->adapter);

        if(!$this->filesystem instanceof Filesystem){
            parent::isInstalled($this->adapter);
            $this->filesystem = new Filesystem(
                parent::getAdapter($this->adapter, $this->config),
                $this->config['urls'] ?? []
            );
        }
    }

    /**
     * Creates a new `Storage` instance for the given adapter context.
     *
     * Provides a convenient way to instantiate the `Storage` class with a specific storage backend.
     * Defaults to the local adapter if none is provided.
     *
     * Supported Adapters:
     * - `local`
     * - `ftp`
     * - `memory`
     * - `aws-s3`
     * - `aws-async-s3`
     * - `azure-blob`
     * - `google-cloud`
     * - `sftp-v3`
     * - `web-dev`
     * - `zip-archive`
     *
     * @param string $adapter The storage adapter context. Defaults to `local`.
     *
     * @return static Return a new instance of the `Storage` class.
     */
    public static function context(string $adapter = parent::LOCAL): self
    {
        return new self($adapter);
    }

    /**
     * Sets the working directory and creates it if it doesn't exist.
     *
     * This method changes the current working directory to the specified `$location`.
     * If the directory does not exist, it will be automatically created.
     *
     * @param string $location The target disk path or directory name. Must not be blank or a relative symbol (`.`, `./`).
     *
     * @return self Returns the instance of storage class.
     *
     * @throws StorageException If the path is invalid or the directory creation fails.
     */
    public function disk(string $location): self 
    {
        if($location === '' || $location === '.' || $location === './'){
            throw new StorageException('Disk method doesn\'t support blank string or patterns "' . $location . '".');
        }

        $this->chdir($location)->mkdir();

        return $this;
    }

    /**
     * Changes the current working storage directory.
     *
     * If the provided `$directory` is an empty string, `.` or `./`, it resets back to the main storage root.
     *
     * @param string $directory The new relative directory path. Use an empty string, `.` or `./` to return to root.
     *
     * @return self Returns the instance of storage class.
     */
    public function chdir(string $directory = './'): self 
    {
        if($directory === '' || $directory === '.' || $directory === './' || $directory === '.\\'){
            $this->directory = '';
            return $this;
        }

        $directory = trim($directory, TRIM_DS);
        $this->directory = rtrim($this->directory, TRIM_DS) . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR;

        return $this;
    }

    /**
     * Get remote file public url.
     * 
     * @param string $file The file name and path to remove file.
     * 
     * @return string|null Return remote url to file otherwise null.
     */
    public function url(string $file): ?string
    {
        $filename = $this->getDisk($file);
        
        try {
            return $this->filesystem->publicUrl($filename);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Get a temporal remote url.
     * 
     * @param string $file The file name and path to remove file.
     * @param int $minutes Expiry duration in minutes.
     * 
     * @return string|null Return remote url to file otherwise null.
     */
    public function tempUrl(string $file, int $minutes = 1): ?string 
    {
        $filename = $this->getDisk($file);
     
        try {
            return $this->filesystem->temporaryUrl($filename, Time::now()->modify("+{$minutes} minutes"));
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Create a symlink to a target local file or directory.
     *
     * @param string $target The targe file or directory to link from.
     * @param string  $link The location of the link.
     * 
     * @return bool Return true if the link was successfully created false otherwise.
     */
    public function symbolic(string $target, string $link): bool
    {
        if($this->adapter !== parent::LOCAL){
            return false;
        }

        $target = $this->config['base'] . ltrim($this->getDisk($target), TRIM_DS);
        $link = rtrim($this->config['assets'], TRIM_DS) . DIRECTORY_SEPARATOR . ltrim($link, TRIM_DS);

        return FS::symbolic($target, $link);
    }

    /**
     * Create a symbolic link after writing file to disk.
     * 
     * @return string|false The symbolic link location or false on failure.
     * 
     * > This method is only available on local filesystem.
     * 
     * > Also it should only be called after method `write` has been called otherwise it will return false.
     */
    public function toLink(): string|bool
    {
        if($this->filename === '' || $this->adapter !== parent::LOCAL){
            return false;
        }

        $target = basename($this->filename);
        $link = str_replace($this->config['base'], $this->config['assets'], $this->filename);

        if($this->symbolic($target, $link)){
            try {
                return $this->url($target);
            } catch (Exception) {
                return false;
            }
        }

        return false;
    }

    /**
     * Writes contents to a file in current working directory.
     * 
     * @param string $filename The name of the file.
     * @param string|resource $contents The contents to write string or resource for stream large uploads.
     * @param bool $steam The type of write operation (default: false).
     * - Passed true when writing large files.
     * 
     * @return self Class instance.
     * @throws StorageException If an error occurs during the write operation.
     */
    public function write(string $filename, mixed $contents, bool $steam = false): self 
    {
        $this->filename = $this->getDisk($filename);
     
        try {
            if($steam){
                $this->filesystem->writeStream($this->filename, $contents);
                return $this;
            }

            $this->filesystem->write($this->filename, $contents);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Upload file in working directory.
     *
     * @param File $file Instance of file being uploaded.
     * 
     * @return bool Return true if upload was successful false otherwise.
     * @throws StorageException If an error occurs during the upload operation.
     */
    public function upload(File $file): bool
    {
        if (!$file || !$file->valid()) {
            return false;
        }

        try {
            $filename = basename($file->getName());

            $this->write(
                $filename, 
                $file->getTemp() ? fopen($file->getTemp(), 'r') : $file->getContent(), 
                true
            );

            return true;
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Get the checksum for a file.
     *
     * @param string $path The path to the file.
     * @return array $options Optional file options.
     * 
     * @return string|false Return file checksum, otherwise false.
     *
     * @throws StorageException If an error occurs during the write operation.
     */
    public function checksum(string $path, array $options = []): string|bool
    {
        try {
            return $this->filesystem->checksum($path, $options);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);

        }

        return false;
    }

    /**
     * Reads the contents of a file from the current working directory.
     * 
     * @param string $filename The name of the file.
     * @param bool $steam The type of read operation (default: false).
     *  - Passed true when reading large files.
     * 
     * @return resource|string|false The file contents.
     * @throws StorageException If an error occurs during the read operation.
     */
    public function read(string $filename, bool $steam = false): mixed
    {
        try {
            $filename = $this->getDisk($filename);

            if($steam){
                return $this->filesystem->readStream($filename);
            }

            return $this->filesystem->read($filename);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Download file from the current working directory.
     * 
     * @param string $filename The name of the file.
     * @param string|null $name Download file name.
     * @param bool $steam The type of read operation (default: false).
     *  - Passed true when downloading large files.
     * @param array $headers Optional download headers.
     * 
     * @return bool Return true if download was successful, otherwise false.
     * @throws StorageException If an error occurs during the read operation.
     */
    public function download(string $filename, ?string $name = null, bool $steam = false, array $headers = []): bool
    {
        try {
            $name ??= basename($filename);

            return Downloader::download($this->read($filename, $steam), $name, $headers);
            
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Deletes a file or directory in current working directory..
     * 
     * @param string $filename The name of the file or directory.
     * @param string $type The type of deletion operation (file or dir).
     * 
     * @throws StorageException If an error occurs during the deletion operation.
     */
    public function delete(string $filename, string $type = 'file'): void 
    {
        try {
            $filename = $this->getDisk($filename);

            if($type === 'file'){
                $this->filesystem->delete($filename);
                return;
            }

            if($type === 'dir'){
                $this->filesystem->deleteDirectory($filename);
                return;
            }

            throw new StorageException('Invalid argument type "' . $type . '" was specified, allowed types are "file" or "dir');
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Lists the contents of a directory in the current working directory..
     * 
     * @param string $directory Optional directory path or it will list all in current directory.
     * @param string $return The type of result to return (files, dirs, or all).
     * @param bool $recursive Whether to list recursively.
     * 
     * @return array The list of files or directories.
     * @throws StorageException If an error occurs during the listing operation.
     */
    public function list(?string $directory = null, string $return = 'files', bool $recursive = false): array
    {
        $directory ??= '';
        $files = [];
        try {
            $directory = $this->getDisk($directory);
            $listing = $this->filesystem->listContents($directory, $recursive);

            foreach ($listing as $item) {
                if ($return === 'all'){
                    $files[] = $item->jsonSerialize();
                } elseif ($return === 'files' && $item instanceof FileAttributes) {
                    $files[] = $item->jsonSerialize();
                } elseif ($return === 'dirs' && $item instanceof DirectoryAttributes) {
                    $files[] = $item->jsonSerialize();
                }
            }
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return $files;
    }

    /**
     * Checks if a file exists in the current working directory.
     * 
     * @param string $filename The name of the file.
     * @return bool True if the file exists, false otherwise.
     * @throws StorageException If an error occurs during the existence check.
     */
    public function fileExist(string $filename): bool 
    {
        try {
            $filename = $this->getDisk($filename);
            return $this->filesystem->fileExists($filename);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Checks if a directory exists in current working directory.
     * 
     * @param string $path The directory path.
     * @return bool True if the directory exists, false otherwise.
     * @throws StorageException If an error occurs during the existence check.
     */
    public function dirExist(string $path): bool 
    {
        try {
            $path = $this->getDisk($path);
            return $this->filesystem->directoryExists($path);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Checks if a file or directory exists based on the provided type.
     * 
     * @param string $filename The name of the file or directory.
     * @param string $type The type of entity to check (file or dir).
     * 
     * @return bool True if the file or directory exists, false otherwise.
     * @throws StorageException If an error occurs during the existence check.
     */
    public function exist(string $filename, string $type = 'file'): bool 
    {
        $filename = $this->getDisk($filename);

        if($type === 'file'){
            return self::fileExist($filename);
        }

        if($type === 'dir'){
            return self::dirExist($filename);
        }

        throw new StorageException(
            sprintf('Invalid argument type "%s" was specified, allowed types are "file" or "dir', $type)
        );
    }

    /**
     * Checks if a file or directory exists based on the provided URL or path.
     * 
     * @param string $filename The name of the file or directory, which can also be a URL.
     * @return bool True if the file or directory exists, false otherwise.
     * @throws StorageException If an error occurs during the existence check.
     */
    public function has(string $filename): bool 
    {
        try {
            $filename = $this->getDisk($filename);

            return $this->filesystem->has($filename);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Retrieves the last modified timestamp of a file.
     * 
     * @param string $filename The name of the file.
     * @return int The UNIX timestamp of the last modification time.
     * @throws StorageException If an error occurs during the retrieval.
     */
    public function modified(string $filename): int 
    {
        try {
            $filename = $this->getDisk($filename);

            return $this->filesystem->lastModified($filename);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return 0;
    }

    /**
     * Retrieves the MIME type of a file.
     * 
     * @param string $filename The name of the file.
     * @return string The MIME type of the file.
     * @throws StorageException If an error occurs during the retrieval.
     */
    public function mime(string $filename): string 
    {
        try {
            $filename = $this->getDisk($filename);

            return $this->filesystem->mimeType($filename);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }
        
        return 'failed';
    }

    /**
     * Retrieves the size of a file.
     * 
     * @param string $filename The name of the file.
     * @return int The size of the file in bytes.
     * @throws StorageException If an error occurs during the retrieval.
     */
    public function size(string $filename): int 
    {
        try {
            $filename = $this->getDisk($filename);
            return $this->filesystem->fileSize($filename);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }    
        
        return 0;
    }

    /**
     * Retrieves or sets the visibility of a file or directory.
     * 
     * @param string $filename The name of the file or directory.
     * @param string|null $visibility Optional. The visibility to set ('public', 'private', or null to retrieve). Default is null.
     * 
     * @return false|string If $visibility is null, returns the current visibility as a string ('public' or 'private').
     *                    If $visibility is provided update visibility and returns the visibility that was set.
     *                    Otherwise returns false if operation failed.
     * @throws StorageException If an error occurs during the operation.
     */
    public function visibility(string $filename, ?string $visibility = null): bool|string 
    {
        if($visibility === ''){
            return false;
        }

        try {
            $filename = $this->getDisk($filename);

            if($visibility === null){
                return $this->filesystem->visibility($filename);
            }

            $this->filesystem->setVisibility($filename, $visibility);

            return $visibility;
          
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }

        return false;
    }

    /**
     * Create a directory in the current working path.
     *
     * If no path is provided, the method will use the value set via `chdir()`.
     *
     * @param string|null $path Optional directory path to create. If null, the previously set working path is used.
     *
     * @throws StorageException If the directory creation fails.
     */
    public function mkdir(?string $path = null): void 
    {
        try {
            $path = $this->getDisk($path ?? '');

            if($this->filesystem->directoryExists($path)){
                return;
            }

            $this->filesystem->createDirectory($path, self::$configs['default']);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }              
    }

    /**
     * Moves a file or directory from the current working `disk` or `chdir` to a new location.
     * 
     * @param string $source The current path of the file or directory.
     * @param string $destination The new path for the file or directory.
     * 
     * @throws StorageException If an error occurs during the move operation.
     */
    public function move(string $source, string $destination): void 
    {
        try {
            $source = $this->getDisk($source);
            $this->filesystem->move($source, $destination, self::$configs['default']);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Copies a file or directory from the current working `disk` or `chdir` to a new location.
     * 
     * @param string $source The path of the original file or directory.
     * @param string $destination The path of the destination file or directory.
     * 
     * @throws StorageException If an error occurs during the copy operation.
     */
    public function copy(string $source, string $destination): void 
    {
        try {
            $source = $this->getDisk($source);
            $this->filesystem->copy($source, $destination, self::$configs['default']);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Retrieves the configurations for the specified context.
     * 
     * @param string $context The storage context.
     * 
     * @return array<int,mixed> The configurations for the context.
     */
    private static function getConfigs(string $context = parent::LOCAL): array 
    {
        if(self::$configs === [] && ($config = configs('Storage')) !== null){
            self::$configs = $config;
        }

        return self::$configs[$context] ?? [];
    }

    /**
     * Prepend storage directory with the new file or path.
     * 
     * @param string $file The name file to prepend.
     * 
     * @return string Returns the full file location.
     */
    private function getDisk(string $file): string 
    {
        return $this->directory . ltrim($file, TRIM_DS);
    }
}