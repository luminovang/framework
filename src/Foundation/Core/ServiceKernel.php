<?php
declare(strict_types=1);
/**
 * Luminova Framework Kernel Interface.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Foundation\Core;

use \Redis;
use \Memcached;
use Luminova\Runtime;
use Luminova\Luminova;
use Luminova\Config\Env;
use Luminova\Routing\Router;
use \Psr\Log\LoggerInterface;
use Luminova\Cache\FileCache;
use Luminova\Cache\RedisCache;
use Luminova\Sessions\Session;
use Luminova\Cache\MemoryCache;
use Luminova\Http\Client\Novio;
use Luminova\Logger\NovaLogger;
use \Psr\Http\Client\ClientInterface;
use Luminova\Base\Cache as BaseCache;
use Luminova\Email\Clients\NovaMailer;
use Luminova\Exceptions\ClassException;
use Luminova\Exceptions\RuntimeException;
use Luminova\Foundation\Core\Application;
use Luminova\Interface\ClientInterface as HttpClientInterface;
use Luminova\Interface\{MailerInterface, RouterInterface, SessionInterface};

/**
 * Defines the core contract for an application services.  
 * 
 * @link https://luminova.ng/docs/0.0.0/foundation/service-kernel
 * 
 * @example - Usages:
 * ```php
 * use App\Kernel;
 * use Luminova\Luminova;
 * use function Luminova\Func\kernel;
 * 
 * $cache = $app->make('cache', shared: true);
 * $cache = kernel('cache', shared: true);
 * 
 * $app = Kernel::create(hared: true)->getApplication();
 * $app = Kernel::resolve(Kernel::SERVICE_APPLICATION, hared: true);
 * ```
 * 
 * > **Note:**
 * > This should be extended once as `app/Kernel.php`.
 * 
 * @phpstan-type CoreServices
 *     'http.client'|
 *     'logger'|
 *     'mailer'|
 *     'session'|
 *     'routing'|
 *     'application'|
 *     'memcached'|
 *     'redis'|
 *     'cache'|
 *     'cache.id'|
 *     'app.keys'|
 *     class-string<ClientInterface>|
 *     class-string<HttpClientInterface>|
 *     class-string<LoggerInterface>|
 *     class-string<MailerInterface>|
 *     class-string<SessionInterface>|
 *     class-string<RouterInterface>|
 *     class-string<Application>|
 *     class-string<Memcached>|
 *     class-string<Redis>|
 *     class-string<BaseCache>
 */
abstract class ServiceKernel
{
    /**
     * HTTP client service identifier.
     *
     * @var string SERVICE_HTTP_CLIENT
     */
    public final const SERVICE_HTTP_CLIENT = 'http.client';

    /**
     * Logger service identifier.
     *
     * @var string SERVICE_LOGGER
     */
    public final const SERVICE_LOGGER = 'logger';

    /**
     * Mailer service identifier.
     *
     * @var string SERVICE_MAILER
     */
    public final const SERVICE_MAILER = 'mailer';

    /**
     * Session service identifier.
     *
     * @var string SERVICE_SESSION
     */
    public final const SERVICE_SESSION = 'session';

    /**
     * Routing system service identifier.
     *
     * @var string SERVICE_ROUTING
     */
    public final const SERVICE_ROUTING = 'routing';

    /**
     * Application service identifier.
     *
     * @var string SERVICE_APPLICATION
     */
    public final const SERVICE_APPLICATION = 'application';

    /**
     * Memcached service identifier.
     *
     * @var string SERVICE_MEMCACHED
     */
    public final const SERVICE_MEMCACHED = 'memcached';

    /**
     * Redis service identifier.
     *
     * @var string SERVICE_REDIS
     */
    public final const SERVICE_REDIS = 'redis';

    /**
     * Cache service identifier.
     *
     * @var string SERVICE_CACHE
     */
    public final const SERVICE_CACHE = 'cache';

    /**
     * Auto URI driven cache identifier.
     *
     * @var string SERVICE_CACHE_ID
     */
    public final const SERVICE_CACHE_ID = 'cache.id';

    /**
     * Application encryption keys.
     *
     * @var string SERVICE_APP_KEYS
     */
    public final const SERVICE_APP_KEYS = 'app.keys';

