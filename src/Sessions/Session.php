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
use Luminova\Sessions\Managers\SessionManager;
use Luminova\Interface\{
    SessionInterface, 
    LazyObjectInterface, 
    SessionManagerInterface
};
use Luminova\Exceptions\{
    ErrorCode, 
    LogicException, 
    RuntimeException, 
    InvalidArgumentException
};

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
 * @see Luminova\Sessions\Managers\SessionManager Session storage using `$_SESSION`
 * @see Luminova\Sessions\Managers\CookieManager Cookie storage using `$_COOKIE`
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
        $bits = (int) Runtime::tryGetIniValue('session.sid_bits_per_character', '4');
        $length = (int) Runtime::tryGetIniValue('session.sid_length', '32');

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
        int $mode = 0
    ): void
    {
        $this->onRoleGuard = [$onDenied, $roles, $mode];
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

            if ((bool) Runtime::tryGetIniValue('session.auto_start')) {
                Logger::error(
                    'Session Error: "session.auto_start" is enabled. '
                    . 'Disable it so Luminova can manage sessions.'
                );

                return self::$isStarted = false;
            }

            $this->registerSessionSaveHandler();
        }

        if ($status === self::ACTIVE) {
            if (self::$isStarted && !Runtime::isProduction()) {
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

        if (
            $cookieName === false 
            || !Runtime::tryGetIniValue('session.use_cookies', '1')
        ) {
            return $status;
        }

        $params = session_get_cookie_params();

        return setcookie($cookieName, '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => $params['secure'] ?? Runtime::isProduction(),
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Strict',
        ]) && $status;
    }

    /**
     * Sets the PHP session storage directory.
     *
     * Creates the directory recursively with restrictive permissions if it does
     * not exist, verifies that it is writable, and configures PHP to store
     * session files in the specified location.
     *
     * Must be called before starting a session.
     *
     * @param string $path Absolute or relative path to the session directory.
     * @param int $dirPermissions Session directory permission (default: 0700).
     *
     * @return bool Return true if success, false if empty path.
     * @throws RuntimeException If the directory cannot be created, is not writable,
     * or PHP cannot configure the session save path.
     */
    public static function setSessionSavePath(string $path, int $dirPermissions = 0700): bool
    {
        if ($path === '') {
            return false;
        }

        if (($dirPermissions & ~0777) !== 0) {
            throw new InvalidArgumentException('Invalid directory permissions.');
        }

        Runtime::clearLastError();

        if (
            !is_dir($path)
            && !mkdir($path, $dirPermissions, true)
            && !is_dir($path)
        ) {
            throw new RuntimeException(sprintf(
                'Unable to create session directory: "%s". %s',
                $path,
                Runtime::lastError()['message'] ?? ''
            ));
        }

        if (!is_writable($path)) {
            throw new RuntimeException(sprintf(
                'Session directory is not writable: "%s". '
                . 'Check the directory permissions.',
                $path
            ));
        }

        if (!session_save_path($path)) {
            throw new RuntimeException(sprintf(
                'Unable to set session save path to "%s". %s',
                $path,
                Runtime::lastError()['message'] ?? ''
            ));
        }

        return true;
    }

    /**
     * Initialize PHP session configuration.
     *
     * Applies the supplied session settings to PHP's session subsystem.
     * This method must be called before starting a session.
     *
     * @param SessionConfig|object|array{
     *     expiration:int,
     *     gcMaxLifetime:int,
     *     savePath:string,
     *     useStrictMode:bool,
     *     cookieName:?string,
     *     sameSite:string,
     *     sessionPath:string,
     *     sessionDomain:string,
     *     cacheLimiter:string
     * } $config Session configuration.
     *
     * @return bool Return true if configuration was applied, otherwise false.
     * @throws RuntimeException If the configured save path is invalid or
     *                          session cookie parameters cannot be configured.
     */
    public static function configure(object|array $config): bool
    {
        $config = self::parseSessionOptions($config);

        $savePath = $config->savePath 
            ?? Luminova::root('/writeable/sessions/');

        if (!empty($savePath)) {
            self::setSessionSavePath($savePath);
        }

        if ($config->gcMaxLifetime > 0) {
            Runtime::setIniValue(
                'session.gc_maxlifetime', 
                (string) $config->gcMaxLifetime
            );
        }

        Runtime::trySetIniValue(
            'session.use_strict_mode', 
            (string) $config->useStrictMode
        );

        Runtime::trySetIniValue('session.lazy_write', '1');
        Runtime::trySetIniValue('session.use_trans_sid', '0');

        if (Runtime::isCommand()) {
            Runtime::trySetIniValue('session.use_cookies', '0');
            Runtime::trySetIniValue('session.use_only_cookies', '0');
            Runtime::trySetIniValue('session.cache_limiter', '');

            return true;
        }

        Runtime::trySetIniValue('session.cache_limiter', $config->cacheLimiter ?? 'nocache');
        Runtime::trySetIniValue('session.use_cookies', '1');
        Runtime::trySetIniValue('session.use_only_cookies', '1');

        if (
            is_string($config->cookieName)
            && $config->cookieName !== ''
        ) {
            if (!session_name($config->cookieName)) {
                throw new RuntimeException(sprintf(
                    'Unable to set session name: "%s".',
                    $config->cookieName
                ));
            }
        }

        $sameSite = $config->sameSite ?: 'Lax';

        if (!in_array($config->sameSite, ['Lax', 'Strict', 'None'], true)) {
            throw new RuntimeException(sprintf(
                'Invalid session SameSite value: "%s". 
                Allowed values are Lax, Strict, and None.',
                $config->cookieName
            ));
        }

        $options = [
            'lifetime' => (int) $config->expiration,
            'path'     => $config->sessionPath ?: '/',
            'secure'   => Runtime::isProduction(),
            'httponly' => true,
            'samesite' => $sameSite,
        ];

        if(!empty($config->sessionDomain)){
            $options['domain'] = $config->sessionDomain;
        }

        return session_set_cookie_params($options);
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
     * @throws RuntimeException If the configured session save path is invalid or not writable.
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

        throw new RuntimeException(
            'Failed to configure session cookie parameters.'
        );
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
     * Parse session configuration options.
     *
     * @param SessionConfig|object|array $config The config.
     * 
     * @return object{
     *     expiration:int,
     *     gcMaxLifetime:int,
     *     savePath:string,
     *     useStrictMode:bool,
     *     cookieName:?string,
     *     sameSite:string,
     *     sessionPath:string,
     *     sessionDomain:string,
     *     cacheLimiter:string
     * } Return session configurations.
     */
    private static function parseSessionOptions(object|array $config): object
    {
        if ($config instanceof SessionConfig) {
            return $config;
        }

        $config = is_object($config)
            ? get_object_vars($config)
            : $config;

        $config['expiration'] = $config['expiration']
            ?? $config['lifetime']
            ?? 0;

        return (object) array_replace([
            'expiration'    => 0,
            'gcMaxLifetime' => 1440,
            'savePath'      => '',
            'useStrictMode' => true,
            'cookieName'    => null,
            'sameSite'      => 'Lax',
            'sessionPath'   => '/',
            'sessionDomain' => '',
            'cacheLimiter'  => 'nocache',
        ], $config);
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