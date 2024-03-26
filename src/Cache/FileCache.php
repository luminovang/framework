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

use \Luminova\Exceptions\ErrorException;
use \Generator;
use \DateTimeInterface;
use \DateInterval;
use \Luminova\Time\Time;

class FileCache 
{
     /**
     * Cache expiry time 7 days
     * @var int TTL_7DAYS constant
     */
    public const TTL_7DAYS = 7 * 24 * 60 * 60;

    /**
     * Cache expiry time 24 hours
     * @var int TTL_24HR constant
    */
    public const TTL_24HR = 24 * 60 * 60;

    /**
     * Cache expiry time 30 minutes 
     * @var int TTL_30MIN constant
    */
    public const TTL_30MIN = 30 * 60;
    /**
     * Hold the cache extension type PHP
     * @var string PHP constant
    */
    public const PHP = ".catch.php";

    /**
     * Hold the cache extension type JSON
     * @var string JSON constant
    */
    public const JSON = ".json";

     /**
     * Hold the cache extension TEXT
     * @var string TEXT constant
     */
    public const TEXT = ".txt";

     /**
     * Hold the cache file hash
     * @var string $storageHashed
     */
    private string $storageHashed = '';
    /**
     * Hold the cache directory path
     * @var string $storagePath
     */
    private string $storagePath = '';

    /**
     * Hold the cache security status option
     * @var bool $secure
     */
    private bool $secure = true;

    /**
     * Hold the cache file extension type
     * @var string $fileType
     */
    private string $fileType;

    /**
     * Hold the cache details array 
     * @var array $cacheInstance
     */
    private array $cacheInstance = [];

    /**
     * Hold the cache expiry delete option
     * @var bool $canDeleteExpired
     */
    private bool $canDeleteExpired = true;

    /**
     * Hold the cache base64 enabling option
     * @var bool $encode
     */
    private bool $encode = true;

    /**
     * Hold the cache expiry time
     * @var int $expiration
     */
    private int $expiration = 0;

    /**
     * Hold the cache expiry time after
     * @var int|null $expireAfter
     */
    private int|null $expireAfter = null;

     /**
     * Lock cache from deletion
     * @var bool $lock
     */
    private bool $lock = false;

    /**
     * Hold the static cache instance Singleton
     * @var static $instance
     */
    private static $instance = null;

    /**
     * Constructor.
     * 
     * @param string $filename cache storage filename to hash
     * @param string $folder cache storage sub folder.
     */
    public function __construct(string $storage = '', string $folder = '')
    {

        $path = path('caches') . $folder;
        $this->setExtension(self::JSON);
        $this->setPath($path);

        if( $storage !== ''){
            $this->storageHashed = static::hashStorage($storage);
            $this->create();
        }
	}

    /**
     * Get static Singleton Class.
     * 
     * @param string $storage cache storage filename to hash
     * @param string $folder cache storage sub folder.
     * 
     * @param static $instance Instance
     */
    public static function getInstance(string $storage = '', string $folder = ''): static 
    {
        if (static::$instance === null) {
            static::$instance = new static($storage, $folder);
        }

        return static::$instance;
    }

    /**
     * Set the new cache directory path
     * @param string $path cache directory must end with 
     * 
     * @return self $this
     */
    public function setPath(string $path): self 
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $path .= DIRECTORY_SEPARATOR;
        $this->storagePath = $path;

