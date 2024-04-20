<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Storages;

use \Luminova\Storages\StorageAdapters;
use \Luminova\Functions\Files;
use \League\Flysystem\Filesystem;
use \League\Flysystem\FileAttributes;
use \League\Flysystem\DirectoryAttributes;
use \Luminova\Time\Time;
use \Luminova\Exceptions\StorageException;
use \Exception;

class Storage extends StorageAdapters
{
    /**
      * The filesystem instance.

     * @var FileSystem|null $filesystem
    */
    private ?FileSystem $filesystem = null;

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
     * Constructs a new `Storage` instance with the specified adapter.
     * 
     * @param string $adapter The storage adapter to use.
    */
    public function __construct(string $adapter)
    {
        $this->adapter = strtolower($adapter);
        $this->config = static::getConfigs($this->adapter);

        if($this->filesystem === null){
            parent::isInstalled($this->adapter);
            $this->filesystem = new Filesystem(
                parent::getAdapter($this->adapter, $this->config),
                $this->config['urls']
            );
        }
    }

    /**
     * Creates a new `Storage` instance for the specified adapter context.
     * 
     * @param string $adapter The storage adapter context.
     * @return self The New `Storage` instance.
    */
    public static function context(string $adapter = 'local'): self
    {
        return new self($adapter);
    }

    /**
     * Creates a new storage disk / directory.
     * 
     * @param string $location The disk path or name.
     * 
     * @return self Return class instance.
     * @throws StorageException If an error occurs during the creation.
    */
    public function disk(string $location): self 
    {
        if($location === '' || $location === '.' || $location === './'){
            throw new StorageException('Disk method doesn\'t support blank string or patterns "' . $location . '".');
        }

        $this->chdir($location)->mkdir('');

        return $this;
    }

    /**
     * Change to another storage directory.
     * 
     * @param string $directory The new current directory
     * 
     * @return self Return class instance.
     * 
     * > Shortcut to return to the main storage directory are `blank string`, `.` or `./`.
    */
    public function chdir(string $directory = './'): self 
    {
        if($directory === '' || $directory === '.' || $directory === './'){
            $this->directory = '';
            return $this;
        }

        $directory = trim($directory, DIRECTORY_SEPARATOR);
        $this->directory = rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $directory . DIRECTORY_SEPARATOR;

        return $this;
    }

    /**
     * Get remote file symbolic url.
     * 
     * @param string $file The file name and path to remove file.
     * 
     * @return string|null Return remote url to file otherwise null.
    */
    public function url(string $file): string|null
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
    public function tempUrl(string $file, int $minutes = 1): string|null 
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
     * Create a symlink to a target file or directory.
     *
     * @param string $target The targe file or directory to link from.
     * @param string  $link The location of the link.
     * 
     * @return bool|null Return true if the link was successfully created false otherwise.
    */
    public function symbolic(string $target, string $link): bool
    {
        $target = $this->config['base'] . ltrim($this->getDisk($target), DIRECTORY_SEPARATOR);
      
        $directory = $this->config['assets'];
        $link = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($link, DIRECTORY_SEPARATOR);
        $linkpath = dirname($link);

        if (!file_exists($target)) {
            logger('alert', 'The symlink target file does not exist');
            return false;
        }

        if(!file_exists($linkpath) && !make_dir($linkpath, 0755)){
            logger('alert', 'Unable tocreate symlink destination directory');
            return false;
        }

        error_clear_last();

        if (is_platform('windows')) {
            $mode = is_dir($target) ? 'D' : 'H';
            exec("mklink /{$mode} ".escapeshellarg($link).' '.escapeshellarg($target), $output, $result);

            if($result === 1){
                logger('alert', 'Symlink creation failed:',[$output]);
            }

            return $result === 0;
        }
        
        $result = symlink($target, $link);
        if (!$result) {
            $error = error_get_last();
            if ($error !== null) {
                logger('alert', 'Symlink creation failed: ' . $error['message'] ?? '');
            } else {
                logger('alert', 'Unknown error occurred while creating symlink.');
            }
        }

        return $result;
    }

