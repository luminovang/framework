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

use \Luminova\Storages\FileManager;
use \Luminova\Time\Timestamp;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\ErrorException;
use \Closure;
use \Generator;
use \DateTimeInterface;
use \DateInterval;
use \Exception;

final class FileCache 
{
    /**
     * Cache expiry time 7 days.
     * 
     * @var int TTL_7DAYS constant
     */
    public const TTL_7DAYS = 7 * 24 * 60 * 60;

    /**
     * Cache expiry time 24 hours.
     * 
     * @var int TTL_24HR constant
    */
    public const TTL_24HR = 24 * 60 * 60;

    /**
     * Cache expiry time 30 minutes.
     * 
     * @var int TTL_30MIN constant
    */
    public const TTL_30MIN = 30 * 60;
    /**
     * Hold the cache extension type PHP.
     * 
     * @var string PHP constant
    */
    public const PHP = ".catch.php";

    /**
     * Hold the cache extension type JSON.
     * 
     * @var string JSON constant
    */
    public const JSON = ".json";

     /**
     * Hold the cache extension TEXT.
     * 
     * @var string TEXT constant
     */
    public const TEXT = ".txt";

     /**
     * Hold the cache file hash.
     * 
     * @var string $storageHashed
     */
    private string $storageHashed = '';
    /**
     * Hold the cache directory path.
     * 
     * @var string $storagePath
     */
    private string $storagePath = '';

    /**
     * Hold the cache security status option.
     * 
     * @var bool $secure
     */
    private bool $secure = true;

    /**
     * Hold the cache file extension type.
     * 
     * @var string $fileType
     */
    private string $fileType;

    /**
     * Hold the cache details array.
     * 
     * @var array $cacheInstance
     */
    private array $cacheInstance = [];

    /**
     * Hold the cache expiry delete option.
     * 
     * @var bool $canDeleteExpired
     */
    private bool $canDeleteExpired = true;

    /**
     * Hold the cache base64 enabling option. 
     * 
     * @var bool $encoding
     */
    private bool $encoding = true;

    /**
     * Hold the cache expiry time. 
     * 
     * @var int $expiration
     */
    private int $expiration = 0;

    /**
     * Hold the cache expiry time after.
     * 
     * @var int|null $expireAfter
     */
    private int|null $expireAfter = null;

     /**
     * Lock cache from deletion.
     * 
     * @var bool $lock
     */
    private bool $lock = false;

    /**
     * Hold the cache instance Singleton.
     * 
     * @var static|null $instance
     */
    private static ?self $instance = null;

    /**
     * Initialize cache constructor, with optional storage name and subfolder.
     * 
     * @param string|null $storage cache storage filename to hash.
     * @param string $folder cache storage sub folder.
     */
    public function __construct(?string $storage = null, string $folder = '')
    {
        $this->setExtension(self::JSON);
        $this->setPath(root('/writeable/caches/') . $folder);

        if( $storage !== null){
            $this->storageHashed = static::hashStorage($storage);
            $this->create();
        }
	}

    /**
     * Get static Singleton Class.
     * 
     * @param string|null $storage cache storage filename to hash.
     * @param string $folder cache storage sub folder.
     * 
     * @param static Return new class instance.
     */
    public static function getInstance(?string $storage = null, string $folder = ''): static 
    {
        if (static::$instance === null) {
            static::$instance = new static($storage, $folder);
        }

        return static::$instance;
    }

    /**
     * Set the new cache directory path.
     * 
     * @param string $path cache directory must end with.
     * 
     * @return self Return class instance.
     */
    public function setPath(string $path): self 
    {
        $this->storagePath = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return $this;
    }

     /**
     * Sets the new cache file name.
     * 
     * @param string $storage cache storage filename,
     * 
     * @return self Return class instance.
     */
    public function setStorage(string $storage): self
    {
        $this->storageHashed = static::hashStorage($storage);

        return $this;
    }

     /**
     * Generate hash name for cache.
     * 
     * @param string $name cache filename to hash.
     * 
     * @return string hashed name with prefix.
     */
    public static function hashStorage(string $name): string 
    {
        return md5(preg_replace('/[^a-zA-Z0-9]/', '', $name));
    }

     /**
     * Sets the cache file extension type.
     * 
     * @param string $extension The file extension to use in storing cache.
     * 
     * @return self Return class instance.
     */
    public function setExtension(string $extension): self
    {
        $this->fileType = $extension;

        return $this;
    }

