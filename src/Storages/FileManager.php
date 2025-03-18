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
namespace Luminova\Storages;

use \Luminova\Http\Header;
use \App\Config\Files;
use \Luminova\Logger\Logger;
use \Luminova\Exceptions\FileException;
use \Luminova\Exceptions\RuntimeException;
use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;
use \SplFileObject;
use \Exception;

class FileManager
{
    /**
     * Path to the system framework codes.
     * 
     * @var string $system
     */
    protected string $system = 'system/';

    /**
     * Path to the system third-party plugins.
     * 
     * @var string $plugins
     */
    protected string $plugins = 'system/plugins/';

    /**
     * Path to the libraries and third-party modules.
     * 
     * @var string $library
     */
    protected string $library = 'libraries/libs/';

    /**
     * Path to the serialized shared services.
     * 
     * @var string $services
     */
    protected string $services = 'writeable/services/';

    /**
     * Path to the application controllers.
     * 
     * @var string $controllers
     */
    protected string $controllers = 'app/Controllers/';

    /**
     * Path to the application hmvc modules.
     * 
     * @var string $modules
     */
    protected string $modules = 'app/Modules/';

    /**
     * Path to the application files.
     * 
     * @var string $app
     */
    protected string $app = 'app/';

    /**
     * Path to the writeable files.
     * 
     * @var string $writeable
     */
    protected string $writeable = 'writeable/';

    /**
     * Path to the error logs.
     * 
     * @var string $logs
     */
    protected string $logs = 'writeable/log/';

    /**
     * Path to the application caches.
     * 
     * @var string $caches
     */
    protected string $caches = 'writeable/caches/';

    /**
     * Path to the public controller document root.
     * 
     * @var string $public
     */
    protected string $public = 'public/';

    /**
     * Path to the public assets directory.
     * 
     * @var string $assets
     */
    protected string $assets = 'public/assets/';

    /**
     * Path to the hmvc template views files.
     * 
     * @var string $views
     */
    protected string $views = 'resources/Views/';

    /**
     * Path to the application routes.
     * 
     * @var string $routes
     */
    protected string $routes = 'routes/';

    /**
     * Path to the languages modules.
     * 
     * @var string $languages
     */
    protected string $languages = 'app/Languages/';

    /**
     * Check if file has read or write permission is granted.
     * 
     * @param string $permission File access permission.
     * @param string|null $file File name or file path to check permissions (default: writeable dir).
     * @param bool $throw Indicate whether to throws an exception if permission is not granted.
     * 
     * @return bool Returns true if permission is granted otherwise false.
     * @throws FileException If permission is not granted and quiet is not passed true.
     */
    public static function permission(string $permission = 'rw', ?string $file = null, bool $throw = false): bool
    {
        $file ??= root('writeable');
        
        if ($permission === 'rw' && (!is_readable($file) || !is_writable($file))) {
            $error = "Read and Write permission denied for '%s, please grant 'read' and 'write' permission.";
            $code = FileException::READ_WRITE_PERMISSION_DENIED;
        } elseif ($permission === 'r' && !is_readable($file)) {
            $error = "Read permission denied for '%s', please grant 'read' permission.";
            $code = FileException::READ_PERMISSION_DENIED;
        } elseif ($permission === 'w' && !is_writable($file)) {
            $error = "Write permission denied for '%s', please grant 'write' permission.";
            $code = FileException::WRITE_PERMISSION_DENIED;
        } else {
            return true;
        }

        if (PRODUCTION && !$throw) {
            Logger::dispatch('critical', sprintf($error, $file));
            return false;
        }

        throw new FileException(sprintf($error, $file), $code);
    }

