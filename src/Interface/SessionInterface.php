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

use Luminova\Base\SessionHandler;
use \App\Config\Session as SessionConfig;
use Luminova\Interface\SessionManagerInterface;
use Luminova\Exceptions\{LogicException, RuntimeException, InvalidArgumentException};

interface SessionInterface 
{
    /**
     * Create a new session instance from the specified storage.
     *
     * The returned instance is configured with the given session storage and is
     * independent of the current Session instance. Use {@see self::setStorage()}
     * when changing the storage of an existing instance.
     *
     * @param string $storage The session storage name.
     * @param SessionManagerInterface|null $manager Optional session manager.
     * @param SessionConfig|null $config Optional session configuration.
     *
     * @return static A new Session instance configured for the specified storage.
     */
    public static function from(
        string $storage,
        ?SessionManagerInterface $manager = null,
        ?SessionConfig $config = null
    ): static;

    /**
     * Retrieves the current session storage manager instance.
     * 
     * This method returns the session manager instance responsible for handling 
     * session data, either: 
     * - {@see Luminova\Sessions\Managers\Cookie}
     * - {@see Luminova\Sessions\Managers\Session}
     *
     * @return SessionManagerInterface|null Return the current session manager instance, or `null` if not set.
     */
    public function getManager(): ?SessionManagerInterface;

    /**
     * Retrieves the current session storage name.
     * 
     * This method returns the current storage name used to store session data.
     * 
     * @return string Return the current session storage name.
     */
    public function getStorage(): string;

    /**
     * Retrieves the session cookie name.
     * 
     * This method returns the name of the session cookie used for session management.
     * If a custom cookie name is set in the configuration, it will be returned; 
     * otherwise, the default PHP session name is used.
     * 
     * @return string Return the session cookie name.
     */
    public function getName(): string;

    /**
     * Retrieves all session data in the specified format.
     * 
     * @param bool $asObject If true return data as `object` otherwise `array` (default: `array`).
     * 
     * @return array|object Return the stored session data in the requested format.
     */
    public function getResult(bool $asObject = false): array|object;

    /**
     * Retrieves a value from the session storage.
     *
     * @param string $name The item name to retrieve.
     * @param mixed $default The default value returned if the key does not exist.
     * 
     * @return mixed Returns the retrieved session data or the default value if not found.
     */
    public function get(string $name, mixed $default = null): mixed;

    /** 
     * Retrieves the PHP session identifier.
     * 
     * This method returns the active PHP session ID, which uniquely identifies 
     * the session within the server.
     * 
     * @return string|null Return the current PHP session identifier or null if failed.
     */
    public function getId(): ?string;

    /**
     * Retrieves session login metadata key value from session storage.
     *
     * @param string $name The metadata name to retrieve.
     * 
     * @return mixed Return the metadata value or null if not exist.
     */
    public function getMeta(string $name): mixed;

    /**
     * Retrieves session login metadata information from session storage.
     * 
     * @return array<string,mixed> Return an associative array containing session metadata.
     */
    public function getMetadata(): array;

    /**
     * Retrieve all session user's data excluding internal metadata.
     *
     * Returns the session items stored in the specified storage. Internal session
     * metadata is excluded from the returned data.
     * 
     * @return array<string,mixed> Return an associative array containing session metadata.
     */
    public function getAttributes(): array;

    /**
     * Retrieves a list of IP address changes during the session.
     *
     * This method returns an array of previously recorded IP addresses if they changed 
     * during the session lifetime.
     *
     * @return array Return the list of IP address changes.
     */
    public function getIpAddresses(): array;

    /**
     * Retrieves the IP address associated with the session.
     *
     * @return string|null Return the stored IP address or null if not set.
     */
    public function getIp(): ?string;

    /**
     * Retrieves the user agent associated with the session.
     *
     * This method returns the browser or client identifier used when the session was created.
     *
     * @return string|null Return the user agent string or null if not set.
     */
    public function getUserAgent(): ?string;