    /**
     * Sets the expiration time of the cache item.
     *
     * @param DateTimeInterface|null $expiration The expiration time of the cache item.
     * 
     * @return self The current instance.
     */
    public function setExpire(DateTimeInterface|int|null $expiration): self
    {
        if($expiration === null){
            $expiration = 0;
        }

        $this->expiration = Timestamp::ttlToSeconds($expiration);
        $this->expireAfter = null;

        return $this;
    }

    /**
     * Sets the expiration time of the cache item relative to the current time.
     *
     * @param int|DateInterval|null $time The expiration time in seconds or as a DateInterval.
     * 
     * @return self The current instance.
     */
    public function expiresAfter(int|DateInterval|null $time): self
    {
        $this->expireAfter = $time === null ? null : Timestamp::ttlToSeconds($time);

        return $this;
    }

     /**
     * Sets the cache lock to avoid deletion even when cache has expired.
     * 
     * @param bool $lock lock flag to be used.
     * 
     * @return self Return class instance.
     */
    public function setLock(bool $lock): self 
    {
        $this->lock = $lock;

        return $this;
    }

     /**
     * Enable the cache to store data in base64 encoded.
     * 
     * @param bool $encoding Enable base64 encoding or disable.
     * 
     * @return self Return class instance.
     */
    public function enableBase64(bool $encoding): self 
    {
        $this->encoding = $encoding;

        return $this;
    }

    /**
     * Enable automatic cache deletion if expired.
     * 
     * @param bool $allow Auto deletion flag.
     * 
     * @return self Return class instance.
     */
    public function enableDeleteExpired(bool $allow): self 
    {
        $this->canDeleteExpired = $allow;

        if($allow){
            $this->deleteIfExpired();
        }

        return $this;
    }

    /**
     * Enable the cache to store secure data in php file extension.
     * 
     * @param bool $secure The secure flag to use.
     * 
     * @return self Return class instance.
    */
    public function enableSecureAccess(bool $secure): self 
    {
        $this->secure = $secure;

        return $this;
    }

    /**
     * Gets Combines directory, filename and extension into a full filepath.
     * 
     * @return string Return full cache path.
    */
    public function getPath(): string 
    {
        return $this->storagePath . $this->storageHashed . $this->fileType;
    }

    /**
     * Read cache content, of update the content with new item if it has expired.
     * 
     * @param string $key cache key.
     * @param Closure $callback(): mixed Callback called when data needs to be refreshed.
     *      -   Return content to be cached.
     * 
     * @return mixed Return cache content currently stored under key.
     * @throws ErrorException if the file cannot be saved.
     */
    public function onExpired(string $key, Closure $callback): mixed 
    {
        if($key === '') {
            throw new ErrorException('Invalid argument, cache $key cannot be empty');
        }

		if($this->expiration === 0 && ($this->expireAfter === null || $this->expireAfter === 0)){
            return $callback();
        }

        if ($this->hasExpired($key)){
            $content = $callback();
            if(!empty($content)){
                $this->setItem($key, $content, $this->expiration, $this->expireAfter, $this->lock);
            }

            return $content;
        }

        return $this->getItem($key);
    }

     /**
     * Refresh cache content with new data and expiration if necessary.
     * 
     * @param string $key cache key.
     * @param mixed $content New content to update.
     * @param int $expiration cache expiry time.
     * @param int $expireAfter cache expiry time after.
     * @param bool $lock lock catch to avoid deletion even when cache time expired.
     * 
     * @return bool Return true if item was successfully updated, otherwise false.
     * @throws ErrorException if the file cannot be saved.
     */
    public function refresh(
        string $key, 
        mixed $content, 
        int $expiration = 0, 
        int|null $expireAfter = null, 
        bool $lock = true
    ): bool 
    {
        if($key === '') {
            throw new ErrorException('Invalid argument, cache $key cannot be empty');
        }

		if($expiration === 0 && ($expireAfter === null || $expireAfter === 0)){
            return false;
        }

        if(!empty($content)){
            return $this->setItem($key, $content, $expiration, $expireAfter, $lock);
        }

        return false;
    }

