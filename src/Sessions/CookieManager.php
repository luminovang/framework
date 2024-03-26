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
    public function add(string $key, mixed $value): self
    {
        $this->setContents($key, $value);

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function set(string $key, mixed $value): self
    {
        $this->setContents($key, $value);

        return $this;
    }

    /** 
     * {@inheritdoc}
    */
    public function get(string $index, mixed $default = null): mixed
    {
        $data = $this->getContents();

        return $data[$index]??$default;
    }

    /** 
     * {@inheritdoc}
    */
    public function getFrom(string $index, string $storage): mixed
    {
        $data = $this->getContents($storage);

        return $data[$index]??null;
    }

    /** 
     * {@inheritdoc}
    */
    public function setTo(string $index, mixed $value, string $storage): self
    {
        $data = $this->getContents($storage);
        $data[$index] = $value;
        $this->updateContents($data);

        return $this;
    }

    /** 
     * {@inheritdoc}
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
     * {@inheritdoc}
    */
    public function clear(string $storage = ''): self
    {
        $context = $storage === '' ? $this->storage : $storage;
        $this->saveContent('',  $context, time() - static::$config::$expiration);
        $_COOKIE[$context] = '';

        return $this;
    }

   /**
     * {@inheritdoc}
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
     * {@inheritdoc}
    */
    public function has(string $key): bool
    {
        $data = $this->getContents();

        return isset($data[$key]);
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
    public function getResult(): array
    {
        if (isset($_COOKIE)) {
            return (array) $_COOKIE;
        }

        return [];
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
     * {@inheritdoc}
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