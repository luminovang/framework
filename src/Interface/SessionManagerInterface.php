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
use \Luminova\Exceptions\RuntimeException;
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
     * @return self Return instance of session manager class.
     */
    public function setStorage(string $storage): self;

    /**
     * Sets the session storage table index name to separate user session from other sessions and cookies.
     *
     * @param string $table The session storage table index.
     * 
     * @return self Return instance of session manager class.
     */
    public function setTable(string $table): self;

    /**
     * Gets the current session storage instance name.
     * 
     * @return string Return the session storage name.
     */
    public function getStorage(): string;

    /** 
     * Retrieves the PHP session or cookie identifier.
     * 
     * This method returns the active PHP session or ID, which uniquely identifies 
     * the session within the server or session cookie-id.
     * 
     * @return string|null Return the current session or cookie identifier or null if failed.
     */
    public function getId(): ?string;

    /**
     * Initializes session or cookie data and starts the session.
     * 
     * This method replaces the default PHP `session_start()` for session manager,
     * while it generate a secure cookie id on cookie manager. Additional it validates session id if provided.
     * 
     * @param string|null $sessionId Optional specify a valid PHP session 
     *      or cookie identifier (e.g,`session_id()` or `bin2hex(random_bytes(16))` for cookie).
     *
     * @return bool Return true if session started successfully, false otherwise.
     * @throws RuntimeException Throws if an invalid session ID is provided or an error is encounter.
     */
    public function start(?string $sessionId = null): bool;

    /**
     * Retrieve the current session or cookie status.
     * 
     * - PHP_SESSION_DISABLED if sessions are disabled. 
     * - PHP_SESSION_NONE if sessions or secure cookie-id are enabled, but none exists. 
     * - PHP_SESSION_ACTIVE if sessions or secure cookie-id are enabled, and one exists.
     * 
     * @return int Returns the current session or cookie status.
     */
    public function status():int;

    /**
     * Regenerate session or cookie identifier and delete the old ID associated session file or ID.
     * 
     * @return string|false Return the new generated session Id on success, otherwise false.
     */
    public function regenerateId(): string|bool;

    /**
     * Validates a session or cookie ID based on PHP's session configuration.
     *
     * This function checks if a given string is valid PHP session ID according to the current
     * PHP session configuration, specifically the 'session.sid_bits_per_character' and 'session.sid_length' settings.
     *
     * @param string $sessionId The session ID to validate.
     *
     * @return bool Returns `true` if the session ID matches the expected format, otherwise `false`.
     *
     * @throws RuntimeException Throws if `session.sid_bits_per_character` has an unsupported value.
     */
    public static function isValidId(string $sessionId): bool;

    /**
     * Empty all data stored in application session or cookie table.
     * 
     * This method can optionally clear entire application session or cookie data if `$allData` is set to true.
     * It uses PHP's `session_destroy()` and `setcookie` to affects the entire session.
     * 
     * @param bool $allData Whether to destroy all application session or cookie data (default: false).
     *
     * @return bool Return true if storage was data was deleted successfully otherwise false.
     * 
     * > **Note:** If `$allData` is set to true, all manager `session` or `cookie` data will be cleared for entire application.
     */
    public function destroy(bool $allData = false): bool;

    /** 
     * Write session data and end session.
     * 
     * @return self Return instance of session manager class.
     */
    public function commit(): self;

    /** 
     * Retrieves an item from the session storage.
     * 
     * @param string $index The key to retrieve.
     * @param mixed $default The default value if the key is not found.
     * 
     * @return mixed Return the retrieved data.
     */
    public function getItem(string $index, mixed $default = null): mixed;

    /** 
     * Stores an item in a specified storage name.
     * 
     * @param string $index The key to store.
     * @param mixed $data The data to store.
     * @param string|null $storage Optional storage name.
     * 
     * @return self Return instance of session manager class.
     */
    public function setItem(string $index, mixed $data, ?string $storage = null): self;

    /** 
     * Stores multiple items in a specified storage name at once.
     * 
     * @param array<string,mixed> $data The date to store where the key is the identifier.
     * @param string|null $storage Optional storage name.
     * 
     * @return self Return instance of session manager class.
     */
    public function setItems(array $data, ?string $storage = null): self;

    /** 
     * Clears all data from session storage. 
     * If $index is provided, it will remove the specified key from the session storage.
     * 
     * @param string|null $index The key index to remove.
     * @param string|null $storage Optionally specify the storage name to clear or remove an item.
     * 
     * @return self Return instance of session manager class.
     */
    public function deleteItem(?string $index = null, ?string $storage = null): self;

    /** 
     * Retrieves stored items from session storage as an array.
     * 
     * @param string|null $storage Optional storage key.
     * 
     * @return array Return the retrieved data.
     */
    public function getItems(?string $storage = null): array;

    /**
     * Gets all stored session data as an array or object.
     *
     * @param string $type The return session data type: e.g, 'array' or 'object' (default: `array`).
     * 
     * @return array|object Return all stored session data as an array or object.
     * @throws JsonException Throws if json error occurs.
     */
    public function getResult(string $type = 'array'): array|object;

    /** 
     * Checks if a key exists in the session.
     * 
     * @param string $key The key to check.
     * 
     * @return bool Return true if the key exists, false otherwise.
     */
    public function hasItem(string $key): bool;

    /** 
     * Checks if a storage key exists in the session.
     * 
     * @param string $storage The storage key to check.
     * 
     * @return bool Return true if the storage key exists, false otherwise.
     */
    public function hasStorage(string $storage): bool;

    /** 
     * Retrieves data as an array or object from the current session storage.
     * 
     * @param string $type The return session data type: e.g, 'array' or 'object' (default: `array`).
     * @param string|null $index Optional property key to retrieve.
     * 
     * @return object|array|null Return the retrieved data or null if key index not found.
     * @throws JsonException Throws if json error occurs.
     */
    public function toAs(string $type = 'array', ?string $index = null): object|array|null;
}