    /** 
     * Retrieves the client's online session login token.
     * 
     * This method returns a randomly generated token when `login()` is called.
     * The returned token can be used to track the online session state, 
     * validate session integrity or prevent session fixation attacks.
     * 
     * @return string|null Return sha256 hashed login session token, or `null` if not logged in.
     */
    public function getToken(): ?string;

    /** 
     * Retrieves the client login session date and time in ISO 8601 format.
     * 
     * The session datetime is generated automatically when `login()` is called, 
     * marking the moment the session login was established.
     * 
     * @return string Return the session login datetime in ISO 8601 format, or `null` if not logged in.
     */
    public function getDatetime(): ?string;

    /**
     * Retrieves the client login session creation timestamp.
     * 
     * The session timestamp is generated automatically when `login()` is called, 
     * marking the moment the session login was established.
     *
     * @return int Return he Unix timestamp when the session was created.
     */
    public function getTimestamp(): int;

    /**
     * Retrieves the session expiration timestamp.
     *
     * This method returns the Unix timestamp at which the session is set to expire.
     *
     * @return int Return the expiration timestamp or 0 if not set.
     */
    public function getExpiration(): int;

    /**
     * Get the timestamp of the last session update.
     *
     * @return int|null The Unix timestamp of the last session update, or `null`
     *     if no update timestamp is recorded.
     *
     * @see self::touch()     To update last activity.
     * @see self::isIdleFor() To calculate last activity.
     */
    public function getUpdatedAt(): ?int;

    /**
     * Get the timestamp of the last login attempt.
     *
     * @return int|null The Unix timestamp of the last recorded login attempt,
     *     or `null` if no login attempt has been recorded.
     *
     * @see self::isLoginLocked()
     */
    public function getLastAttempt(): ?int;

    /**
     * Get the timestamps of recent login attempts.
     *
     * @return array<int,float> Unix timestamps recorded for recent login attempts.
     *
     * @see self::attempt()
     * @see self::isRapidAttempts()
     */
    public function getAttemptTimes(): array;

    /**
     * Get the list of roles assigned to the current session user.
     *
     * Retrieves roles from the session metadata. Returns an empty array if no roles are set.
     *
     * @return array<int,string|int> Return a list of assigned roles or an empty array if none.
     * 
     * @see self::setRoles() - Set user roles.
     * @see self::onRoleGuard() - Guard access by user roles.
     * @see self::inRoles() - Check if user is in roles.
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
    public function getRoles(): array;

    /**
     * Sets the session save handler responsible for managing session storage.
     * 
     * This method allows specifying a custom session save handler, such as a 
     * database array-handler,or filesystem-based handler, to control how session data is stored and retrieved.
     * 
     * Supported session save handlers:
     * - {@see Luminova\Sessions\Handlers\Database}: Stores session data in a database.
     * - {@see Luminova\Sessions\Handlers\Filesystem}: Saves session data in files.
     * - {@see Luminova\Sessions\Handlers\ArrayHandler}: Stores session data temporarily in an array.
     *
     * @param SessionHandler $handler The session save handler instance.
     *
     * @return self Returns the instance of session class.
     *
     * @see https://luminova.ng/docs/edit/0.0.0/sessions/database-handler
     * @see https://luminova.ng/docs/edit/0.0.0/sessions/filesystem-handler
     * @see https://luminova.ng/docs/edit/0.0.0/base/session-handler
     */
    public function setHandler(SessionHandler $handler): self;

    /**
     * Sets the session manager that controls the underlying storage engine for session data.
     *
     * Unlike a session handler `setHandler()`, which is only applicable when using `Luminova\Sessions\Managers\Session`, 
     * this method allows specifying a session manager to determine where session data is stored.
     *
     * Supported session managers:
     * - {@see Luminova\Sessions\Managers\Cookie}: Stores session data securely in client-side cookies.
     * - {@see Luminova\Sessions\Managers\Session}: Uses PHP's default `$_SESSION` storage.
     *
     * @param SessionManagerInterface $manager The session manager instance to set.
     * 
     * @return self Returns the instance of session class.
     */

    public function setManager(SessionManagerInterface $manager): self;

