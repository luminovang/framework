<?php 
/**
 * Luminova Framework Request Throttling.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Security;

use \Luminova\Interface\LazyInterface;
use \Luminova\Base\BaseCache;
use \Psr\SimpleCache\CacheInterface;
use \Psr\Cache\CacheItemPoolInterface;
use \Luminova\Cache\FileCache;
use \Luminova\Functions\IP;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\RuntimeException;
use \Predis\Client as PredisClient;
use \Redis;
use \Memcached;
use \DateInterval;
use \DateTimeImmutable;
use \Throwable;

class Throttler implements LazyInterface
{
    /**
     * Time-to-live window in seconds for throttle limiting.
     *
     * @var DateInterval|int $ttl
     */
    private int $ttl = 60;

    /**
     * Stores timestamps of incoming requests.
     *
     * @var array<int> $timestamps
     */
    private array $timestamps = [];

    /**
     * Client's IP address (shared across instances).
     *
     * @var string|null $ip
     */
    private static ?string $ip = null;

    /**
     * Status indicating if the cache is connected.
     *
     * @var bool $isConnected
     */
    private static bool $isConnected = false;

    /**
     * Indicates whether the current rate check has completed.
     *
     * @var bool $finished
     */
    private bool $finished = true;

    /**
     * True if the request limit has been exceeded.
     *
     * @var bool $exceeded
     */
    private bool $exceeded = false;

    /**
     * The resolved IP address of the current client.
     *
     * @var string|null $ipAddress
     */
    private ?string $ipAddress = null;

    /**
     * Hashed cache key used to identify client state.
     *
     * @var string $hash
     */
    private string $hash = '';

    /**
     * Create a new Throttler instance for rate limiting.
     *
     * This constructor sets up the rate-limiting logic using various supported caching backends.
     * It tracks client requests (typically via IP) and enforces request limits within a time window.
     *
     * **Supported Cache Types:**
     * - PSR-6: `CacheItemPoolInterface`
     * - PSR-16: `CacheInterface`
     * - Luminova's `BaseCache` (file or memory cache)
     * - Native `Memcached` or `Redis` instance
     * - `PredisClient` (Predis library)
     *
     * @param CacheItemPoolInterface|CacheInterface|BaseCache|Memcached|PredisClient|Redis|null $cache
     *        Optional cache backend. Defaults to Luminova's file-based cache if not provided.
     * @param int $limit Maximum number of allowed requests in the time window (default: 5).
     * @param DateInterval|int $ttl Time window duration in seconds or as a DateInterval (default: 10).
     * @param string $persistentId Optional unique identifier to include in the rate-limiting key.
     *
     * @example - Using FileCache (Luminova):
     * ```php
     * $file = new FileCache(root('/writeable/caches/throttler'));
     * $throttle = new Throttler($file);
     * ```
     *
     * @example - Using Redis:
     * ```php
     * $redis = new Redis();
     * $redis->connect('127.0.0.1', 6379);
     * $throttle = new Throttler($redis);
     * ```
     *
     * @example - Using Memcached:
     * ```php
     * $memcached = new Memcached();
     * $memcached->addServer('127.0.0.1', 11211);
     * $throttle = new Throttler($memcached);
     * ```
     */
    public function __construct(
        private CacheItemPoolInterface|CacheInterface|BaseCache|Memcached|PredisClient|Redis|null $cache = null,
        private int $limit = 5,
        DateInterval|int $ttl = 10,
        private string $persistentId = ''
    ) {
        $this->ttl = $this->getNormalizeTtl($ttl);
        $this->cache ??= new FileCache(root('/writeable/caches/throttler/'));
        self::$ip ??= IP::get();
        self::$isConnected = $this->isConnected();
    }

    /**
     * Set the cache instance used for rate limiting.
     *
     * This method allows you to provide a custom caching backend to store
     * rate-limiting data. Supported cache types include:
     * 
     * - PSR-6 (`CacheItemPoolInterface`) and PSR-16 (`CacheInterface`) implementations
     * - Native `Memcached` or `Redis` instances
     * - `PredisClient` instance
     * - Luminova's custom `BaseCache`
     * 
     * If no cache is set explicitly, the default fallback is Luminova's file-based cache.
     *
     * @param CacheItemPoolInterface|CacheInterface|BaseCache|Memcached|PredisClient|Redis $cache The cache instance used to store limiter data.
     * 
     * @return self Returns the instance of Throttler class.
     */
    public function setCache(CacheItemPoolInterface|CacheInterface|BaseCache|Memcached|PredisClient|Redis $cache): self 
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Get the maximum number of allowed requests within the time window.
     *
     * @return int Return the configured request limit.
     */
    public function getLimit(): int 
    {
        return $this->limit;
    }

    /**
     * Retrieves the stored client IP address associated with the current request context.
     *
     * @return string|null Return the IP address if available, or null if not set.
     */
    public function getIp(): ?string
    {
        return $this->ipAddress;
    }

    /**
     * Get the current number of request attempts made during the current time window.
     *
     * @return array Return the number of requests made so far.
     */
    public function getTimestamps(): array 
    {
        return $this->timestamps;
    }

    /**
     * Get the number of seconds until throttle resets.
     *
     * @return int|null Seconds until the throttle resets, or null if not throttled.
     */
    public function getRestAfter(): ?int
    {
        if($this->exceeded === false){
            return null;
        }

        $now = time();
        $timestamps = array_filter($this->getTimestamps(), fn($timestamp) => $now - $timestamp < $this->ttl);

        if (count($timestamps) <= $this->limit) {
            return null;
        }

        $earliest = min($timestamps);
        return ($earliest + $this->ttl) - $now;
    }


    /**
     * Get the time window in seconds used for rate limiting.
     *
     * @return int Return the duration of the rate-limiting window in seconds.
     */
    public function getTimeWindow(): int 
    {
        return $this->ttl;
    }

    /**
     * Retrieves the result after performing the `is` method.
     * 
     * @return bool Returns true if the request is allowed, false if the rate limit has been reached.
     */
    public function isExceeded(): bool
    {
        return $this->exceeded;
    }

    /**
     * Determine if the stored IP address matches the current request IP.
     *
     * Useful for validating that the limiter data corresponds to the current client,
     * especially when using persistent keys shared across multiple sessions or users.
     *
     * @return bool True if IP matches or if no IP is stored; false otherwise.
     */
    public function isIpAddress(): bool
    {
        return $this->ipAddress !== null && IP::equals($this->ipAddress, self::$ip);
    }

    /**
     * Applies throttling based on a unique key or client IP address.
     *
     * This method initializes the rate-throttling check using a hashed key derived from the client's IP address 
     * and/or a provided custom identifier. It tracks the request timestamps to determine whether the limit 
     * has been exceeded within the configured time window (`ttl`) and limit frequency of requests to prevent from overload, 
     * ensure fair usage, and optimize performance
     *
     * @param string|null $key Optional custom identifier (e.g., `User-Id`) for distinguishing between request entities.
     *                         If not provided, the client's IP address is used.
     *
     * @return self Returns the instance of Throttler class, reflecting the status.
     *
     * @throws InvalidArgumentException If the cache instance is invalid or the key is empty.
     * @throws RuntimeException If the cache connection is unavailable.
     *
     * @example - Basic usage:
     * ```php
     * if($throttle->throttle('User-Id:Ip-Address')->isExceeded()){
     *      $throttle->delay(5); // Wait 5 seconds
     * }
     * ```
     *
     * @example - With wait and IP validation:
     * ```php
     * $throttle->throttle('User-Id')->wait();
     *
     * if (
     *     $throttle->isExceeded() &&
     *     $throttle->isIpAddress() // Optional additional check
     * ) {
     *    $throttle->delay(5); // Wait 5 seconds
     * }
     * ```
     *
     * @example - Enforce delay on limit breach:
     * ```php
     * $throttle->throttle('User-Id')->wait();
     *
     * if ($throttle->isExceeded()) {
     *     $throttle->delay(5); // Wait 5 seconds
     *     return;
     * }
     * ```
     */
    public function throttle(?string $key = null): self
    {
        $this->asserts($key);
        $this->finished = false;
        $this->hash = $this->key($key);

        $result = $this->get($this->hash);
        $this->ipAddress = $result['ip'] ?? null;
        $this->timestamps = $result['timestamps'] ?? [];

        $now = time();

        if($this->timestamps !== []){
            $this->timestamps = array_filter($this->timestamps, fn($timestamp) => $now - $timestamp < $this->ttl);
        }

        $this->timestamps[] = $now;

        $this->set($this->hash, $this->timestamps);

        if (count($this->timestamps) > $this->limit) {
            $this->finished = true;
            $this->exceeded = true;
            
            return $this;
        }

        $this->finished = true;
        $this->exceeded = false;

        return $this;
    }

    /**
     * Waits until the rate throttling finishes processing or the optional timeout is reached.
     *
     * This is a passive wait that periodically checks if the throttling decision is complete.
     * It is useful when throttling involves asynchronous or deferred logic.
     *
     * @param float|int $interval  The interval to sleep between checks (in seconds). Supports sub-second delays (e.g., 0.1 for 100ms).
     * @param int|null  $maxWait   The maximum duration to wait (in seconds). Use `null` to wait indefinitely.
     *
     * @return self Returns the instance of Throttler class.
     */
    public function wait(float|int $interval = 1.0, ?int $maxWait = null): self
    {
        $startTime = microtime(true);
        $microInterval = (int)($interval * 1_000_000);

        while (!$this->finished) {
            if ($maxWait !== null && (microtime(true) - $startTime) >= $maxWait) {
                $this->finished = true;
                $this->exceeded = false;
                break;
            }

            usleep($microInterval);
        };

        return $this;
    }

    /**
     * Introduces an execution delay if the rate limit was exceeded.
     *
     * This can be used to enforce backoff or slow down abusive clients after a throttle breach.
     * If the limit was not exceeded, this method exits immediately.
     *
     * @param float|int $interval Delay duration in seconds. Supports sub-second values (e.g., 0.5 for 500ms).
     *
     * @return void
     */
    public function delay(float|int $interval = 1.0): void
    {
        if(!$this->exceeded){
            return;
        }

        usleep((int)($interval * 1_000_000));
    }

    /**
     * Reset remaining rate to initial limit and consumed request to 0.
     * 
     * Clears all stored timestamps and restores the available request count
     * to the initial limit for the active client key.
     * 
     * @param string|null $key An optional unique identifier to reset requests (e.g, `User-Id`).
     *              Default to client IP address if key is not provided.
     * 
     * @return bool Returns true request key was cleared, otherwise false. 
     * @throws InvalidArgumentException If the cache instance is not valid or the key is empty.
     * @throws RuntimeException If cache is not connected.
     */
    public function reset(?string $key = null): bool
    {
        $this->asserts($key);
        $key = $this->key($key);

       return match (true) {
            $this->cache instanceof CacheInterface, $this->cache instanceof Memcached => $this->cache->delete($key),
            $this->cache instanceof Redis, $this->cache instanceof PredisClient => $this->cache->del($key),
            $this->cache instanceof CacheItemPoolInterface => $this->cache->deleteItem($key),
            $this->cache instanceof BaseCache => $this->cache->deleteItem($key, true),
            default => false,
        };
    }

    /**
     * Store or update the request count and IP in cache.
     *
     * @param string $key Cache key set request info.
     * @param int $requests Number of requests to store.
     * @param bool $withLimit Weather to attach custom limit to this key.
     * 
     * @return bool Return true on successful cache save, false on failure.
     */
    protected function set(string $key, array $timestamps): bool
    {
        $value = [
            'timestamps' => $timestamps,
            'ip' => self::$ip
        ];

        return match (true) {
            $this->cache instanceof CacheInterface => $this->cache->set($key, $value, $this->ttl),
            $this->cache instanceof Memcached => $this->cache->set($key, $value, time() + $this->ttl),
            $this->cache instanceof BaseCache => $this->cache->setItem($key, $value, $this->ttl),
            $this->cache instanceof CacheItemPoolInterface =>
                $this->cache->save($this->cache->getItem($key)->expiresAfter($this->ttl)->set($value)),
            $this->cache instanceof Redis,
            $this->cache instanceof PredisClient => (bool) $this->cache->setex($key, $this->ttl, json_encode($value)),
            default => false,
        };
    }

    /**
     * Retrieve cached rate limit data for the given key.
     *
     * @param string $key Cache key to retrieve.
     * 
     * @return array Return an associative array with 'requests', 'ip', and `extended` limit or empty array if not found.
     */
    protected function get(string $key): array
    {
        $result = match (true) {
            $this->cache instanceof CacheItemPoolInterface => $this->cache->getItem($key)->get(),
            $this->cache instanceof BaseCache => $this->cache->getItem($key),
            default => $this->cache->get($key)
        };

        if ($this->cache instanceof Memcached && $this->cache->getResultCode() === Memcached::RES_NOTFOUND) {
            return [];
        }

        if(is_string($result)){
            return json_decode($result, true) ?? [];
        }

        return $result ?: [];
    }

   /**
     * Check if the underlying cache connection is established.
     *
     * @return bool Return true if connected, false otherwise.
     */
    protected function isConnected(): bool
    {
        $isRedis = ($this->cache instanceof Redis);
        $pong = ($isRedis ? '+PONG' : 'PONG');

        try {
            if ($isRedis || $this->cache instanceof PredisClient) {
                return $this->cache->ping() === $pong;
            }

            if ($this->cache instanceof Memcached) {
                $this->cache->set('__ping', $pong, 10);
                return $this->cache->get('__ping') === $pong;
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Normalize TTL to seconds.
     *
     * @param DateInterval|int $ttl The ttl to normalize.
     * 
     * @return int Return TTL in seconds.
     */
    protected function getNormalizeTtl(DateInterval|int $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            return (new DateTimeImmutable())->add($ttl)->getTimestamp() - time();
        }

        return $ttl;
    }

    /**
     * Generate a consistent cache key using the user key and IP address.
     * 
     * (e.g., `Throttler:{Persistent-Id}+{Custom-Key|Ip-Address}`).
     *
     * @param string|null $key User-defined key (e.g., user ID or session).
     * 
     * @return string Return an MD5 hash used for cache key.
     */
    protected function key(?string $key = null): string
    {
        $key ??= self::$ip;

        return md5("Request-Throttler:{$this->persistentId}+{$key}");
    }

    /**
     * Asserts the validity of the cache instance and the key.
     *
     * This method checks if the provided cache instance is an instance of one of the supported cache interfaces
     * (CacheInterface, CacheItemPoolInterface, BaseCache, Memcached, Redis, PredisClient). 
     * It also checks if the provided key is not an empty string.
     *
     * @param string|null $key The user-defined key (e.g., user ID or session).
     *
     * @throws InvalidArgumentException If the cache instance is not valid or the key is empty.
     * @throws RuntimeException If cache is not connected.
     *
     * @return void
     */
    protected function asserts(?string $key): void 
    {
        if (
            !$this->cache instanceof CacheInterface &&
            !$this->cache instanceof CacheItemPoolInterface &&
            !$this->cache instanceof BaseCache && 
            !$this->cache instanceof Memcached && 
            !$this->cache instanceof Redis && 
            !$this->cache instanceof PredisClient
        ) {
            throw new InvalidArgumentException(sprintf(
                'Invalid cache instance. Expected an instance of %s, %s, %s, %s, %s, or %s.',
                CacheInterface::class,
                CacheItemPoolInterface::class,
                BaseCache::class,
                Memcached::class,
                Redis::class,
                PredisClient::class
            ));
        }

        if(!self::$isConnected){
            throw new RuntimeException('Cache server connection failed.');
        }

        if ($key === '') {
            throw new InvalidArgumentException(
                'Cache key cannot be an empty string. Provide a non-empty string or use NULL to fallback to default key.'
            );
        }
    }
}