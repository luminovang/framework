<?php 
/**
 * Luminova Framework Request Rate Limiter (RRL).
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
use \Luminova\Http\Header;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\RuntimeException;
use \Predis\Client as PredisClient;
use \Redis;
use \Memcached;
use \DateInterval;
use \DateTimeImmutable;
use \Throwable;

class RateLimiter implements LazyInterface
{
    /**
     * Time-to-live window in seconds.
     * 
     * @var DateInterval|int $ttl
     */
    private int $ttl = 60;

    /**
     * Remaining request limit.
     * 
     * @var int|null $remaining
     */
    private int $remaining = 0;

    /**
     * Response message and type.
     * 
     * @var array<string,string> $response
     */
    private array $response = ['type' => 'json', 'message' => null];

    /**
     * Stores timestamps of incoming requests.
     *
     * @var array<int> $timestamps
     */
    private array $timestamps = [];

    /**
     * Request client IP address. 
     * 
     * @var string|null $ip
     */
    private static ?string $ip = null;

    /**
     * Cache connection status. 
     * 
     * @var bool $isConnected
     */
    private static bool $isConnected = false;

    /**
     * Waiting status. 
     * 
     * @var bool $finished
     */
    private bool $finished = true;

    /**
     * Validation result exceeded. 
     * 
     * @var bool $exceeded
     */
    private bool $exceeded = false;

    /**
     * Request attempts count. 
     * 
     * @var int $requests
     */
    private int $requests = 0;

    /**
     * Number of extended limit. 
     * 
     * @var int $extended
     */
    private int $extended = 0;

    /**
     * Number of seconds to reset. 
     * 
     * @var int|null $resetAt
     */
    private ?int $resetAt = null;

    /**
     * Headers. 
     * 
     * @var array $headers
     */
    private array $headers = [];

    /**
     * Stored client IP address. 
     * 
     * @var string|null $ipAddress
     */
    private ?string $ipAddress = null;

    /**
     * Hashed key. 
     * 
     * @var string $hash
     */
    private string $hash = '';

    /**
     * Create a new RateLimiter instance.
     *
     * This constructor allows flexible integration with different caching backends to track
     * and limit request rates per client (typically IP-based).
     * 
     * **Supported Cache Instance**
     * 
     * - PSR-6: `CacheItemPoolInterface` (e.g., CustomCache)
     * - PSR-16: `CacheInterface` (e.g., Psr\SimpleCache\CacheInterface)
     * - `BaseCache`: Luminova's custom file or memory cache.
     * - `Memcached`: Native Memcached instance.
     * - `Redis`: PHP Redis extension instance.
     * - `PredisClient`: Predis library instance.
     *
     * @param CacheItemPoolInterface|CacheInterface|BaseCache|Memcached|PredisClient|Redis|null $cache
     *        Optional cache instance. If null, the default Luminova file-based cache will be used.
     * @param int $limit Maximum number of allowed requests within the TTL window (Default: 10).
     * @param DateInterval|int $ttl Time-to-live for request tracking, in seconds or as a DateInterval (default: 60).
     * @param string $persistentId Optional unique ID to be included with key.
     * 
     * @example - Using Luminova's FileCache (default fallback)
     * 
     * ```php
     * $file = new FileCache(root('/writeable/caches/path/to/limiter'));
     * $limiter = new RateLimiter($file);
     * ```
     * 
     * @example - Using Redis:
     * 
     * ```php
     * use \Redis;
     * $redis = new Redis();
     * $redis->connect('127.0.0.1', 6379);
     * $limiter = new RateLimiter($redis);
     * ```
     *
     * @example - Using Memcached: 
     * 
     * ```php
     * use \Memcached;
     * 
     * $memcached = new Memcached();
     * $memcached->addServer('127.0.0.1', 11211);
     * $limiter = new RateLimiter($memcached);
     * ```
     */
    public function __construct(
        private CacheItemPoolInterface|CacheInterface|BaseCache|Memcached|PredisClient|Redis|null $cache = null,
        private int $limit = 10,
        DateInterval|int $ttl = 60,
        private string $persistentId = ''
    ) {
        $this->ttl = $this->getNormalizeTtl($ttl);
        $this->remaining = $limit;
        $this->cache ??= new FileCache(root('/writeable/caches/limiter/'));
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
     * @return self Returns the instance of RateLimiter class.
     */
    public function setCache(CacheItemPoolInterface|CacheInterface|BaseCache|Memcached|PredisClient|Redis $cache): self 
    {
        $this->cache = $cache;
        return $this;
    }

    /**
     * Generate and return proper headers for rate limitation.
     * 
     * @return array<string,mixed> Return an associative array of headers.
     */
    public function getHeaders(): array 
    {
        $reset = $this->getReset();
        $this->headers = ['X-RateLimit-Remaining' => $this->remaining];
        $this->headers['X-RateLimit-Reset'] = $reset;
        $this->headers['X-RateLimit-Limit'] = $this->getLimit();
        $this->headers['Retry-After'] = max(1, $reset - time());
        $this->headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        $this->headers['X-Content-Type-Options'] = 'nosniff';
        $this->headers['Expires'] = 0;

        return $this->headers;
    }

    /**
     * Get the maximum number of allowed requests within the time window.
     *
     * @return int Return the configured request limit.
     */
    public function getLimit(): int 
    {
        return $this->limit + $this->extended;
    }

    /**
     * Retrieve the recorded request timestamps.
     *
     * Optionally filters and returns only valid (non-expired) timestamps within the current time window, 
     * based on the configured TTL (time-to-live).
     *
     * @param bool $filterValidOnly If true, only return timestamps that are still valid (not expired).
     *
     * @return array Return an array of request timestamps, filtered by validity if requested.
     */
    public function getTimestamps(bool $filterValidOnly = false): array 
    {
        if(!$filterValidOnly || $this->timestamps === []){
            return $this->timestamps;
        }

        $now = time();
        return array_filter($this->timestamps, fn($timestamp) => $now - $timestamp < $this->ttl);
    }

    /**
     * Get the total number of extended requests limit added via (`continue`).
     *
     * @return int Return the extended request limit or `0` if none.
     */
    public function getExtendedLimit(): int 
    {
        return $this->extended;
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
     * @return int Return the number of requests made so far.
     */
    public function getRequests(): int 
    {
        return $this->requests;
    }

    /**
     * Get the number of remaining allowed requests in the current time window.
     *
     * @return int Return the remaining number of requests before limit is reached.
     */
    public function getRemaining(): int 
    {
        return $this->remaining;
    }

    /**
     * Get the number of seconds remaining until the current rate limit window resets.
     *
     * This represents the total time left before the client is allowed to make requests again
     * once the rate limit has been exceeded.
     *
     * @return int Returns the number of seconds until the rate limit resets.
     */
    public function getRetryAfter(): int 
    {
        return max(1, $this->getReset() - time());
    }

    /**
     * Get the exact UNIX timestamp when the rate limit will reset.
     *
     * @return int Return the timestamp of rate limit reset.
     */
    public function getReset(): int 
    {
        return $this->resetAt ?? (time() + $this->ttl);
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
    public function isAllowed(): bool
    {
        return $this->exceeded === false;
    }

    /**
     * Determines whether the current request is from the same IP address that initialized the key.
     *
     * Returns true if this is the first request, or if the stored IP address matches the current IP.
     *
     * @return bool Return true if the IP matches or it's the first request; otherwise, false.
     * 
     * > Useful for validating the continuity of a rate-limited session or key-bound identity.
     */
    public function isIpAddress(): bool
    {
        return $this->requests <= 1 || (
            $this->ipAddress !== null && IP::equals($this->ipAddress, self::$ip)
        );
    }

    /**
     * Checks if a request is allowed based on the rate limit for the given key.
     * 
     * This method generates a unique key based on the clients's IP address or default to provided key.
     * It uses MD5 hashing for simplicity in key hashing (e.g., `Rate-Limiter:{Persistent-Id}+{Custom-Key||Ip-Address}`).
     *
     * @param string|null $key An optional custom identifier (e.g., `User-Id`) to check the rate limit for.
     * 
     * @return self Returns the instance of the RateLimiter class, reflecting the status.
     * @throws InvalidArgumentException If the cache instance is invalid or the key is empty.
     * @throws RuntimeException If the cache is not connected.
     * 
     * @example - Usage Examples:
     * 
     * ```php
     * if(!$limiter->check('User-Id:Ip-Address')->isAllowed()){
     *      $limiter->respond();
     * }
     * ```
     * 
     * @example - Using Wait execution: 
     * 
     * Respond with message:
     * 
     * ```php
     * $limiter->check('User-Id')->wait();
     * 
     * if(!$limiter->isAllowed()){
     *      $limiter->respond();
     * }
     * ```
     * 
     * Delay operation using throttle:
     * 
     * ```php
     * $limiter->check('User-Id')->wait();
     * 
     * if(!$limiter->isAllowed()){
     *      $limiter->throttle(5);
     * }
     * ```
     * 
     * Adjust rate limit:
     * 
     * ```php
     * $limiter->check('User-Id')->wait();
     * 
     * if(!$limiter->isAllowed() && !$limiter->isIpAddress()){
     *       $limiter->continue(2);
     * }
     * ```
     * > This may work only if the key doesn't use client ip address.
     */
    public function check(?string $key = null): self
    {
        $this->asserts($key);
        $this->finished = false;
        $this->hash = $this->key($key);

        $result = $this->get($this->hash);
        $requests = (int) ($result['requests'] ?? 0);

        $this->extended = (int) ($result['extend'] ?? 0);
        $this->ipAddress = $result['ip'] ?? null;
        $this->resetAt = $result['reset'] ?? null;
        $this->timestamps = $result['timestamps'] ?? [];
        $this->requests = $requests + 1;

        if ($requests >= $this->getLimit()) {
            $this->finished = true;
            $this->exceeded = true;
            $this->remaining = 0;
            
            return $this;
        }

        $now = time();
        $this->remaining = max(0, $this->getLimit() - $this->requests);
        $this->resetAt = $now + $this->ttl;
        $this->timestamps[] = $now;
        $this->set($this->hash, $this->requests);

        $this->finished = true;
        $this->exceeded = false;

        return $this;
    }

    /**
     * Waits until the rate limiter finishes processing or the optional max wait timeout is exceeded.
     *
     * This is a passive wait that periodically checks if the limitter decision is complete.
     * It is useful when limitter involves asynchronous or deferred logic.
     *
     * @param float|int $interval The interval to sleep between checks (in seconds). Supports sub-second delays (e.g., 0.1 for 100ms).
     * @param int|null $maxWait The maximum duration to wait (in seconds). Use `null` to wait indefinitely.
     *
     * @return void
     */
    public function wait(float|int $interval = 1.0, ?int $maxWait = null): void
    {
        $startTime = microtime(true);
        $microInterval = (int)($interval * 1_000_000);

        while (!$this->finished) {
            if ($this->finished || ($maxWait !== null && (microtime(true) - $startTime) >= $maxWait)) {
                $this->finished = true;
                break;
            }

            usleep($microInterval);
        };
    }

    /**
     * Generate the number of remaining allowed requests for the given key.
     *
     * @param string|null $key An optional unique identifier to check (e.g, `User-Id`).
     *                  Default to client IP address if key is not provided.
     * 
     * @return int Return the remaining number of requests before the limit is reached.
     * @throws InvalidArgumentException If the cache instance is not valid or the key is empty.
     * @throws RuntimeException If cache is not connected.
     */
    public function remaining(?string $key = null): int
    {
        $this->asserts($key);

        return max(0, $this->getLimit() - $this->get($this->key($key))['requests'] ?? 0);
    }

    /**
     * Resets the rate-limiting state to allow additional requests to continue processing.
     *
     * This method updates the internal request counters by subtracting the given `$limit` from
     * the total consumed request count and adding it back to the remaining allowance. It also resets
     * the `finished` and `exceeded` flags to indicate that the current rate-limiting session
     * is active and within bounds.
     *
     * @param int $limit The number of requests to re-allow or reassign (default is 1).
     *
     * @return self Returns the instance of the RateLimiter class, reflecting the status.
     * @throws InvalidArgumentException If the cache instance is invalid or the key is empty.
     * @throws RuntimeException If the cache is not connected.
     * 
     * > **Note:** This will persist new limit (e.g, `defaultLimit + $limit`), till ttl expires before default limit is restored.
     */
    public function continue(int $limit = 1): self
    {
        $this->asserts($this->hash);

        $this->finished = false;
        $this->extended += max(1, $limit);
        $this->limit += $this->extended;
        $this->requests -= $this->extended;
        $this->remaining += $this->extended;

        $this->set($this->hash, $this->requests);

        $this->exceeded = false;
        $this->finished = true;

        return $this;
    }

    /**
     * Reset remaining rate to initial limit and consumed request to 0.
     * 
     * @param string|null $key An optional unique identifier to reset requests (e.g, `User-Id`).
     *              Default to client IP address if key is not provided.
     * 
     * @return self Returns the instance of the RateLimiter class.
     * @throws InvalidArgumentException If the cache instance is not valid or the key is empty.
     * @throws RuntimeException If cache is not connected.
     */
    public function reset(?string $key = null): self
    {
        $this->asserts($key);

        $this->finished = false;
        $this->extended = 0;
        $this->set($key, 0);

        $this->requests = 0;
        $this->resetAt = null;
        $this->remaining = $this->limit;
        $this->finished = true;

        return $this;
    }

    /**
     * Sets the response message and its output format.
     *
     * This method allows you to define the body of the rate limit response in various formats.
     * If the message is a string, you can include the following placeholders which will be replaced automatically:
     *
     * **Placeholder Variables:**
     * - `{limit}` - Total number of allowed requests.
     * - `{remaining}` - Number of requests remaining.
     * - `{reset}` - Timestamp when the limit resets.
     * - `{retry}` - Seconds to wait before retrying.
     * - `{ttl}` - Request Time Window.
     *
     * **Supported Output Formats:**
     * 
     * - `json` - Outputs as JSON. Arrays/objects are encoded automatically.
     * - `html` - Outputs as HTML string.
     * - `xml` - Outputs as XML string.
     * - `text` - Outputs as plain text.
     * - `custom` - Outputs with custom format; you must manually set the `Content-Type` header.
     *
     * @param string|array|object $body The response message or payload.
     * @param string $type The response format (default: `json`).
     * 
     * @return self Return instance of RateLimiter class.
     *
     * @example - Usage Examples:
     * 
     * ```php
     * // As JSON with message placeholders
     * $rateLimiter->message("You have {remaining} of {limit} requests left. Try again in {retry} seconds.");
     *
     * // As plain text
     * $rateLimiter->message("Rate limit exceeded. Please retry later.", 'text');
     *
     * // With structured array (auto-converted if not JSON format)
     * $rateLimiter->message([
     *     'error' => 'Rate limit exceeded',
     *     'retry_after' => 60
     * ], 'html');
     * ```
     */
    public function message(string|array|object $body, string $type = 'json'): self 
    {
        $type = strtolower($type);
        $this->response['type'] = $type;

        if ($type !== 'json' && is_array($body)) {
            $body = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $this->response['message'] = $body;
        return $this;
    }


    /**
     * Send an HTTP 429 (Too Many Requests) response with appropriate rate-limit headers.
     *
     * @param array<string,string> $headers Additional headers to include in the response key-pair.
     * 
     * @return int Return response status (e.g, `STATUS_*`).
     */
    public function respond(array $headers = []): int
    {
        if(\is_command()){
            return STATUS_SILENT;
        }

        $type = $this->response['type'] ?? 'json';
        $message = $this->response['message'] 
            ?? 'Rate limit exceeded: allowed {limit} requests in {ttl} seconds. Please try again after {retry} seconds.';
        $headers += $this->getHeaders();

        if(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD'){
            ob_start();
            response(429, $headers)->send();

            Header::clearOutputBuffers();
            return STATUS_SUCCESS;
        }

        if (is_array($message) || is_string($message)) {
            $message = str_replace(
                ['{limit}', '{remaining}', '{reset}', '{retry}', '{ttl}'],
                [$this->getLimit(), $this->remaining, $headers['X-RateLimit-Reset'], $headers['Retry-After'], $this->ttl],
                $message
            );
        }

        Header::setOutputHandler(true);

        return match ($type) {
            'custom' => response(429, $headers)->render($message),
            'html', 'xml', 'text' => response(429, $headers)->{$type}($message),
            default => response(429, $headers)->json([
                'error' => $message,
                'limit' => $this->getLimit(),
                'retry_after' => $headers['Retry-After'],
                'remaining' => $this->remaining,
                'reset' => $headers['X-RateLimit-Reset']
            ]),
        };
    }

    /**
     * Introduces an execution delay throttling if the rate limit was exceeded.
     *
     * This can be used to enforce backoff or slow down abusive clients after a throttle breach.
     * If the limit was not exceeded, this method exits immediately.
     *
     * @param float|int $interval Delay duration in seconds. Supports sub-second values (e.g., 0.5 for 500ms).
     *
     * @return void
     * 
     * @example -Enforce delay on limit breach:
     * 
     * ```php
     * $limiter->check('User-Id')->wait();
     *
     * if (!$limiter->isAllowed()) {
     *    $limiter->throttle(5); // Wait 5 seconds
     * }
     * ```
     */
    public function throttle(float|int $interval = 1.0): void
    {
        if(!$this->exceeded){
            return;
        }

        usleep((int)($interval * 1_000_000));
    }

    /**
     * Check whether the rate limiter has an entry for the given key.
     * 
     * This method uses client IP address if key is not provide.
     * 
     * @param string|null $key An optional unique identifier to check (e.g, `User-Id`).
     * 
     * @return bool Return true if the key exists in the cache, false otherwise.
     * @throws InvalidArgumentException If the cache instance is not valid or the key is empty.
     * @throws RuntimeException If cache is not connected.
     */
    public function has(?string $key = null): bool
    {
        $this->asserts($key);

        $this->finished = false;
        $key = $this->key($key);

        $result = match (true) {
            $this->cache instanceof CacheInterface => $this->cache->has($key),
            $this->cache instanceof Redis, $this->cache instanceof PredisClient => $this->cache->exists($key),
            $this->cache instanceof Memcached => $this->cache->get($key),
            $this->cache instanceof CacheItemPoolInterface,
                $this->cache instanceof BaseCache => $this->cache->hasItem($key),
            default => false,
        };

        $this->finished = true;
        if ($this->cache instanceof Memcached) {
            return $result === true || $this->cache->getResultCode() === Memcached::RES_SUCCESS;
        }

        return $result === true || $result > 0;
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
    protected function set(string $key, int $requests): bool
    {
        $value = [
            'requests' => $requests,
            'ip' => self::$ip,
            'extend' => $this->extended,
            'reset' => $this->resetAt,
            'timestamps' => $this->timestamps
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
     * (e.g., `Rate-Limiter:{Persistent-Id}+{Custom-Key||Ip-Address}`).
     *
     * @param string|null $key User-defined key (e.g., user ID or session).
     * 
     * @return string Return an MD5 hash used for cache key.
     */
    protected function key(?string $key = null): string
    {
        $key ??= self::$ip;

        return md5("Rate-Limiter:{$this->persistentId}+{$key}");
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