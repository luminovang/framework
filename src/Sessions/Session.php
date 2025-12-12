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

use Luminova\Luminova;
use Luminova\Runtime;
use Luminova\Time\Time;
use Luminova\Logger\Logger;
use Luminova\Http\Network\IP;
use Luminova\Base\SessionHandler;
use \App\Config\Session as SessionConfig;
use Luminova\Sessions\Managers\Session as SessionManager;
use Luminova\Interface\{SessionInterface, LazyObjectInterface, SessionManagerInterface};
use Luminova\Exceptions\{ErrorCode, LogicException, RuntimeException, InvalidArgumentException};

/**
 * PHP server session manager.
 *
 * Manages application session data using {@see $_SESSION} or {@see $_COOKIE}
 * as the underlying storage.
 *
 * > **Recommendation:**
 * >
 * > Use a custom storage via {@see Session::setStorage()} when managing
 * > login sessions. This keeps login data isolated from other application
 * > session data.
 *
 * @see Luminova\Sessions\Managers\Session Session storage using `$_SESSION`
 * @see Luminova\Sessions\Managers\Cookie Cookie storage using `$_COOKIE`
 */
class Session implements SessionInterface, LazyObjectInterface
{
    /**
     * At least one required role must exist in user roles.
     * 
     * @var int GUARD_ANY
     * @see self::inRoles()
     * @see self::onRoleGuard()
     */
    public final const GUARD_ANY   = 0;

    /**
     * All required roles must be present in user roles (but extras allowed).
     * 
     * @var int GUARD_ALL
     * @see self::inRoles()
     * @see self::onRoleGuard()
     */
    public final const GUARD_ALL   = 1;

    /**
     * Exact match — all and only the specified roles must exist.
     * 
     * @var int GUARD_EXACT
     * @see self::inRoles()
     * @see self::onRoleGuard()
     */
    public final const GUARD_EXACT = 2;

    /**
     * None of the given roles should be present (e.g., guest access only).
     * 
     * @var int GUARD_NONE
     * @see self::inRoles()
     * @see self::onRoleGuard()
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
     * @var int CLOSED 
     */
    private const CLOSED = 2;

    /**
     * Index key for session metadata.
     * 
     * @var string METADATA 
     */
    private const METADATA = '__session_metadata__';
    
    /**
     * static class instance.
     * 
     * @var self|null $instance 
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
     * @var callable|null $onIpChanged
     */
    private mixed $onIpChanged = null;

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
     * Login metadata.
     *
     * @var array<string,mixed> $metadata
     */
    private array $metadata = [];

    /**
     * Login session roles.
     *
     * @var array<int,string|int>
     */
    private array $roles = [];

    /**
     * Role guard handler.
     *
     * @var array{0:callable,1:?array,2:int} $onRoleGuard
     */
    private array $onRoleGuard = [];

    /**
     * Whether to enable strict session ID validation.
     *
     * When enabled, PHP rejects session IDs that do not already exist in the
     * configured session storage and generates a new session ID instead.
     *
     * @var bool $useStrictMode
     */
    private bool $useStrictMode = true;

    /**
     * Initialize the session manager.
     *
     * Configures the session manager with the provided session configuration,
     * including its storage table and strict mode settings. When no configuration
     * is provided, the default {@see SessionConfig} is used.
     *
     * @param SessionManagerInterface $manager Session manager responsible for
     *     storing and managing session data (default: {@see SessionManager}).
     * @param SessionConfig $config Session configuration object (default: {@see SessionConfig}).
     *
     * @see self::setStorage() - To set a custom session storage name.
     * @link https://luminova.ng/docs/0.0.0/sessions/session
     * @link https://luminova.ng/docs/0.0.0/sessions/examples
     *
     * @note The manager is configured automatically using the supplied session
     *     configuration.
     */
    public function __construct(
        private SessionManagerInterface $manager = new SessionManager(),
        private SessionConfig $config = new SessionConfig()
    ) 
    {
        $this->manager->setNamespace($this->config->namespace)
            ->setConfig($this->config);

        $this->useStrictMode((bool) $this->config->useStrictMode);
    }

