<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Sessions;

use \Luminova\Interface\SessionInterface;
use \App\Controllers\Config\Session as CookieConfig;

class CookieManager implements SessionInterface 
{ 
    /**
     * @var string $storage Session storage name 
    */
    protected string $storage;

    /**
     * @var ?string $config Session cookie configuration
    */
    private static ?string $config = null;

    /**
     * {@inheritdoc}
    */
    public function __construct(string $storage = 'global') 
    {
        $this->storage = $storage;
    }

    /** 
     * {@inheritdoc}
    */
    public function setConfig(string $config): void 
    {
        static::$config = $config;
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
    public function getStorage(): string 
    {
        return $this->storage;
    }

    /** 
     * {@inheritdoc}
    */
    public function setItem(string $index, mixed $value, string $storage = ''): self
    {
        $storage = ($storage === '') ? $this->storage : $storage;
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
            return $default;
        }

        return $result[$index] ?? $default;
    }

    /**
     * {@inheritdoc}
    */
    public function deleteItem(?string $index = null, string $storage = ''): self
    {
        $storage = ($storage === '') ? $this->storage : $storage;

        if($storage !== '' && isset($_COOKIE[$storage])) {
            if($index === '' || $index === null){
                $this->saveContent('',  $storage, time() - static::$config::$expiration);
                $_COOKIE[$storage] = '';
            }else{
                $data = $this->getItems($storage);
                if (isset($data[$index])) {
                    unset($data[$index]);
                    $this->updateItems($data, $storage);
                }
            }
        }

        return $this;
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
        return isset($_COOKIE[$storage]);
    }

    /** 
     * {@inheritdoc}
    */
    public function getResult(string $type = 'array'): array|object
    {
        $result = [];

        if (isset($_COOKIE)) {
            $result = $_COOKIE;
        }

        if($type === 'array'){
            return (array) $result;
        }

        return (object) json_decode(json_encode($result));
    }

    /** 
     * {@inheritdoc}
    */
    public function toAs(string $type = 'array', string $index = ''): object|array
    {
        $result = $this->getItems();

        if($index !== '' && isset($result[$index])) {
            $result = $result[$index];
        }
    
        if($type === 'array'){
            return (array) $result;
        }

        return (object) json_decode(json_encode($result));
    }

    /** 
     * {@inheritdoc}
    */
    public function getItems(string $storage = ''): array
    {
        $storage = ($storage === '') ? $this->storage : $storage;

        if (isset($_COOKIE[$storage])) {
            if(is_string($_COOKIE[$storage])){
                return json_decode($_COOKIE[$storage], true) ?? [];
            }

            return (array) $_COOKIE[$storage];
        }

        return [];
    }

    /**
     * Update data to cookie storage.
     *
     * @param array $data contents
     * 
     * @return void 
    */
    private function updateItems(array $data, string $storage = ''): void
    {
        $storage = ($storage === '') ? $this->storage : $storage;
        $cookieValue = json_encode($data);

        $this->saveContent($cookieValue, $storage);
        $_COOKIE[$storage] =  $data;
    }

    /**
     * Save delete data from cookie storage.
     *
     * @param array $value contents
     * @param string $storage cookie storage context
     * @param ?int $expiry cookie expiration time
     * 
     * @return self $this
    */
    private function saveContent(string $value, string $storage, ?int $expiry = null): void
    {

        static::$config ??= CookieConfig::class;
        $expiration = $expiry === null ? time() + static::$config::$expiration : $expiry;

        setcookie($storage, $value, [
            'expires' => $expiration,
            'path' => static::$config::$sessionPath,
            'domain' => static::$config::$sessionDomain,
            'secure' => true,
            'httponly' => true,
            'samesite' => static::$config::$sameSite 
        ]);
    }
}