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
use \Luminova\Sessions\Managers\Session as SessionManager;
use \Luminova\Interface\{SessionInterface, LazyObjectInterface, SessionManagerInterface};
use \Luminova\Exceptions\{ErrorCode, LogicException, RuntimeException, InvalidArgumentException};

final class Session implements SessionInterface, LazyObjectInterface
{
    /**
     * At least one required role must exist in user roles.
     * 
     * @var int GUARD_ANY
     * @see self::guard()
     */
    public final const GUARD_ANY   = 0;

    /**
     * All required roles must be present in user roles (but extras allowed).
     * 
     * @var int GUARD_ALL
     * @see self::guard()
     */
    public final const GUARD_ALL   = 1;

    /**
     * Exact match — all and only the specified roles must exist.
     * 
     * @var int GUARD_EXACT
     * @see self::guard()
     */
    public final const GUARD_EXACT = 2;

    /**
     * None of the given roles should be present (e.g., guest access only).
     * 
     * @var int GUARD_NONE
     * @see self::guard()
     */
    public final const GUARD_NONE  = 3;

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
     * Index key for session metadata.
     * 
     * @var string METADATA 
     */
    private const METADATA = '__session_metadata__';
    
    /**
     * static class instance
     * 
     * @var self $instance 
     */
    private static ?self $instance = null;

