<?php 
/**
 * Luminova Framework filesystem and memcache base class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base;

use \Throwable;
use \DateInterval;
use \DateTimeInterface;
use Luminova\Base\Helper\CacheTrait;
use Luminova\Interface\LazyObjectInterface;
use Luminova\Exceptions\{CacheException, InvalidArgumentException};

abstract class Cache implements LazyObjectInterface
{
    use CacheTrait;

    /**
     * Initialize the base cache system.
     */
    public function __construct()
    {
        $this->isConnected = false;

        $this->setSerializerOption(
            (int) env('system.cache.encoding.serializer', self::SERIALIZER_NONE),
            (bool) env('system.cache.encoding.base64', false)
        )->setSerializer();
    }

    /**
     * Establish (or reuse) a connection to the cache backend.
     *
     * Creates and configures the cache client when necessary, then registers the
     * configured server(s). The connection is pooled under the persistent identifier
     * so that it can be reused across requests.
     *
     * @return bool Returns true if the connection was established or already active.
     *
     * @throws CacheException If the client cannot be initialized or the server(s) cannot be reached.
     */
    abstract public function connect(): bool;

    /**
     * Close the connection to the cache backend.
     *
     * Gracefully terminates the active connection. If no connection is open, returns true immediately.
     *
     * @return bool Always returns true.
     */
    abstract public function disconnect(): bool;

    /**
     * Retrieve a single cache item by key.
     *
     * Returns the stored value when the key exists and has not expired.
     * When `$withMetadata` is true, returns a metadata array instead of the raw value.
     *
     * @param string $key Cache key to retrieve (must be non-empty).
     * @param bool $withMetadata When false, returns only the cached value;
     *                             when true, returns a metadata array (default: false).
     *
     * @return mixed Cached value, or null when the key is missing or expired and `$withMetadata` is true.
     *               Metadata array is returned instead.
     *
     * @throws CacheException         If no storage is configured or a read error occurs.
     * @throws InvalidArgumentException If the key is empty.
     */
    abstract public function getItem(string $key, bool $withMetadata = false): mixed;

    /**
     * Retrieve multiple cache items by key.
     *
     * @param string[] $keys Cache keys to retrieve.
     * @param bool $withMetadata When false, returns only cached values keyed by cache key;
     *                              when true, returns metadata arrays (default: false).
     *
     * @return array<string,mixed> Retrieved items, or an empty array when none are found.
     */
    abstract public function getItems(array $keys, bool $withMetadata = false): array;

    /**
     * Retrieve multiple cache item keys by pattern.
     *
     * @param string|null $pattern Cache key or key pattern (e.g, `admin:users`).
     *          If null return all keys.
     *
     * @return string[] Retrieved item keys, or an empty array when none are found.
     */
    abstract public function getKeys(?string $pattern = null): array;

    /**
     * Initiate an asynchronous-style fetch of multiple cache items.
     *
     * Queues the requested keys for retrieval. Results are accessed afterward via
     * `fetchNext()` (one item at a time) or `fetchResult()` (all at once).
     * When `$onItem` is provided, it is called for each item during the fetch and
     * the internal iterator is not populated.
     *
     * @param string[] $keys Cache keys to retrieve.
     * @param bool $withCas When true, includes CAS tokens in results
     *                   (Memcached only; ignored by other drivers) (default: false).
     * @param (callable(static, array): void)|null $onItem  Callback invoked per retrieved item.
     *                        Receives the cache instance and the item array.
     *                        When provided, `fetchNext()` and `fetchResult()`
     *                        will have no queued results (default: null).
     *
     * @return bool Returns true if the fetch was initiated and at least one key was found, otherwise false.
     *
     * @throws InvalidArgumentException If `$keys` is empty.
     */
    abstract public function getDelayed(
        array $keys,
        bool $withCas = false,
        ?callable $onItem = null
    ): bool;

    /**
     * Store a single item in the cache.
     *
     * @param string $key Cache key (must be non-empty).
     * @param mixed $content Value to store.
     * @param DateTimeInterface|int|null $expiration Absolute expiry time or TTL in seconds.
     *                      Use 0 for no expiration (default: 0).
     * @param DateInterval|int|null $expireAfter Relative TTL in seconds or as a DateInterval.
     *                              When set, takes precedence over `$expiration` (default: null).
     * @param bool $lock When true, the item cannot be deleted even after expiry (default: false).
     *
     * @return bool Returns true on success, false on failure.
     *
     * @throws CacheException         If no storage is configured or the write fails.
     * @throws InvalidArgumentException If the key is empty.
     */
    abstract public function setItem(
        string $key,
        mixed $content,
        DateTimeInterface|int|null $expiration = 0,
        DateInterval|int|null $expireAfter = null,
        bool $lock = false
    ): bool;

    /**
     * Check whether a cache key exists in storage, regardless of expiration.
     *
     * @param string $key Cache key to check.
     *
     * @return bool Returns true if the key exists, otherwise false.
     */
    abstract public function hasItem(string $key): bool;

    /**
     * Count how many of the given keys exist in the cache.
     *
     * @param string|string[] $keys A single key or an array of keys to check.
     *
     * @return int Number of keys found; 0 when none exist.
     */
    abstract public function exists(string|array $keys): int;

    /**
     * Determine whether a cache item is locked against deletion.
     *
     * A locked item cannot be removed by normal delete operations.
     * The behavior when the key does not exist is driver-specific:
     * `MemoryCache` returns true (treats missing as locked), while
     * `RedisCache` returns false.
     *
     * @param string $key Cache key to check.
     *
     * @return bool Returns true if the item is locked, false if unlocked or (for some drivers) missing.
     */
    abstract public function isLocked(string $key): bool;

    /**
     * Determine whether a cache item has expired.
     *
     * Returns true if the key does not exist or its TTL has elapsed.
     *
     * @param string $key Cache key to check.
     *
     * @return bool Returns true if the item is expired or missing, false if still valid.
     */
    abstract public function hasExpired(string $key): bool;

    /**
     * Delete a single cache item by key.
     *
     * Locked items are skipped unless `$gcExpiredLocks` is true.
     * Returns true when the key does not exist (no-op is considered success).
     *
     * @param string $key Cache key to delete.
     * @param bool $gcExpiredLocks  When true, deletes the item even if it is locked (default: false).
     *
     * @return bool Returns true if the item was deleted or did not exist, false on failure.
     *
     * @throws CacheException If no storage is configured or the delete operation fails.
     */
    abstract public function deleteItem(string $key, bool $gcExpiredLocks = false): bool;

    /**
     * Delete multiple cache items by key.
     *
     * Locked items are skipped unless `$gcExpiredLocks` is true.
     *
     * @param iterable<string> $keys  Array of cache keys to delete.
     * @param bool $gcExpiredLocks When true, deletes items even if they are locked.
     *                                              Applies to all supplied keys (default: false).
     *
     * @return bool Returns true if at least one item was deleted, false otherwise.
     *
     * @throws CacheException If no storage is configured or the delete operation fails.
     */
    abstract public function deleteItems(iterable $keys, bool $gcExpiredLocks = false): bool;

    /**
     * Flush all cached items across all storages managed by this driver instance.
     *
     * The scope of flush is driver-specific: `FileCache` deletes the entire cache
     * root directory; `MemoryCache` calls `Memcached::flush()`; `RedisCache` calls
     * `FLUSHDB` on the configured database. The in-process item pool is also cleared.
     *
     * @return bool Returns true on success, false on failure.
     */
    abstract public function flush(): bool;

    /**
     * Remove all cached items belonging to the current storage context.
     *
     * Only items under the active storage namespace are removed; other namespaces
     * within the same backend are unaffected. The in-process item pool is also cleared.
     *
     * @return bool Returns true on success, false on failure.
     */
    abstract public function clear(): bool;

    /**
     * Delete specific keys from a given storage namespace.
     *
     * When `$storage` is null, the current instance storage is used.
     * Items are removed regardless of their lock state.
     *
     * @param string[] $keys Cache keys to delete.
     * @param string|null $storage Target storage name. When null, the current storage is used (default: null).
     *
     * @return bool Returns true if all targeted items were deleted, false otherwise.
     *
     * @throws CacheException If a deletion error occurs.
     */
    abstract public function delete(array $keys, ?string $storage = null): bool;

    /**
     * Delete expired items from the current storage context.
     *
     * Iterates the in-process item pool and removes entries whose TTL has elapsed.
     * Locked items are skipped unless `$gcExpiredLocks` is enabled.
     * Items removed from the backend are also removed from the local pool.
     *
     * @return int Number of items deleted.
     */
    abstract protected function deleteIfExpired(): int;

    /**
     * Load a cache entry from the backend into the local item pool.
     *
     * Implementations should populate `$this->items` with the deserialized payload.
     *
     * @param string|null $key Cache key to read, or null to load all items for the
     *                         current storage (driver behavior may vary) (default: null).
     *
     * @return bool Returns true if the key was found and loaded, false otherwise.
     *
     * @throws CacheException If a connection or read error occurs.
     */
    abstract protected function read(?string $key = null): bool;

    /**
     * Persist the current in-process item pool to the cache backend.
     *
     * Implementations encode each item and write it to the backend.
     * On error, the behavior is environment-dependent: logged silently in production,
     * re-thrown as a `CacheException` in development.
     *
     * @param int &$commits Reference variable incremented by the number of successfully written items (default: 0).
     *
     * @return bool Returns true if at least one item was committed, false otherwise.
     *
     * @throws CacheException If a write error occurs in a non-production environment.
     */
    abstract protected function commit(int &$commits = 0): bool;

    /**
     * Iterate over cache keys matching a pattern.
     *
     * - `Redis` - Uses SCAN to avoid blocking redis like KEYS
     * - `Memcached` - Uses getAllKeys with chunk operation and 1000ms delay per chunk.
     * - `Filecache` - Checks for key in current storage file with chunk operation and 1000ms delay per chunk.
     * 
     * @param string  $pattern  Key pattern (e.g. "users:*").
     * @param (callable(string $key): void) $onFoundKey Callback invoked for each key.
     *
     * @return int Return number of found keys.
     * @see self::getKeys()
     */
    abstract public function scan(string $pattern, callable $onFoundKey): int;

    /**
     * Disconnect from and reconnect to the cache backend.
     *
     * @return bool Returns true if the reconnection succeeded, false otherwise.
     */
    public function reconnect(): bool 
    {
        $this->disconnect();

        return $this->connect();
    }

    /**
     * Ping the cache backend to verify it is reachable.
     *
     * @return string|null Returns `'PONG'` if the server responds, null otherwise.
     */
    public function ping(): ?string
    {
        return null;
    }

    /**
     * Get the active persistent connection identifier.
     *
     * @return string|null Returns the persistent ID, or null if not applicable for this driver.
     */
    public function getPersistentId(): ?string
    {
        return $this->persistentId;
    }

    /**
     * Get the original (un-hashed) cache storage name.
     *
     * @return string|null Returns the storage name, or null if not yet set.
     */
    public function getStorage(): ?string 
    {
        return $this->storageName;
    }

    /**
     * Get the full filesystem path of the current cache storage file.
     *
     * Applicable to `FileCache` only; other drivers return null.
     *
     * @return string|null Returns the absolute file path, or null if not applicable.
     */
    public function getPath(): ?string 
    {
        return null;
    }

    /**
     * Get the root directory used for cache file storage.
     *
     * Applicable to `FileCache` only; other drivers return null.
     *
     * @return string|null Returns the absolute directory path, or null if not applicable.
     */
    public function getRoot(): ?string 
    {
        return null;
    }

    /**
     * Get the active backend connection instance.
     *
     * @return \Luminova\Storage\Stream|\Memcached|\Redis|null Returns the connection object,
     *             or null if not connected.
     */
    public function getConn(): ?object
    {
        return null;
    }

    /**
     * Set the active cache storage namespace.
     *
     * The name is normalized and hashed internally. If the new name differs from the
     * current one, the connection is marked as disconnected; call `connect()` or
     * `reconnect()` afterward.
     *
     * @param string $storage Storage name (must be non-empty).
     *
     * @return self Returns the current instance.
     *
     * @throws CacheException  If a load error occurs during storage assignment.
     * @throws InvalidArgumentException If an empty string is provided.
     *
     * @see self::connect()
     * @see self::reconnect()
     * @see self::isConnected()
     */
    public function setStorage(string $storage): self
    {
        if(!$storage){
            return $this;
        }

        $this->isConnected = $this->isConnected && $storage === $this->storageName;

        $this->storage = self::hashStorage($storage);
        $this->storageName = $storage;
        return $this;
    }

    /**
     * Set the persistent connection identifier.
     *
     * If the new ID differs from the active one, the connection is marked as
     * disconnected; call `connect()` or `reconnect()` afterward.
     * No-op in the base implementation; overridden by connection-pooling drivers.
     *
     * @param string $persistentId New connection identifier.
     *
     * @return self Returns the current instance.
     *
     * @throws InvalidArgumentException If an invalid ID provided.
     *
     * @see self::connect()
     * @see self::reconnect()
     * @see self::isConnected()
     * 
     * > **Note:**
     * > Persistent ID on filecache is used as a sub-directory for storage.
     */
    public function setPersistentId(string $persistentId): self 
    {
        $persistentId = trim($persistentId);
        $persistentId = ($persistentId == '') ? null : $persistentId;

        if($persistentId !== null){
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $persistentId)) {
                throw new InvalidArgumentException(
                    'Persistent ID may only contain letters, numbers, underscores, and hyphens.'
                );
            }
        }

        $this->isConnected =  $this->isConnected && $this->persistentId === $persistentId;
        $this->persistentId = $persistentId;
        return $this;
    }

    /**
     * Check whether a backend connection is currently active.
     *
     * @return bool Returns true if connected, false otherwise.
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * Check whether a `getDelayed()` result set is available for fetching.
     *
     * @return bool Returns true if results are ready, false otherwise.
     */
    public function isResult(): bool
    {
        return $this->isResult;
    }

    /**
     * Configure the serialization policy and optional base64 encoding.
     *
     * Accepted policy values:
     * - `static::SERIALIZER_NONE`     - None, allow cache server to handle serializing.
     * - `static::SERIALIZER_PHP_AUTO` — auto-select igbinary or PHP serialize.
     * - `static::SERIALIZER_PHP`      — PHP native serialize.
     * - `static::SERIALIZER_IGBINARY` — igbinary binary format.
     * - `static::SERIALIZER_JSON`     — JSON encoding for cross-platform portability.
     *
     * @param int $serializer Serialization policy constant (e.g. `static::SERIALIZER_IGBINARY`).
     * @param bool $base64Encode When true, the serialized payload is base64-encoded before storage (default: false).
     *
     * @return self Returns the current instance.
     */
    public function setSerializerOption(int $serializer, bool $base64Encode = false): self 
    {
        $this->serializer = $serializer;
        $this->base64Encode = $base64Encode;
        return $this;
    }

    /**
     * Generate the hashed storage identifier used for key namespacing.
     *
     * Normalizes the name (replacing non-alphanumeric characters with dashes)
     * and returns its XXH3 hash. The result is the same value stored in `$this->storage`.
     *
     * @param string $storage Storage name to hash (must be non-empty).
     *
     * @return string Return an XXH3 hash of the normalized storage name.
     *
     * @throws InvalidArgumentException If `$storage` is empty.
     */
    public static function hashStorage(string $storage): string 
    {
        $storage = trim($storage);

        if($storage === ''){
            throw new InvalidArgumentException(
                'Invalid, storage cannot be empty string.'
            );
        }

        return hash('xxh3', $storage);
    }

    /**
     * Set an absolute expiration time for subsequent cache operations.
     *
     * Clears any previously configured relative expiration (`expiresAfter`).
     * When set to null, items never expire. When set to 0, items expire immediately.
     *
     * @param DateTimeInterface|int|null $expiration Absolute expiry timestamp, TTL in seconds,
     *                                               or null for no expiration (default: null).
     *
     * @return self Returns the current instance.
     */
    public function setExpire(DateTimeInterface|int|null $expiration = null): self
    {
        $this->expiration = $this->ttlToSeconds($expiration);

        if($this->expiration !== null){
            $this->expireAfter = null;
        }

        return $this;
    }

    /**
     * Set a relative expiration duration for subsequent cache operations.
     *
     * Clears any previously configured absolute expiration (`setExpire`).
     * When set, takes precedence over `$expiration` in `setItem()`.
     *
     * @param DateInterval|int|null $after Relative TTL in seconds or as a DateInterval,
     *                                     or null for no expiration (default: null).
     *
     * @return self Returns the current instance.
     */
    public function expiresAfter(DateInterval|int|null $after = null): self
    {
        $this->expireAfter = $this->ttlToSeconds($after);

        if($this->expireAfter !== null){
            $this->expiration = null;
        }

        return $this;
    }

    /**
     * Set the lock flag for subsequent cache write operations.
     *
     * Locked items cannot be deleted by normal delete operations.
     *
     * @param bool $lock Lock state to apply.
     *
     * @return self Returns the current instance.
     */
    public function setLock(bool $lock): self 
    {
        $this->lock = $lock;
        return $this;
    }

    /**
     * Configure garbage collection behavior.
     *
     * Controls automatic removal of expired cache entries, including:
     * - enabling/disabling GC
     * - whether locked entries can be removed
     * - GC execution interval
     *
     * @param bool $gcEnable Enable or disable automatic garbage collection.
     * @param bool $gcExpiredLocks When true, locked expired items are also removed (default: false).
     * @param int $interval Minimum interval between GC runs in seconds.
     *                                   Values below 1 are clamped to 1 (default: 300).
     *
     * @return self Returns the current instance.
     */
    public function configureGarbageCollection(
        bool $gcEnable,
        bool $gcExpiredLocks = false,
        int $interval = 300
    ): self 
    {
        $this->autoGarbageCollection = $gcEnable;
        $this->garbageCollectExpiredLocks = $gcExpiredLocks;
        $this->gcInterval = max(1, $interval);

        return $this;
    }

    /**
     * Replace the content of an existing cache item.
     *
     * Uses the expiration and lock settings configured via `setExpire()`,
     * `expiresAfter()`, and `setLock()`. Returns false when: the content is
     * empty, no expiration policy is set, or the key does not exist.
     *
     * @param string $key Cache key to update (must be non-empty).
     * @param mixed  $content New content to store.
     *
     * @return bool Returns true if the item was updated, false otherwise.
     *
     * @throws CacheException  If no storage is configured or the write fails.
     * @throws InvalidArgumentException If the key is empty.
     */
    public function replace(string $key, mixed $content): bool 
    {
        $this->assertStorageAndKey($key);

        // If not expiration set, then not need to refresh.
        if (
            empty($content)
            || !$this->hasExpirationPolicy()
            || !$this->hasItem($key)
        ) {
            return false;
        }

        return $this->setItem(
            $key, 
            $content, 
            $this->expiration, 
            $this->expireAfter, 
            $this->lock
        );
    }

    /**
     * Determine whether an expiration policy is currently configured.
     *
     * Returns true when either an absolute expiration or a positive relative TTL
     * is set. Used internally to guard operations that require time-bounded cache entries.
     *
     * @return bool Returns true if an expiration policy is active, false otherwise.
     */
    public function hasExpirationPolicy(): bool
    {
        return $this->expiration !== null
            || ($this->expireAfter !== null && $this->expireAfter > 0);
    }

    /**
     * Retrieve all items queued by the last `getDelayed()` call.
     *
     * Must be called after `getDelayed()`. The base implementation returns an empty
     * array; overriding drivers populate results from the internal iterator.
     *
     * @return array<int,array<string,mixed>> Retrieved items, or an empty array if none are available.
     *
     * @see self::getDelayed()
     * @see self::fetchNext()
     * @see self::isResult()
     */
    public function fetchResult(): array
    {
        return [];
    }

    /**
     * Retrieve the next item from the iterator populated by `getDelayed()`.
     *
     * Each call advances the internal cursor. Returns null when no more items
     * are available. The base implementation always returns null; overriding
     * drivers supply results from the internal iterator.
     *
     * @return array<string,mixed>|null Next cache item, or null when exhausted.
     *
     * @see self::getDelayed()
     * @see self::fetchResult()
     * @see self::isResult()
     */
    public function fetchNext(): ?array
    {
        return null;
    }

    /**
     * Retrieve a single cache item by key without metadata.
     *
     * Delegates to `getItem()`
     *
     * @param string $key Cache key to retrieve (must be non-empty).
     *
     * @return mixed Cached value, or null when the key is missing or expired.
     *
     * @throws CacheException  If no storage is configured or a read error occurs.
     * @throws InvalidArgumentException If the key is empty.
     */
    public function get(string $key): mixed 
    {
        return $this->getItem($key);
    }

    /**
     * Store a cache item using the expiration and lock settings already configured on this instance.
     *
     * Delegates to `setItem()` using the values from `setExpire()`, `expiresAfter()`,
     * and `setLock()`. Returns false when `$content` is empty or no expiration policy
     * is set.
     *
     * @param string $key Cache key (must be non-empty).
     * @param mixed $content Value to store.
     *
     * @return bool Returns true on success, false on failure.
     *
     * @throws CacheException If no storage is configured or the write fails.
     * @throws InvalidArgumentException If the key is empty.
     */
    public function set(string $key, mixed $content): bool 
    {
		if(empty($content) || !$this->hasExpirationPolicy()){
            return false;
        }
       
        return $this->setItem(
            $key, 
            $content, 
            $this->expiration, 
            $this->expireAfter, 
            $this->lock
        );
    }

    /**
     * Return the cached value for a key, refreshing it when expired or missing.
     *
     * When the item is valid, it is returned directly without invoking `$callback`.
     * When missing or expired, `$callback` is called, its return value is stored
     * (if non-empty), and then returned.
     *
     * When no expiration policy is configured, `$callback` is always invoked and
     * its result returned without caching.
     *
     * @param string $key  Cache key (must be non-empty).
     * @param (callable(): mixed) $onRefresh Produces the fresh value when the cache is invalid.
     *
     * @return mixed The cached or freshly generated value.
     *
     * @throws CacheException  If no storage is configured or the write fails.
     * @throws InvalidArgumentException If the key is empty.
     * @example - Example:
     * ```php
     * $key = "users:100";
     * $users = $cache->onExpired($key, fn() => Builder::table('users')
     *      ->where('country', '=', 'NG')
     *      ->limit(100)
     *      ->get()
     * );
     * ```
     */
    public function onExpired(string $key, callable $onRefresh): mixed 
    {
        $this->assertStorageAndKey($key);

        // Return item immediately. 
        if(!$this->hasExpirationPolicy()){
            return $onRefresh();
        }

        if (!$this->hasExpired($key)){
            return $this->getItem($key);
        }

        $content = $onRefresh();

        if(!empty($content)){
            $this->setItem(
                $key, 
                $content, 
                $this->expiration, 
                $this->expireAfter, 
                $this->lock
            );
        }

        return $content;
    }

    /**
     * Trigger a garbage collection cycle to remove expired items.
     *
     * When `$lazyRun` is false (default), GC runs probabilistically (1-in-100 chance)
     * to avoid adding overhead on every call. When `$lazyRun` is true, GC runs
     * only if the configured interval has elapsed since the last run.
     * Returns false immediately when auto-GC is disabled.
     *
     * @param bool $lazyRun When true, enforces the interval check before running (default: false).
     *
     * @return bool Returns true if GC ran successfully, false if skipped or disabled.
     */
    public function gc(bool $lazyRun = false): bool 
    {
        if(!$this->autoGarbageCollection){
            return false;
        }

        if($lazyRun){
            $now = time();

            if (($now - $this->lastGcRun) < $this->gcInterval) {
                return true;
            }

            $this->lastGcRun = $now;
        } elseif (mt_rand(1, 100) > 1) {
            return false;
        }

        try {
            $this->deleteIfExpired();
            return true;
        } catch(Throwable){
            return false;
        }
    }



    /**
     * Auto PHP or binary unserialization.
     *
     * @param string $data
     * @param boolean $isIgBinary
     * @return mixed
     */
    private function autoUnserialize(string $data, bool $isIgBinary): mixed
    {
        if ($isIgBinary && self::isBinarySerialized($data)) {
            return \igbinary_unserialize($data);
        }

        if (self::isPhpSerialized($data)) {
            return self::phpUnserialize($data);
        }

        return $data;
    }

    /**
     * Enable or disable automatic deletion of expired cache items.
     *
     * @param bool $allow Enable (true) or disable (false) automatic deletion.
     * @param bool $gcExpiredLocks  When true, locked expired items are also removed (default: false).
     *
     * @return self Returns the current instance.
     *
     * @deprecated Use `configureGarbageCollection()` instead.
     */
    public function enableDeleteExpired(bool $allow, bool $gcExpiredLocks = false): self 
    {
        return $this->configureGarbageCollection($allow, $gcExpiredLocks);
    }

    /**
     * Enable or disable base64 encoding of the serialized cache payload.
     *
     * @param bool $encode True to enable base64 encoding, false to disable.
     *
     * @return self Returns the current instance.
     *
     * @deprecated Use `setSerializerOption()` with the `$base64Encode` parameter instead.
     */
    public function enableBase64(bool $encode): self 
    {
        $this->base64Encode = $encode;
        return $this;
    }

    /**
     * Set the persistent cache identifier.
     *
     * @param string $persistentId The persistent identifier.
     *
     * @return self Returns the current instance.
     * @deprecated Use setPersistentId() instead.
     */
    public function setId(string $persistentId): self
    {
        return $this->setPersistentId($persistentId);
    }

    /**
     * @deprecated Use getPersistentId() instead.
     */
    public function getId(): ?string
    {
        return $this->getPersistentId();
    }
}