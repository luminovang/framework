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
   * Session constructor.
   *
   * @param string $storage The session storage key.
   */
  public function __construct(string $storage = 'global');

  /**
   * Set the session storage key.
   *
   * @param string $storage The session storage key.
   * 
   * @return self
   */
  public function setStorage(string $storage): self;

    /**
   * Get the session storage key.
   * @return string The session storage key.
   */
  public function getStorage(): string;

  /**
   * Add a key-value pair to the session data.
   *
   * @param string $key The key.
   * @param mixed $value The value.
   * 
   * @return self
   */
  public function add(string $key, mixed $value): self;

  /** 
   * Set a key and value in the session.
   * 
   * @param string $key The key to set.
   * @param mixed $value The value to set.
   * 
   * @return self
  */
  public function set(string $key, mixed $value): self;

  /** 
   * Retrieve data from the session.
   * 
   * @param string $index The key to retrieve.
   * @param mixed $default The default value if the key is not found.
   * 
   * @return mixed The retrieved data.
  */
  public function get(string $index, mixed $default = null): mixed;

  /** 
   * Retrieve data from a specified storage instance.
   * 
   * @param string $index The key to retrieve.
   * @param string $storage The storage key name.
   * 
   * @return mixed The retrieved data.
  */
  public function getFrom(string $index, string $storage): mixed;

  /** 
   * Store data in a specified storage instance.
   * 
   * @param string $index The key to store.
   * @param mixed $data The data to store.
   * @param string $storage The storage key name.
   * 
   * @return self
  */
  public function setTo(string $index, mixed $data, string $storage): self;

  /** 
   * Check if the session user is online from any storage instance.
   * 
   * @param string $online Optional storage instance key.
   * 
   * @return bool True if the session user is online, false otherwise.
  */
  public function online(string $storage = ''): bool;

  /** 
   * Clear all data from a specific session storage by passing the storage key.
   * 
   * @param string $storage The storage key to clear.
   * 
   * @return self
  */
  public function clear(string $storage = ''): self;

  /** 
   * Remove a key from the current session storage by passing the key.
   * 
   * @param string $index The key index to remove.
   * 
   * @return self
  */
  public function remove(string $index): self;

  /** 
   * Retrieve data as an array from storage.
   * 
   * @param string $storage Optional storage key.
   * 
   * @return array The retrieved data.
  */
  public function getContents(string $storage = ''): array;

  /**
   * Get all stored session data as an array.
   *
   * @return array All stored session data.
  */
  public function getResult(): array;

  /** 
   * Check if a key exists in the session.
   * 
   * @param string $key The key to check.
   * 
   * @return bool True if the key exists, false otherwise.
  */
  public function has(string $key): bool;

    /** 
   * Check if a storage key exists in the session.
   * 
   * @param string $storage The storage key to check.
   * 
   * @return bool True if the storage key exists, false otherwise.
  */
  public function hasStorage(string $storage): bool;

  /** 
   * Retrieve data as an array from the current session storage.
   * 
   * @param string $index Optional key to retrieve.
   * 
   * @return array The retrieved data.
  */
  public function toArray(string $index = ''): array;

  /** 
   * Retrieve data as an object from the current session storage.
   * 
   * @param string $index Optional key to retrieve.
   * 
   * @return object The retrieved data.
  */
  public function toObject(string $index = ''): object;

  /** 
   * Retrieve data as an object or array from the current session storage.
   * 
   * @param string $type Return type of object or array.
   * @param string $index Optional key to retrieve.
   * 
   * @return object|array The retrieved data.
  */
  public function toAs(string $type = 'array', string $index = ''): object|array;

  /** 
   * Set cookie and sesstion config class name.
   * 
   * @param string $config The SessionConfig class name.
   * 
   * @return void
  */
  public function setConfig(string $config): void;
}