    /**
     * Set the storage name used to store and retrieve session data.
     *
     * @param string $storage The storage name to use.
     *
     * @return self Return the current session instance.
     * @throws InvalidArgumentException If the storage name is invalid.
     */
    public function setStorage(string $storage): self;

    /**
     * Set the storage namespace.
     *
     * Unlike {@see setStorage()}, this allows data to be stored under a
     * different namespace within the same storage.
     *
     * @param string|null $namespace The namespace to use, or null to use `default`.
     *
     * @return self Return the current session instance.
     * @throws InvalidArgumentException If the namespace is invalid.
     */
    public function setNamespace(?string $namespace): self;

    /**
     * Set roles for the current session user.
     *
     * Replaces the roles currently assigned to the session with the specified
     * roles. The roles are stored in the session metadata when the session is
     * online and kept in memory otherwise.
     *
     * @param array<int, string|int> $roles Roles to assign to the session user.
     *
     * @return self Returns the current Session instance for method chaining.
     * @throws InvalidArgumentException If the roles are not a valid indexed list.
     *
     * @see self::assignRoles() To add roles to the existing role list.
     * @see self::inRoles() To check the user's roles.
     * @see self::getRoles() To retrieve the user's roles.
     *
     * @since 3.6.8
     *
     * @example - Example:
     * ```php
     * $session->setRoles(['admin', 'editor']);
     * ```
     */
    public function setRoles(array $roles): self;

    /**
     * Retrieve all session data.
     *
     * Returns the session items stored in the specified storage. Including internal session
     * metadata
     *
     * @return array<string,mixed> Session data excluding internal metadata.
     */
    public function all(): array;

    /**
     * Update the session's last activity timestamp.
     *
     * Refreshes the session activity timestamp without changing other session
     * data. When no timestamp is provided, the current Unix timestamp is used.
     *
     * @param int|null $timestamp Optional Unix timestamp to record (default: current time).
     *
     * @return self Returns the instance of session class.
     */
    public function touch(?int $timestamp = null): self;

    /**
     * Set a value in the session storage.
     *
     * Stores the value under the specified key, replacing any existing value.
     * The change is persisted immediately and does not require a subsequent
     * call to {@see self::save()}.
     *
     * @param string $name The session data key.
     * @param mixed $value The value to store.
     *
     * @return self The current Session instance for method chaining.
     *
     * @throws RuntimeException If the session is not active.
     */
    public function set(string $name, mixed $value): self;

    /**
     * Add a value to the session storage if the key does not already exist.
     *
     * If the key already exists, its existing value is preserved and `$status`
     * is set to `false`. Otherwise, the value is stored and `$status` is set
     * to `true`.
     *
     * @param string $name The session data key.
     * @param mixed $value The value to store.
     * @param bool &$status Indicates whether the value was added successfully.
     *
     * @return self The current Session instance for method chaining.
     */
    public function add(string $name, mixed $value, bool &$status = false): self;

    /**
     * Queue a value for session storage.
     *
     * Stores the value in the pending data stack without immediately persisting
     * it. Queued values are persisted when {@see self::save()} is called.
     * Existing values with the same key are replaced in the pending stack.
     *
     * @param string $name The session data key.
     * @param mixed $value The value to queue.
     *
     * @return self The current Session instance for method chaining.
     *
     * @see self::save()
     * @see self::dequeue() To clear queued data.
     */
    public function put(string $name, mixed $value): self;

    /**
     * Persist queued session data.
     *
     * Saves all values queued with {@see self::put()} to current session storage and
     * clears the pending data stack after a successful save.
     *
     * @return bool `true` if the queued data was successfully saved, otherwise `false`.
     * @throws RuntimeException If the session is not active.
     *
     * @see self::put()
     * @see self::close()
     */
    public function save(): bool;

    /**
     * Commits the current session data.
     *
     * This method finalizes the session write process by committing any changes 
     * made to the session data. Once committed, the session is considered closed 
     * and cannot be modified until restarted.
     * 
     * @return bool Return true on success, false if failed.
     * 
     * @see self::start() - To start the session.
     */
    public function close(): bool;


