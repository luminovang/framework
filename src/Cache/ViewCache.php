<?php
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Cache;

use \Luminova\Http\Header;
use \Luminova\Time\Timestamp;
use \Luminova\Storages\FileManager;
use \DateTimeInterface;

final class ViewCache
{
    /**
     * The directory where cached files will be stored.
     * 
     * @var string $directory 
     */
    private string $directory;

    /**
     * The expiration time for cached 
     * 
     * @var int $expiration 
     */
    private int $expiration;

    /**
     * @var string $key Cache key
     */
    private string $key;

    /**
     * @var array $lockFile Lock files.
    */
    private array $lockFile = [];

    /**
     * @var string $lockFunc Lock function name.
    */
    private string $lockFunc = '';

    /**
     * Class constructor.
     *
     * @param DateTimeInterface|int $expiration The expiration time for cached files in seconds (default: 0).
     * @param string $directory The directory where cached files will be stored (default: 'cache').
     */
    public function __construct(DateTimeInterface|int $expiration = 0, string $directory = 'cache')
    {
        $this->directory = $directory;
        $this->setExpiry($expiration);
    }

    /**
     * Set cache expiration in seconds.
     *  
     * @param DateTimeInterface|int $expiration Expiry
     * 
     * @return self Return class instance.
    */
    public function setExpiry(DateTimeInterface|int $expiration): self
    {
        $this->expiration = is_int($expiration) ? $expiration : Timestamp::ttlToSeconds($expiration);

        return $this;
    }

    /**
     * Set cache directory.
     * 
     * @param string $directory The directory where cached files will be stored (default: 'cache').
     * 
     * @return self Return class instance.
    */
    public function setDirectory(string $directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Set the cache key.
     *
     * @param string $key The key to set.
     *
     * @return self Return class instance.
     */
    public function setKey(string $key): self
    {
        $this->key = $key;
        $this->lockFunc = '__lmv_template_cache_lock_' . md5($key . 'cache_lock_function');
        return $this;
    }

    /**
     * Get the cache key.
     *
     * @return string The cache key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the file path for the cache based on the current request URI.
     * 
     * @return string The file path for the cache.
    */
    public function getFilename(): string
    {
        return $this->getLocation() . $this->key . '.lmv.php';
    }

    /**
     * Get the cache directory path.
     *
     * @return string The cache directory path.
     */
    public function getLocation(): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Check if file was cached.
     *
     * @return bool True if the cache is still valid; false otherwise.
     */
    public function has(): bool
    {
        return file_exists($this->getFilename());
    }

    /**
     * Check if the cached content has expired.
     * 
     * @param string|null $type The type of cached content to check (default: null).
     * 
     * @return bool|int Return true if the cache is expired, otherwise false.
     */
    public function expired(?string $type = null): bool|int
    {
        // Initialize and validate the cache
        if (!$this->init() || $this->lockFile === []) {
            return true;
        }

        // If content extension type doesn't match with cached version.
        if($type !== null && ($this->lockFile['viewType']??'') !== $type){
            return 404;
        }

        // Check if cache has expired
        return time() >= (int) ($this->lockFile['Expiry'] ?? 0);
    }

    /**
     * Load and initialize the cache file.
     * 
     * @return bool Return true if the cache initialized, false otherwise.
    */
    private function init(): bool
    {
        $location = $this->getFilename();
        $this->lockFile = [];

        if(file_exists($location)){
            include_once $location;
            $func = $this->lockFunc;
            if(function_exists($func) && ($lock = $func($this->key)) !== false){
                $this->lockFile = $lock;
                return true;
            }
        }

        return false;
    }

    /**
     * Delete a cache entry.
     * 
     * @return bool Return true if the cache entry was deleted, false otherwise.
    */
    public function delete(): bool 
    {
        return unlink($this->getFilename());
    }

    /**
     * Clear all cache entries.
     * 
     * @return int Return number of deleted caches.
    */
    public function clear(): int 
    {
        return FileManager::remove($this->getLocation());
    }

    /**
     * Load the content from the cache file and exit the script.
     * 
     * @param string|null $type The type of cached content to check (default: null).
     * 
     * @return bool|int Return true if loading was successful, if miss-matched type return int 404, otherwise false.
    */
    public function read(?string $type = null): bool|int
    {
        if($this->lockFile === [] && $this->init() === false){
            Header::headerNoCache(404);
            return false;
        }

        if($type !== null && ($this->lockFile['viewType']??'') !== $type){
            return 404;
        }

        $func = ($this->lockFile['Func']??false);
        if ($func && function_exists($func)) {
            $headers = ['default_headers' => true];
            $headers['ETag'] =  '"' . $this->lockFile['ETag'] . '"';
            $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T',  $this->lockFile['Expiry']);
            $headers['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T',  $this->lockFile['Modify']);
            $headers['Cache-Control'] = 'public, max-age=' . $this->lockFile['MaxAge'];
            $headers['ETag'] =  '"' . $this->lockFile['ETag'] . '"';
    
            $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH']??'');
            // Check if the ETag matches the client's If-None-Match header
            if ($ifNoneMatch !== '' && $ifNoneMatch === $headers['ETag']) {
                Header::parseHeaders($headers, 304);
                return true;
            }

            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && ($modify = $this->lockFile['Modify']) !== false) {
                if (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $modify) {
                    Header::parseHeaders($headers, 304);
                    return true;
                }
            }

            $headers['Content-Type'] = ($this->lockFile['Content-Type'] ?? Header::getContentTypes($this->lockFile['viewType']));
            if(isset($this->lockFile['Content-Encoding'])){
                $headers['Content-Encoding'] = $this->lockFile['Content-Encoding'];
            }

            Header::parseHeaders($headers);
            echo $this->lockFile['Func']();
            return true;
        }

        Header::headerNoCache(404);
        return false;
    }