    /**
     * The singleton instance of the kernel.
     *
     * @var self|null $instance
     */
    private static ?self $instance = null;

    /**
     * Shared service instances.
     *
     * @var array<string,mixed> $services
     */
    private static array $services = [];

    /**
     * Create or retrieve a kernel instance.
     *
     * When `$shared` is `true`, returns the existing shared kernel instance or
     * creates and stores one when none exists. When `$shared` is `false`, a new
     * kernel instance is created for the current resolution and is not stored
     * as the shared instance.
     *
     * @param bool $shared Whether to return or create the shared kernel instance.
     *
     * @return static The shared kernel instance, or a new instance when sharing
     *                is disabled.
     * 
     * @see self::resolve() To resolve service.
     */
    public static function create(bool $shared): static
    {
        if (!$shared) {
            return new static();
        }

        if (!self::$instance instanceof self) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    /**
     * Resolve a service from the application kernel.
     *
     * When `$shared` is enabled, an existing shared service instance is returned
     * when available. Otherwise, the service is resolved from the kernel and may
     * be stored for reuse when the service is configured to support sharing.
     *
     * When `$shared` is disabled, the service is resolved without reusing or
     * storing a previously resolved shared instance.
     *
     * @template T of object
     *
     * @param CoreServices|string $service The service identifier or
     *        class/interface name.
     * @param bool $shared Whether to reuse and, when supported, store a shared
     *        service instance.
     * @param mixed ...$arguments Arguments passed to the service resolver or
     *        constructor.
     *
     * @return T|mixed The resolved service result.
     *
     * @throws RuntimeException If the service is not registered and is not a
     *                          built-in kernel service.
     * @throws ClassException If the resolved service is not a valid object.
     *
     * @see self::create() To create or retrieve the shared kernel instance.
     */
    public final static function resolve(
        string $service,
        bool $shared = true,
        mixed ...$arguments
    ): mixed
    {
        if ($shared && self::isShared($service)) {
            return self::$services[self::resolveServiceName($service)];
        }

        $isShared = false;
        $resolved = null;

        if (self::isDefaultService($service)){
            $resolved = self::create(true)
                ->getDefaultService($service, ...$arguments);

            $isShared = $shared
                && self::shouldShareDefaultService($service);
        } else {
            $kernel = self::create(true);

            if(!$kernel->has($service)){
                throw new RuntimeException(sprintf(
                    'Service "%s" is not registered in the kernel.',
                    $service
                ));
            }

            $resolved = $kernel->get($service, ...$arguments);

            if (is_string($resolved) && class_exists($resolved)) {
                $resolved = new $resolved(...$arguments);
            }

            $isShared = $shared 
                && $kernel->shouldShareService($service);
        }

        if (!$isShared || $resolved === null) {
            return $resolved;
        }

        self::share($service, $resolved);

        return $resolved;
    }

    /**
     * Register a service as a shared service.
     *
     * The provided value is stored in the shared service registry using the
     * service's resolved name. Subsequent requests for the same service name
     * can reuse the registered value instead of creating a new instance.
     *
     * @param string $service The service name or alias to register.
     * @param mixed $result The service instance or value to share.
     *
     * @return void
     */
    public static final function share(string $service, mixed $result): void
    {
        self::$services[self::resolveServiceName($service)] = $result;
    }

    /**
     * Remove a service from the shared service registry.
     *
     * @param string $service The service name or alias to remove.
     *
     * @return void
     */
    public static final function unshare(string $service): void
    {
        unset(self::$services[self::resolveServiceName($service)]);
    }

    /**
     * Determine whether a service is registered as a shared service.
     *
     * @param string $service The service name or alias to check.
     *
     * @return bool `true` if the service is registered as shared, otherwise `false`.
     */
    public static final function isShared(string $service): bool
    {
        return isset(self::$services[self::resolveServiceName($service)]);
    }

    /**
     * Determine whether the kernel should share the same service object.
     *
     * Returning `true` allows the kernel to reuse the same service instance
     * for subsequent requests or resolutions. Returning `false` causes a
     * new instance to be created for each resolution.
     *
     * @param CoreServices|string $service The service identifier or
     *        class/interface name.
     *
     * @return bool `true` to share the service object, otherwise `false`.
     */
    abstract public function shouldShareService(string $service): bool;

    /**
     * Resolve a service instance from the kernel.
     *
     * Implementations are responsible for resolving the given service identifier
     * and returning the corresponding service instance, class name or other values.
     *
     * A class name may be returned when the service is registered as a class-string,
     * the kernel resolver will instantiate it using the supplied arguments.
     *
     * @param CoreServices|string $service The service identifier or
     *        class/interface name.
     * @param mixed ...$arguments Arguments passed to the service resolver or
     *        constructor when applicable.
     *
     * @return mixed The resolved service result, a resolvable
     *                                  class name, or `null` when the service cannot
     *                                  be resolved.
     *
     * @throws \Throwable If the service cannot be resolved.
     */
    abstract public function get(
        string $service,
        mixed ...$arguments
    ): mixed;

    /**
     * Determine whether a service is defined in the kernel.
     *
     * This method only checks for the existence of the service identifier
     * and does not guarantee that the service can be successfully resolved.
     *
     * @param CoreServices|string $service The service identifier or
     *        class/interface name.
     *
     * @return bool Returns true if the service exists, false otherwise.
     */
    abstract public function has(string $service): bool;

    /**
     * Resolve and return the application's routing system.
     *
     * Override this method to provide a custom routing implementation. The
     * returned router must implement {@see RouterInterface}.
     *
     * The application instance is passed to the routing system constructor
     * so the router can access the application's runtime configuration.
     *
     * @param Application $app The application instance passed to the routing
     *        system constructor.
     *
     * @return RouterInterface The application's routing system.
     */
    public function getRoutingSystem(Application $app): RouterInterface
    {
        return new Router($app);
    }

    /**
     * Resolve and return the application instance.
     *
     * Override this method to provide a custom application implementation.
     * The returned instance is used as the application's main runtime entry
     * point and may vary depending on the current runtime environment.
     *
     * @return Application The application instance to use.
     *
     * @example - Custom application resolution:
     * ```php
     * return match (true) {
     *     Runtime::isCommand() => new App\CliApplication(),
     *     Runtime::isHmvc()    => new App\HttpHmvcApplication(),
     *     default              => new App\HttpApplication(),
     * };
     * ```
     */
    public function getApplication(): Application
    {
        return new \App\Application();
    }

    /**
     * Resolve and return the application's session client.
     *
     * Override this method to provide a custom session implementation. The
     * returned client must implement {@see SessionInterface}.
     *
     * Additional arguments are forwarded to the session client constructor.
     *
     * @param mixed ...$arguments Arguments passed to the session client constructor.
     *
     * @return SessionInterface The application's session client.
     *
     * @example - Custom session resolution:
     * ```php
     * $session = new Session(...$arguments);
     *
     * match ($request->getPrefix()) {
     *     'admin' => $session->setStorage('admin'),
     *     default => $session->setStorage('users'),
     * };
     *
     * return $session;
     * ```
     */
    public function getSessionClient(mixed ...$arguments): SessionInterface
    {
        return new Session(...$arguments);
    }

    /**
     * Resolve and return the application's preferred logger.
     *
     * Override this method to provide a custom logger implementation. The
     * returned logger must implement PSR's {@see LoggerInterface}.
     *
     * Additional arguments are forwarded to the logger constructor.
     *
     * @param mixed ...$arguments Arguments passed to the logger constructor.
     *
     * @return LoggerInterface The application's logger instance.
     *
     * @example - Custom logger:
     * ```php
     * return new MonoLogger($config);
     * ```
     */
    public function getLogger(mixed ...$arguments): LoggerInterface
    {
        return new NovaLogger(...$arguments);
    }

    /**
     * Resolve and return the application's preferred mailer.
     *
     * Override this method to provide a custom mailer implementation. The
     * returned mailer must implement {@see MailerInterface}.
     *
     * Additional arguments are forwarded to the mailer constructor.
     *
     * Built-in mailer implementations include:
     *
     * - {@see \Luminova\Components\Email\Clients\PHPMailer}
     * - {@see \Luminova\Components\Email\Clients\SwiftMailer}
     * - {@see \Luminova\Components\Email\Clients\NovaMailer}
     *
     * @param mixed ...$arguments Arguments passed to the mailer constructor.
     *
     * @return MailerInterface The application's mailer instance.
     *
     * @example - PHPMailer:
     * ```php
     * return new PHPMailer($config);
     * ```
     *
     * @example - SwiftMailer:
     * ```php
     * return new SwiftMailer($config);
     * ```
     *
     * @example - Custom mailer:
     * ```php
     * return new MyMailer($config);
     * ```
     */
    public function getMailer(mixed ...$arguments): MailerInterface
    {
        return new NovaMailer(...$arguments);
    }

    /**
     * Resolve and return the application's HTTP client.
     *
     * Override this method to provide a custom HTTP client implementation. The
     * returned client must implement the PSR {@see ClientInterface}.
     *
     * Additional arguments are forwarded to the HTTP client constructor.
     *
     * Built-in Luminova HTTP clients include:
     *
     * - {@see \Luminova\Http\Client\Novio} — default client.
     * - {@see \Luminova\Http\Client\Guzzle}.
     *
     * @param mixed ...$arguments Arguments passed to the HTTP client constructor.
     *
     * @return ClientInterface The application's HTTP client instance.
     */
    public function getHttpClient(mixed ...$arguments): ClientInterface
    {
        return new Novio(...$arguments);
    }

    /**
     * Create and configure the application cache provider.
     *
     * When no driver is explicitly provided, the driver is loaded from the
     * `system.cache.driver` environment setting and defaults to `filecache`.
     *
     * Supported drivers:
     *
     * - `filecache` — {@see FileCache} file-based cache storage.
     * - `memcached` — {@see MemoryCache} Memcached-based cache storage.
     * - `redis`     — {@see RedisCache} Redis-based cache storage.
     *
     * The storage namespace and persistent connection identifier are passed
     * directly to the selected cache implementation. The cache provider is
     * configured to disable PHP automatic serialization before being returned.
     *
     * @param string|null $driver Cache driver name, or `null` to use the configured default.
     * @param string|null $storage Storage namespace or identifier.
     * @param string|null $persistentId Persistent connection identifier.
     * @param string $context Logical cache context, such as `orm` or `global`.
     *
     * @return BaseCache|null The configured cache provider, or `null` when the
     *                        driver is unsupported.
     */
    public function getCacheProvider(
        ?string $driver = null,
        ?string $storage = null,
        ?string $persistentId = null,
        string $context = 'global'
    ): ?BaseCache 
    {
        $driver ??= Env::get('system.cache.driver', 'filecache');

        $cache = match (trim($driver)) {
            'filecache' => new FileCache(
                storage: $storage,
                persistentId: $persistentId
            ),

            'memcached' => new MemoryCache(
                storage: $storage,
                persistentId: $persistentId
            ),

            'redis' => new RedisCache(
                storage: $storage,
                persistentId: $persistentId
            ),

            default => null,
        };

        if ($cache === null) {
            return null;
        }

        $cache->setSerializerOption(
            BaseCache::SERIALIZER_PHP_AUTO,
            false
        );

        return $cache;
    }

    /**
     * Create and configure a Memcached client instance.
     *
     * Returns `null` when the Memcached client implementation is unavailable.
     * The client supports optional persistent connections, a post-creation
     * callback, and a logical identifier for grouping or distinguishing
     * connections.
     *
     * The following options are enabled or configured:
     *
     * - consistent hashing via `OPT_LIBKETAMA_COMPATIBLE`
     * - binary protocol
     * - TCP_NODELAY
     * - connection timeout
     * - retry timeout
     * - server failure limit
     * - automatic removal of failed servers
     * - optional key prefix
     *
     * Server connection settings are loaded from the environment:
     *
     * - `memcached.host` — default: `127.0.0.1`
     * - `memcached.port` — default: `11211`
     * - `memcached.weight` — default: `0`
     *
     * @param string|null $persistentId Persistent connection identifier used
     *        for connection reuse.
     * @param callable|null $onNewObject Callback invoked when a new client
     *        instance is created.
     * @param string $identifier Logical connection identifier or context key.
     *
     * @return Memcached|null The configured Memcached client, or `null` when
     *                        the Memcached implementation is unavailable.
     */
    public function getMemcached(
        ?string $persistentId = null,
        ?callable $onNewObject = null,
        string $identifier = ''
    ): ?Memcached 
    {
        if (!class_exists(Memcached::class)) {
            return null;
        }

        $memcached = new Memcached(
            (string) $persistentId,
            $onNewObject,
            $identifier
        );

        $memcached->setOptions([
            Memcached::OPT_LIBKETAMA_COMPATIBLE  => true,
            Memcached::OPT_BINARY_PROTOCOL       => true,
            Memcached::OPT_TCP_NODELAY           => true,
            Memcached::OPT_CONNECT_TIMEOUT       => (int) Env::get(
                'memcached.connect.timeout',
                1000
            ),
            Memcached::OPT_RETRY_TIMEOUT         => (int) Env::get(
                'memcached.retry.timeout',
                2
            ),
            Memcached::OPT_SERVER_FAILURE_LIMIT  => (int) Env::get(
                'memcached.server.failure.limit',
                5
            ),
            Memcached::OPT_REMOVE_FAILED_SERVERS => true,
        ]);

        if (($prefix = Env::get('memcached.key.prefix')) !== null) {
            $memcached->setOption(
                Memcached::OPT_PREFIX_KEY,
                $prefix
            );
        }

        $memcached->addServer(
            Env::get('memcached.host', '127.0.0.1'),
            (int) Env::get('memcached.port', 11211),
            (int) Env::get('memcached.weight', 0)
        );

        return $memcached;
    }

    /**
     * Create and configure a Redis client instance.
     *
     * Returns `null` when the Redis extension is unavailable. The client supports
     * both regular and persistent connections, selected by the presence of a
     * persistent connection identifier.
     *
     * Connection and client settings are loaded from the environment:
     *
     * - `redis.host` — Redis server host, default: `127.0.0.1`.
     * - `redis.port` — Redis server port, default: `6379`.
     * - `redis.timeout` — Connection timeout, default: `0`.
     * - `redis.password` — Optional Redis authentication password.
     * - `redis.database` — Redis database index, default: `0`.
     * - `redis.key.prefix` — Optional key prefix. Falls back to the connection
     *   identifier when not configured.
     *
     * When `$persistentId` is provided, a persistent Redis connection is created
     * using that identifier. Otherwise, a regular connection is established.
     *
     * The `$onNewObject` callback is invoked after the connection has been
     * established and all configured Redis options have been applied.
     *
     * @param string|null $persistentId Persistent connection identifier used
     *        to reuse an existing Redis connection.
     * @param callable|null $onNewObject Callback invoked after a new Redis
     *        instance has been connected and configured.
     * @param string $identifier Logical connection identifier or context key.
     *
     * @return Redis|null The configured Redis client, or `null` when the Redis
     *                    extension is unavailable.
     */
    public function getRedis(
        ?string $persistentId = null,
        ?callable $onNewObject = null,
        string $identifier = ''
    ): ?Redis 
    {
        if (!class_exists(Redis::class)) {
            return null;
        }

        $options = [
            (string) Env::get('redis.host', '127.0.0.1'),
            (int)    Env::get('redis.port', 6379),
            (float)  Env::get('redis.timeout', 0),
        ];

        $redis = new Redis();

        if($persistentId !== '' && $persistentId !== null){
            $options[] = $persistentId;
            $connected = $redis->pconnect(...$options);
        } else{
            $connected = $redis->connect(...$options);
        }

        if (!$connected) {
            throw new RuntimeException(sprintf(
                'Could not connect to Redis at %s:%d',
                $options[0],
                $options[1]
            ));
        }

        $prefix = Env::get('redis.key.prefix', $identifier);
        $password = Env::get('redis.password');

        if ($password !== null && $password !== '') {
            if(!$redis->auth($password)){
                throw new RuntimeException('Redis password authentication failed.');
            }
        }

        $redis->select(
            (int) Env::get('redis.database', 0)
        );

        if ($prefix !== null && $prefix !== '') {
            $redis->setOption(
                Redis::OPT_PREFIX,
                $prefix
            );
        }

        if ($onNewObject !== null) {
            $onNewObject($redis);
        }

        return $redis;
    }

    /**
     * Get the current and previous application encryption keys.
     *
     * The current key is returned first, followed by previously configured
     * keys. Previous keys allow data encrypted with an older application key
     * to remain decryptable after key rotation.
     *
     * @return array<int,string> Application encryption keys ordered from
     *                            newest to oldest.
     */
    public static function getApplicationKeys(): array
    {
        $keys = [
            Env::get('app.key')
        ];

        foreach (Env::get('app.keys', []) as $key) {
            if ($key === '' || trim($key) === '') {
                continue;
            }

            $keys[$key] = $key;
        }

        return array_values($keys);
    }

    /**
     * Generate a cache identifier for the current request.
     *
     * Creates a normalized cache key using the request method and URI. The URI may
     * optionally include query parameters and can be normalized by removing known
     * static file extensions to prevent duplicate cache entries for the same
     * resource.
     *
     * The generated identifier can be returned as either a raw key or an XXH3 hash.
     *
     * @param string|null $prefix Optional prefix to prepend to the generated key.
     * @param bool|null $withUriQuery Whether to include query parameters in the key.
     *                                When null, the value is determined by
     *                                `env('page.cache.query.params', false)`.
     * @param bool $hashValue Whether to return the generated key as an XXH3 hash.
     *
     * @return string The generated cache identifier or its XXH3 hash.
     *
     * @example - Example
     * ```php
     * $cacheId = Kernel::getCacheId();
     *
     * $cacheIdWithoutQuery = Kernel::getCacheId(
     *     withUriQuery: false
     * );
     *
     * $rawCacheId = Kernel::getCacheId(
     *     hashValue: false
     * );
     * 
     * $userCacheId = Kernel::getCacheId(
     *     prefix: 'user-1',
     *     hashValue: true
     * );
     * ```
     */
    public static function getCacheId(
        ?string $prefix = null,
        ?bool $withUriQuery = null,
        bool $hashValue = true
    ): string
    {
        $withUriQuery ??= (bool) Env::get('page.cache.query.params', false);

        $id = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
        $id .= (
            $withUriQuery
                ? $_SERVER['REQUEST_URI'] ?? '/'
                : (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '')
        );

        $types = Runtime::getStaticCacheTypes();

        if ($types !== '') {
            // Remove file extension for static cache formats
            // To avoid creating 2 versions of same cache
            // While serving static content (e.g, .html).
            $id = preg_replace(
                '/\.(' . preg_quote($types, '/') . ')(?=$|[?#])/i',
                '',
                $id
            );
        }

        $id = strtr($id, [
            '/' => ':',
            '?' => ':',
            '&' => ':',
            '=' => ':',
            '#' => ':',
            ' ' => ':'
        ]);

        $prefix = ($prefix !== null && $prefix !== '') 
            ? trim($prefix, " \t\n\r\0\x0B:") 
            : '';

        return $hashValue 
            ? Luminova::hash('xxh3', "{$prefix}:{$id}", fallbackAlgo: 'md5') 
            : str_replace("\0", ':', "{$prefix}:{$id}");
    }

    /**
     * Determine whether a built-in service should be shared.
     *
     * Built-in services are shared by default, except for services that are
     * intended to create a new instance for each resolution.
     *
     * The HTTP client is not shared because its implementation may maintain
     * request-specific state or configuration.
     *
     * Unknown or custom services are not shared by default and must be handled
     * explicitly by the kernel when a shared instance is required.
     *
     * @param CoreServices|string $service The service identifier or
     *        class/interface name.
     *
     * @return bool `true` if the built-in service should be shared, otherwise `false`.
     */
    protected final static function shouldShareDefaultService(
        string $service
    ): bool 
    {
        return match (self::resolveServiceName($service)) {
            self::SERVICE_HTTP_CLIENT => false,

            self::SERVICE_LOGGER,
            self::SERVICE_MAILER,
            self::SERVICE_SESSION,
            self::SERVICE_ROUTING,
            self::SERVICE_APPLICATION,
            self::SERVICE_MEMCACHED,
            self::SERVICE_REDIS,
            self::SERVICE_CACHE => true,

            default => false
        };
    }

    /**
     * Determine whether a service identifier maps to a built-in kernel service.
     *
     * Built-in services are resolved by {@see getDefaultService()} when they are
     * not explicitly registered in the kernel container.
     *
     * @param CoreServices|string $service The service identifier or
     *        class/interface name.
     *
     * @return bool `true` if the service is a built-in kernel service, otherwise `false`.
     */
    protected final static function isDefaultService(string $service): bool
    {
        return match (self::resolveServiceName($service)) {
            self::SERVICE_HTTP_CLIENT,
            self::SERVICE_LOGGER,
            self::SERVICE_MAILER,
            self::SERVICE_SESSION,
            self::SERVICE_ROUTING,
            self::SERVICE_APPLICATION,
            self::SERVICE_MEMCACHED,
            self::SERVICE_REDIS,
            self::SERVICE_CACHE_ID,
            self::SERVICE_APP_KEYS,
            self::SERVICE_CACHE => true,

            default => false
        };
    }

    /**
     * Resolve a built-in kernel service.
     *
     * The service identifier may be a supported short name, class name, or
     * interface name. Known identifiers are normalized to their canonical
     * service name before resolving the corresponding framework service.
     *
     * Application-specific providers take precedence where supported. If no
     * provider is available, the framework's default implementation is returned.
     *
     * Cache providers are resolved using the supplied driver arguments or,
     * when omitted, the configured `system.cache.driver` environment value.
     *
     * @param CoreServices|string $service The service identifier or
     *        class/interface name.
     * @param mixed ...$arguments Arguments passed to the service resolver.
     *
     * @return mixed The resolved service, or `null` when the identifier is not
     *               a built-in service.
     */
    protected final function getDefaultService(
        string $service,
        mixed ...$arguments
    ): mixed 
    {
        return match (self::resolveServiceName($service)) {
            self::SERVICE_HTTP_CLIENT => $this->getHttpClient(...$arguments),
            self::SERVICE_LOGGER      => $this->getLogger(...$arguments),
            self::SERVICE_MAILER      => $this->getMailer(...$arguments),
            self::SERVICE_SESSION     => $this->getSessionClient(...$arguments),
            self::SERVICE_ROUTING     => $this->getRoutingSystem(...$arguments),
            self::SERVICE_APPLICATION => $this->getApplication(),
            self::SERVICE_MEMCACHED   => $this->getMemcached(...$arguments),
            self::SERVICE_REDIS       => $this->getRedis(...$arguments),
            self::SERVICE_CACHE       => $this->getCacheProvider(...$arguments),
            self::SERVICE_CACHE_ID    => self::getCacheId(...$arguments),
            self::SERVICE_APP_KEYS    => self::getApplicationKeys(),
            default                   => null
        };
    }
    
    /**
     * Resolve a service alias or interface/class name to its canonical service name.
     *
     * Known service aliases and service class names are mapped to the identifier
     * used by the service container. Unknown names are returned unchanged after
     * surrounding whitespace has been removed.
     *
     * @param string $service The service alias, interface, or class name.
     *
     * @return string The canonical service name, or the trimmed input when no
     *                known service mapping exists.
     */
    protected static final function resolveServiceName(string $service): string
    {
        $service = trim($service);

        return match ($service) {
            self::SERVICE_HTTP_CLIENT,
            ClientInterface::class,
            HttpClientInterface::class => self::SERVICE_HTTP_CLIENT,

            self::SERVICE_LOGGER,
            LoggerInterface::class => self::SERVICE_LOGGER,

            self::SERVICE_MAILER,
            MailerInterface::class => self::SERVICE_MAILER,

            self::SERVICE_SESSION,
            SessionInterface::class => self::SERVICE_SESSION,

            'router',
            self::SERVICE_ROUTING,
            RouterInterface::class => self::SERVICE_ROUTING,

            'app',
            self::SERVICE_APPLICATION,
            Application::class => self::SERVICE_APPLICATION,

            self::SERVICE_MEMCACHED,
            Memcached::class => self::SERVICE_MEMCACHED,

            self::SERVICE_REDIS,
            Redis::class => self::SERVICE_REDIS,

            self::SERVICE_CACHE,
            BaseCache::class => self::SERVICE_CACHE,

            default => $service,
        };
    }
}