    /**
     * Start the session manager.
     *
     * Initializes and starts the underlying native or cookie-based session.
     * Native PHP session configuration and save handlers are applied only when
     * the configured manager supports PHP session management.
     *
     * @param string|null $sessionId Optional session ID to resume when starting session.
     *
     * @return bool `true` when the session is started or already active,  otherwise `false`.
     * @throws RuntimeException If the session extension is disabled or an
     *                          incompatible session save handler is configured.
     * 
     * @see Terminal::getSystemId() To start a session in CLI using system ID.
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
    public function start(?string $sessionId = null): bool;

    /**
     * Start the user's login session.
     *
     * Marks the current session as logged in and stores the login state and
     * associated metadata. When strict session IP validation is enabled, the
     * session is bound to the specified IP address or, when omitted, the
     * client's current IP address.
     *
     * @param string|null $ip Optional IP address to bind to the login session.
     *     When omitted and strict session IP validation is enabled, the client's
     *     current IP address is used.
     *
     * @return bool `true` if the login session was successfully started,
     *     otherwise `false`.
     *
     * @throws LogicException If an IP address is provided while strict session
     *     IP validation is disabled.
     * @throws RuntimeException If the session is not active.
     *
     * @see self::logout() To log out the current session user.
     * @see self::attempt() To record or reset a login attempt.
     * @see self::attempts() To retrieve the number of login attempts.
     * @see self::isOnline() To check whether the user is logged in.
     * @see self::isLoginLocked()
     *
     * @example - Example:
     * ```php
     * if (!$auth->isLoginAuthenticated($username, $password)) {
     *     if ($session->attempts() >= 3) {
     *         return response()->json([
     *             'success' => false,
     *             'message' => 'Too many attempts',
     *         ]);
     *     }
     *
     *     $session->attempt();
     *
     *     return response()->json([
     *         'success' => false,
     *         'message' => 'Login failed',
     *     ]);
     * }
     *
     * $session
     *     ->put('username', $username)
     *     ->put('email', 'admin@example.com')
     *     ->setRoles(['admin'])
     *     ->login();
     *
     * return response()->json(['success' => true]);
     * ```
     *
     * @note When strict session IP validation is enabled, the session is bound
     *     to the client's current IP address when `$ip` is omitted.
     */
    public function login(?string $ip = null): bool;

    /**
     * Record or reset a session login attempt count.
     *
     * Increments the number of recorded login attempts, or resets the count to
     * zero when `$reset` is `true`.
     *
     * @param bool $reset Whether to reset the login attempt count instead of
     *     incrementing it.
     *
     * @return bool `true` when the attempt count is updated successfully
     * 
     * @see self::isLoginLocked().
     */
    public function attempt(bool $reset = false): bool;

    /**
     * Retrieve the number of recorded session login attempts.
     *
     * @return int The current number of recorded login attempts.
     * 
     * @see self::isLoginLocked()
     */
    public function attempts(): int;

    /**
     * Logs out the current session user.
     *
     * Clears the login state and associated login metadata while preserving
     * the session and its other stored data. The session remains available
     * for subsequent use.
     *
     * @return bool `true` if the user is logged out, otherwise `false`.
     *
     * @see self::login() To log in a user.
     * @see self::destroy() To destroy this session.
     * @see self::close() To close the active session without logging out.
     */
    public function logout(): bool;

    /**
     * Destroys the current session managed by this instance.
     *
     * Logs out the current user, clears the session data, regenerates the
     * global session identifier, and marks this session as inactive.
     *
     * @return bool `true` if the session was destroyed successfully,
     *     otherwise `false`.
     *
     * @see self::logout() To log out while preserving the session.
     * @see self::close() To close the active session without destroying it.
     */
    public function destroy(): bool;

    /**
     * Destroy the current PHP session and expire its session cookie.
     *
     * This method operates on the entire native PHP session and does not
     * preserve session data created outside this session manager.
     *
     * @return bool True if the session and cookie were successfully destroyed,
     *              otherwise false.
     */
    public static function destroyNative(): bool;