    /**
     * Set permissions for a file or directory.
     *
     * @param string $location The path to the file or directory.
     * @param int $permission The permission to set (Unix file permissions).
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
     * Converts file permission code to its string representation
     * 
     * @param int $permission Permission code
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
     * @param string $permission The permission string (e.g., 'rw-r--r--').
     * @param bool $decimal Weather to return permission as decimal or formatted octal string.
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
     * Reads the content of a file with options for specifying the length of data to read and the starting offset.
     * 
     * @param string $filename The path to the file to be read.
     * @param int $length The maximum number of bytes to read, if set to `0`, it read 8192 bytes at a time (default: 0).
     * @param int $offset The starting position in the file to begin reading from (default: 0).
     * @param bool $useInclude If `true`, the file will be searched in the include path (default: false). 
     * @param resource|null $context A context resource created with `stream_context_create()` (default: null).
     * @param int $delay The delay between each chunk in microseconds (default:, 0 second).
     * 
     * @return string|false Returns the contents of the file as a string, or `false` on failure.
     * @throws FileException If an error occurs while opening or reading the file.
     */
    public static function getContent(
        string $filename, 
        int $length = 0, 
        int $offset = 0, 
        bool $useInclude = false, 
        mixed $context = null,
        int $delay = 0
    ): string|bool
    {
        if ($filename === '') {
            return false;
        }

        try {
            $file = new SplFileObject($filename, 'r', $useInclude, $context);
            
            // Seek to the offset if needed
            if ($offset > 0) {
                $file->seek($offset);
            }

            $contents = '';
            while (!$file->eof()) {
                $readLength = ($length > 0 ? min($length - strlen($contents), 8192) : 8192);
                $data = $file->fread($readLength);

                if ($data === false) {
                    return false;
                }
                $contents .= $data;
                if($delay > 0){
                    usleep($delay);
                }

                // If a specific length is required and that length is met
                if ($length > 0 && strlen($contents) >= $length) {
                    break;
                }
            }

            return $contents;
        } catch (Exception|\RuntimeException|\LogicException $e) {
            FileException::handleReadFile($filename, $e->getMessage(), $e);
            return false;
        }

        return false;
    }

    /**
     * Write or append contents to a file.
     *
     * @param string $filename The path to the file where to write the data.
     * @param string|resource $content The contents to write to the file, either as a string or a stream resource.
     * @param int $flags [optional] The flags determining the behavior of the write operation (default: 0).
     * @param resource|null $context [optional] A valid context resource created with stream_context_create (default: null).
     * 
     * @return bool Return true if the operation was successful, false otherwise.
     * @throws FileException If unable to write to the file.
     */
    public static function write(string $filename, mixed $content, int $flags = 0, $context = null): bool
    {
        if ($filename === '') {
            return false;
        }

        if (!is_string($content)) {
            return self::stream($filename, $content, $flags, $context);
        }

        try {
            $include = ($flags & FILE_USE_INCLUDE_PATH) !== 0;
            $mode = (($flags & FILE_APPEND) !== 0) ? 'a' : 'w';
            $file = new SplFileObject($filename, $mode, $include, $context);

            if (($flags & (LOCK_EX | LOCK_NB | LOCK_SH | LOCK_UN)) !== 0) {
                $file->flock($flags);
                $result = $file->fwrite($content);
                $file->flock(LOCK_UN);
            } else {
                $result = $file->fwrite($content);
            }
        } catch (Exception|\RuntimeException|\LogicException $e) {
            FileException::handleFile($filename, $e->getMessage(), $e);
            return false;
        }

        return $result !== false;
    }

    /**
     * Write or append contents to a file using a stream resource.
     *
     * @param string $filename The path to the file where to write the data.
     * @param resource $content The contents to write to the file as a stream resource.
     * @param int $flags [optional] The value of flags can be any combination of the following flags (with some restrictions), joined with the binary OR (|) operator.
     * @param resource $context [optional] A valid context resource created with stream_context_create.
     * @param int $delay The delay between each chunk in microseconds (default: 0 second).
     * 
     * @return bool Return true if the operation was successful, false otherwise.
     * @throws FileException If unable to write to the file.
     */
    public static function stream(
        string $filename, 
        mixed $resource, 
        int $flags = 0, 
        mixed $context = null,
        int $delay = 0
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
            $mode = (($flags & FILE_APPEND) !== 0) ? 'a' : 'w';
            $include = ($flags & FILE_USE_INCLUDE_PATH) !== 0;
            $file = new SplFileObject($filename, $mode, $include, $context);

            // Rewind the stream if needed
            if (ftell($resource) !== 0 && stream_get_meta_data($resource)['seekable']) {
                rewind($resource);
            }

            while (!feof($resource)) {
                $data = fread($resource, 8192);
                if ($data === false) {
                    throw new FileException("Error reading from the stream.");
                }

                $written = $file->fwrite($data);
                if ($written === false) {
                    throw new FileException("Error writing to the file $filename.");
                }

                if($delay > 0){
                    usleep($delay);
                }
            }

            fclose($resource);
        } catch (Exception|\RuntimeException|\LogicException $e) {
            FileException::handleFile($filename, $e->getMessage(), $e);
            return false;
        }

