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
use \Luminova\Interface\SessionInterface;
use \Luminova\Exceptions\JsonException;

final class SessionManager implements SessionInterface 
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
     * The session storage index name.
     * 
     * @var string $table
     */
    private static string $table = 'default';

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

        $_SESSION[self::$table][$storage][$index] = $data;

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function deleteItem(?string $index = null, string $storage = ''): self
    {
        $storage = ($storage === '') ? $this->storage : $storage;

        if($storage !== '' && isset($_SESSION[self::$table][$storage])){
            if($index === '' || $index === null){
                unset($_SESSION[self::$table][$storage]);
            }else{
                unset($_SESSION[self::$table][$storage][$index]);
            }
        }

        return $this;
    }

    /** 
     * {@inheritdoc}
     */
    public function destroyItem(): bool
    {
        if(isset($_SESSION[self::$table])) {
            $_SESSION[self::$table] = [];
            unset($_SESSION[self::$table]);
            return true;
        }

        return false;
    }

    /** 
     * {@inheritdoc}
     */
    public function hasItem(string $key): bool
    {
        if(isset($_SESSION[self::$table][$this->storage])){
            return isset($_SESSION[self::$table][$this->storage][$key]);
        }

        return false;
    }

    /** 
     * {@inheritdoc}
     */
    public function hasStorage(string $storage): bool
    {
        return isset($_SESSION[self::$table][$storage]);
    }

    /** 
     * {@inheritdoc}
     */
    public function getResult(string $type = 'array'): array|object
    {
        $result = [];
        
        if (isset($_SESSION[self::$table])) {
            $result = $_SESSION[self::$table];
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
    public function toAs(string $type = 'array', ?string $index = null): object|array|null
    {
        $result = $this->getItems();

        if($index !== null && $index !== '') {
            $result = $result[$index] ?? null;
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
    public function getItems(string $storage = ''): array
    {
        $storage = ($storage === '') ? $this->storage : $storage;

        if (isset($_SESSION[self::$table][$storage])) {
            return (array) $_SESSION[self::$table][$storage];
        }

        return [];
    }
}