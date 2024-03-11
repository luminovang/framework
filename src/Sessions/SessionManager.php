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
     * @var string $storage
    */
    protected string $storage;

    /**
     * @var ?string $config
    */
    private ?string $config = null;

    /**
     * Session constructor.
     *
     * @param string $storage The session storage key.
     * @param array $config Session configuration
    */
    public function __construct(string $storage = 'global') 
    {
        $this->storage = $storage;
    }

    /** 
     * Set cookie options 
     * 
     * @param string $config SessionConfig class name
     * 
     * @return void
    */
    public function setConfig(string $config): void 
    {
        $this->config = $config;
    }

    /**
     * Set storage key
     *
     * @param string $storage The session storage key.
     * 
     * @return self
    */
    public function setStorage(string $storage): self {
        $this->storage = $storage;
        return $this;
    }

    /**
     * Get storage key
     * 
     * @return string
    */
    public function getStorage(): string {
        return $this->storage;
    }
  
    /**
     * Add a key-value pair to the session data.
     *
     * @param string $key The key.
     * @param mixed $value The value.
     * 
     * @return self
     */
    public function add(string $key, mixed $value): self
    {
        $_SESSION[$this->storage][$key] = $value;
        return $this;
    }

    /** 
     * Set key and value to session
     * 
     * @param string $key key to set
     * @param mixed $value value to set
     * 
     * @return self
    */
    public function set(string $key, mixed $value): self
    {
        $_SESSION[$this->storage][$key] = $value;
        return $this;
    }

    /** 
     * get data from session
     * 
     * @param string $index key to het
     * @param mixed $default default value 
     * 
     * @return mixed
    */
    public function get(string $index, mixed $default = null): mixed
    {
        return $_SESSION[$this->storage][$index]??$default;
    }

    /** 
     * Get data from specified storage instance
     * 
     * @param string $index value key to get
     * @param string $storage Storage key name
     * 
     * @return mixed
    */
    public function getFrom(string $index, string $storage): mixed
    {
        return $_SESSION[$storage][$index]??null;
    }


    /** 
     * Get data from specified storage instance
     * 
     * @param string $index value key to get
     * @param mixed $data data to set
     * @param string $storage Storage key name
     * 
     * @return self
    */
    public function setTo(string $index, mixed $data, string $storage): self
    {
        $_SESSION[$storage][$index] = $data;
        return $this;
    }

    /** 
     * Check if session user is online from any storage instance
     * 
     * @param string $storage Optional storage key 
     * 
     * @return bool
    */
    public function online($storage = ''): bool
    {
        $data = $this->getContents($storage);
        return (isset($data["_online"]) && $data["_online"] == "YES");
    }

    /** 
     * Clear all data from specific session storage by passing the storage key
     * 
     * @param string $storage storage key to unset
     * 
     * @return self
    */
    public function clear(string $storage = ''): self
    {
        $storageKey = $storage === '' ? $this->storage : $storage;
        unset($_SESSION[$storageKey]);
        return $this;
    }

    /** 
     * Remove key from current session storage by passing the key
     * 
     * @param string $index key index to unset
     * 
     * @return self
    */
    public function remove(string $index): self
    {
        unset($_SESSION[$this->storage][$index]);
        return $this;
    }

    /** 
     * Check if key exists in session storage
     * 
     * @param string $key
     * 
     * @return bool
    */
    public function has(string $key): bool
    {
        return isset($_SESSION[$this->storage][$key]);
    }

    /** 
     * Check if storage key exists in session
     * 
     * @param string $storage
     * 
     * @return bool
    */
    public function hasStorage(string $storage): bool
    {
        return isset($_SESSION[$storage]);
    }

    /** 
     * Get all stored session as array
     * 
     * @return array
    */
    public function getResult(): array
    {
        if (isset($_SESSION)) {
            return (array) $_SESSION;
        }
        return [];
    }

    /** 
     * Get data as array from current session storage 
     * 
     * @param string $index optional key to get
     * 
     * @return array
    */
    public function toArray(string $index = ''): array
    {
        if( $index === ''){
            if(isset($_SESSION[$this->storage])){
                return (array) $_SESSION[$this->storage];
            }

            if(isset($_SESSION)){
                return (array) $_SESSION;
            }
        }elseif(isset($_SESSION[$this->storage][$index])){
            return (array) $_SESSION[$this->storage][$index];
        }
        return [];
    }

    /** 
     * Get data as object from current session storage
     * 
     * @param string $index optional key to get
     * 
     * @return object
    */
    public function toObject(string $index = ''): object
    {
        if( $index === ''){
            if(isset($_SESSION[$this->storage])){
                return (object) $_SESSION[$this->storage];
            }

            if(isset($_SESSION)){
                return (object) $_SESSION;
            }
        }elseif(isset($_SESSION[$this->storage][$index])){
            return (object) $_SESSION[$this->storage][$index];
        }
        return (object)[];
    }

    /** 
     * Get data as array from storage 
     * 
     * @param string $storage optional storage key 
     * 
     * @return array
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