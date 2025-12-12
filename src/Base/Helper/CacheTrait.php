<?php 
/**
 * Luminova Framework queue table schemes.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Base\Helper;

use \Redis;
use \Closure;
use \Memcached;
use \Throwable;
use \DateInterval;
use \DateTimeZone;
use \DateTimeInterface;
use Luminova\Time\Time;
use Luminova\Logger\Logger;
use Luminova\Storage\Stream;
use Luminova\Exceptions\ErrorCode;
use Luminova\Exceptions\{CacheException, LuminovaException, InvalidArgumentException};

trait CacheTrait
{
    /**
     * TTL constant: 7 days in seconds (604800).
     *
     * @var int TTL_7DAYS
     */
    public final const TTL_7DAYS = 7 * 24 * 60 * 60;

    /**
     * TTL constant: 24 hours in seconds (86400).
     *
     * @var int TTL_24HR
     */
    public final const TTL_24HR = 24 * 60 * 60;

    /**
     * TTL constant: 30 minutes in seconds (1800).
     *
     * @var int TTL_30MIN
     */
    public final const TTL_30MIN = 30 * 60;

    /**
     * Serialization policy: auto-select the best available serializer.
     *
     * Uses igbinary when the extension is loaded; falls back to PHP native serialize.
     *
     * @var int SERIALIZER_PHP_AUTO
     */
    public final const SERIALIZER_PHP_AUTO = 0;

    /**
     * Serialization policy: PHP native serialize/unserialize.
     *
     * Widely supported but produces larger output than igbinary.
     *
     * @var int SERIALIZER_PHP
     */
    public final const SERIALIZER_PHP = 1;

    /**
     * Serialization policy: igbinary binary serializer.
     *
     * Faster and more compact than PHP serialize; requires the igbinary extension.
     *
     * @var int SERIALIZER_IGBINARY
     */
    public final const SERIALIZER_IGBINARY = 2;

    /**
     * Serialization policy: JSON encoding.
     *
     * Portable and human-readable, but does not preserve full PHP type fidelity.
     *
     * @var int SERIALIZER_JSON
     */
    public final const SERIALIZER_JSON = 3;

    /**
     * Serialization policy: No encoding.
     * 
     * Allow cache server to handle serializing.
     * 
     * @var int SERIALIZER_NONE
     */
    public final const SERIALIZER_NONE = 4;

    /**
     * Storage key prefix.
     * 
     * @var string KEY_PREFIX
     */
    protected const KEY_PREFIX = '__lmvc:';

    // Map cache metadata array key-names 
    protected const DATA       = 'i';
    protected const TTL        = 'x';
    protected const TIMESTAMP  = 't';
    protected const SERIALIZER = 's';
    protected const BASE64     = 'e';
    protected const IGBINARY   = 'b';
    protected const LOCK       = 'l';
    protected const DECODED    = 'd';
    protected const HASH       = 'h';
    protected const F_HASH     = 'f';

    /**
     * XXH3-hashed cache storage name used as the key namespace.
     *
     * @var string|null $storage
     */
    protected ?string $storage = null;

    /**
     * Original (un-hashed) cache storage name as supplied by the caller.
     *
     * @var string|null $storageName
     */
    protected ?string $storageName = null;

    /**
     * Persistent connection identifier for connection pooling.
     * 
     * - `file`: Use as sub-directory within the cache root .
     * - `memcached`: Use as connection identifier for server pooling..
     * - `redis`: Use as connection for server pooling.
     *
     * @var string|null $persistentId
     */
    protected ?string $persistentId = null;

    /**
     * In-process cache of loaded items, keyed by the fully qualified cache key.
     *
     * @var array<string,mixed> $items
     */
    protected array $items = [];

    /**
     * Resolved TTL in seconds for the current expiration setting, or 0 for immediate expiry.
     *
     * @var int|null $expiration
     */
    protected ?int $expiration = 0;

    /**
     * Resolved TTL in seconds for the current relative expiration setting.
     *
     * @var int|null $expireAfter
     */
    protected ?int $expireAfter = null;

    /**
     * When true, cache items are protected from deletion even after expiration.
     *
     * @var bool $lock
     */
    protected bool $lock = false;

    /**
     * Active serialization policy.
     *
     * @var int $serializer
     * @see self::SERIALIZER_NONE
     * @see self::SERIALIZER_PHP_AUTO
     * @see self::SERIALIZER_PHP
     * @see self::SERIALIZER_IGBINARY
     * @see self::SERIALIZER_JSON
     */
    protected int $serializer = self::SERIALIZER_NONE;

    /**
     * Resolved serializer handler identifier.
     *
     * - `false` — PHP native serialize
     * - `true` — igbinary_serialize
     * - `null` — not yet resolved (auto-detect pending)
     *
     * @var bool|null $isIgBinary
     */
    protected ?bool $isIgBinary = null;

    /**
     * When true, the serialized payload is base64-encoded before storage.
     *
     * Useful for binary-safe storage in text-only backends.
     *
     * @var bool $base64Encode
     */
    protected bool $base64Encode = false;

    /**
     * Whether automatic garbage collection of expired items is enabled.
     *
     * @var bool $autoGarbageCollection
     */
    protected bool $autoGarbageCollection = false;

    /**
     * Whether locked expired items are eligible for garbage collection.
     *
     * @var bool $garbageCollectExpiredLocks
     */
    protected bool $garbageCollectExpiredLocks = false;

    /**
     * Garbage collection run interval.
     *
     * @var int $gcInterval
     */
    private int $gcInterval = 300;

    /**
     * Garbage collection last run.
     *
     * @var int $lastGcRun
     */
    private int $lastGcRun = 0;

    /**
     * Whether a cache server connection has been established.
     *
     * @var bool $isConnected
     */
    protected bool $isConnected = false;

    /**
     * Timezone used when computing expiration timestamps.
     *
     * @var DateTimeZone|string|null $timezone
     */
    protected DateTimeZone|string|null $timezone = null;

    /**
     * Active connection handle for the current storage.
     * 
     * - Memcached connection handle.
     * - Redis connection handle.
     * - File Stream handle.
     *
     * @var Memcached|Redis|Stream|null $conn
     */
    protected Memcached|Redis|Stream|null $conn = null;

    /**
     * Whether the last `getDelayed()` call produced a result set available for fetching.
     *
     * @var bool $isResult
     */
    protected bool $isResult = false;

    /**
     * Load a cache entry from the backend, with connection guard and optional GC.
     *
     * Delegates to `read()`. In production, errors are logged and false is returned.
     * In development, errors are re-thrown as `CacheException`.
     * A GC cycle is always triggered in the `finally` block when `$gc` is true.
     *
     * @param string|null $key Cache key to load, or null for driver-specific default behavior (default: null).
     * @param bool $gc  When true, triggers a GC cycle after the load attempt (default: true).
     *
     * @return bool Returns true on success, false on failure.
     *
     * @throws CacheException If not connected, or if a read error occurs in a non-production environment.
     */
    protected function load(?string $key = null, bool $gc = true): bool 
    {
        if(!$this->storage){
            return false;
        }

        if (!$this->isConnected) {
            $this->errorHandler(
                new CacheException(sprintf(
                    'Cache server not connected. Call %s->connect()',
                    static::class
                ), ErrorCode::UNABLE_TO_CONNECT),
                'load'
            );
            return false;
        }

        try{
            return $this->read($key);
        }catch(Throwable $e){
            $this->errorHandler(
                new CacheException(
                    sprintf('Failed to read cache content: %s', $e->getMessage()), 
                    $e->getCode(), 
                    $e
                ),
                'load'
            );
        } finally {
            if($gc){
                $this->gc();
            }
        }

        return false;
    }

    /**
     * Return a cache item from the local pool, loading it from the backend if not already present.
     *
     * @param string|null $key Cache key to check and optionally load (default: null).
     * @param bool  $gc  When true, passes the GC flag through to `load()` (default: false).
     *
     * @return bool Returns true if the item is available in the local pool, false otherwise.
     */
    protected function reload(?string $key = null, bool $gc = false): bool 
    {
        if (isset($this->items[$key])) {
            return true;
        }

        if (!$this->load($key, $gc)) {
            return false;
        }

        return true;
    }

    /**
     * Encode content, build the cache payload, and commit it to the backend.
     *
     * Validates connection and storage, encodes the content, stores the payload in
     * the local pool, commits to the backend, then restores the decoded value in
     * the local pool for immediate reuse. Triggers a GC cycle in the `finally` block.
     *
     * @param string  $key  Cache key.
     * @param mixed  $content  Value to store.
     * @param DateTimeInterface|int|null $expiration  Absolute expiry or TTL in seconds (default: 0).
     * @param DateInterval|int|null $expireAfter Relative TTL; takes precedence over `$expiration` (default: null).
     * @param bool  $lock When true, marks the item as locked (default: false).
     * @param bool  $reload When true, reloads storage before building the payload (default: false).
     *
     * @return bool Returns true on success, false on failure.
     *
     * @throws CacheException         If not connected or if encoding/write fails.
     * @throws InvalidArgumentException If the key is empty.
     */
    protected function write(
        string $key, 
        mixed $content, 
        DateTimeInterface|int|null $expiration = 0, 
        DateInterval|int|null $expireAfter = null, 
        bool $lock = false,
        bool $reload = false
    ): bool 
    {
        if (!$this->isConnected) {
            $this->errorHandler(
                new CacheException(sprintf(
                    'Cache server not connected. Call %s->connect()',
                    static::class
                ), ErrorCode::UNABLE_TO_CONNECT),
                'write'
            );
            return false;
        }

        try{
            $this->assertStorageAndKey($key);

            $item = $this->onSetItem(
                $content,
                $expiration,
                $expireAfter,
                $lock,
                $reload
            );

            if ($item === null) {
                $this->errorHandler(
                    new CacheException('Failed to encode cache data.'),
                    'write'
                );
                return false;
            }

            $key = $this->toKey($key);

            $this->items[$key] = $item;

            $result = $this->commit();

            $this->items[$key][self::DATA] = $content;
            $this->items[$key][self::DECODED] = true;

            if(!$result){
                unset($this->items[$key]);
            }

            return $result;
        } finally {
            $this->gc();
        }
    }

    /**
     * Encode content and build the raw cache payload array.
     *
     * @param mixed  $content     Value to encode and wrap in a payload.
     * @param DateTimeInterface|int|null $expiration  Absolute expiry or TTL in seconds.
     *                                                Ignored when `$expireAfter` is set (default: 0).
     * @param DateInterval|int|null $expireAfter Relative TTL; when set, `$expiration` is cleared (default: null).
     * @param bool $lock  When true, marks the item as locked (default: false).
     * @param bool $reload When true, reloads storage before building the payload (default: false).
     *
     * @return array<string,mixed>|null Returns the payload array, or null when encoding fails.
     */
    protected function onSetItem(
        mixed $content, 
        DateTimeInterface|int|null $expiration = 0, 
        DateInterval|int|null $expireAfter = null, 
        bool $lock = false,
        bool $reload = false
    ): ?array 
    {
        $content = $this->encode($content);

        if ($content === false) {
            return null;
        }

        if($expireAfter !== null){
            $expiration = null;
        }

        if($reload){
            $this->load(gc: false);
        }

        $item = $this->createRawPayload(
            $content,
            $expiration ?? $expireAfter,
            $lock
        );

        return $item;
    }

    /**
     * JSON-encode an array for cache storage.
     *
     * Uses `JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
     * | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION`.
     *
     * @param array<string,mixed>|object $item Data to encode.
     *
     * @return string|null JSON string, or null when encoding produces an empty result.
     */
    protected function toJsonString(array|object $item): ?string 
    {
        return json_encode(
            $item,
            JSON_THROW_ON_ERROR
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
        ) ?: null;
    }

    /**
     * Resolve and cache the serializer identifier based on the active encoding policy.
     *
     * Sets `$this->isIgBinary` to `true` when igbinary is available and the policy
     * is `SERIALIZER_PHP_AUTO` or `SERIALIZER_IGBINARY`; otherwise sets it to `false`.
     * No-op when the serializer is already resolved.
     *
     * @return void
     */
    protected function setSerializer(): void
    {
        if ($this->isIgBinary !== null) {
            return;
        }

        $this->isIgBinary = match ($this->serializer) {
            self::SERIALIZER_IGBINARY,
            self::SERIALIZER_PHP_AUTO    => function_exists('igbinary_serialize'),
            default                      => false,
        };
    }

    /**
     * Handle a caught exception based on the current environment.
     *
     * In production mode (when `$silently` is true and `PRODUCTION` is defined),
     * the error is dispatched to the logger. In development mode, known framework
     * exceptions are re-thrown as-is; all others are wrapped in `CacheException`.
     *
     * @param Throwable $e The caught exception.
     * @param string $method Calling method name, included in the exception message.
     * @param bool $silently When true, logs silently in production instead of throwing (default: true).
     *
     * @return void
     *
     * @throws CacheException In non-production environments.
     */
    protected function errorHandler(Throwable $e, string $method, bool $silently = true): void
    {
        if ($silently && PRODUCTION) {
            Logger::dispatch('error', $e->getMessage(), [
                'class' => static::class,
                'storage' => $this->storageName
            ]);
            return;
        }

        if (!$e instanceof LuminovaException) {
            $err = $e->getPrevious() ?? $e;
            $e = (new CacheException(sprintf(
                '[%s::%s] %s', static::class, $method, $err->getMessage()
            ), $err->getCode(), $err))
                ->setFile($err->getFile())
                ->setLine($err->getLine());
        }

        throw $e;
    }

    /**
     * Encode a value using the configured serialization policy.
     *
     * Applies igbinary, PHP serialize, or JSON encoding depending on the policy.
     * When `$base64Encode` is enabled, the serialized result is base64-encoded.
     *
     * @param mixed $data Value to encode.
     *
     * @return array|string|false Encoded string on success, or false when igbinary is required but unavailable.
     */
    protected function encode(mixed $data): array|string|bool
    {
        $this->setSerializer();

        if($this->serializer === self::SERIALIZER_NONE){
            $isObject = is_object($data);
            return [
                'type' => $isObject ? 'O' : 'M',
                'raw'  => $isObject ? $this->toJsonString($data) : $data
            ];
        }

        $encoded = match ($this->serializer) {
            self::SERIALIZER_IGBINARY => ($this->isIgBinary === true)
                ? \igbinary_serialize($data)
                : false,
            self::SERIALIZER_PHP => serialize($data),
            self::SERIALIZER_JSON 
                => $this->toJsonString(['json' => $data]) ?? false,
            default => ($this->isIgBinary === true)
                ? \igbinary_serialize($data)
                : serialize($data),
        };

        if ($encoded === false || !$this->base64Encode) {
            return $encoded;
        }

        return base64_encode($encoded);
    }

    /**
     * Decode a previously encoded cache value back to its original form.
     *
     * When `$base64Encoded` is true, the data is base64-decoded first.
     * Decoding strategy is selected by `$policy` and `$serializer`.
     * Returns null when decoding fails or the serializer is unavailable.
     *
     * @param array|string $data Encoded cache value.
     * @param int $serializer Encoding policy used when the value was stored (e.g. `self::SERIALIZER_PHP`).
     * @param bool $isIgBinary Serializer flag stored alongside the payload (default: false).
     * @param bool $isBase64Encoded Whether the value is base64-encoded (default: false).
     *
     * @return mixed Decoded original value, or null on failure.
     */
    protected function decode(
        array|string $data,
        int $serializer,
        bool $isIgBinary = true,
        bool $isBase64Encoded = false
    ): mixed 
    {
        if($data === ''){
            return null;
        }

        if ($isBase64Encoded && self::SERIALIZER_NONE !== $serializer) {
            $data = base64_decode($data, true);

            if ($data === false) {
                return null;
            }
        }

        return match ($serializer) {
            self::SERIALIZER_IGBINARY => $this->binaryUnserialize($data, $isIgBinary),
            self::SERIALIZER_PHP      => $this->phpUnserialize($data),
            self::SERIALIZER_JSON     => $this->decodeJson($data),
            self::SERIALIZER_NONE     => $this->decodeRaw($data),
            default                   => $this->autoUnserialize($data, $isIgBinary),
        };
    }

    /**
     * Check if a value is an PHP serialized string.
     *
     * @param mixed $data The value to check.
     * 
     * @return bool True if PHP serialized, false otherwise.
     */
    protected static function isPhpSerialized(mixed $data): bool
    {
        if (!is_string($data) || strlen($data) < 4) {
            return false;
        }

        return preg_match('/^(?:N;|[abCdEiOsR]:)/', $data) === 1;
    }
  
    /**
     * Checks whether a value appears to be an igbinary-serialized string.
     *
     * This performs a best-effort inspection of the igbinary header and should
     * not be relied on as a definitive format check.
     *
     * @param mixed $data The value to inspect.
     * @return bool Returns true if the value appears to be igbinary serialized.
     */
    protected static function isBinarySerialized(mixed $data): bool
    {
        if (!is_string($data) || strlen($data) < 4) {
            return false;
        }

        return match (substr($data, 0, 4)) {
            "\x00\x00\x00\x01",
            "\x00\x00\x00\x02" => true,
            default => false,
        };
    }

    /**
     * Decode and return multiple cache items, deleting expired entries.
     *
     * Iterates `$items`, skips expired entries (calling `$onDeleteExpired` for each),
     * decodes payloads not yet decoded, updates the local pool, and returns the
     * result keyed by cache key.
     *
     * @param array<string,mixed> $items Items to process, keyed by cache key.
     * @param Closure(string): void $onDeleteExpired Callback invoked with the key of each expired item to delete.
     * @param bool $withMetadata When false, returns values only; when true, returns metadata arrays (default: true).
     * @param array<string,mixed> $decoded  Accumulator for decoded results (default: []).
     *
     * @return array<string,mixed> Decoded items keyed by cache key.
     */
    protected function decodes(
        array $items, 
        \Closure $onDeleteExpired, 
        bool $withMetadata = false,
        array $decoded = []
    ): array 
    {
        foreach ($items as $key => $item) {
            $key = $this->toKey($key);

            if($item === null || isset($normalized[$key])){
                continue;
            }

            if($this->isExpired($item)){
                if ($this->garbageCollectExpiredLocks || (bool) ($item[self::LOCK] ?? false) === false) {
                    $onDeleteExpired($key);
                    continue;
                }
            }

            $isDecoded = $item[self::DECODED] ?? false;
            $content = $item[self::DATA] ?? '';

            if($content && !$isDecoded){
                $content = $this->decode(
                    $content,
                    (int)  ($item[self::SERIALIZER] ?? self::SERIALIZER_NONE),
                    (bool) ($item[self::IGBINARY] ?? false),
                    (bool) ($item[self::BASE64] ?? true)
                );
            }

            $result = $item;

            $result[self::DATA] = $content;
            $result[self::DECODED] = true;

            $this->items[$key] = $result;

            if(!$withMetadata){
                $decoded[$key] = $content;
                continue;
            }

            $decoded[$key] = $this->toMetadata($result);
        }

        return $decoded;
    }

    /**
     * Process a single fetched result entry for use with `getDelayed`.
     *
     * Checks expiration, decodes the payload, merges CAS/flags metadata when
     * present, and either invokes `$onItem` or returns the normalized item array.
     * Returns null when the item is expired and a callback is provided, or after
     * calling the callback (the callback consumes the result).
     *
     * @param array<string,mixed>  $result  Raw result entry with at minimum `'key'` and `'value'` fields.
     * @param callable|null  $onItem  When set, is called with `($this, $item)` and null is returned (default: null).
     *
     * @return array<string,mixed>|null Normalized item array, or null when the callback consumed the result.
     */
    protected function onItem(array $result, ?callable $onItem = null): ?array 
    {
        $item = [];
        $key = $this->toUserKey($result['key'] ?? '');

        if ($this->isExpired($result['value'] ?? null)) {
            if($onItem){
                return null;
            }

            $item = [
                'key' => $key, 
                'value' => null
            ];
        }

        $cas = $result['cas'] ?? null;
        $flags = $result['flags'] ?? null;

        if($item === []){
            $item = [
                'key'   => $key,
                'value' => $this->decode(
                    $result['value'][self::DATA], 
                    (int)  ($result['value'][self::SERIALIZER] ?? self::SERIALIZER_NONE),
                    (bool) ($result['value'][self::IGBINARY] ?? false),
                    (bool) ($result['value'][self::BASE64] ?? false)
                ),
            ];
        }

        if($cas !== null){
            $item['cas'] = $cas;
        }

        if($flags !== null){
            $item['flags'] = $flags;
        }

        if($onItem !== null){
            $onItem($this, $item);
            return null;
        }

        return $item;
    }

    /**
     * Build a raw cache payload array from an already-encoded value.
     *
     * Captures the current timestamp, resolved TTL, encoding flags, and storage
     * hash into a flat array ready for JSON serialization and backend storage.
     *
     * @param array|string $encoded Serialized/encoded content.
     * @param DateTimeInterface|DateInterval|int|null   $expiration Expiry specification (passed to `ttlToSeconds()`).
     * @param bool $lock When true, marks the item as locked (default: false).
     *
     * @return array<string,mixed> Cache payload with keys:
     *         `_t` (timestamp), `_x` (ttl), `_i` (data), `_b` (igbinary),
     *         `_e` (base64 flag), `_s` (serializer), `_l` (lock), `_d` (decoded flag), `_h` (storage hash).
     */
    protected function createRawPayload(
        array|string $encoded,
        DateTimeInterface|DateInterval|int|null $expiration,
        bool $lock = false
    ): array
    {
        return [
            self::TTL         => $this->ttlToSeconds($expiration),
            self::DATA        => $encoded,
            self::TIMESTAMP   => Time::now($this->timezone)->getTimestamp(),
            self::SERIALIZER  => $this->serializer,
            self::BASE64      => $this->base64Encode,
            self::IGBINARY    => $this->isIgBinary,
            self::LOCK        => $lock,
            self::DECODED     => false,
            self::HASH        => $this->storage,
        ];
    }

    /**
     * Map an internal payload array to a public metadata array.
     *
     * Returns null when `$item` is empty.
     *
     * @param array<string,mixed> $item Internal cache payload.
     *
     * @return array<string,mixed>|null Associative metadata array with keys:
     *         `timestamp`, `ttl`, `data`, `igbinary`, `encoding`, `serializer`, `lock`, `decoded`, `hash`;
     *         or null when `$item` is empty.
     */
    protected function toMetadata(array $item): ?array
    {
        if($item === []){
            return null;
        }

        return [
            'data'        => $item[self::DATA] ?? null,
            'ttl'         => $item[self::TTL] ?? -1,
            'lock'        => $item[self::LOCK] ?? false,
            'hash'        => $item[self::HASH] ?? $this->storage,
            'base64'      => $item[self::BASE64] ?? $this->base64Encode,
            'decoded'     => $item[self::DECODED] ?? true,
            'igbinary'    => $item[self::IGBINARY] ?? $this->isIgBinary,
            'timestamp'   => $item[self::TIMESTAMP] ?? -1,
            'serializer'  => $item[self::SERIALIZER] ?? $this->serializer,
        ];
    }

    /**
     * Build a normalized empty cache item suitable for returning on a cache miss.
     *
     * Produces a metadata array with a null data value and a timestamp set to
     * one second before the stored creation time (or the current time minus one
     * second), to clearly signal the item is not a live entry.
     *
     * @param array<string,mixed> $metadata Partial metadata from the original (expired/missing) payload.
     *
     * @return array<string,mixed> Normalized metadata array (see `toMetadata()`).
     */
    protected function createEmptyCacheItem(array $metadata): array
    {
        $item = $this->createRawPayload(
            '',
            $metadata[self::TTL] ?? null,
            $metadata[self::LOCK] ?? false
        );
        $item[self::DATA] = null;
        $item[self::DECODED] = true;

        $timestamp = ($item[self::TIMESTAMP] ?? 1) - 1;
        $item[self::TIMESTAMP] = $metadata[self::TIMESTAMP] ?? $timestamp;

        return $this->toMetadata($item);
    }

    /**
     * Build the fully qualified cache key for the current storage context.
     *
     * Format: `prefix:hash:key`.
     * If the key already carries the prefix, it is returned unchanged.
     *
     * @param string $key Raw item key.
     *
     * @return string Namespaced cache key.
     */
    protected function toKey(string $key): string
    {
        return $this->buildKey($this->storage, $key);
    }

    /**
     * Build fully qualified cache keys for a list of raw item keys.
     *
     * Empty strings are silently skipped.
     *
     * @param string[] $keys Raw item keys.
     * @param string|null $storageHash Storage hash to use as namespace prefix.
     *                                 Defaults to `$this->storage` when null (default: null).
     *
     * @return string[] Namespaced cache keys.
     */
    protected function toKeys(array $keys, ?string $storageHash = null): array
    {
        $storageHash ??= $this->storage;
        $normalized = [];

        foreach($keys as $key){
            if($key === ''){
                continue;
            }

            $normalized[] = $this->buildKey($storageHash, $key);
        }

        return $normalized;
    }

    /**
     * Build a single fully qualified cache key from a storage hash and a raw item key.
     *
     * Trims leading/trailing whitespace and colons from the key.
     * Format: `prefix:hash:key`.
     * Returns the key unchanged when it already carries the expected prefix.
     *
     * @param string $storageHash Hashed storage name (from `self::hashStorage()`).
     * @param string $key Raw item key.
     *
     * @return string Namespaced cache key.
     */
    protected function buildKey(string $storageHash, string $key): string
    {
        $key = trim($key, " \t\n\r\0\x0B:");
        $prefix = self::KEY_PREFIX . "{$storageHash}:";

        return str_starts_with($key, $prefix)
            ? $key
            : $prefix . $key;
    }

    /**
     * Extract the user-specified cache key from a fully qualified key.
     * 
     * Based on {@see self::buildKey()}, {@see self::toKey()} and {@see self::toKeys()},
     * this method extract user key suffix.
     *
     * @param string $key Raw item key.
     * @param string|null $storageHash Storage hash to use as namespace prefix.
     * 
     * @return string User specified cache key.
     */
    protected function toUserKey(string $key, ?string $storageHash = null): string
    {
        $storageHash ??= $this->storage;
        $key = trim($key);

        $prefix = self::KEY_PREFIX . "{$storageHash}:";

        if (!str_starts_with($key, $prefix)) {
            return $key;
        }

        return substr($key, strlen($prefix));
    }

    /**
     * Convert an expiration value to a TTL in seconds.
     *
     * - `int`               → returned as-is.
     * - `DateInterval`      → converted to total seconds.
     * - `DateTimeInterface` → seconds remaining until that timestamp.
     * - `null`              → returns null (no expiration).
     *
     * @param DateTimeInterface|DateInterval|int|null $ttl Expiration value.
     *
     * @return int|null TTL in seconds, or null when no expiration is defined.
     */
    protected function ttlToSeconds(DateTimeInterface|DateInterval|int|null $ttl): ?int 
    {
        if(($ttl instanceof DateInterval) || ($ttl instanceof DateTimeInterface)){
            return Time::toTtl($ttl);
        }

        return $ttl;
    }

    /**
     * Validate format and length constraints for a persistent connection identifier.
     *
     * Allowed characters: letters, digits, dot (`.`), underscore (`_`), dash (`-`), colon (`:`).
     * Maximum length: 64 characters.
     *
     * @param string $id Persistent ID to validate.
     *
     * @return void
     * @throws InvalidArgumentException If the ID is empty, exceeds 64 characters, 
     *              or contains invalid characters.
     */
    protected function assertPersistentId(string $id): void
    {
        if ($id === '') {
            throw new InvalidArgumentException('Persistent ID cannot be empty.');
        }

        if (strlen($id) > 64) {
            throw new InvalidArgumentException('Persistent ID is too long (max 64 characters).');
        }

        if (!preg_match('/^[a-zA-Z0-9._:-]+$/', $id)) {
            throw new InvalidArgumentException(
                'Invalid persistent ID format. Allowed: letters, numbers, dot, underscore, dash, colon.'
            );
        }
    }

    /**
     * Remove preloaded cache items for a specific storage namespace.
     *
     * Clears matching entries from the internal preload collection without
     * affecting the underlying cache storage. An optional callback may be
     * executed for each removed item.
     *
     * @param string|null $storage Storage namespace whose preloaded items should be removed.
     *                               Uses the current storage name when null.
     * @param (Closure(string $key, int $count): void)|null $onRemove Optional callback 
     *                               invoked with the removed cache key.
     *
     * @return int Return number of deleted items.
     */
    protected function clearPreloadItems(?string $storage = null, ?Closure $onRemove = null): int
    {
        if($this->items === []){
            return 0;
        }

        $deleted = 0;
        $storage ??= $this->storage;
        $prefix = self::KEY_PREFIX . "{$storage}:";

        foreach(array_keys($this->items) as $key){
            if(!str_starts_with($key, $prefix)){
                continue;
            }

            unset($this->items[$key]);
            $deleted++;

            if($onRemove){
                $onRemove($key, $deleted);
            }
        }

        return $deleted;
    }

    /**
     * Check whether the active handle 
     * is a live Redis, Memcached or Stream instance.
     *
     * @return bool Returns true when `$this->conn` is an object.
     */
    protected function isConn(): bool 
    {
        return $this->conn !== null && (
            $this->conn instanceof Stream
            || $this->conn instanceof Memcached
            || $this->conn instanceof Redis
        );
    }

    /**
     * Check if cache server is connected.
     * 
     * If not connected throw error
     *
     * @return bool Return false if connected.
     * @throws CacheException
     */
    protected function isConnectionError(): bool 
    {
        if (!$this->isConnected || !$this->isConn()) {
            $this->errorHandler(
                new CacheException(sprintf(
                    'Cache server not connected. Call %s->connect()',
                    static::class
                ), ErrorCode::UNABLE_TO_CONNECT),
                'write'
            );
            return true;
        }

        return false;
    }

    /**
     * Assert that a storage namespace is configured and that the provided key(s) are non-empty.
     *
     * @param string|string[] $key A single cache key or an array of cache keys.
     *
     * @return void
     *
     * @throws CacheException  If no storage is configured.
     * @throws InvalidArgumentException If the key is an empty string or an empty array.
     */
    protected function assertStorageAndKey(string|array $key): void 
    {
        if (empty($this->storage)) {
            throw new CacheException(
                'No cache storage specified. Use setStorage() before performing cache operations.'
            );
        }

        if ($key === '' || $key === []) {
            throw new InvalidArgumentException(
                is_array($key)
                    ? 'Cache keys cannot be an empty array.'
                    : 'Cache key cannot be an empty string.'
            );
        }
    }

    /**
     * Determine whether an internal cache payload has expired.
     *
     * Returns true when: `$item` is null, the stored timestamp is 0, or the
     * elapsed time since creation meets or exceeds the stored TTL.
     * Returns false when TTL is null (item never expires) or when the TTL has not elapsed.
     *
     * @param array<string,mixed>|null $item Internal cache payload, or null for an unconditional miss.
     *
     * @return bool Returns true if the item has expired or is invalid, false if still valid.
     */
    protected function isExpired(?array $item): bool
    {
        if ($item === null) {
            return true;
        }

        $timestamp = (int) ($item[self::TIMESTAMP] ?? 0);

        if ($timestamp === 0) {
            return true;
        }

        $expiration = $item[self::TTL] ?? null;

        if ($expiration === null) {
            return false;
        }

        $elapsed = Time::now($this->timezone)->getTimestamp() - $timestamp;

        return $elapsed >= $expiration;
    }

    /**
     * Encode a value using the configured serialization policy.
     *
     * @param mixed $data Value to serialize.
     *
     * @return string|false Encoded string on success, or false on failure.
     *
     * @deprecated Use `encode()` instead.
     */
    protected function enSerialize(mixed $data): string|bool
    {
        return $this->encode($data);
    }

    /**
     * Decode a previously serialized cache value.
     *
     * @param string   $data        Serialized cache value.
     * @param int|null $serializer  Serializer flag used at storage time: `0` = PHP, `1` = igbinary (default: 1).
     * @param bool     $encoding    Whether the value is base64-encoded (default: true).
     *
     * @return mixed Decoded original value, or null on failure.
     *
     * @deprecated Use `decode()` instead.
     */
    protected function deSerialize(string $data, ?int $serializer = 1, bool $encoding = true): mixed
    {
        return $this->decode(
            $data, 
            self::SERIALIZER_PHP_AUTO, 
            $serializer, 
            $encoding
        );
    }

    /**
     * IG binary unserialize.
     *
     * @param string $data
     * @param bool $enabled
     * 
     * @return mixed
     */
    private function binaryUnserialize(string $data, bool $enabled): mixed
    {
        if (!$enabled || !self::isBinarySerialized($data)) {
            return $data;
        }

        return \igbinary_unserialize($data);
    }

    /**
     * PHP unserialize
     *
     * @param string $data
     * 
     * @return mixed
     */
    private function phpUnserialize(string $data): mixed
    {
        return unserialize($data);
    }

    /**
     * JSON decode
     *
     * @param string $data
     * @param string|null $property
     * 
     * @return mixed
     */
    private function decodeJson(string $data, ?string $property = 'json'): mixed
    {
        if($data === ''){
            return null;
        }

        if(!json_validate($data)){
            return $data;
        }

        $data = json_decode($data, false, 512, JSON_THROW_ON_ERROR);

        if(!$property){
            return $data;
        }

        return isset($data->{$property}) 
            ? $data->{$property} 
            : $data;
    }

    /**
     * Raw decode.
     *
     * @param array $data
     * 
     * @return mixed
     */
    private function decodeRaw(array $data): mixed 
    {
        return (($data['type'] ?? 'M') === 'O') 
            ? $this->decodeJson($data['raw'] ?? '')
            : ($data['raw'] ?? null);
    }
}