    /**
     * Retrieve a session value using property syntax.
     *
     * @param string $name Session data key.
     *
     * @return mixed The stored session value, or `null` if the key does not exist.
     */
    public function __get(string $name): mixed
    {
        return $this->get($name);
    }

    /**
     * Set a session value using property syntax.
     *
     * @param string $name Session data key.
     * @param mixed $value Value to store.
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->set($name, $value);
    }

    /**
     * Determine whether a session value exists and is not `null`.
     *
     * @param string $name Session data key.
     *
     * @return bool `true` if the value exists and is not `null`, otherwise `false`.
     */
    public function __isset(string $name): bool
    {
        return $this->has($name);
    }
    
    /**
     * Auto-save if there are unsaved stacked items.
     * 
     * @return void
     */
    public function __destruct()
    {
        $this->save();
    }

    /**
     * Retrieve the shared Session instance.
     *
     * Creates and initializes the session instance on the first call, then
     * returns the same instance for all subsequent calls. Manager and
     * configuration arguments are only used when the singleton is initialized.
     *
     * @param SessionManagerInterface|null $manager Optional session manager.
     *     When omitted, the default {@see SessionManager} is used.
     * @param SessionConfig|null $config Optional session configuration.
     *     When omitted, the default {@see SessionConfig} is used.
     *
     * @return static The shared Session instance.
     */
    public static function getInstance(
        ?SessionManagerInterface $manager = null,
        ?SessionConfig $config = null
    ): static 
    {
        if(!static::$instance instanceof self){
            static::$instance = new static(
                $manager ?? new SessionManager(),
                $config ?? new SessionConfig()
            );
        }

        return static::$instance;
    }

    /**
     * {@inheritDoc}
     */
    public static function from(
        string $storage,
        ?SessionManagerInterface $manager = null,
        ?SessionConfig $config = null
    ): static 
    {
        return (new static(
            $manager ?? new SessionManager(),
            $config ?? new SessionConfig()
        ))->setStorage($storage);
    }

