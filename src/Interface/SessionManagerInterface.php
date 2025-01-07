<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 */
namespace Luminova\Interface;

use \Luminova\Exceptions\JsonException;
use \Luminova\Base\BaseConfig;

interface SessionManagerInterface 
{
  /**
   * Initializes the session manager constructor.
   *
   * @param string $storage The session storage instance name. Default is 'global'.
   */
  public function __construct(string $storage = 'global');

  /**
   * Set session configuration object.
   *
   * @param BaseConfig $config Session configuration.
   */
  public function setConfig(BaseConfig $config): void;

  /**
   * Sets the session storage instance name where all session items will be stored.
   *
   * @param string $storage The session storage key.
   * 
   * @return self
   */
  public function setStorage(string $storage): self;

   /**
   * Sets the session storage table index name to separate user session from other sessions and cookies.
   *
   * @param string $table The session storage table index.
   * 
   * @return self
  */
  public function setTable(string $table): self;

  /**
   * Gets the current session storage instance name.
   * 
   * @return string The session storage name.
  */
  public function getStorage(): string;

  /**
   * Empty all data stored in session table.
   * This method doesn't behave same way as PHP `session_destroy`.
   *
   * @return bool Return true if storage was data was deleted successfully otherwise false.
  */
  public function destroyItem(): bool;

  /** 
   * Retrieves an item from the session storage.
   * 
   * @param string $index The key to retrieve.
   * @param mixed $default The default value if the key is not found.
   * 
   * @return mixed The retrieved data.
  */
  public function getItem(string $index, mixed $default = null): mixed;

  /** 
   * Stores an item in a specified storage instance.
   * 
   * @param string $index The key to store.
   * @param mixed $data The data to store.
   * @param string|null $storage The storage key name.
   * 
   * @return self
  */
  public function setItem(string $index, mixed $data, ?string $storage = null): self;

  /** 
   * Clears all data from session storage. 
   * If $index is provided, it will remove the specified key from the session storage.
   * 
   * @param string|null $index The key index to remove.
   * @param string|null $storage Optionally specify the storage name to clear or remove an item.
   * 
   * @return self
  */
  public function deleteItem(?string $index = null, ?string $storage = null): self;

  /** 
   * Retrieves stored items from session storage as an array.
   * 
   * @param string|null $storage Optional storage key.
   * 
   * @return array The retrieved data.
  */
  public function getItems(?string $storage = null): array;

  /**
   * Gets all stored session data as an array or object.
   *
   * @param string $type Return type of 'array' or 'object'. Default is 'array'.
   * 
   * @return array|object All stored session data.
   * @throws JsonException Throws if json error occurs.
  */
  public function getResult(string $type = 'array'): array|object;

  /** 
   * Checks if a key exists in the session.
   * 
   * @param string $key The key to check.
   * 
   * @return bool True if the key exists, false otherwise.
  */
  public function hasItem(string $key): bool;

    /** 
   * Checks if a storage key exists in the session.
   * 
   * @param string $storage The storage key to check.
   * 
   * @return bool True if the storage key exists, false otherwise.
  */
  public function hasStorage(string $storage): bool;

  /** 
   * Retrieves data as an object or array from the current session storage.
   * 
   * @param string $type Return type of 'array' or 'object'. Default is 'array'.
   * @param string $index Optional key to retrieve.
   * 
   * @return object|array|null The retrieved data or null if key index not found.
   * @throws JsonException Throws if json error occurs.
  */
  public function toAs(string $type = 'array', ?string $index = null): object|array|null;
}