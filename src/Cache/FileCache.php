<?php 
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
use \Luminova\Time\Time;
use \Luminova\Base\Cache;
use \Luminova\Logger\Logger;
use \Luminova\Storage\Stream;
use \Luminova\Storage\Filesystem;
use \Luminova\Exceptions\{LuminovaException, CacheException, InvalidArgumentException};
use function \Luminova\Funcs\{root, make_dir};

final class FileCache extends Cache
{
    /**
     * Hold the cache directory path.
     * 
     * @var string|null $root
     */
    private static ?string $root = null;

    /**
     * Hold the cache instance Singleton.
     * 
     * @var ?static $instance
     */
    private static ?self $instance = null;

    /**
     * File stream object
     *
     * @var Stream|null
     */
    private ?Stream $stream = null;

    /**
     * Initialize cache constructor, with optional storage name and subfolder.
     * 
     * @param string|null $storage The cache storage name (default: null).
     * @param string|null $subfolder Optional cache storage sub directory (default: null).
     * 
     * @throws CacheException if there is a problem loading the cache.
     * 
     * > **Note:** 
     * > All cache items are store in `/writeable/caches/filesystem/`, 
     * > this cannot be changed, you can optionally specify a subfolder within the cache directory 
     * > to store your cache items.
     * > Additionally if your didn't specify the storage name on initialization, 
     * > then you must call `setStorage` method later before accessing caches.
     */
    public function __construct(?string $storage = null, private ?string $subfolder = null)
    {
        parent::__construct();
        self::$root ??= root('/writeable/caches/filesystem/');
        
        if($this->subfolder){
            $this->setFolder($this->subfolder);
        }

        if($storage){
            $this->setStorage($storage);
        }
	}

    /**
     * Get a static singleton instance of cache class.
     * 
     * @param string|null $storage The cache storage name (default: null).
     * @param string|null $subfolder Optional cache storage sub directory (default: null).
     * 
     * @param static Return new static instance of file cache class.
     * @throws CacheException if there is a problem loading the cache.
     */
    public static function getInstance(
        ?string $storage = null, 
        ?string $subfolder = null
    ): static 
    {
        if (self::$instance === null) {
            self::$instance = new self($storage, $subfolder);
        }

        return self::$instance;
    }

    /**
     * Gets the cache full storage filename.
     * 
     * @return string Return the cache storage filename.
    */
    public function getPath(): string 
    {
        return $this->getRoot() . $this->storage . '.json';
    }

    /**
     * Gets the cache storage root directory.
     * 
     * @return string Return the cache storage directory.
    */
    public function getRoot(): string 
    {
        if(!$this->subfolder){
            return self::$root;
        }

        return self::$root . $this->subfolder;
    }

    /**
     * Gets the cache storage sub folder.
     * 
     * @return string Return the cache storage sub folder.
     */
    public function getFolder(): ?string 
    {
        return $this->subfolder;
    }