    /**
     * Writes contents to a file in current working directory.
     * 
     * @param string $filename The name of the file.
     * @param mixed $contents The contents to write.
     *  - Passed true when writing large files.
     * @param bool $steam The type of write operation (default: false).
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
     * Create a symbolic link after writing file to disk.
     * 
     * @return string|false The symbolic link location or false on failure.
     * 
     * > This method is only available on local filesystem.
     * 
     * > Also it shpuld only be called after method `write` has been called otherwise it will return false.
    */
    public function toLink(): string|false
    {
        if($this->filename === '' || $this->adapter !== 'local'){
            return false;
        }

        $target = basename($this->filename);
        $link = str_replace($this->config['base'], $this->config['assets'], $this->filename);

        if($this->symbolic($target, $link)){
            try {
                return $this->url($target);
                //return $this->filesystem->publicUrl($this->filename);
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Get the checksum for a file.
     *
     * @return string|false
     *
     * @throws StorageException If an error occurs during the write operation.
    */
    public function checksum(string $path, array $options = []): string
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
     * @return mixed The file contents.
     * @throws StorageException If an error occurs during the read operation.
     */
    public function read(string $filename, bool $steam = false){
        try {
            $filename = $this->getDisk($filename);

            if($steam){
                return $this->filesystem->readStream($filename);
            }

            return $this->filesystem->read($filename);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }
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
     * @return mixed The file contents.
     * @throws StorageException If an error occurs during the read operation.
    */
    public function download(string $filename, string $name = null, bool $steam = false, array $headers = []){
        try {
            $name ??= basename($filename);
            $content = $this->read($filename, $steam);

            return Files::download($content, $name, $headers);
            
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }
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
                if($return === 'all'){
                    $files[] = $item->jsonSerialize();
                }else{
                    if ($return === 'files' && $item instanceof FileAttributes) {
                        $files[] = $item->jsonSerialize();
                    } elseif ($return === 'dirs' && $item instanceof DirectoryAttributes) {
                        $files[] = $item->jsonSerialize();
                    }
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
            return static::fileExist($filename);
        }

        if($type === 'dir'){
            return static::dirExist($filename);
        }

        throw new StorageException('Invalid argument type "' . $type . '" was specified, allowed types are "file" or "dir');
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
     *                    Otherwise returns false if opration failed.
     * @throws StorageException If an error occurs during the operation.
     */
    public function visibility(string $filename, ?string $visibility = null): false|string 
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
     * Creates a new directory in the current workind `disk` or `chdir`.
     * 
     * @param string $path The path of the directory to create.
     * 
     * @throws StorageException If an error occurs during the creation.
    */
    public function mkdir(string $path): void 
    {
        try {
            $path = $this->getDisk($path);
            $this->filesystem->createDirectory($path, static::$configs['default']);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }              
    }

    /**
     * Moves a file or directory from the current workind `disk` or `chdir` to a new location.
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
            $this->filesystem->move($source, $destination, static::$configs['default']);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Copies a file or directory from the current workind `disk` or `chdir` to a new location.
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
            $this->filesystem->copy($source, $destination, static::$configs['default']);
        } catch (Exception $e) {
            StorageException::throwException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * Retrieves the configurations for the specified context.
     * 
     * @param string $context The storage context.
     * @return array<int,mixed> The configurations for the context.
    */
    private static function getConfigs(string $context = 'local'): array 
    {
        if(static::$configs === []){
            $path = path('controllers') . 'Config' . DIRECTORY_SEPARATOR . 'Storage.php';

            if (file_exists($path)) {
                static::$configs = require_once $path;
            }
        }

        return static::$configs[$context] ?? [];
    }

    /**
     * Prepend storage directory with the new file or path.
     * 
     * @param string $file The name file to prepend.
     * 
     * @return string Full file location.
    */
    private function getDisk(string $file): string 
    {
        return $this->directory . ltrim($file, DIRECTORY_SEPARATOR);
    }
}