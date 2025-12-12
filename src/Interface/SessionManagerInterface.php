<?php 
/**
 * Luminova Framework
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use Luminova\Base\Configuration;
use Luminova\Exceptions\JsonException;
use Luminova\Exceptions\RuntimeException;
use Luminova\Exceptions\InvalidArgumentException;

interface SessionManagerInterface 
{
    /**
     * Initializes the session manager constructor.
     *
     * @param string $storage The session storage instance name (default: 'global').
     */
    public function __construct(string $storage = 'global');

    /**
     * Determines whether the current session storage is empty.
     *
     * Returns `true` when no value exists for the current session key,
     * otherwise `false`.
     *
     * @return bool `true` if the session storage is empty, otherwise `false`.
     */
    public function isEmpty(): bool;

    /**
     * Determines whether the current session is closed.
     *
     * A closed session is no longer available for active read/write operations
     * until it is started again.
     *
     * @return bool `true` if the session is closed, otherwise `false`.
     */
    public function isClosed(): bool;
    
    /**
     * Validates a session or cookie identifier.
     *
     * Determines whether the given identifier conforms to the format accepted
     * by the underlying session storage.
     *
     * @param string $sessionId The session or cookie identifier to validate.
     *
     * @return bool `true` if the identifier is valid, otherwise `false`.
     */
    public static function isValidId(string $sessionId): bool;

    /**
     * Set session configuration object.
     *
     * @param Configuration<\App\Config\Session> $config Session configuration.
     */
    public function setConfig(Configuration $config): void;

    /**
     * Set the session storage name used to store and retrieve session items.
     *
     * @param string $storage The session storage name.
     *
     * @return self Return the current session manager instance.
     * @throws InvalidArgumentException If the storage name is invalid.
     */
    public function setStorage(string $storage): self;

    /**
     * Set the storage namespace used to organize session items.
     *
     * @param string $namespace The storage namespace.
     *
     * @return self Return the current session manager instance.
     * @throws InvalidArgumentException If the namespace is invalid.
     */
    public function setNamespace(string $namespace): self;

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
     * Regenerate session or cookie identifier.
     * 
     * This method delete the old ID associated to the current session, to retain data set to false.
     * 
     * @param bool $clearSessionData Whether to clear existing session data.
     * 
     * @return string|false Return the new generated session Id on success, otherwise false.
     */
    public function regenerateId(bool $clearSessionData = false): string|bool;

    /**
     * Clear all session data stored in the current storage and namespace.
     *
     * Only sessions managed by this class are cleared. Other session or cookie
     * data is not affected unless its key matches the manager's key format.
     *
     * @return bool True if all matching data was cleared successfully, otherwise false.
     */
    public function clear(): bool;

    /**
     * Write pending session data and close the active session.
     *
     * Persists the current session data and releases the session lock. The session
     * remains available for reading during the current request but must be started
     * again before further changes can be persisted.
     *
     * @return bool `true` if the session was successfully closed, otherwise `false`.
     */
    public function close(): bool;

    /** 
     * Retrieves an item from the session storage.
     * 
     * @param string $name The item name to retrieve.
     * @param mixed $default The default value if the key is not found.
     * 
     * @return mixed Return the retrieved data.
     */
    public function getItem(string $name, mixed $default = null): mixed;

    /** 
     * Stores an item in a specified storage name.
     * 
     * @param string $name The item name to store.
     * @param mixed $data The data to store.
     * 
     * @return self Return instance of session manager class.
     */
    public function setItem(string $name, mixed $data): self;

    /** 
     * Stores multiple items in a specified storage name at once.
     * 
     * @param array<string,mixed> $items The items to store where the key is the identifier.
     * 
     * @return self Return instance of session manager class.
     */
    public function setItems(array $items): self;

    /** 
     * Delete item from session storage.
     *  
     * If `$storage` is provided, it will remove item from specified session storage name.
     * 
     * @param string $name The item name to remove.
     * 
     * @return self Return instance of session manager class.
     */
    public function deleteItem(string $name): self;

    /** 
     * Retrieves stored items from session storage as an array.
     * 
     * @return array Return the retrieved data.
     */
    public function getItems(): array;

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
     * Retrieve session data as an array.
     * 
     * @param string|null $storage Optional session storage name to retrieve.
     * 
     * @return array Return all stored session data as an array or object.
     */
    public function toArray(?string $storage = null): array;

    /**
     * Retrieve session data as an object.
     *
     * The current session data is converted and
     * returned as an object.
     *
     * @param string|null $storage Optional session storage name to retrieve.
     *
     * @return object|null The retrieved data as an object, or `null` if the
     *     specified key does not exist or cannot be converted to an object.
     *
     * @throws JsonException If the session data cannot be encoded or decoded during conversion.
     */
    public function toObject(?string $storage = null): ?object;
}