    /**
     * Initialize PHP session configuration.
     *
     * Applies the supplied session settings to PHP's session subsystem.
     * This method must be called before starting a session.
     *
     * @param SessionConfig|object|array{
     *     expiration:int,
     *     savePath:string,
     *     useStrictMode:bool,
     *     cookieName:string,
     *     sameSite:string,
     *     sessionPath:string,
     *     sessionDomain:string
     * } $config Session configuration.
     *
     * @return bool Return true if configuration was applied, otherwise false.
     * @throws RuntimeException If the configured save path is invalid or
     *                          session cookie parameters cannot be configured.
     * 
     * @example - Example:
     * ```php
     * Session::configure(...);
     * session_start();
     * ```
     */
    public static function configure(object|array $config): bool;

    /**
     * Clears all stacked session data without saving.
     *
     * This method removes all temporarily stored session data before it is saved. 
     * Use it if you want to discard changes before calling `save()`.
     *
     * @return true Always return true.
     * @see self::put()
     */
    public function dequeue(): bool;

    /**
     * Checks if the current session or cookie status matches the given status.
     *
     * @param int $status The session status to check.
     *         - `Session::DISABLED` (PHP_SESSION_DISABLED): Sessions are disabled.
     *         - `Session::NONE` (PHP_SESSION_NONE): Sessions or cookie are enabled but no session exists.
     *         - `Session::ACTIVE` (PHP_SESSION_ACTIVE): A session or cookie is currently active.
     *
     * @return bool Returns `true` if the current session status matches the given status, otherwise `false`.
     */
    public function is(int $status = 2): bool;

    /**
     * Checks if the session has started.
     * 
     * This method will return true after calling `start` session method.
     * 
     * @return bool Returns `true` if session has stated, otherwise `false`.
     */
    public function isStarted(): bool;

    /** 
     * Checks if the session user is currently online.
     * 
     * This method verifies whether the {@see self::login()} method has been called,
     * meaning the session user is considered online.
     * 
     * @return bool Returns true if the session user is online, false otherwise.
     */
    public function isOnline(): bool;

    /** 
     * Checks if the session is still valid based on elapsed time.
     * 
     * This method determines whether the session has expired based on the last 
     * recorded online time. By default, a session is considered expired after 
     * 3600 seconds (1 hour).
     * 
     * @param int $seconds The time threshold in seconds before the session is considered expired (default: 3600).
     * 
     * @return bool Returns true if the session is still valid, false if it has expired.
     */
    public function isExpired(int $seconds = 3600): bool;

    /**
     * Determine whether strict session ID mode is enabled.
     *
     * Checks if `$config->useStrictMode`, strict mode is enabled in session
     * configuration.
     *
     * @return bool `true` if strict session ID mode is enabled, otherwise `false`.
     */
    public function isStrictMode(): bool;

    /**
     * Determine whether the session is locked to its originating IP.
     *
     * @return bool `true` if the session is restricted to its originating IP,
     *              otherwise `false`.
     */
    public function isIpLocked(): bool;

    /**
     * Determine whether the current IP matches the session IP.
     *
     * Returns `true` only when IP locking is enabled and the current IP has not
     * changed since the session was established.
     *
     * @return bool `true` if the session IP is valid, otherwise `false`.
     */
    public function isIpMatch(): bool;
    
    /**
     * Determine whether the session has been inactive for the specified duration.
     *
     * @param int $seconds Minimum number of seconds since the last activity.
     *
     * @return bool `true` if the last activity occurred at least `$seconds`
     *     seconds ago, otherwise `false`.
     */
    public function isIdleFor(int $seconds): bool;

    /**
     * Determine whether login attempts are currently locked.
     *
     * The session is considered locked when the recorded login attempts reach
     * or exceed the maximum allowed attempts and the lockout period has not
     * elapsed since the last activity.
     *
     * @param int $seconds Number of seconds the login remains locked after
     *     the maximum attempt count is reached.
     * @param int $maxAttempts Maximum number of failed login attempts allowed
     *     before the session is locked.
     *
     * @return bool `true` if login attempts are currently locked, otherwise `false`.
     * 
     * @see self::isRapidAttempts()
     */
    public function isLoginLocked(
        int $seconds = 300,
        int $maxAttempts = 3
    ): bool;

