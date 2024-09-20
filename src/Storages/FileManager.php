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
            logger('critical', sprintf($error, $file));
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
            if (($error = error_get_last()) !== null) {
                FileException::handlePermission($location, $error['message']);
            }

            return false;
        }

        return true;
    }

    /**
     * Converts file permission code to its string representation
     * 
     * @param int $permission Permission code
     * 
     * @return string Permission string representation.
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
     * @return string|int|null The Unix integer permission, or null if the conversion failed.
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
     * 
     * @return string|false Returns the contents of the file as a string, or `false` on failure.
     * 
     * @throws FileException If an error occurs while opening or reading the file.
     */
    public static function getContent(
        string $filename, 
        int $length = 0, 
        int $offset = 0, 
        bool $useInclude = false, 
        $context = null
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
     * @return bool True if the operation was successful, false otherwise.
     * @throws FileException If unable to write to the file.
     */
    public static function write(string $filename, mixed $content, int $flags = 0, $context = null): bool
    {
        if ($filename === '' || $content === '' || $content === null) {
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
     * 
     * @return bool True if the operation was successful, false otherwise.
     * @throws FileException If unable to write to the file.
     */
    public static function stream(string $filename, mixed $resource, int $flags = 0, mixed $context = null): bool
    {
        if ($filename === '' || $resource === null) {
            return false;
        }

        if (!static::isResource($resource, 'stream')) {
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
     * Attempts to create the directory, if it doesn't exist.
     * 
     * @param string $path Directory path to create.
     * @param int $permissions Unix file permissions
     * @param bool $recursive Allows the creation of nested directories specified in the pathname (default: true).
     * 
     * @return bool true if files existed or was created else false
     * @throws RuntimeException If path is not readable.
     * @throws FileException If unable to create directory
    */
    public static function mkdir(string $path, int $permissions = 0777, bool $recursive = true): bool 
    {
        if($path === ''){
            return false;
        }
    
        if (!file_exists($path)) {
            error_clear_last();

            if(!@mkdir($path, $permissions, $recursive)){
                if (($error = error_get_last()) !== null) {
                    FileException::handleDirectory($path, $error['message']);
                }
                
                return false;
            }
            
            // Check if mkdir failed due to lack of write permission
            static::permission('rw', $path);
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
		if(!make_dir($dest)){
            return false;
        }

		$dir = opendir($origin);

		if (!$dir) {
			return false;
		}

		while (false !== ($file = readdir($dir))) {
			if (($file != '.') && ($file != '..')) {
				$srcFile = $origin . DIRECTORY_SEPARATOR . $file;
				$destFile = $dest . DIRECTORY_SEPARATOR . $file;

				if (is_dir($srcFile)) {
					if(static::copy($srcFile, $destFile, $copied)){
                        $copied++;
                    }
				} elseif(copy($srcFile, $destFile)){
					$copied++;
				}
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
        if(!make_dir($dest)){
            return false;
        }

        $dir = opendir($origin);

        if (!$dir) {
            return false;
        }

        while (false !== ($file = readdir($dir))) {
            if ($file != '.' && $file != '..') {
                $srcFile = $origin . DIRECTORY_SEPARATOR . $file;
                $destFile = $dest . DIRECTORY_SEPARATOR . $file;

                if (is_dir($srcFile)) {
                    if (static::move($srcFile, $destFile, $moved)) {
                        $moved++;
                    }
                } elseif(rename($srcFile, $destFile)) {
                    $moved++;
                }
            }
        }
        closedir($dir);

        if (rmdir($origin)) {
            $moved++;
        }

        return $moved > 0;
    }

    /**
     * Download a file to the user's browser.
     *
     * @param mixed $content The full file path, resource, or string to download.
     *      - File path - Download content from path specified.
     *      - Resource - Download content from resource specified.
     *      - String - Download content from string specified.
     * @param string|null $filename The filename as it will be shown in the download.
     * @param array $headers Optional headers for download.
     * @param bool $delete Whether to delete the file after download (default: false).
     * 
     * @return bool Return true on success, false on failure.
     */
    public static function download(mixed $content, ?string $filename = null, array $headers = [], bool $delete = false): bool
    {
        if (!$content) {
            return false;
        }

        $length = 0;
        $typeOf = '';

        if (is_resource($content)) {
            $typeOf = 'resource';
            $stat = fstat($content);
            if ($stat !== false) {
                $length = $stat['size'];
            }
        } elseif (str_contains($content, DIRECTORY_SEPARATOR) && is_readable($content)) {
            $typeOf = 'file';
            $filename ??= basename($content);
            $mime = get_mime($content);
            $length = filesize($content);
            if ($mime === false) {
                $mime = null;
            }
        } elseif (is_string($content)) {
            $typeOf = 'string';
            $length = strlen($content);
        }
        
        if (!$length) {
            return false;
        }

        $bufferSize = 8192; // 8KB buffer size
        $isPartialContent = $length > (5 * 1024 * 1024);
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
                $isPartialContent = true;
                $headers["Content-Range"] = $rangeHeader;
                $headers["Content-Length"] = $length;
                http_response_code(206);
            }
        }

        if ($isPartialContent && ob_get_level()) {
            ob_end_clean();
        }

        foreach ($headers as $key => $value) {
            header("{$key}: {$value}");
        }

        if ($typeOf === 'file' || $typeOf === 'resource') {
            $handler = ($typeOf === 'file') ? fopen($content, 'rb') : $content;

            if (!$handler) {
                return false;
            }

            fseek($handler, $offset);
            if ($isPartialContent) {
                while (!feof($handler) && $length > 0) {
                    $read = min($bufferSize, $length);
                    echo fread($handler, $read);
                    $length -= $read;
                    ob_flush();
                    flush();
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

        if ($isPartialContent && $typeOf === 'string') {
            $start = $offset;
            $end = $length - 1;
            while ($start <= $end) {
                $bufferSize = min($bufferSize, $end - $start + 1);
                echo substr($content, $start, $bufferSize);
                $start += $bufferSize;
                ob_flush();
                flush();
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
				static::remove($file, true, $deleted);
			} else {
				unlink($file);
				$deleted++;
			}
		}

        if($delete_base && is_dir($location) &&  rmdir($location)){
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
            logger('alert', 'The symlink target file does not exist');
            return false;
        }

        $linkPath = dirname($link);
        if(!file_exists($linkPath) && !make_dir($linkPath, 0755)){
            logger('alert', 'Unable to create symlink destination directory');
            return false;
        }

        if(file_exists($link) || is_link($link)){
            unlink($link);
        }
       
        error_clear_last();

        if (is_platform('windows')) {
            $mode = is_dir($target) ? 'D' : 'H';
            exec("mklink /{$mode} " . escapeshellarg($link) . ' ' . escapeshellarg($target), $output, $result);

            if($result === 1){
                logger('alert', 'Symlink creation failed:',[$output]);
            }

            return $result === 0;
        }
        
        if (symlink($target, $link) === false) {
            $error = error_get_last();
            if ($error !== null) {
                logger('alert', 'Symlink creation failed: ' . $error['message'] ?? '');
            } else {
                logger('alert', 'Unknown error occurred while creating symlink.');
            }

            return false;
        }

        return true;
    }

    /**
     * Calculate the size of a given file or directory.
     *
     * This method calculates the size of a given file or recursively calculates the
     * total size of all files within a directory. If the path does not exist, it
     * throws a FileException.
     *
     * @param string $path The path to the file or directory.
     * 
     * @return int The size in bytes.
     * @throws FileException If the path does not exist.
     */
    public static function size(string $path): int 
    {
        if (!file_exists($path)) {
            throw new FileException("The file does not exist: $path");
        }
    
        $size = 0;
        if(is_dir($path)){
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS));
        
            foreach ($files as $file) {
                $size += $file->getSize();
            }

            return $size;
        }

        $size = filesize($path);
        return $size !== false ? $size : 0;
    }

    /**
     * Reads binary files in chunks.
     * 
     * @param resource $handler The file handler.
     * @param int $length The length of each chunk (default: 2MB in bytes).
     * 
     * @return bool True if the file was successfully read, false otherwise.
     */
    public static function readBinary($handler, int $length = (1 << 21)): bool
    {
        while (!feof($handler)) {
            $chunk = fread($handler, $length);

            if ($chunk === false) {
                fclose($handler);
                return false;
            }

            echo $chunk;
            flush();

            if (connection_status() != CONNECTION_NORMAL) {
                break;
            }
        }

        fclose($handler);
        return true;
    }

    /**
     * Reads text-based files in chunks while preserving line endings.
     * 
     * @param resource $handler The file handler.
     * @param int $filesize The size of the file.
     * @param int $length The length of each chunk (default: 2MB in bytes).
     * 
     * @return bool True if the file was successfully read, false otherwise.
     */
    public static function readText($handler, int $filesize, int $length = (1 << 21)): bool
    {
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

            if (connection_status() != CONNECTION_NORMAL) {
                break;
            }
        }

        fclose($handler);
        return true;
    }

    /**
     * Reads a file in chunks, optimizing for efficiency and type-specific handling.
     * 
     * @param resource $handler The file handler.
     * @param int $filesize The size of the file.
     * @param string $mime The MIME type of the file.
     * @param int $length The length of each chunk (default: 2MB).
     * 
     * @return bool Return true if the file was successfully read, false otherwise.
     */
    public static function read($handler, int $filesize, string $mime, int $length = (1 << 21)): bool
    {
        if ($filesize === 0) {
            fclose($handler);
            return false;
        }

        if ($filesize > 5 * 1024 * 1024) {
            if (preg_match('/^(text\/.*|application\/json|application\/xml)$/i', $mime)) {
                return self::readText($handler, $filesize, $length);
            }
            
            return self::readBinary($handler, $length);
        }

        $read = fpassthru($handler);
        fclose($handler);
        return $read > 0;
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
        if (property_exists($this, $name)) {
            return root($this->{$name});
        }
    
        return '';
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