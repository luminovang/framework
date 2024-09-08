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
     * The expiration time for cached.
     * 
     * @var int|null $expiration 
     */
    private int|null $expiration = 0;

    /**
     * @var string $key Cache key.
     */
    private string $key = '';

    /**
     * @var array|null $lockFile Lock files.
    */
    private static ?array $lockFile = null;

    /**
     * @var string $lockFunc Lock function name.
    */
    private string $lockFunc = '';

    /**
     * The found cache filename and version.
     * 
     * @var string|null $foundCacheLocation
    */
    private static ?string $foundCacheLocation = null;

    /**
     * Class page cache constructor.
     *
     * @param DateTimeInterface|int $expiration The expiration time for cached files in seconds (default: 0).
     * @param string $directory The directory where cached files will be stored (default: 'cache').
     * @param int|null $modified The last modified of content (default: 'null').
     */
    public function __construct(
        DateTimeInterface|int|null $expiration = 0, 
        private string $directory = 'cache',
        private ?string $filename = null
    )
    {
        $this->setExpiry($expiration);
    }

    /**
     * Set cache expiration in seconds.
     *  
     * @param DateTimeInterface|int|null $expiration The cache expiration.
     *          If set to 0, the content will never expire.
     *          If set to null, the content will expire immediately.
     * 
     * @return self Return class instance.
    */
    public function setExpiry(DateTimeInterface|int|null $expiration): self
    {
        $this->expiration = ($expiration instanceof DateTimeInterface) ? Timestamp::ttlToSeconds($expiration) : $expiration;

        return $this;
    }

    /**
     * Set he filename to extract metadata.
     *  
     * @param string $filename The file to extract metadata.
     * 
     * @return self Return class instance.
    */
    public function setFile(string $filename): self
    {
        $this->filename = $filename;
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
        $this->lockFunc = '__lmv_template_cache_lock_' . md5($this->key . 'cache_lock_function');
        return $this;
    }

    /**
     * Get the cache key.
     *
     * @return string Return the cache key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the current version cache filename and path.
     * 
     * @param string|null Optionally specify application version to retrieve (default: null).
     * 
     * @return string Return the file path for the cache.
    */
    public function getFilename(?string $version = null): string
    {
        return $this->getLocation() . ($version ?? APP_VERSION) . DIRECTORY_SEPARATOR . $this->key . '.lmv.php';
    }

    /**
     * Get the cache directory path.
     *
     * @return string Return the cache directory path.
     */
    public function getLocation(): string
    {
        return rtrim($this->directory, TRIM_DS) . DIRECTORY_SEPARATOR;
    }

    /**
     * Check if file was cached for the current version.
     *
     * @return bool Return true if the cache is still valid; false otherwise.
     */
    public function has(): bool
    {
        return file_exists($this->getFilename());
    }

    /**
     * Check if the cached content has expired.
     * This method return false if not expired, but if the view type is mismatched it return integer 404.
     * 
     * @param string|null $type The type of cached content to check (default: null).
     * 
     * @return bool|int Return true if the cache is expired, otherwise false.
     */
    public function expired(?string $type = null): bool|int
    {
        // Validate initialization and ensure lockFile is not empty
        if (!$this->init() || self::$lockFile === null) {
            return true;
        }

        /// Check if content type matches the cached version
        if($type !== null && (self::$lockFile['viewType'] ?? '_') !== $type){
            return 404;
        }

        // Check if cache has expired
        $expiration = (int) (self::$lockFile['Expiry'] ?? 0);
     
        return ($expiration === 0 || (self::$lockFile['CacheImmutable'] ?? false) === true) 
            ? false 
            : time() >= $expiration;
    }

    /**
     * Delete a cache entry.
     * 
     * @param string|null Optionally specify application version to delete (default: null).
     * 
     * @return bool Return true if the cache entry was deleted, false otherwise.
    */
    public function delete(?string $version = null): bool 
    {
        $filename = $this->getFilename($version);
        return file_exists($filename) && unlink($filename);
    }

    /**
     * Clear all cache entries.
     * 
     * @param string|null Optionally specify application version to clear (default: null).
     * 
     * @return int Return number of deleted caches.
    */
    public function clear(?string $version = null): int 
    {
        return FileManager::remove($this->getLocation() . ($version ?? APP_VERSION) . DIRECTORY_SEPARATOR);
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
        if(self::$lockFile === [] && $this->init() === false){
            Header::headerNoCache(404);
            return false;
        }

        // Check if the view type matches the cached content view type.
        if($type !== null && (self::$lockFile['viewType'] ?? '_') !== $type){
            return 404;
        }

        $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        $eTag = self::$lockFile['ETag'] ?? null;
    
        // Check if the ETag matches the client's If-None-Match header
        if ($eTag && $ifNoneMatch === "\"$eTag\"") {
            Header::parseHeaders($this->getHeaders(), 304);
            return true;
        }

        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
        $lasModify = self::$lockFile['Modify'] ?? null;

        // Check if modify since matches the client's If-Modified-Since header
        if ($ifModifiedSince && $lasModify && strtotime($ifModifiedSince) >= $lasModify) {
            Header::parseHeaders($this->getHeaders(), 304);
            return true;
        }

        Header::parseHeaders($this->getHeaders());
        echo self::$lockFile['Func']();
        return true;
    }

    /**
     * Get the content from the cache filet.
     * 
     * @param string|null $type The type of cached content to check (default: null).
     * 
     * @return string|int|false|null Return cached contents, 404 for mismatched, null otherwise.
    */
    public function get(?string $type = null): string|int|bool|null
    {
        if(self::$lockFile === [] && $this->init() === false){
            return ($type === null) ? false : null;
        }

        if($type !== null && (self::$lockFile['viewType'] ?? '_') !== $type){
            return 404;
        }

        ob_start();
        echo self::$lockFile['Func']();
        return ob_get_clean();
    }

    /**
     * Save the content to the cache file.
     *
     * @param string $content The content to be saved to the cache file.
     * @param array $headers Additional view headers.
     * @param string $type The view content type.
     *
     * @return bool Return true if saving was successful; false otherwise.
     */
    public function saveCache(string $content, array $headers = [], string $type = 'html'): bool
    {
        $path = $this->getLocation() . APP_VERSION . DIRECTORY_SEPARATOR;

        if($this->expiration === null || !make_dir($path)){
            return false;
        }

        // Clear file stat cache for the specific file if filename exists
        if ($this->filename) {
            @clearstatcache(true, $this->filename);
        }

        $now = time();
        $fileSize = $this->filename ? @filesize($this->filename) : mb_strlen($content);
        $fileMTime = $this->filename ? @filemtime($this->filename) : $now;
        $expiration = ($this->expiration === 0) ? 31536000 * 5 : $this->expiration;

        $locks = [$this->key => [
            'viewType' => $type,
            'MaxAge' => $expiration,
            'TTL' => $this->expiration,
            'CacheImmutable' => env('page.caching.immutable', false),
            'AppVersion' => APP_VERSION,
            'Expiry' => $now + $expiration,
            'Date' => gmdate('D, d M Y H:i:s \G\M\T'),
            'Modify' => $fileMTime,
            'Size' => $fileSize,
            'Func' => '__lmv_template_content_' . $this->key,
            'ETag' => md5("eTag_{$type}_{$fileMTime}_{$fileSize}" . APP_VERSION),
            'Headers' =>  array_merge([
                'Content-Encoding' => static::whichEncode(),
                'Content-Type' => Header::getContentTypes($type)
            ], $headers)
        ]];

        $line = "<?php function {$this->lockFunc}(string \$key): array|bool {\n";
        $line .= "    \$lock = " . var_export($locks, true) . ";\n";
        $line .= "    return \$lock[\$key]??false;\n";
        $line .= "}?>\n";
        $line .= "<?php function __lmv_template_content_{$this->key}():void { ?>\n";
        $line .= $content;
        $line .= "\n<?php } ?>\n";

        return FileManager::write($path . $this->key . '.lmv.php', $line);
    }

    /**
     * Load and initialize the cache file.
     * 
     * @return false Return true if cache is found and valid, false otherwise.
     */
    private function init(): bool
    {
        self::$lockFile = [];
        self::$foundCacheLocation = null;
        
        return $this->findCache() && $this->isCacheValid();
    }

    /**
     * Search for cached version in current and passed version.
     * 
     * @return bool Return true if cache found, false otherwise.
     */
    private function findCache(): bool
    {
        $filename = $this->key . '.lmv.php';
        $path = $this->getLocation();

        if(file_exists($file = $path . APP_VERSION . DIRECTORY_SEPARATOR . $filename)){
            self::$foundCacheLocation = $file;
            return true;
        }

        if(($past = env('page.cache.app.versions', [])) !== []){
            foreach($past as $version){
                if(file_exists($file = $path . $version . DIRECTORY_SEPARATOR . $filename)){
                    self::$foundCacheLocation = $file;
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Validate found cached to see if function key matched.
     * 
     * @return bool Return true if cache is valid, false otherwise.
     */
    private function isCacheValid(): bool
    {
        if(self::$foundCacheLocation === null){
            return false;
        }

        include_once self::$foundCacheLocation;
        $func = $this->lockFunc;
        if(function_exists($func) && ($lock = $func($this->key)) !== false){
            self::$lockFile = $lock;
            return true;
        }

        return false;
    }

    /**
     * Get cache headers to server.
     * 
     * @return array<string,mixed> Return cache headers.
     */
    private function getHeaders(): array 
    {
        $immutable = (self::$lockFile['CacheImmutable'] ?? false) ? ', immutable' : '';
        $headers = self::$lockFile['Headers'] ?? [];
        $headers['default_headers'] = true;
        $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T',  self::$lockFile['Expiry']);
        $headers['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T',  self::$lockFile['Modify']);
        $headers['Cache-Control'] = 'public, max-age=' . self::$lockFile['MaxAge'] . $immutable;
        $headers['ETag'] =  '"' . self::$lockFile['ETag'] . '"';

        if(($headers['Content-Encoding'] ?? null) === false){
            unset($headers['Content-Encoding']);
        }

        return $headers;
    }

    /**
     * Determine the content encoding
     * 
     * @return string|false Return the content encoding handler, otherwise false.
     */
    private static function whichEncode(): string|bool
    {
        $encoding = env('compression.encoding', false);
        if ($encoding) {
            if (isset($_SERVER['HTTP_CONTENT_ENCODING'])) {
                return $_SERVER['HTTP_CONTENT_ENCODING'];
            }
            
            if (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
                $encoding = strtolower($encoding);
                if (str_contains($_SERVER['HTTP_ACCEPT_ENCODING'], $encoding)) {
                    return $encoding;
                }
            }
        }
        
        return false;
    }
}