    /**
     * Get the content from the cache filet.
     * 
     * @param string|null $type The type of cached content to check (default: null).
     * 
     * @return string|null Return cached contents, null otherwise.
    */
    public function get(?string $type = null): ?string
    {
        if($this->lockFile === [] && $this->init() === false){
            return null;
        }

        if($type !== null && ($this->lockFile['viewType']??'') !== $type){
            return 404;
        }

        if (isset($this->lockFile['Func']) && function_exists($this->lockFile['Func'])) {
            ob_start();
            echo $this->lockFile['Func']();
            return ob_get_clean();
        }
        
        return null;
    }

    /**
     * Save the content to the cache file.
     *
     * @param string $content The content to be saved to the cache file.
     * @param array $headers Cache headers.
     * @param string $type Cache content type.
     *
     * @return bool True if saving was successful; false otherwise.
     */
    public function saveCache(string $content, array $headers = [], string $type = 'html'): bool
    {
        make_dir($this->getLocation());     

        $headers['Content-Encoding'] =  static::whichEncode();
        $headers['viewType'] = $type;
        $headers['MaxAge'] = $this->expiration;
        $headers['Expiry'] = time() + $this->expiration;
        $headers['Date'] = date('D, d M Y H:i:s \G\M\T');
        $headers['Modify'] = time();
        $headers['ETag'] = md5($content);
        $headers['Func'] = '__lmv_template_content_' . $this->key;

        $locks = [];
        $locks[$this->key] = $headers;

        $pageContent = "<?php function {$this->lockFunc}(string \$key): array|bool {\n";
        $pageContent .= "    \$lock = " . var_export($locks, true) . ";\n";
        $pageContent .= "    return \$lock[\$key]??false;\n";
        $pageContent .= "}?>\n";
        $pageContent .= "<?php function __lmv_template_content_{$this->key}():void { ?>\n";
        $pageContent .= $content;
        $pageContent .= "<?php }?>\n";

        if(write_content($this->getFilename(), $pageContent)){
            return true;
        }

        return false;
    }

    /**
     * Determine the content encoding
     * 
     * @return string|false Return the content encoding handler, otherwise false.
    */
    private static function whichEncode(): string|bool
    {
        $encoding = env('compression.encoding', false);
        if ($encoding !== false) {
            if (isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
                return $_SERVER['HTTP_CONTENT_ENCODING'];
            }
            
            if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
                $encoding = strtolower($encoding);
                if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], $encoding) !== false) {
                    return $encoding;
                }
            }
        }
        
        return false;
    }
}