    /**
     * Get cache content from disk.
     * 
     * @param string $key cache key.
     * @param bool $onlyContent Weather to return only cache content or with metadata (default: true).
     * 
     * @return mixed Returns data if key is valid and not expired, NULL otherwise.
     * @throws ErrorException if the file cannot be saved.
    */
    public function getItem(string $key, bool $onlyContent = true): mixed
    {
        if($this->canDeleteExpired){
            $this->deleteIfExpired();
        }

        if ($this->hasExpired($key)){
            if($onlyContent){
                return null;
            }
    
            return [
                "timestamp" => null,
                "expiration" => 0,
                "expireAfter" => null,
                "data" => null,
                "lock" => false,
                "encoding" => $this->encoding ? 'base64' : 'raw'
            ]; 
        }
 
        $data = $this->cacheInstance[$key];
        $content = unserialize(($data["encoding"] === 'base64') ? base64_decode($data["data"]) : $data["data"]);
       
        if($onlyContent){
            return $content;
        }

        $data["data"] = $content;
        return $data;
    }

    /**
     * Creates, Reloads and retrieve cache once class is created.
     * 
     * @return self Return cache class instance.
     * @throws ErrorException if there is a problem loading the cache.
    */
    public function create(): self 
    {
        $this->cacheInstance = $this->fetch();

        return $this;
    }

    /**
     * Checks if cache key exist.
     * 
     * @param string $key cache key.
     * 
     * @return bool Return true if cache key exists, otherwise false.
    */
    public function hasItem(string $key): bool 
    {
        return isset($this->cacheInstance[$key]);
    }

    /**
     * Remove expired cache by key.
     * 
     * @return int Return number of deleted expired items.
    */
    public function deleteIfExpired(): int 
    {
        $counter = 0;
        foreach ($this->cacheInstance as $key => $value) {
            if ($this->hasExpired($key) && !$value["lock"]) {
                unset($this->cacheInstance[$key]);
                $counter++;
            }
        }

        if ($counter > 0 && $this->commit()){
            return $counter;
        }

        return 0;
    }

    /**
     * Deletes cache item associated with a given key.
     * 
     * @param string $key The cache key to delete.
     * 
     * @return bool Return true if cache was successfully deleted, otherwise false.
    */
    public function deleteItem(string $key): bool 
    {
        if ($this->hasItem($key)) {
            unset($this->cacheInstance[$key]);
            return $this->commit();
        }
      
        return false;
    }

    /**
     * Delete cache by array keys.
     * 
     * @param iterable $keys array cache keys.
     * 
     * @return bool Return true if cache was successfully deleted, otherwise false.
    */
    public function deleteItems(iterable $keys): bool 
    {
        $counter = 0;
        foreach ($keys as $key) {
            if ($this->hasItem($key)) {
                unset($this->cacheInstance[$key]);
                $counter++;
            }
        }

        if ($counter > 0){
            return $this->commit();
        }

        return false;
    }
  
    /**
     * Remove a list of items from the collection.
     *
     * @param iterable<int,string> $iterable An iterable list of keys representing items to be removed.
     * 
     * @return Generator Yields the result of the delete operation for each item.
     * @throws ErrorException if the file cannot be saved.
     */
    public function removeList(iterable $iterable): Generator 
    {
        foreach ($iterable as $key) {
            yield $this->deleteItem($key);
        }
    }

    /**
     * Checks if the cache timestamp has expired by key.
     * 
     * @param string $key cache key.
     * 
     * @return bool Return true if cache has expired, otherwise false.
    */
    public function hasExpired(string $key): bool 
    {
        if ($this->hasItem($key)) {
            $item = $this->cacheInstance[$key];
            $timestamp = $item["timestamp"];
            $expiration = $item["expiration"] ?? 0;
            $expireAfter = $item["expireAfter"] ?? null;
            $now = time();

            if ($expiration !== null && ($now - $timestamp) >= $expiration) {
                return true;
            }

            if ($expireAfter !== null && ($now - $timestamp) >= $expireAfter) {
                return true;
            }
    
            return false;
            
        }

        return true;
    }

    /**
     * Builds cache data and save it.
     * 
     * @param string $key The cache keys.
     * @param mixed $data The cache contents.
     * @param int|DateTimeInterface|null $expiration cache expiration time.
     * @param int|DateInterval|null $expireAfter cache expiration time after.
     * @param bool $lock Lock catch to avoid deletion even when cache time expired.
     * 
     * @return bool Return true if cache was saved, otherwise false.
     * @throws ErrorException if the file cannot be saved.
    */
    public function setItem(string $key, mixed $data, int|DateTimeInterface|null $expiration = 0, int|DateInterval|null $expireAfter = null, bool $lock = false): bool 
    {
        $serialize = serialize($data);
        if ($serialize === '') {
            throw new ErrorException("Failed to create cache file!");
        }

        if($expiration !== null){
            $expireAfter = null;
        }

        if($this->encoding){
            $serialize = base64_encode($serialize);
        }

        $this->cacheInstance[$key] = [
            "timestamp" => time(),
            "expiration" => Timestamp::ttlToSeconds($expiration),
            "expireAfter" => ($expireAfter === null) ? null : Timestamp::ttlToSeconds($expireAfter),
            "data" => $serialize,
            "lock" => $lock,
            "encoding" => $this->encoding ? 'base64' : 'raw'
        ];

        return $this->commit();
    }