        if (($error = error_get_last()) !== null) {
            FileException::handleFile($filename, $error['message']);
            return false;
        }

        return true;
    }

    /**
     * Checks if a variable is a valid resource of the specified type.
     *
     * @param mixed $resource The resource to check.
     * @param string|null $type The expected resource type. Possible values are: 'stream-context', 'stream', etc.
     * 
     * @return bool Returns true if the variable is a resource and matches the expected resource type, otherwise returns false.
     */
    public static function isResource(mixed $resource, ?string $type = null): bool 
    {
        if ($resource === null || !is_resource($resource)) {
            return false;
        } 
            
        if ($type !== null && get_resource_type($resource) !== $type) {
            return false;
        }

        return true;
    }

    /**
     * Validate if the provided file path is safe, exists, and is readable.
     *
     * @param string $path The file path to validate (relative or absolute).
     *
     * @return bool Returns true if the file is accessible and readable.
     */
    public static function isAccessible(string $path): bool
    {
        if (!self::isPathPermitted($path)) {
            return false;
        }

        $readable = is_file($path);
        if (!str_starts_with($path, '\\\\')) {
            $readable = $readable && is_readable($path);
        }
        return $readable;
    }

    /**
     * Verify if the file path follows the allowed format and is not a URL or PHP Archive (Phar).
     *
     * @param string $path The file path to check (relative or absolute).
     *
     * @return bool Returns true if the path is a valid local file path.
     */
    public static function isPathPermitted(string $path): bool
    {
        return !preg_match('#^[a-z][a-z\d+.-]*://#i', $path);
    }

    /**
     * Attempts to create the directory, if it doesn't exist.
     * 
     * @param string $path Directory path to create.
     * @param int $permissions Unix file permissions (default: 0777 fully accessible to all users).
     * @param bool $recursive Allows the creation of nested directories specified in the pathname (default: true).
     * 
     * @return bool Return true if files existed or was created else false.
     * @throws RuntimeException Throws if path is not readable.
     * @throws FileException Throws if unable to create directory.
     */
    public static function mkdir(string $path, int $permissions = 0777, bool $recursive = true): bool 
    {
        if($path === ''){
            return false;
        }
    
        if (!file_exists($path)) {
            error_clear_last();

            if(!@mkdir($path, $permissions, $recursive)){
                $error = error_get_last();
                FileException::handleDirectory(
                    $path, 
                    ($error === null) 
                        ? 'Could not create directory' 
                        : $error['message']
                );
                
                return false;
            }
            
            // Check if mkdir failed due to lack of write permission
            self::permission('rw', $path);
        }

        return true;
    }

    /**
	 * Copy files and folders from the source directory to the destination directory.
	 *
	 * @param string $origin The source directory.
	 * @param string $dest The destination directory.
     * @param int &$copied Reference to a variable to store the number of copied items.
	 *
	 * @return bool True if the copy operation is successful, false otherwise.
	 */
	public static function copy(string $origin, string $dest, int &$copied = 0): bool
	{
		if(!self::mkdir($dest, Files::$dirPermissions)){
            return false;
        }

		$dir = opendir($origin);

		if (!$dir) {
			return false;
		}

		while (false !== ($file = readdir($dir))) {
			if (($file !== '.') && ($file !== '..')) {
				$srcFile = $origin . DIRECTORY_SEPARATOR . $file;
				$destFile = $dest . DIRECTORY_SEPARATOR . $file;

				if (is_dir($srcFile)) {
					if(self::copy($srcFile, $destFile, $copied)){
                        $copied++;
                    }
				} elseif(copy($srcFile, $destFile)){
					$copied++;
				}

                usleep(10000);
			}
		}

		closedir($dir);

		return $copied > 0;
	}

    /**
     * Move files and folders from one location to another recursively.
     *
     * @param string $origin The source directory or file path.
     * @param string $dest The destination directory path.
     * @param int &$moved Reference to a variable to store the number of moved items.
     * 
     * @return bool True if the operation is successful, false otherwise.
     */
    public static function move(string $origin, string $dest, int &$moved = 0): bool
    {
        if(!self::mkdir($dest, Files::$dirPermissions)){
            return false;
        }

        $dir = opendir($origin);

        if (!$dir) {
            return false;
        }

        while (false !== ($file = readdir($dir))) {
            if ($file !== '.' && $file !== '..') {
                $srcFile = $origin . DIRECTORY_SEPARATOR . $file;
                $destFile = $dest . DIRECTORY_SEPARATOR . $file;

                if (is_dir($srcFile)) {
                    if (self::move($srcFile, $destFile, $moved)) {
                        $moved++;
                    }
                } elseif(rename($srcFile, $destFile)) {
                    $moved++;
                }

                usleep(10000);
            }
        }
        closedir($dir);

        if (@rmdir($origin)) {
            $moved++;
        }

        return $moved > 0;
    }

    /**
     * Download a file to the user's browser with optional delay between chunks.
     * 
     * @param string|resource $content The full file path, resource, or string to download.
     *      - File path - Download content from path specified.
     *      - Resource - Download content from resource specified.
     *      - String - Download content from string specified.
     * @param string|null $filename The filename as it will be shown in the download (e.g, `image.png`).
     * @param array<string,mixed> $headers Optional array headers for download.
     * @param bool $delete Whether to delete the file after download (default: false).
     * @param int $chunk_size The size of each chunk in bytes for large content (default: 8192, 8KB).
     * @param int $delay The delay between each chunk in microseconds (default: 0).
     * 
     * @return bool Return true on success, false on failure.
     */
    public static function download(
        mixed $content, 
        ?string $filename = null, 
        array $headers = [], 
        bool $delete = false, 
        int $chunk_size = 8192,
        int $delay = 0
    ): bool 
    {
        if (!$content) {
            return false;
        }

        $length = 0;
        $typeOf = null;

        if (is_resource($content)) {
            $typeOf = 'resource';
            $stat = fstat($content);
            if ($stat !== false) {
                $length = $stat['size'];
            }
        } elseif(str_contains($content, DIRECTORY_SEPARATOR) && is_readable($content)) {
            $typeOf = 'file';
            $filename ??= basename($content);
            $mime = get_mime($content)?: null;
            $length = filesize($content);
        } elseif(is_string($content)) {
            $typeOf = 'string';
            $length = strlen($content);
        }
        
        if (!$length) {
            return false;
        }

        // Files larger than 5MB are considered partial content
        $isPartial = $length > (5 * 1024 * 1024);
        $offset = 0;
        $filename ??= 'file_download';
        $mime ??= 'application/octet-stream';

        $headers = array_merge([
            'Connection' => 'close',
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Transfer-Encoding' => 'binary',
            'Accept-Ranges' => 'bytes',
            'Expires' => 0,
            'Cache-Control' => 'must-revalidate',
            'Pragma' => 'public',
            'Content-Length' => $length,
            'Content-Range' => "bytes 0-" . ($length - 1) . "/$length"
        ], $headers);

        if (isset($_SERVER['HTTP_RANGE'])) {
            [$length, $offset, $limit, $rangeHeader] = self::getHttpContentRange($length);
            
            if ($rangeHeader !== null) {
                $isPartial = true;
                $headers["Content-Range"] = $rangeHeader;
                $headers["Content-Length"] = $length;
                Header::sendStatus(206);
            }
        }

        if ($isPartial && ob_get_level()) {
            ob_end_clean();
        }

        Header::send($headers, false);

        if ($typeOf === 'file' || $typeOf === 'resource') {
            $handler = ($typeOf === 'file') ? fopen($content, 'rb') : $content;

            if (!$handler) {
                return false;
            }

            fseek($handler, $offset);
            if ($isPartial) {
                while (!feof($handler) && $length > 0) {
                    $read = min($chunk_size, $length);
                    echo fread($handler, $read);
                    $length -= $read;
                    ob_flush();
                    flush();
                    usleep($delay);
                }
            } else {
                fpassthru($handler);
            }

            if ($typeOf === 'file') {
                fclose($handler);

                if ($delete) {
                    unlink($content);
                }
            }

            return true;
        }

        if ($isPartial && $typeOf === 'string') {
            $start = $offset;
            $end = $length - 1;

            while ($start <= $end) {
                $chunk_size = min($chunk_size, $end - $start + 1);
                echo substr($content, $start, $chunk_size);
                $start += $chunk_size;
                ob_flush();
                flush();
                usleep($delay);
            }

            return true;
        }

        echo $content;
        return true;
    }

    /**
     * Handle byte-range requests from HTTP_RANGE.
     * 
     * @param int $filesize The original content length.
     * 
     * @return array<int,mixed> Return content length and range.
     *      - [length, offset, limit, rangeHeader].
     */
    public static function getHttpContentRange(int $filesize): array
    {
        $offset = 0;
        $length = $filesize;
        $header = null;
        $limit = $filesize - 1;
        $httpRange = $_SERVER['HTTP_RANGE'] ?? false;

        if ($httpRange && preg_match('/bytes=(\d+)-(\d+)?/', $httpRange, $matches)) {
            $offset = (int) $matches[1];
            $limit = isset($matches[2]) ? (int) $matches[2] : $filesize - 1;
            
            $limit = min($limit, $filesize - 1);
            $length = ($limit - $offset) + 1;
            $header = "bytes $offset-$limit/$filesize";
        }

        return [$length, $offset, $limit, $header];
    }

    /**
	 * Deletes files and folders recursively.
	 *
	 * @param string $location  Directory or file to delete.
	 * @param bool $delete_base  Remove the base directory once done (default is false).
     * @param int &$deleted Reference to a variable to store the number of deleted items.
     * 
	 * @return int Returns count of deleted files.
	 */
	public static function remove(string $location, bool $delete_base = false, int &$deleted = 0): int 
	{
		if (!file_exists($location)) {
			return $deleted;
		}
		
		$files = is_dir($location) ? 
			glob(rtrim($location, TRIM_DS) . DIRECTORY_SEPARATOR . '*', GLOB_MARK) : 
			glob($location . '*');

		foreach ($files as $file) {
			if (is_dir($file)) {
				self::remove($file, true, $deleted);
			} elseif(@unlink($file)){
				$deleted++;
			}
		}

        if($delete_base && is_dir($location) && @rmdir($location)){
            $deleted++;
        }

		return $deleted;
	}

    /**
     * Create a symlink to a target file or directory.
     *
     * @param string $target The targe file or directory to link from.
     * @param string  $link The location of the link.
     * 
     * @return bool Return true if the link was successfully created false otherwise.
     */
    public static function symbolic(string $target, string $link): bool
    {
        if (!file_exists($target)) {
            Logger::dispatch('alert', 'The symlink target file does not exist');
            return false;
        }

        $linkPath = dirname($link);
        if(!file_exists($linkPath) && !self::mkdir($linkPath, 0755)){
            Logger::dispatch('alert', 'Unable to create symlink destination directory');
            return false;
        }

        if(file_exists($link) || is_link($link)){
            @unlink($link);
        }
       
        error_clear_last();

        if (is_platform('windows')) {
            $mode = is_dir($target) ? 'D' : 'H';
            exec("mklink /{$mode} " . escapeshellarg($link) . ' ' . escapeshellarg($target), $output, $result);

            if($result === 1){
                Logger::dispatch('alert', 'Symlink creation failed:',[$output]);
            }

            return $result === 0;
        }
        
        if (symlink($target, $link) === false) {
            $error = error_get_last();
            Logger::dispatch(
                'alert', 
                ($error === null) 
                    ? 'Unknown error occurred while creating symlink.' 
                    : 'Symlink creation failed: ' . $error['message'] ?? ''
            );

            return false;
        }

        return true;
    }

    /**
     * Calculate the size of a given file or directory.
     *
     * @param string $path The path to the file or directory.
     * 
     * @return int Return the size in bytes.
     * @throws FileException If the path does not exist.
     */
    public static function size(string $path): int 
    {
        if (!file_exists($path)) {
            throw new FileException("The file does not exist: $path");
        }
    
        $size = 0;
        if(is_dir($path)){
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
        
            foreach ($files as $file) {
                $size += $file->getSize();
            }

            return $size;
        }

        $size = filesize($path);
        return ($size !== false) ? $size : 0;
    }

    /**
     * Reads binary files in chunks with a customizable delay.
     * 
     * @param resource $handler The file handler.
     * @param int $length The length of each chunk (default: 2MB in bytes).
     * @param int $delay The delay in microseconds between each chunk read (default: 1).
     * 
     * @return bool Return true if the file was successfully read, false otherwise.
     */
    public static function readBinary($handler, int $length = (1 << 21), int $delay = 0): bool
    {
        if(!is_resource($handler)){
            return false;
        }
  
        while (!feof($handler)) {
            $chunk = fread($handler, $length);

            if ($chunk === false) {
                fclose($handler);
                return false;
            }

            echo $chunk;
            flush();
            usleep($delay);

            if (connection_status() != CONNECTION_NORMAL) {
                break;
            }
        }

        fclose($handler);
        return true;
    }

    /**
     * Reads text-based files in chunks with a customizable delay while preserving line endings.
     * 
     * @param resource $handler The file handler.
     * @param int $filesize The size of the file.
     * @param int $length The length of each chunk (default: 2MB in bytes).
     * @param int $delay The delay in microseconds between each chunk read (default: 1).
     * 
     * @return bool Return true if the file was successfully read, false otherwise.
     */
    public static function readText($handler, int $filesize, int $length = (1 << 21), int $delay = 0): bool
    {
        if(!is_resource($handler)){
            return false;
        }

        if($filesize === 0){
            fclose($handler);
            return false;
        }

        $position = 0;
        while (!feof($handler)) {
            fseek($handler, $position);
            $chunk = fread($handler, $length);

            if ($chunk === false) {
                fclose($handler);
                return false;
            }

            $last = strrpos($chunk, "\n");
            if ($last === false) {
                $last = mb_strlen($chunk);
            }

            echo mb_substr($chunk, 0, $last);
            flush();

            $position += $last;

            if (($position + $length) > $filesize) {
                $length = $filesize - $position;
            }

            usleep($delay);
            if (connection_status() != CONNECTION_NORMAL) {
                break;
            }
        }

        fclose($handler);
        return true;
    }

    /**
     * Reads a file in chunks with type-specific handling and customizable delay, for optimized performance.
     * For text-based files, it reads while preserving line endings. For binary files, it reads in raw chunks.
     * 
     * @param resource $handler The file handler.
     * @param int $filesize The total size of the file.
     * @param string|null $mime The MIME type of the file (degault: null).
     * @param int $length The size of each chunk to be read (default: 2MB).
     * @param int $delay The delay in microseconds between chunk reads (default: 0).
     * 
     * @return bool Returns true if the file was successfully read, false otherwise.
     */
    public static function read(
        $handler, 
        int $filesize, 
        ?string $mime = null, 
        int $length = (1 << 21),
        int $delay = 0
    ): bool
    {
        $result = false;

        if ($filesize > 0 && is_resource($handler)) {
            if ($filesize > 5 * 1024 * 1024) {
                $result = ($mime && (str_starts_with($mime, 'text/') || preg_match('/^(application\/(?:json|xml|javascript)|image\/svg\+xml)$/i', $mime)))
                    ? self::readText($handler, $filesize, $length, $delay)
                    : self::readBinary($handler, $length, $delay);
            }else{
                $result = fpassthru($handler) > 0;
            }
        }

        if($handler && is_resource($handler)){
            fclose($handler);
        }

        return $result;
    }

    /**
     * Get path properties in compatible based on os return from application root.
     * 
     * @param string $name File property name.
     * 
     * @return string Return compatible path based on operating system.
     * @ignore
     */
    public function getCompatible(string $name): string 
    {
        return property_exists($this, $name) ? root($this->{$name}) : '';
    } 
    
    /**
     * Convert file path be compatible based on os.
     * 
     * @param string $path File path
     * 
     * @return string Return compatible path based on operating system.
     * @ignore
     */
    public static function toCompatible(string $path): string 
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    } 

    /**
     * Get protected properties path.
     * 
     * @param string $key
     * 
     * @return string Return compatible path based on operating system.
     * @ignore
     */
    public function __get(string $key): string 
    {
        return $this->getCompatible($key);
    }
}