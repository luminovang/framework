<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Application;

use \Luminova\Exceptions\FileException;

class FileSystem
{
    /**
     * @var string $system
    */
    protected string $system =  'system/';
    
    /**
     * @var string $systemPlugins
    */
    protected string $plugins = 'system/plugins/';

    /**
     * @var string $systemPlugins
    */
    protected string $library = 'libraries/libs/';

    /**
     * @var string $systemPlugins
    */
    protected string $services = 'writeable/services/';

    /**
     * @var string $controllers
    */
    protected string $controllers =  'app/Controllers/';

    /**
     * @var string $writeable
     */
    protected string $writeable =  'writeable/';
    
    /**
     * @var string $logs
     */
    protected string $logs =  'writeable/log/';

    /**
     * @var string $caches
     */
    protected string $caches =  'writeable/caches/';

    /**
     * @var string $public 
     */
    protected string $public = 'public/';

     /**
     * @var string $assets
     */
    protected string $assets = 'public/assets/';

    /**
     * @var string $views
     */
    protected string $views =  'resources/views/';

     /**
     * @var string $routes
     */
    protected string $routes =  'routes/';

     /**
     * @var string $languages
     */
    protected string $languages =  'app/Controllers/Languages/';


    /**
     * Check if file read and write permission is granted.
     * 
     * @param string $permission File access permission.
     * @param string|null $file File name or file path to check permissions.
     * @param bool $quiet Indicate whether to throws an exception if permission is not granted.
     * 
     * @return bool Returns true if permission is granted otherwise false.
     * @throws FileException If permission is not granted and quiet is not passed true.
     */
    public static function permission(string $permission = 'rw', ?string $file = null, bool $quiet = false): bool
    {
        $file ??= static::trimPath('writeable/');

        if ($permission === 'rw' && (!is_readable($file) || !is_writable($file))) {
            $error = "Read and Write permission denied for '{$file}', please grant 'read' and 'write' permission.";
        } elseif ($permission === 'r' && !is_readable($file)) {
            $error = "Read permission denied for '{$file}', please grant 'read' permission.";
        } elseif ($permission === 'w' && !is_writable($file)) {
            $error = "Write permission denied for '{$file}', please grant 'write' permission.";
        } else {
            return true;
        }

        if ($quiet) {
            return false;
        }

        throw new FileException($error);
    }

    public static function setPermission(string $location, int $permission): bool
    {
        error_clear_last();
        if ( ! @chmod($location, $permission)) {
            FileException::handlePermission($location, (error_get_last()['message'] ?? ''));
            
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
        $symbolic .= ($permission & 0x0100) ? 'r' : '-';
        $symbolic .= ($permission & 0x0080) ? 'w' : '-';
        $symbolic .= ($permission & 0x0040) ?
            (($permission & 0x0800) ? 's' : 'x') :
            (($permission & 0x0800) ? 'S' : '-');

        // Group permissions
        $symbolic .= ($permission & 0x0020) ? 'r' : '-';
        $symbolic .= ($permission & 0x0010) ? 'w' : '-';
        $symbolic .= ($permission & 0x0008) ?
            (($permission & 0x0400) ? 's' : 'x') :
            (($permission & 0x0400) ? 'S' : '-');

        // Other permissions
        $symbolic .= ($permission & 0x0004) ? 'r' : '-';
        $symbolic .= ($permission & 0x0002) ? 'w' : '-';
        $symbolic .= ($permission & 0x0001) ?
            (($permission & 0x0200) ? 't' : 'x') :
            (($permission & 0x0200) ? 'T' : '-');

        return $symbolic;
    }


    /**
     * Write, append contents to file.
     * @param string $filename — Path to the file where to write the data.
     * @param mixed $content
     * @param int $flags [optional] The value of flags can be any combination of the following flags (with some restrictions), joined with the binary OR (|) operator.
     * @param resource $context [optional] A valid context resource created with stream_context_create.
     * 
     * @return bool true or false on failure.
     * @throws FileException If unable to write file.
    */
    public static function write(string $filename, mixed $content, int $flag = 0, $context = null): bool 
    {
        if(empty($filename)){
            return false;
        }

        error_clear_last();
        $handler = false;
        $lock = $flag & (LOCK_EX | LOCK_NB | LOCK_SH | LOCK_UN);
        if(!$lock){
            $include = $flag & FILE_USE_INCLUDE_PATH;
            $mode = $flag & FILE_APPEND ? 'a' : 'w';
            $handler = @fopen($filename, $mode, $include, $context);
        }
        
        if ($handler === false) {
            $result = @file_put_contents($filename, $content, $flag, $context);
        }else{
            $result = @fwrite($handler, $content);
            @fclose($handler);
        }

        if ($result === false) {
            FileException::handleFile($filename, (error_get_last()['message'] ?? ''));

            return false;
        }

        return true;
    }

    /**
     * Attempts to create the directory specified by pathname if not exist.
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
        if(empty($path)){
            return false;
        }
    
        if (!file_exists($path)) {
            error_clear_last();

            if(! @mkdir($path, $permissions, $recursive)){
                FileException::handleDirectory($path, (error_get_last()['message'] ?? ''));
                
                return true;
            }
            
            // Check if mkdir failed due to lack of write permission
            static::permission('rw', $path);
        }

        return true;
    }

    /**
     * Trim path based on os.
     * 
     * @param string $path 
     * 
     * @return string $path 
    */
    private static function trimPath(string $path): string 
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        return root(__DIR__, $path);
    }

    /**
     * Get protected properties path.
     * 
     * @param string $key
     * 
     * @return string $path 
    */
    public function __get(string $key): string 
    {
        $path = $this->{$key} ?? '';

        if($path === ''){
            return '';
        }

        return static::trimPath($path);
    }
}