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

use \Luminova\Interface\SessionManagerInterface;
use \Luminova\Base\BaseConfig;
use \Luminova\Exceptions\JsonException;
use \Throwable;

final class CookieManager implements SessionManagerInterface 
{ 
    /**
     * @var string $storage Session storage name 
    */
    private string $storage = '';

    /**
     * @var BaseConfig $config
    */
    private ?BaseConfig $config = null;

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
                $this->saveContent('',  $storage, time() - $this->config->expiration);
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
        if($type === 'array'){
            return (array) $_COOKIE;
        }

        try {
            return (object) json_decode(json_encode($_COOKIE, JSON_THROW_ON_ERROR));
        }catch(Throwable $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        };
    }

    /** 
     * {@inheritdoc}
    */
    public function toAs(string $type = 'array', ?string $index = null): object|array|null
    {
        $result = $this->getItems();

        if($index !== null) {
            if(isset($result[$index])) {
                $result = $result[$index];
            }else{
                return null;
            }
        }
    
        if($type === 'array'){
            return (array) $result;
        }

        try {
            $result = json_encode($result, JSON_THROW_ON_ERROR);

            return (object) json_decode($result);
        }catch(Throwable $e){
            throw new JsonException($e->getMessage(), $e->getCode(), $e);
        }
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
     * @return void
    */
    private function saveContent(string $value, string $storage, ?int $expiry = null): void
    {
        $expiry ??= time() + $this->config->expiration;
        setcookie($storage, $value, [
            'expires' => $expiry,
            'path' => $this->config->sessionPath,
            'domain' => $this->config->sessionDomain,
            'secure' => true,
            'httponly' => true,
            'samesite' => $this->config->sameSite 
        ]);
    }
}