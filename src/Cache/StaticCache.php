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
namespace Luminova\Cache;

use \Closure;
use \Luminova\Boot;
use \DateTimeInterface;
use \Luminova\Http\Header;
use \Luminova\Utility\MIME;
use \Luminova\Time\Timestamp;
use \Luminova\Storage\Filesystem;
use function \Luminova\Funcs\{make_dir, string_length};

final class StaticCache
{
    /**
     * The expiration time for cached.
     * 
     * @var int|null $expiration 
     */
    private ?int $expiration = 0;

    /**
     * @var string $key Cache key.
     */
    private string $key = '';

    /**
     * Lock function name.
     * 
     * @var string $lockFunc
     */
    private string $lockFunc = '';

    /**
     * The found cache filename and version.
     * 
     * @var string|null $foundCacheLocation
     */
    private static ?string $foundCacheLocation = null;

    /**
     * Cache lock files.
     * 
     * @var array|null $cache
     */
    private static ?array $cache = null;

    /**
     * Cache burst window.
     * 
     * @var DateTimeInterface|int|null $maxBurst
     */
    private DateTimeInterface|int|null $maxBurst = null;

    /**
     * Class page cache constructor.
     *
     * @param DateTimeInterface|int $expiration The expiration time for cached files in seconds (default: 0).
     * @param string $directory The directory where cached files will be stored (default: 'cache').
     * @param string|null $filename The cache filename (default: 'null').
     * @param int|null $uri The request uri paths (default: 'null').
     * @param bool|null $immutable Is immutable or not (default: 'null').
     */
    public function __construct(
        DateTimeInterface|int|null $expiration = 0, 
        private string $directory = 'cache',
        private ?string $filename = null,
        private ?string $uri = null,
        private ?bool $immutable = null
    )
    {
        $this->immutable ??= env('page.caching.immutable', false);
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
        $this->expiration = ($expiration instanceof DateTimeInterface) 
            ? Timestamp::ttlToSeconds($expiration) 
            : $expiration;

        return $this;
    }

    /**
     * Set the filename to extract metadata.
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
     * Send headers to force browser to ignore cached content for a limited duration.
     *  
     * @param DateTimeInterface|int|null $maxTime Maximum burst time in seconds.
     * 
     * @return self Return class instance.
     */
    public function burst(DateTimeInterface|int|null $maxBurst): self
    {
        $this->maxBurst = $maxBurst;
        return $this;
    }

