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

use \Luminova\Base\BaseCache;
use \Luminova\Storages\FileManager;
use \Luminova\Logger\Logger;
use \Luminova\Time\Timestamp;
use \Luminova\Exceptions\AppException;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Exceptions\CacheException;
use \DateTimeInterface;
use \DateInterval;
use \Exception;
use \JsonException;

final class FileCache extends BaseCache
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
     * @var ?self $instance
     */
    private static ?self $instance = null;

    /**
     * Initialize cache constructor, with optional storage name and subfolder.
     * 
     * @param string|null $storage The cache storage name (default: null).
     * @param string|null $subfolder Optional cache storage sub directory (default: null).
     * 
     * @throws CacheException if there is a problem loading the cache.
     * 
     * > **Note:** All cache items are store in `/writeable/caches/filesystem/`, this cannot be changed, 
     * you can optionally specify a subfolder within the cache directory to store your cache items.
     * > Additionally if your didn't specify the storage name on initialization, then you must call `setStorage` method later before accessing caches.
     */
    public function __construct(
        ?string $storage = null, 
        private ?string $subfolder = null
    )
    {
        parent::__construct();
        $this->encoding = ($this->serialize === 2) ? false : true;
        self::$root ??= root('/writeable/caches/filesystem/');
        
        if($this->subfolder){
            $this->setFolder($this->subfolder);
        }

        // Set the storage and initialize the storage.
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
        if (static::$instance === null) {
            static::$instance = new static($storage, $subfolder);
        }

        return static::$instance;
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
        $this->storage = static::hashStorage($storage);
        $this->storageName = $storage;
        $this->items[$this->storage] = [];
        $this->read();
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
            if(($item = $this->getItem($key)) !== null){
                $result = [
                    'key' => $key,
                    'value' => $item
                ];

                if($callback !== null) {
                    $callback($this, $result);
                }

                $this->iterator[] = $result;
            }
        }

        return true;
    }

   /**
     * {@inheritdoc}
    */
    public function getItem(string $key, bool $onlyContent = true): mixed
    {
        $this->assertStorageAndKey($key);
        if ($this->hasExpired($key)){
            return $this->respondWithEmpty($onlyContent); 
        }

        if(!$this->items[$this->storage][$key]['decoded']){
            $this->items[$this->storage][$key]['data'] = $this->deSerialize(
                ($this->items[$this->storage][$key]['encoding'] === 'base64') ? 
                    base64_decode($this->items[$this->storage][$key]['data']) : 
                    $this->items[$this->storage][$key]['data'],
                $this->items[$this->storage][$key]['serialize']
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

        $this->items[$this->storage][$key] = [
            "timestamp" => time(),
            "expiration" => ($expiration instanceof DateTimeInterface) ? Timestamp::ttlToSeconds($expiration) : $expiration,
            "expireAfter" => ($expireAfter instanceof DateInterval) ? Timestamp::ttlToSeconds($expireAfter) : $expireAfter,
            "data" => ($this->encoding ? base64_encode($content) : $content),
            "lock" => $lock,
            "decoded" => false,
            "encoding" => $this->encoding ? 'base64' : 'raw',
            "serialize" => $this->serialize
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
        }catch(Exception|AppException){
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

        if ($this->hasItem($key) && ($includeLocked || !$this->isLocked($key))) {
            unset($this->items[$this->storage][$key]);
            return $this->commit();
        }
      
        return false;
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
        foreach ($keys as $key) {
            if ($key !== '' && $this->hasItem($key) && ($includeLocked || !$this->isLocked($key))) {
                unset($this->items[$this->storage][$key]);
                $deletedCount++;
            }
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
        if(FileManager::remove($this->getRoot())){
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

		if(file_exists($path) && unlink($path)){
            $this->items[$this->storage] = [];
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
    */
    public function delete(string $storage, array $keys): bool
    {
        if($storage === '' || $keys === []){
            return false;
        }

        $filepath = $this->getRoot() . static::hashStorage($storage) . '.json';

        try{
            if (!is_readable($filepath)) {
                return false;
            }

            $content = FileManager::getContent($filepath);
            if($content === false){
                return false;
            }

            $items = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
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

                if(is_writable($filepath)){
                    return FileManager::write(
                        $filepath, 
                        json_encode($items, JSON_THROW_ON_ERROR), 
                        LOCK_EX
                    );
                }
            }
        }catch(Exception|AppException|JsonException $e){
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

        $counter = 0;
        foreach ($this->items[$this->storage] as $key => $value) {
            if ($this->hasExpired($key) && ($this->includeLocked || !$value['lock'])) {
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
        try{
            if (!is_readable($filepath)) {
                return false;
            }

            $content = FileManager::getContent($filepath);

            if($content === false){
                return false;
            }

            $this->items[$this->storage] = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return true;

        }catch(Exception|AppException|JsonException $e){
            unlink($filepath);

            if(PRODUCTION){
                Logger::dispatch('error',sprintf('Failed to read cache content: %s', $e->getMessage()), [
                    'class' => self::class
                ]);

                return false;
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

            if($this->items[$this->storage] === []){
                return unlink($this->getPath());
            }

            return FileManager::write(
                $this->getPath(), 
                json_encode($this->items[$this->storage], JSON_THROW_ON_ERROR), 
                LOCK_EX
            );
        }catch(Exception|AppException|JsonException $e){
            if(PRODUCTION){
                Logger::dispatch('error', sprintf('Unable to commit cache: %s', $e->getMessage()), [
                    'class' => self::class
                ]);

                return false;
            }

            throw new CacheException(sprintf('Unable to commit cache: %s', $e->getMessage()));
        }

        return false;
    }
}