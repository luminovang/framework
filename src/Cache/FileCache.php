<?php 
declare(strict_types=1);
/**
 * Luminova Framework file system cache class.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Cache;

use \Throwable;
use \DateInterval;
use \DateTimeInterface;
use Luminova\Base\Cache;
use Luminova\Storage\Stream;
use Luminova\Storage\Filesystem;
use function Luminova\Funcs\{root, make_dir};
use Luminova\Exceptions\{RuntimeException, CacheException, InvalidArgumentException};

final class FileCache extends Cache
{
    /**
     * Absolute path to the filecache cache root directory.
     *
     * @var string|null $root
     */
    private static ?string $root = null;

    /**
     * Singleton instance returned by `getInstance()`.
     *
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Active file stream instances keyed by storage name.
     *
     * @var array<string,Stream> $instances
     */
    private static array $instances = [];

    /**
     * Ordered list of cache keys queued for deferred iteration.
     *
     * @var string[] $iterator
     */
    private array $iterator = [];

    /**
     * Current cursor position within `$iterator`.
     *
     * @var int $position
     */
    private int $position = 0;

    /**
     * Per-key tamper-detection cache.
     *
     * Maps fully qualified cache keys to their last validated hash status.
     *
     * @var array<string,bool> $hashes
     */
    private array $hashes = [];

    /**
     * Initialize the filecache cache driver.
     *
     * All cache files are stored under `/writeable/caches/filecache/`.
     * An optional sub-folder may be provided to group related storages.
     * If `$storage` is omitted, call `setStorage()` before any cache operation.
     *
     * @param string|null $storage   Cache storage name (default: null).
     * @param string|null $persistentId Optional persistent Id as a relative sub-directory within the cache root (default: null).
     *
     * @throws CacheException If the cache root cannot be prepared.
     */
    public function __construct(?string $storage = null, ?string $persistentId = null)
    {
        parent::__construct();
        
        self::$root ??= root('/writeable/caches/filecache/');
        $persistentId = $persistentId 
            ?? env('filecache.persistent.id')
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
     * @param string|null $storage Cache storage name (default: null).
     * @param string|null $persistentId Optional persistent ID use as  sub-directory within the cache root (default: null).
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
        if (self::$instance === null) {
            self::$instance = new self($storage, $persistentId);
        }

        return self::$instance;
    }

    /**
     * {@inheritdoc}
     */
    public function getConn(): ?Stream
    {
        return self::$instances[$this->storage] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath(): ?string 
    {
        return $this->getRoot() . $this->storage . '.json';
    }

    /**
     * {@inheritdoc}
     */
    public function getRoot(): ?string 
    {
        if(!$this->persistentId){
            return self::$root;
        }

        return self::$root . $this->toFolderName($this->persistentId);
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
        $this->reset();

        if ($keys === [] || !$this->isConn()) {
            return false;
        }

        $this->assertStorageAndKey($keys);

        $keys = $this->toKeys($keys);

        if ($onItem === null) {
            $exists = $this->exists($keys);

            if ($exists <= 0) {
                return false;
            }

            $this->iterator = $keys;
            $this->isResult = true;
            return true;
        }

        $this->load(gc: false);
        $ratePerSecond = 100;
        $start = microtime(true);
        $results = 0;

        foreach ($keys as $i => $key) {
            try {
                $value = $this->items[$key] ?? null;

                if ($value === null) {
                    continue;
                }

                $this->onItem([
                    'key'   => $key,
                    'value' => $value,
                ], $onItem);

            } catch (Throwable) {
                continue;
            }

            $results++;

            $expected = ($i + 1) / $ratePerSecond;
            $remaining = $expected - (microtime(true) - $start);

            uwait($remaining, 3);
        }

        $this->isResult = $results > 0;
        return $this->isResult;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNext(): ?array
    {
        if (!$this->isResult || !$this->isConn()) {
            return null;
        }

        if (!isset($this->iterator[$this->position])) {
            $this->reset();
            return null;
        }

        $key = $this->iterator[$this->position];
        $this->position++;

        $result = $this->conn->get($key);

        if ($result === false || $result === null) {
            return null;
        }

        return $this->onItem([
            'key'   => $key,
            'value' => $result,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function fetchResult(): array
    {
        if ($this->iterator === [] || !$this->isResult || !$this->isConn()) {
            $this->reset();
            return [];
        }

        if ($this->items === []) {
            $this->reset();
            return [];
        }

        $results = [];

        foreach ($this->iterator as $i => $key) {
            $value = $this->items[$i] ?? null;

            if ($value === null) {
                continue;
            }

            $results[] = $this->onItem([
                'key'   => $key,
                'value' => $value,
            ]);
        }

        $this->reset();

        return $results;
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
     * 
     * > **Note:**
     * > This method requires the cache storage to be loaded. If the storage
     * > is not available or empty, the method will return 0.
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
    public function getItem(string $key, bool $withMetadata = false): mixed
    {
        $this->assertStorageAndKey($key);
        $key = $this->toKey($key);

        $this->reload($key, gc: false);

        $item = $this->items[$key] ?? [];
        $isDecoded = (bool) ($item[self::DECODED] ?? false);

        if(!$item || $this->hasExpired($key) || !$this->isValidSignature($key, $item)){
            return $withMetadata 
                ? $this->createEmptyCacheItem($item) 
                : null;
        }

        if(!$isDecoded){
            try{
                $this->items[$key][self::DATA] = $this->decode(
                    $item[self::DATA],
                    (int)  ($item[self::SERIALIZER] ?? self::SERIALIZER_NONE),
                    (bool) ($item[self::IGBINARY] ?? false),
                    (bool) ($item[self::BASE64] ?? true)
                );

                $this->items[$key][self::DECODED] = true;
            } catch(Throwable $e){
                $this->deleteItem($key, true);
                $this->errorHandler($e, 'getItem');

                return $withMetadata ? $this->createEmptyCacheItem([]) : null;
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
        $this->assertStorageAndKey($keys);
        $this->reload(gc: false);

        $keys = $this->toKeys($keys);
        $items = [];

        foreach($this->items as $key => $item){
            $key = $this->toKey($key);

            if(!in_array($key, $keys, true)){
                continue;
            }

            if(!$this->isValidSignature($key, $item)){
                continue;
            }

            $items[$key] = $item;
        }

        if($items === []){
            return [];
        }

        $deletions = [];

        $items = $this->decodes(
            $items,
            static function (string $key) use (&$deletions): void {
                $deletions[] = $key;
            },
            $withMetadata
        );

        if($deletions !== []){
            try{
                $this->deleteItems($deletions, $this->garbageCollectExpiredLocks);
            } catch(Throwable){}
        }

        return $items;
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
        $key = $this->toKey($key);

        $result = $this->write(
            $key,
            $content,
            $expiration,
            $expireAfter,
            $lock,
            true
        );

        $this->hashes[$key] = $result;
        return $result;
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
        $added = 0;
        $this->load(gc: false);
        $tmp = [];

        foreach($items as $key => $item){
            if(!$key || !$item){
                continue;
            }

            try{
                $this->assertStorageAndKey($key);

                $payload = $this->createRawPayload(
                    $this->encode($item),
                    $expireAfter ?? $expiration,
                    $lock
                );

                $key = $this->toKey($key);
                $this->items[$key] = $payload;
                $this->hashes[$key] = true;

                $tmp[$key] = $payload;
                $tmp[$key][self::DATA] = $item;
                $tmp[$key][self::DECODED] = false;

                $added++;
            } catch(Throwable){
                continue;
            }
        }

        if($added > 0){
            $this->commit($committed);
        }

        $this->items = array_merge($this->items, $tmp);

        return $committed;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool 
    {
        if (!$key || !$this->storage || !$this->isConn()) {
            return false;
        }

        $key = $this->toKey($key);

        try{
            if (!$this->reload($key, gc: false)) {
                return false;
            }
        }catch(Throwable){
            return false;
        }

        return isset($this->items[$key]);
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
        // $this->load(gc: false);

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
        // $this->load(gc: false);
        $key = $this->toKey($key);

        if (!$this->hasItem($key)) {
            return true;
        }

        if(!$gcExpiredLocks && $this->isLocked($key)){
            return false;
        }

        try{
            if($this->commit()){
                unset($this->items[$key], $this->hashes[$key]);
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
        if(!$keys || !$this->storage){
            return false;
        }

        $deletedCount = 0;
        $items = [];

        $this->load(gc: false);

        foreach ($keys as $key) {
            if($key === ''){
                continue;
            }

            $key = $this->toKey($key);

            if (!$this->hasItem($key)) {
                continue;
            }

            if(!$gcExpiredLocks && $this->isLocked($key)){
                continue;
            }

            $items[$key] = $this->items[$key] ?? [];

            $this->items[$key] = [];
            unset($this->items[$key], $this->hashes[$key]);

            $deletedCount++;
        }

        try{
            if ($deletedCount > 0 && $this->commit()){
                $items = null;
                return true;
            }
        } catch (Throwable $e) {
            $this->errorHandler($e, 'deleteItems');
        }

        $this->items = array_replace(
            $this->items,
            $items
        );

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): bool
    {
        try{
            if(Filesystem::delete($this->getRoot())){
                $this->items = [];
                $this->hashes = [];
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
        if(!$this->storage || !$this->isConn()){
            return false;
        }

		$path = $this->getPath();

        if(!is_file($path)){
            return false;
        }

        if(!unlink($path)){
            return false;
        }

        $items = $this->items;
        $this->clearPreloadItems(
            onRemove: function(string $key): void {
                unset($this->hashes[$key]);
            }
        );

        try{
            if(!$this->commit()){
                $this->items = $items;
                return false;
            }

            $this->hashes = [];
            return true;
        } catch (Throwable $e) {
            $this->errorHandler($e, 'clear');
        } finally {
            $items = null;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(array $keys, ?string $storage = null): bool
    {
        if($keys === []){
            return false;
        }

        $isCurrentStorage = true;
        $stream = null;
        $filepath =  null;

        if($storage === null){
            if(!$this->isConn()){
                return false;
            }

            $stream = $this->conn;

            if(!$stream->isOpen() && !$stream->open()){
                return false;
            }

            $filepath = $this->getPath();
        }else{
            if(!$storage){
                return false;
            }

            $isCurrentStorage = false;
            $storage = self::hashStorage($storage);
            $filepath = $this->getRoot() . "{$storage}.json";

            if (!is_file($filepath) || !is_readable($filepath) || is_writable($filepath)) {
                return false;
            }

            $stream = new Stream($filepath, 'c+b');
        }

        try{
            $tmp = [];
            $hashes = [];
            $items = $stream->toArray();

            if($isCurrentStorage){
                $items = array_merge($this->items, $items);
            }

            $deleted = 0;

            foreach($keys as $key){
                $key = $this->toKey($key);

                if(isset($items[$key])){
                    $tmp[$key] = $items[$key];
                    $hashes[$key] = $this->hashes[$key] ?? null;

                    unset($items[$key], $this->hashes[$key]);
                    $deleted++;
                }
            }

            if($deleted > 0){
                $ok = ($items === []) 
                    ? unlink($filepath) 
                    : $this->sink($items, $stream);

                if($ok){
                    return true;
                }

                if($isCurrentStorage){
                    $this->items = array_merge($this->items, $tmp);
                    $this->hashes = array_replace(
                        $this->hashes,
                        $hashes
                    );
                }
            }
        } catch(Throwable $e){
            $this->errorHandler($e, 'delete');
        } finally {
            if(!$isCurrentStorage){
                $stream->close();
                $stream = null;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteIfExpired(): int 
    {
        if(!$this->storage || !$this->isConn()){
            return 0;
        }

        $this->load(gc: false);
        $items = [];
        $hashes = [];
        $counter = 0;

        foreach ($this->items as $key => $value) {
            try{
                if (!$this->hasExpired($key)) {
                    continue;
                }
            } catch (Throwable) {
                continue;
            }

            if (!$this->garbageCollectExpiredLocks && (bool) ($value[self::LOCK] ?? false) === true) {
                continue;
            }

            $items[$key] = $value;
            $hashes[$key] = $this->hashes[$key] ?? null;

            unset($this->items[$key], $this->hashes[$key]);
            $counter++;
        }

        try{
            if ($counter > 0 && $this->commit()){
                return $counter;
            }
        } catch (Throwable) {}

        $this->items = array_replace(
            $this->items,
            $items
        );

        $this->hashes = array_replace(
            $this->hashes,
            $hashes
        );

        return 0;
    }

    /**
     * {@inheritdoc}
     * 
     * Open a new file Stream instance.
     */
    public function connect(): bool
    {
        $this->conn = $this->getConn();

        if ($this->conn instanceof Stream) {
            return true;
        }

        $filepath = $this->getPath();

        if (is_file($filepath) && !is_readable($filepath)) {
            throw new CacheException(sprintf('Cache file is not readable: %s', $filepath));
        }

        try {
            $this->conn = new Stream($filepath, 'c+b', autoOpen: false);

            self::$instances[$this->storage] = $this->conn;
            $this->isConnected = true;

            if($this->serializer === self::SERIALIZER_NONE){
                $this->serializer = self::SERIALIZER_PHP_AUTO;
            }

            return true;
        } catch (Throwable $e) {
            $this->errorHandler($e, 'connect');
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): bool
    {
        if ($this->isConn()) {
            try {
                $this->conn->close();
            } 
            catch (Throwable) {}
            finally {
                $this->clearPreloadItems(
                    onRemove: function(string $key): void {
                        unset($this->hashes[$key]);
                    }
                );
            }
        }

        unset(self::$instances[$this->storage]);
        $this->conn = null;
        $this->isConnected = false;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function read(?string $key = null): bool 
    {
        if(!$this->storage){
            return false;
        }
        
        if($this->items !== [] && $this->isConnected){
            return true;
        }

        $filepath = $this->getPath();

        if (!$this->isConn() || !is_file($filepath)) {
            return false;
        }

        try{
            if(!$this->conn->isOpen() && !$this->conn->open()){
                return false;
            }

            $this->items = array_merge(
                $this->items, 
                $this->conn->toArray()
            );

            return true;
        }catch(Throwable $e){
            unlink($filepath);

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function commit(int &$commits = 0): bool 
    {
        $commits = 0;

        if(!$this->isConn()){
            return false;
        }

        try{
            if(!make_dir($this->getRoot())){
                return false;
            }

            if($this->items === []){
                return unlink($this->getPath());
            }

            if($this->sink($this->items)){
                $commits = count($this->items);
                return true;
            }
        }catch(Throwable $e){
            $this->errorHandler(
                new CacheException(sprintf('Unable to commit item: %s', $e->getMessage()), $e->getCode(), $e), 
                'commit'
            );
        }

        return false;
    }

    /**
     * Persist cache data to the underlying stream.
     *
     * This method acquires an exclusive lock, overwrites the stream content
     * with the given items encoded as JSON, and then releases the lock.
     * The write is atomic at the stream level (lock + overwrite).
     *
     * @param array $items The cache data to store.
     * @param Stream|null $stream Optional target stream. Defaults to the internal stream.
     *
     * @return bool True if data was written successfully, false otherwise.
     *
     * @throws RuntimeException If encoding or write operation fails.
     */
    private function sink(array $items, ?Stream $stream = null): bool
    {
        $stream ??= $this->conn;

        if(!$stream->isOpen() && !$stream->open()){
            return false;
        }

        try {
            $stream->lock(LOCK_EX);
            $payload = [];

            foreach($items as $key => $item){
                if(!$key){
                    continue;
                }

                $hash = $item[self::HASH] ?? null;

                if($hash !== $this->storage){
                    continue;
                }

                if(($item[self::DECODED] ?? false) === true){
                    $item[self::DECODED] = false;

                    $raw =  $this->encode($item[self::DATA]);

                    if($raw === false){
                        continue;
                    }

                    $item[self::DATA] = $raw;
                }

                $item[self::F_HASH] = hash('sha256', serialize($item));
                $payload[$this->toKey($key)] = $item;
            }

            $payload = $this->toJsonString($payload);

            if($payload === null){
                return false;
            }

            $bytes = $stream->overwrite($payload);
        } finally {
            $stream->unlock();
        }

        return $bytes > 0;
    }

    /**
     * Scan key pattern and return number of match.
     *
     * @param string $pattern
     * @param callable $onEachKey
     * @param int $chunkSize
     * @param float|int $delay
     * @param bool $forUserKey
     * 
     * @return integer
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

        if(!$this->reload(gc: false) || $this->items === []){
            return 0;
        }

        //$keys = array_flip(array_keys($this->items));
        $found = 0;
        $keys = array_keys($this->items);
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

        return $found;
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
     * Assert and normalize folder name.
     *
     * @param string $name
     * 
     * @return string
     */
    private function toFolderName(string $name): string
    {
        if ($name === '') {
            return '';
        }

        if (str_starts_with($name, self::$root)) {
            $relative = substr($name, strlen(self::$root));

            throw new InvalidArgumentException(sprintf(
                'File Persistent ID must be relative to cache directory (%s). 
                Use "%s" instead of an absolute cache path.',
                self::$root,
                ($relative !== '') ? $relative : '/'
            ));
        }

        $name = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_-');

        if($name === ''){
            throw new InvalidArgumentException(
                'Invalid file cache $persistentId. Provide a valid directory name.'
            );
        }

        return rtrim($name, TRIM_DS) . DIRECTORY_SEPARATOR;
    }

    /**
     * Verify Tamper protection.
     *
     * @param string $key
     * @param array $item
     * 
     * @return bool
     */
    private function isValidSignature(string $key, array $item): bool
    {
        $isValid = $this->hashes[$key] ?? null;

        if ($isValid === null) {
            $hash = $item[self::F_HASH] ?? '';

            unset($item[self::F_HASH]);

            $item[self::DECODED] = false;

            $isValid = hash_equals(
                $hash,
                hash('sha256', serialize($item))
            );

            $this->hashes[$key] = $isValid;
        }

        if ($isValid) {
            return true;
        }

        $this->deleteItem($key, true);

        return false;
    }
}