    /**
     * Session start status.
     * 
     * @var int $status 
     */
    private static int $status = self::INACTIVE;

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
     * Initializes the backend session handler class.
     *
     * This constructor sets up the session manager to handle user login and backend session management. 
     * It allows for an optional custom session manager and session handler 
     * to be provided or defaults to the standard manager.
     *
     * @param SessionManagerInterface $manager Optional. A custom session manager instance.
     *              If not provided, the default `\Luminova\Sessions\Managers\Session` will be used.
     * @param SessionConfig|null $config Session configuration (default: `App\Config\Session`).
     * 
     * @see https://luminova.ng/docs/0.0.0/sessions/session
     * @see https://luminova.ng/docs/0.0.0/sessions/examples
     *
     * > **Note:** 
     * > When no custom manager is provided, the default session manager is automatically 
     * > initialized and configured using the session configuration settings.
     */
    public function __construct(
        private SessionManagerInterface $manager = new SessionManager(),
        private ?SessionConfig $config = null
    )
    {
        if(!$this->config instanceof SessionConfig){
            $this->config = new SessionConfig();
        }

        $this->manager->setTable($this->config->tableIndex);
        $this->manager->setConfig($this->config);
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
     * @param SessionConfig|null $config Session configuration (default: `App\Config\Session`).
     * 
     * @return static Return static Session class instance.
     */
    public static function getInstance(
        SessionManagerInterface $manager = new SessionManager(),
        ?SessionConfig $config = null
    ): static
    {
        if (self::$instance === null) {
            self::$instance = new self($manager, $config);
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
     * {@inheritDoc}
     */
    public function getManager(): ?SessionManagerInterface
    {
        return $this->manager;
    }

    /**
     * {@inheritDoc}
     */
    public function getStorage(): string 
    {
        return $this->manager->getStorage();
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string 
    {
        return $this->config?->cookieName ?: session_name() ?: 'PHPSESSID';
    }

    /**
     * {@inheritDoc}
     */
    public function getResult(string $format = 'array'): array|object
    {
        return $this->manager->getResult($format);
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->manager->getItem($key, $default);
    }

    /**
     * {@inheritDoc}
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
        return Time::now()->modify('+' . $this->config->expiration . ' seconds')->getTimestamp();
    }

    /**
     * {@inheritDoc}
     */
    public function getFrom(string $storage, string $key): mixed
    {
        return $this->manager->getItems($storage)[$key] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getMeta(string $key, ?string $storage = null): mixed 
    {
        return $this->getMetadata($storage)[$key] ?? null;
    }

    /**
     * {@inheritDoc}
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
    public function getIpAddresses(): array 
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
     * {@inheritDoc}
     */
    public function setHandler(SessionHandler $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function setManager(SessionManagerInterface $manager): self
    {
        $this->manager = $manager;
        return $this;
    }

    /**
     * {@inheritDoc}
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
     * > **Note:** 
     * > The `save()` method is not required to persist session date when using `setTo()` method.
     */
    public function setTo(string $key, mixed $value, string $storage): self
    {
        $this->restart();
        $this->manager->setItem($key, $value, $storage);
        $this->setActivity($storage);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $key, mixed $value): self
    {
        $this->restart();
        $this->manager->setItem($key, $value);
        $this->setActivity();
        return $this;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function put(string $key, mixed $value): self
    {
        $this->stacks[$key] = $value;
        return $this;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function commit(): void 
    {
        $this->manager->commit();
        self::$status = self::COMMITTED;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function isStarted(): bool 
    {
        return self::$isStarted && self::$status === self::STARTED;
    }

    /**
     * {@inheritDoc}
     */
    public function isOnline(): bool
    {
        return $this->online();
    }

    /**
     * {@inheritDoc}
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
        return (bool) $this->config->strictSessionIp;
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
     * {@inheritDoc}
     */
    public function toArray(?string $key = null): array
    {
        return $this->manager->toAs('array', $key);
    }

    /**
     * {@inheritDoc}
     */
    public function toObject(?string $key = null): object
    {
        return $this->manager->toAs('object', $key);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $key): self
    {
        $this->restart();
        $this->manager->deleteItem($key);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(?string $storage = null): self
    {
        $this->restart(false);
        $this->manager->deleteItem(null, $storage);
        return $this;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function regenerate(bool $clearData = true): string|bool
    {
        return $this->manager->regenerateId($clearData);
    }

    /**
     * {@inheritDoc}
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
               $this->initialize();
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
     * {@inheritDoc}
     */
    public function login(?string $ip = null, array $roles = []): bool
    {
        if($this->online()){
            return true;
        }

        $this->restart();
        $metadata = ['ip_changes' => []];

        if($this->config->strictSessionIp){
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
     * @see self::login()
     */
    public function synchronize(?string $ip = null, array $roles = []): bool
    {
        return $this->login($ip, $roles);
    }

    /**
     * {@inheritDoc}
     */
    public function roles(array $roles): self 
    {
        $this->assertRoles($roles);
        $this->setMetadata('roles', $roles);
        
        return $this;
    }

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function logout(): bool
    {
        return $this->terminate();
    }

    /**
     * {@inheritDoc}
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
            $this->setMetadata('ip_changes', [
                ...$this->getIpAddresses(),
                IP::get()
            ]);
        }
        
        return $changed;
    }

    /**
     * IP Address Change Listener to detect and respond to user IP changes.
     *
     * This method monitors the user's IP address during a session 
     * and `$strictSessionIp` is enabled. If the IP address changes, the specified callback is executed. 
     * 
     * Based on the callback's return value:
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
     * {@inheritDoc}
     */
    public function initialize(): void
    {
        if ($this->config->expiration > 0) {
            ini_set('session.gc_maxlifetime', (string) $this->config->expiration);
        }

        if ($this->config->savePath) {
            if(!is_writable($this->config->savePath)){
                throw new RuntimeException(sprintf(
                    'The specified session save path "%s" is not writable. Please ensure the directory exists and has appropriate permissions.',
                    $this->config->savePath
                ));
            }

            ini_set('session.save_path', $this->config->savePath);
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

        if ($this->config->cookieName) {
            session_name($this->config->cookieName);
        }

        $sameSite = in_array($this->config->sameSite, ['Lax', 'Strict', 'None'], true)
            ? $this->config->sameSite
            : 'Lax';

        session_set_cookie_params([
            'lifetime' => $this->config->expiration,
            'path'     => $this->config->sessionPath,
            'domain'   => $this->config->sessionDomain,
            'secure'   => true,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
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
        if($this->config->strictSessionIp && $this->ipChanged()){
            if( 
                $this->onIpChange !== null && 
                ($this->onIpChange)(
                    $this, 
                    $this->getIp(),
                    $this->getIpAddresses()
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
            if($this->config instanceof SessionConfig){
                $this->handler->setConfig($this->config);
            }
            
            session_set_save_handler($this->handler, true);
        }
    }

    /**
     * Set a metadata key-value pair for the current session.
     *
     * Skips saving if `online` is true and the session is not marked as "online".
     *
     * @param string $key Metadata key.
     * @param mixed $value Metadata value.
     * @param string|null $storage  Optional custom storage key.
     * @param bool $whenOnline   Whether to enforce that session is "online".
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