    /**
     * Convert a string into a valid PHP session ID.
     *
     * Generates a session ID based on PHP's session configuration:
     * - session.sid_bits_per_character
     * - session.sid_length
     *
     * Supported bit modes:
     * - 4 bits: hex (0-9a-f)
     * - 5 bits: base32-like (0-9a-v)
     * - 6 bits: extended base64-like set
     *
     * @param string $input Input string to convert.
     * @param string $algo Hash algorithm if input is not already a hash.
     *
     * @return string|null Generated session ID or null on failure.
     *
     * @throws RuntimeException If unsupported session.sid_bits_per_character is used.
     * 
     * @example - Convert CLI System Id to php session Id:
     * ```php
     * $sid = Session::toSessionId(\Luminova\Command\Terminal::getSystemId());
     * ```
     * @example - Convert string to session Id:
     * ```php
     * $sid = Session::toSessionId('user-id');
     * ```
     */
    public static function toSessionId(string $input, string $algo = 'sha256'): ?string
    {
        $bits = (int) (ini_get('session.sid_bits_per_character') ?: 4);
        $length = (int) (ini_get('session.sid_length') ?: 32);

        $alphabet = match ($bits) {
            4 => '0123456789abcdef',
            5 => '0123456789abcdefghijklmnopqrstuv',
            6 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ,-',
            default => throw new RuntimeException(
                "Unsupported session.sid_bits_per_character: {$bits}"
            ),
        };

        $binary = (strlen($input) === 64 && ctype_xdigit($input))
            ? hex2bin($input)
            : Luminova::hash($algo, $input, true, fallbackAlgo: null);

        if ($binary === false) {
            return null;
        }

        $mask = (1 << $bits) - 1;

        $value = 0;
        $bitCount = 0;
        $result = '';

        $i = 0;
        $byteLen = strlen($binary);

        for ($out = 0; $out < $length; $out++) {
            
            while ($bitCount < $bits && $i < $byteLen) {
                $value = ($value << 8) | ord($binary[$i]);
                $bitCount += 8;
                $i++;
            }

            if ($bitCount < $bits) {
                $value <<= ($bits - $bitCount);
                $bitCount = $bits;
            }

            $bitCount -= $bits;

            $result .= $alphabet[($value >> $bitCount) & $mask];

            $value &= (1 << $bitCount) - 1;
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
        return $this->config?->cookieName 
            ?: session_name() 
            ?: 'PHPSESSID';
    }

    /**
     * {@inheritDoc}
     */
    public function getResult(bool $asObject = false): array|object
    {
        return $asObject
            ? ($this->manager->toObject() ?? (object)[])
            : $this->manager->getResult();
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $name, mixed $default = null): mixed
    {
        return $this->manager->getItem($name, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function getId(): ?string
    {
        return ($this->is(self::ACTIVE) || $this->isOnline()) 
            ? $this->manager->getId() 
            : null;
    }

    /**
     * {@inheritDoc}
     */
    public function getIp(): ?string 
    {
        return $this->getMeta('ip');
    }

    /**
     * {@inheritDoc}
     */
    public function getUserAgent(): ?string 
    {
        return $this->getMeta('agent');
    }

    /**
     * {@inheritDoc}
     */
    public function getUpdatedAt(): ?int
    {
        return $this->getMeta('updated_at');
    }

    /**
     * {@inheritDoc}
     */
    public function getLastAttempt(): ?int
    {
        return $this->getMeta('attempted_at');
    }

    /**
     * {@inheritDoc}
     */
    public function getToken(): ?string
    {
        return $this->getMeta('token');
    }

    /**
     * {@inheritDoc}
     */
    public function getDatetime(): ?string
    {
        $timestamp = $this->getTimestamp();
        return ($timestamp === 0) 
            ? null 
            : date(DATE_ATOM, $timestamp);
    }

    /**
     * {@inheritDoc}
     */
    public function getTimestamp(): int 
    {
        return (int) $this->getMeta('timestamp');
    }

    /**
     * {@inheritDoc}
     */
    public function getExpiration(): int 
    {
        return Time::now()
            ->modify('+' . $this->config->expiration . ' seconds')
            ->getTimestamp();
    }

    /**
     * {@inheritDoc}
     */
    public function getMeta(string $name): mixed 
    {
        return $this->getMetadata()[$name] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getMetadata(): array 
    {
        if($this->metadata !== []){
            return $this->metadata;
        }

        return $this->metadata = (array) ($this->manager->getItems()[self::METADATA] ?? []);
    }

    /**
     * {@inheritDoc}
     */
    public function getAttributes(): array
    {
        $items = $this->manager->getItems();

        if ($items === []) {
            return [];
        }

        unset($items[self::METADATA]);

        return $items;
    }

    /** 
     * {@inheritdoc}
     */
    public function getIpAddresses(): array 
    {
        return $this->getMeta('ip_changes') ?? [];
    }

    /** 
     * {@inheritdoc}
     */
    public function getAttemptTimes(): array 
    {
        return $this->getMeta('attempt_times') ?? [];
    }

    /** 
     * {@inheritdoc}
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
     * {@inheritDoc}
     */
    public function setNamespace(?string $table): self
    {
        $this->manager->setNamespace($table ?? 'default');

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function set(string $name, mixed $value): self
    {
        $this->assert();

        $this->manager->setItem($name, $value);
        $this->touch();

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function add(string $name, mixed $value, bool &$status = false): self
    {
        $status = false;

        if($this->has($name)){
            return $this;
        }

        $this->set($name, $value);
        $status = true;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function put(string $name, mixed $value): self
    {
        $this->stacks[$name] = $value;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function save(): bool
    {
        if($this->stacks === []){
            return false;
        }

        $this->assert();

        $this->manager->setItems($this->stacks);
        $this->stacks = [];

        $this->touch();
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->manager->getItems();
    }

    /**
     * {@inheritDoc}
     */
    public function touch(?int $timestamp = null): self 
    {
        $this->setMetadata(
            'updated_at', 
            $timestamp ?? time(),
            whenOnline: false
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function close(): bool 
    {
        if($this->manager->close()){
            self::$status = self::CLOSED;
            return true;
        }

        return false;
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
     * {@inheritDoc}
     */
    public function is(int $status = self::ACTIVE): bool 
    {
        return $this->manager->status() === match ($status) {
            self::DISABLED => PHP_SESSION_DISABLED,
            self::NONE     => PHP_SESSION_NONE,
            self::ACTIVE   => PHP_SESSION_ACTIVE,
            self::CLOSED   => PHP_SESSION_NONE,
            default        => null
        };
    }

    /**
     * {@inheritDoc}
     */
    public function isStarted(): bool 
    {
        return self::$isStarted 
            && self::$status === self::STARTED;
    }

    /**
     * {@inheritDoc}
     */
    public function isOnline(): bool
    {
        return self::isLoggedIn($this->getMetadata());
    }

    /**
     * {@inheritDoc}
     */
    public function isExpired(int $seconds = 3600): bool
    {
        $timestamp = $this->getTimestamp();
        return (
            $timestamp !== null 
            && (time() - $timestamp < $seconds)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isIpLocked(): bool
    {
        return (bool) $this->config->strictSessionIp;
    }

    /**
     * {@inheritDoc}
     */
    public function isStrictMode(): bool
    {
        return $this->useStrictMode;
    }

    /**
     * {@inheritDoc}
     */
    public function isIpMatch(): bool
    {
        return $this->isIpLocked() && !$this->hasIpChanged();
    }

    /**
     * {@inheritDoc}
     */
    public function isIdleFor(int $seconds): bool
    {
        $timestamp = $this->getUpdatedAt();

        return $timestamp !== null
            && (time() - $timestamp) >= $seconds;
    }

    /**
     * {@inheritDoc}
     */
    public function isLoginLocked(
        int $seconds = 300,
        int $maxAttempts = 3
    ): bool 
    {
        if ($this->attempts() < $maxAttempts) {
            return false;
        }

        $timestamp = $this->getLastAttempt();

        return $timestamp !== null
            && (time() - $timestamp) < $seconds;
    }

    /**
     * {@inheritDoc}
     */
    public function isRapidAttempts(
        int $window = 10,
        int $maxAttempts = 5
    ): bool 
    {
        $attempts = $this->getAttemptTimes();

        if (count($attempts) < $maxAttempts) {
            return false;
        }

        $now = microtime(true);
        $cutoff = $now - $window;

        $recent = 0;

        foreach ($attempts as $timestamp) {
            if ($timestamp >= $cutoff) {
                $recent++;
            }
        }

        return $recent >= $maxAttempts;
    }

    /**
     * {@inheritDoc}
     */
    public function useStrictMode(bool $enable = true): self
    {
        $this->useStrictMode = $enable;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function toArray(?string $name = null): array
    {
        return $this->manager->toAs('array', $name);
    }

    /**
     * {@inheritDoc}
     */
    public function toObject(?string $name = null): object
    {
        return $this->manager->toAs('object', $name);
    }

    /**
     * {@inheritDoc}
     */
    public function remove(string $name): self
    {
        $this->assert();

        $this->manager->deleteItem($name);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function clear(): self
    {
        $this->assert();

        $this->manager->clear();
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function has(string $name): bool
    {
        return $this->manager->hasItem($name);
    }

    /**
     * {@inheritDoc}
     */
    public function attempt(bool $reset = false): bool 
    {
        $this->setMetadata(
            'attempts', 
            $reset ? 0 : $this->attempts() + 1,
            whenOnline: false
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function attempts(): int 
    {
        return (int) $this->getMeta('attempts') ?? 0;
    }

    /**
     * {@inheritDoc}
     */
    public function regenerate(bool $clearSessionData = false): string|bool
    {
        $id = $this->manager->regenerateId($clearSessionData);

        if($id === false){
            return false;
        }

        if(!$clearSessionData && $this->isOnline()){
            $this->setMetadata('token', $this->getHashId());
        }

        return $id;
    }

    /**
     * {@inheritDoc}
     */
    public function onRoleGuard(
        callable $onDenied, 
        ?array $roles = null, 
        int $permission = 0
    ): void
    {
        $this->onRoleGuard = [$onDenied, $roles, $permission];
    }

    /**
     * {@inheritDoc}
     */
    public function onIpChanged(callable $onChange): self
    {
        $this->onIpChanged = $onChange;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function start(?string $sessionId = null): bool
    {
        $isSession = $this->manager instanceof SessionManager;

        if (!$isSession && $this->handler instanceof SessionHandler) {
            throw new RuntimeException(
                sprintf(
                    'Session manager: "%s" does not support session save handlers. '
                    . 'Use "%s" or remove the handler.',
                    $this->manager::class,
                    SessionManager::class
                ),
                ErrorCode::LOGIC_ERROR
            );
        }

        $status = $isSession
            ? session_status()
            : $this->manager->status();

        if ($isSession) {
            if ($status === self::DISABLED) {
                throw new RuntimeException(
                    'Session Error: Sessions are disabled. '
                    . 'Enable the "session" extension in php.ini.'
                );
            }

            if ((bool) ini_get('session.auto_start')) {
                Logger::error(
                    'Session Error: "session.auto_start" is enabled. '
                    . 'Disable it so Luminova can manage sessions.'
                );

                return self::$isStarted = false;
            }

            $this->registerSessionSaveHandler();
        }

        if ($status === self::ACTIVE) {
            if (self::$isStarted && !PRODUCTION) {
                Logger::warning(
                    'Session' . ($isSession ? '' : ' Cookie') .
                    ' already started. Avoid calling $session->start() multiple times.'
                );
            }

            self::$status = self::STARTED;
            self::$isStarted = true;

            $this->registerIpChangeEventListener();
            $this->registerRoleEventListener();

            return true;
        }

        if ($status !== self::NONE) {
            return self::$isStarted = false;
        }

        if ($isSession) {
            $this->initialize();
        }

        if (!$this->manager->start($sessionId)) {
            Logger::warning('Failed to start session.', [
                'status'     => $status,
                'session_id' => $sessionId,
            ]);

            return self::$isStarted = false;
        }

        self::$status = self::STARTED;
        self::$isStarted = true;

        $this->registerIpChangeEventListener();
        $this->registerRoleEventListener();

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function login(?string $ip = null): bool
    {
        if(self::$status === self::CLOSED){
            return false;
        }

        if($this->isOnline()){
            return true;
        }

        $this->assert();
        $this->metadata['ip_changes'] = [];

        if ($this->config->strictSessionIp){
            $this->metadata['ip'] = $ip ?? IP::get();
        } elseif($ip !== null){
            throw new LogicException(sprintf(
                'An IP address cannot be bound to the session when "strictSessionIp" is disabled. '
                . 'Enable "%s::$strictSessionIp" to use the $bindIp parameter.',
                'App\Config\Session'
            ));
        }
       
        $timestamp = time();

        $this->metadata['online']      = 'on';
        $this->metadata['timestamp']   = $timestamp;
        $this->metadata['agent']       = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $this->metadata['token']       = $this->getHashId();
        $this->metadata['attempts']    = 0;
        $this->metadata['roles']       = $this->roles;
        $this->metadata['attempted_at']  = null;
        $this->metadata['attempt_times'] = [];
        $this->metadata['updated_at']    = $timestamp;

        $this->stacks[self::METADATA] = $this->metadata;

        $this->manager->setItems($this->stacks);
        $this->stacks = [];

        return $this->isOnline();
    }

    /**
     * {@inheritDoc}
     */
    public function setRoles(array $roles): self
    {
        $this->assertRoles($roles);

        $this->roles = $roles;

        if ($this->isOnline()) {
            $this->setMetadata('roles', $roles);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function assignRoles(array $roles): self
    {
        $this->assertRoles($roles);

        return $this->setRoles(
            array_values(array_unique([
                ...$this->getRoles(),
                ...$roles,
            ]))
        );
    }

    /**
     * {@inheritDoc}
     */
    public function revokeRoles(array $roles): self
    {
        $this->assertRoles($roles);

        if (!$this->isOnline()) {
            return $this;
        }

        $current = $this->getRoles();

        if ($current === [] || $roles === []) {
            return $this;
        }

        $this->setMetadata(
            'roles', 
            array_values(array_diff($current, $roles))
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function inRoles(?array $roles = null, int $mode = self::GUARD_ANY): bool
    {
        $roles ??= $this->roles;

        if ($roles === []) {
            return false;
        }

        $this->assertRoles($roles);

        if(!$this->isOnline()){
            return false;
        }

        $subscriptions = $this->getRoles();

        if($subscriptions === []){
            return false;
        }

        return match ($mode) {
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

    /**
     * {@inheritDoc}
     */
    public function logout(): bool
    {
        if(!$this->isActive()){
            return false;
        }

        $this->manager->setItem(self::METADATA, []);
        $this->metadata = [];

        return !$this->isOnline();
    }

    /**
     * {@inheritDoc}
     */
    public function destroy(): bool
    {
        if(!$this->isActive()){
            return true;
        }

        $this->manager->clear();
        $this->manager->regenerateId(true);
        $this->manager->close();

        $this->stacks = [];
        $this->metadata = [];
        self::$status = self::INACTIVE;

        return $this->manager->isEmpty();
    }

    /**
     * {@inheritDoc}
     */
    public static function destroyNative(): bool
    {
        $status = true;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $status = session_destroy();
        }

        $cookieName = session_name();

        if ($cookieName === false || !ini_get('session.use_cookies')) {
            return $status;
        }

        $params = session_get_cookie_params();

        return setcookie($cookieName, '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => $params['secure'] ?? PRODUCTION,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Strict',
        ]) && $status;
    }

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
     */
    public static function configure(object|array $config): bool
    {
        $config = is_array($config) ? (object) $config : $config;

        if ($config->expiration > 0) {
            ini_set(
                'session.gc_maxlifetime',
                (string) $config->expiration
            );
        }

        if ($config->savePath !== '') {
            if (
                !is_dir($config->savePath)
                || !is_writable($config->savePath)
            ) {
                throw new RuntimeException(sprintf(
                    'The specified session save path "%s" is not writable. '
                    . 'Please ensure the directory exists and has appropriate permissions.',
                    $config->savePath
                ));
            }

            session_save_path($config->savePath);
        }

        ini_set(
            'session.use_strict_mode',
            $config->useStrictMode ? '1' : '0'
        );

        ini_set('session.lazy_write', '1');
        ini_set('session.use_trans_sid', '0');

        if (PHP_SAPI === 'cli' || Runtime::isCommand()) {
            ini_set('session.use_cookies', '0');
            ini_set('session.use_only_cookies', '0');
            ini_set('session.cache_limiter', '');

            return true;
        }

        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');

        if ($config->cookieName !== '') {
            session_name($config->cookieName);
        }

        $sameSite = in_array(
            $config->sameSite,
            ['Lax', 'Strict', 'None'],
            true
        ) ? $config->sameSite : 'Lax';

        return session_set_cookie_params([
            'lifetime' => $config->expiration,
            'path'     => $config->sessionPath,
            'domain'   => $config->sessionDomain,
            'secure'   => PRODUCTION,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function restartIfNeeded(): bool
    {
        return $this->isActive() || $this->start();
    }

    /**
     * Determine whether the current IP differs from the session IP.
     *
     * @return bool `true` if the current IP has changed since the session was
     *              established, otherwise `false`.
     */
    protected function hasIpChanged(): bool
    {
        if (!$this->isOnline()) {
            return false;
        }

        $onlineIp = $this->getIp();

        if ($onlineIp === '' || IP::equals($onlineIp)) {
            return false;
        }

        $this->setMetadata('ip_changes', array_values(array_unique([
            ...$this->getIpAddresses(),
            $onlineIp,
        ])));

        return true;
    }

    /**
     * Initialize the PHP session configuration.
     *
     * Configures the session lifetime, storage path, session ID validation,
     * cookie behavior, and session cookie parameters before the session starts.
     *
     * This method is safe to call multiple times.
     *
     * @return void
     * @throws RuntimeException If the configured session save path is invalid
     *     or not writable.
     */
    protected function initialize(): void
    {
        if (!$this->is(self::NONE)) {
            return;
        }

        static $conf = null;

        $conf ??= clone $this->config;
        $conf->useStrictMode = $this->isStrictMode();

        if(self::configure($conf)){
            return;
        }

        throw new RuntimeException('Failed to configure session cookie parameters.');
    }

    /** 
     * @deprecated Use {@see self::from()} or {@see self::setStorage()} instead.
     * Retrieves a session data from a specific session storage name.
     * 
     * @param string $storage The storage name where the data is stored.
     * @param string $name The item name to retrieve from storage.
     * 
     * @return mixed Returns the retrieved session data or `null` if not found.
     */
    public function getFrom(string $storage, string $name): mixed
    {
        $default = $this->manager->getStorage();

        try{
            return $this->manager->setStorage($storage)
                ->getItem($name);
        } finally {
            $this->manager->setStorage($default);
        }
    }

    /** 
     * @deprecated Use {@see self::from()} or {@see self::setStorage()} instead.
     * Stores a value in a specific session storage name.
     * 
     * @param string $name The item name to set.
     * @param mixed $value The value to be stored.
     * @param string $storage The storage name where the value will be saved.
     * 
     * @return self Returns the instance of session class.
     * @throws RuntimeException If an operation is attempted without an active session.
     * 
     * > **Note:** 
     * > The {@see self::save()} method is not required to persist session date when using `setTo()` method.
     */
    public function setTo(string $name, mixed $value, string $storage): self
    {
        $this->assert();
        $default = $this->manager->getStorage();

        try{
            $this->manager->setStorage($storage)
                ->setItem($name, $value);

            return $this;
        } finally {
            $this->manager->setStorage($default);
        }
    }

    /**
     * @deprecated Use {@see self::login()} instead.
     * 
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
        return $this->setRoles($roles)
            ->login($ip);
    }

    /**
     * @deprecated Use {@see self::onRoleGuard()} instead.
     */
    public function guard(
        array $roles, 
        int $mode = self::GUARD_ANY, 
        ?callable $onDenied = null
    ): bool
    {
        if ($roles === []) {
            return false;
        }

        $this->assertRoles($roles);

        if(!$this->isOnline()){
            return true;
        }

        $passed = $this->inRoles($roles, $mode);
       
        if (!$passed && $onDenied && is_callable($onDenied)) {
            $onDenied($roles, $this->getRoles());
        }

        return !$passed;
    }

    /**
     * @deprecated Use  {@see self::setRoles()} instead.
     */
    public function roles(array $roles): self 
    {
        return $this->setRoles($roles);
    }

    /** 
     * Determines if the client has successfully logged in.
     * 
     * @deprecated Use  {@see self::isOnline()} instead.
     * 
     * @param string|null $storage Optional session storage name.
     * 
     * @return bool Returns true if the session user is online, false otherwise.
     * @codeCoverageIgnore
     */
    public function online(?string $storage = null): bool
    {
        return $this->isOnline();
    }

    /**
     * Terminates the user's online session and clears session metadata.
     *
     * @deprecated Use  {@see self::logout()} instead
     *
     * @return bool Returns true if session was terminated, otherwise false.
     * @codeCoverageIgnore
     */
    public function terminate(): bool
    {
        return $this->logout();
    }

    /**
     * Check whether the user's IP address has changed.
     *
     * @deprecated  Use {@see self::hasIpChanged()} instead.
     *
     * @param string|null $storage Optional session storage name to check.
     *
     * @return bool `true` if the user's IP differs from the stored session IP,
     *     otherwise `false`.
     * @codeCoverageIgnore
     */
    public function ipChanged(?string $storage = null): bool
    {
        return $this->hasIpChanged();
    }

    /**
     * Check if user is logged in.
     *
     * @param array $data
     * 
     * @return bool
     */
    private static function isLoggedIn(array $data): bool
    {
        if($data === []){
            return false;
        }

        return isset(
            $data['online'],
            $data['token'],
            $data['timestamp']
        )
            && $data['online'] === 'on'
            && is_int($data['timestamp'])
            && strlen((string) $data['token']) === 64;
    }

    /**
     * Register the configured session storage handler with PHP.
     *
     * Applies the session configuration to the handler when supported and
     * registers the handler as PHP's active session save handler.
     *
     * @return void
     */
    protected function registerSessionSaveHandler(): void
    {
        if (!$this->handler instanceof SessionHandler) {
            return;
        }

        if ($this->config instanceof SessionConfig) {
            $this->handler->setConfig($this->config);
        }

        session_set_save_handler($this->handler, true);
    }

    /**
     * Handle a session IP address change event.
     *
     * Checks for an IP address change when strict session IP validation is enabled.
     * If the IP address has changed, the registered callback is invoked with the
     * session instance, the previous IP address, and the recorded IP addresses.
     * When no callback is registered, the session is logged out.
     *
     * @return void
     */
    protected function registerIpChangeEventListener(): void
    {
        if (!$this->config->strictSessionIp || !$this->hasIpChanged()) {
            return;
        }

        if ($this->onIpChanged === null) {
            $this->logout();
            return;
        }

        ($this->onIpChanged)(
            $this,
            $this->getIp(),
            $this->getIpAddresses()
        );
    }

    /**
     * Handle a failed session role guard event.
     *
     * Checks the configured role requirements for the online session user and
     * invokes the registered callback when the user does not satisfy them.
     * The callback receives the session instance, required roles, and the user's
     * currently assigned roles.
     *
     * @return void
     */
    protected function registerRoleEventListener(): void
    {
        if ($this->onRoleGuard === [] || !$this->isOnline()) {
            return;
        }

        [$onDenied, $roles, $mode] = $this->onRoleGuard;

        $roles ??= $this->roles;

        if ($roles === []) {
            return;
        }

        if ($this->inRoles($roles, $mode)) {
            return;
        }

        $onDenied($this, $roles, $this->getRoles());
    }

    /**
     * Generate hash token.
     *
     * @return string
     */
    private function getHashId(): string 
    {
        $token = $this->getId() 
            . ($this->metadata['ip'] ?? IP::get())
            . $this->getStorage();
            
        return hash('sha256', $token);
    }

    /**
     * Check if session is started or active
     *
     * @return bool
     */
    private function isActive(): bool 
    {
        if (self::$status === self::STARTED || $this->is(self::ACTIVE)) {
            return true;
        }

        return false;
    }

    /**
     * Assert session state.
     *
     * @return void
     */
    private function assert(): void 
    {
        if ($this->isActive()) {
            return;
        }

        throw new RuntimeException(
            'Session Error: A session must be started before performing read/write operations. ' .
            'Call "$session->start()" first.'
        );
    }

    /**
     * Set a metadata key-value pair for the current session.
     *
     * Skips saving if `online` is true and the session is not marked as "online".
     *
     * @param string $name Metadata key.
     * @param mixed $value Metadata value.
     * @param bool $whenOnline   Whether to enforce that session is "online".
     */
    private function setMetadata(
        string $name, 
        mixed $value, 
        bool $whenOnline = true
    ): void 
    {
        $metadata = $this->getMetadata();

        if ($whenOnline && !self::isLoggedIn($metadata)) {
            return;
        }

        if($name !== 'updated_at'){
            $metadata['updated_at'] = time();
        }

        if($name === 'attempts'){
            $now = microtime(true);

            $metadata['attempted_at'] = (int) $now;
            $metadata['attempt_times'][] = $now;
        }

        $metadata[$name] = $value;
        $this->manager->setItem(self::METADATA, $metadata);

        $this->metadata = $metadata;
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