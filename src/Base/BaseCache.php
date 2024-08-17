<?php 
/**
 * Luminova Framework filesystem and memcache base class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Base;

use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\CacheException;
use \Closure;
use \DateTimeInterface;
use \DateInterval;

abstract class BaseCache
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
     * Hold the cache hashed storage name.
     * 
     * @var string|null $storage
     */
    protected ?string $storage = null;

    /**
     * Hold the un-hashed cache storage name.
     * 
     * @var string|null $storageName
     */
    protected ?string $storageName = null;

    /**
     * Hold the cache details array.
     * 
     * @var array $items
     */
    protected array $items = [];

    /**
     * Hold the cache details array.
     * 
     * @var array $iterator
     */
    protected array $iterator = [];

    /**
     * Hold the cache array iterator position.
     * 
     * @var int $position
     */
    protected int $position = 0;

     /**
     * Hold the cache expiry time. 
     * 
     * @var int|null $expiration
     */
    protected int|null $expiration = 0;

    /**
     * Hold the cache expiry time after.
     * 
     * @var int|null $expireAfter
     */
    protected ?int $expireAfter = null;

     /**
     * Lock cache from deletion.
     * 
     * @var bool $lock
     */
    protected bool $lock = false;

    /**
     * Hold the cache serialization flag. 
     * 
     * @var int $serialize
     */
    protected int $serialize = 1;

    /**
     * Hold the cache base64 enabling option. 
     * 
     * @var bool $encoding
     */
    protected bool $encoding = false;

     /**
     * Hold the cache expiry delete option.
     * 
     * @var bool $autoDeleteExpired
     */
    protected bool $autoDeleteExpired = false;

    /**
     * Hold the cache expiry delete option.
     * 
     * @var bool $includeLocked
     */
    protected bool $includeLocked = false;

    /**
     * Initialize the base cache class.
     * You don't have to extend this class directly except if you want to implement your own caching class.
     */
    public function __construct()
    {
        $this->serialize = (function_exists('igbinary_serialize') ? 2 : 1);
	}

    /**
     * Retrieves or creates a singleton instance of the cache.
     * 
     * @param string|null $storage The name of the cache storage. If null, you must call the `setStorage` method later.
     * @param string|null $idOrSubfolder Optional:
     *  - For Memcached: A unique persistent connection ID. If null, the default ID from environment variables is used, or "default" if not set.
     *  - For Filesystem Cache: A subdirectory within the cache directory. If null, defaults to the base cache directory.
     * 
     * @return static The singleton instance of the cache.
     * @throws CacheException If there is an issue initializing the cache.
     * @throws InvalidArgumentException If an invalid subdirectory is provided for the filesystem cache.
     */
    abstract public static function getInstance(
        ?string $storage = null, 
        ?string $idOrSubfolder = null
    ): static;
 
    /**
     * Get the cache storage name.
     * 
     * @return string|null Return the cache storage name.
     */
    public function getStorage(): ?string 
    {
        return $this->storageName;
    }

    /**
     * Sets the cache storage name.
     * 
     * @param string $storage The cache storage name.
     * 
     * @return self Return instance of memory cache class.
     * @throws CacheException Throws if cannot load cache or unable to process items.
     * @throws InvalidArgumentException Throws if empty cache storage name is provided.
     */
    abstract public function setStorage(string $storage): self;

    /**
     * Generate hash storage name for cache.
     * This method will generate a hash value of storage name which is same as the one used in storing cache items.
     * 
     * @param string $storage The cache storage name to hash.
     * 
     * @return string Return a hashed value of the specified storage name.
     * @throws InvalidArgumentException Throws if empty cache storage name is provided.
     */
    public static function hashStorage(string $storage): string 
    {
        if(!$storage){
            throw new InvalidArgumentException('Invalid, storage cannot be empty string.');
        }

        $storage = preg_replace('/[^a-zA-Z0-9-_]/', '-', $storage);
        //return hash('sha256', $storage);
        return md5($storage);
    }

    /**
     * Sets the expiration time of the cache item.
     *
     * @param DateTimeInterface|int|null $expiration The expiration time of the cache item.
     * 
     * @return self Return instance of memory cache class.
     * 
     * > **Note:** When set to null, the item will never expire, when set to 0, it automatically expires immediately.
     */
    public function setExpire(DateTimeInterface|int|null $expiration): self
    {
        $this->expireAfter = null;

        if($expiration instanceof DateInterval){
            $this->expiration = Timestamp::ttlToSeconds($expiration);
            return $this;
        }

        $this->expiration = $expiration;
        return $this;
    }

    /**
     * Sets the expiration time of the cache item relative to the current time.
     *
     * @param DateInterval|int|null $after The expiration time in seconds or as a DateInterval.
     * 
     * @return self Return instance of memory cache class.
     */
    public function expiresAfter(DateInterval|int|null $after): self
    {
        if($after instanceof DateInterval){
            $this->expireAfter = Timestamp::ttlToSeconds($after);
            return $this;
        }

        $this->expireAfter = $after;
        return $this;
    }

    /**
     * Sets the cache lock to avoid deletion even when cache has expired.
     * 
     * @param bool $lock lock flag to be used.
     * 
     * @return self Return instance of memory cache class.
     */
    public function setLock(bool $lock): self 
    {
        $this->lock = $lock;

        return $this;
    }

    /**
     * Set enable or disable automatic deletion for expired caches.
     * 
     * @param bool $allow The deletion flag.
     * @param bool $includeLocked Optional specify whether to also delete locked caches (default: true).
     * 
     * @return self Return instance of file cache class.
     */
    public function enableDeleteExpired(bool $allow, bool $includeLocked = false): self 
    {
        $this->autoDeleteExpired = $allow;
        $this->includeLocked = $includeLocked;

        return $this;
    }

    /**
     * Retrieve the cache content, using the closure callback to update the content with new item if cache has expired.
     * The closure is used to update new content when cache is expired, so the closure won't be executed until cache expires or empty.
     * 
     * @param string $key The cache key, non-empty string.
     * @param Closure $callback Callback function to refreshed cache when expired.
     *      -   The callback must return content to be cached.
     * 
     * @return mixed Return cache content currently stored under key.
     * @throws CacheException Throws if called without any storage name specified or unable to store cache.
     * @throws InvalidArgumentException Throws if empty cache key is provided.
     * 
     * > **Note:** This method uses the expiration set using `setExpire`, `expiresAfter` and locking for `setLock` method.
     * > Additionally, this method will return only the the cache contents, metadata will be included.
     */
    public function onExpired(string $key, Closure $callback): mixed 
    {
        $this->assertStorageAndKey($key);

        // Return item immediately. 
		if($this->expiration === null && ($this->expireAfter === null || $this->expireAfter === 0)){
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
     * Retrieve cache items for the given keys. If items are successfully retrieved, an optional callback can be invoked 
     * with the retrieved data. Use `getItems` or `getNext` to access the retrieved items, or call `reset` to reset the 
     * iterator position to the start.
     * 
     * @param string[] $keys The array of cache keys to retrieve.
     * @param bool $withCas Optional memcached specific feature. If true, CAS tokens will be used for retrieval (default: false).
     * @param callable|null $callback Optional callback function. If provided, it will be called with each retrieved 
     *                                 item. The callback should accept two parameters: the cache instance and an array 
     *                                 representing the retrieved cache item.
     * 
     * @return bool Returns true if the items were successfully retrieved, false otherwise.
     * @throws InvalidArgumentException Throws if keys is empty array.
     * 
     * > **Note:** 
     * > The callback function should accept two parameters: the cache instance and an array of the retrieved cache item. 
     * > To modify the item and have the changes reflected in `getItems` or `getNext`, pass the item array by reference.
     * 
     * @example
     * 
     * In this example, the callback converts the cache item value to uppercase before it is stored in the iterator.
     * 
     * ```php
     * function myCallback($instance, array &$result) {
     *     $result['value'] = strtoupper($result['value']);
     * }
     * ```
     */
    abstract public function execute(
        array $keys, 
        bool $withCas = false, 
        ?callable $callback = null
    ): bool;

    /**
     * Replace cache content with new data and expiration if necessary.
     * This method replaces the existing cache item identified by the provided key with new content. 
     * 
     * @param string $key The cache key to replace content.
     * @param mixed $content The new content to update.
     * 
     * @return bool Return true if item was successfully updated, otherwise false.
     * @throws CacheException Throws if called without any storage name specified or unable to store cache.
     * @throws InvalidArgumentException Throws if empty cache key is provided.
     * 
     * > **Note:** This method uses the expiration set using `setExpire`, `expiresAfter` and locking for `setLock` method.
     */
    public function replace(string $key,  mixed $content): bool 
    {
        $this->assertStorageAndKey($key);

        // If not expiration set, then not need to refresh.
        if (
            ($this->expiration === null && ($this->expireAfter === null || $this->expireAfter === 0)) ||
            empty($content) || 
            !$this->hasItem($key)
        ) {
            return false;
        }

        return $this->setItem($key, $content, $this->expiration, $this->expireAfter, $this->lock);
    }

    /**
     * Retrieve cache content from storage.
     * 
     * This method retrieves the cache content associated with the specified key. 
     * You can choose to return only the cached data or include metadata.
     * 
     * @param string $key The cache key to retrieve content for.
     * @param bool $onlyContent Whether to return only the cache content or include metadata (default: true).
     * 
     * @return mixed Returns the cache content if the key is valid and not expired; 
     *               otherwise, returns null if `$onlyContent` is true.
     * 
     * @throws CacheException Throws if called without any storage name specified or error occurred while reading cache.
     * @throws InvalidArgumentException Throws if empty cache key is provided.
     */
    abstract public function getItem(string $key, bool $onlyContent = true): mixed;

    /**
     * Retrieves all items from the delay method.
     *
     * Returns an array of all items if the iterator contains items; returns false otherwise.
     * 
     * @return array|false Return an array of items or false if no items are available.
     * 
     * > **Note:** Before calling this method, you must first call the `delay` method.
     */
    public function getItems(): array|bool
    {
        return $this->iterator === []
            ? false
            : $this->iterator;
    }

    /**
     * Retrieves the next item from the delay method if available.
     * 
     * @return array|false Return the next item or false if there are no more items.
     * 
     * > **Note:** before calling this method, you must first call the `delay` method.
     */
    public function getNext(): array|bool
    {
        if ($this->iterator === [] || $this->position > count($this->iterator)) {
            return false;
        }

        return $this->iterator[$this->position++] ?? false;
    }

    /**
     * Resets the execute iterator to the first item.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->position = 0;
    }

    /**
     * Save item to cache storage.
     * 
     * @param string $key The cache key to use.
     * @param mixed $content The contents to store in cache.
     * 
     * @return bool Return true if cache was saved, otherwise false.
     * @throws CacheException Throws if called without any storage name specified or unable to store cache.
     * @throws InvalidArgumentException Throws if empty cache key is provided.
     * 
     * > **Note:** This method uses the expiration set using `setExpire`, `expiresAfter` and locking for `setLock` method.
    */
    public function set(string $key, mixed $content): bool 
    {
		if(
            $this->expiration === null && 
            ($this->expireAfter === null || $this->expireAfter === 0) || 
            empty($content)
        ){
            return false;
        }
       
        return $this->setItem($key, $content, $this->expiration, $this->expireAfter, $this->lock);
    }

    /**
     * Save an item to cache storage.
     * 
     * This method stores the specified data in the cache associated with the given key. 
     * It supports optional expiration and locking mechanisms. Data can be serialized and 
     * optionally encoded based on the configuration.
     * 
     * @param string $key The cache key to use.
     * @param mixed $content The content to store in the cache.
     * @param DateTimeInterface|int|null $expiration The cache expiration time. 
     *        If a DateTimeInterface or integer is provided, it will set the cache to expire 
     *        after the specified duration. If 0, the cache does not expire.
     * @param DateInterval|int|null $expireAfter Specifies the exact cache expiration time after 
     *        a certain period. If null, it uses the $expiration parameter.
     * @param bool $lock Whether to lock the cache item to prevent deletion even when it expires (default: false).
     * 
     * @return bool Returns true if the cache was successfully saved; otherwise, false.
     * @throws CacheException Throws if called without any storage name specified or unable to store cache.
     * @throws InvalidArgumentException Throws if empty cache key is provided.
     */
    abstract public function setItem(
        string $key, 
        mixed $content, 
        DateTimeInterface|int|null $expiration = 0, 
        DateInterval|int|null $expireAfter = null, 
        bool $lock = false
    ): bool;

    /**
     * Check if a cache key exists in storage.
     * This method verifies if the cache item identified by the given key exists in the cache storage, 
     * it doesn't check expiration.
     * 
     * @param string $key The cache key to check.
     * 
     * @return bool Returns true if the cache key exists, otherwise false.
     */
    abstract public function hasItem(string $key): bool;

    /**
     * Check if a cache item is locked to prevent deletion.
     * 
     * This method determines if the cache item identified by the given key is locked, 
     * which prevents it from being deleted. If the cache item does not exist, it 
     * is considered locked by default.
     * 
     * @param string $key The cache key to check.
     * 
     * @return bool Returns true if the item is locked or does not exist, otherwise, false.
     */
    abstract public function isLocked(string $key): bool;

    /**
     * Determine if a cache item has expired.
     * 
     * This method checks if the cache item identified by the given key has expired 
     * based on its timestamp and expiration settings.
     * 
     * @param string $key The cache key to check for expiration.
     * 
     * @return bool Returns true if the cache item has expired, otherwise false.
     * 
     * > **Note:** If the cache key does not exist, it is considered expired and return true.
     */
    abstract public function hasExpired(string $key): bool;

    /**
     * Delete a specific cache item by key.
     * 
     * This method removes the cache item associated with the given key. 
     * 
     * @param string $key The cache key of the item to delete.
     * @param bool $includeLocked Whether to delete this item if it's locked (default: false).
     * 
     * @return bool Returns true if the cache item was successfully deleted, otherwise false.
     * @throws CacheException Throws if called without any storage name specified or unable to store cache.
     */
    abstract public function deleteItem(string $key, bool $includeLocked = false): bool;

    /**
     * Delete multiple cache items by their keys.
     * 
     * This method removes multiple cache items specified by an array of keys. 
     * 
     * @param iterable<string> $keys The array of cache keys to delete.
     * @param bool $includeLocked Whether to delete the item if it's locked (default: false).
     *                      - Setting this parameter to true will apply to all keys.
     * 
     * @return bool Returns true if at least one cache item was successfully deleted; otherwise, false.
     * @throws CacheException Throws if called without any storage name specified or unable to store cache.
     */
    abstract public function deleteItems(iterable $keys, bool $includeLocked = false): bool;

    /**
     * Invalidate all cached items in the current cache server or cache storage directory.
     * It also resets the internal pre-cached instance array to an empty state.
     * 
     * @return bool Returns true if the cache was successfully flushed, otherwise false.
     */
    abstract public function flush(): bool;

    /**
     * Clear all cached items in the current storage name.
     * It also resets the internal pre-cached instance array to an empty state.
     * 
     * @return bool Returns true if the cache was successfully cleared, otherwise false.
     */
    abstract public function clear(): bool;

    /**
     * Delete multiple cache items associated with given keys from a specified storage.
     * 
     * This method removes cache items based on their keys from the specified storage, which 
     * is hashed to ensure proper key distribution. 
     * 
     * @param string $storage The storage identifier for which cache items should be deleted.
     * @param string[] $keys The array of cache keys to be deleted.
     * 
     * @return bool Returns true if the cache items were successfully deleted; otherwise, false.
     * @throws CacheException Throws if error occurred while deleting items.
     * 
     * > **Note:** This method will remove item even if its was locked to prevent deletion.
     */
    abstract public function delete(string $storage, array $keys): bool;

    /**
     * Delete all expired cache items.
     * 
     * This method removes all cache items that have expired, based on their 
     * expiration timestamps. Optionally, it can include locked items in the 
     * deletion process.
     * 
     * @return void
     * @internal
     * @ignore
     */
    abstract protected function deleteIfExpired(): void;

    /**
     * Fetch cache data from storage.
     * 
     * @param string|null $key The cache key to fetch for memcached only (default: null).
     * 
     * @return bool Returns true if cache data is successfully retrieved, otherwise false.
     * @throws CacheException Safe throws if cannot load cache or unable to process items.
     * @internal
     * @ignore
     */
    abstract protected function read(?string $key = null): bool;

    /**
     * Write cache data to storage.
     * 
     * This method attempts to save all cache items to the disk. If an exception occurs, the error
     * is logged in production environments, or an exception is thrown in other environments.
     * 
     * @return bool Returns true if the cache data was successfully written, otherwise false.
     * @throws CacheException Throws if storage path is not readable or writable.
     * @internal
     * @ignore
     */
    abstract protected function commit(): bool;

    /**
     * Custom serialization function.
     *
     * @param mixed $data The data to serialize.
     * 
     * @return string|false The serialized data.
     * @internal
     * @ignore
     */
    protected function enSerialize(mixed $data): string|bool
    {
        if ($this->serialize === 2) {
            return igbinary_serialize($data);
        }

        return serialize($data);
    }

    /**
     * Custom deserialization function.
     *
     * @param string $data The serialized data.
     * @param int $serialize The serialization used when storing item.
     * 
     * @return mixed The unserialized data.
     * @internal
     * @ignore
     */
    protected function deSerialize(string $data, int $serialize = 1): mixed
    {
        if ($this->serialize === 2 || $serialize === 2) {
            return igbinary_unserialize($data);
        }

        return unserialize($data);
    }

    /**
     * Assert that the cache storage and key are valid.
     *
     * @param string|array $key The cache item key.
     * @return void
     * @throws InvalidArgumentException If an invalid or empty key is specified.
     * @throws CacheException If no storage is specified.
     * @internal
     * @ignore
     */
    protected function assertStorageAndKey(string|array $key): void 
    {
        if ($key === '' || $key === []) {
            $message = is_array($key) 
                ? 'Cache keys cannot be an empty array.' 
                : 'Cache key cannot be an empty string.';
            throw new InvalidArgumentException("Invalid argument: {$message}");
        }

        if (!$this->storage) {
            throw new CacheException('No cache storage specified. Use the setStorage method to define storage.');
        }
    }

     /**
     * Generate an empty response for cache retrieval.
     * 
     * @param bool $onlyContent Whether to return only content or include metadata.
     * 
     * @return ?array Returns null if $onlyContent is true, otherwise returns an array with default values.
     * @internal
     * @ignore
     */
    protected function respondWithEmpty(bool $onlyContent = true): ?array
    {
        if ($onlyContent) {
            return null;
        }

        return [
            'timestamp' => null,
            'expiration' => 0,
            'expireAfter' => null,
            'data' => null,
            'lock' => false,
            'encoding' => $this->encoding ? 'base64' : 'raw',
            'decoded' => true,
            'serialize' => $this->serialize
        ];
    }

    /**
     * Determine if a cache result has expired based on its timestamp and expiration settings.
     * 
     * @param array<string,mixed>|null $result The cache item details including timestamp and expiration settings.
     * 
     * @return bool Returns true if the cache result has expired, otherwise false.
     * @internal
     * @ignore
     */
    protected function isExpired(array|null $result): bool 
    {
        if($result === null){
            return true;
        }

        $expiration = $result['expiration'] ?? null;
        $expireAfter = $result['expireAfter'] ?? null;

        if($expiration === null && $expireAfter === null){
            return false;
        }

        $now = time();

        return (
            ($expiration && ($now - $result['timestamp']) >= $expiration) ||
            ($expireAfter && ($now - $result['timestamp']) >= $expireAfter)
        );
    }
}