    /**
     * Set the request uri paths.
     *  
     * @param string $uri The request uri paths.
     * 
     * @return self Return class instance.
     */
    public function setUri(string $url): self
    {
        $this->uri = $url;
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
     * Set whether the cached page content is immutable.
     * 
     * * `true` - cached content will not change and can be safely reused
     * * `false` - content may update dynamically
     * * `null` - Use default configuration
     * 
     * @param bool|null $immutable Is immutable or not.
     * 
     * @return self Return class instance.
     */
    public function isImmutable(?bool $immutable): self
    {
        if($immutable === null){
            return $this;
        }

        $this->immutable = $immutable;
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
        return $this->getLocation() . 
            ($version ?? APP_VERSION) . 
            DIRECTORY_SEPARATOR . 
            $this->key . 
            '.lmv.php';
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
        return is_file($this->getFilename());
    }

    /**
     * Check if the cached content has expired.
     * This method return false if not expired, but if the view type is mismatched it return integer 404.
     * 
     * @param string|null $type The type of cached content to check (e.g, `View::HTML`).
     * 
     * @return bool|int Return true if the cache is expired, otherwise false.
     */
    public function expired(?string $type = null): bool|int
    {
        if($type === '' || $type === '_'){
            return 404;
        }

        // Validate initialization and ensure lockFile is not empty
        if (!$this->init() || self::$cache === null) {
            return true;
        }

        /// Check if content type matches the cached version
        if($type && !$this->isTypeof($type)){
            return 404;
        }

        // Check if cache has expired
        $expiration = (int) (self::$cache['Expiry'] ?? 0);
     
        return ($expiration === 0 || (self::$cache['CacheImmutable'] ?? false) === true) 
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
        return is_file($filename) && unlink($filename);
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
        return Filesystem::delete(
            $this->getLocation() . 
            ($version ?? APP_VERSION) . 
            DIRECTORY_SEPARATOR
        );
    }

    /**
     * Load the content from the cache file and exit the script.
     * 
     * @param string $type The type of cached content to check (e.g, `View::HTML`).
     * 
     * @return bool|int Return true if loading was successful, if miss-matched type return int 404, otherwise false.
     */
    public function read(
        ?string $type = null, 
        ?Closure $onBeforeRender = null,
        ?Closure $onRendered = null
    ): bool|int
    {
        if($type === '' || $type === '_'){
            return 404;
        }

        
        if(self::$cache === [] && $this->init() === false){
            Header::sendNoCacheHeaders(404);
            return false;
        }

        if($type && !$this->isTypeof($type)){
            return 404;
        }
    
        $status = null;
        $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
        $eTag = self::$cache['ETag'] ?? null;

        if($onBeforeRender === null){
            Boot::add('__CLASS_METADATA__', 'cache', true);
        }else{
            $onBeforeRender();
        }

        if($this->maxBurst === null){
            // Check if the ETag matches the client's If-None-Match header
            if ($eTag && $ifNoneMatch === "\"$eTag\"") {
                $status = 304;
            }elseif($ifModifiedSince){
                $lastModify = self::$cache['Modify'] ?? null;

                // Check if modify since matches the client's If-Modified-Since header
                if ($lastModify) {
                    $modifiedSince = strtotime($ifModifiedSince);

                    if($modifiedSince >= $lastModify){
                        $status = 304;
                    }else{
                        $filename = self::$cache['Filename'] ?? null;

                        if(
                            $filename && 
                            (self::$cache['AppVersion'] ?? null) === APP_VERSION &&
                            is_file($filename)
                        ){
                            $fileMTime = @filemtime($filename) ?: null;
                            $status = ($fileMTime && $modifiedSince >= $fileMTime) ? 304 : null;
                        }
                    }
                }
            }
        }

        Header::send($this->getHeaders(true), status: $status);

        if($status === null){
            Header::clearOutputBuffers('all');
            Header::setOutputHandler(true);
            self::$cache['Func']();
        }

        if($onRendered !== null){
            $onRendered();
        }

        return true;
    }

    /**
     * Get the content from the cache filet.
     * 
     * @param string|null $type The type of cached content to check (e.g, `View::HTML`).
     * 
     * @return string|int|false|null Return cached contents, 404 for mismatched, null otherwise.
     */
    public function get(?string $type = null): string|int|bool|null
    {
        if($type === '' || $type === '_'){
            return 404;
        }

        if(self::$cache === [] && $this->init() === false){
            return null;
        }

        if($type && !$this->isTypeof($type)){
            return 404;
        }

        Header::setOutputHandler(true, false);
        self::$cache['Func']();
        return ob_get_clean();
    }

    /**
     * Save the content to the cache file.
     *
     * @param string $content The content to be saved to the cache file.
     * @param array $headers Additional view headers.
     * @param string $type The view template content type.
     *
     * @return bool Return true if saving was successful; false otherwise.
     */
    public function saveCache(string $content, array $headers = [], string $type = 'html'): bool
    {
        $type = trim(strtolower($type));

        if(!$type || $type === '_' ){
            return false;
        }

        $path = $this->getLocation() . APP_VERSION . DIRECTORY_SEPARATOR;

        if($this->expiration === null || !make_dir($path)){
            return false;
        }

        // Clear file stat cache for the specific file if filename exists
        //if ($this->filename) {
        //    @clearstatcache(true, $this->filename);
        //}

        $now = time();
        $fileMTime = $this->filename ? (@filemtime($this->filename) ?: $now) : $now;
        $expiration = ($this->expiration === 0) ? 31536000 * 5 : $this->expiration;
        $length = (int) ($headers['Content-Length'] ?? string_length($content));

        $headers['Content-Type'] ??= MIME::findType($type);
        $headers['Content-Length'] = $length;

        return Filesystem::write(
            $path . $this->key . '.lmv.php', 
            "<?php function {$this->lockFunc}(string \$key): array|bool {\n"
            . " \$lock = " . var_export([$this->key => [
                'viewType' => $type,
                'MaxAge' => $expiration,
                'TTL' => $this->expiration,
                'CacheImmutable' => (bool) $this->immutable,
                'AppVersion' => APP_VERSION,
                'Expiry' => $now + $expiration,
                'Date' => gmdate('D, d M Y H:i:s \G\M\T'),
                'Modify' => $fileMTime,
                'Size' => $length,
                'Filename' => $this->filename,
                'Func' => '__lmv_template_content_' . $this->key,
                'ETag' => md5("eTag_{$type}_{$fileMTime}_{$length}_{$expiration}" . APP_VERSION),
                'Headers' => $headers
            ]], true) . ";\n"
            . " return \$lock[\$key]??false;\n"
            . "}?>\n"
            . "<?php function __lmv_template_content_{$this->key}():void { ?>\n"
            . $content
            . "\n<?php } ?>\n"
        );
    }

    /**
     * Load and initialize the cache file.
     * 
     * @return false Return true if cache is found and valid, false otherwise.
     */
    private function init(): bool
    {
        self::$cache = [];
        self::$foundCacheLocation = null;
        
        return $this->findCache() && $this->isCacheValid();
    }

    /**
     * Check if the view type matches the cached content view type.
     * 
     * @var string $type The view type to check.
     * 
     * @return bool Return true if matched.
     */
    private function isTypeof(string $type): bool 
    {
        $type = strtolower($type);
        $t = self::$cache['viewType'] ?? '_';

        return ($t === $type || ($t === 'text' && $type === 'txt'));
    }

    /**
     * Searches for an existing cached version of the content.
     *
     * The method first checks the cache for the current application version.
     * If not found, it optionally avoids older versions based on URI matching.
     * If permitted, it falls back to searching older versioned cache directories.
     *
     * @return bool True if a cached file is found, false otherwise.
     */
    private function findCache(): bool
    {
        $filename = $this->key . '.lmv.php';
        $path = $this->getLocation();

        // Check for cache in the current app version's cache directory
        $file = $path . APP_VERSION . DIRECTORY_SEPARATOR . $filename;

        if (is_file($file)) {
            self::$foundCacheLocation = $file;
            return true;
        }

        $pastVersions = env('page.cache.app.versions', []);
        if($pastVersions === []){
            return false;
        }

        // Prevent fallback to older versions if URI matches preferred latest-only paths
        // This ensures certain pages (e.g., '/', 'users') always render fresh content
        if ($this->uri !== null && ($patterns = env('page.cache.latest.content', [])) !== []) {
            foreach ($patterns as $pattern) {
                if ($this->preferLatestContent($pattern)) {
                    return false;
                }
            }
        }

        // Search in past version directories as a fallback
        $pastVersions = env('page.cache.app.versions', []);
        if ($pastVersions !== []) {
            foreach ($pastVersions as $past) {
                $file = $path . $past . DIRECTORY_SEPARATOR . $filename;

                if (!is_file($file)) {
                    continue;
                }

                self::$foundCacheLocation = $file;
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL matches latest contents pattern.
     * 
     * @param string $pattern The url pattern to match.
     * 
     * @return bool Return true if match otherwise false.
     */
    private function preferLatestContent(string $pattern): bool
    {
        if($this->uri === $pattern || ($pattern === '/' && $this->uri === '')){
            return true;
        }

        $pattern = str_replace('\*', '[^\/]+', preg_quote($pattern, '/'));
        return preg_match('/^' . $pattern . '$/', $this->uri) === 1;
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
 
        return (static function(string $_fn, string $_k): bool {
            include_once self::$foundCacheLocation;
            if(function_exists($_fn) && ($lock = $_fn($_k)) !== false){
                self::$cache = $lock;
                return true;
            }

            return false;
        })($this->lockFunc, $this->key);
    }

    /**
     * Get cache headers to server.
     * 
     * @return array<string,mixed> Return cache headers.
     */
    private function getHeaders(bool $burst = false): array 
    {
        $headers = self::$cache['Headers'] ?? [];

        if(($headers['Content-Encoding'] ?? null) === false){
            unset($headers['Content-Encoding']);
        }
        
        if($burst && $this->maxBurst !== null){
            if ($this->maxBurst instanceof \DateTimeInterface) {
                $this->maxBurst = $this->maxBurst->getTimestamp();
            } elseif (is_int($this->maxBurst) && $this->maxBurst <= 315360000) { 
                $this->maxBurst = time() + $this->maxBurst;
            }

            if (time() < $this->maxBurst) {
                $headers['Expires']       = 'Tue, 01 Jan 2000 00:00:00 GMT';
                $headers['Last-Modified'] = gmdate("D, d M Y H:i:s") . ' GMT';
                $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
                $headers['Pragma']        = 'no-cache';
                $headers['Cache-Control2'] = 'post-check=0, pre-check=0';

                return $headers;
            }
        }

        $immutable = (self::$cache['CacheImmutable'] ?? false) ? ', immutable' : '';
        $headers['X-System-Default-Headers'] = true;
        $headers['Expires'] = gmdate('D, d M Y H:i:s \G\M\T', self::$cache['Expiry']);
        $headers['Last-Modified'] = gmdate('D, d M Y H:i:s \G\M\T', self::$cache['Modify']);
        $headers['Cache-Control'] = 'public, max-age=' . self::$cache['MaxAge'] . $immutable;
        $headers['ETag'] =  '"' . self::$cache['ETag'] . '"';

        return $headers;
    }
}