        return $this;
    }

     /**
     * Sets the new cache file name.
     * 
     * @param string $name cache filename hash value
     * 
     * @return self $this
     */
    public function setStorage(string $storage): self
    {
        $this->storageHashed = static::hashStorage($storage);

        return $this;
    }

     /**
     * Generate hash name for cache 
     * 
     * @param string $name cache filename to hash
     * 
     * @return string hashed name with prefix
     */
    public static function hashStorage(string $name): string 
    {
        $result = preg_replace('/[^a-zA-Z0-9]/', '', $name);

        return md5($result);
    }

     /**
     * Sets the cache file extension type
     * 
     * @param string $extension 
     * 
     * @return self $this
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
     * @return static The current instance.
     */
    public function setExpire(DateTimeInterface|int|null $expiration): static
    {
        if($expiration === null){
            $expiration = 0;
        }

        $this->expiration = static::ttlToSeconds($expiration);
        $this->expireAfter = null;

        return $this;
    }

    /**
     * Sets the expiration time of the cache item relative to the current time.
     *
     * @param int|DateInterval|null $time The expiration time in seconds or as a DateInterval.
     * 
     * @return static The current instance.
     */
    public function expiresAfter(int|DateInterval|null $time): static
    {
        $this->expireAfter = $time === null ? null : static::ttlToSeconds($time);

        return $this;
    }

     /**
     * Sets the cache lock
     * 
     * @param bool $lock lock catch to avoid deletion even when cache time expire
     * 
     * @return self $this
     */
    public function setLock(bool $lock): self 
    {
        $this->lock = $lock;

        return $this;
    }

     /**
     * Enable the cache to store data in base64 encoded.
     * 
     * @param bool $encode true or false
     * 
     * @return self $this
     */
    public function enableBase64(bool $encode): self 
    {
        $this->encode = $encode;

        return $this;
    }

    /**
     * Enable the cache delete expired data
     * 
     * @param bool $allow true or false
     * 
     * @return self $this
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
     * @param bool $secure true or false
     * 
     * @return self $this
    */
    public function enableSecureAccess(bool $secure): self 
    {
        $this->secure = $secure;

        return $this;
    }

    /**
     * Gets Combines directory, filename and extension into a full filepath
     * 
     * @return string
    */
    public function getPath(): string 
    {
        return $this->storagePath . $this->storageHashed . $this->fileType;
    }

    /**
     * Loads, create, update and delete cache with fewer options
     * 
     * @param string $key cache key
     * @param callable $callback Callback called when data needs to be refreshed.
     * 
     * @return mixed Data currently stored under key
     * @throws ErrorException if the file cannot be saved
     */
    public function onExpired(string $key, callable $callback): mixed 
    {
        if($key === '') {
            throw new ErrorException('Invalid argument, cache $key cannot be empty');
        }

		if($this->expiration === 0 && ($this->expireAfter === null || $this->expireAfter === 0)){
            return $callback();
        }

        if ($this->hasExpired($key)){
            $content = $callback();
            if($content !== null && $content !== ''){
                $this->setItem($key, $content, $this->expiration, $this->expireAfter, $this->lock);
            }

            return $content;
        }

        return $this->getItem($key);
    }

     /**
     * Loads, create, update and delete cache with full options
     * 
     * @param string $key cache key
     * @param callable $callback Callback called when data needs to be refreshed.
     * @param int $expiration cache expiry time
     * @param int $expireAfter cache expiry time after
     * @param bool $lock lock catch to avoid deletion even when cache time expire
     * 
     * @return bool
     * @throws ErrorException if the file cannot be saved
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

        if($content !== null && $content !== ''){
            return $this->setItem($key, $content, $expiration, $expireAfter, $lock);
        }

        return false;
    }

    /**
     * Get cache content from disk
     * 
     * @param string $key cache key
     * 
     * @return mixed  returns data if $key is valid and not expired, NULL otherwise
     * @throws ErrorException if the file cannot be saved
     */
    public function getItem(string $key, bool $onlyContent = true): mixed
    {
    
        if($this->canDeleteExpired){
            $this->deleteIfExpired();
        }

        if ($this->hasExpired($key) || !$this->hasItem($key)){
            if($onlyContent){
                return null;
            }
    
            return [
                "timestamp" => null,
                "expiration" => 0,
                "expireAfter" => null,
                "data" => null,
                "lock" => false
            ]; 
        }
 
        $data = $this->cacheInstance[$key];
        $content = unserialize($this->encode ? base64_decode($data["data"]) : $data["data"]);

        if($onlyContent){
            return $content;
        }

        $data["data"] = $content;

        return $data;
    }

    /**
     * Creates, Reloads and retrieve cache once class is created
     * 
     * @return self $this
     * @throws ErrorException if there is a problem loading the cache
    */
    public function create(): self 
    {
        $this->cacheInstance = $this->fetch();

        return $this;
    }

    /**
     * Checks if cache key exist
     * 
     * @param string $key cache key
     * 
     * @return bool true or false
    */
    public function hasItem(string $key): bool 
    {
        return isset($this->cacheInstance[$key]);
    }

    /**
     * Remove expired cache by key
     * 
     * @return int number of deleted keys
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

        if ($counter > 0){
            $this->commit();
        }
        return $counter;
    }

    /**
     * Deletes data associated with $key
     * 
     * @param string $key cache key
     * 
     * @return bool true or false
     * @throws ErrorException if the file cannot be saved
    */
    public function deleteItem(string $key): bool 
    {
        if ($this->hasItem($key)) {
            unset($this->cacheInstance[$key]);
            $this->commit();

            return true;
        }
      
        return false;
    }

    /**
     * Delete cache by array keys
     * @param iterable $keys array cache keys
     * 
     * @return bool 
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
            $this->commit();

            return true;
        }

        return false;
    }

    /**
     * Deletes data associated array of keys
     * 
     * @param iterable $array cache keys
     * 
     * @return Generator
     * @throws ErrorException if the file cannot be saved
    */
    public function removeList(iterable $array): Generator 
    {
        foreach($array as $key){
            yield $this->deleteItem($key);
        }
    }

    /**
     * Checks if the cache timestamp has expired by key
     * 
     * @param string $key cache key
     * 
     * @return bool true or false
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
     * Builds cache data to save
     * 
     * @param string $key cache keys
     * @param mixed $data cache data
     * @param int|DateTimeInterface|null $expiration cache expiration time
     * @param int|DateInterval|null $expireAfter cache expiration time after
     * @param bool $lock cache lock expiry deletion
     * 
     * @return bool
     * @throws ErrorException if the file cannot be saved
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

        $this->cacheInstance[$key] = [
            "timestamp" => time(),
            "expiration" => static::ttlToSeconds($expiration),
            "expireAfter" => $expireAfter === null ? null : static::ttlToSeconds($expireAfter),
            "data" => ($this->encode ? base64_encode($serialize) : $serialize),
            "lock" => $lock
        ];

        return $this->commit();
    }

    /**
     * Convert DateInterval to seconds.
     * 
     * @param DateInterval|DateTimeInterface $ttl Time 
     * 
     * @return int seconds.
    */
    public static function ttlToSeconds(DateInterval|DateTimeInterface|int|null $ttl): int
    {
        if($ttl === null){
            return 0;
        }

        if(is_int($ttl)){
            return $ttl;
        }

        $now = new Time();
        if($ttl instanceof DateInterval){
            $endTime = $now->add($ttl);

            return $endTime->getTimestamp() - $now->getTimestamp();
        }

        if($ttl instanceof DateTimeInterface){
            $diff = $now->diff($ttl);

            $seconds = $diff->s + ($diff->i * 60) + ($diff->h * 3600) + ($diff->d * 86400) + ($diff->m * 2592000) + ($diff->y * 31536000);
            
            return $seconds;
        }

        return 0;
    }

    /**
     * Fetch cache data from disk
     * 
     * @return mixed cached data
     * @throws ErrorException if cannot load cache, unable to unserialize, hash sum not found or invalid key
    */
    private function fetch(): mixed 
    {
    
        $filepath = $this->getPath();

        if (is_readable( $filepath )) {

            $file = file_get_contents($filepath);
         
            if ($file === false) {
                throw new ErrorException("Cannot load cache file! ({$this->storageHashed})");
            }

            $data = unserialize(static::unlock($file));

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
     * Remove the security line in php file cache
     * 
     * @param string $str cache string
     * 
     * @return string cache text without the first security line
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
     * @return bool
     */
    public function clear(): bool
    {
        $this->cacheInstance = [];

        return $this->commit();
    }

    /**
     * Remove cache file
     * 
     * @return bool true if file path exist else false
     */
    public function clearStorage(): bool 
    {
		$fileCache = $this->getPath();

		if(file_exists($fileCache)){
			return unlink($fileCache);
		}

		return false;
    }

    /**
     * Remove cache file from disk with full path
     * 
     * @param string $path cache full path /
     * @param array $filenames cache file array names
     * @param string $extension cache file extension type
     * 
     * @return bool
     */
    public static function deleteStorageDisk(string $path, array $filenames, string $extension = self::JSON): bool 
    {
        $success = true;
        foreach($filenames as $name){
            $fileCache = $path . static::hashStorage($name) . $extension;
            if(file_exists($fileCache)){
                if (!unlink($fileCache)) {
                    $success = false;
                }
            }
        }
        return $success;
    }

    /**
     * Write the cache data disk.
     * 
     * @return bool
     */
     private function commit(): bool 
     {

        make_dir($this->storagePath);     
    
        $cache = $this->cacheInstance;
        $cache["hash-sum"] = md5(serialize($cache));

        $writeLine = '';
        if ($this->fileType == self::PHP && $this->secure) {
            $writeLine = '<?php header("Content-type: text/plain"); die("Access denied"); ?>' . PHP_EOL;
        }
    
        $writeLine .= serialize($cache);
    
        $filePath = $this->getPath();
        $saved = write_content($filePath, $writeLine);

        return $saved;
    }
}
