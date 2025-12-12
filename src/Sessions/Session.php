<?php 
declare(strict_types=1);
/**
 * Luminova Framework backend session helper class.
 * This class is responsible for storing and retrieving session information 
 * as well as managing user login session data.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Sessions;

use \Luminova\Luminova;
use \Luminova\Time\Time;
use \Luminova\Logger\Logger;
use \Luminova\Http\Network\IP;
use \Luminova\Base\SessionHandler;
use \App\Config\Session as SessionConfig;
use \Luminova\Exceptions\InvalidArgumentException;
use \Luminova\Sessions\Managers\Session as SessionManager;
use \Luminova\Interface\{LazyObjectInterface, SessionManagerInterface};
use \Luminova\Exceptions\{ErrorCode, LogicException, RuntimeException};

final class Session implements LazyObjectInterface
{
    /**
     * Session manager interface
     * 
     * @var SessionManagerInterface $manager
     */
    private ?SessionManagerInterface $manager = null;

    /**
     * static class instance
     * 
     * @var self $instance 
     */
    private static ?self $instance = null;

    /**
     * Session start inactive.
     * 
     * @var int INACTIVE 
     */
    private const INACTIVE = 0;

    /**
     * Session start started.
     * 
     * @var int STARTED 
     */
    private const STARTED = 1;

    /**
     * Session start committed.
     * 
     * @var int COMMITTED 
     */
    private const COMMITTED = 2;

    /**
     * At least one required role must exist in user roles.
     * 
     * @var int GUARD_ANY
     * @see guard()
     */
    public final const GUARD_ANY   = 0;

    /**
     * All required roles must be present in user roles (but extras allowed).
     * 
     * @var int GUARD_ALL
     * @see guard()
     */
    public final const GUARD_ALL   = 1;

    /**
     * Exact match — all and only the specified roles must exist.
     * 
     * @var int GUARD_EXACT
     * @see guard()
     */
    public final const GUARD_EXACT = 2;

    /**
     * None of the given roles should be present (e.g., guest access only).
     * 
     * @var int GUARD_NONE
     * @see guard()
     */
    public final const GUARD_NONE  = 3;

    /**
     * Session start status.
     * 
     * @var int $status 
     */
    private static int $status = self::INACTIVE;

    /**
     * Session configuration.
     * 
     * @var SessionConfig $config 
     */
    private static ?SessionConfig $config = null;

    /**
     * Session handler.
     * 
     * @var SessionHandler $handler 
     */
    private ?SessionHandler $handler = null;

    /**
     * Callback handler for ip change.
     * 
     * @var callable|null $onIpChange 
     */
    private mixed $onIpChange = null;

    /**
     * Is session started in context.
     * 
     * @var bool $isStarted 
     */
    private static bool $isStarted = false;

    /**
     * Stacked items.
     * 
     * @var array<string,mixed> $stacks 
     */
    private array $stacks = [];

    /**
     * Sessions are disabled.
     * 
     * @var int DISABLED 
     */
    public final const DISABLED = 0;

    /**
     * Sessions are enabled, but no session exists.
     * 
     * @var int NONE 
     */
    public final const NONE = 1;

    /**
     * A session is currently active.
     * 
     * @var int ACTIVE 
     */
    public final const ACTIVE = 2;

    /**
     * Index key for session metadata.
     * 
     * @var string METADATA 
     */
    private const METADATA = '__session_metadata__';

    /**
     * Initializes the backend session handler class.
     *
     * This constructor sets up the session manager to handle user login and backend session management. 
     * It allows for an optional custom session manager and session handler to be provided or defaults to the standard manager.
     *
     * @param SessionManagerInterface|null $manager Optional. A custom session manager instance.
     *              If not provided, the default `\Luminova\Sessions\Managers\Session` will be used.
     *
     * > **Note:** When no custom manager is provided, the default session manager is automatically 
     * > initialized and configured using the session configuration settings.
     * @see https://luminova.ng/docs/0.0.0/sessions/session
     * @see https://luminova.ng/docs/0.0.0/sessions/examples
     */
    public function __construct(?SessionManagerInterface $manager = null)
    {
        self::$config ??= new SessionConfig();
        $this->manager = $manager ?? new SessionManager();
        $this->manager->setTable(self::$config->tableIndex);
        $this->manager->setConfig(self::$config);
        $manager = null;
    } 

    /**
     * Auto-save if there are unsaved stacked items.
     */
    public function __destruct()
    {
        if ($this->stacks !== []) {
            $this->save();
        }
    }

    /**
     * Singleton method to return an instance of the Session class.
     *
     * @param SessionManagerInterface|null $manager Optional. A custom session manager instance.
     *              If not provided, the default `\Luminova\Sessions\Managers\Session` will be used.
     * 
     * @return static Return static Session class instance.
     */
    public static function getInstance(?SessionManagerInterface $manager = null): static
    {
        if (self::$instance === null) {
            self::$instance = new self($manager);
        }

        return self::$instance;
    }

    /**
     * Convert a string into a valid PHP session ID.
     *
     * This method generates a session ID that conforms to the current PHP
     * session configuration (`session.sid_bits_per_character` and `session.sid_length`).
     * If the input string is already a hash, it uses it directly.
     * 
     * Character sets based on `session.sid_bits_per_character`:
     * - 4: Hexadecimal characters [0-9a-f]
     * - 5: Base32 characters [0-9a-v]
     * - 6: Extended base64 characters [0-9a-zA-Z,-]
     *
     * @param string $input The input string to convert.
     * @param string $algo The hashing algorithm to use if input is not already hashed (Default `sha256`).
     *
     * @return string|null Return a valid PHP session ID based on the current configuration or null if failed.
     *
     * @throws RuntimeException If `session.sid_bits_per_character` is unsupported.
     * @example - Examples:
     * 
     * Convert CLI System Id to php session Id:
     * 
     * ```php
     * $sid = Session::toSessionId(Terminal::getSystemId());
     * ```
     * Convert string to session Id:
     * ```php
     * $sid = Session::toSessionId('user-id');
     * ```
     */
    public static function toSessionId(string $input, string $algo = 'sha256'): ?string
    {
        $bitsPerCharacter = (int) ini_get('session.sid_bits_per_character');
        $sidLength = (int) ini_get('session.sid_length');

        if($bitsPerCharacter <= 0){
            $bitsPerCharacter = 4;
        }

        if($sidLength <= 0){
            $sidLength = 32;
        }

        $chars = match ($bitsPerCharacter) {
            4 => '0123456789abcdef',
            5 => '0123456789abcdefghijklmnopqrstuv',
            6 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ,-',
            default => throw new RuntimeException(
                sprintf("Unsupported session.sid_bits_per_character value: '%d'.", $bitsPerCharacter)
            )
        };

        $hash = preg_match('/^[0-9a-f]{64}$/iu', $input) ? $input : hash($algo, $input);

        // Convert hash to raw bytes
        $bytes = hex2bin($hash);

        if($bytes === false){
            return null;
        }

        $result = '';
        $mask = (1 << $bitsPerCharacter) - 1;
        $totalBits = strlen($bytes) * 8;

        $bitIndex = 0;
        for ($i = 0; $i < $sidLength; $i++) {
            // Calculate which byte(s) to read
            $byteIndex = intdiv($bitIndex, 8);
            $offset = $bitIndex % 8;

            // Read 16 bits to safely cover cross-byte boundary
            $byte1 = ord($bytes[$byteIndex]);
            $byte2 = ($byteIndex + 1 < strlen($bytes)) ? ord($bytes[$byteIndex + 1]) : 0;
            $combined = ($byte1 << 8) | $byte2;

            // Extract bits
            $chunk = ($combined >> (16 - $offset - $bitsPerCharacter)) & $mask;
            $result .= $chars[$chunk];

            $bitIndex += $bitsPerCharacter;
            if ($bitIndex >= $totalBits) {
                $bitIndex = 0; // Wrap around if needed
            }
        }

        return $result;
    }

    /**
     * Retrieves the current session storage manager instance.
     * 
     * This method returns the session manager instance responsible for handling 
     * session data, either `Luminova\Sessions\Managers\Cookie` or `Luminova\Sessions\Managers\Session`.
     *
     * @return SessionManagerInterface|null Return the current session manager instance, or `null` if not set.
     */
    public function getManager(): ?SessionManagerInterface
    {
        return $this->manager;
    }

    /**
     * Retrieves the current session storage name.
     * 
     * This method returns the current storage name used to store session data.
     * 
     * @return string Return the current session storage name.
     */
    public function getStorage(): string 
    {
        return $this->manager->getStorage();
    }

    /**
     * Retrieves the session cookie name.
     * 
     * This method returns the name of the session cookie used for session management.
     * If a custom cookie name is set in the configuration, it will be returned; 
     * otherwise, the default PHP session name is used.
     * 
     * @return string Return the session cookie name.
     */
    public function getName(): string 
    {
        return self::$config?->cookieName ?: session_name() ?: 'PHPSESSID';
    }

    /**
     * Retrieves all session data in the specified format.
     * 
     * @param string $format The data format, either `object` or `array` (default: `array`).
     * 
     * @return array|object Return the stored session data in the requested format.
     */
    public function getResult(string $format = 'array'): array|object
    {
        return $this->manager->getResult($format);
    }

    /**
     * Retrieves a value from the session storage.
     *
     * @param string $key The key used to identify the session data.
     * @param mixed $default The default value returned if the key does not exist.
     * 
     * @return mixed Returns the retrieved session data or the default value if not found.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->manager->getItem($key, $default);
    }

    /** 
     * Retrieves the PHP session identifier.
     * 
     * This method returns the active PHP session ID, which uniquely identifies 
     * the session within the server.
     * 
     * @return string|null Return the current PHP session identifier or null if failed.
     */
    public function getId(): ?string
    {
        return ($this->is(self::ACTIVE) || $this->online()) ? $this->manager->getId() : null;
    }

    /**
     * Retrieves the IP address associated with the session.
     *
     * @return string|null Return the stored IP address or null if not set.
     */
    public function getIp(): ?string 
    {
        return $this->getMeta('ip');
    }

    /**
     * Retrieves the user agent associated with the session.
     *
     * This method returns the browser or client identifier used when the session was created.
     *
     * @return string|null Return the user agent string or null if not set.
     */
    public function getUserAgent(): ?string 
    {
        return $this->getMeta('agent');
    }

    /** 
     * Retrieves the client's online session login token.
     * 
     * This method returns a randomly generated token when `login()` or `synchronize()` is called.
     * The returned token can be used to track the online session state, 
     * validate session integrity or prevent session fixation attacks.
     * 
     * @return string|null The login session token, or `null` if not logged in.
     */
    public function getToken(): ?string
    {
        return $this->getMeta('token');
    }

    /** 
     * Retrieves the client login session date and time in ISO 8601 format.
     * 
     * The session datetime is generated automatically when `login()` or `synchronize()` is called, 
     * marking the moment the session login was established.
     * 
     * @return string Return the session login datetime in ISO 8601 format, or `null` if not logged in.
     */
    public function getDatetime(): ?string
    {
        $timestamp = $this->getTimestamp();
        return ($timestamp === 0) ? null : date(DATE_ATOM, $timestamp);
    }

    /**
     * Retrieves the client login session creation timestamp.
     * 
     * The session timestamp is generated automatically when `login()` or `synchronize()` is called, 
     * marking the moment the session login was established.
     *
     * @return int Return he Unix timestamp when the session was created.
     */
    public function getTimestamp(): int 
    {
        return $this->getMeta('timestamp') ?? 0;
    }

    /**
     * Retrieves the session expiration timestamp.
     *
     * This method returns the Unix timestamp at which the session is set to expire.
     *
     * @return int Return the expiration timestamp or 0 if not set.
     */
    public function getExpiration(): int 
    {
        return Time::now()->modify('+' . self::$config->expiration . ' seconds')->getTimestamp();
    }

    /** 
     * Retrieves a session data from a specific session storage name.
     * 
     * @param string $storage The storage name where the data is stored.
     * @param string $key The key used to identify the session data.
     * 
     * @return mixed Returns the retrieved session data or `null` if not found.
     */
    public function getFrom(string $storage, string $key): mixed
    {
        return $this->manager->getItems($storage)[$key] ?? null;
    }

    /**
     * Retrieves session login metadata key value from session storage.
     *
     * @param string $key The metadata key to retrieve.
     * @param string|null $storage Optional storage name.
     * 
     * @return mixed Return the metadata value or null if not exist.
     */
    public function getMeta(string $key, ?string $storage = null): mixed 
    {
        return $this->getMetadata($storage)[$key] ?? null;
    }

    /**
     * Retrieves session login metadata information from session storage.
     *
     * @param string|null $storage Optional storage name.
     * 
     * @return array<string,mixed> Return an associative array containing session metadata.
     */
    public function getMetadata(?string $storage = null): array 
    {
        return (array) (($storage === null) 
            ? $this->get(self::METADATA)
            : $this->getFrom($storage, self::METADATA)
        ) ?? [];
    }

    /**
     * Retrieves the session fingerprint.
     *
     * The fingerprint is a unique identifier used to track session consistency.
     *
     * @return string|null Return the session fingerprint or null if not set.
     */
    public function getFingerprint(): ?string 
    {
        return $this->getMeta('fingerprint');
    }

    /**
     * Retrieves a list of IP address changes during the session.
     *
     * This method returns an array of previously recorded IP addresses if they changed 
     * during the session lifetime.
     *
     * @return array Return the list of IP address changes.
     */
    public function getIpChanges(): array 
    {
        return $this->getMeta('ip_changes') ?? [];
    }

    /**
     * Get the list of roles assigned to the current session user.
     *
     * Retrieves roles from the session metadata. Returns an empty array if no roles are set.
     *
     * @return array<int,string|int> Return a list of assigned roles or an empty array if none.
     * 
     * @see roles() - Set user roles.
     * @see guard() - Guard access by user roles.
     * @since 3.6.8
     *
     * @example - Example:
     * ```php
     * $roles = $session->getRoles();
     * 
     * if (in_array('admin', $roles)) {
     *     // grant admin access
     * }
     * ```
     */
    public function getRoles(): array 
    {
        return $this->getMeta('roles') ?? [];
    }

    /**
     * Sets the session save handler responsible for managing session storage.
     * 
     * This method allows specifying a custom session save handler, such as a 
     * database array-handler,or filesystem-based handler, to control how session data is stored and retrieved.
     * 
     * Supported session save handlers:
     * - `Luminova\Sessions\Handlers\Database`: Stores session data in a database.
     * - `Luminova\Sessions\Handlers\Filesystem`: Saves session data in files.
     * - `Luminova\Sessions\Handlers\ArrayHandler`: Stores session data temporarily in an array.
     *
     * @param SessionHandler $handler The session save handler instance.
     *
     * @return self Returns the instance of session class.
     *
     * @see https://luminova.ng/docs/edit/0.0.0/sessions/database-handler
     * @see https://luminova.ng/docs/edit/0.0.0/sessions/filesystem-handler
     * @see https://luminova.ng/docs/edit/0.0.0/base/session-handler
     */
    public function setHandler(SessionHandler $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * Sets the session manager that controls the underlying storage engine for session data.
     *
     * Unlike a session handler `setHandler()`, which is only applicable when using `Luminova\Sessions\Managers\Session`, 
     * this method allows specifying a session manager to determine where session data is stored.
     *
     * Supported session managers:
     * - `Luminova\Sessions\Managers\Cookie`: Stores session data securely in client-side cookies.
     * - `Luminova\Sessions\Managers\Session`: Uses PHP's default `$_SESSION` storage.
     *
     * @param SessionManagerInterface $manager The session manager instance to set.
     * 
     * @return self Returns the instance of session class.
     */

    public function setManager(SessionManagerInterface $manager): self
    {
        $this->manager = $manager;
        return $this;
    }

    /**
     * Sets the storage name for storing and retrieving session data.
     * 
     * This method allows you to define or override the session name under which session data will be managed.
     *
     * @param string $storage The session storage key to set.
     * 
     * @return self Returns the instance of session class.
     */
    public function setStorage(string $storage): self
    {
        $this->manager->setStorage($storage);
        return $this;
    }

    /** 
     * Stores a value in a specific session storage name.
     * 
     * @param string $key The key used to identify the session data.
     * @param mixed $value The value to be stored.
     * @param string $storage The storage name where the value will be saved.
     * 
     * @return self Returns the instance of session class.
     * @throws RuntimeException If an operation is attempted without an active session.
     * 
     * > **Note:** The `save()` method is not required to persist session date when using `setTo()` method.
     */
    public function setTo(string $key, mixed $value, string $storage): self
    {
        $this->restart();
        $this->manager->setItem($key, $value, $storage);
        $this->setActivity($storage);
        return $this;
    }

    /**
     * Sets a value in the session storage by key.
     *
     * This method saves or updates a value in the session using the specified key. 
     * If the key already exists, its value will be overwritten with the new value.
     *
     * @param string $key The key to identify the session data.
     * @param mixed $value The value to associate with the specified key.
     * 
     * @return self Returns the instance of session class.
     * @throws RuntimeException If an operation is attempted without an active session.
     * 
     * > **Note:** The `save()` method is not required to persist session date when using `set()` method.
     */
    public function set(string $key, mixed $value): self
    {
        $this->restart();
        $this->manager->setItem($key, $value);
        $this->setActivity();
        return $this;
    }

    /**
     * Adds a value to the session storage without overwriting existing keys.
     *
     * This method attempts to add a new key-value pair to the session. 
     * If the specified key already exists in the session storage, the method does not modify the value and sets the status to `false`. 
     * Otherwise, it adds the new key-value pair and sets the status to `true`.
     *
     * @param string $key The key to identify the session data.
     * @param mixed $value The value to associate with the specified key.
     * @param bool $status A reference variable to indicate whether the operation succeeded (`true`) or failed (`false`).
     * 
     * @return self Returns the instance of session class.
     * > **Note:** The `save()` method is not required to persist session date when using `add()` method.
     */
    public function add(string $key, mixed $value, bool &$status = false): self
    {
        if($this->has($key)){
            $status = false;
            return $this;
        }

        $this->set($key, $value);
        $status = true;
        return $this;
    }

    /**
     * Queues multiple items for batch storage when `save` is called.
     *
     * This method allows adding multiple key-value pairs to a temporary stack, 
     * which can later be saved to session storage using `$session->save()`. 
     * If a key already exists in the stack or storage, its value will be overwritten.
     *
     * @param string $key The key to associate with the value.
     * @param mixed $value The value to be stored in the stack.
     * 
     * @return self Returns the instance of session class.
     */
    public function put(string $key, mixed $value): self
    {
        $this->stacks[$key] = $value;
        return $this;
    }

    /**
     * Saves all stacked items to the session storage.
     *
     * This method moves all previously stacked items (added via `put()`) 
     * to session storage. If a storage name is provided, the items are saved 
     * under that specific session storage. Once saved, the stack is cleared.
     *
     * @param string|null $storage Optional storage name where stacked data will be saved.
     * 
     * @return bool Returns true if data was successfully saved, otherwise false.
     * @throws RuntimeException If an operation is attempted without an active session.
     */
    public function save(?string $storage = null): bool
    {
        if($this->stacks === []){
            return false;
        }

        $this->restart();
        $this->manager->setItems($this->stacks, $storage);
        $this->setActivity($storage);
        $this->stacks = [];
        return true;
    }

    /**
     * Commits the current session data.
     *
     * This method finalizes the session write process by committing any changes 
     * made to the session data. Once committed, the session is considered closed 
     * and cannot be modified until restarted.
     * 
     * @return void
     */
    public function commit(): void 
    {
        $this->manager->commit();
        self::$status = self::COMMITTED;
    }

    /**
     * Clears all stacked session data without saving.
     *
     * This method removes all temporarily stored session data before it is saved. 
     * Use it if you want to discard changes before calling `save()`.
     *
     * @return true Always return true.
     */
    public function dequeue(): bool
    {
        $this->stacks = [];
        return true;
    }

    /** 
     * Determines if the client has successfully logged in.
     * 
     * This method verifies whether the `login()` or `synchronize()` method has been called,
     * meaning the session user is considered online. It optionally checks a 
     * specific session storage; otherwise, it defaults to the current storage.
     * 
     * @param string|null $storage Optional session storage name.
     * 
     * @return bool Returns true if the session user is online, false otherwise.
     */
    public function online(?string $storage = null): bool
    {
        $data = $this->getMetadata($storage);
        return (
            $data !== []
            && isset($data['online'], $data['token']) 
            && $data['online'] === 'on'
        );
    }

    /**
     * Checks if the current session or cookie status matches the given status.
     *
     * @param int $status The session status to check.
     *                       - `Session::DISABLED` (PHP_SESSION_DISABLED): Sessions are disabled.
     *                       - `Session::NONE` (PHP_SESSION_NONE): Sessions or cookie are enabled but no session exists.
     *                       - `Session::ACTIVE` (PHP_SESSION_ACTIVE): A session or cookie is currently active.
     *
     * @return bool Returns `true` if the current session status matches the given status, otherwise `false`.
     */
    public function is(int $status = self::ACTIVE): bool 
    {
        return $this->manager->status() === match ($status) {
            self::DISABLED => PHP_SESSION_DISABLED,
            self::NONE     => PHP_SESSION_NONE,
            self::ACTIVE   => PHP_SESSION_ACTIVE,
            default        => null
        };
    }

    /**
     * Checks if the session has started.
     * 
     * This method will return true after calling `start` session method.
     * 
     * @return bool Returns `true` if session has stated, otherwise `false`.
     */
    public function isStarted(): bool 
    {
        return self::$isStarted && self::$status === self::STARTED;
    }

    /** 
     * Checks if the session user is currently online.
     * 
     * This method acts as an alias for `online()`, maintaining naming consistency.
     * 
     * @return bool Returns true if the session user is online, false otherwise.
     */
    public function isOnline(): bool
    {
        return $this->online();
    }

    /** 
     * Checks if the session is still valid based on elapsed time.
     * 
     * This method determines whether the session has expired based on the last 
     * recorded online time. By default, a session is considered expired after 
     * 3600 seconds (1 hour).
     * 
     * @param int $seconds The time threshold in seconds before the session is considered expired (Default: 3600).
     * 
     * @return bool Returns true if the session is still valid, false if it has expired.
     */
    public function isExpired(int $seconds = 3600): bool
    {
        $timestamp = $this->getTimestamp();
        return ($timestamp !== null && (time() - $timestamp < $seconds));
    }

    /** 
     * Checks if strict IP validation is enabled in the session configuration.
     * 
     * @return bool Returns true if strict session IP enforcement is enabled, false otherwise.
     */
    public function isStrictIp(): bool
    {
        return (bool) self::$config->strictSessionIp;
    }

    /** 
     * Validates whether the session IP remains unchanged when strict IP enforcement is enabled.
     * 
     * This method ensures that the user's IP address matches the stored session IP,
     * preventing session hijacking if strict IP validation is enabled.
     * 
     * @return bool Returns true if strict IP validation is enabled and the IP is unchanged, false otherwise.
     */
    public function isSessionIp(): bool
    {
        return $this->isStrictIp() && !$this->ipChanged();
    }

    /** 
     * Retrieves session data as an associative array.
     * 
     * @param string|null $key Optional key to retrieve specific data. If null, returns all session data.
     * 
     * @return array Return the session data as an associative array.
     */
    public function toArray(?string $key = null): array
    {
        return $this->manager->toAs('array', $key);
    }

    /** 
     * Retrieves session data as an object.
     * 
     * @param string|null $key Optional key to retrieve specific data. If null, returns all session data.
     * 
     * @return object return the session data as a standard object.
     */
    public function toObject(?string $key = null): object
    {
        return $this->manager->toAs('object', $key);
    }

    /** 
     * Remove a key from the session storage by passing the key.
     * 
     * @param string $key The key to identify the session data to remove.
     * 
     * @return self Returns the instance of session class.
     * @throws RuntimeException If an operation is attempted without an active session.
     */
    public function remove(string $key): self
    {
        $this->restart();
        $this->manager->deleteItem($key);
        return $this;
    }

    /** 
     * Clear all data from session storage by passing the storage name or using the default storage.
     * 
     * @param string|null $storage Optionally session storage name to clear.
     * 
     * @return self Returns the instance of session class.
     */
    public function clear(?string $storage = null): self
    {
        $this->restart(false);
        $this->manager->deleteItem(null, $storage);
        return $this;
    }

    /** 
     * Check if item key exists in session storage.
     * 
     * @param string $key The key to identify the session data to check.
     * 
     * @return bool Return true if key exists in session storage else false.
     */
    public function has(string $key): bool
    {
        return $this->manager->hasItem($key);
    }

    /**
     * Tracks the number of session login attempts.
     *
     * This method increments the number of session login attempts unless reset is requested.
     * The attempt count is stored in session metadata.
     *
     * @param bool $reset If true, resets the attempt count to zero.
     * @return bool Always returns true.
     */
    public function attempt(bool $reset = false): bool 
    {
        $this->setMetadata(
            'attempts', 
            $reset ? 0 : $this->attempts() + 1,
            null,
            false
        );
        return true;
    }

    /**
     * Retrieves the number of session login attempts.
     *
     * @return int Return the number of recorded login attempts.
     */
    public function attempts(): int 
    {
        return (int) $this->getMeta('attempts') ?? 0;
    }

    /**
     * Regenerate session or cookie identifier.
     * 
     * This method delete the old ID associated to the current session, to retain data set to false.
     * 
     * @param bool $clearData Whether to delete the old associated session or not (default: `true`).
     * 
     * @return string|false Return the new generated session Id on success, otherwise false.
     */
    public function regenerate(bool $clearData = true): string|bool
    {
        return $this->manager->regenerateId($clearData);
    }

    /**
     * Initializes PHP session configurations and starts the session if it isn't already started.
     * 
     * This method replaces the default PHP `session_start()`, 
     * with additional configuration and security implementations.
     * 
     * It also capable of starting session in CLI and persist session when use `Terminal::getSystemId()` as session id.
     * 
     * @param string|null $sessionId Optional specify a valid PHP session identifier (e.g,`session_id()`).
     *
     * @return bool Return true if session started successfully, false otherwise.
     * @throws RuntimeException Throws if an invalid session ID is provided or an error is encounter.
     * 
     * @example - Starting a session with a specified session ID:
     * 
     * ```php
     * namespace App;
     * 
     * use Luminova\Sessions\Session;
     * 
     * class Application extends Luminova\Foundation\Core\Application
     * {
     *      protected ?Session $session = null;
     *      protected function onCreate(): void 
     *      {
     *          $this->session = new Session();
     *          $this->session->start('optional_session_id');
     *      }
     * }
     * ```
     */
    public function start(?string $sessionId = null): bool
    {
        $isSession = ($this->manager instanceof SessionManager);
        $status = $isSession ? session_status() : $this->manager->status();

        if ($isSession) {
            if($status === self::DISABLED){
                throw new RuntimeException(
                    'Session Error: Sessions are disabled. Enable the "session" extension in php.ini.'
                );
            }

            if ((bool) ini_get('session.auto_start')) {
                Logger::error(
                    'Session Error: "session.auto_start" is enabled. Disable it so Luminova can manage sessions.'
                );
                return self::$isStarted = false;
            }

            $this->setSaveHandler();
        }elseif($this->handler instanceof SessionHandler){
            throw new RuntimeException(sprintf(
                    'Session manager: "%s" does not support session save handlers. ' .
                    'Use "%s" or remove the handler.',
                    $this->manager::class,
                    SessionManager::class
                ),
                ErrorCode::LOGIC_ERROR
            );
        }

        if ($status === self::ACTIVE) {
            $this->setIpChangeEventListener();
            
            if(self::$isStarted && !PRODUCTION){
                Logger::warning(
                    'Session' . ($isSession ? '' : ' Cookie') .
                    ' already started. Avoid calling $session->start() multiple times.'
                );
            }

            self::$status = self::STARTED;
            return self::$isStarted = true;
        }

        if ($status === self::NONE) {
            if($isSession){
               self::initializeSessionCookie();
            }

            if($this->manager->start($sessionId)){
                $this->setIpChangeEventListener();
                self::$status = self::STARTED;
                return self::$isStarted = true;
            }

            Logger::warning('Failed to start session.', [
                'status' => $status,
                'session_id' => $sessionId
            ]);
        }

        return self::$isStarted = false;
    }

    /**
     * Starts a user's online login session and synchronizes session data.
     * 
     * This method is called once after a successful login to initialize and persist session-related data, 
     * marking the user as logged in. If strict IP validation is enabled, it associates the session 
     * with a specific IP address. Session data is synchronized and stored using the configured 
     * session manager and save handler.
     *
     * @param string|null $ip Optional IP address to associate with login session (default: null). 
     *                  If not provided, the client's current IP address will be used if strict IP validation is enabled.
     * @param array<int,string|int> $roles Optional list of roles to assign (e.g., ['admin', 'editor']).
     *
     * @return bool Returns true if session login was started, otherwise false.
     * @throws LogicException If strict IP validation is disabled and IP address is provided.
     * @throws RuntimeException If an operation is attempted without an active session.
     * @throws InvalidArgumentException If roles are not in a proper indexed list format.
     * 
     * @example - Synchronizing a user login session:
     * ```php
     * namespace App\Controllers\Http;
     * 
     * use Luminova\Base\Controller;
     * 
     * class AdminController extends Controller
     * {
     *      public function loginAction(): int 
     *      {
     *          $username = $this->request->getPost('username');
     *          $password = $this->request->getPost('password');
     * 
     *          // Authenticate login credentials
     *          if($username === 'admin' && $password === 'password'){
     *              // Set client data
     *              $this->app->session->put('username', $username);
     *              $this->app->session->put('email', 'admin@example.com');
     * 
     *              // Save client data
     *              $this->app->session->save();
     * 
     *              // Login client
     *              $this->app->session->login();
     * 
     *              return response()->json(['success' => true]);
     *          }
     * 
     *          return response()->json(['success' => false, 'error' => 'Invalid credentials']);
     *      }
     * }
     * ```
     *
     * > **Note:** If `$strictSessionIp` is enabled, the session automatically associates with 
     * > the client's IP address. If no IP is provided, it will be detected and assigned.
     */
    public function login(?string $ip = null, array $roles = []): bool
    {
        if($this->online()){
            return true;
        }

        $this->restart();
        $metadata = ['ip_changes' => []];

        if(self::$config->strictSessionIp){
            $metadata['ip'] = $ip ?? IP::get();
        }elseif($ip){
            throw new LogicException(sprintf(
                'Invalid Logic: %s %s',
                'The strictSessionIp configuration option is disabled, but an IP address was provided.',
                'To fix the problem, you must set the "App\Config\Session->strictSessionIp" configuration option to true.'
            ));
        }
       
        $this->assertRoles($roles);
        $fingerprint = APP_NAME 
            . ($_SERVER['HTTP_USER_AGENT'] ?? '')
            . ($metadata['ip'] ?? IP::get());

        $metadata['online']      = 'on';
        $metadata['token']       = bin2hex(random_bytes(36));
        $metadata['timestamp']   = Time::now()->getTimestamp();
        $metadata['agent']       = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $metadata['fingerprint'] = hash('sha256', $fingerprint);
        $metadata['attempts']    = 0;
        $metadata['roles']       = $roles;
        $metadata['last_activity']  = time();

        $this->manager->setItem(self::METADATA, $metadata);
        return $this->online();
    }

    /**
     * Logs in the user by synchronizing session login metadata.
     *
     * This method serves as an alias for `login()`, which initializes and 
     * maintains the session state after a successful login. If IP validation is enabled, 
     * the session will be linked to the provided IP address.
     *
     * @param string|null $ip Optional IP address to associate with the session.
     * @param array<int,string|int> $roles Optional list of roles to assign (e.g., ['admin', 'editor']).
     * 
     * @return bool Returns true if the session was successfully started, otherwise false.
     * 
     * @throws LogicException If strict IP validation is disabled and IP address is provided.
     * @throws RuntimeException If an operation is attempted without an active session.
     * @throws InvalidArgumentException If roles are not in a proper indexed list format.
     *
     * @see login()
     */
    public function synchronize(?string $ip = null, array $roles = []): bool
    {
        return $this->login($ip, $roles);
    }

    /**
     * Assign roles to the current session user.
     *
     * This method stores the specified roles in the session metadata,
     * allowing you to associate access levels or permissions with the session.
     *
     * @param array<int,string|int> $roles A list of roles (e.g., ['admin', 'editor']).
     *
     * @return self Returns the current Session instance for method chaining.
     * @throws InvalidArgumentException If roles are not in a proper indexed list format.
     * 
     * @see guard() - Guard access by user roles.
     * @see getRoles() - Get user roles.
     * @since 3.6.8
     *
     * @example - Example:
     * ```php
     * $session->roles(['admin', 'editor']);
     * ```
     */
    public function roles(array $roles): self 
    {
        $this->assertRoles($roles);
        $this->setMetadata('roles', $roles);
        
        return $this;
    }

    /**
     * Checks if the current session user has the specified roles.
     *
     * This method guards routes or logic by evaluating the session roles against the required ones.
     * It supports multiple modes:
     *
     * - `GUARD_ANY` (default): At least one required role must exist in user roles.
     * - `GUARD_ALL`: All required roles must be present in user roles (but extras allowed).
     * - `GUARD_EXACT`: Exact match — all and only the specified roles must exist.
     * - `GUARD_NONE`: None of the given roles should be present (e.g., guest access only).
     *
     * Returns `true` if access is denied (guard failed), and `false` if access is granted.
     * This mimics Swift-style early-exit.
     *
     * @param array<int,string|int> $roles A list of required role(s) to validate against the session.
     * @param int $mode Guard match mode. One of: (`GUARD_ANY`, `GUARD_ALL`, `GUARD_EXACT`, `GUARD_NONE`).
     * @param (callable(array $roles, array $subscriptions):void)|null $onDenied Optional handler to call if access is denied.
     *
     * @return bool Returns `true` if access is denied, `false` if access is allowed.
     * @throws InvalidArgumentException If an invalid mode was provided or if roles are not in a proper indexed list format.
     *
     * @see roles() To assign user roles.
     * @see getRoles() To retrieve current user roles.
     * 
     * @since 3.6.8
     *
     * @example - Allow access if user has any of the roles:
     * ```php
     * if (!$session->guard(['admin', 'editor'])) {
     *     // access granted
     * }
     * ```
     *
     * @example - Require all roles:
     * ```php
     * if (!$session->guard(['admin', 'editor'], Session::GUARD_ALL)) {
     *     // access granted
     * }
     * ```
     *
     * @example - Require exact match:
     * ```php
     * if (!$session->guard(['admin', 'editor'], Session::GUARD_EXACT)) {
     *     // access granted
     * }
     * ```
     *
     * @example - Deny access if user has any of the listed roles:
     * ```php
     * if ($session->guard(['banned', 'suspended'], Session::GUARD_NONE)) {
     *     // access denied
     * }
     * ```
     *
     * @example - With custom failure handler:
     * ```php
     * $session->guard(['admin'], Session::GUARD_ANY, function(array $expected, array $roles): void {
     *     throw new AccessDeniedException('Not allowed.');
     * });
     * ```
     */
    public function guard(array $roles, int $mode = self::GUARD_ANY, ?callable $onDenied = null): bool
    {
        if ($roles === []) {
            return false;
        }

        $this->assertRoles($roles);
        $passed = false;
        $subscriptions = [];

        if($this->online()){
            $subscriptions = $this->getRoles();
            $passed = match ($mode) {
                self::GUARD_EXACT => (
                    count($roles) === count($subscriptions)
                    && array_diff($roles, $subscriptions) === []
                    && array_diff($subscriptions, $roles) === []
                ),
                self::GUARD_ALL  => array_diff($roles, $subscriptions) === [],
                self::GUARD_NONE => array_intersect($roles, $subscriptions) === [],
                self::GUARD_ANY  => array_intersect($roles, $subscriptions) !== [],
                default => throw new InvalidArgumentException(sprintf(
                    'Invalid guard mode "%s" provided. Expected one of: GUARD_ANY (%d), GUARD_ALL (%d), GUARD_EXACT (%d), GUARD_NONE (%d).',
                    $mode,
                    self::GUARD_ANY,
                    self::GUARD_ALL,
                    self::GUARD_EXACT,
                    self::GUARD_NONE
                )),
            };
        }

        if (!$passed && $onDenied && is_callable($onDenied)) {
            $onDenied($roles, $subscriptions);
        }

        return !$passed;
    }

    /**
     * Terminates the user's online session and clears session metadata.
     *
     * This method removes only the session's online status and metadata, ensuring the user is logged out.
     * It does not delete any stored session data but forces the application to recognize the session as inactive.
     * If strict session IP validation is enabled, the associated IP address will also be removed.
     *
     * @return bool Returns true if session was terminated, otherwise false.
     */
    public function terminate(): bool
    {
        $this->restart(false);
        $this->manager->setItem(self::METADATA, []);
        return !$this->online();
    }

    /**
     * Logs out the user by terminating the session login metadata.
     *
     * This method acts as an alias for `terminate()`, ensuring the session metadata is cleared 
     * and marking the user as logged out. The session data itself remains intact, but the 
     * session state will no longer be recognized as active.
     *
     * @return bool Returns true if the session was successfully terminated, otherwise false.
     *
     * @see terminate()
     */
    public function logout(): bool
    {
        return $this->terminate();
    }

    /**
     * Deletes session data stored in the session table `$tableIndex`, based on the active session configuration  
     * or the table set via `setTable` in the session manager.
     *
     * If `$allData` is `true`, all session and cookie data for the application will be cleared.
     *
     * @param bool $allData Whether to destroy clear all application session or cookie data, based on session manager in use (default: `false`).
     *
     * @return bool Returns `true` if the session data was successfully cleared; `false` otherwise.
     * 
     */
    public function destroy(bool $allData = false): bool 
    {
        $this->restart(false);
        return $this->manager->destroy($allData);
    }

    /**
     * Checks if the user's IP address has changed since the last login session.
     *
     * @param string|null $storage Optional session storage name to perform the check.
     * 
     * @return bool Returns false if the user's IP address matches the session login IP, otherwise returns true.
     */
    public function ipChanged(?string $storage = null): bool
    {
        $default = $this->getStorage();
        $changed = false;

        if($storage && $storage !== $default){
            $this->setStorage($storage);
        }

        if($this->online()){
            $onlineIp = $this->getIp();
            $changed = (!empty($onlineIp) && !IP::equals($onlineIp));
        }

        if($storage && $storage !== $default){
            $this->setStorage($default);
        }

        if($changed){
            $this->setMetadata('ip_changes', array_merge($this->getIpChanges(), [
                IP::get()
            ]));
        }
        
        return $changed;
    }

    /**
     * IP Address Change Listener to detect and respond to user IP changes.
     *
     * This method monitors the user's IP address during a session and `$strictSessionIp` is enabled. If the IP address changes, 
     * the specified callback is executed. Based on the callback's return value:
     * - `true`: The session is terminate the client login session.
     * - `false`: The session remains active, allowing manual handling what happens on IP change event.
     *
     * @param (callable(static $instance, string $lastIp, array $ipChanges):bool) $onChange A callback function to handle the IP change event. 
     *                           The function receives the `Session` instance, the previous IP, 
     *                           and array list of IP changes as arguments.
     * 
     * @return self Returns the current `Session` instance.
     *
     * @example - Session IP address change event:
     * 
     * ```php
     * namespace App;
     * 
     * use Luminova\Sessions\Session;
     * 
     * class Application extends Luminova\Foundation\Core\Application
     * {
     *     protected ?Session $session = null;
     * 
     *     protected function onCreate(): void 
     *     {
     *         $this->session = new Session();
     *         $this->session->start();
     * 
     *         $this->session->onIpChanged(function (Session $instance, string $lastIp, array $ipChanges): bool {
     *             // Handle the IP address change event manually
     *             return true; // Terminate the session, or return false to keep it or indication that it been handled
     *         });
     *     }
     * }
     * ```
     */
    public function onIpChanged(callable $onChange): self
    {
        $this->onIpChange = $onChange;
        return $this;
    }

    /**
     * Handles IP address change events during a user session.
     *
     * This method checks if the user's IP address has changed since the last login 
     * and takes appropriate actions based on the configuration and callback provided:
     * - If `strictSessionIp` is enabled and the IP address has changed:
     *   - Executes the `onIpChange` callback if defined.
     *   - If the callback returns `true` or no callback is defined, the session is cleared.
     * 
     * @return void
     */
    private function setIpChangeEventListener(): void
    {
        if(self::$config->strictSessionIp && $this->ipChanged()){
            if( 
                $this->onIpChange !== null && 
                ($this->onIpChange)(
                    $this, 
                    $this->getIp(),
                    $this->getIpChanges()
                )
            ){
                $this->terminate();
            }

            if($this->onIpChange === null){
                $this->terminate();
            }
        }
    }

    /**
     * Configure PHP session settings based on the session configuration.
     *
     * This method sets various PHP session ini settings according to the 
     * properties defined in the `SessionConfig` class. It handles settings 
     * such as session expiration, save path, cookie usage, and strict mode.
     *
     * @return void
     * @throws RuntimeException If the specified session save path is not writable.
     * 
     * @example - Example:
     * ```php
     * Session::initializeSessionCookie();
     * session_start();
     * ```
     */
    public static function initializeSessionCookie(): void
    {
        self::$config ??= new SessionConfig();

        if (self::$config->expiration > 0) {
            ini_set('session.gc_maxlifetime', (string) self::$config->expiration);
        }

        if (self::$config->savePath) {
            if(!is_writable(self::$config->savePath)){
                throw new RuntimeException(sprintf(
                    'The specified session save path "%s" is not writable. Please ensure the directory exists and has appropriate permissions.',
                    self::$config->savePath
                ));
            }

            ini_set('session.save_path', self::$config->savePath);
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.lazy_write', '1');
        ini_set('session.use_trans_sid', '0');

        if (PHP_SAPI === 'cli' || Luminova::isCommand()) {
            ini_set('session.use_cookies', '0');
            ini_set('session.use_only_cookies', '0');
            ini_set('session.cache_limiter', '');
            return;
        }

        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');

        if (self::$config->cookieName) {
            session_name(self::$config->cookieName);
        }

        $sameSite = in_array(self::$config->sameSite, ['Lax', 'Strict', 'None'], true)
            ? self::$config->sameSite
            : 'Lax';

        session_set_cookie_params([
            'lifetime' => self::$config->expiration,
            'path'     => self::$config->sessionPath,
            'domain'   => self::$config->sessionDomain,
            'secure'   => true,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    }

    /**
     * Restarts the session if necessary.
     *
     * If the session is committed, this method ensures that a new session 
     * is started when required. If `assert` is enabled, it throws an exception 
     * if an operation is attempted without an active session.
     *
     * @param bool $assert Whether to enforce session start validation.
     *
     * @throws RuntimeException If an operation is attempted without an active session.
     */
    private function restart(bool $assert = true): void 
    {
        if (self::$status === self::STARTED) {
            return;
        }

        if (self::$status === self::COMMITTED) {
            if ($this->is(self::NONE)) {
                $this->start();
            }
            
            return;
        }

        if ($assert && self::$status === self::INACTIVE) {
            throw new RuntimeException(
                'Session Error: A session must be started before performing read/write operations. ' .
                'Call "$session->start()" first.'
            );
        }
    }

    /**
     * Enable session storage handler.
     */
    private function setSaveHandler(): void 
    {
        if ($this->handler instanceof SessionHandler) {
            if(self::$config instanceof SessionConfig){
                $this->handler->setConfig(self::$config);
            }
            
            session_set_save_handler($this->handler, true);
        }
    }

    /**
     * Set a metadata key-value pair for the current session.
     *
     * Skips saving if `online` is true and the session is not marked as "online".
     *
     * @param string      $key      Metadata key.
     * @param mixed       $value    Metadata value.
     * @param string|null $storage  Optional custom storage key.
     * @param bool        $whenOnline   Whether to enforce that session is "online".
     */
    private function setMetadata(string $key, mixed $value, ?string $storage = null, bool $whenOnline = true): void 
    {
        $metadata = $this->getMetadata($storage);

        if ($whenOnline) {
            $isOnline = ($metadata['online'] ?? 'off') === 'on';
            $hasToken = isset($metadata['token']);

            if ($metadata === [] || !$isOnline || !$hasToken) {
                return;
            }
        }

        $metadata[$key] = $value;
        $this->manager->setItem(self::METADATA, $metadata, $storage);
    }

    /**
     * Stores metadata for session last access activity.
     *
     * @param string|null $storage Optional storage name.
     * 
     * @return void
     */
    private function setActivity(?string $storage = null): void 
    {
        $this->setMetadata('last_activity', time(), $storage);
    }

    /**
     * Assert that the given roles array is a non-empty, ordered list.
     *
     * @param array<int, string|int> $roles The roles to validate.
     *
     * @throws InvalidArgumentException If roles are not in a proper indexed list format.
     */
    private static function assertRoles(array $roles): void
    {
        if ($roles === []) {
            return;
        }

        if (!array_is_list($roles)) {
            throw new InvalidArgumentException(
                'Roles must be a sequential indexed array (a list) of strings or integers.'
            );
        }
    }
}