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

use \Luminova\Sessions\SessionInterface;

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
    public function add(string $key, mixed $value): self
    {
        $_SESSION[$this->storage][$key] = $value;

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function set(string $key, mixed $value): self
    {
        $_SESSION[$this->storage][$key] = $value;

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function get(string $index, mixed $default = null): mixed
    {
        return $_SESSION[$this->storage][$index]??$default;
    }

    /** 
     * {@inheritdoc}
    */
    public function getFrom(string $index, string $storage): mixed
    {
        return $_SESSION[$storage][$index]??null;
    }

    /** 
     * {@inheritdoc}
    */
    public function setTo(string $index, mixed $data, string $storage): self
    {
        $_SESSION[$storage][$index] = $data;

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function online($storage = ''): bool
    {
        $data = $this->getContents($storage);

        return (isset($data["_online"]) && $data["_online"] === 'YES');
    }

    /** 
     * {@inheritdoc}
    */
    public function clear(string $storage = ''): self
    {
        $storageKey = $storage === '' ? $this->storage : $storage;
        unset($_SESSION[$storageKey]);

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function remove(string $index): self
    {
        unset($_SESSION[$this->storage][$index]);

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function has(string $key): bool
    {
        return isset($_SESSION[$this->storage][$key]);
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
    public function getResult(): array
    {
        return (array) $_SESSION ?? [];
    }

    /** 
     * {@inheritdoc}
    */
    public function toArray(string $index = ''): array
    {
        return $this->toAs('array', $index);
    }

    /** 
     * {@inheritdoc}
    */
    public function toObject(string $index = ''): object
    {
        return $this->toAs('object', $index);
    }

    /** 
     * {@inheritdoc}
    */
    public function toAs(string $type = 'array', string $index = ''): object|array
    {
        $result = [];

        if( $index === ''){
            if(isset($_SESSION[$this->storage])){
                $result = $_SESSION[$this->storage];
            }

            if(isset($_SESSION)){
                $result = $_SESSION;
            }
        }elseif(isset($_SESSION[$this->storage][$index])){
            $result = $_SESSION[$this->storage][$index];
        }

        if($type === 'array'){
            return (array) $result;
        }

        return (object) $result;
    }

    /** 
     * {@inheritdoc}
    */
    public function getContents(string $storage = ''): array
    {
        $storageKey = $storage === '' ? $this->storage : $storage;

        if (isset($_SESSION[$storageKey])) {
            return $_SESSION[$storageKey];
        }

        return [];
    }
}