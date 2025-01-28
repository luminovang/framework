<?php 
/**
 * Luminova Framework memcached extension class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Cache;

use \Luminova\Base\BaseCache;
use \Luminova\Time\Timestamp;
use \Luminova\Logger\Logger;
use \Luminova\Exceptions\CacheException;
use \Memcached;
use \DateTimeInterface;
use \DateInterval;
use \Exception;

final class MemoryCache extends BaseCache
{
    /**
     * Hold the cache instance Singleton.
     * 
     * @var ?self $instance
     */
    private static ?self $instance = null;

    /**
     * @var array<int,mixed> Memcached configuration servers.
     */
    private array $config = [];

    /**
     * Memcached connection instances.
     * 
     * @var array<string,Memcached> $instances
    */
    private static array $instances = [];

    /**
     * Initializes memcache extension class instance with an optional storage name and an optional persistent ID.
     * 
     * @param string|null $storage The cache storage name (default: null). If null, you must call `create` method later.
     * @param string|null $persistent_id Optional unique ID for persistent connections (default: null).
     *                      - If null is specified, will used the default persistent id from environment variables.
     *                      - If not set in environment variables `default` will be used instead,
     * 
     * @throws CacheException if there is an issue loading the cache.
     */
    public function __construct(
        ?string $storage = null, 
        private ?string $persistent_id = null
    )
    {
        parent::__construct();
        $this->persistent_id ??= env('memcached.persistent.id', 'default');
        //$this->config = ($this->config === []) ? (configs('Storage', [])['memcache'] ?? []) : $this->config;

        if(!$this->connect()){
            throw new CacheException('Could not connect to memcache server');
        }

        if($storage){
            $this->setStorage($storage);
        }
	}

    /**
     * Retrieves or creates a singleton instance of the memcache extension class.
     * 
     * @param string|null $storage The cache storage name (default: null). If null, you must call `create` method later.
     * @param string|null $persistent_id Optional unique ID for persistent connections (default: null).
     *                      - If null is specified, will used the default persistent id from environment variables.
     *                      - If not set in environment variables `default` will be used instead,
     * 
     * @return static The singleton instance of the cache.
     * @throws CacheException if there is an issue loading the cache.
     */
    public static function getInstance(
        ?string $storage = null, 
        ?string $persistent_id = null
    ): static 
    {
        if (self::$instance === null) {
            self::$instance = new static($storage, $persistent_id);
        }

        return self::$instance;
    }

    /**
     * Retrieves the current connection instance of the cache Memcached being used. 
     * 
     * @return Memcached|null Return the instance of Memcached, otherwise null.
     */
    public function getConn(): ?Memcached
    {
        return self::$instances[$this->persistent_id] ?? null;
    }

    /**
     * Retrieves the current connection persistent id being used. 
     * 
     * @return string|null Return the persistent id, otherwise null.
     */
    public function getId(): ?string
    {
        return $this->persistent_id;
    }

    /**
     * Set cache storage sub directory path to store cache items.
     * 
     * @param string $subfolder The cache storage root directory.
     * 
     * @return self Returns the memory cache instance.
     * @throws CacheException Throws if unable to reconnect after changing persistent id.
     */
    public function setId(string $persistent_id): self 
    {
        $this->persistent_id = $persistent_id;
        if(!$this->connect()){
            throw new CacheException(sprintf('Could not connect to memcache server using persistent id: %s', $this->persistent_id));
        }
        
        return $this;
    }

    /**
     * Adds a server to the cache configuration.
     * 
     * @param string $host The server hostname or IP address.
     * @param int $port The server port number.
     * @param int $weight Optional weight for the server (default: 0).
     * 
     * @return self Returns the memory cache instance.
     * 
     * > **Note:** After setting server you should call `reconnect` method to connect to new servers.
     */
    public function setServer(string $host, int $port, int $weight = 0): self 
    {
        $this->config[] = [$host, $port, $weight];
        return $this;
    }

    /**
     * Sets multiple servers in the cache configuration.
     * 
     * @param array<int,string|int> $config An array of server configurations where each element is an array [host, port, weight].
     * 
     * @return self Returns the memory cache instance.
     * 
     * > **Note:** After setting servers you should call `reconnect` method to connect to new servers.
     */
    public function setServers(array $config): self 
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Sets an option for the cache.
     * 
     * @param int $option The option to set.
     * @param mixed $value The value for the option.
     * 
     * @return self Returns the memory cache instance.
     */
    public function setOption(int $option, mixed $value): self 
    {
        $this->getConn()?->setOption($option, $value);
        return $this;
    }

    /**
     * Connects to the Memcached server(s) using the configured settings.
     * 
     * Initializes a connection to the Memcached server(s) with the defined host, port, and weight in env file
     * or using the provided server configuration.
     * 
     * @return bool Returns true if the connection is successful, false otherwise.
     */
    public function connect(): bool 
    {
        $conn = $this->getConn();
        $result = true;

        if($conn === null){
            $conn = new Memcached($this->persistent_id);
            $conn->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
            
            if(($prefix = env('memcached.key.prefix', null)) !== null){
                $conn->setOption(Memcached::OPT_PREFIX_KEY, $prefix);
            }
        }

        if(!count($conn->getServerList())) {
            $result = ($this->config === []) ? $conn->addServer(
                env('memcached.host', 'localhost'), 
                env('memcached.port', 11211), 
                env('memcached.server.weight', 0)
            ) : $conn->addServers($this->config);
        }
    
        self::$instances[$this->persistent_id] = $conn;
        return $result;
    }

    /**
     * Reconnect, closes the connection to the memcached server and initialize a new connection.
     * 
     * @return bool Returns true if the connection is successful, otherwise false.
     */
    public function reconnect(): bool 
    {
        if($this->disconnect()){
            return $this->connect();
        }

        return false;
    }

    /**
     * Closes the connection to the Memcached server.
     * 
     * Gracefully terminates the connection to the Memcached server. If no connection exists, the method will return `true` immediately.
     * 
     * @return bool Returns true if the disconnection is successful or if no connection was open.
     */
    public function disconnect(): bool 
    {
        if ($this->getConn() === null) {
            return true;
        }

        if($this->getConn()->resetServerList() && $this->getConn()->quit()){
            self::$instances[$this->persistent_id] = null;
            return true;
        }

        return false;
    }

    /**
     * Check if result code matches with a given code.
     * 
     * @param int $resultCode The memcached result code (default: Memcached::RES_SUCCESS).
     * 
     * @return bool Returns true if the result code matches, otherwise false.
    */
    public function is(int $resultCode = Memcached::RES_SUCCESS): bool
    {
        return ($this->getConn()?->getResultCode() === $resultCode);
    }

    /**
     * {@inheritdoc}
    */
    public function setStorage(string $storage): self
    {
        $this->storage = self::hashStorage($storage);
        $this->storageName = $storage;
        return $this;
    }

    /**
     * {@inheritdoc}
    */
    public function execute(
        array $keys, 
        bool $withCas = false, 
        ?callable $callback = null
    ): bool 
    {
        $this->assertStorageAndKey($keys);
        $this->iterator = [];
        $this->position = 0;
        
        if(!$this->getConn()){
            return false;
        }

        return $this->getConn()->getDelayedByKey(
            $this->storage, 
            $keys, 
            $withCas,
            fn($cache, $result) => $this->parseItem($result, $callback)
        );
    }

    /**
     * {@inheritdoc}
    */
    public function getItem(string $key, bool $onlyContent = true): mixed
    {
        $this->assertStorageAndKey($key);

        if (!$this->read($key)) {
            return $this->respondWithEmpty($onlyContent);
        }

        if ($this->hasExpired($key)) {
            return $this->respondWithEmpty($onlyContent);
        }

        // Decode the cache data if not already decoded.
        if(!$this->items[$key]['decoded']){
            $this->items[$key]['data'] = $this->deSerialize(
                $this->items[$key]['data'],
                $this->items[$key]['serialize']
            );
            $this->items[$key]['decoded'] = true;
        }

        // Auto delete expired caches, so next time we get fresh one.
        $this->deleteIfExpired();

        return $onlyContent 
            ? ($this->items[$key]['data'] ?? null) 
            : ($this->items[$key] ?? null);
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
        $this->assertStorageAndKey($key);

        $content = $this->enSerialize($content);

        if (!$content) {
            CacheException::throwException('Failed to serialize cache data.');
            return false;
        }

        if ($expiration !== null) {
            $expireAfter = null;
        }

        $this->items[$key] = [
            "timestamp" => time(),
            "expiration" => ($expiration instanceof DateTimeInterface) ? Timestamp::ttlToSeconds($expiration) : $expiration,
            "expireAfter" => ($expireAfter instanceof DateInterval) ? Timestamp::ttlToSeconds($expireAfter) : $expireAfter,
            "data" => $content,
            "lock" => $lock,
            "encoding" => 'raw',
            'decoded' => false,
            "serialize" => $this->serialize,
            "hash-sum" => $this->storage
        ];

        return $this->commit();
    }

    /**
     * {@inheritdoc}
    */
    public function hasItem(string $key): bool
    {
        if (!$key || !$this->read($key)) {
            return false;
        }

        return isset($this->items[$key]);
    }

    /**
     * {@inheritdoc}
    */
    public function isLocked(string $key): bool 
    {
        if (!$this->hasItem($key)) {
            return true;
        }

        return isset($this->items[$key]['lock']);
    }

    /**
     * {@inheritdoc}
    */
    public function hasExpired(string $key): bool 
    {
        if (!$this->hasItem($key)) {
            return true;
        }

        return $this->isExpired($this->items[$key]);
    }

    /**
     * {@inheritdoc}
    */
    public function deleteItem(string $key, bool $includeLocked = false): bool 
    {
        if(!$key || !$this->getConn()){
            return false;
        }

        if (
            $this->hasItem($key) &&
            ($includeLocked || !$this->isLocked($key)) &&
            $this->getConn()->deleteByKey($this->storage, $key)
        ) {
            unset($this->items[$key]);
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
    */
    public function deleteItems(iterable $keys, bool $includeLocked = false): bool 
    {
        $deletedCount = 0;
        foreach ($keys as $key) {
            if ($key !== '' && $this->deleteItem($key, $includeLocked)) {
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
        if (!$this->getConn()) {
            return false;
        }

        if ($this->getConn()->resetServerList() && $this->getConn()->flush()) {
            $this->items = [];
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
    */
    public function clear(): bool
    {
        if (!$this->storage || !$this->getConn()) {
            return false;
        }

        $keys = $this->getConn()->getAllKeys();

        if($keys === false || $keys === []){
            return false;
        }

        $this->getConn()->deleteMultiByKey($this->storage, $keys);

        if($this->is(Memcached::RES_SUCCESS)){
            $this->items = [];
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
    */
    public function delete(string $storage, array $keys): bool
    {
        $storage = self::hashStorage($storage);
        return $this->getConn()?->deleteMultiByKey($storage, $keys);
    }

    /**
     * {@inheritdoc}
    */
    protected function deleteIfExpired(): void 
    {
        if(!$this->autoDeleteExpired || !$this->storage || !$this->getConn()){
            return;
        }

        foreach ($this->items as $key => $value) {
            // Check if the cache item is loaded, expired, and either unlocked or
            // deletion of locked items is allowed
            if (
                $this->hasItem($key) &&
                $this->isExpired($value) &&
                ($this->includeLocked || !$value['lock'])
            ) {
                if ($this->getConn()->deleteByKey($this->storage, $key)) {
                    unset($this->items[$key]);
                }
            }
        }
    }

    /**
     * Parse and process cache items.
     * 
     * This method processes cache items to handle serialization and encoding, then filters out 
     * expired items and prepares them for further use.
     * 
     * @param array $result The cache items to parse, typically from a fetch operation.
     * @param ?callable $callback The callback function (default: null).
     * 
     * @return void
     */
    private function parseItem(array $result, ?callable $callback = null): void 
    {
        if (!$this->isExpired($result['value'])) {
            $cas = $result['cas'] ?? false;
            $flags = $result['flags'] ?? false;
            $item = [
                'key' => $result['key'],
                'value' => $this->deSerialize($result['value']['data'], $result['value']['serialize']),
            ];

            if($cas !== false){
                $item['cas'] = $cas;
            }

            if($flags !== false){
                $item['flags'] = $flags;
            }

            if ($callback !== null) {
                $callback($this, $item);
            }

            $this->iterator[] = $item;
        }
    }

    /**
     * {@inheritdoc}
    */
    protected function read(?string $key = null): bool
    {
        if(!$this->storage || !$this->getConn()){
            return false;
        }
        
        if (isset($this->items[$key])) {
            return true;
        }

        try {
            $content = $this->getConn()->getByKey($this->storage, $key);
        } catch (Exception) {
            return false;
        }

        if ($content === false) {
            return false;
        }

        if ($content === []) {
            $this->getConn()->deleteByKey($this->storage, $key);
            return false;
        }

        // If the stored hash is not same as the current storage hash,
        // Then don't load because not in same storage context.
        if($content['hash-sum'] !== $this->storage){
            return false;
        }

        $content['data'] = $this->deSerialize($content['data'], $content['serialize']);
        $content['decoded'] = true;

        $this->items[$key] = $content;
        return true;
    }

    /**
     * {@inheritdoc}
    */
    protected function commit(): bool
    {
        if(!$this->storage || !$this->items || !$this->getConn()){
            return false;
        }

        try {
            // TTL (Time-To-Live) for cache storage (default: 0 never expire).
            // Item expiration is set withing the payload.
            return $this->getConn()->setMultiByKey($this->storage, $this->items, 0);
        } catch (Exception $e) {
            if (PRODUCTION) {
                Logger::dispatch('error', sprintf('Unable to commit cache: %s', $e->getMessage()), [
                    'class' => self::class
                ]);
                return false;
            }

            throw new CacheException(sprintf('Unable to commit cache: %s', $e->getMessage()));
        }
    }
}