    /**
     * Fetch cache data from disk.
     * 
     * @return array Return cache information.
     * @throws ErrorException if cannot load cache, unable to unserialize, hash sum not found or invalid key.
    */
    private function fetch(): array 
    {
        $filepath = $this->getPath();

        if (is_readable( $filepath )) {
            $file = get_content($filepath);
         
            if ($file === false) {
                throw new ErrorException("Cannot load cache file! ({$this->storageHashed})");
            }

            $data = unserialize(self::unlock($file));

            if ($data === null) {
                unlink($filepath);
                throw new ErrorException("Failed to unserialize cache file, cache file deleted. ({$this->storageHashed})");
            }
           
     
            if (isset($data["hash-sum"])) {

                $hash = $data["hash-sum"];
                unset($data["hash-sum"]);

                if ($hash !== md5(serialize($data))) {
                    unlink($filepath);
                    throw new ErrorException("Cache data miss-hashed, cache file was deleted");
                }

                return $data;
            }

            unlink($filepath);
            throw new ErrorException("No hash found in cache file, cache file deleted");
        }

        return [];
    }

    /**
     * Remove the security line in php file cache.
     * 
     * @param string $str cache string.
     * 
     * @return string cache text without the first security line.
     */
    private static function unlock(string $str): string 
    {
        $position = strpos($str, PHP_EOL);
        if ($position === false){
            return $str;
        }

        return substr($str, $position + 1);
    }

    /**
     * Wipes clean the entire cache's.
     * 
     * @param bool $clearDisk Whether to clear all cache disk.
     * 
     * @return bool Return true if cache was successfully cleared, false otherwise.
     */
    public function clear(bool $clearDisk = false): bool
    {
        $this->cacheInstance = [];

        if($clearDisk && FileManager::remove($this->storagePath)){
            return true;
        }

        return $this->commit();
    }

    /**
     * Remove current cache file 
     * 
     * @return bool Return true if file path exist else false
     */
    public function clearStorage(): bool 
    {
		$fileCache = $this->getPath();

		return file_exists($fileCache) && unlink($fileCache);
    }

    /**
     * Remove cached storage file from disk with full path.
     * This will use the current storage path.
     * 
     * @param string $storage cache storage names.
     * @param string $extension cache file extension type.
     * 
     * @return bool Return true on success, false on failure.
    */
    public function delete(string $storage, string $extension = self::JSON): bool 
    {
        return static::deleteDisk($this->storagePath, $storage, $extension);
    }

    /**
     * Remove cache file from disk with full path.
     * 
     * @param string $path cache full path /.
     * @param string $storage cache file array names.
     * @param string $extension cache file extension type.
     * 
     * @return bool Return true if cache file was successfully deleted, false otherwise.
    */
    public static function deleteDisk(string $path, string $storage, string $extension = self::JSON): bool 
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $path = $path . static::hashStorage($storage) . '.' . trim($extension, '.');

        return file_exists($path) && unlink($path);
    }

    /**
     * Write the cache data disk.
     * If cache is unable to write due to an exception, the error will be logged using `error` log level.
     * 
     * @return bool Return true if commit was successful, false otherwise.
     */
     private function commit(): bool 
     {
        try{
            make_dir($this->storagePath);     
        
            $cache = $this->cacheInstance;
            $cache["hash-sum"] = md5(serialize($cache));

            $writeLine = '';
            if ($this->fileType === self::PHP && $this->secure) {
                $writeLine .= '<?php header("Content-type: text/plain"); die("Access denied"); ?>' . PHP_EOL;
            }
        
            $writeLine .= serialize($cache);
        
            $filePath = $this->getPath();
            $saved = write_content($filePath, $writeLine);

            return $saved;
        }catch(Exception|AppException $e){
            logger('error', 'Unable to commit cache: ' . $e->getMessage(), [
                'class' => 'FileCache'
            ]);
        }

        return false;
    }
}
