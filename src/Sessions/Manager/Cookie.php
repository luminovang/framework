<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Sessions\Manager;

use \Luminova\Interface\SessionManagerInterface;
use \Luminova\Base\BaseConfig;
use \Luminova\Exceptions\JsonException;

final class Cookie implements SessionManagerInterface 
{
    /**
     * Cookie config. 
     * 
     * @var BaseConfig $config
     */
    private ?BaseConfig $config = null;

    /**
     * The session storage index name.
     * 
     * @var string $table
     */
    private static string $table = 'default';

    /**
     * {@inheritdoc}
     */
    public function __construct(private string $storage = 'global') {}

    /**
     * {@inheritdoc}
     */
    public function setConfig(BaseConfig $config): void
    {
        $this->config = $config;
    }

    /**
     * {@inheritdoc}
     */
    public function setStorage(string $storage): self 
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setTable(string $table): self 
    {
        self::$table = $table;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getStorage(): string 
    {
        return $this->storage;
    }

    /** 
     * {@inheritdoc}
     */
    public function setItem(string $index, mixed $value, ?string $storage = null): self
    {
        $storage = $this->getKey($storage);
        $data = $this->getItems($storage);

        $data[$index] = $value;
        $this->updateItems($data, $storage);

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function getItem(string $index, mixed $default = null): mixed
    {
        $result = $this->getItems();

        if($result === []){
            $storage = $this->getKey();
            if(isset($_COOKIE[self::$table][$storage])){
                $result = $_COOKIE[self::$table][$storage];
            }else{
                return $default;
            }
        }

        return $result[$index] ?? $default;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem(?string $index = null, ?string $storage = null): self
    {
        $storage = $this->getKey($storage);

        if(isset($_COOKIE[self::$table][$storage])) {
            if($index){
                $data = $this->getItems($storage);
                $data = $data !== []?:$_COOKIE[self::$table][$storage]; 

                if (isset($data[$index])) {
                    unset($data[$index]);
                }

                $this->updateItems($data, $storage);
            }else{
                $this->updateItems([], $storage);
            }
        }

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function destroyItem(): bool
    {
        if(isset($_COOKIE[self::$table])) {
            $this->saveContent('', time() - $this->config->expiration);
            $_COOKIE[self::$table] = [];

            return true;
        }

        return false;
    }

    /** 
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        $result = $this->getItems();

        if($result === []){
            return false;
        }

        return isset($result[$key]);
    }

    /** 
     * {@inheritdoc}
     */
    public function hasStorage(string $storage): bool
    {
        return isset($_COOKIE[self::$table][$storage]);
    }

    /** 
     * {@inheritdoc}
     */
    public function getResult(string $type = 'array'): array|object
    {
        if($type === 'array'){
            return (array) $_COOKIE[self::$table] ?? [];
        }

        try {
            return (object) json_decode(json_encode($_COOKIE[self::$table]??[], JSON_THROW_ON_ERROR));
        }catch(\JsonException $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** 
     * {@inheritdoc}
     */
    public function toAs(string $type = 'array', ?string $index = null): object|array|null
    {
        $result = $this->getItems();

        if($index) {
            $result = $result[$index]??null;
        }

        if($result === null){
            return null;
        }
    
        if($type === 'array'){
            return (array) $result;
        }

        try {
            $result = json_encode($result, JSON_THROW_ON_ERROR);
            return (object) json_decode($result);
        }catch(\JsonException $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /** 
     * {@inheritdoc}
     */
    public function getItems(?string $storage = null): array
    {
        $storage = $this->getKey($storage);
        $contents = null;

        if(isset($_COOKIE[self::$table])) {
            if(isset($_COOKIE[self::$table][$storage])){
                $contents = $_COOKIE[self::$table][$storage];
            }else{
                $contents = $_COOKIE[self::$table];
            }
        }

        if($contents !== null){
            
            if(is_string($contents)){
                $contents = json_decode($contents, true) ?? [];
                return ($contents[$storage] ?? $contents);
            }

            return (array) ($contents[$storage] ?? $contents);
        }

        return [];
    }

    /**
     * Get storage name.
     * 
     * @param string $storage Optional storage name.
     * 
     * @return string Storage name.
     */
    private function getKey(?string $storage = null): string 
    {
        return $storage ?? $this->storage;
    }

    /**
     * Update data to cookie storage.
     *
     * @param array $data contents.
     * 
     * @return void 
     */
    private function updateItems(array $data, ?string $storage = null): void
    {
        $data[$this->getKey($storage)] = $data;
        $_COOKIE[self::$table] = $data;

        $this->saveContent(json_encode($data));
    }

    /**
     * Save delete data from cookie storage.
     *
     * @param string $value contents.
     * @param ?int $expiry cookie expiration time.
     * 
     * @return void
     */
    private function saveContent(string $value, ?int $expiry = null): void
    {
        $expiry ??= time() + $this->config->expiration;
        setcookie(self::$table, $value, [
            'expires' => $expiry,
            'path' => $this->config->sessionPath,
            'domain' => $this->config->sessionDomain,
            'secure' => true,
            'httponly' => true,
            'samesite' => $this->config->sameSite 
        ]);
    }
}