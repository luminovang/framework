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

final class PageViewCache
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
        return $this->getLocation() . $this->key . '.html.php';
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
     * Check if the cached has expired.
     * 
     * @return bool True if the cache is still valid; false otherwise.
    */
    public function expired(): bool
    {
        $location = $this->getFilename();
        $this->lockFile = [];

        if(file_exists($location)){
            include_once $location;
            $func = $this->lockFunc;
            if(function_exists($func) && ($lock = $func($this->key)) !== false){
                if(time() >= (int) ($lock['Expiry'] ?? 0)){
                    return true;
                }

                $this->lockFile = $lock;
                return false;
            }
        }

        return true;
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
     * @return bool True if loading was successful; false otherwise.
    */
    public function read(): bool
    {
        $headers = ['default_headers' => true];
    
        if (isset($this->lockFile['Func']) && function_exists($this->lockFile['Func'])) {
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
     * @return string|null Return cached contents, null otherwise.
    */
    public function get(): ?string
    {
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

        $pageContent = "<?php function {$this->lockFunc}(string \$key): array|false {\n";
        $pageContent .= "    \$lock = " . var_export($locks, true) . ";\n";
        $pageContent .= "    return \$lock[\$key]??false;\n";
        $pageContent .= "}?>\n";
        $pageContent .= "<?php function __lmv_template_content_{$this->key}():void { ?>\n";
        $pageContent .= $content;
        $pageContent .= "<?php }?>\n";

        if(!write_content($this->getFilename(), $pageContent)){
            return false;
        }

        return true;
    }

    /**
     * Determin the content encoding
     * 
     * @return string|false Return the content encoding handler, otherwise false.
    */
    private static function whichEncode(): string|false
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