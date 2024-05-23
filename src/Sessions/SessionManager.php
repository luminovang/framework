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

use \Luminova\Base\BaseConfig;
use \Luminova\Interface\SessionManagerInterface;
use \Luminova\Exceptions\JsonException;
use \Throwable;

final class SessionManager implements SessionManagerInterface 
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
    public function setItem(string $index, mixed $data, string $storage = ''): self
    {
        $storage = ($storage === '') ? $this->storage : $storage;

        $_SESSION[$storage][$index] = $data;

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function deleteItem(?string $index = null, string $storage = ''): self
    {
        $storage = ($storage === '') ? $this->storage : $storage;

        if($storage !== '' && isset($_SESSION[$storage])){
            if($index === '' || $index === null){
                unset($_SESSION[$storage]);
            }else{
                unset($_SESSION[$storage][$index]);
            }
        }

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function hasItem(string $key): bool
    {
        if(isset($_SESSION[$this->storage])){
            return isset($_SESSION[$this->storage][$key]);
        }

        return false;
    }

    /** 
     * {@inheritdoc}
    */
    public function hasStorage(string $storage): bool
    {
        return isset($_SESSION[$storage]);
    }

    /** 
     * {@inheritdoc}
    */
    public function getResult(string $type = 'array'): array|object
    {
        $result = [];
        
        if (isset($_SESSION)) {
            $result = $_SESSION;
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

        if (isset($_SESSION[$storage])) {
            return (array) $_SESSION[$storage];
        }

        return [];
    }
}