    /**
     * Determine whether login attempts are occurring at an unusually high rate.
     *
     * Checks the recent login attempt timestamps and returns `true` when the
     * number of attempts within the specified time window exceeds the allowed
     * threshold.
     *
     * @param int $window Time window in seconds.
     * @param int $maxAttempts Maximum allowed attempts within the window.
     *
     * @return bool `true` if the recent attempt rate is unusually high, otherwise `false`.
     * 
     * @see self::isLoginLocked()
     */
    public function isRapidAttempts(
        int $window = 10,
        int $maxAttempts = 5
    ): bool;

    /**
     * Check whether the current session user satisfies the specified roles.
     *
     * Evaluates the user's assigned roles against the required roles using the
     * specified matching mode. Returns `false` when the session is offline or
     * when the user does not satisfy the role requirements.
     *
     * Supported matching modes:
     *
     * - {@see self::GUARD_ANY}: At least one required role must be assigned.
     * - {@see self::GUARD_ALL}: All required roles must be assigned.
     * - {@see self::GUARD_EXACT}: The assigned roles must exactly match the
     *   required roles.
     * - {@see self::GUARD_NONE}: None of the required roles may be assigned.
     *
     * @param array<int,string|int>|null $roles Roles to check against the current user.
     * @param int $mode Role matching mode.
     *
     * @return bool `true` if the user satisfies the role requirements, otherwise `false`.
     * @throws InvalidArgumentException If the role list is invalid or the
     *     specified matching mode is not supported.
     *
     * @see self::setRoles() To set required validation roles for current session user.
     * @see self::roles() To assign roles to the current session user.
     * @see self::getRoles() To retrieve the user's assigned roles.
     * 
     * @example - Allow access if user has any of the roles:
     * ```php
     * if (!$session->inRoles(['admin', 'editor'])) {
     *     // access granted
     * }
     * ```
     *
     * @example - Require all roles:
     * ```php
     * if (!$session->inRoles(['admin', 'editor'], Session::GUARD_ALL)) {
     *     // access granted
     * }
     * ```
     *
     * @example - Require exact match:
     * ```php
     * if (!$session->inRoles(['admin', 'editor'], Session::GUARD_EXACT)) {
     *     // access granted
     * }
     * ```
     *
     * @example - Deny access if user has any of the listed roles:
     * ```php
     * if ($session->inRoles(['banned', 'suspended'], Session::GUARD_NONE)) {
     *     // access denied
     * }
     * ```
     */
    public function inRoles(?array $roles, int $mode = 0): bool;

    /**
     * Enable or disable strict session ID validation.
     *
     * @param bool $enable Whether to enable strict session ID validation.
     *
     * @return self Return the session instance for method chaining.
     */
    public function useStrictMode(bool $enable = true): self;

    /** 
     * Remove an item from the session storage.
     * 
     * @param string $name The item name to remove.
     * 
     * @return self Returns the instance of session class.
     * @throws RuntimeException If an operation is attempted without an active session.
     */
    public function remove(string $name): self;

    /** 
     * Clear all data from current session storage.
     * 
     * @return self Returns the instance of session class.
     */
    public function clear(): self;

    /** 
     * Check if item exists in session storage.
     * 
     * @param string $name The item name to check.
     * 
     * @return bool Return true if key exists in session storage else false.
     */
    public function has(string $name): bool;

    /**
     * Regenerate the session or cookie identifier.
     *
     * Generates a new identifier and optionally deletes the data associated
     * with the previous identifier.
     *
     * @param bool $clearSessionData Whether to delete the data associated with
     *                               the previous identifier (default: `false`).
     *
     * @return string|false The new session identifier on success, or `false`
     *                      if regeneration fails.
     */
    public function regenerate(bool $clearSessionData = false): string|bool;

    /**
     * Ensure the session is started.
     *
     * Returns immediately when the session is already active; otherwise, attempts
     * to start the session.
     *
     * @return bool `true` if the session is already active or was successfully
     *     started, otherwise `false`.
     */
    public function restartIfNeeded(): bool;

