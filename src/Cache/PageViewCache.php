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
     * @var string $type Cache type
     */
    private string $type = 'html';

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
     * @return self 
    */
    public function setExpiry(DateTimeInterface|int $expiration): self
    {
        $this->expiration = is_int($expiration) ? $expiration : Timestamp::ttlToSeconds($expiration);

        return $this;
    }

    /**
     * Set cache directory
     * @param string $directory The directory where cached files will be stored (default: 'cache').
     * 
     * @return self 
    */
    public function setDirectory(string $directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Set cache directory
     * @param string $directory The directory where cached files will be stored (default: 'cache').
     * 
     * @return self 
    */
    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set the cache key.
     *
     * @param string $key The key to set.
     *
     * @return void
     */
    public function setKey(string $key): void
    {
        $this->key = $key;
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
        return $this->getLocation() . $this->key . '.' . $this->type;
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
     * Check if the cached file is still valid based on its expiration time.
     *
     * @return bool True if the cache is still valid; false otherwise.
     */
    public function has(): bool
    {
        $location = $this->getFilename();

        return file_exists($location) && !$this->expired($this->key, $this->directory);
    }

    /**
     * Check if the cached has expired.
     * 
     * @param string $key Cache key
     * 
     * @return bool True if the cache is still valid; false otherwise.
    */
    public static function expired(string $key, string $directory): bool
    {
        $metaLocation = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pagecache.lock';
      
        if(file_exists($metaLocation)){
            $info = json_decode(file_get_contents($metaLocation), true);

            if(isset($info[$key])){
                return time() >= (int) ($info[$key]['Expiry'] ?? 0);
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
        $lockFile = $this->getLocation() . 'pagecache.lock';

        if (file_exists($lockFile)) {
            $lock = json_decode(file_get_contents($lockFile), true);

            if(isset($lock[$this->key])){
                unset($lock[$this->key]);
                write_content($lockFile, json_encode($lock, JSON_PRETTY_PRINT));
            }
        }

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
        $headers = [
            'default_headers' => true
        ];
        $metadta = $this->getLocation() . 'pagecache.lock';

        if (file_exists($metadta)) {
            $items = json_decode(file_get_contents($metadta), true);

            if(isset($items[$this->key])){
                $item = $items[$this->key];
                $headers['Content-Type'] = ($item['Content-Type'] ?? Header::getContentTypes($item['viewType']));
                if(isset($item['Content-Encoding'])){
                    $headers['Content-Encoding'] = $item['Content-Encoding'];
                }
                $headers['Expires'] = gmdate("D, d M Y H:i:s",  $item['Expiry']) . ' GMT';
                $headers['Cache-Control'] = 'max-age=' . $item['MaxAge'] . ', public';
                $headers['ETag'] =  '"' . $item['ETag'] . '"';
            }else{
                return false;
            }
        }else{
            return false;
        }

        Header::parseHeaders($headers);
        $bytesRead = @readfile($this->getFilename());
        
        return $bytesRead !== false;
    }

    /**
     * Get the content from the cache filet.
     * 
     * @return string|null Return cached contents, null otherwise.
    */
    public function get(): ?string
    {
        $contents = @file_get_contents($this->getFilename());
        
        if ($contents === false) {
            return null;
        }
        
        return $contents;
    }

    /**
     * Save the content to the cache file.
     *
     * @param string $content The content to be saved to the cache file.
     * @param array|null $headers Cache headers.
     * @param string $type Cache content type.
     *
     * @return bool True if saving was successful; false otherwise.
     */
    public function saveCache(string $content, ?array $headers = null, string $type = 'html'): bool
    {
        $location = $this->getLocation();
        make_dir($location);     

        $filename = $this->getFilename();
        if(write_content($filename, $content)){
            $headers ??= [];

            $headers['viewType'] = $type;
            $lockFile = $location . 'pagecache.lock';
            $locks = file_get_contents($lockFile);
    
            $locks = ($locks === false) ? [] : json_decode($locks, true);
    
            $headers['MaxAge'] = $this->expiration;
            $headers['Expiry'] = time() + $this->expiration;
            $headers['Date'] = date("D, d M Y H:i:s");
            $headers['ETag'] = md5_file($filename);
            $locks[$this->key] = $headers;

            if(!write_content($lockFile, json_encode($locks, JSON_PRETTY_PRINT))){
                unlink($filename);

                return false;
            }

            return true;
        }

        return false;
    }
}