    /**
     * {@inheritdoc}
     */
    public function setFolder(string $subfolder): self 
    {
        if (str_starts_with($subfolder, self::$root)) {
            $folder = substr($subfolder, strlen(self::$root));
            throw new InvalidArgumentException(sprintf(
                "Invalid subfolder path: The path should be relative to the cache directory (%s) and must not start with it. Consider using '%s' as the subfolder path instead.", 
                self::$root,
                $folder
            ));
        }

        $this->subfolder = trim($subfolder, TRIM_DS) . DIRECTORY_SEPARATOR;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setStorage(string $storage): self
    {
        $this->storage = self::hashStorage($storage);
        $this->storageName = $storage;
        $this->items[$this->storage] = [];

        return $this;
    }

    /**
     * Set enable or disable if the cache content should be encoded in base64.
     * 
     * @param bool $encode Enable or disable base64 encoding.
     * 
     * @return self Return instance of file cache class.
     */
    public function enableBase64(bool $encode): self 
    {
        $this->encoding = $encode;
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

        foreach ($keys as $key) {
            $item = $this->getItem($key);

            if($item === null){
                continue;
            }

            $result = [
                'key' => $key,
                'value' => $item
            ];

            if($callback !== null) {
                $callback($this, $result);
            }

            $this->iterator[] = $result;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(string $key, bool $onlyContent = true): mixed
    {
        $this->assertStorageAndKey($key);
        $this->read();

        if ($this->hasExpired($key)){
            return $this->respondWithEmpty($onlyContent); 
        }

        $content = $this->items[$this->storage][$key] ?? [];

        if(!$content){
            return null;
        }

        if(!$content['decoded']){
            $this->items[$this->storage][$key]['data'] = $this->deSerialize(
                $content['data'],
                $content['serializer'] ?? 0,
                $content['encoding']  ?? true
            );

            $this->items[$this->storage][$key]['decoded'] = true;
        }

        // Auto delete expired caches, so next time we get fresh one.
        $this->deleteIfExpired();

        return $onlyContent 
            ? ($this->items[$this->storage][$key]['data'] ?? null)
            : ($this->items[$this->storage][$key] ?? null);
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

        if($expiration !== null){
            $expireAfter = null;
        }

        $this->read();
        $this->items[$this->storage][$key] = [
            "timestamp" => Time::now($this->timezone)->getTimestamp(),
            "expiration" => ($expiration instanceof DateTimeInterface) 
                ? Time::toSeconds($expiration) 
                : $expiration,
            "expireAfter" => ($expireAfter instanceof DateInterval) 
                ? Time::toSeconds($expireAfter) 
                : $expireAfter,
            "data" => $content,
            "lock" => $lock,
            "decoded" => false,
            "encoding" => $this->encoding,
            "serializer" => $this->serializer
        ];

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool 
    {
        if (!$this->storage || !$key) {
            return false;
        }

        try{
            if (!$this->read()) {
                return false;
            }
        }catch(Throwable){
            return false;
        }

        return isset($this->items[$this->storage][$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function isLocked(string $key): bool 
    {
        if (!$this->read()) {
            return true;
        }

        return isset($this->items[$this->storage][$key]['lock']);
    }

    /**
     * {@inheritdoc}
     */
    public function hasExpired(string $key): bool 
    {
        $this->read();

        if (!$this->hasItem($key)) {
            return true;
        }

        return $this->isExpired($this->items[$this->storage][$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(string $key, bool $includeLocked = false): bool 
    {
        if(!$key){
            return false;
        }

        $this->read();
        if (!$this->hasItem($key) || (!$includeLocked && $this->isLocked($key))) {
            return false;
        }

        unset($this->items[$this->storage][$key]);
        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(iterable $keys, bool $includeLocked = false): bool 
    {
        if(!$this->storage){
            return false;
        }

        $deletedCount = 0;
        $this->read();

        foreach ($keys as $key) {
            if(!$key){
                continue;
            }

            if (!$this->hasItem($key) || (!$includeLocked && $this->isLocked($key))) {
                continue;
            }

            $this->items[$this->storage][$key] = [];

            unset($this->items[$this->storage][$key]);
            $deletedCount++;
        }

        if ($deletedCount > 0){
            return $this->commit();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function flush(): bool
    {
        if(Filesystem::delete($this->getRoot())){
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
        if(!$this->storage){
            return false;
        }

		$path = $this->getPath();

        if(!is_file($path)){
            return false;
        }
        
        $this->items[$this->storage] = [];

		if(!unlink($path)){
            return $this->commit();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $storage, array $keys): bool
    {
        if($storage === '' || $keys === []){
            return false;
        }

        $filepath = $this->getRoot() . self::hashStorage($storage) . '.json';

        if (!is_file($filepath) || !is_readable($filepath) || is_writable($filepath)) {
            return false;
        }

        try{
            $stream = new Stream($filepath, 'c+b');
            $items = $stream->toArray();
            $deleted = 0;

            foreach($keys as $key){
                if(isset($items[$key])){
                    unset($items[$key]);
                    $deleted++;
                }
            }

            if($deleted > 0){
                if($items === []){
                    return unlink($filepath);
                }

                return $this->sink($items, $stream);
            }
        }catch(Throwable $e){
            if($e instanceof LuminovaException){
                throw $e;
            }

            throw new CacheException($e->getMessage());
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteIfExpired(): void 
    {
        if(!$this->autoDeleteExpired || !$this->storage){
            return;
        }

        $this->read();
        $counter = 0;

        foreach ($this->items[$this->storage] as $key => $value) {
            if ($this->hasExpired($key) && ($this->includeLocked || !$value['lock'])) {
                $this->items[$this->storage][$key] = [];

                unset($this->items[$this->storage][$key]);
                $counter++;
            }
        }

        if ($counter > 0){
            $this->commit();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function read(?string $key = null): bool 
    {
        if(!$this->storage){
            return false;
        }

        if($this->items[$this->storage] !== []){
            return true;
        }

        $filepath = $this->getPath();

        if (!is_file($filepath) || !is_readable($filepath)) {
            return false;
        }

        try{
            $this->stream = new Stream($filepath, 'c+b');
            $this->items[$this->storage] = $this->stream->toArray();

            return true;
        }catch(Throwable $e){
            unlink($filepath);

            if(PRODUCTION){
                Logger::dispatch(
                    'error',
                    sprintf('Failed to read cache content: %s', $e->getMessage()),
                    [
                        'class' => self::class
                    ]
                );

                return false;
            }

            if($e instanceof LuminovaException){
                throw $e;
            }

            throw new CacheException(sprintf('Failed to read cache content: %s', $e->getMessage()));
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function commit(): bool 
     {
        try{
            if(!make_dir($this->getRoot())){
                return false;
            }

            $filepath = $this->getPath();

            if($this->items[$this->storage] === []){
                return unlink($filepath);
            }

            $this->stream ??= new Stream($filepath);
            return $this->sink($this->items[$this->storage]);
        }catch(Throwable $e){
            if(PRODUCTION){
                Logger::dispatch('error', sprintf('Unable to commit cache: %s', $e->getMessage()), [
                    'class' => self::class
                ]);

                return false;
            }

            if($e instanceof LuminovaException){
                throw $e;
            }

            throw new CacheException(sprintf('Unable to commit cache: %s', $e->getMessage()));
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
        $stream ??= $this->stream;

        try {
            $stream->lock(LOCK_EX);

            $payload = json_encode(
                $items,
                JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
                | JSON_PRESERVE_ZERO_FRACTION
                | JSON_UNESCAPED_SLASHES
            );

            $bytes = $stream->overwrite($payload);
        } finally {
            $stream->unlock();
        }

        return $bytes > 0;
    }
}