    /**
     * Add roles to the current session user's existing roles.
     *
     * Merges the specified roles with the user's existing roles and removes
     * duplicates. Existing roles are preserved.
     *
     * @param array<int,string|int> $roles Roles to add to the session user.
     *
     * @return self Returns the current Session instance for method chaining.
     * @throws InvalidArgumentException If the roles are not a valid indexed list.
     *
     * @see self::setRoles() To replace the current roles.
     * @see self::inRoles() To check the user's roles.
     * @see self::getRoles() To retrieve the user's roles.
     *
     * @since 3.6.8
     */
    public function assignRoles(array $roles): self;

    /**
     * Revoke roles from the current session user.
     *
     * Removes the specified roles from the user's assigned roles while preserving
     * all other roles. The updated roles are stored in session metadata when the
     * session is online.
     *
     * @param array<int, string|int> $roles Roles to revoke from the session user.
     *
     * @return self Returns the current Session instance for method chaining.
     * @throws InvalidArgumentException If the roles are not a valid indexed list.
     *
     * @see self::setRoles() To set the user's roles.
     * @see self::assignRoles() To add roles to the user.
     * @see self::inRoles() To check the user's roles.
     * @see self::getRoles() To retrieve the user's roles.
     */
    public function revokeRoles(array $roles): self;

    /** 
     * Retrieves session data as an associative array.
     * 
     * @param string|null $name Optional name to retrieve specific data. If null, returns all session data.
     * 
     * @return array Return the session data as an associative array.
     */
    public function toArray(?string $name = null): array;

    /** 
     * Retrieves session data as an object.
     * 
     * @param string|null $name Optional name to retrieve specific data. If null, returns all session data.
     * 
     * @return object return the session data as a standard object.
     */
    public function toObject(?string $name = null): object;

    /**
     * Register a callback to handle failed role authorization.
     *
     * The callback is invoked when the user is online but does not satisfy the
     * specified role requirements. It receives the required roles and the user's
     * currently assigned roles.
     * 
     * The supported modes are:
     *
     * - {@see self::GUARD_ANY}: At least one required role must be present.
     * - {@see self::GUARD_ALL}: All required roles must be present.
     * - {@see self::GUARD_EXACT}: The user's roles must exactly match the required roles.
     * - {@see self::GUARD_NONE}: None of the required roles may be present.
     *
     * @param callable(static, array<int, string|int>, array<int, string|int>): void $onDenied
     *     Callback invoked when the role check fails.
     * @param array<int,string|int>|null $roles Roles required for authorization.
     * @param int $mode Role matching mode. One of `self::GUARD_*`.
     *
     * @return void
     * @see self::setRoles() - To set required validation roles for current session user.
     * @see self::inRoles()
     * 
     * @example - Guard login:
     * ```php
     * $session->onRoleGuard(
     *      function(array $expected, array $subscriptions): void {
     *          throw new AccessDeniedException('Access not allowed.');
     *      }, 
     *      ['admin'], 
     *      Session::GUARD_ANY, 
     * );
     */
    public function onRoleGuard(
        callable $onDenied,
        ?array $roles = null,
        int $mode = 0
    ): void;

    /**
     * Register a callback to handle session IP address changes.
     *
     * Monitors the user's IP address when strict session IP checking is enabled
     * and invokes the callback when the IP address changes.
     *
     * The callback is responsible for deciding how to handle the IP change,
     * including whether to terminate the session.
     *
     * @param callable(static $session, string $previousIp, array<int,mixed> $ipChanges): void $onChange
     *     Callback invoked when the session IP address changes. Receives the
     *     session instance, the previous IP address, and IP change information.
     *
     * @return self Returns the current session instance.
     *
     * @example
     * ```php
     * use Luminova\Sessions\Session;
     *
     * $session = new Session();
     * $session->start();
     *
     * $session->onIpChanged(
     *     function (Session $session, string $previousIp, array $ipChanges): void {
     *         $session->logout();
     *     }
     * );
     * ```
     */
    public function onIpChanged(callable $onChange): self;
}