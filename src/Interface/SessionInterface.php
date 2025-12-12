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

use \Luminova\Base\SessionHandler;
use \Luminova\Interface\SessionManagerInterface;
use \Luminova\Exceptions\{LogicException, RuntimeException, InvalidArgumentException};

interface SessionInterface 
{
    /**
     * Retrieves the current session storage manager instance.
     * 
     * This method returns the session manager instance responsible for handling 
     * session data, either `Luminova\Sessions\Managers\Cookie` or `Luminova\Sessions\Managers\Session`.
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
     * @param string $format The data format, either `object` or `array` (default: `array`).
     * 
     * @return array|object Return the stored session data in the requested format.
     */
    public function getResult(string $format = 'array'): array|object;

    /**
     * Retrieves a value from the session storage.
     *
     * @param string $key The key used to identify the session data.
     * @param mixed $default The default value returned if the key does not exist.
     * 
     * @return mixed Returns the retrieved session data or the default value if not found.
     */
    public function get(string $key, mixed $default = null): mixed;

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
     * Retrieves a session data from a specific session storage name.
     * 
     * @param string $storage The storage name where the data is stored.
     * @param string $key The key used to identify the session data.
     * 
     * @return mixed Returns the retrieved session data or `null` if not found.
     */
    public function getFrom(string $storage, string $key): mixed;

    /**
     * Retrieves session login metadata key value from session storage.
     *
     * @param string $key The metadata key to retrieve.
     * @param string|null $storage Optional storage name.
     * 
     * @return mixed Return the metadata value or null if not exist.
     */
    public function getMeta(string $key, ?string $storage = null): mixed;

    /**
     * Retrieves session login metadata information from session storage.
     *
     * @param string|null $storage Optional storage name.
     * 
     * @return array<string,mixed> Return an associative array containing session metadata.
     */
    public function getMetadata(?string $storage = null): array;

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
    public function setHandler(SessionHandler $handler): self;

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

    public function setManager(SessionManagerInterface $manager): self;

    /**
     * Sets the storage name for storing and retrieving session data.
     * 
     * This method allows you to define or override the session name under which session data will be managed.
     *
     * @param string $storage The session storage key to set.
     * 
     * @return self Returns the instance of session class.
     */
    public function setStorage(string $storage): self;

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
    public function set(string $key, mixed $value): self;

    /**
     * Adds a value to the session storage without overwriting existing keys.
     *
     * This method attempts to add a new key-value pair to the session. 
     * If the specified key already exists in the session storage, 
     * the method does not modify the value and sets the status to `false`. 
     * Otherwise, it adds the new key-value pair and sets the status to `true`.
     *
     * @param string $key The key to identify the session data.
     * @param mixed $value The value to associate with the specified key.
     * @param bool $status A reference variable to indicate whether the operation succeeded (`true`) or failed (`false`).
     * 
     * @return self Returns the instance of session class.
     * > **Note:** 
     * > The `save()` method is not required to persist session date when using `add()` method.
     */
    public function add(string $key, mixed $value, bool &$status = false): self;

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
    public function put(string $key, mixed $value): self;

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
    public function save(?string $storage = null): bool;

    /**
     * Commits the current session data.
     *
     * This method finalizes the session write process by committing any changes 
     * made to the session data. Once committed, the session is considered closed 
     * and cannot be modified until restarted.
     * 
     * @return void
     */
    public function commit(): void;

    /**
     * Clears all stacked session data without saving.
     *
     * This method removes all temporarily stored session data before it is saved. 
     * Use it if you want to discard changes before calling `save()`.
     *
     * @return true Always return true.
     */
    public function dequeue(): bool;

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
     * This method acts as an alias for `online()`, maintaining naming consistency.
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
     * @param int $seconds The time threshold in seconds before the session is considered expired (Default: 3600).
     * 
     * @return bool Returns true if the session is still valid, false if it has expired.
     */
    public function isExpired(int $seconds = 3600): bool;

    /** 
     * Retrieves session data as an associative array.
     * 
     * @param string|null $key Optional key to retrieve specific data. If null, returns all session data.
     * 
     * @return array Return the session data as an associative array.
     */
    public function toArray(?string $key = null): array;

    /** 
     * Retrieves session data as an object.
     * 
     * @param string|null $key Optional key to retrieve specific data. If null, returns all session data.
     * 
     * @return object return the session data as a standard object.
     */
    public function toObject(?string $key = null): object;

    /** 
     * Remove a key from the session storage by passing the key.
     * 
     * @param string $key The key to identify the session data to remove.
     * 
     * @return self Returns the instance of session class.
     * @throws RuntimeException If an operation is attempted without an active session.
     */
    public function remove(string $key): self;

    /** 
     * Clear all data from session storage by passing the storage name or using the default storage.
     * 
     * @param string|null $storage Optionally session storage name to clear.
     * 
     * @return self Returns the instance of session class.
     */
    public function clear(?string $storage = null): self;

    /** 
     * Check if item key exists in session storage.
     * 
     * @param string $key The key to identify the session data to check.
     * 
     * @return bool Return true if key exists in session storage else false.
     */
    public function has(string $key): bool;

    /**
     * Regenerate session or cookie identifier.
     * 
     * This method delete the old ID associated to the current session, to retain data set to false.
     * 
     * @param bool $clearData Whether to delete the old associated session or not (default: `true`).
     * 
     * @return string|false Return the new generated session Id on success, otherwise false.
     */
    public function regenerate(bool $clearData = true): string|bool;

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
    public function start(?string $sessionId = null): bool;

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
    public function login(?string $ip = null, array $roles = []): bool;

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
    public function roles(array $roles): self;

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
     * @param (callable(array $roles, array $subscriptions):void)|null $onDenied Optional handler to call 
     *                          if access is denied.
     *
     * @return bool Returns `true` if access is denied, `false` if access is allowed.
     * @throws InvalidArgumentException If an invalid mode was provided 
     *              or if roles are not in a proper indexed list format.
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
    public function guard(array $roles, int $mode = 0, ?callable $onDenied = null): bool;

    /**
     * Logs out the user by terminating the session login metadata.
     *
     * This method acts as an alias for `terminate()`, ensuring the session metadata is cleared 
     * and marking the user as logged out. The session data itself remains intact, but the 
     * session state will no longer be recognized as active.
     *
     * @return bool Returns true if the session was successfully terminated, otherwise false.
     */
    public function logout(): bool;

    /**
     * Deletes session data stored in the session table `$tableIndex`, based on the active session configuration  
     * or the table set via `setTable` in the session manager.
     *
     * If `$allData` is `true`, all session and cookie data for the application will be cleared.
     *
     * @param bool $allData Whether to destroy clear all application session 
     *      or cookie data, based on session manager in use (default: `false`).
     *
     * @return bool Returns `true` if the session data was successfully cleared; `false` otherwise.
     * 
     */
    public function destroy(bool $allData = false): bool;

    /**
     * initialize PHP session configuration properties.
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
     * new Session->initialize();
     * session_start();
     * ```
     */
    public function initialize(): void;
}