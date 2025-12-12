<?php
/**
 * Luminova Framework Kernel Interface.
 *
 * @package Luminova
 * @author Ujah Chigozie Peter
 * @copyright (c) Nanoblock Technology Ltd
 * @license See LICENSE file
 * @link https://luminova.ng
 */
namespace Luminova\Interface;

use \Redis;
use \Memcached;
use \Psr\Log\LoggerInterface;
use \Psr\Http\Client\ClientInterface;
use Luminova\Base\Cache as BaseCache;
use Luminova\Foundation\Core\Application;
use Luminova\Interface\{MailerInterface, RouterInterface, SessionInterface};

/**
 * Interface ServiceKernelInterface
 *
 * Defines the core contract for an application kernel.  
 * 
 * > **Note:**
 * >
 * > This should be extended once as `app/Kernel.php`
 * 
 * @example - Usages:
 * ```php
 * use Luminova\Luminova;
 * 
 * $app = Luminova::kernel()->getApplication();
 * 
 * $kernel = App\Kernel::create();
 * $app = $kernel->getApplication();
 * ```
 */
interface ServiceKernelInterface
{
    /**
     * Create a kernel instance.
     *
     * Called by the framework to construct the application kernel.
     * Implementations must return a fully initialized kernel object.
     *
     * When `$shared` is true, the kernel may be prepared for shared use.
     * When false, a fresh, non-shared kernel instance must be returned.
     *
     * @param bool $shared Whether the kernel is intended to be shared.
     *
     * @return self Returns shared or new kernel instance.
     *
     * @internal Used by Luminova to create kernel instances.
     */
    public static function create(bool $shared): self;

    /**
     * Determine if the kernel should share a single application object.
     * 
     * Default `true`.
     *
     * Returning `true` instructs the framework to reuse the same
     * application kernel instance across requests or modules.  
     * Returning `false` allows each kernel invocation to create
     * a separate instance.
     * 
     * @param string|null $id Option unique service identifier.
     *
     * @return bool Returns `true` to share object, `false` otherwise.
     */
    public static function shouldShareObject(?string $id = null): bool;

    /**
     * Retrieve a service instance from the kernel.
     *
     * The given identifier typically represents a service name or class name.
     * Implementations are responsible for resolving and returning the
     * corresponding instance.
     *
     * @param string $id The unique service identifier.
     * @param mixed ...$arguments Optional arguments to pass to the service constructor or resolver.
     *
     * @return mixed Returns the resolved service instance.
     *
     * @throws \Luminova\Exceptions\NotFoundException If the service is not defined.
     * @throws \Luminova\Exceptions\ClassException If an error occurs while resolving the service.
     */
    public function get(string $id, mixed ...$arguments): mixed;

    /**
     * Determine whether a service is defined in the kernel.
     *
     * This method only checks for the existence of the service identifier
     * and does not guarantee that the service can be successfully resolved.
     *
     * @param string $id The unique service identifier.
     *
     * @return bool Returns true if the service exists, false otherwise.
     */
    public function has(string $id): bool;

    /**
     * Resolves and return application routing system instance.
     *
     * Override this method if your application provides a custom router.
     * Returning null instructs the framework to use the default router.
     * 
     * @param Application $app The instance of application object to parse to routing system constructor.
     *
     * @return RouterInterface|null Returns the routing system instance, or null for the default.
     */
    public function getRoutingSystem(Application $app): ?RouterInterface;

    /**
     * Resolves and return the application instance.
     *
     * Override this method when using a custom Application class.
     * Returning null allows the framework to instantiate its default application.
     *
     * @return Application|null Returns the application instance, or null for the default.
     */
    public function getApplication(): ?Application;

    /**
     * Resolves and return application preferred logger instance.
     *
     * The returned logger must implement PSR's LoggerInterface.
     * Returning null instructs the framework to fall back to its default logger.
     *
     * @param mixed ...$arguments Optional arguments to pass to the logger constructor.
     *
     * @return LoggerInterface|null Returns the logger instance, or null for the default.
     * @example - Example:
     * ```php
     * return new MyLogger($config);
     * ```
     */
    public function getLogger(mixed ...$arguments): ?LoggerInterface;

