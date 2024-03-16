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
use \App\Controllers\Config\Session as CookieConfig;

class CookieManager implements SessionInterface 
{ 
    /**
     * @var string $storage
    */
    protected string $storage = '';

    /**
     * @var string $config CookieConfig
    */
    private ?string $config = null;

    /**
     * Session constructor.
     *
     * @param string $storage The session storage key.
    */
    public function __construct(string $storage = 'global') 
    {
        $this->storage = $storage;
    }

    /** 
     * Set cookie options 
     * 
     * @param string $config CookieConfig class name
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
    public function setStorage(string $storage): self 
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * Get storage key
     * 
     * @return string
    */
    public function getStorage(): string 
    {
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
        $this->setContents($key, $value);

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
        $this->setContents($key, $value);

        return $this;
    }

    /** 
     * get data from session
     * 
     * @param string $index key to get
     * @param mixed $default default value 
     * 
     * @return mixed
    */
    public function get(string $index, mixed $default = null): mixed
    {
        $data = $this->getContents();

        return $data[$index]??$default;
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
        $data = $this->getContents($storage);

        return $data[$index]??null;
    }

    /** 
     * Get data from specified storage instance
     * 
     * @param string $index value key to get
     * @param mixed $value data to set
     * @param string $storage Storage key name
     * 
     * @return self
    */
    public function setTo(string $index, mixed $value, string $storage): self
    {
        $data = $this->getContents($storage);
        $data[$index] = $value;
        $this->updateContents($data);

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

        if((isset($data["_online"]) && $data["_online"] === 'YES')){
            return true;
        }

        return false;
    }

    /**
     * Clear all data from a specific session storage by passing the storage key.
     *
     * @param string $storage Storage key to unset.
     * 
     * @return self
    */
    public function clear(string $storage = ''): self
    {
        $context = $storage === '' ? $this->storage : $storage;
        $this->saveContent('',  $context, time() - $this->config::$expiration);
        $_COOKIE[$context] = '';

        return $this;
    }

   /**
     * Remove key from the current session storage by passing the key.
     *
     * @param string $index Key index to unset.
     * 
     * @return self
    */
    public function remove(string $index): self
    {
        $data = $this->getContents();
        if (isset($_COOKIE[$this->storage], $data[$index])) {
            unset($data[$index]);
            $this->updateContents($data);
        }
        return $this;
    }

    /** 
     * Check if key exists in session
     * 
     * @param string $key
     * 
     * @return bool
    */
    public function has(string $key): bool
    {
        $data = $this->getContents();

        return isset($data[$key]);
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
        return isset($_COOKIE[$storage]);
    }

    /** 
     * Get all stored session as array
     * 
     * @return array
    */
    public function getResult(): array
    {
        if (isset($_COOKIE)) {
            return (array) $_COOKIE;
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
        return $this->toAs('array', $index);
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
        return $this->toAs('object', $index);
    }

    /** 
     * Get data as object or array from current session storage
     * 
     * @param string $type return type of object or array
     * @param string $index optional key to get
     * 
     * @return object|array
    */
    public function toAs(string $type = 'array', string $index = ''): object|array
    {
        $data = $this->getContents();
        $result = [];
        if($index === ''){
            if($data !== []){
                $result = $data;
            }
            if(isset($_COOKIE)){
                $result = $_COOKIE;
            }
        }elseif (isset($data[$index])) {
            $result = $data[$index];
        }

        if($type === 'array'){
            return (array) $result;
        }

        return (object) $result;
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
        $key = $storage === '' ? $this->storage : $storage;

        if (isset($_COOKIE[$key])) {
            if(is_string($_COOKIE[$key])){
                return json_decode($_COOKIE[$key], true) ?? [];
            }

            return $_COOKIE[$key] ?? [];
        }

        return [];
    }

    /**
     * Save data to cookie storage.
     *
     * @param string $key Key
     * @param mixed $value Value
     * 
     * @return void
    */
    private function setContents(string $key, mixed $value): void
    {
        $data = $this->getContents();
        $data[$key] = $value;

        $this->updateContents($data);
    }

    /**
     * Update data to cookie storage.
     *
     * @param array $data contents
     * 
     * @return void 
    */
    private function updateContents(array $data): void
    {
        $cookieValue = json_encode($data);

        $this->saveContent($cookieValue, $this->storage);
        $_COOKIE[$this->storage] =  $data;
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

        $this->config ??= CookieConfig::class;
        $expiration = $expiry === null ? time() + $this->config::$expiration : $expiry;

        setcookie($storage, $value, [
            'expires' => $expiration,
            'path' => $this->config::$sessionPath,
            'domain' => $this->config::$sessionDomain,
            'secure' => true,
            'httponly' => true,
            'samesite' => $this->config::$sameSite 
        ]);
    }

}