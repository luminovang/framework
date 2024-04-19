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

class SessionManager implements SessionInterface 
{
    /**
     * @var string $storage Session storage name 
    */
    protected string $storage;

    /**
     * @var ?string $config Session configuration
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

        if (isset($_SESSION[$storage])) {
            return (array) $_SESSION[$storage];
        }

        return [];
    }
}