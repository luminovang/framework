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

interface SessionInterface 
{
    /**
     * Set storage key
     *
     * @param string $storage The session storage key.
     * @return self
    */
    public function setStorage(string $storage): self;

     /**
     * Get storage key
     * @return string
    */
    public function getStorage(): string;
  
    /**
     * Add a key-value pair to the session data.
     *
     * @param string $key The key.
     * @param mixed $value The value.
     * @return self
     */
    public function add(string $key, mixed $value): self;

    /** 
     * Set key and value to session
     * @param string $key key to set
     * @param mixed $value value to set
     * @return self
    */
    public function set(string $key, mixed $value): self;

    /** 
     * get data from session
     * @param string $index key to het
     * @param mixed $default default value 
     * 
     * @return mixed
    */
    public function get(string $index, mixed $default = null): mixed;

    /** 
     * Get data from specified storage instance
     * @param string $index value key to get
     * @param string $storage Storage key name
     * @return mixed
    */
    public function getFrom(string $index, string $storage): mixed;

    /** 
     * Get data from specified storage instance
     * @param string $index value key to get
     * @param mixed $data data to set
     * @param string $storage Storage key name
     * @return self
    */
    public function setTo(string $index, mixed $data, string $storage): self;

   /** 
     * Check if session user is online from any storage instance
     * @param string $online optional storage instance key
     * @return bool
    */
    public function online(string $storage = ''): bool;

    /** 
     * Clear all data from specific session storage by passing the storage key
     * @param string $storage storage key to unset
     * @return self
    */
    public function clear(string $storage = ''): self;

    /** 
     * Remove key from current session storage by passing the key
     * @param string $index key index to unset
     * @return self
    */
    public function remove(string $index): self;

    /** 
     * Get data as array from storage 
     * 
     * @param string $storage optional storage key 
     * 
     * @return array
    */
    public function getContents(string $storage = ''): array;

    /**
    *Get all stored session as array
    * @return array
    */
    public function getResult(): array;

    /** 
     * Check if key exists in session
     * @param string $key
     * @return bool
    */
    public function has(string $key): bool;

     /** 
     * Check if storage key exists in session
     * 
     * @param string $storage
     * 
     * @return bool
    */
    public function hasStorage(string $storage): bool;

    /** 
     * Get data as array from current session storage 
     * @param string $index optional key to get
     * @return array
    */
    public function toArray(string $index = ''): array;

    /** 
     * Get data as object from current session storage
     * @param string $index optional key to get
     * @return object
    */
    public function toObject(string $index = ''): object;

    /** 
     * Set cookie options 
     * 
     * @param string $config SessionConfig class name
     * 
     * @return void
    */
    public function setConfig(string $config): void;

}