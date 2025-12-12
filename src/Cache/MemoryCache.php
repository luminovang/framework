<?php 
declare(strict_types=1);
/**
 * Luminova Framework Memcached cache driver.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Cache;

use \Memcached;
use \Throwable;
use \DateInterval;
use \DateTimeInterface;
use Luminova\Luminova;
use Luminova\Base\Cache;
use Luminova\Exceptions\CacheException;

final class MemoryCache extends Cache
{
    /**
     * Singleton instance returned by `getInstance()`.
     *
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Server pool configuration collected before `connect()` or `reconnect()` is called.
     *
     * Each entry: `[host, port, weight]`.
     *
     * @var array<int,array{string,int,int}> $servers
     */
    private array $servers = [];

    /**
     * Active Memcached connection instances keyed by persistent ID.
     *
     * @var array<string,Memcached> $instances
     */
    private static array $instances = [];

    /**
     * Default server configuration resolved from environment variables.
     *
     * Format: `[host, port, weight]`.
     *
     * @var array{0:string,1:int,2:int}|null $default
     */
    private static ?array $default = null;

    /**
     * Initialize the Memcached driver.
     *
     * Reads connection parameters from environment variables. If `$storage` is provided,
     * `setStorage()` is called immediately.
     *
     * Environment variables consumed:
     * - `memcached.host`            (default: `127.0.0.1`)
     * - `memcached.port`            (default: `11211`)
     * - `memcached.server.weight`   (default: `0`)
     * - `memcached.persistent.id`   (default: `'default'`)
     * - `memcached.key.prefix`      (default: none)
     *
     * @param string|null $storage      Cache storage name. When null, call `setStorage()` before
     *                                  performing any cache operations (default: null).
     * @param string|null $persistentId Persistent connection identifier for connection pooling.
     *                                  Falls back to the `memcached.persistent.id` env variable,
     *                                  then `'default'` (default: null).
     *
     * @throws CacheException If the Memcached extension is unavailable or initialization fails.
     */
    public function __construct(?string $storage = null, ?string $persistentId = null)
    {
        parent::__construct();
        self::$default ??= [
            env('memcached.host', '127.0.0.1'),
            (int) env('memcached.port', 11211),
            (int) env('memcached.server.weight', 0)
        ];

        $persistentId = $persistentId 
            ?? env('memcached.persistent.id') 
            ?? env('system.cache.persistent.id');

        if($persistentId){
            $this->setPersistentId($persistentId);
        }

        if($storage){
            $this->setStorage($storage);
        }
	}

    /**
     * Get the singleton instance of this driver.
     *
     * Creates the instance on first call; subsequent calls return the same object.
     *
     * @param string|null $storage Cache storage name (default: null).
     * @param string|null $persistentId Persistent connection identifier (default: null).
     *
     * @return self Returns the singleton instance.
     *
     * @throws CacheException If initialization fails.
     */
    public static function getInstance(
        ?string $storage = null, 
        ?string $persistentId = null
    ): self 
    {
        if (static::$instance === null) {
            self::$instance = new self($storage, $persistentId);
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getConn(): ?Memcached
    {
        return self::$instances[$this->persistentId] ?? null;
    }

    /**
     * Check whether the last Memcached result code matches the expected code(s).
     *
     * @param int|int[] $code Expected result code(s) (default: `Memcached::RES_SUCCESS`).
     *
     * @return bool Returns true when the last result code matches, false otherwise.
     */
    public function isResultCode(array|int $code = Memcached::RES_SUCCESS): bool
    {
        if (!$this->isConn()) {
            return false;
        }

        if (is_int($code)) {
            return $this->conn->getResultCode() === $code;
        }

        return in_array($this->conn->getResultCode(), $code, true);
    }

    /**
     * Add a single Memcached server to the configuration pool.
     *
     * Servers are collected locally and only applied when `connect()` or
     * `reconnect()` is called.
     *
     * @param string $host Server hostname or IP address.
     * @param int $port  Server port number.
     * @param int $weight Optional server weight (used for consistent hashing).
     *
     * @return self Returns the memory cache instance.
     */
    public function addServer(string $host, int $port, int $weight = 0): self
    {
        $this->servers[] = [$host, $port, $weight];
        return $this;
    }

    /**
     * Replace the current Memcached server pool configuration.
     *
     * Each server entry must follow:
     * [host, port, weight]
     *
     * @param array<int,array{string,int,int}> $servers Server pool configuration.
     *
     * @return self Returns the memory cache instance.
     * 
     * > **Note:** 
     * > After setting servers you should call `reconnect` method to connect to new servers.
     */
    public function setServers(array $servers): self
    {
        $this->servers = $servers;
        return $this;
    }

    /**
     * Set a Memcached option on the active connection.
     *
     * @param int   $option Memcached option constant (e.g. `Memcached::OPT_PREFIX_KEY`).
     * @param mixed $value  Option value.
     *
     * @return self Returns the current instance.
     *
     * @throws CacheException If no connection is active.
     */
    public function setOption(int $option, mixed $value): self 
    {
        if(!$this->isConn()){
            throw new CacheException('Refuse to set option. Memcache is not connected');
        }

        $this->conn->setOption($option, $value);
        return $this;
    }

    /**
     * {@inheritdoc}
     * 
     * Establish a connection to the configured Memcached server pool.
     */
    public function connect(): bool
    {
        $this->conn = $this->getConn();

        if($this->conn instanceof Memcached){
            return true;
        }

        try {
            $this->conn = Luminova::kernel()->getMemcached($this->persistentId);

            if(!$this->conn instanceof Memcached){
                $this->conn = new Memcached($this->persistentId);
                $this->conn->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);

                $prefix = env('memcached.key.prefix');

                if ($prefix !== '' && $prefix !== null) {
                    $this->conn->setOption(Memcached::OPT_PREFIX_KEY, (string) $prefix);
                }
            }

            if($this->serializer === self::SERIALIZER_IGBINARY){
                $serializer = (int) $this->conn->getOption(Memcached::OPT_SERIALIZER);

                if($serializer === 0 || $serializer === Memcached::SERIALIZER_IGBINARY){
                    $this->serializer = self::SERIALIZER_NONE;
                }
            }

            if ($this->conn->getServerList() === []) {
                $this->attachServers();
            }

            $this->assertConnection();
            self::$instances[$this->persistentId] = $this->conn;
            $this->isConnected = true;

            return true;
        } catch (Throwable $e) {
            $this->errorHandler($e, 'connect');
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function ping(): ?string
    {
        if(!$this->isConn()){
            return null;
        }

        try {
            $this->conn->set('__ping__', 'PONG', 10);

            return $this->conn->get('__ping__') ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): bool
    {
        if ($this->isConn()) {
            try {
                $this->conn->quit();
                $this->conn->resetServerList();
            } catch (Throwable) {}
        }

        unset(self::$instances[$this->persistentId]);
        $this->conn = null;
        $this->isConnected = false;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function scan(string $pattern, callable $onEachKey): int 
    {
        return $this->forEach(
            pattern: $pattern, 
            onEachKey: $onEachKey, 
            chunkSize: 100, 
            delay: 1000
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getKeys(?string $pattern = null): array
    {
        $pattern ??= '*';
        $pattern = trim($pattern);

        if ($pattern === '' || !$this->isConn()) {
            return [];
        }

        $keys = [];

        $this->forEach(
            $pattern,
            static function (string $key) use (&$keys): void {
                $keys[] = $key;
            },
            500
        );

        return $keys;
    }

    /**
     * {@inheritdoc}
     */
    public function getDelayed(
        array $keys, 
        bool $withCas = false, 
        ?callable $onItem = null
    ): bool 
    {
        $this->isResult = false;

        if($keys === [] || !$this->isConn()){
            return false;
        }

        $this->assertStorageAndKey($keys);
        
        if(!$this->isConn()){
            return false;
        }

        $callback = null;

        if($onItem !== null){
            $callback = fn($cache, $result) => $this->onItem($result, $onItem);
        }

        $result = $this->conn->getDelayed(
            $this->toKeys($keys),
            $withCas,
            $callback
        );

        if($result === false){
            return false;
        }

        $this->isResult = true;
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNext(): ?array
    {
        if(!$this->isResult || !$this->isConn()){
            $this->isResult = false;
            return null;
        }

        $result = $this->conn->fetch();

        if (
            $result === false 
            || $result === null
            || $this->isResultCode(Memcached::RES_END)
        ) {
            $this->isResult = false;
            return null;
        }

        return $this->onItem($result);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchResult(): array
    {
        if(!$this->isResult || !$this->isConn()){
            $this->isResult = false;
            return [];
        }

        $items = $this->conn->fetchAll();

        if (
            $items === false 
            || $items === null
        ) {
            $this->isResult = false;
            return [];
        }

        $results = [];

        foreach($items as $item){
            $results[] = $this->onItem($item);
        }

        $this->isResult = false;
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $key, bool $withMetadata = false): mixed
    {
        $this->assertStorageAndKey($key);

        $key = $this->toKey($key);
        $this->load($key, false);

        $item = $this->items[$key] ?? [];

        if (!$item || $this->hasExpired($key)) {
            return $withMetadata 
                ? $this->createEmptyCacheItem($item)
                : null;
        }

        if(($item[self::DECODED] ?? false) === false){
            try{
                $this->items[$key][self::DATA] = $this->decode(
                    $item[self::DATA],
                    (int)  ($item[self::SERIALIZER] ?? self::SERIALIZER_NONE),
                    (bool) ($item[self::IGBINARY] ?? false),
                    (bool) ($item[self::BASE64] ?? false)
                );

                $this->items[$key][self::DECODED] = true;
            } catch(Throwable $e){
                $this->deleteItem($key, true);
                $this->errorHandler($e, 'getItem');

                return $withMetadata 
                    ? $this->createEmptyCacheItem([]) 
                    : null;
            }
        }

        return $withMetadata 
            ? $this->toMetadata($this->items[$key] ?? [])
            : ($this->items[$key][self::DATA] ?? null);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys, bool $withMetadata = false): array 
    {
        if($keys === [] || !$this->isConn()){
            return [];
        }

        $this->assertStorageAndKey($keys);
        $keys = $this->toKeys($keys);

        // $decoded = $this->decodes(
        //    $this->items,
        //    fn(string $key) => $this->conn->delete($key),
        //    $withMetadata
        // );

        $items = $this->conn->getMulti($keys);

        if($items === false){
            return [];
        }

        return $this->decodes(
            $items,
            function(string $key): void {
                if($this->conn->delete($key)){
                    unset($this->items[$key]);
                }
            },
            $withMetadata
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setItem(
        string $key, 
        mixed $content, 
        DateTimeInterface|int|null $expiration = 0, 
        DateInterval|int|null $expireAfter = null, 
        bool $lock = false
    ): bool 
    {
        return $this->write(
            $key,
            $content,
            $expiration,
            $expireAfter,
            $lock,
            false
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setItems(
        array $items,
        DateTimeInterface|int|null $expiration = 0, 
        DateInterval|int|null $expireAfter = null, 
        bool $lock = false
    ): int 
    {
        if($items === []){
            return 0;
        }

        if ($this->isConnectionError()) {
            return 0;
        }

        $committed = 0;
        $normalized = [];
        $tmp = [];
        $ttl = null;

        foreach($items as $key => $item){

            if(!$key || !$item){
                continue;
            }

            try{
                $this->assertStorageAndKey($key);

                $payload = $this->onSetItem(
                    $item,
                    $expiration,
                    $expireAfter,
                    $lock,
                    false
                );

                if ($payload === null) {
                    continue;
                }

                $data = $this->toJsonString($payload);

                if($data === null){
                    continue;
                }

                $key = $this->toKey($key);
                $normalized[$key] = $data;
                $tmp[$key] = $payload;

                $ttl = $payload[self::TTL] ?? null;
                $committed++;
            } catch(Throwable){
                continue;
            }
        }

        $ttl ??= $this->ttlToSeconds($expireAfter ?? $expiration);

        if($committed > 0 && $this->conn->setMulti($normalized, (int) $ttl) !== false){
            $this->items = array_merge(
                $this->items, 
                $tmp
            );

            return $committed;
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function exists(string|array $keys): int 
    {
        if ($keys === '' || $keys === [] || !$this->storage || !$this->isConn()) {
            return 0;
        }

        if(is_string($keys)){
            $keys = [trim($keys)];
        }

        $keys = array_flip(array_values($keys));
        $count = 0;

        $this->forEach(
            '*',
            static function (string $key) use (&$count, $keys): void {
                if (isset($keys[$key])) {
                    $count++;
                }
            },
            500
        );

        return $count;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        if (!$key || !$this->isConn()) {
            return false;
        }

        $key = $this->toKey($key);

        if (isset($this->items[$key])) {
            return true;
        }

        try{
            return $this->read($key);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isLocked(string $key): bool 
    {
        $key = $this->toKey($key);

        if (!$this->hasItem($key)) {
            return true;
        }

        return (bool) ($this->items[$key][self::LOCK] ?? false);
    }

    /**
     * {@inheritdoc}
     */
    public function hasExpired(string $key): bool 
    {
        $key = $this->toKey($key);

        if (!$this->hasItem($key)) {
            return true;
        }

        return $this->isExpired($this->items[$key] ?? null);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key, bool $gcExpiredLocks = false): bool 
    {
        $key = $this->toKey($key);

        if(!$this->hasItem($key)){
            return true;
        }

        if (!$gcExpiredLocks && $this->isLocked($key)){
            return false;
        }

        try{
            if ($this->conn->delete($this->toKey($key))) {
                unset($this->items[$key]);
                return true;
            }
        } catch (Throwable $e) {
            $this->errorHandler($e, 'deleteItem');
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(iterable $keys, bool $gcExpiredLocks = false): bool 
    {
        $deletedCount = 0;

        foreach ($keys as $key) {
            if ($key === '') {
                continue;
            }

            if ($this->deleteItem($key, $gcExpiredLocks)) {
                $deletedCount++;
            }
        }

        return $deletedCount > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): bool
    {
        if (!$this->isConn()) {
            return false;
        }

        try{
            if ($this->conn->flush() && $this->conn->resetServerList()) {
                $this->clearPreloadItems();
                return true;
            }
        } catch (Throwable $e) {
            $this->errorHandler($e, 'flush');
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        if (!$this->storage || !$this->isConn()) {
            return false;
        }

        try {
            return $this->scanAndDelete($this->toKey('*')) > 0;
        } catch (Throwable $e) {
            $this->errorHandler($e, 'clear');
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(array $keys, ?string $storage = null): bool
    {
        if($keys === [] || !$this->isConn()){
            return false;
        }

        $isCurrentStorage = true;

        if($storage === null){
            $storage = $this->storage;
        }else{
            if(!$storage){
                return false;
            }

            $isCurrentStorage = false;
            $storage = self::hashStorage($storage);
        }

        try{
            $statuses = $this->conn->deleteMulti($this->toKeys($keys, $storage));

            if($statuses === []){
                return false;
            }

            if($isCurrentStorage){
                foreach($statuses as $key => $status){
                    if($status === Memcached::RES_SUCCESS){
                        unset($this->items[$key]);
                    }
                }
            }

            return true;
        } catch (Throwable $e) {
            $this->errorHandler($e, 'delete');
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteIfExpired(): int
    {
        if (!$this->storage || !$this->isConn()) {
            return 0;
        }

        $counter = 0;

        foreach ($this->items as $key => $value) {
            $key = $this->toKey($key);

            try {
                if(!$this->hasItem($key) || !$this->isExpired($value)){
                    continue;
                }

                if (!$this->garbageCollectExpiredLocks && (bool) ($value[self::LOCK] ?? false) === true) {
                    continue;
                }

                if ($this->conn->delete($key)) {
                    unset($this->items[$key]);
                    $counter++;
                }
            } catch (Throwable) {}
        }

        return $counter;
    }

    /**
     * Attache memcache servers
     *
     * @return void
     */
    private function attachServers(): void 
    {
        $result = ($this->servers === [])
            ? $this->conn->addServer(...self::$default)
            : $this->conn->addServers($this->servers);

        if ($result) {
            return;
        }

        $server = ($this->servers === [])
            ? sprintf(
                '%s:%d',
                self::$default[0],
                self::$default[1]
            )
            : 'configured Memcached server pool';

        throw new CacheException(sprintf(
            'Failed to register %s%s. %s',
            $this->persistentId ? " [{$this->persistentId}]" : '',
            $server,
            $this->conn->getResultMessage()
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function read(?string $key = null): bool
    {
        if(!$this->storage){
            return false;
        }

        $key = $this->toKey($key);

        if (isset($this->items[$key])) {
            return true;
        }

        $this->assertConnection();
        $raw = $this->conn->get($key);

        if ($this->isResultCode(Memcached::RES_NOTFOUND)) {
            return false;
        }

        if ($raw === false || $raw === null) {
            return false;
        }

        $payload = json_decode($raw, true);
        $hash = ($payload && is_array($payload)) ? ($payload[self::HASH] ?? null) : null;

        if($hash !== $this->storage){
            $this->conn->delete($key);
            return false;
        }

        $payload[self::DECODED] = false;
        $this->items[$key]  = $payload;

        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function commit(int &$commits = 0): bool
    {
        $commits = 0;

        if (!$this->storage || $this->items === []) {
            return false;
        }

        if (!$this->isConn()) {
            return false;
        }

        $this->assertConnection();

        try {
            foreach ($this->items as $key => $payload) {

                $hash = $payload[self::HASH] ?? null;

                if($hash !== $this->storage){
                    continue;
                }

                if(($payload[self::DECODED] ?? false) === true){
                    $payload[self::DECODED] = false;

                    $raw =  $this->encode($payload[self::DATA]);
                    
                    if($raw === false){
                        continue;
                    }

                    $payload[self::DATA] = $raw;
                }

                $raw = $this->toJsonString($payload);

                if($raw === null){
                    continue;
                }

                $ttl = (int) ($payload[self::TTL] ?? 0);

                if($this->conn->set($this->toKey($key), $raw, $ttl)){
                    $commits++;
                }
            }

            return $commits > 0;
        } catch (Throwable $e) {
            $this->errorHandler(
                new CacheException(sprintf('Unable to commit item: %s', $e->getMessage()), $e->getCode(), $e), 
                'commit'
            );
        }

        return false;
    }

    /**
     * Use SCAN to iterate all keys matching $pattern and delete them in batches.
     *
     * @param string $pattern Glob-style key pattern.
     *
     * @return int Total number of keys deleted, or -1 on error.
     */
    private function scanAndDelete(string $pattern): int
    {
        $keys = [];
        $this->forEach(
            $pattern,
            function (string $key) use (&$keys): void {
                $keys[] = $key;
            },
            forUserKey: false
        );

        if($keys === []){
            return 0;
        }

        try {
            $statuses =  $this->conn->deleteMulti($keys);

            if($statuses === []){
                return 0;
            }

            $count  = 0;
            foreach($statuses as $key => $status){
                if($status === Memcached::RES_SUCCESS){
                    unset($this->items[$key]);
                    $count++;
                }
            }

            return $count;
        } catch (Throwable) {
            return -1;
        }
    }

    /**
     * Iterate items in chunk.
     *
     * @param string $pattern
     * @param callable $onEachKey
     * @param int $chunkSize
     * @param float|int $delay
     * @param bool $forUserKey
     * 
     * @return int
     */
    private function forEach(
        string $pattern, 
        callable $onEachKey, 
        int $chunkSize = 100,
        float|int $delay = 0,
        bool $forUserKey = true
    ): int 
    {
        $pattern = trim($pattern);

        if ($pattern === '' || !$this->isConn()) {
            return 0;
        }

        $this->assertStorageAndKey($pattern);

        $found = 0;
        $binaryProtocol = $this->toggleBinaryProtocol();

        try{
            $keys = $this->conn->getAllKeys();

            if (!$keys) {
                return 0;
            }

            $pattern = $this->toKeyPattern($this->toKey($pattern));
            $chunks = array_chunk($keys, $chunkSize);

            foreach ($chunks as $chunk) {
                foreach ($chunk as $key) {
                    if ($key === '' || !$this->isKeyMatch($key, $pattern)) {
                        continue;
                    }

                    $onEachKey($forUserKey ? $this->toUserKey($key) : $key);
                    $found++;
                }
                
                uwait($delay);
            }
        } finally {
            if($binaryProtocol){
                $this->toggleBinaryProtocol(true);
            }
        }

        return $found;
    }

    /**
     * Toggle binary protocol option.
     * 
     * @param bool $enable
     *
     * @return bool
     * @throws CacheException
     */
    private function toggleBinaryProtocol(bool $enable = false): bool 
    {
        if((bool) $this->conn->getOption(Memcached::OPT_BINARY_PROTOCOL) === $enable){
            return false;
        }

        if((bool) $this->conn->setOption(Memcached::OPT_BINARY_PROTOCOL, $enable) === true){
            return true;
        }

        if(!$enable){
            $this->errorHandler(new CacheException(
                    'Key scan failed, binary protocol option is enabled on your Memcached instance.'
                ), 
                'scan'
            );
        }

        return false;
    }
    
    /**
     * Normalize pattern key.
     *
     * @param string $key
     * 
     * @return string
     */
    private function toKeyPattern(string $key): string
    {
        return str_replace(
            ['\*', '\?'],
            ['.*', '.'],
            preg_quote($key, '/')
        );
    }

    /**
     * Check if key match pattern.
     *
     * @param string $key
     * @param string $pattern
     * @return bool
     */
    private function isKeyMatch(string $key, string $pattern): bool
    {
        return (bool) preg_match('/^' . $pattern . '$/i', $key);
    }

    /**
     * Assert connection.
     *
     * @return void
     */
    private function assertConnection(): void 
    {
        if (!$this->isResultCode([Memcached::RES_SUCCESS, Memcached::RES_NOTFOUND])) {
            $code = $this->conn->getResultCode();

            throw new CacheException(sprintf(
                'Memcached connection error%s: %s (%d)',
                $this->persistentId ? " [{$this->persistentId}]" : '',
                $this->conn->getResultMessage(),
                $code
            ), $code);
        }
    }

    /**
     * Check if result code matches with a given code.
     * 
     * @param int $resultCode The memcached result code (default: Memcached::RES_SUCCESS).
     * 
     * @return bool Returns true if the result code matches, otherwise false.
     * @deprecated Use isResultCode()
     */
    public function is(int $resultCode = Memcached::RES_SUCCESS): bool
    {
        return $this->isConn() 
            && $this->conn->getResultCode() === $resultCode;
    }
}