    /**
     * Resolves and return mail client to use for the application.
     *
     * The mailer must implement `Psr\Mail\MailerInterface`.  
     * You may return either:
     * - an instance of the mailer,
     * - a class name implementing the mailer,
     * - or null to use the default mailer.
     * 
     * - You can use built-in clients like:  
     *   - `\Luminova\Components\Email\Clients\PHPMailer`  
     *   - `\Luminova\Components\Email\Clients\SwiftMailer`  
     * 
     * @param mixed ...$arguments Optional arguments to pass to the mailer constructor.
     *
     * @return MailerInterface|class-string<MailerInterface>|null Returns a mailer instance, 
     *      a mailer fully qualified name, 
     *      or null for the default mailer.
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
     * @example - Example:
     * ```php
     * return new MyMailer($config);
     * ```
     */
    public function getMailer(mixed ...$arguments): MailerInterface|string|null;

    /**
     * Resolves and return application HTTP client used for outbound requests.
     *
     * If `null` is returned, the default Luminova `Novio` client is used.
     * Custom clients must implement the PSR `ClientInterface`.
     *
     * **Luminova clients:**
     * - `\Luminova\Http\Client\Novio`   (default)
     * - `\Luminova\Http\Client\Guzzle`
     * 
     * @param mixed ...$arguments Optional arguments to pass to the HTTP client constructor.
     *
     * @return ClientInterface|string|null Returns a client instance, a client class name, or null to use the default.
     */
    public function getHttpClient(mixed ...$arguments): ClientInterface|string|null;

    /**
     * Resolves and returns a configured application cache driver object.
     *
     * The method acts as a factory for cache implementations and resolves the
     * appropriate driver implementation at runtime.
     *
     * The cache driver is determined in the following order:
     * - `database.orm.cache.driver`
     * - `database.caching.driver`
     * - `system.cache.persistent.id`
     * - `system.cache.driver` (fallback: filecache)
     *
     * Supported drivers:
     * - redis     → Redis-based cache storage
     * - memcached → Memcached-based cache storage
     * - filecache → Filesystem-based cache storage
     *
     * @param string $driver Cache driver name (currently overridden by env config).
     * @param string|null $storage Storage namespace or identifier (default: null).
     * @param string|null $persistentId Persistent connection identifier (default: null).
     *              Used as sub-directory for filecache cache driver if provided.
     * @param string $context Logical context for cache usage (e.g, `orm`, `global`, etc..).
     *
     * @return BaseCache|null Returns a cache driver instance or null if the driver is unsupported.
     */
    public function getCacheProvider(
        string $driver,
        ?string $storage = null,
        ?string $persistentId = null,
        string $context = 'global'
    ): ?BaseCache;

    /**
     * Creates or retrieves a Memcached client instance.
     *
     * This method initializes a Memcached connection with:
     * - optional persistent connection support
     * - optional post-creation callback hook
     * - optional connection identifier for grouping instances
     *
     * Default configuration applied:
     * - OPT_LIBKETAMA_COMPATIBLE enabled for consistent hashing
     * - optional key prefix loaded from environment
     * - default server: 127.0.0.1:11211
     *
     * @param string|null $persistentId Persistent connection ID for connection reuse.
     * @param callable|null $onNewObject Optional callback executed when a new instance is created.
     * @param string $identifier Logical connection identifier or context key.
     *
     * @return Memcached|null Returns a configured Memcached instance, or null if initialization fails.
     */
    public function getMemcached(
        ?string $persistentId = null, 
        ?callable $onNewObject = null, 
        string $identifier = ''
    ): ?Memcached;

    /**
     * Creates or retrieves a Redis client instance.
     *
     * This method initializes a Redis connection using the configured
     * connection settings and optionally supports persistent connections.
     *
     * Default configuration may include:
     * - host and port
     * - authentication credentials
     * - database selection
     * - key prefix
     * - connection and read timeouts
     *
     * @param string|null $persistentId Optional persistent connection identifier.
     * @param callable|null $onNewObject Optional callback executed after a new Redis instance is created.
     * @param string $identifier Optional connection identifier or context key.
     *
     * @return Redis|null Returns a configured Redis instance, or null if initialization fails.
     */
    public function getRedis(
        ?string $persistentId = null,
        ?callable $onNewObject = null,
        string $identifier = ''
    ): ?Redis;

    /**
     * Resolves and return session client for the application.
     *
     * The session client must implement `Luminova\Interface\SessionInterface`.  
     * You may return either:
     * - an instance of the session client,
     * - a class name implementing the session client,
     * - or null to use the default session client.
     * 
     * @param mixed ...$arguments Optional arguments to pass to the session client constructor.
     *
     * @return \Luminova\Sessions\Session|SessionInterface|class-string<SessionInterface>|null Returns:
     *      - a session instance, 
     *      - a session client fully qualified name, 
     *      - or null for the default session client.
     */
    public function getSessionClient(mixed ...$arguments): SessionInterface|string|null;
}